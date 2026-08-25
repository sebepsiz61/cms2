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

    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'cpanel_kullanici_db',
        'username' => 'cpanel_kullanici',
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
