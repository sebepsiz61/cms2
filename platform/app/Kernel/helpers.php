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

function eskiDeger(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}
