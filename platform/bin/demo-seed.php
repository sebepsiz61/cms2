<?php
/**
 * Demo saglayicinin sattigi ulke ve servisleri veritabanina ekler.
 *
 *     php bin/demo-seed.php
 *
 * Demo kipini denerken Yonetim > Katalog ekranindan tek tek girmek zorunda
 * kalmamak icin. Var olan kayitlari bozmaz.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/autoload.php';

use Onay\App\Kernel\Config;
use Onay\App\Kernel\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnizca komut satirindan calistirilir.\n");
}

Config::load($root . '/config/config.php');

$ulkeler = ['GB' => 'Ingiltere', 'US' => 'Amerika', 'TR' => 'Turkiye', 'DE' => 'Almanya'];
$servisler = ['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'instagram' => 'Instagram'];

$pdo = Database::pdo();

$ulkeEkle = $pdo->prepare(Database::upsert('countries', ['code', 'name', 'is_active'], ['code'], ['name']));
foreach ($ulkeler as $kod => $ad) {
    $ulkeEkle->execute([$kod, $ad, 1]);
}

$servisEkle = $pdo->prepare(Database::upsert('services', ['code', 'name', 'is_active'], ['code'], ['name']));
foreach ($servisler as $kod => $ad) {
    $servisEkle->execute([$kod, $ad, 1]);
}

printf("%d ulke, %d servis hazir.\n", count($ulkeler), count($servisler));
echo "\nSonraki adim: Yonetim > Katalog > 'Katalogu simdi senkronla'\n";
echo "Ardindan musteri panelinden numara alabilirsiniz.\n\n";
