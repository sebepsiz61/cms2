<?php
namespace Onay\App\Service;

use Onay\App\Kernel\Database;
use Onay\Core\Contract\HttpClientInterface;
use Onay\Core\Contract\LedgerInterface;
use Onay\Core\Wallet\PdoLedger;

/**
 * Basit servis konumlandirici. Cerceve yok, konteyner kutuphanesi yok; bu kadari
 * yetiyor. Testler set*() ile sahte bagimliliklari yerine koyabilir.
 */
final class Container
{
    private static ?LedgerInterface $ledger = null;
    private static ?ProviderFactory $factory = null;
    private static ?OrderService $orderService = null;
    private static ?WalletService $walletService = null;
    private static ?HttpClientInterface $http = null;

    public static function ledger(): LedgerInterface
    {
        return self::$ledger ??= new PdoLedger(Database::pdo());
    }

    public static function providerFactory(): ProviderFactory
    {
        return self::$factory ??= new ProviderFactory(self::$http);
    }

    public static function orderService(): OrderService
    {
        return self::$orderService ??= new OrderService(self::ledger(), self::providerFactory());
    }

    public static function walletService(): WalletService
    {
        return self::$walletService ??= new WalletService(self::ledger());
    }

    public static function catalogSync(): CatalogSyncService
    {
        return new CatalogSyncService(self::providerFactory());
    }

    /** Testte gercek HTTP yerine FakeHttpClient koymak icin. */
    public static function setHttp(?HttpClientInterface $http): void
    {
        self::$http = $http;
        self::reset();
    }

    public static function setLedger(?LedgerInterface $ledger): void
    {
        self::$ledger = $ledger;
        self::$orderService = null;
        self::$walletService = null;
    }

    public static function reset(): void
    {
        self::$factory = null;
        self::$orderService = null;
        self::$walletService = null;
    }
}
