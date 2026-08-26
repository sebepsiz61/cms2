<?php
/**
 * Saglayici baglanti testi. API anahtarinin calisip calismadigini, katalog
 * senkronundan ve kod eslemesinden BAGIMSIZ olarak dogrular.
 *
 *     php bin/provider-test.php
 *     php bin/provider-test.php fivesim      (yalnizca bir saglayici)
 *
 * Neden ayri bir betik: yonetim panelindeki "Katalogu senkronla" iki farkli
 * sorunu ayni hataya dusuruyor — anahtar gecersiz mi, yoksa kodlar eslenmemis mi?
 * Bu betik ikisini ayirir.
 *
 * Numara SATIN ALMAZ, para harcamaz. Yalnizca okuma cagrilari yapar.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/autoload.php';

use Onay\App\Kernel\Config;
use Onay\App\Kernel\Database;
use Onay\App\Service\ProviderFactory;
use Onay\Core\Exception\ProviderException;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnizca komut satirindan calistirilir.\n");
}

Config::load($root . '/config/config.php');

$sadece = $argv[1] ?? null;

// Kod eslemeleri veritabanindan okunur; sema kurulmadan bu betik calisamaz.
try {
    Database::pdo()->query('SELECT 1 FROM provider_codes LIMIT 1');
} catch (\PDOException $e) {
    echo "\nVeritabani hazir degil: " . $e->getMessage() . "\n\n";
    echo "Once semayi kurun:\n";
    echo "  php bin/install.php <admin-eposta> <sifre>\n\n";
    exit(1);
} catch (\Throwable $e) {
    echo "\n" . $e->getMessage() . "\n\nTeshis icin: php bin/doctor.php\n\n";
    exit(1);
}

$factory = new ProviderFactory();

$tumSaglayicilar = (array) Config::get('providers', []);
$acik = array_filter($tumSaglayicilar, static fn (array $s): bool => !empty($s['enabled']));

echo "\nSaglayici testi\n" . str_repeat('=', 62) . "\n";

if ($acik === []) {
    echo "\nHicbir saglayici acik degil.\n\n";
    echo "config/config.php icinde en az bir saglayicinin 'enabled' degerini true\n";
    echo "yapin ve 'api_key' alanini doldurun. Tanimli saglayicilar:\n";
    foreach (array_keys($tumSaglayicilar) as $ad) {
        echo "  - {$ad}\n";
    }
    echo "\n";
    exit(1);
}

$hata = 0;

foreach ($factory->registry()->enabled() as $provider) {
    $ad = $provider->name();

    if ($sadece !== null && $sadece !== $ad) {
        continue;
    }

    $ayar = $tumSaglayicilar[$ad] ?? [];
    $anahtar = (string) ($ayar['api_key'] ?? '');
    $caps = $provider->capabilities();

    echo "\n{$ad}\n" . str_repeat('-', 62) . "\n";
    printf("  surucu        %s\n", $ayar['driver'] ?? '?');
    printf("  adres         %s\n", $ayar['base_url'] ?? '?');
    printf("  anahtar       %s\n", $anahtar === ''
        ? 'BOS — doldurulmali'
        : substr($anahtar, 0, 4) . str_repeat('*', max(0, min(20, strlen($anahtar) - 8))) . substr($anahtar, -4)
          . ' (' . strlen($anahtar) . ' karakter)');
    printf("  para birimi   %s\n", $caps->currency);
    printf("  iptal suresi  %d sn (musteriye %d sn verilir)\n",
        $caps->cancelWindowSeconds,
        max(0, $caps->cancelWindowSeconds - (int) Config::get('order.safety_margin_seconds', 300)));

    if ($anahtar === '') {
        echo "\n  SONUC: api_key bos. Test edilemez.\n";
        $hata++;
        continue;
    }

    // 1) Bakiye — anahtarin gecerli olup olmadiginin en kisa kaniti.
    echo "\n  [1] Bakiye sorgusu\n";
    try {
        $bakiye = $provider->balanceMinor();
        printf("      OK — saglayicidaki bakiyemiz: %s %s\n", number_format($bakiye / 100, 2), $caps->currency);

        if ($bakiye === 0) {
            echo "      UYARI: bakiye sifir. Numara satin alinamaz.\n";
        }
    } catch (ProviderException $e) {
        echo "      HATA — " . $e->getMessage() . "\n";
        echo "      Anahtar gecersiz ya da adres yanlis olabilir. Diger testler atlandi.\n";
        $hata++;
        continue;
    } catch (\Throwable $e) {
        echo "      HATA — " . $e->getMessage() . "\n";
        echo "      Aga cikilamiyor olabilir (guvenlik duvari, DNS).\n";
        $hata++;
        continue;
    }

    // 2) Katalog — anahtar calisiyor, simdi kac teklif geliyor?
    echo "\n  [2] Katalog\n";
    try {
        $teklifler = $provider->catalog();
        printf("      OK — bizim kodlarimiza eslenen teklif: %d\n", count($teklifler));

        if ($teklifler !== []) {
            echo "      Ornek:\n";
            foreach (array_slice($teklifler, 0, 5) as $t) {
                printf("        %-6s %-12s %8s %s   stok %d\n",
                    $t->countryCode, $t->serviceCode,
                    number_format($t->costMinor / 100, 2), $t->currency, $t->stock);
            }
        }
    } catch (\Throwable $e) {
        echo "      HATA — " . $e->getMessage() . "\n";
        $hata++;
        continue;
    }

    // 3) Eslenmemis kodlar — katalog bos ise sebep neredeyse her zaman burasi.
    $eslenmemis = array_values(array_filter(
        $factory->mapper()->unmapped(),
        static fn (array $k): bool => $k['provider'] === $ad
    ));

    echo "\n  [3] Kod eslemesi\n";

    if ($eslenmemis === []) {
        echo "      OK — eslenmemis kod yok.\n";
    } else {
        printf("      %d kod eslenmemis. Bunlar katalogda gorunmez.\n", count($eslenmemis));

        $ulke = array_filter($eslenmemis, static fn (array $k): bool => $k['kind'] === 'country');
        $servis = array_filter($eslenmemis, static fn (array $k): bool => $k['kind'] === 'service');

        foreach (['ulke' => $ulke, 'servis' => $servis] as $tur => $liste) {
            if ($liste === []) {
                continue;
            }
            $kodlar = array_map(static fn (array $k): string => $k['code'], array_slice($liste, 0, 15));
            printf("      %s (%d): %s%s\n", $tur, count($liste), implode(', ', $kodlar),
                count($liste) > 15 ? ' …' : '');
        }

        echo "      Yonetim > Katalog ekranindan eslestirin.\n";
    }

    if (count($teklifler) === 0) {
        echo "\n  SONUC: anahtar calisiyor ama katalog bos.\n";
        echo "  Once Yonetim > Katalog ekranindan ulke ve servisleri ekleyin,\n";
        echo "  sonra yukaridaki saglayici kodlarini bunlarla eslestirin.\n";
        $hata++;
    } else {
        echo "\n  SONUC: bu saglayici satisa hazir.\n";
    }
}

echo "\n" . str_repeat('=', 62) . "\n";
echo $hata === 0
    ? "Tum saglayicilar hazir.\n\n"
    : "{$hata} saglayicida is var. Yukaridaki SONUC satirlarina bakin.\n\n";

exit($hata === 0 ? 0 : 1);
