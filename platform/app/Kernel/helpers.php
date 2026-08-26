<?php
use Onay\App\Kernel\Money;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function para(int $minor): string
{
    return Money::format($minor);
}

/**
 * Durum kodlarini musteriye gosterilecek metne cevirir. Veritabaninda kod,
 * ekranda Turkce; ikisi karismasin diye tek yerde tutulur.
 */
function durumAdi(string $kod): string
{
    return [
        'waiting_sms' => 'SMS bekleniyor',
        'received'    => 'SMS geldi',
        'completed'   => 'Tamamlandi',
        'cancelled'   => 'Iptal edildi',
        'expired'     => 'Sure doldu, iade edildi',
        'refunded'    => 'Iade edildi',
        'pending'     => 'Onay bekliyor',
        'approved'    => 'Onaylandi',
        'rejected'    => 'Reddedildi',
    ][$kod] ?? $kod;
}

function eskiDeger(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}
