<?php
namespace Onay\Core\Exception;

class ProviderException extends \RuntimeException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        public readonly ?string $rawResponse = null,
    ) {
        parent::__construct(sprintf('[%s] %s', $provider, $message));
    }
}
