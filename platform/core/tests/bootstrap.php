<?php
/**
 * PHPUnit kurulamadigi icin (paket deposu bu ortamda kapali) kucuk bir kosucu.
 * Laravel projesine tasindiginda testler PHPUnit'e birebir cevrilebilir.
 */
require __DIR__ . '/../autoload.php';

final class T
{
    public static int $passed = 0;
    public static int $failed = 0;
    private static string $group = '';

    public static function group(string $name): void
    {
        self::$group = $name;
        echo "\n" . $name . "\n";
    }

    public static function it(string $name, callable $fn): void
    {
        try {
            $fn();
            self::$passed++;
            echo "  ok    " . $name . "\n";
        } catch (\Throwable $e) {
            self::$failed++;
            echo "  HATA  " . $name . "\n";
            echo "        " . $e->getMessage() . "\n";
            echo "        " . basename($e->getFile()) . ':' . $e->getLine() . "\n";
        }
    }

    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \Exception(sprintf(
                '%sBeklenen %s, gelen %s',
                $message === '' ? '' : $message . ' — ',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    public static function true(bool $value, string $message = 'Dogru olmasi bekleniyordu'): void
    {
        if (!$value) {
            throw new \Exception($message);
        }
    }

    public static function throws(string $expectedClass, callable $fn): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $expectedClass) {
                throw new \Exception(sprintf('%s bekleniyordu, %s geldi: %s', $expectedClass, $e::class, $e->getMessage()));
            }
            return $e;
        }

        throw new \Exception($expectedClass . ' firlatilmasi bekleniyordu, hicbir sey firlatilmadi.');
    }
}
