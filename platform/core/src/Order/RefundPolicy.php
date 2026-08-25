<?php
namespace Onay\Core\Order;

use Onay\Core\Dto\ProviderCapabilities;

/**
 * Sistemin en pahali hatasini engelleyen kural.
 *
 * Musteriye verdigimiz iade suresi, saglayicinin iptal penceresinden KISA olmali.
 * Ters kurulursa musteriye iade edilir ama saglayicidan tahsil edilemez ve fark
 * dogrudan zarar yazilir. Guvenlik payi, yoklama gecikmesi ve saglayiciya giden
 * iptal cagrisinin suresi icindir.
 */
final class RefundPolicy
{
    public function __construct(
        private readonly int $safetyMarginSeconds = 300,
        private readonly int $minimumCustomerWindowSeconds = 180,
    ) {
    }

    public function customerTimeoutSeconds(ProviderCapabilities $capabilities): int
    {
        $window = $capabilities->cancelWindowSeconds - $this->safetyMarginSeconds;
        $floor  = max($this->minimumCustomerWindowSeconds, $capabilities->minCancelDelaySeconds + 60);

        if ($window < $floor) {
            throw new \LogicException(sprintf(
                'Saglayicinin iptal penceresi (%d sn) guvenli bir musteri suresi birakmiyor; '
                . 'bu saglayici ile calisilmamali ya da guvenlik payi dusurulmeli.',
                $capabilities->cancelWindowSeconds
            ));
        }

        return $window;
    }

    /** Iptal cagrisi saglayicinin izin verdigi pencerede mi? */
    public function canCancelAt(ProviderCapabilities $capabilities, \DateTimeImmutable $purchasedAt, \DateTimeImmutable $now): bool
    {
        $elapsed = $now->getTimestamp() - $purchasedAt->getTimestamp();

        return $elapsed >= $capabilities->minCancelDelaySeconds
            && $elapsed <= $capabilities->cancelWindowSeconds;
    }
}
