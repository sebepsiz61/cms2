<?php
/**
 * Gercek es zamanlilik testi: pcntl_fork ile ayni anda N surec ayni kullanicinin
 * bakiyesinden harcama yapmaya calisir.
 *
 * Bu test ayri duruyor cunku surec catallamak gerekiyor; run.php icinde kosmaz.
 * Kanitlamak istedigi sey su: bakiye tek kolonda tutulup "oku, kontrol et, yaz"
 * yapilsaydi 10 surecin hepsi gecerdi ve bakiye eksiye duserdi.
 */
require __DIR__ . '/../autoload.php';

use Onay\Core\Contract\LedgerInterface;
use Onay\Core\Exception\InsufficientBalanceException;
use Onay\Core\Wallet\PdoLedger;

$dbFile = sys_get_temp_dir() . '/onay_concurrency_' . getmypid() . '.sqlite';
@unlink($dbFile);

function baglan(string $dbFile): PDO
{
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA busy_timeout = 10000');   // kilit beklerken hemen pes etme
    $pdo->exec('PRAGMA journal_mode = WAL');
    return $pdo;
}

$pdo = baglan($dbFile);
$pdo->exec(file_get_contents(__DIR__ . '/../schema/ledger.sql'));
$pdo->exec("INSERT INTO users (id, email) VALUES (1, 'yaris@ornek.com')");

// Baslangic bakiyesi de deftere yazilir. Kolona dogrudan para koymak, butunluk
// kontrolunun yakaladigi turden bir hatadir: defterde karsiligi olmayan bakiye.
(new PdoLedger($pdo))->credit(1, 1000, LedgerInterface::TYPE_LOAD, 'baslangic-bakiyesi');
unset($pdo);

$surecSayisi = 10;
$harcama     = 200;   // 1000 / 200 = tam olarak 5 tanesi gecmeli
$pidler      = [];

for ($i = 0; $i < $surecSayisi; $i++) {
    $pid = pcntl_fork();

    if ($pid === 0) {
        // Cocuk surec: kendi baglantisini acar, hepsi ayni anda dener.
        usleep(50000);
        $ledger = new PdoLedger(baglan($dbFile));

        try {
            $ledger->debit(1, $harcama, LedgerInterface::TYPE_SPEND, 'siparis-' . $i, 'number_order', $i);
            exit(0);   // basarili harcama
        } catch (InsufficientBalanceException) {
            exit(1);   // bakiye yetmedi, dogru davranis
        } catch (Throwable $e) {
            fwrite(STDERR, "beklenmeyen hata: " . $e->getMessage() . "\n");
            exit(2);
        }
    }

    $pidler[] = $pid;
}

$basarili = $reddedilen = $hatali = 0;
foreach ($pidler as $pid) {
    pcntl_waitpid($pid, $status);
    match (pcntl_wexitstatus($status)) {
        0       => $basarili++,
        1       => $reddedilen++,
        default => $hatali++,
    };
}

$ledger = new PdoLedger(baglan($dbFile));
$son = $ledger->balanceMinor(1);
$butunluk = $ledger->verifyIntegrity(1);

echo "Es zamanli harcama testi\n";
echo str_repeat('-', 52) . "\n";
printf("  %d surec ayni anda %d minor harcamaya calisti (baslangic 1000)\n", $surecSayisi, $harcama);
printf("  basarili: %d   bakiye yetmedi: %d   beklenmeyen hata: %d\n", $basarili, $reddedilen, $hatali);
printf("  son bakiye: %d\n", $son);
printf("  defter butunlugu: %s\n", $butunluk ? 'tutarli' : 'BOZUK');

@unlink($dbFile);
@unlink($dbFile . '-wal');
@unlink($dbFile . '-shm');

$gecti = $basarili === 5 && $son === 0 && $hatali === 0 && $butunluk;
echo $gecti ? "\nGECTI: cift harcama olmadi, bakiye eksiye dusmedi.\n"
            : "\nBASARISIZ: es zamanlilik korumasi calismiyor.\n";

exit($gecti ? 0 : 1);
