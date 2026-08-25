<?php
namespace Onay\Core\Dto;

/**
 * Saglayicinin ticari kurallari. Bu degerler saglayici dokumanindan okunup
 * yapilandirmaya yazilir; kod icinde sabitlenmez cunku saglayicilar degistirir.
 */
final class ProviderCapabilities
{
    public function __construct(
        public readonly string $currency,
        /** Saglayicinin iade penceresi: bu sureden sonra iptal edilemez. */
        public readonly int $cancelWindowSeconds,
        /** Iptal cagrisindan once beklenmesi gereken asgari sure. */
        public readonly int $minCancelDelaySeconds = 0,
        public readonly bool $supportsRental = false,
        public readonly bool $supportsStock = true,
    ) {
    }
}
