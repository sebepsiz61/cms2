<?php
namespace Onay\App\Service;

use Onay\App\Kernel\Config;
use Onay\App\Repository\CatalogRepository;
use Onay\App\Repository\OrderRepository;
use Onay\Core\Contract\LedgerInterface;
use Onay\Core\Dto\ProviderStatus;
use Onay\Core\Exception\InsufficientBalanceException;
use Onay\Core\Exception\OutOfStockException;
use Onay\Core\Order\RefundPolicy;
use Onay\Core\Provider\ProviderRegistry;

/**
 * Siparisin tum yasam dongusu. Para hareketleri her zaman defter uzerinden ve
 * her zaman idempotency anahtariyla yapilir; boylece yeniden denenen bir istek
 * ya da cron'un iki kez calismasi ikinci kez ucret kesmez veya iade etmez.
 *
 * Durumlar:  waiting_sms -> received -> completed
 *                        \-> cancelled | expired  (iade edilir)
 */
final class OrderService
{
    public function __construct(
        private readonly LedgerInterface $ledger,
        private readonly ProviderFactory $factory,
        private readonly PricingService $pricing = new PricingService(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly CatalogRepository $catalog = new CatalogRepository(),
        private readonly RefundPolicy $refundPolicy = new RefundPolicy(),
    ) {
    }

    /**
     * Numara satin alir. Sira onemlidir: once para dusulur, sonra numara alinir.
     * Ters sirada, bakiyesi yetmeyen bir kullanici icin saglayicidan numara
     * alinmis ve para odenmis olurdu.
     */
    public function purchase(int $userId, string $countryCode, string $serviceCode): array
    {
        $registry = $this->factory->registry();
        $manager  = $this->factory->manager($registry);

        $offers = $this->catalog->inStock($countryCode, $serviceCode);
        $ranked = $manager->rank($offers, $countryCode, $serviceCode);

        if ($ranked === []) {
            throw new OutOfStockException('*', 'Bu ulke ve servis icin su an numara yok.');
        }

        $priceMinor = $this->pricing->sellPriceMinor(
            $ranked[0]->costMinor,
            $ranked[0]->currency,
            $countryCode,
            $serviceCode
        );

        // Bakiye yetmiyorsa saglayiciya hic gitmeyiz.
        if ($this->ledger->balanceMinor($userId) < $priceMinor) {
            throw new InsufficientBalanceException($userId, $priceMinor, $this->ledger->balanceMinor($userId));
        }

        $purchase = $manager->buyWithFailover($ranked);

        // Failover baska saglayiciya dustuyse fiyat o teklife gore yeniden hesaplanir.
        foreach ($ranked as $offer) {
            if ($offer->provider === $purchase->provider) {
                $priceMinor = $this->pricing->sellPriceMinor($offer->costMinor, $offer->currency, $countryCode, $serviceCode);
                break;
            }
        }

        $capabilities = $registry->get($purchase->provider)->capabilities();
        $timeout = $this->refundPolicy->customerTimeoutSeconds($capabilities);
        $expiresAt = (new \DateTimeImmutable())->modify('+' . $timeout . ' seconds');

        $orderId = $this->orders->create($userId, $purchase, $countryCode, $serviceCode, $priceMinor, $expiresAt);

        try {
            $this->ledger->debit(
                $userId,
                $priceMinor,
                LedgerInterface::TYPE_SPEND,
                'order-' . $orderId . '-spend',
                'number_order',
                $orderId
            );
        } catch (\Throwable $e) {
            // Para dusulemedi: numarayi saglayiciya geri birak, siparisi kapat.
            $this->safeCancelAtProvider($registry, $purchase->provider, $purchase->providerOrderId);
            $this->orders->setStatus($orderId, 'cancelled');
            throw $e;
        }

        return $this->orders->find($orderId) ?? [];
    }

    /**
     * Saglayiciya sorar, gelen SMS'i kaydeder. Ayni siparis icin cok sik
     * sorulmaz; saglayicilar hiz siniri uygular.
     */
    public function poll(array $order, bool $force = false): array
    {
        if (!in_array($order['status'], ['waiting_sms', 'received'], true)) {
            return $order;
        }

        if (!$force && !$this->pollDue($order)) {
            return $order;
        }

        $registry = $this->factory->registry();
        $result = $registry->get($order['provider'])->poll($order['provider_order_id']);
        $this->orders->markPolled((int) $order['id']);

        foreach ($result->messages as $sms) {
            $this->orders->addMessage((int) $order['id'], $sms);
        }

        $code = $result->firstCode();

        if ($result->status === ProviderStatus::Received || $code !== null) {
            $this->orders->setStatus((int) $order['id'], 'received', $code);
        } elseif ($result->status->requiresRefund()) {
            $this->refund((int) $order['id'], $result->status === ProviderStatus::Expired ? 'expired' : 'cancelled');
        }

        return $this->orders->find((int) $order['id']) ?? $order;
    }

    /**
     * Musteri kodu aldi ve isi bitti. Ucret kesinlesir, iade yapilmaz.
     */
    public function complete(array $order): void
    {
        if ($order['status'] !== 'received') {
            throw new \RuntimeException('Yalnizca SMS gelmis siparisler tamamlanabilir.');
        }

        $registry = $this->factory->registry();

        try {
            $registry->get($order['provider'])->finish($order['provider_order_id']);
        } catch (\Throwable $e) {
            // Saglayici tarafindaki kapanis basarisiz olsa da musteri kodunu aldi;
            // siparisi tamamlanmis sayariz, aksaklik kayda gecer.
            Logger::warn('Saglayici finish cagrisi basarisiz', ['order' => $order['id'], 'hata' => $e->getMessage()]);
        }

        $this->orders->setStatus((int) $order['id'], 'completed');
    }

    /**
     * Iptal ve iade. Idempotency anahtari siparise bagli oldugu icin cron ile
     * kullanici ayni anda iptal etse bile iade tek kez yapilir.
     */
    public function refund(int $orderId, string $finalStatus = 'cancelled'): void
    {
        $order = $this->orders->find($orderId);

        if ($order === null || in_array($order['status'], ['completed', 'cancelled', 'expired'], true)) {
            return;
        }

        $registry = $this->factory->registry();
        $this->safeCancelAtProvider($registry, $order['provider'], $order['provider_order_id']);

        $this->ledger->credit(
            (int) $order['user_id'],
            (int) $order['price_minor'],
            LedgerInterface::TYPE_REFUND,
            'order-' . $orderId . '-refund',
            'number_order',
            $orderId
        );

        $this->orders->setStatus($orderId, $finalStatus);
    }

    /** Cron'un cagirdigi is: suresi dolan siparisleri kapat ve iade et. */
    public function expireDueOrders(\DateTimeImmutable $now): int
    {
        $count = 0;

        foreach ($this->orders->expired($now) as $order) {
            // Son bir kez sor: SMS zaman asimindan hemen once gelmis olabilir.
            $fresh = $this->poll($order, force: true);

            if ($fresh['status'] === 'received') {
                continue;
            }

            $this->refund((int) $order['id'], 'expired');
            $count++;
        }

        return $count;
    }

    private function pollDue(array $order): bool
    {
        if ($order['last_polled_at'] === null) {
            return true;
        }

        $interval = (int) Config::get('order.min_poll_interval', 3);

        return (time() - strtotime((string) $order['last_polled_at'])) >= $interval;
    }

    private function safeCancelAtProvider(ProviderRegistry $registry, string $provider, string $providerOrderId): void
    {
        try {
            $registry->get($provider)->cancel($providerOrderId);
        } catch (\Throwable $e) {
            // Saglayici iptali reddedebilir (pencere kapanmis olabilir). Musteriye
            // iade yine yapilir; fark mutabakat raporunda gorunur.
            Logger::warn('Saglayici iptali basarisiz', ['provider' => $provider, 'hata' => $e->getMessage()]);
        }
    }
}
