<?php
namespace Onay\Core\Http;

use Onay\Core\Contract\HttpClientInterface;

final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly int $timeoutSeconds = 15,
        private readonly int $connectTimeoutSeconds = 5,
    ) {
    }

    public function get(string $url, array $query = [], array $headers = []): array
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_HTTPHEADER     => $normalized,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('HTTP istegi basarisiz: ' . $error);
        }

        return ['status' => $status, 'body' => (string) $body];
    }
}
