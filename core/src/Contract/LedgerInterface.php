<?php
namespace Onay\Core\Contract;

/**
 * Cuzdan defteri. Bakiye tek bir kolonda tutulmaz; her hareket yazilir ve bakiye
 * bunlarin toplamidir. Her cagri idempotency anahtari ister: ayni anahtarla ikinci
 * cagri yeni hareket uretmez, mevcut hareketin id'sini doner.
 */
interface LedgerInterface
{
    public const TYPE_LOAD   = 'load';
    public const TYPE_SPEND  = 'spend';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUST = 'adjust';

    public function balanceMinor(int $userId): int;

    public function credit(int $userId, int $amountMinor, string $type, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null): int;

    public function debit(int $userId, int $amountMinor, string $type, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null): int;
}
