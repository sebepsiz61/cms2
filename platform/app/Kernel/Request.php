<?php
namespace Onay\App\Kernel;

final class Request
{
    /**
     * Uygulama alt klasorden yayinlanabilir (dokuman koku degistirilemeyen
     * paylasimli sunucularda sik). O durumda REQUEST_URI "/platform/public/giris"
     * gelir; rota tablosu ise "/giris" bilir. Bu on ek burada tespit edilip
     * yoldan dusulur, uretilen baglantilara ise geri eklenir.
     */
    public static string $basePath = '';

    private function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $body,
        public readonly array $files,
    ) {
    }

    public static function capture(): self
    {
        self::$basePath = self::detectBasePath();

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (self::$basePath !== '' && str_starts_with($path, self::$basePath)) {
            $path = substr($path, strlen(self::$basePath));
        }

        $path = '/' . trim($path, '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path === '//' ? '/' : $path,
            $_GET,
            $_POST,
            $_FILES,
        );
    }

    /** index.php'nin bulundugu dizin; kok kurulumda bos dizedir. */
    private static function detectBasePath(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        if ($script === '') {
            return '';
        }

        $dizin = rtrim(str_replace('\\', '/', dirname($script)), '/');

        return $dizin === '' || $dizin === '.' ? '' : $dizin;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
