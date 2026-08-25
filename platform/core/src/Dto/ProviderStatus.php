<?php
namespace Onay\Core\Dto;

/**
 * Saglayicilarin kendi durum kodlari buraya normallestirilir.
 * Siparis durum makinesi yalnizca bu degerleri gorur.
 */
enum ProviderStatus: string
{
    case Pending   = 'pending';    // numara alindi, SMS bekleniyor
    case Received  = 'received';   // en az bir SMS geldi
    case Finished  = 'finished';   // islem tamamlandi, ucret kesinlesti
    case Cancelled = 'cancelled';  // iptal edildi, iade beklenir
    case Expired   = 'expired';    // sure doldu, SMS gelmedi
    case Banned    = 'banned';     // numara kotu, saglayici degistirdi

    public function isTerminal(): bool
    {
        return $this === self::Finished
            || $this === self::Cancelled
            || $this === self::Expired
            || $this === self::Banned;
    }

    /** Musteriye para iadesi gereken bitis durumlari. */
    public function requiresRefund(): bool
    {
        return $this === self::Cancelled || $this === self::Expired || $this === self::Banned;
    }
}
