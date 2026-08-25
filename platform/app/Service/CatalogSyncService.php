<?php
namespace Onay\App\Service;

use Onay\App\Repository\CatalogRepository;
use Onay\Core\Exception\ProviderException;

/**
 * Cron'un calistirdigi katalog senkronu: her saglayicinin ulke/servis/fiyat/stok
 * verisini ceker, kanonik kodlara cevirip yazar. Bir saglayici cevap vermezse
 * digerleri yine islenir.
 */
final class CatalogSyncService
{
    public function __construct(
        private readonly ProviderFactory $factory,
        private readonly CatalogRepository $catalog = new CatalogRepository(),
    ) {
    }

    /** @return array<string, int|string> saglayici => yazilan teklif sayisi ya da hata */
    public function sync(): array
    {
        $report = [];

        foreach ($this->factory->registry()->enabled() as $provider) {
            try {
                $offers = $provider->catalog();
                $report[$provider->name()] = $this->catalog->sync($provider->name(), $offers);
            } catch (ProviderException $e) {
                $report[$provider->name()] = 'hata: ' . $e->getMessage();
                Logger::error('Katalog senkronu basarisiz', ['provider' => $provider->name(), 'hata' => $e->getMessage()]);
            }
        }

        // Eslenmemis kodlar yonetici paneline dusurulur ki yeni ulke/servis kacmasin.
        $this->catalog->recordUnmapped($this->factory->mapper()->unmapped());

        return $report;
    }
}
