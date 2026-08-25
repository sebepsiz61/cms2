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

        $driver = (string) Config::get('db.driver', 'mysql');

        self::assertDriverAvailable($driver);

        try {
            $pdo = match ($driver) {
                'sqlite' => self::connectSqlite(),
                'mysql'  => self::connectMysql(),
                default  => throw new \RuntimeException(
                    "Desteklenmeyen veritabani surucusu: '{$driver}'. config/config.php icinde "
                    . "db.driver 'mysql' ya da 'sqlite' olmali."
                ),
            };
        } catch (\PDOException $e) {
            throw new \RuntimeException(self::explain($e, $driver), 0, $e);
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

        return self::$pdo = $pdo;
    }

    private static function connectSqlite(): \PDO
    {
        $pdo = new \PDO('sqlite:' . Config::get('db.database'));
        $pdo->exec('PRAGMA busy_timeout = 10000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    private static function connectMysql(): \PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            Config::get('db.host'),
            (int) Config::get('db.port', 3306),
            Config::get('db.database'),
            Config::get('db.charset', 'utf8mb4')
        );

        return new \PDO($dsn, Config::get('db.username'), Config::get('db.password'));
    }

    /**
     * PDO surucusu yoksa "could not find driver" diye anlasilmaz bir hata gelir.
     * Paylasimli sunucularda PHP surumu degistirilince eklenti seti de degistigi
     * icin en sik karsilasilan kurulum sorunu budur; ne yapilacagini soyleriz.
     */
    public static function assertDriverAvailable(string $driver): void
    {
        if (!extension_loaded('pdo')) {
            throw new \RuntimeException(
                'PHP PDO eklentisi yuklu degil. cPanel > Select PHP Version (ya da MultiPHP INI '
                . 'Editor) ekranindan "pdo" secenegini isaretleyin.'
            );
        }

        $available = \PDO::getAvailableDrivers();

        if (in_array($driver, $available, true)) {
            return;
        }

        $extension = $driver === 'mysql' ? 'pdo_mysql (cPanel\'de nd_pdo_mysql olarak gorunebilir)' : 'pdo_' . $driver;

        throw new \RuntimeException(sprintf(
            "PDO '%s' surucusu yuklu degil.\n"
            . "  Kurulu suruculer: %s\n"
            . "  Cozum: cPanel > Select PHP Version ekraninda PHP 8.2 secili iken '%s' eklentisini "
            . "isaretleyip kaydedin.\n"
            . "  Teshis icin: php bin/doctor.php",
            $driver,
            $available === [] ? 'hicbiri' : implode(', ', $available),
            $extension
        ));
    }

    /** Baglanti hatalarini kurulum sirasinda ise yarar hale getirir. */
    private static function explain(\PDOException $e, string $driver): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'could not find driver')) {
            return "PDO '{$driver}' surucusu yuklu degil. Teshis icin: php bin/doctor.php";
        }

        if (str_contains($message, 'Access denied')) {
            return 'Veritabani kullanici adi ya da sifresi yanlis. cPanel > MySQL Databases ekraninda '
                . 'kullaniciyi veritabanina ekleyip TUM yetkileri verdiginizden emin olun. '
                . 'cPanel kullanici adi on eki dahil yazilmali (ornek: kullanici_dbuser). '
                . 'Ozgun hata: ' . $message;
        }

        if (str_contains($message, 'Unknown database')) {
            return 'Veritabani bulunamadi. cPanel > MySQL Databases ekranindan olusturun ve '
                . 'config/config.php icinde on ekli tam adini yazin. Ozgun hata: ' . $message;
        }

        // [2002] soket hatasi: MySQL calismiyor ya da soket yolu farkli.
        if (str_contains($message, '[2002]') && str_contains($message, 'No such file or directory')) {
            return 'MySQL sunucusuna baglanilamadi (soket bulunamadi). Muhtemel sebepler: MySQL '
                . 'servisi calismiyor, ya da db.host degeri yanlis. cPanel\'de db.host "localhost" '
                . 'olmali; yine de olmuyorsa "127.0.0.1" deneyin (TCP uzerinden baglanir). '
                . 'Ozgun hata: ' . $message;
        }

        if (str_contains($message, 'getaddrinfo') || str_contains($message, "Can't connect")) {
            return "Veritabani sunucusuna ulasilamiyor. cPanel'de db.host neredeyse her zaman "
                . "'localhost' olur. Ozgun hata: " . $message;
        }

        return 'Veritabanina baglanilamadi: ' . $message;
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
