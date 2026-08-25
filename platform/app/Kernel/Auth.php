<?php
namespace Onay\App\Kernel;

use Onay\App\Repository\UserRepository;

final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): bool
    {
        $user = (new UserRepository())->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        self::$user = $user;

        return true;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::put('user_id', $userId);
        self::$user = null;
    }

    public static function logout(): void
    {
        self::$user = null;
        Session::destroy();
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');

        return is_int($id) ? $id : null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = self::id();

        return $id === null ? null : self::$user = (new UserRepository())->find($id);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }
}
