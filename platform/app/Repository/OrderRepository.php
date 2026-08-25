<?php
namespace Onay\App\Repository;

use Onay\App\Kernel\Database;
use Onay\Core\Dto\PurchaseResult;
use Onay\Core\Dto\Sms;

final class OrderRepository
{
    public function create(int $userId, PurchaseResult $purchase, string $countryCode, string $serviceCode, int $priceMinor, \DateTimeImmutable $expiresAt): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO number_orders
               (user_id, type, provider, provider_order_id, country_code, service_code, phone,
                cost_minor, cost_currency, price_minor, status, purchased_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            'activation',
            $purchase->provider,
            $purchase->providerOrderId,
            $countryCode,
            $serviceCode,
            $purchase->phone,
            $purchase->costMinor,
            $purchase->currency,
            $priceMinor,
            'waiting_sms',
            date('Y-m-d H:i:s'),
            $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM number_orders WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM number_orders WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string,mixed>> */
    public function forUser(int $userId, int $limit = 50): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM number_orders WHERE user_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string,mixed>> Suresi dolmus ama hala acik siparisler. */
    public function expired(\DateTimeImmutable $now): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM number_orders WHERE status IN ('waiting_sms','received') AND expires_at <= ?"
        );
        $stmt->execute([$now->format('Y-m-d H:i:s')]);

        return $stmt->fetchAll();
    }

    public function markPolled(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE number_orders SET last_polled_at = ? WHERE id = ?');
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function setStatus(int $id, string $status, ?string $code = null): void
    {
        $completed = in_array($status, ['completed', 'cancelled', 'expired', 'refunded'], true)
            ? date('Y-m-d H:i:s')
            : null;

        $stmt = Database::pdo()->prepare(
            'UPDATE number_orders SET status = ?, code = COALESCE(?, code), completed_at = COALESCE(?, completed_at) WHERE id = ?'
        );
        $stmt->execute([$status, $code, $completed, $id]);
    }

    public function addMessage(int $orderId, Sms $sms): void
    {
        // Ayni mesaj yeniden yoklamada tekrar gelebilir; metin ve zamana gore tekilleştir.
        $exists = Database::pdo()->prepare(
            'SELECT id FROM order_messages WHERE order_id = ? AND body = ? AND received_at = ?'
        );
        $exists->execute([$orderId, $sms->text, $sms->receivedAt->format('Y-m-d H:i:s')]);

        if ($exists->fetch()) {
            return;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO order_messages (order_id, sender, body, code, received_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $sms->sender, $sms->text, $sms->code, $sms->receivedAt->format('Y-m-d H:i:s')]);
    }

    /** @return array<int, array<string,mixed>> */
    public function messages(int $orderId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM order_messages WHERE order_id = ? ORDER BY id');
        $stmt->execute([$orderId]);

        return $stmt->fetchAll();
    }
}
