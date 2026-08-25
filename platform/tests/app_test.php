<?php
/**
 * Uctan uca test: kayit -> bakiye -> numara al -> SMS -> tamamla / iade.
 * Gercek saglayiciya cikilmaz; FakeHttpClient senaryolari uretir.
 */
require __DIR__ . '/../autoload.php';
require __DIR__ . '/../core/tests/bootstrap.php';
require __DIR__ . '/../app/Kernel/helpers.php';

use Onay\App\Kernel\Config;
use Onay\App\Kernel\Database;
use Onay\App\Repository\CatalogRepository;
use Onay\App\Repository\OrderRepository;
use Onay\App\Repository\UserRepository;
use Onay\App\Service\Container;
use Onay\Core\Exception\InsufficientBalanceException;
use Onay\Core\Http\FakeHttpClient;
use Onay\Core\Wallet\PdoLedger;

$dbFile = sys_get_temp_dir() . '/onay_app_test_' . getmypid() . '.sqlite';
@unlink($dbFile);
putenv('TEST_DB=' . $dbFile);

Config::load(__DIR__ . '/config.test.php');
date_default_timezone_set('Europe/Istanbul');

$pdo = Database::pdo();
Database::runSchema(__DIR__ . '/../schema/sqlite.sql');

// Kanonik katalog ve saglayici kod eslemeleri.
$pdo->exec("INSERT INTO countries (code, name, is_active) VALUES ('GB', 'Ingiltere', 1)");
$pdo->exec("INSERT INTO services (code, name, is_active) VALUES ('whatsapp', 'WhatsApp', 1)");
foreach (['ucuz', 'pahali'] as $saglayici) {
    $pdo->exec("INSERT INTO provider_codes (provider, kind, provider_code, canonical_code)
                VALUES ('{$saglayici}', 'country', 'england', 'GB')");
    $pdo->exec("INSERT INTO provider_codes (provider, kind, provider_code, canonical_code)
                VALUES ('{$saglayici}', 'service', 'whatsapp', 'whatsapp')");
}

$fake = new FakeHttpClient();
Container::setHttp($fake);
Container::setLedger(new PdoLedger($pdo));

$fake->on('ucuz.test/v1/guest/prices', json_encode([
    'england' => ['whatsapp' => ['any' => ['cost' => 8.0, 'count' => 5]]],
]));
$fake->on('pahali.test/v1/guest/prices', json_encode([
    'england' => ['whatsapp' => ['any' => ['cost' => 15.0, 'count' => 50]]],
]));

$users    = new UserRepository();
$orders   = new OrderRepository();
$catalog  = new CatalogRepository();
$wallet   = Container::walletService();
$service  = Container::orderService();
$ledger   = Container::ledger();

$userId = $users->create('Test Musteri', 'musteri@ornek.com', 'sifre12345');

T::group('Bakiye yukleme (havale/EFT)');

T::it('talep olusturulur ve referans kodu uretilir', function () use ($wallet, $userId) {
    $talep = $wallet->requestTopUp($userId, 10000, null);
    T::true(str_starts_with($talep['reference_code'], 'HV'), 'referans kodu HV ile baslamali');
});

T::it('yonetici onayi bakiyeyi yukler', function () use ($wallet, $ledger, $userId) {
    $talep = $wallet->requestTopUp($userId, 50000, null);
    $onceki = $ledger->balanceMinor($userId);

    T::same(true, $wallet->approve($talep['id'], 1, 'dekont dogrulandi'));
    T::same($onceki + 50000, $ledger->balanceMinor($userId));
});

T::it('ayni talep ikinci kez onaylanirsa bakiye artmaz', function () use ($wallet, $ledger, $userId) {
    $talep = $wallet->requestTopUp($userId, 20000, null);
    $wallet->approve($talep['id'], 1, null);
    $sonra = $ledger->balanceMinor($userId);

    T::same(false, $wallet->approve($talep['id'], 1, null), 'ikinci onay reddedilmeli');
    T::same($sonra, $ledger->balanceMinor($userId), 'bakiye degismemeli');
});

T::group('Katalog senkronu');

T::it('iki saglayicinin teklifleri yazilir', function () use ($catalog) {
    $rapor = Container::catalogSync()->sync();

    T::same(1, $rapor['ucuz']);
    T::same(1, $rapor['pahali']);
    T::same(2, count($catalog->inStock('GB', 'whatsapp')));
});

T::group('Numara satin alma');

$siparisId = null;

T::it('en ucuz saglayicidan alinir ve bakiye duser', function () use ($service, $ledger, $userId, $fake, &$siparisId) {
    $fake->on('ucuz.test/v1/user/buy/activation', json_encode([
        'id' => 111, 'phone' => '+447700900111', 'price' => 8.0, 'status' => 'PENDING',
    ]));

    $onceki = $ledger->balanceMinor($userId);
    $siparis = $service->purchase($userId, 'GB', 'whatsapp');
    $siparisId = (int) $siparis['id'];

    T::same('ucuz', $siparis['provider']);
    T::same('waiting_sms', $siparis['status']);
    // 8,00 RUB -> 272 kurus -> %45 marj 394 -> asgari kar 472 -> 50'ye yuvarlama 500
    T::same(500, (int) $siparis['price_minor']);
    T::same($onceki - 500, $ledger->balanceMinor($userId));
});

T::it('musteri suresi saglayici penceresinden kisadir', function () use ($orders, &$siparisId) {
    $siparis = $orders->find($siparisId);
    $sure = strtotime($siparis['expires_at']) - strtotime($siparis['purchased_at']);

    T::same(900, $sure, '1200 sn pencere - 300 sn guvenlik payi');
});

T::it('gelen SMS yakalanir ve kod okunur', function () use ($service, $orders, $fake, &$siparisId) {
    $fake->on('ucuz.test/v1/user/check/', json_encode([
        'status' => 'RECEIVED',
        'sms' => [['sender' => 'WhatsApp', 'text' => 'Kodunuz 481523', 'code' => '481523']],
    ]));

    $siparis = $service->poll($orders->find($siparisId));

    T::same('received', $siparis['status']);
    T::same('481523', $siparis['code']);
    T::same(1, count($orders->messages($siparisId)));
});

T::it('yeniden yoklama ayni mesaji ikinci kez yazmaz', function () use ($service, $orders, &$siparisId) {
    $service->poll($orders->find($siparisId), force: true);
    T::same(1, count($orders->messages($siparisId)));
});

T::it('tamamlanan sipariste iade yapilmaz', function () use ($service, $orders, $ledger, $userId, $fake, &$siparisId) {
    $fake->on('ucuz.test/v1/user/finish/', json_encode(['status' => 'FINISHED']));

    $onceki = $ledger->balanceMinor($userId);
    $service->complete($orders->find($siparisId));

    T::same('completed', $orders->find($siparisId)['status']);
    T::same($onceki, $ledger->balanceMinor($userId), 'tamamlanan siparis iade edilmez');
});

T::group('Sure dolmasi ve otomatik iade');

T::it('SMS gelmezse ucret iade edilir', function () use ($service, $orders, $ledger, $userId, $fake, $pdo) {
    $fake->on('ucuz.test/v1/user/buy/activation', json_encode(['id' => 222, 'phone' => '+447700900222', 'price' => 8.0]));
    $fake->on('ucuz.test/v1/user/check/', json_encode(['status' => 'PENDING', 'sms' => []]));
    $fake->on('ucuz.test/v1/user/cancel/', json_encode(['status' => 'CANCELED']));

    $siparis = $service->purchase($userId, 'GB', 'whatsapp');
    $sonra = $ledger->balanceMinor($userId);

    // Sureyi gecmise cek: cron'un gorecegi durumu uret.
    $pdo->prepare('UPDATE number_orders SET expires_at = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s', time() - 60), $siparis['id']]);

    T::same(1, $service->expireDueOrders(new DateTimeImmutable()));
    T::same('expired', $orders->find((int) $siparis['id'])['status']);
    T::same($sonra + (int) $siparis['price_minor'], $ledger->balanceMinor($userId), 'ucret iade edilmeli');
});

T::it('iade ikinci kez calistirilirsa bakiye tekrar artmaz', function () use ($service, $ledger, $userId, $pdo) {
    $siparisId = (int) $pdo->query("SELECT id FROM number_orders WHERE status = 'expired' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $onceki = $ledger->balanceMinor($userId);

    $service->refund($siparisId, 'expired');

    T::same($onceki, $ledger->balanceMinor($userId));
});

T::group('Hata durumlari');

T::it('bakiye yetmiyorsa saglayiciya hic gidilmez', function () use ($service, $pdo, $users, $fake) {
    $fakirId = $users->create('Fakir', 'fakir@ornek.com', 'sifre12345');
    $oncekiCagri = count($fake->calls);

    T::throws(InsufficientBalanceException::class, fn () => $service->purchase($fakirId, 'GB', 'whatsapp'));

    $satinAlmaCagrisi = 0;
    foreach (array_slice($fake->calls, $oncekiCagri) as $call) {
        if (str_contains($call['url'], '/user/buy/')) {
            $satinAlmaCagrisi++;
        }
    }

    T::same(0, $satinAlmaCagrisi, 'saglayiciya satin alma istegi gitmemeli');
    T::same(0, (int) $pdo->query("SELECT COUNT(*) FROM number_orders WHERE user_id = {$fakirId}")->fetchColumn());
});

T::it('ilk saglayici stok veremezse ikinciye gecilir', function () use ($service, $ledger, $userId, $fake) {
    $fake->on('ucuz.test/v1/user/buy/activation', 'no free phones', 400);
    $fake->on('pahali.test/v1/user/buy/activation', json_encode([
        'id' => 333, 'phone' => '+447700900333', 'price' => 15.0,
    ]));

    $siparis = $service->purchase($userId, 'GB', 'whatsapp');

    T::same('pahali', $siparis['provider'], 'failover calismali');
    // 15,00 RUB -> 510 kurus -> %45 marj 740 -> 50'ye yuvarlama 750
    T::same(750, (int) $siparis['price_minor'], 'fiyat gercekte alinan saglayiciya gore hesaplanmali');
});

T::group('Defter butunlugu');

T::it('tum hareketlerin toplami bakiyeye esit', function () use ($ledger, $userId) {
    T::true($ledger->verifyIntegrity($userId));
});

echo "\n" . str_repeat('-', 52) . "\n";
printf("%d gecti, %d basarisiz\n", T::$passed, T::$failed);

@unlink($dbFile);
@unlink($dbFile . '-wal');
@unlink($dbFile . '-shm');

exit(T::$failed === 0 ? 0 : 1);
