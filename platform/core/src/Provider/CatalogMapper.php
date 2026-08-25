<?php
namespace Onay\Core\Provider;

/**
 * Her saglayici ulkeyi ve servisi kendi koduyla adlandirir: 5sim "england" der,
 * SMS-Activate "16" der, biz "GB" deriz. Bu sinif iki yonlu cevirir.
 *
 * Eslesmeyen kodlar atilmaz, kaydedilir; yonetici panelinde "eslenmemis katalog"
 * listesi olarak gosterilir ki yeni ulke/servis eklendiginde fark edilsin.
 */
final class CatalogMapper
{
    /** @var array<string, array<string,string>> saglayici => [saglayiciKodu => kanonik] */
    private array $countries = [];
    /** @var array<string, array<string,string>> */
    private array $services = [];
    /** @var array<int, array{provider:string, kind:string, code:string}> */
    private array $unmapped = [];

    /**
     * @param array<string, array<string,string>> $countries
     * @param array<string, array<string,string>> $services
     */
    public function __construct(array $countries = [], array $services = [])
    {
        foreach ($countries as $provider => $map) {
            $this->countries[$provider] = array_change_key_case($map, CASE_LOWER);
        }
        foreach ($services as $provider => $map) {
            $this->services[$provider] = array_change_key_case($map, CASE_LOWER);
        }
    }

    public function toCanonicalCountry(string $provider, string $code): ?string
    {
        return $this->lookup($this->countries, $provider, 'country', $code);
    }

    public function toCanonicalService(string $provider, string $code): ?string
    {
        return $this->lookup($this->services, $provider, 'service', $code);
    }

    public function toProviderCountry(string $provider, string $canonical): ?string
    {
        return $this->reverse($this->countries, $provider, $canonical);
    }

    public function toProviderService(string $provider, string $canonical): ?string
    {
        return $this->reverse($this->services, $provider, $canonical);
    }

    /** @return array<int, array{provider:string, kind:string, code:string}> */
    public function unmapped(): array
    {
        return array_values($this->unmapped);
    }

    private function lookup(array $table, string $provider, string $kind, string $code): ?string
    {
        $key = strtolower($code);
        $canonical = $table[$provider][$key] ?? null;

        if ($canonical === null) {
            $signature = $provider . '|' . $kind . '|' . $key;
            $this->unmapped[$signature] = ['provider' => $provider, 'kind' => $kind, 'code' => $code];
        }

        return $canonical;
    }

    private function reverse(array $table, string $provider, string $canonical): ?string
    {
        $found = array_search($canonical, $table[$provider] ?? [], true);
        return $found === false ? null : (string) $found;
    }
}
