<?php
namespace Onay\Core\Provider;

use Onay\Core\Contract\HttpClientInterface;
use Onay\Core\Contract\NumberProviderInterface;
use Onay\Core\Dto\Offer;
use Onay\Core\Dto\PollResult;
use Onay\Core\Dto\ProviderCapabilities;
use Onay\Core\Dto\ProviderStatus;
use Onay\Core\Dto\PurchaseResult;
use Onay\Core\Dto\Sms;
use Onay\Core\Exception\OutOfStockException;
use Onay\Core\Exception\ProviderBalanceException;
use Onay\Core\Exception\ProviderException;

/**
 * REST + JSON + Bearer token kullanan saglayici ailesi (5sim ve benzerleri).
 *
 * Uc adresleri ve iade penceresi yapilandirmadan gelir; saglayici dokumani
 * degistiginde kod degil config guncellenir.
 */
final class FiveSimProvider implements NumberProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CatalogMapper $mapper,
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://5sim.net/v1',
        private readonly string $providerName = '5sim',
        private readonly ProviderCapabilities $capabilities = new ProviderCapabilities(
            currency: 'RUB',
            cancelWindowSeconds: 1200,
            minCancelDelaySeconds: 120,
            supportsRental: true,
        ),
    ) {
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function capabilities(): ProviderCapabilities
    {
        return $this->capabilities;
    }

    public function balanceMinor(): int
    {
        $data = $this->request('/user/profile');
        return $this->toMinor($data['balance'] ?? 0);
    }

    public function catalog(): array
    {
        $data = $this->request('/guest/prices');
        $offers = [];

        // Yapi: {ulke: {urun: {operator: {cost, count}}}}
        foreach ($data as $country => $products) {
            if (!is_array($products)) {
                continue;
            }
            $canonicalCountry = $this->mapper->toCanonicalCountry($this->providerName, (string) $country);
            if ($canonicalCountry === null) {
                continue;
            }

            foreach ($products as $product => $operators) {
                if (!is_array($operators)) {
                    continue;
                }
                $canonicalService = $this->mapper->toCanonicalService($this->providerName, (string) $product);
                if ($canonicalService === null) {
                    continue;
                }

                foreach ($operators as $operator => $info) {
                    if (!is_array($info) || !isset($info['cost'])) {
                        continue;
                    }
                    $offers[] = new Offer(
                        provider: $this->providerName,
                        countryCode: $canonicalCountry,
                        serviceCode: $canonicalService,
                        costMinor: $this->toMinor($info['cost']),
                        currency: $this->capabilities->currency,
                        stock: (int) ($info['count'] ?? 0),
                        providerCountry: (string) $country,
                        providerService: (string) $product,
                        operator: (string) $operator,
                    );
                }
            }
        }

        return $offers;
    }

    public function buy(Offer $offer): PurchaseResult
    {
        $path = sprintf(
            '/user/buy/activation/%s/%s/%s',
            rawurlencode($offer->providerCountry),
            rawurlencode($offer->operator ?? 'any'),
            rawurlencode($offer->providerService)
        );

        $data = $this->request($path);

        if (!isset($data['id'], $data['phone'])) {
            throw new OutOfStockException($this->providerName, 'Numara alinamadi.', json_encode($data));
        }

        return new PurchaseResult(
            provider: $this->providerName,
            providerOrderId: (string) $data['id'],
            phone: (string) $data['phone'],
            costMinor: $this->toMinor($data['price'] ?? $offer->costMinor / 100),
            currency: $this->capabilities->currency,
            expiresAt: isset($data['expires'])
                ? new \DateTimeImmutable((string) $data['expires'])
                : (new \DateTimeImmutable())->modify('+' . $this->capabilities->cancelWindowSeconds . ' seconds'),
        );
    }

    public function poll(string $providerOrderId): PollResult
    {
        $data = $this->request('/user/check/' . rawurlencode($providerOrderId));

        $messages = [];
        foreach ($data['sms'] ?? [] as $sms) {
            $text = (string) ($sms['text'] ?? '');
            $messages[] = new Sms(
                sender: (string) ($sms['sender'] ?? ''),
                text: $text,
                code: isset($sms['code']) && $sms['code'] !== '' ? (string) $sms['code'] : Sms::extractCode($text),
                receivedAt: isset($sms['created_at'])
                    ? new \DateTimeImmutable((string) $sms['created_at'])
                    : new \DateTimeImmutable(),
            );
        }

        return new PollResult($this->mapStatus((string) ($data['status'] ?? ''), $messages !== []), $messages);
    }

    public function cancel(string $providerOrderId): void
    {
        $this->request('/user/cancel/' . rawurlencode($providerOrderId));
    }

    public function finish(string $providerOrderId): void
    {
        $this->request('/user/finish/' . rawurlencode($providerOrderId));
    }

    private function mapStatus(string $status, bool $hasSms): ProviderStatus
    {
        return match (strtoupper($status)) {
            'PENDING'            => $hasSms ? ProviderStatus::Received : ProviderStatus::Pending,
            'RECEIVED'           => ProviderStatus::Received,
            'FINISHED'           => ProviderStatus::Finished,
            'CANCELED', 'CANCELLED' => ProviderStatus::Cancelled,
            'TIMEOUT'            => ProviderStatus::Expired,
            'BANNED'             => ProviderStatus::Banned,
            default              => $hasSms ? ProviderStatus::Received : ProviderStatus::Pending,
        };
    }

    /** @return array<mixed> */
    private function request(string $path): array
    {
        $response = $this->http->get($this->baseUrl . $path, [], [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
        ]);

        $body = trim($response['body']);

        if ($response['status'] === 401 || $response['status'] === 403) {
            throw new ProviderException($this->providerName, 'Kimlik dogrulama basarisiz.', $body);
        }
        if (stripos($body, 'no free phones') !== false || stripos($body, 'out of stock') !== false) {
            throw new OutOfStockException($this->providerName, 'Stokta numara yok.', $body);
        }
        if (stripos($body, 'not enough') !== false) {
            throw new ProviderBalanceException($this->providerName, 'Saglayicidaki bakiyemiz yetersiz.', $body);
        }
        if ($response['status'] >= 400) {
            throw new ProviderException($this->providerName, 'HTTP ' . $response['status'], $body);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new ProviderException($this->providerName, 'Gecersiz JSON yaniti.', $body);
        }

        return $data;
    }

    private function toMinor(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
