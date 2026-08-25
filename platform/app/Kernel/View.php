<?php
namespace Onay\App\Kernel;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layout/app'): string
    {
        $content = self::capture($template, $data);

        if ($layout === '') {
            return $content;
        }

        return self::capture($layout, $data + [
            'content' => $content,
            'flash'   => Session::pullFlash(),
        ]);
    }

    private static function capture(string $template, array $data): string
    {
        $file = dirname(__DIR__, 2) . '/views/' . $template . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException('Sablon bulunamadi: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
