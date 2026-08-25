<?php
namespace Onay\Core\Exception;

class InsufficientBalanceException extends \RuntimeException
{
    public function __construct(public readonly int $userId, public readonly int $requestedMinor, public readonly int $availableMinor)
    {
        parent::__construct(sprintf(
            'Yetersiz bakiye: kullanici %d, istenen %d, mevcut %d (minor birim).',
            $userId,
            $requestedMinor,
            $availableMinor
        ));
    }
}
