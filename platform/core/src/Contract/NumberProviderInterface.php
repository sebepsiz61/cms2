<?php
namespace Onay\Core\Contract;

use Onay\Core\Dto\Offer;
use Onay\Core\Dto\PollResult;
use Onay\Core\Dto\ProviderCapabilities;
use Onay\Core\Dto\PurchaseResult;

/**
 * Her toptanci firma icin bir uygulama yazilir. Sistemin geri kalani yalnizca bu
 * arayuzu bilir; firma degistiginde ya da ikinci firma eklendiginde baska hicbir
 * yer degismez.
 */
interface NumberProviderInterface
{
    public function name(): string;

    public function capabilities(): ProviderCapabilities;

    /** Bizim saglayicidaki bakiyemiz (saglayici para biriminin minor birimi). */
    public function balanceMinor(): int;

    /** @return Offer[] */
    public function catalog(): array;

    public function buy(Offer $offer): PurchaseResult;

    public function poll(string $providerOrderId): PollResult;

    public function cancel(string $providerOrderId): void;

    public function finish(string $providerOrderId): void;
}
