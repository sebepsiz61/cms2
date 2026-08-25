<?php
/**
 * Kurulum teshisi. Sunucuda bir sey calismadiginda ilk calistirilacak betik:
 *   php bin/doctor.php
 *
 * PHP surumu, eklentiler, PDO suruculeri, yapilandirma, yazma izinleri ve
 * veritabani baglantisini tek tek dener; eksik olan icin ne yapilacagini yazar.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/autoload.php';

use Onay\App\Kernel\Config;
use Onay\App\Kernel\Database;

$sorun = 0;

function satir(bool $ok, string $baslik, string $detay = '', string $cozum = ''): void
{
    global $sorun;

    printf("  %-7s %-34s %s\n", $ok ? '[OK]' : '[EKSIK]', $baslik, $detay);

    if (!$ok) {
        $sorun++;
        if ($cozum !== '') {
            echo "        -> " . str_replace("\n", "\n           ", $cozum) . "\n";
        }
    }
}

echo "\nKurulum teshisi\n" . str_repeat('=', 62) . "\n\n";

echo "PHP\n";
$surum = PHP_VERSION;
satir(
    PHP_VERSION_ID >= 80200,
    'Surum >= 8.2',
    $surum,
    "cPanel > MultiPHP Manager ekranindan alan adi icin PHP 8.2 secin.\n"
    . "Komut satirinda farkli bir surum cikiyorsa tam yol kullanin:\n"
    . "/opt/cpanel/ea-php82/root/usr/bin/php bin/doctor.php"
);

echo "\nEklentiler\n";
$gerekli = [
    'pdo'      => 'Veritabani erisimi',
    'pdo_mysql'=> 'MySQL baglantisi (cPanel\'de nd_pdo_mysql adiyla gorunebilir)',
    'curl'     => 'Saglayici API cagrilari',
    'mbstring' => 'Turkce karakter isleme',
    'fileinfo' => 'Dekont MIME dogrulamasi',
    'json'     => 'API yanitlarinin cozumlenmesi',
];

foreach ($gerekli as $eklenti => $nicin) {
    satir(
        extension_loaded($eklenti),
        $eklenti,
        $nicin,
        "cPanel > Select PHP Version > Extensions ekranindan '{$eklenti}' kutusunu isaretleyip kaydedin."
    );
}

echo "\nPDO suruculeri\n";
$suruculer = extension_loaded('pdo') ? PDO::getAvailableDrivers() : [];
satir(
    $suruculer !== [],
    'Kurulu suruculer',
    $suruculer === [] ? 'hicbiri' : implode(', ', $suruculer),
    "Hic surucu yoksa PDO eklentisi yuklu ama surucu paketleri kurulu degildir."
);

echo "\nYapilandirma\n";
$configFile = $root . '/config/config.php';
$configVar = is_file($configFile);
satir(
    $configVar,
    'config/config.php',
    $configVar ? 'var' : 'yok',
    "cp config/config.example.php config/config.php ve icini doldurun."
);

echo "\nYazma izinleri\n";
foreach (['storage', 'storage/logs', 'storage/uploads'] as $dizin) {
    $yol = $root . '/' . $dizin;
    satir(
        is_dir($yol) && is_writable($yol),
        $dizin,
        is_dir($yol) ? (is_writable($yol) ? 'yazilabilir' : 'yazilamaz') : 'yok',
        "mkdir -p {$yol} && chmod 755 {$yol}"
    );
}

echo "\nSema dosyalari\n";
foreach (['schema/mysql.sql', 'schema/sqlite.sql'] as $dosya) {
    satir(is_file($root . '/' . $dosya), $dosya, '', "php bin/make-sqlite-schema.php calistirin.");
}

if ($configVar) {
    echo "\nVeritabani\n";
    Config::load($configFile);
    $driver = (string) Config::get('db.driver', 'mysql');

    try {
        Database::assertDriverAvailable($driver);
        satir(true, "Surucu '{$driver}'", 'kullanilabilir');

        $pdo = Database::pdo();
        satir(true, 'Baglanti', 'basarili');

        $tablo = $pdo->query(
            $driver === 'sqlite'
                ? "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'"
                : 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
        )->fetchColumn();

        satir(
            (int) $tablo > 0,
            'Tablolar',
            $tablo . ' tablo',
            'php bin/install.php <admin-eposta> <sifre> calistirin.'
        );
    } catch (\Throwable $e) {
        satir(false, 'Baglanti', 'basarisiz', $e->getMessage());
    }
}

echo "\n" . str_repeat('=', 62) . "\n";

if ($sorun === 0) {
    echo "Her sey yolunda.\n\n";
    exit(0);
}

printf("%d sorun bulundu. Yukaridaki '->' satirlari ne yapilacagini soyluyor.\n\n", $sorun);
exit(1);
