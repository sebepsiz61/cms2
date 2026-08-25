<?php
namespace Onay\Core\Dto;

final class PollResult
{
    /** @param Sms[] $messages */
    public function __construct(
        public readonly ProviderStatus $status,
        public readonly array $messages = [],
    ) {
    }

    public function firstCode(): ?string
    {
        foreach ($this->messages as $sms) {
            if ($sms->code !== null) {
                return $sms->code;
            }
        }
        return null;
    }
}
