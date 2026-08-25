<?php
namespace Onay\App\Kernel;

/**
 * Para her yerde tam sayi kurustur. Ondalik sayi yalnizca ekrana basarken olusur.
 */
final class Money
{
    public static function format(int $minor, bool $withSymbol = true): string
    {
        $text = number_format($minor / 100, 2, ',', '.');

        return $withSymbol ? $text . ' ' . Config::get('currency.symbol', '₺') : $text;
    }

    /** Kullanicinin girdigi "125,50" ya da "125.50" metnini kurusa cevirir. */
    public static function parse(string $input): ?int
    {
        $normalized = str_replace([' ', '.'], ['', ''], $input);
        $normalized = str_replace(',', '.', $normalized);

        if (!is_numeric($normalized)) {
            return null;
        }

        $minor = (int) round(((float) $normalized) * 100);

        return $minor > 0 ? $minor : null;
    }
}
