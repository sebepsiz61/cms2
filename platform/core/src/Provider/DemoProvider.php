<?php
namespace Onay\Core\Provider;

use Onay\Core\Contract\NumberProviderInterface;
use Onay\Core\Dto\Offer;
use Onay\Core\Dto\PollResult;
use Onay\Core\Dto\ProviderCapabilities;
use Onay\Core\Dto\ProviderStatus;
use Onay\Core\Dto\PurchaseResult;
use Onay\Core\Dto\Sms;
use Onay\Core\Exception\OutOfStockException;

/**
 * Disari hic cikmayan sahte saglayici.
 *
 * Gercek bir API anahtari hazir olmadan sistemin geri kalanini calistirabilmek
 * icin vardir: katalog, satin alma, SMS bekleme, iade ve yonetim ekranlari
 * gercek para ve gercek numara olmadan uctan uca denenir.
 *
 * Durumu hicbir yerde saklamaz; siparis kimligine satin alma zamanini gomer ve
 * SMS'in "geldigi" ani oradan hesaplar. Boylece yoklama ekrani gercekte oldugu
 * gibi once bekler, sonra kodu gosterir.
 *
 * !! Gercek musterilere acik bir sitede etkin birakilmamalidir: sattigi numara
 * !! yoktur. Uygulama, etkinken her sayfada uyari gosterir.
 */
final class DemoProvider implements NumberProviderInterface
{
    /** @var array<int, array{country:string, service:string, cost:float, stock:int}> */
    private const KATALOG = [
        ['country' => 'GB', 'service' => 'whatsapp',  'cost' => 12.50, 'stock' => 48],
        ['country' => 'GB', 'service' => 'telegram',  'cost' => 9.00,  'stock' => 120],
        ['country' => 'GB', 'service' => 'instagram', 'cost' => 14.00, 'stock' => 31],
        ['country' => 'US', 'service' => 'whatsapp',  'cost' => 21.00, 'stock' => 16],
        ['country' => 'US', 'service' => 'telegram',  'cost' => 17.50, 'stock' => 64],
        ['country' => 'TR', 'service' => 'whatsapp',  'cost' => 26.00, 'stock' => 5],
        ['country' => 'TR', 'service' => 'telegram',  'cost' => 19.00, 'stock' => 22],
        ['country' => 'DE', 'service' => 'instagram', 'cost' => 15.50, 'stock' => 40],
    ];

    public function __construct(
        private readonly string $providerName = 'demo',
        /** SMS'in kac saniye sonra "gelecegi". */
        private readonly int $smsGecikmesi = 15,
        /** Bu servis icin SMS hic gelmez; iade akisini denemek icindir. */
        private readonly string $sessizServis = 'instagram',
        private readonly ProviderCapabilities $capabilities = new ProviderCapabilities(
            currency: 'TRY',
            cancelWindowSeconds: 900,
            minCancelDelaySeconds: 0,
        ),
    ) {
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function capabilities(): ProviderCapabilities
    {
        return $this->capabilities;
    }

    public function balanceMinor(): int
    {
        return 100000;   // 1.000,00 — tukenmez
    }

    public function catalog(): array
    {
        $offers = [];

        foreach (self::KATALOG as $satir) {
            $offers[] = new Offer(
                provider: $this->providerName,
                countryCode: $satir['country'],
                serviceCode: $satir['service'],
                costMinor: (int) round($satir['cost'] * 100),
                currency: $this->capabilities->currency,
                stock: $satir['stock'],
                // Demo saglayici kendi kod duzeni kullanmaz; eslemeye gerek kalmaz.
                providerCountry: $satir['country'],
                providerService: $satir['service'],
            );
        }

        return $offers;
    }

    public function buy(Offer $offer): PurchaseResult
    {
        if (!$offer->inStock()) {
            throw new OutOfStockException($this->providerName, 'Demo katalogda stok yok.');
        }

        $simdi = time();

        return new PurchaseResult(
            provider: $this->providerName,
            // Satin alma zamani ve servis kimlige gomulur; poll() durumu buradan cikarir.
            providerOrderId: sprintf('demo-%d-%s-%s', $simdi, $offer->serviceCode, bin2hex(random_bytes(3))),
            phone: $this->numaraUret($offer->countryCode),
            costMinor: $offer->costMinor,
            currency: $this->capabilities->currency,
            expiresAt: (new \DateTimeImmutable())->modify('+' . $this->capabilities->cancelWindowSeconds . ' seconds'),
        );
    }

    public function poll(string $providerOrderId): PollResult
    {
        [$alinmaZamani, $servis] = $this->kimligiCoz($providerOrderId);

        if ($servis === $this->sessizServis) {
            return new PollResult(ProviderStatus::Pending);   // bu servise SMS hic gelmez
        }

        if (time() - $alinmaZamani < $this->smsGecikmesi) {
            return new PollResult(ProviderStatus::Pending);
        }

        $kod = $this->koduUret($providerOrderId);

        return new PollResult(ProviderStatus::Received, [
            new Sms(
                sender: strtoupper($servis),
                text: $kod . ' dogrulama kodunuz. (DEMO — gercek bir SMS degildir)',
                code: $kod,
                receivedAt: (new \DateTimeImmutable())->setTimestamp($alinmaZamani + $this->smsGecikmesi),
            ),
        ]);
    }

    public function cancel(string $providerOrderId): void
    {
        // Demo saglayicida iptal edilecek bir sey yok.
    }

    public function finish(string $providerOrderId): void
    {
        // Demo saglayicida tamamlanacak bir sey yok.
    }

    /** @return array{0:int, 1:string} */
    private function kimligiCoz(string $providerOrderId): array
    {
        $parca = explode('-', $providerOrderId);

        return [
            isset($parca[1]) && ctype_digit($parca[1]) ? (int) $parca[1] : 0,
            $parca[2] ?? '',
        ];
    }

    /** Gercek numaralarla karismasin diye ayrilmis test bloklarini kullanir. */
    private function numaraUret(string $countryCode): string
    {
        $onEk = match ($countryCode) {
            'GB'    => '+4479460018',
            'US'    => '+1555015501',
            'TR'    => '+9053200000',
            'DE'    => '+4915100000',
            default => '+9999000000',
        };

        return $onEk . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    }

    /** Ayni siparis icin her yoklamada ayni kod dondurulur. */
    private function koduUret(string $providerOrderId): string
    {
        return substr((string) (crc32($providerOrderId) % 900000 + 100000), 0, 6);
    }
}
