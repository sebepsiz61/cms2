<?php
namespace Onay\Core\Dto;

/**
 * Tek bir saglayicinin, tek bir ulke+servis icin verdigi anlik teklif.
 *
 * $countryCode ve $serviceCode her zaman bizim kanonik kodlarimizdir; saglayicinin
 * kendi kodlari $providerCountry / $providerService alanlarinda saklanir cunku
 * satin alma cagrisi onlarla yapilir.
 */
final class Offer
{
    public function __construct(
        public readonly string $provider,
        public readonly string $countryCode,
        public readonly string $serviceCode,
        public readonly int $costMinor,
        public readonly string $currency,
        public readonly int $stock,
        public readonly string $providerCountry,
        public readonly string $providerService,
        public readonly ?string $operator = null,
    ) {
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    public function key(): string
    {
        return $this->provider . '|' . $this->countryCode . '|' . $this->serviceCode
            . '|' . ($this->operator ?? 'any');
    }
}
