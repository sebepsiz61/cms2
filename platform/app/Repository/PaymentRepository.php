<?php
namespace Onay\App\Repository;

use Onay\App\Kernel\Database;

final class PaymentRepository
{
    public function create(int $userId, int $amountMinor, ?string $receiptPath): array
    {
        $reference = $this->generateReference();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO payment_requests (user_id, amount_minor, reference_code, receipt_path, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $amountMinor, $reference, $receiptPath, 'pending', date('Y-m-d H:i:s')]);

        return ['id' => (int) Database::pdo()->lastInsertId(), 'reference_code' => $reference];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payment_requests WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string,mixed>> */
    public function pending(): array
    {
        return Database::pdo()->query(
            'SELECT p.*, u.email, u.name FROM payment_requests p
             JOIN users u ON u.id = p.user_id
             WHERE p.status = \'pending\' ORDER BY p.id'
        )->fetchAll();
    }

    /** @return array<int, array<string,mixed>> */
    public function forUser(int $userId, int $limit = 30): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payment_requests WHERE user_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Yalnizca 'pending' durumdaki talebi kapatir ve etkilenen satir sayisini doner.
     * Bu, iki yoneticinin ayni talebi ayni anda onaylamasini engelleyen kilittir:
     * ikincisinin guncellemesi 0 satir etkiler ve bakiye ikinci kez yuklenmez.
     */
    public function resolveIfPending(int $id, string $status, int $adminId, ?string $note): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE payment_requests SET status = ?, approved_by = ?, admin_note = ?, resolved_at = ?
             WHERE id = ? AND status = \'pending\''
        );
        $stmt->execute([$status, $adminId, $note, date('Y-m-d H:i:s'), $id]);

        return $stmt->rowCount() === 1;
    }

    private function generateReference(): string
    {
        return 'HV' . strtoupper(bin2hex(random_bytes(4)));
    }
}
