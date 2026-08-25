<?php
namespace Onay\Core\Dto;

final class PurchaseResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerOrderId,
        public readonly string $phone,
        public readonly int $costMinor,
        public readonly string $currency,
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }
}
