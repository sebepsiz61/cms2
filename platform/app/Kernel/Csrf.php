<?php
namespace Onay\App\Kernel;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }

        return $token;
    }

    public static function check(?string $candidate): bool
    {
        $token = Session::get('_csrf');

        return is_string($token) && is_string($candidate) && hash_equals($token, $candidate);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }
}
