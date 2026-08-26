<?php
use Onay\App\Kernel\Money;

use Onay\App\Kernel\Request;

/**
 * Uygulama ici adres uretir. Alt klasor kurulumunda basa taban yolunu ekler;
 * kok kurulumda hicbir sey degismez.
 */
function url(string $yol = '/'): string
{
    // Bos dize yalnizca on ek istendigi anlamina gelir; basina egik cizgi
    // eklenirse cagiran taraf kendi yolunu ekledinde "//" olusur ve tarayici
    // bunu protokol-goreli adres sayar (http://sayfa/... gibi).
    if ($yol === '') {
        return Request::$basePath;
    }

    if ($yol[0] !== '/') {
        $yol = '/' . $yol;
    }

    return Request::$basePath . $yol;
}

/** Varlik adresi. index.php on eki almaz; dosyalar dogrudan sunulur. */
function asset(string $yol): string
{
    if ($yol === '' || $yol[0] !== '/') {
        $yol = '/' . $yol;
    }

    return Request::$assetPath . $yol;
}

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
