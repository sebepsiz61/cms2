<?php
/**
 * Gercekci demo verisi uretir: musteriler, bakiye yuklemeleri, gecmis siparisler,
 * gelen SMS'ler, iadeler ve bekleyen havale talepleri.
 *
 *     php bin/demo-data.php            # veri ekler
 *     php bin/demo-data.php --temizle  # once mevcut demo verisini siler
 *
 * Amaci gorsel ve icerik testidir: bos bir sistemde tasarim degerlendirilemez.
 * Para hareketleri dogrudan tabloya yazilmaz, defter uzerinden islenir; boylece
 * uretilen veri gercek sistemin uretecegi veriyle ayni tutarliliktadir.
 *
 * Demo saglayici acik olmalidir (config icinde 'demo' => enabled true).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/autoload.php';

use Onay\App\Kernel\Config;
use Onay\App\Kernel\Database;
use Onay\App\Repository\OrderRepository;
use Onay\App\Repository\UserRepository;
use Onay\App\Service\Container;
use Onay\App\Service\ProviderFactory;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnizca komut satirindan calistirilir.\n");
}

Config::load($root . '/config/config.php');
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Istanbul'));

if (!ProviderFactory::demoEtkin()) {
    exit("Demo saglayici kapali. config/config.php icinde 'demo' => 'enabled' => true yapin.\n");
}

$pdo = Database::pdo();
$temizle = in_array('--temizle', $argv, true);

if ($temizle) {
    echo "Mevcut demo verisi siliniyor...\n";
    $pdo->exec("DELETE FROM order_messages");
    $pdo->exec("DELETE FROM number_orders");
    $pdo->exec("DELETE FROM payment_requests");
    $pdo->exec("DELETE FROM wallet_transactions WHERE user_id IN (SELECT id FROM users WHERE role = 'customer')");
    $pdo->exec("DELETE FROM users WHERE role = 'customer'");
    $pdo->exec("UPDATE users SET balance_minor = 0 WHERE role = 'customer'");
}

// --- Katalog ------------------------------------------------------------
$ulkeler = [
    'GB' => 'Ingiltere', 'US' => 'Amerika', 'TR' => 'Turkiye', 'DE' => 'Almanya',
];
$servisler = [
    'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'instagram' => 'Instagram',
];

$ulkeEkle = $pdo->prepare(Database::upsert('countries', ['code', 'name', 'is_active'], ['code'], ['name']));
foreach ($ulkeler as $kod => $ad) {
    $ulkeEkle->execute([$kod, $ad, 1]);
}

$servisEkle = $pdo->prepare(Database::upsert('services', ['code', 'name', 'is_active'], ['code'], ['name']));
foreach ($servisler as $kod => $ad) {
    $servisEkle->execute([$kod, $ad, 1]);
}

echo "Katalog: " . count($ulkeler) . " ulke, " . count($servisler) . " servis\n";

$rapor = Container::catalogSync()->sync();
echo "Senkron: " . json_encode($rapor, JSON_UNESCAPED_UNICODE) . "\n";

// --- Musteriler ---------------------------------------------------------
$isimler = [
    ['Ayse Yilmaz', 'ayse@ornek.com'],
    ['Mehmet Demir', 'mehmet@ornek.com'],
    ['Zeynep Kaya', 'zeynep@ornek.com'],
    ['Emre Sahin', 'emre@ornek.com'],
    ['Elif Celik', 'elif@ornek.com'],
    ['Burak Arslan', 'burak@ornek.com'],
];

$users = new UserRepository();
$wallet = Container::walletService();
$orders = new OrderRepository();
$service = Container::orderService();

$musteriler = [];

foreach ($isimler as [$ad, $eposta]) {
    $mevcut = $users->findByEmail($eposta);
    $id = $mevcut !== null ? (int) $mevcut['id'] : $users->create($ad, $eposta, 'demo12345');
    $musteriler[] = ['id' => $id, 'ad' => $ad, 'eposta' => $eposta];
}

echo count($musteriler) . " musteri hazir (sifre: demo12345)\n";

// --- Bakiye yuklemeleri -------------------------------------------------
$onayli = $bekleyen = 0;

foreach ($musteriler as $i => $m) {
    $tutar = [15000, 25000, 50000, 10000, 30000, 20000][$i];
    $talep = $wallet->requestTopUp($m['id'], $tutar, null);
    $wallet->approve($talep['id'], 1, 'demo verisi');
    $onayli++;

    // Ilk iki musteride bekleyen talep birakilir: yonetim ekrani bos gorunmesin.
    if ($i < 2) {
        $wallet->requestTopUp($m['id'], 7500, null);
        $bekleyen++;
    }
}

echo "{$onayli} onayli, {$bekleyen} bekleyen havale talebi\n";

// --- Siparisler ---------------------------------------------------------
// Rastgele secim katalogda olmayan ulke/servis ciftine dusebiliyor ve sonuc
// dagilimi her calistirmada degisiyordu. Bunun yerine gercek katalogdan
// okunan ciftler uzerinde planli bir dagilim uretiliyor.
$ciftler = $pdo->query(
    'SELECT DISTINCT country_code, service_code FROM provider_offers WHERE stock > 0'
)->fetchAll();

if ($ciftler === []) {
    exit("Katalog bos. Once senkron calistirin.\n");
}

$sessizServis = (string) Config::get('providers.demo.silent_service', 'instagram');
$sesli = array_values(array_filter($ciftler, static fn (array $c): bool => $c['service_code'] !== $sessizServis));
$sessiz = array_values(array_filter($ciftler, static fn (array $c): bool => $c['service_code'] === $sessizServis));

if ($sesli === []) {
    exit("Katalogda SMS getiren servis yok.\n");
}

/**
 * Demo saglayici SMS'i, siparis kimligine gomulu satin alma zamanindan
 * sms_delay_seconds sonra verir. Gecmis veri uretirken beklemek yerine
 * kimlikteki zaman damgasi geriye alinir; boylece gercek kod yolu kullanilir.
 */
$zamaniGeriAl = static function (array $siparis) use ($pdo): void {
    $parca = explode('-', (string) $siparis['provider_order_id']);
    if (count($parca) < 4) {
        return;
    }
    $parca[1] = (string) (time() - 3600);
    $pdo->prepare('UPDATE number_orders SET provider_order_id = ? WHERE id = ?')
        ->execute([implode('-', $parca), (int) $siparis['id']]);
};

// Toplam 46 siparis: 28 tamamlandi, 8 iade (SMS gelmedi), 4 iptal, 6 acik.
$plan = array_merge(
    array_fill(0, 28, 'completed'),
    array_fill(0, 8,  'expired'),
    array_fill(0, 4,  'cancelled'),
    array_fill(0, 6,  'waiting_sms'),
);
shuffle($plan);

$olusan = [];
$geriTarih = $pdo->prepare(
    'UPDATE number_orders SET purchased_at = ?, expires_at = ?, completed_at = ? WHERE id = ?'
);
$mesajTarih = $pdo->prepare('UPDATE order_messages SET received_at = ? WHERE order_id = ?');
$hareketTarih = $pdo->prepare(
    'UPDATE wallet_transactions SET created_at = ? WHERE reference_type = ? AND reference_id = ?'
);

foreach ($plan as $sira => $hedef) {
    $m = $musteriler[$sira % count($musteriler)];

    // Iade senaryosu icin SMS getirmeyen servis varsa o secilir.
    $cift = $hedef === 'expired' && $sessiz !== []
        ? $sessiz[array_rand($sessiz)]
        : $sesli[array_rand($sesli)];

    try {
        $siparis = $service->purchase($m['id'], $cift['country_code'], $cift['service_code']);
    } catch (\Throwable $e) {
        continue;   // bakiye bitmis olabilir
    }

    $id = (int) $siparis['id'];

    if ($hedef === 'completed') {
        $zamaniGeriAl($siparis);
        $service->poll($orders->find($id), force: true);
        $taze = $orders->find($id);

        if ($taze['status'] === 'received') {
            $service->complete($taze);
        }
    } elseif ($hedef === 'expired') {
        $service->refund($id, 'expired');
    } elseif ($hedef === 'cancelled') {
        $service->refund($id, 'cancelled');
    }

    $gercek = $orders->find($id)['status'];
    $olusan[$gercek] = ($olusan[$gercek] ?? 0) + 1;

    // Son 30 gune yay; acik siparisler bugun kalsin ki geri sayim calissin.
    if ($hedef === 'waiting_sms') {
        continue;
    }

    $alinma = time() - random_int(1, 29) * 86400 - random_int(0, 80000);
    $zaman = date('Y-m-d H:i:s', $alinma);
    $bitis = date('Y-m-d H:i:s', $alinma + 900);

    $geriTarih->execute([$zaman, $bitis, $bitis, $id]);
    $mesajTarih->execute([date('Y-m-d H:i:s', $alinma + 40), $id]);
    $hareketTarih->execute([$zaman, 'number_order', $id]);
}

echo "\nSiparisler:\n";
ksort($olusan);
foreach ($olusan as $durum => $adet) {
    printf("  %-14s %d\n", $durum, $adet);
}

// --- Ozet ---------------------------------------------------------------
$toplam = $pdo->query("SELECT COUNT(*) FROM number_orders")->fetchColumn();
$ciro = $pdo->query("SELECT COALESCE(SUM(price_minor),0) FROM number_orders WHERE status = 'completed'")->fetchColumn();

echo "\n" . str_repeat('-', 52) . "\n";
printf("Toplam %d siparis, %s TL ciro\n", $toplam, number_format($ciro / 100, 2, ',', '.'));

// Defter butunlugu: uretilen veri gercek sistemin uretecegiyle ayni olmali.
$ledger = Container::ledger();
$bozuk = 0;
foreach ($musteriler as $m) {
    if (!$ledger->verifyIntegrity($m['id'])) {
        $bozuk++;
    }
}
echo $bozuk === 0
    ? "Defter butunlugu: tum musterilerde tutarli\n"
    : "UYARI: {$bozuk} musteride defter tutarsiz\n";

echo "\nGiris bilgileri:\n";
echo "  musteri  ayse@ornek.com / demo12345\n";
echo "  yonetici kurulumda verdiginiz adres ve sifre\n\n";
