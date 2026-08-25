<?php
namespace Onay\App\Kernel;

final class Config
{
    private static array $data = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new \RuntimeException(
                'Yapilandirma dosyasi bulunamadi. config/config.example.php dosyasini config/config.php olarak kopyalayin.'
            );
        }

        self::$data = require $file;
    }

    /** Nokta ile ic ice erisim: Config::get('db.host') */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
