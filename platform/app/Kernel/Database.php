<?php
namespace Onay\App\Kernel;

final class Database
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        $driver = Config::get('db.driver', 'mysql');

        if ($driver === 'sqlite') {
            $pdo = new \PDO('sqlite:' . Config::get('db.database'));
            $pdo->exec('PRAGMA busy_timeout = 10000');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                Config::get('db.host'),
                (int) Config::get('db.port', 3306),
                Config::get('db.database'),
                Config::get('db.charset', 'utf8mb4')
            );
            $pdo = new \PDO($dsn, Config::get('db.username'), Config::get('db.password'));
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

        return self::$pdo = $pdo;
    }

    public static function set(?\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Sema dosyasini calistirir.
     *
     * Yorum satirlari once temizlenir: dosya basindaki aciklama bloguyla ilk
     * CREATE TABLE ayni parcaya dusup birlikte atlanmasin diye.
     */
    public static function runSchema(string $file): int
    {
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        $count = 0;
        foreach (explode(';', (string) $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            self::pdo()->exec($statement);
            $count++;
        }

        return $count;
    }

    public static function driver(): string
    {
        return (string) self::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * "Varsa guncelle, yoksa ekle" ifadesini surucuye gore uretir.
     *
     * MySQL ile SQLite bu ifadede ayrisir (ON DUPLICATE KEY UPDATE / ON CONFLICT
     * DO UPDATE). Uretim MySQL, testler SQLite oldugu icin ikisi de desteklenir;
     * cagiran taraf farki gormez.
     *
     * @param string[] $columns        eklenecek tum kolonlar
     * @param string[] $conflictColumns benzersiz indeksi olusturan kolonlar
     * @param string[] $updateColumns  catisma halinde guncellenecek kolonlar
     */
    public static function upsert(string $table, array $columns, array $conflictColumns, array $updateColumns): string
    {
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insert = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), $placeholders);

        if (self::driver() === 'mysql') {
            $assignments = array_map(
                static fn (string $column): string => sprintf('%s = VALUES(%s)', $column, $column),
                $updateColumns
            );

            return $insert . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
        }

        $assignments = array_map(
            static fn (string $column): string => sprintf('%s = excluded.%s', $column, $column),
            $updateColumns
        );

        return sprintf(
            '%s ON CONFLICT (%s) DO UPDATE SET %s',
            $insert,
            implode(', ', $conflictColumns),
            implode(', ', $assignments)
        );
    }
}
