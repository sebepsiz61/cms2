<?php
namespace Onay\Core\Provider;

use Onay\Core\Dto\Offer;
use Onay\Core\Dto\PurchaseResult;
use Onay\Core\Exception\OutOfStockException;
use Onay\Core\Exception\ProviderBalanceException;
use Onay\Core\Exception\ProviderException;

/**
 * Birden fazla firmayi tek bir arayuz gibi gosterir.
 *
 * Iki secim kipi vardir:
 *   - CHEAPEST: ayni ulke+servis icin en ucuz stoklu teklifi secer (marj icin)
 *   - PRIORITY: kayittaki oncelik sirasini izler (guvenilirlik icin)
 *
 * Satin alma sirasinda bir firma stok vermezse ya da hata dondururse siradaki
 * firmaya gecilir; musteri bunu hic gormez. Saglayicidaki kendi bakiyemizin
 * bitmesi failover'a takilmaz, disari tasinir: bu bir stok sorunu degil, bizim
 * kapatmamiz gereken bir aksakliktir.
 */
final class ProviderManager
{
    public const SELECT_CHEAPEST = 'cheapest';
    public const SELECT_PRIORITY = 'priority';

    /** @var array<int, array{provider:string, error:string}> */
    private array $lastFailures = [];

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly string $selection = self::SELECT_CHEAPEST,
    ) {
    }

    /**
     * Tum etkin saglayicilarin kataloglarini tek listede toplar.
     * Bir saglayici cevap veremezse digerleri yine de doner.
     *
     * @return Offer[]
     */
    public function catalog(): array
    {
        $offers = [];
        $this->lastFailures = [];

        foreach ($this->registry->enabled() as $provider) {
            try {
                foreach ($provider->catalog() as $offer) {
                    $offers[] = $offer;
                }
            } catch (ProviderException $e) {
                $this->lastFailures[] = ['provider' => $provider->name(), 'error' => $e->getMessage()];
            }
        }

        return $offers;
    }

    /**
     * Verilen ulke+servis icin uygun teklifleri secim kipine gore siralar.
     *
     * @param  Offer[] $offers
     * @return Offer[]
     */
    public function rank(array $offers, string $countryCode, string $serviceCode): array
    {
        $matching = array_values(array_filter(
            $offers,
            static fn (Offer $o): bool => $o->countryCode === $countryCode
                && $o->serviceCode === $serviceCode
                && $o->inStock()
        ));

        if ($this->selection === self::SELECT_CHEAPEST) {
            usort($matching, static fn (Offer $a, Offer $b): int => $a->costMinor <=> $b->costMinor);

            return $matching;
        }

        $order = [];
        foreach ($this->registry->enabled() as $index => $provider) {
            $order[$provider->name()] = $index;
        }
        usort(
            $matching,
            static fn (Offer $a, Offer $b): int => ($order[$a->provider] ?? PHP_INT_MAX) <=> ($order[$b->provider] ?? PHP_INT_MAX)
        );

        return $matching;
    }

    /**
     * Siralanmis teklifleri sirayla dener; ilk basarili satin almayi doner.
     *
     * @param Offer[] $rankedOffers
     */
    public function buyWithFailover(array $rankedOffers): PurchaseResult
    {
        $this->lastFailures = [];

        if ($rankedOffers === []) {
            throw new OutOfStockException('*', 'Hicbir saglayicida stok yok.');
        }

        foreach ($rankedOffers as $offer) {
            try {
                return $this->registry->get($offer->provider)->buy($offer);
            } catch (ProviderBalanceException $e) {
                // Bizim bakiye sorunumuz: saglayiciyi devre disi birak ama sessizce yutma.
                $this->registry->disable($offer->provider);
                $this->lastFailures[] = ['provider' => $offer->provider, 'error' => $e->getMessage()];
            } catch (ProviderException $e) {
                $this->lastFailures[] = ['provider' => $offer->provider, 'error' => $e->getMessage()];
            }
        }

        throw new OutOfStockException(
            '*',
            'Tum saglayicilar basarisiz: ' . json_encode($this->lastFailures, JSON_UNESCAPED_UNICODE)
        );
    }

    /** @return array<int, array{provider:string, error:string}> */
    public function lastFailures(): array
    {
        return $this->lastFailures;
    }
}
