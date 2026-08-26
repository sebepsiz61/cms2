<?php
/**
 * config.php olarak kopyalayin ve doldurun. config.php surum kontrolune girmez.
 */
return [
    'app' => [
        'name'     => 'Sanal Numara',
        'url'      => 'https://ornek.com',
        'debug'    => false,
        'timezone' => 'Europe/Istanbul',
        'locale'   => 'tr',
    ],

    // cPanel'de veritabani ve kullanici adlari her zaman cPanel hesap adiyla
    // on eklenir: hesap adiniz "ornek" ise veritabani "ornek_sanalnumara" olur.
    // Bu on ek yazilmazsa "Unknown database" ya da "Access denied" alirsiniz.
    // Kurulum sorunlarinda: php bin/doctor.php
    'db' => [
        'driver'   => 'mysql',              // 'mysql' ya da 'sqlite'
        'host'     => 'localhost',          // cPanel'de neredeyse her zaman localhost
        'port'     => 3306,
        'database' => 'hesapadi_sanalnumara',
        'username' => 'hesapadi_kullanici',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // Musteriye gosterilen para birimi. Tum tutarlar tam sayi kurus olarak tutulur.
    'currency' => [
        'code'   => 'TRY',
        'symbol' => '₺',
    ],

    // Saglayici para biriminden bizim para birimimize cevrim carpani.
    // Kur degistiginde burasi guncellenir; otomatik kur cekmek isteniyorsa
    // Service\PricingService icine bir kur kaynagi takilir.
    'rates' => [
        'RUB' => 0.34,
        'USD' => 34.00,
        'EUR' => 37.00,
    ],

    'pricing' => [
        'margin_percent'   => 45,    // maliyetin uzerine eklenen yuzde
        'min_profit_minor' => 200,   // asgari kar (kurus)
        'round_to_minor'   => 50,    // satis fiyatini bu katina yuvarla
    ],

    'order' => [
        // Musteriye verilen sure. Saglayicinin iptal penceresinden kisa olmak
        // zorundadir; RefundPolicy bunu ayrica dogrular.
        'safety_margin_seconds' => 300,
        // Ayni siparis icin saglayiciya en sik bu araliklarla sorulur.
        'min_poll_interval'     => 3,
    ],

    'providers' => [
        // Gercek API anahtari hazir olmadan sistemi denemek icin. Disari hic
        // cikmaz; sahte numara ve sahte SMS uretir. Etkinken her sayfada uyari
        // gosterilir. GERCEK MUSTERILERE ACIK SITEDE KAPALI OLMALIDIR.
        'demo' => [
            'enabled'  => false,
            'driver'   => 'demo',
            'priority' => 1,
            'api_key'  => 'gerekmez',
            'currency' => 'TRY',
            'sms_delay_seconds' => 15,        // SMS kac saniye sonra "gelsin"
            'silent_service'    => 'instagram', // bu servise SMS hic gelmez (iade denemesi icin)
            'cancel_window_seconds'    => 900,
            'min_cancel_delay_seconds' => 0,
        ],

        'fivesim' => [
            'enabled'  => false,
            'driver'   => 'fivesim',
            'priority' => 10,
            'api_key'  => '',
            'base_url' => 'https://5sim.net/v1',
            // Saglayicinin guncel dokumanindan dogrulayin.
            'cancel_window_seconds'   => 1200,
            'min_cancel_delay_seconds' => 120,
            'currency' => 'RUB',
        ],
        'smsactivate' => [
            'enabled'  => false,
            'driver'   => 'smsactivate',
            'priority' => 20,
            'api_key'  => '',
            'base_url' => 'https://sms-activate.io/stubs/handler_api.php',
            'cancel_window_seconds'   => 1200,
            'min_cancel_delay_seconds' => 120,
            'currency' => 'RUB',
        ],
    ],

    // Saglayici secim kipi: cheapest | priority
    'provider_selection' => 'cheapest',

    'bank' => [
        'account_name' => 'Ornek Sirket Ltd.',
        'iban'         => 'TR00 0000 0000 0000 0000 0000 00',
        'bank_name'    => 'Ornek Bank',
        'note'         => 'Aciklama alanina referans kodunuzu yaziniz.',
    ],

    'security' => [
        // Oturum cerezleri yalnizca HTTPS uzerinden gonderilsin.
        'secure_cookies' => true,
    ],
];
