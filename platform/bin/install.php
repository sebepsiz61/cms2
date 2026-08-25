<?php
/**
 * Kurulum. Semayi yukler ve ilk yonetici hesabini acar.
 *
 * Kullanim (SSH):
 *   php bin/install.php admin@ornek.com "Guclu Bir Sifre"
 *
 * Yonetici rolu yalnizca buradan ya da dogrudan veritabanindan verilir; kayit
 * formundan asla admin olunamaz.
 */
declare(strict_types=1);

use Onay\App\Kernel\Config;
use Onay\App\Kernel\Database;
use Onay\App\Repository\UserRepository;

$root = dirname(__DIR__);
require $root . '/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnizca komut satirindan calistirilir.\n");
}

Config::load($root . '/config/config.php');

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;

if ($email === null || $password === null) {
    exit("Kullanim: php bin/install.php <admin-eposta> <sifre>\n");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Gecersiz e-posta adresi.\n");
}

if (strlen($password) < 10) {
    exit("Yonetici sifresi en az 10 karakter olmali.\n");
}

try {
    $pdo = Database::pdo();
} catch (\Throwable $e) {
    echo "\n" . $e->getMessage() . "\n\n";
    echo "Ayrintili teshis icin: php bin/doctor.php\n";
    exit(1);
}

$driver = Database::driver();
$schemaFile = $root . '/schema/' . ($driver === 'sqlite' ? 'sqlite.sql' : 'mysql.sql');

echo "Sema yukleniyor ({$driver}): {$schemaFile}\n";

$count = Database::runSchema($schemaFile);
echo "{$count} ifade calistirildi.\n";

$users = new UserRepository();

if ($users->emailExists($email)) {
    exit("Bu e-posta zaten kayitli. Rolu veritabanindan degistirin.\n");
}

$id = $users->create('Yonetici', $email, $password, 'admin');

echo "Yonetici olusturuldu (#{$id}): {$email}\n";
echo "\nSonraki adimlar:\n";
echo "  1. config/config.php icinde en az bir saglayiciyi 'enabled' yapin ve API anahtarini girin.\n";
echo "  2. Yonetim > Katalog ekranindan ulke ve servisleri tanimlayin.\n";
echo "  3. Katalog senkronunu calistirin; eslenmemis kodlari ayni ekrandan eslersiniz.\n";
echo "  4. Cron ekleyin: * * * * * php {$root}/bin/cron.php\n";
