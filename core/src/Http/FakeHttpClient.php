<?php
namespace Onay\Core\Http;

use Onay\Core\Contract\HttpClientInterface;

/**
 * Testte ve staging'de kullanilan sahte istemci. Saglayici hatalarini, bos stogu ve
 * zaman asimini gercek para harcamadan denemeye yarar.
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var array<string, array{status:int, body:string}> */
    private array $responses = [];

    /** @var array<int, array{url:string, query:array}> */
    public array $calls = [];

    /** Yanit anahtari, istenen URL'de gecen herhangi bir alt dizedir. */
    public function on(string $urlContains, string $body, int $status = 200): self
    {
        $this->responses[$urlContains] = ['status' => $status, 'body' => $body];
        return $this;
    }

    public function get(string $url, array $query = [], array $headers = []): array
    {
        $full = $query === [] ? $url : $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        $this->calls[] = ['url' => $full, 'query' => $query];

        foreach ($this->responses as $needle => $response) {
            if (str_contains($full, $needle)) {
                return $response;
            }
        }

        throw new \RuntimeException('FakeHttpClient icin tanimsiz istek: ' . $full);
    }
}
