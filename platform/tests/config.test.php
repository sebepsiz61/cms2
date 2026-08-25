<?php
/** Testlerin kullandigi yapilandirma. Iki saglayici da sahte HTTP ile konusur. */
return [
    'app' => ['name' => 'Test', 'url' => 'http://localhost', 'debug' => true, 'timezone' => 'Europe/Istanbul'],
    'db'  => ['driver' => 'sqlite', 'database' => getenv('TEST_DB') ?: ':memory:'],
    'currency' => ['code' => 'TRY', 'symbol' => '₺'],
    'rates'    => ['RUB' => 0.34],
    'pricing'  => ['margin_percent' => 45, 'min_profit_minor' => 200, 'round_to_minor' => 50],
    'order'    => ['safety_margin_seconds' => 300, 'min_poll_interval' => 0],
    'provider_selection' => 'cheapest',
    'providers' => [
        'ucuz' => [
            'enabled' => true, 'driver' => 'fivesim', 'priority' => 10, 'api_key' => 'k',
            'base_url' => 'https://ucuz.test/v1', 'currency' => 'RUB',
            'cancel_window_seconds' => 1200, 'min_cancel_delay_seconds' => 120,
        ],
        'pahali' => [
            'enabled' => true, 'driver' => 'fivesim', 'priority' => 20, 'api_key' => 'k',
            'base_url' => 'https://pahali.test/v1', 'currency' => 'RUB',
            'cancel_window_seconds' => 1200, 'min_cancel_delay_seconds' => 120,
        ],
    ],
    'bank' => ['account_name' => 'Test', 'iban' => 'TR00', 'bank_name' => 'Test', 'note' => ''],
    'security' => ['secure_cookies' => false],
];
