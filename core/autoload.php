<?php
/**
 * Composer olmadan da calisabilmesi icin minimal PSR-4 yukleyici.
 * Laravel projesine tasindiginda composer autoload devralir.
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
