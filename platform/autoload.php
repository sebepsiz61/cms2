<?php
/**
 * Composer gerekmez. cPanel'e dosyalari yukleyip calistirmak yeterli.
 */
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Onay\\Core\\' => __DIR__ . '/core/src/',
        'Onay\\App\\'  => __DIR__ . '/app/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
