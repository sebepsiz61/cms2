<?php
namespace Onay\Core\Dto;

final class Sms
{
    public function __construct(
        public readonly string $sender,
        public readonly string $text,
        public readonly ?string $code,
        public readonly \DateTimeImmutable $receivedAt,
    ) {
    }

    /**
     * Saglayici kodu ayri alanda vermediginde metinden cikarir.
     * 4-8 haneli ilk rakam dizisi dogrulama kodu kabul edilir.
     */
    public static function extractCode(string $text): ?string
    {
        return preg_match('/\b(\d{4,8})\b/', $text, $m) === 1 ? $m[1] : null;
    }
}
