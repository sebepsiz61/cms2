<?php
/**
 * Minimal PSR-4 yukleyici. Bu proje Composer kullanmaz; dosyalari sunucuya
 * yuklemek yeterlidir.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Onay\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});
