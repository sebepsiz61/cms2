<?php
namespace Onay\Core\Wallet;

use Onay\Core\Contract\LedgerInterface;
use Onay\Core\Exception\InsufficientBalanceException;

/**
 * Defterin PDO uygulamasi.
 *
 * Iki kurali veritabani zorlar, uygulama degil:
 *   1. Idempotency anahtari uzerindeki benzersiz indeks cift kaydi engeller.
 *   2. Kullanici satiri kilitlenmeden bakiye okunmaz; boylece iki es zamanli
 *      istek ayni bakiyeyle iki harcama yapamaz.
 *
 * SQLite FOR UPDATE desteklemedigi icin orada BEGIN IMMEDIATE kullanilir; ikisi de
 * ayni sonucu verir: yazma islemi boyunca satir baskasina acilmaz.
 */
final class PdoLedger implements LedgerInterface
{
    private readonly string $driver;
    private bool $inTransaction = false;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->driver = (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function balanceMinor(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT balance_minor FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            throw new \InvalidArgumentException('Kullanici bulunamadi: ' . $userId);
        }

        return (int) $value;
    }

    public function credit(int $userId, int $amountMinor, string $type, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null): int
    {
        return $this->write($userId, $amountMinor, $type, 1, $idempotencyKey, $referenceType, $referenceId);
    }

    public function debit(int $userId, int $amountMinor, string $type, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null): int
    {
        return $this->write($userId, $amountMinor, $type, -1, $idempotencyKey, $referenceType, $referenceId);
    }

    /** Defterdeki hareketlerin toplami ile onbellek kolonunu karsilastirir. */
    public function verifyIntegrity(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount_minor * direction), 0) FROM wallet_transactions WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn() === $this->balanceMinor($userId);
    }

    private function write(int $userId, int $amountMinor, string $type, int $direction, string $idempotencyKey, ?string $referenceType, ?int $referenceId): int
    {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Tutar pozitif olmali; yon type ve direction ile belirlenir.');
        }

        $this->begin();

        try {
            // Ayni anahtar daha once islendiyse hicbir sey yapma, mevcut kaydi don.
            $existing = $this->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                $this->commit();
                return $existing;
            }

            $balance = $this->lockBalance($userId);
            $newBalance = $balance + ($amountMinor * $direction);

            if ($newBalance < 0) {
                throw new InsufficientBalanceException($userId, $amountMinor, $balance);
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO wallet_transactions
                 (user_id, type, amount_minor, direction, balance_after, idempotency_key, reference_type, reference_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $userId,
                $type,
                $amountMinor,
                $direction,
                $newBalance,
                $idempotencyKey,
                $referenceType,
                $referenceId,
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            $id = (int) $this->pdo->lastInsertId();

            $update = $this->pdo->prepare('UPDATE users SET balance_minor = ? WHERE id = ?');
            $update->execute([$newBalance, $userId]);

            $this->commit();

            return $id;
        } catch (\Throwable $e) {
            $this->rollBack();

            // Yaris durumunda benzersiz indeks patlar; bu bir hata degil, korumanin calismasidir.
            if ($e instanceof \PDOException) {
                $existing = $this->findByIdempotencyKey($idempotencyKey);
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    /**
     * SQLite'ta BEGIN IMMEDIATE yazma kilidini hemen alir; PDO'nun kendi
     * beginTransaction'i ertelenmis kilit kullandigi icin iki es zamanli yazici
     * ayni bakiyeyi okuyabilirdi. Bu yuzden islem sinirlari elle yonetiliyor ve
     * durum $inTransaction ile izleniyor.
     */
    private function begin(): void
    {
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('BEGIN IMMEDIATE');
        } else {
            $this->pdo->beginTransaction();
        }

        $this->inTransaction = true;
    }

    private function commit(): void
    {
        if (!$this->inTransaction) {
            return;
        }

        if ($this->driver === 'sqlite') {
            $this->pdo->exec('COMMIT');
        } else {
            $this->pdo->commit();
        }

        $this->inTransaction = false;
    }

    private function rollBack(): void
    {
        if (!$this->inTransaction) {
            return;
        }

        if ($this->driver === 'sqlite') {
            $this->pdo->exec('ROLLBACK');
        } else {
            $this->pdo->rollBack();
        }

        $this->inTransaction = false;
    }

    private function lockBalance(int $userId): int
    {
        $sql = 'SELECT balance_minor FROM users WHERE id = ?';
        if ($this->driver !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            throw new \InvalidArgumentException('Kullanici bulunamadi: ' . $userId);
        }

        return (int) $value;
    }

    private function findByIdempotencyKey(string $key): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM wallet_transactions WHERE idempotency_key = ?');
        $stmt->execute([$key]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
