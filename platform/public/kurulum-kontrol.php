<?php
/**
 * Tek dosyalik kurulum kontrolu. Hicbir seye bagimli degildir; projeyi kurmadan
 * once de calisir. public_html icine atip tarayicidan acabilirsiniz:
 *
 *     https://alanadiniz.com/kurulum-kontrol.php
 *
 * Komut satirindan da calisir:
 *
 *     php kurulum-kontrol.php
 *
 * Eksik olan her madde icin hem cPanel hem WHM (EasyApache 4) yolunu yazar.
 *
 * !! Kontrol bittiginde bu dosyayi SILIN. Sunucu bilgilerini disari acar. !!
 */
declare(strict_types=1);

$cli = PHP_SAPI === 'cli';

/** Bir maddeyi degerlendirip sonuc dizisi olarak dondurur. */
function madde(string $ad, bool $ok, string $deger, string $cpanel = '', string $whm = '', string $komut = ''): array
{
    return compact('ad', 'ok', 'deger', 'cpanel', 'whm', 'komut');
}

$phpSurum = PHP_VERSION;
$surumOk  = PHP_VERSION_ID >= 80200 && PHP_VERSION_ID < 80500;

$bolumler = [];

// --- PHP surumu ---------------------------------------------------------
$bolumler['PHP'] = [
    madde(
        'Surum 8.2 veya uzeri',
        $surumOk,
        $phpSurum . ' (' . PHP_SAPI . ')',
        'MultiPHP Manager > alan adini sec > PHP 8.2',
        'WHM > MultiPHP Manager > alan adini sec > ea-php82. Surum listede yoksa once '
        . 'WHM > EasyApache 4 > Customize > PHP Versions bolumunden PHP 8.2 kurulmali.',
        'whmapi1 php_set_vhost_versions version=ea-php82 vhost=alanadiniz.com'
    ),
];

// --- Eklentiler ---------------------------------------------------------
// EasyApache 4 paket adlari ea-php82-php-<ad> bicimindedir. pdo_mysql ayri bir
// paket degildir; mysqlnd paketiyle birlikte gelir.
$eklentiler = [
    'pdo'       => ['Veritabani erisimi',            'ea-php82-php-pdo'],
    'pdo_mysql' => ['MySQL baglantisi',              'ea-php82-php-mysqlnd'],
    'curl'      => ['Saglayici API cagrilari',       'ea-php82-php-curl'],
    'mbstring'  => ['Turkce karakter isleme',        'ea-php82-php-mbstring'],
    'fileinfo'  => ['Dekont MIME dogrulamasi',       'ea-php82-php-fileinfo'],
    'json'      => ['API yanitlarini cozumleme',     '(PHP 8 cekirdeginde, ayri paket yok)'],
];

foreach ($eklentiler as $eklenti => [$nicin, $paket]) {
    $yuklu = extension_loaded($eklenti);
    $ea4Paket = str_starts_with($paket, '(') ? '' : $paket;

    $bolumler['Eklentiler'][] = madde(
        $eklenti,
        $yuklu,
        $yuklu ? 'yuklu — ' . $nicin : 'YOK — ' . $nicin,
        "Select PHP Version > Extensions > '{$eklenti}' kutusunu isaretle "
        . ($eklenti === 'pdo_mysql' ? "(listede 'nd_pdo_mysql' adiyla gorunur)" : ''),
        $ea4Paket === ''
            ? 'PHP 8 ile birlikte gelir; ayrica kurulmaz. Yoksa PHP kurulumu bozuktur.'
            : "EasyApache 4 > Customize > PHP Extensions > arama kutusuna '{$eklenti}' yaz, "
              . "cikan {$ea4Paket} paketini isaretle, Provision et.",
        $ea4Paket === '' ? '' : "yum install -y {$ea4Paket}"
    );
}

// --- PDO suruculeri -----------------------------------------------------
$suruculer = extension_loaded('pdo') ? PDO::getAvailableDrivers() : [];
$bolumler['PDO suruculeri'][] = madde(
    'Kurulu suruculer',
    in_array('mysql', $suruculer, true),
    $suruculer === [] ? 'hicbiri' : implode(', ', $suruculer),
    "Select PHP Version > Extensions > 'nd_pdo_mysql'",
    "EasyApache 4 > Customize > PHP Extensions > 'mysqlnd' paketi",
    'yum install -y ea-php82-php-mysqlnd'
);

// --- MySQL sunucusu -----------------------------------------------------
// Surucu varsa gercekten baglanabiliyor muyuz diye bakariz; kimlik bilgisi
// istemeden yalnizca sunucunun ayakta olup olmadigini anlariz.
if (in_array('mysql', $suruculer, true)) {
    $mesaj = 'baglanti denenmedi';
    $ok = true;

    try {
        new PDO('mysql:host=localhost', '__kontrol__', '__kontrol__');
    } catch (PDOException $e) {
        $hata = $e->getMessage();
        // "Access denied" = sunucu ayakta, sadece kullanici yanlis. Bu iyi haber.
        $ok = str_contains($hata, 'Access denied');
        $mesaj = $ok
            ? 'MySQL sunucusu ayakta (kimlik reddi bekleniyordu)'
            : 'MySQL sunucusuna ulasilamiyor: ' . $hata;
    }

    $bolumler['MySQL sunucusu'][] = madde(
        'localhost erisimi',
        $ok,
        $mesaj,
        'MySQL Databases ekraninin acilmasi gerekir',
        'WHM > SQL Services > MySQL/MariaDB servisinin calistigini dogrula. '
        . 'Soket hatasi aliyorsan config icinde host degerini 127.0.0.1 yap.',
        'systemctl status mysqld'
    );
}

// --- PHP ayarlari -------------------------------------------------------
$ayarlar = [
    ['file_uploads', (bool) ini_get('file_uploads'), ini_get('file_uploads') ? 'acik' : 'KAPALI', 'Dekont yuklemesi icin gerekli'],
    ['memory_limit', true, (string) ini_get('memory_limit'), '128M yeterli'],
    ['max_execution_time', true, (string) ini_get('max_execution_time'), '30 sn yeterli'],
    ['date.timezone', true, (string) (ini_get('date.timezone') ?: 'ayarsiz — uygulama kendi ayarlar'), ''],
];

foreach ($ayarlar as [$ad, $ok, $deger, $not]) {
    $bolumler['PHP ayarlari'][] = madde(
        $ad,
        $ok,
        $deger . ($not === '' ? '' : ' — ' . $not),
        'MultiPHP INI Editor',
        'WHM > MultiPHP INI Editor > Editor Mode',
        ''
    );
}

// --- Adres yapisi -------------------------------------------------------
// "Sayfa bulunamadi" hatalarinin iki sebebi var: uygulama alt klasorde ama
// dokuman koku ayarlanmamis, ya da mod_rewrite kapali. Ikisini de ayirt ederiz.
if (!$cli) {
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $taban = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $kokte = $taban === '' || $taban === '.';

    $bolumler['Adres yapisi'][] = madde(
        'Kurulum yeri',
        true,
        $kokte ? 'alan adi koku (onerilen)' : 'alt klasor: ' . $taban,
        '',
        '',
        ''
    );

    $rewrite = function_exists('apache_get_modules')
        ? in_array('mod_rewrite', apache_get_modules(), true)
        : null;

    $bolumler['Adres yapisi'][] = madde(
        'mod_rewrite',
        $rewrite !== false,
        $rewrite === null ? 'okunamadi (PHP-FPM/CGI olabilir)' : ($rewrite ? 'acik' : 'KAPALI'),
        '',
        'WHM > EasyApache 4 > Apache Modules bolumunde mod_rewrite isaretli olmali.',
        'httpd -M | grep rewrite'
    );
}

// --- Proje dosyalari ----------------------------------------------------
// Bu dosya platform/public icindeyse proje kokunu bulabiliriz.
$kok = dirname(__DIR__);
$projeVar = is_file($kok . '/autoload.php') && is_dir($kok . '/app');

if ($projeVar) {
    foreach (['config/config.php', 'schema/mysql.sql', 'bin/install.php'] as $dosya) {
        $bolumler['Proje dosyalari'][] = madde(
            $dosya,
            is_file($kok . '/' . $dosya),
            is_file($kok . '/' . $dosya) ? 'var' : 'yok',
            $dosya === 'config/config.php' ? 'cp config/config.example.php config/config.php' : '',
            '',
            ''
        );
    }

    foreach (['storage', 'storage/logs', 'storage/uploads'] as $dizin) {
        $yol = $kok . '/' . $dizin;
        $yazilir = is_dir($yol) && is_writable($yol);
        $bolumler['Proje dosyalari'][] = madde(
            $dizin . '/ yazilabilir',
            $yazilir,
            is_dir($yol) ? ($yazilir ? 'evet' : 'HAYIR') : 'dizin yok',
            '',
            '',
            "mkdir -p {$yol} && chmod 755 {$yol}"
        );
    }
}

// --- Sonuc --------------------------------------------------------------
$eksikler = [];
foreach ($bolumler as $baslik => $maddeler) {
    foreach ($maddeler as $m) {
        if (!$m['ok']) {
            $eksikler[] = $m;
        }
    }
}

$sonrakiAdim = $projeVar
    ? 'php bin/doctor.php  (proje kokunde, tam teshis icin)'
    : 'Dosyalari sunucuya yukleyip kurulum adimlarina devam edin.';

/* ---------------------------------------------------------------------- */

if ($cli) {
    echo "\nKurulum kontrolu — PHP {$phpSurum} (" . PHP_SAPI . ")\n" . str_repeat('=', 60) . "\n";

    foreach ($bolumler as $baslik => $maddeler) {
        echo "\n{$baslik}\n";
        foreach ($maddeler as $m) {
            printf("  %-8s %-26s %s\n", $m['ok'] ? '[OK]' : '[EKSIK]', $m['ad'], $m['deger']);
            if (!$m['ok'] && $m['whm'] !== '') {
                echo "           WHM: " . $m['whm'] . "\n";
            }
            if (!$m['ok'] && $m['komut'] !== '') {
                echo "           Komut: " . $m['komut'] . "\n";
            }
        }
    }

    echo "\n" . str_repeat('=', 60) . "\n";
    echo $eksikler === []
        ? "Her sey yolunda.\nSonraki adim: {$sonrakiAdim}\n\n"
        : count($eksikler) . " eksik var. Yukaridaki WHM/komut satirlarini uygulayin.\n\n";

    echo "Bu dosyayi kontrol bittikten sonra silin.\n\n";
    exit($eksikler === [] ? 0 : 1);
}

header('Content-Type: text/html; charset=utf-8');
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kurulum kontrolu</title>
<style>
  :root{
    --bg:#f1f4f8; --kart:#fff; --ink:#131a26; --soluk:#5d6a7e; --cizgi:#d3dbe6;
    --mavi:#2a4b8d; --ok:#22664a; --okz:#ddede4; --hata:#a03a2a; --hataz:#f7e4df;
    --uyari:#8a5a12; --uyariz:#f6ebd7; --kod:#edf1f7;
  }
  @media (prefers-color-scheme: dark){
    :root{
      --bg:#0e1219; --kart:#161d27; --ink:#e3e9f2; --soluk:#8c9ab0; --cizgi:#28313e;
      --mavi:#84aaeb; --ok:#71c29a; --okz:#152e24; --hata:#e28c79; --hataz:#37201b;
      --uyari:#dcac61; --uyariz:#332916; --kod:#11171f;
    }
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);
       font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
  .k{max-width:58rem;margin:0 auto;padding:2rem 1.2rem 4rem}
  h1{font-size:1.6rem;margin:0 0 .3rem}
  .alt{color:var(--soluk);margin:0 0 1.5rem;font-size:.95rem}
  .ozet{padding:1rem 1.2rem;border-radius:4px;margin-bottom:1.5rem;border:1px solid var(--cizgi)}
  .ozet.iyi{background:var(--okz);border-color:var(--ok)}
  .ozet.kotu{background:var(--hataz);border-color:var(--hata)}
  .ozet strong{font-size:1.1rem}
  h2{font-size:1rem;margin:1.8rem 0 .5rem;text-transform:uppercase;
     letter-spacing:.08em;color:var(--soluk)}
  table{width:100%;border-collapse:collapse;background:var(--kart);
        border:1px solid var(--cizgi);border-radius:4px;overflow:hidden}
  td{padding:.6rem .8rem;border-bottom:1px solid var(--cizgi);vertical-align:top;font-size:.92rem}
  tr:last-child td{border-bottom:0}
  .rozet{display:inline-block;font-size:.7rem;font-weight:700;letter-spacing:.06em;
         padding:.15rem .45rem;border-radius:2px;white-space:nowrap}
  .rozet.ok{background:var(--okz);color:var(--ok)}
  .rozet.yok{background:var(--hataz);color:var(--hata)}
  .ad{font-family:ui-monospace,Menlo,monospace;font-weight:600}
  .cozum{margin-top:.4rem;padding:.5rem .7rem;background:var(--uyariz);
         border-left:3px solid var(--uyari);border-radius:0 3px 3px 0;font-size:.86rem}
  .cozum b{color:var(--uyari)}
  code,pre{font-family:ui-monospace,Menlo,monospace;font-size:.84em}
  pre{background:var(--kod);border:1px solid var(--cizgi);padding:.5rem .7rem;
      border-radius:3px;overflow-x:auto;margin:.4rem 0 0}
  .sil{margin-top:2.5rem;padding:1rem 1.2rem;background:var(--hataz);
       border:1px solid var(--hata);border-radius:4px;color:var(--ink)}
  .sil strong{color:var(--hata)}
</style>
</head>
<body>
<div class="k">

<h1>Kurulum kontrolu</h1>
<p class="alt">PHP <?= $e($phpSurum) ?> · <?= $e(PHP_SAPI) ?> · <?= $e(php_uname('s') . ' ' . php_uname('r')) ?></p>

<div class="ozet <?= $eksikler === [] ? 'iyi' : 'kotu' ?>">
  <strong><?= $eksikler === [] ? 'Her sey yolunda.' : count($eksikler) . ' eksik var.' ?></strong>
  <div style="margin-top:.3rem;font-size:.92rem">
    <?= $eksikler === []
        ? 'Sonraki adim: <code>' . $e($sonrakiAdim) . '</code>'
        : 'Asagida sari kutulardaki adimlari uygulayin, sonra sayfayi yenileyin.' ?>
  </div>
</div>

<?php foreach ($bolumler as $baslik => $maddeler): ?>
  <h2><?= $e($baslik) ?></h2>
  <table>
    <?php foreach ($maddeler as $m): ?>
    <tr>
      <td style="width:5.5rem"><span class="rozet <?= $m['ok'] ? 'ok' : 'yok' ?>"><?= $m['ok'] ? 'TAMAM' : 'EKSIK' ?></span></td>
      <td style="width:13rem" class="ad"><?= $e($m['ad']) ?></td>
      <td>
        <?= $e($m['deger']) ?>
        <?php if (!$m['ok'] && ($m['whm'] !== '' || $m['cpanel'] !== '' || $m['komut'] !== '')): ?>
          <div class="cozum">
            <?php if ($m['whm'] !== ''): ?><div><b>WHM:</b> <?= $e($m['whm']) ?></div><?php endif; ?>
            <?php if ($m['cpanel'] !== ''): ?><div style="margin-top:.25rem"><b>cPanel:</b> <?= $e($m['cpanel']) ?></div><?php endif; ?>
            <?php if ($m['komut'] !== ''): ?><pre><?= $e($m['komut']) ?></pre><?php endif; ?>
          </div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endforeach; ?>

<h2>WHM'de eklenti kurma sirasi</h2>
<table>
  <tr><td style="width:2rem">1</td><td>WHM &gt; <b>EasyApache 4</b> &gt; yuklu profilde <b>Customize</b></td></tr>
  <tr><td>2</td><td><b>PHP Versions</b> sekmesinde <code>ea-php82</code> isaretli olsun</td></tr>
  <tr><td>3</td><td><b>PHP Extensions</b> sekmesinde arama kutusuna eklenti adini yaz, cikan <code>ea-php82-php-*</code> paketini isaretle</td></tr>
  <tr><td>4</td><td>Sag altta <b>Review</b> &gt; <b>Provision</b> — kurulum birkac dakika surer</td></tr>
  <tr><td>5</td><td>WHM &gt; <b>MultiPHP Manager</b> ile alan adini <code>ea-php82</code> yap</td></tr>
  <tr><td>6</td><td>Bu sayfayi yenile</td></tr>
</table>
<p class="alt" style="margin-top:.6rem">
  Komut satirini tercih ederseniz ayni isi <code>yum install -y ea-php82-php-mysqlnd ea-php82-php-curl ea-php82-php-mbstring</code> yapar;
  sonrasinda <code>systemctl restart httpd</code> ve varsa <code>systemctl restart ea-php82-php-fpm</code>.
</p>

<div class="sil">
  <strong>Bu dosyayi silin.</strong> Kontrol bittiginde <code>kurulum-kontrol.php</code> dosyasini sunucudan kaldirin —
  PHP surumunu ve yuklu eklentileri disariya acar, bu bilgi saldirgan icin ise yarar.
  <pre>rm <?= $e(__FILE__) ?></pre>
</div>

</div>
</body>
</html>
