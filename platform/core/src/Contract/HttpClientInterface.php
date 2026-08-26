<?php
namespace Onay\Core\Contract;

/**
 * Saglayici adaptorleri HTTP'yi dogrudan cagirmaz; testte sahte istemci gecirilebilsin
 * ve gerektiginde baska bir istemcuyle degistirilebilsin diye bu arayuz kullanilir.
 */
interface HttpClientInterface
{
    /**
     * @param array<string,string|int> $query
     * @param array<string,string>     $headers
     * @return array{status:int, body:string}
     */
    public function get(string $url, array $query = [], array $headers = []): array;
}
