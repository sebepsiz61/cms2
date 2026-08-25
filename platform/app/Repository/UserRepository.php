<?php
namespace Onay\App\Repository;

use Onay\App\Kernel\Database;

final class UserRepository
{
    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower($email)]);

        return $stmt->fetch() ?: null;
    }

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function create(string $name, string $email, string $password, string $role = 'customer'): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (name, email, password_hash, role, status, balance_minor, created_at)
             VALUES (?, ?, ?, ?, ?, 0, ?)'
        );
        $stmt->execute([
            $name,
            mb_strtolower($email),
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            'active',
            date('Y-m-d H:i:s'),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<int, array<string,mixed>> */
    public function all(int $limit = 100): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
