<?php
namespace Onay\App\Service;

use Onay\App\Repository\PaymentRepository;
use Onay\Core\Contract\LedgerInterface;

/**
 * Havale/EFT ile bakiye yukleme.
 *
 * Otomatik tahsilat yok: musteri referans koduyla havale yapar, yonetici onaylar.
 * Onayin iki katmanli korumasi var — once talep 'pending' iken kilitlenir (iki
 * yonetici ayni anda onaylarsa ikincisi 0 satir gunceller), sonra defterdeki
 * idempotency anahtari ayni talebin ikinci kez para yuklemesini engeller.
 */
final class WalletService
{
    public function __construct(
        private readonly LedgerInterface $ledger,
        private readonly PaymentRepository $payments = new PaymentRepository(),
    ) {
    }

    public function requestTopUp(int $userId, int $amountMinor, ?string $receiptPath = null): array
    {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Tutar sifirdan buyuk olmali.');
        }

        return $this->payments->create($userId, $amountMinor, $receiptPath);
    }

    public function approve(int $paymentId, int $adminId, ?string $note = null): bool
    {
        $payment = $this->payments->find($paymentId);

        if ($payment === null || $payment['status'] !== 'pending') {
            return false;
        }

        if (!$this->payments->resolveIfPending($paymentId, 'approved', $adminId, $note)) {
            return false;   // baska bir yonetici saniyeler icinde onaylamis
        }

        $this->ledger->credit(
            (int) $payment['user_id'],
            (int) $payment['amount_minor'],
            LedgerInterface::TYPE_LOAD,
            'payment-' . $paymentId . '-load',
            'payment_request',
            $paymentId
        );

        Logger::info('Bakiye yuklendi', ['payment' => $paymentId, 'admin' => $adminId]);

        return true;
    }

    public function reject(int $paymentId, int $adminId, ?string $note = null): bool
    {
        return $this->payments->resolveIfPending($paymentId, 'rejected', $adminId, $note);
    }
}
