<?php
namespace Onay\Core\Provider;

use Onay\Core\Contract\HttpClientInterface;
use Onay\Core\Contract\NumberProviderInterface;
use Onay\Core\Dto\Offer;
use Onay\Core\Dto\PollResult;
use Onay\Core\Dto\ProviderCapabilities;
use Onay\Core\Dto\ProviderStatus;
use Onay\Core\Dto\PurchaseResult;
use Onay\Core\Dto\Sms;
use Onay\Core\Exception\OutOfStockException;
use Onay\Core\Exception\ProviderBalanceException;
use Onay\Core\Exception\ProviderException;

/**
 * Tek uc adresli, action parametreli, duz metin yanit veren saglayici ailesi
 * (SMS-Activate ve onun protokolunu taklit eden servisler).
 *
 * Yanitlar JSON degil "ACCESS_NUMBER:123:4470..." bicimindedir; ayristirma burada
 * kapali kalir, sistemin geri kalani farki gormez.
 */
final class SmsActivateProvider implements NumberProviderInterface
{
    private const STATUS_CANCEL = '8';
    private const STATUS_FINISH = '6';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CatalogMapper $mapper,
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://sms-activate.io/stubs/handler_api.php',
        private readonly string $providerName = 'sms-activate',
        private readonly ProviderCapabilities $capabilities = new ProviderCapabilities(
            currency: 'RUB',
            cancelWindowSeconds: 1200,
            minCancelDelaySeconds: 120,
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
        $body = $this->call(['action' => 'getBalance']);

        if (!str_starts_with($body, 'ACCESS_BALANCE:')) {
            throw new ProviderException($this->providerName, 'Bakiye okunamadi.', $body);
        }

        return (int) round(((float) substr($body, strlen('ACCESS_BALANCE:'))) * 100);
    }

    public function catalog(): array
    {
        $data = $this->json(['action' => 'getPrices']);
        $offers = [];

        // Yapi: {ulkeKodu: {servisKodu: {cost, count}}}
        foreach ($data as $country => $services) {
            if (!is_array($services)) {
                continue;
            }
            $canonicalCountry = $this->mapper->toCanonicalCountry($this->providerName, (string) $country);
            if ($canonicalCountry === null) {
                continue;
            }

            foreach ($services as $service => $info) {
                if (!is_array($info) || !isset($info['cost'])) {
                    continue;
                }
                $canonicalService = $this->mapper->toCanonicalService($this->providerName, (string) $service);
                if ($canonicalService === null) {
                    continue;
                }

                $offers[] = new Offer(
                    provider: $this->providerName,
                    countryCode: $canonicalCountry,
                    serviceCode: $canonicalService,
                    costMinor: (int) round(((float) $info['cost']) * 100),
                    currency: $this->capabilities->currency,
                    stock: (int) ($info['count'] ?? 0),
                    providerCountry: (string) $country,
                    providerService: (string) $service,
                );
            }
        }

        return $offers;
    }

    public function buy(Offer $offer): PurchaseResult
    {
        $body = $this->call([
            'action'  => 'getNumber',
            'service' => $offer->providerService,
            'country' => $offer->providerCountry,
        ]);

        if (!str_starts_with($body, 'ACCESS_NUMBER:')) {
            throw new ProviderException($this->providerName, 'Beklenmeyen satin alma yaniti.', $body);
        }

        $parts = explode(':', $body);
        if (count($parts) < 3) {
            throw new ProviderException($this->providerName, 'Satin alma yaniti eksik.', $body);
        }

        return new PurchaseResult(
            provider: $this->providerName,
            providerOrderId: $parts[1],
            phone: $parts[2],
            costMinor: $offer->costMinor,
            currency: $this->capabilities->currency,
            expiresAt: (new \DateTimeImmutable())->modify('+' . $this->capabilities->cancelWindowSeconds . ' seconds'),
        );
    }

    public function poll(string $providerOrderId): PollResult
    {
        $body = $this->call(['action' => 'getStatus', 'id' => $providerOrderId]);

        if (str_starts_with($body, 'STATUS_OK')) {
            $text = substr($body, strlen('STATUS_OK:'));
            $sms = new Sms(
                sender: '',
                text: $text,
                code: Sms::extractCode($text) ?? $text,
                receivedAt: new \DateTimeImmutable(),
            );
            return new PollResult(ProviderStatus::Received, [$sms]);
        }

        return new PollResult(match (true) {
            str_starts_with($body, 'STATUS_WAIT_CODE'),
            str_starts_with($body, 'STATUS_WAIT_RETRY')  => ProviderStatus::Pending,
            str_starts_with($body, 'STATUS_CANCEL')      => ProviderStatus::Cancelled,
            default                                      => throw new ProviderException(
                $this->providerName,
                'Bilinmeyen durum yaniti.',
                $body
            ),
        });
    }

    public function cancel(string $providerOrderId): void
    {
        $this->setStatus($providerOrderId, self::STATUS_CANCEL);
    }

    public function finish(string $providerOrderId): void
    {
        $this->setStatus($providerOrderId, self::STATUS_FINISH);
    }

    private function setStatus(string $providerOrderId, string $status): void
    {
        $body = $this->call(['action' => 'setStatus', 'id' => $providerOrderId, 'status' => $status]);

        if (!str_starts_with($body, 'ACCESS_')) {
            throw new ProviderException($this->providerName, 'Durum degistirilemedi.', $body);
        }
    }

    /** @param array<string,string> $params */
    private function call(array $params): string
    {
        $response = $this->http->get($this->baseUrl, $params + ['api_key' => $this->apiKey]);
        $body = trim($response['body']);

        // Bu protokolde hatalar da HTTP 200 ile duz metin olarak doner.
        return match ($body) {
            'NO_NUMBERS'    => throw new OutOfStockException($this->providerName, 'Stokta numara yok.', $body),
            'NO_BALANCE'    => throw new ProviderBalanceException($this->providerName, 'Saglayicidaki bakiyemiz yetersiz.', $body),
            'BAD_KEY'       => throw new ProviderException($this->providerName, 'API anahtari gecersiz.', $body),
            'ERROR_SQL',
            'BAD_ACTION',
            'BAD_SERVICE'   => throw new ProviderException($this->providerName, 'Saglayici hatasi: ' . $body, $body),
            default         => $body,
        };
    }

    /** @param array<string,string> $params @return array<mixed> */
    private function json(array $params): array
    {
        $data = json_decode($this->call($params), true);

        if (!is_array($data)) {
            throw new ProviderException($this->providerName, 'Gecersiz JSON yaniti.');
        }

        return $data;
    }
}
