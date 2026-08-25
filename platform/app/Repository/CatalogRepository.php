<?php
namespace Onay\App\Repository;

use Onay\App\Kernel\Database;
use Onay\Core\Dto\Offer;

final class CatalogRepository
{
    /**
     * Katalog senkronu: saglayicidan gelen teklifleri yazar, o saglayicinin
     * artik donmedigi kayitlarin stogunu sifirlar (silmez; gecmis fiyat kalir).
     *
     * @param Offer[] $offers
     */
    public function sync(string $provider, array $offers): int
    {
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            $reset = $pdo->prepare('UPDATE provider_offers SET stock = 0 WHERE provider = ?');
            $reset->execute([$provider]);

            $upsert = $pdo->prepare(Database::upsert(
                'provider_offers',
                ['provider', 'country_code', 'service_code', 'operator', 'cost_minor', 'currency',
                 'stock', 'provider_country', 'provider_service', 'synced_at'],
                ['provider', 'country_code', 'service_code', 'operator'],
                ['cost_minor', 'currency', 'stock', 'provider_country', 'provider_service', 'synced_at']
            ));

            foreach ($offers as $offer) {
                $upsert->execute([
                    $offer->provider,
                    $offer->countryCode,
                    $offer->serviceCode,
                    $offer->operator ?? '',
                    $offer->costMinor,
                    $offer->currency,
                    $offer->stock,
                    $offer->providerCountry,
                    $offer->providerService,
                    $now,
                ]);
            }

            $pdo->commit();

            return count($offers);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return Offer[] Stokta olan tum teklifler. */
    public function inStock(?string $countryCode = null, ?string $serviceCode = null): array
    {
        $sql = 'SELECT * FROM provider_offers WHERE stock > 0';
        $params = [];

        if ($countryCode !== null) {
            $sql .= ' AND country_code = ?';
            $params[] = $countryCode;
        }
        if ($serviceCode !== null) {
            $sql .= ' AND service_code = ?';
            $params[] = $serviceCode;
        }

        $stmt = Database::pdo()->prepare($sql . ' ORDER BY cost_minor ASC');
        $stmt->execute($params);

        return array_map(
            static fn (array $row): Offer => new Offer(
                provider: $row['provider'],
                countryCode: $row['country_code'],
                serviceCode: $row['service_code'],
                costMinor: (int) $row['cost_minor'],
                currency: $row['currency'],
                stock: (int) $row['stock'],
                providerCountry: $row['provider_country'],
                providerService: $row['provider_service'],
                operator: $row['operator'] === '' ? null : $row['operator'],
            ),
            $stmt->fetchAll()
        );
    }

    /** @return array<string, array<string,string>> CatalogMapper'in bekledigi bicim. */
    public function codeMap(string $kind): array
    {
        $stmt = Database::pdo()->prepare('SELECT provider, provider_code, canonical_code FROM provider_codes WHERE kind = ?');
        $stmt->execute([$kind]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['provider']][$row['provider_code']] = $row['canonical_code'];
        }

        return $map;
    }

    /** @param array<int, array{provider:string, kind:string, code:string}> $codes */
    public function recordUnmapped(array $codes): void
    {
        if ($codes === []) {
            return;
        }

        $stmt = Database::pdo()->prepare(Database::upsert(
            'unmapped_codes',
            ['provider', 'kind', 'provider_code', 'seen_at'],
            ['provider', 'kind', 'provider_code'],
            ['seen_at']
        ));

        foreach ($codes as $code) {
            $stmt->execute([$code['provider'], $code['kind'], $code['code'], date('Y-m-d H:i:s')]);
        }
    }

    /** @return array<int, array<string,mixed>> */
    public function unmapped(): array
    {
        return Database::pdo()->query('SELECT * FROM unmapped_codes ORDER BY seen_at DESC')->fetchAll();
    }

    public function map(string $provider, string $kind, string $providerCode, string $canonicalCode): void
    {
        $stmt = Database::pdo()->prepare(Database::upsert(
            'provider_codes',
            ['provider', 'kind', 'provider_code', 'canonical_code'],
            ['provider', 'kind', 'provider_code'],
            ['canonical_code']
        ));
        $stmt->execute([$provider, $kind, $providerCode, $canonicalCode]);

        $delete = Database::pdo()->prepare('DELETE FROM unmapped_codes WHERE provider = ? AND kind = ? AND provider_code = ?');
        $delete->execute([$provider, $kind, $providerCode]);
    }

    /** @return array<int, array<string,mixed>> Musteriye gosterilecek ulke listesi. */
    public function availableCountries(): array
    {
        return Database::pdo()->query(
            'SELECT c.code, c.name, COUNT(o.id) AS teklif
             FROM countries c
             JOIN provider_offers o ON o.country_code = c.code AND o.stock > 0
             WHERE c.is_active = 1
             GROUP BY c.code, c.name
             ORDER BY c.name'
        )->fetchAll();
    }

    /** @return array<int, array<string,mixed>> */
    public function availableServices(string $countryCode): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.code, s.name, MIN(o.cost_minor) AS min_cost, o.currency, SUM(o.stock) AS stok
             FROM services s
             JOIN provider_offers o ON o.service_code = s.code AND o.stock > 0
             WHERE s.is_active = 1 AND o.country_code = ?
             GROUP BY s.code, s.name, o.currency
             ORDER BY s.name'
        );
        $stmt->execute([$countryCode]);

        return $stmt->fetchAll();
    }
}
