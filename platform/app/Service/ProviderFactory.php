<?php
namespace Onay\App\Service;

use Onay\App\Kernel\Config;
use Onay\App\Repository\CatalogRepository;
use Onay\Core\Contract\HttpClientInterface;
use Onay\Core\Dto\ProviderCapabilities;
use Onay\Core\Http\CurlHttpClient;
use Onay\Core\Provider\CatalogMapper;
use Onay\Core\Provider\FiveSimProvider;
use Onay\Core\Provider\ProviderManager;
use Onay\Core\Provider\ProviderRegistry;
use Onay\Core\Provider\SmsActivateProvider;

/**
 * Yapilandirmadaki saglayicilari kurar. Yeni bir firma eklemek icin buraya bir
 * "case" ve config'e bir blok yeterlidir; sistemin geri kalani degismez.
 */
final class ProviderFactory
{
    private ?CatalogMapper $mapper = null;

    public function __construct(
        private readonly ?HttpClientInterface $http = null,
        private readonly ?CatalogRepository $catalog = null,
    ) {
    }

    public function mapper(): CatalogMapper
    {
        if ($this->mapper === null) {
            $catalog = $this->catalog ?? new CatalogRepository();
            $this->mapper = new CatalogMapper(
                countries: $catalog->codeMap('country'),
                services: $catalog->codeMap('service'),
            );
        }

        return $this->mapper;
    }

    public function registry(): ProviderRegistry
    {
        $registry = new ProviderRegistry();
        $http = $this->http ?? new CurlHttpClient();

        foreach ((array) Config::get('providers', []) as $name => $settings) {
            if (empty($settings['enabled'])) {
                continue;
            }

            $capabilities = new ProviderCapabilities(
                currency: $settings['currency'] ?? 'RUB',
                cancelWindowSeconds: (int) ($settings['cancel_window_seconds'] ?? 1200),
                minCancelDelaySeconds: (int) ($settings['min_cancel_delay_seconds'] ?? 0),
            );

            $provider = match ($settings['driver'] ?? '') {
                'fivesim' => new FiveSimProvider(
                    $http,
                    $this->mapper(),
                    (string) $settings['api_key'],
                    (string) ($settings['base_url'] ?? 'https://5sim.net/v1'),
                    (string) $name,
                    $capabilities
                ),
                'smsactivate' => new SmsActivateProvider(
                    $http,
                    $this->mapper(),
                    (string) $settings['api_key'],
                    (string) ($settings['base_url'] ?? ''),
                    (string) $name,
                    $capabilities
                ),
                default => throw new \RuntimeException('Tanimsiz saglayici surucusu: ' . ($settings['driver'] ?? '?')),
            };

            $registry->register($provider, (int) ($settings['priority'] ?? 100));
        }

        return $registry;
    }

    public function manager(?ProviderRegistry $registry = null): ProviderManager
    {
        return new ProviderManager(
            $registry ?? $this->registry(),
            (string) Config::get('provider_selection', ProviderManager::SELECT_CHEAPEST)
        );
    }
}
