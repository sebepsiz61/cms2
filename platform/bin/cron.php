<?php
/**
 * Dakikada bir calisir. cPanel > Cron Jobs:
 *   * * * * * /usr/local/bin/php /home/kullanici/platform/bin/cron.php
 *
 * Iki isi yapar:
 *   1. Suresi dolan siparisleri kapatir ve ucreti iade eder (her dakika)
 *   2. Katalogu saglayicilardan senkronlar (yapilandirilan araliklarla)
 *
 * Kilit dosyasi, onceki calisma bitmeden ikincisinin baslamasini engeller;
 * ayni siparis icin iki kez iade denenmesini defterdeki idempotency anahtari
 * zaten engelliyor ama saglayiciya bosuna gitmemek daha iyi.
 */
declare(strict_types=1);

use Onay\App\Kernel\Config;
use Onay\App\Service\Container;
use Onay\App\Service\Logger;

$root = dirname(__DIR__);
require $root . '/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnizca komut satirindan calistirilir.\n");
}

Config::load($root . '/config/config.php');
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Istanbul'));

$lockFile = $root . '/storage/cron.lock';
$lock = fopen($lockFile, 'c');

if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Onceki calisma surüyor, atlandi.\n";
    exit(0);
}

$baslangic = microtime(true);

try {
    $iade = Container::orderService()->expireDueOrders(new DateTimeImmutable());

    if ($iade > 0) {
        Logger::info('Suresi dolan siparisler iade edildi', ['adet' => $iade]);
    }
    echo "Suresi dolan siparis: {$iade}\n";

    // Katalog her dakika degil, belirlenen aralikta senkronlanir.
    $aralik = (int) Config::get('catalog.sync_interval_minutes', 15);
    $damga = $root . '/storage/last-catalog-sync';
    $son = is_file($damga) ? (int) file_get_contents($damga) : 0;

    if (time() - $son >= $aralik * 60) {
        $rapor = Container::catalogSync()->sync();
        file_put_contents($damga, (string) time());
        echo 'Katalog senkronu: ' . json_encode($rapor, JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (\Throwable $e) {
    Logger::error('Cron hatasi', ['mesaj' => $e->getMessage(), 'dosya' => $e->getFile() . ':' . $e->getLine()]);
    echo 'HATA: ' . $e->getMessage() . "\n";

    flock($lock, LOCK_UN);
    exit(1);
}

printf("Tamamlandi (%.2f sn)\n", microtime(true) - $baslangic);
flock($lock, LOCK_UN);
