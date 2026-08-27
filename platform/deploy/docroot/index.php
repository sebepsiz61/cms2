<?php
/**
 * Dokuman kokune konan giris dosyasi.
 *
 * Amaci: adresin "example.com/giris" gorunmesi. Dokuman kokunu
 * platform/public yapamadiginizda bu dosya ile ".htaccess" dosyasini
 * public_html/ icine kopyalayin; istekler buradan uygulamaya devredilir,
 * ziyaretci "platform" ya da "public" klasorunu hic gormez.
 *
 * Uygulamayi iki yerde arar:
 *   public_html/platform/public/index.php      (platform, public_html icinde)
 *   public_html/../platform/public/index.php   (platform, public_html yaninda)
 *
 * Ikincisi daha guvenlidir: uygulama dosyalari dokuman kokunun disinda kalir.
 */
declare(strict_types=1);

$adaylar = [
    __DIR__ . '/platform/public/index.php',
    dirname(__DIR__) . '/platform/public/index.php',
];

foreach ($adaylar as $aday) {
    if (is_file($aday)) {
        // Uygulama kendi konumunu SCRIPT_NAME'den okur. Burada kokte
        // calistigimizi soyluyoruz ki uretilen adresler "/giris" olsun.
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_SERVER['PHP_SELF'] = '/index.php';

        require $aday;
        return;
    }
}

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');

echo "Uygulama bulunamadi.\n\n";
echo "Aranan yerler:\n";
foreach ($adaylar as $aday) {
    echo '  ' . $aday . "\n";
}
echo "\nplatform/ klasorunu bu dosyanin yanina ya da bir ust dizine koyun.\n";
