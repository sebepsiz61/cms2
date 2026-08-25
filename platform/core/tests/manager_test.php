<?php
use Onay\Core\Dto\Offer;
use Onay\Core\Exception\OutOfStockException;
use Onay\Core\Http\FakeHttpClient;
use Onay\Core\Provider\CatalogMapper;
use Onay\Core\Provider\FiveSimProvider;
use Onay\Core\Provider\ProviderManager;
use Onay\Core\Provider\ProviderRegistry;
use Onay\Core\Provider\SmsActivateProvider;

$mapper = new CatalogMapper(
    countries: ['ucuz' => ['england' => 'GB'], 'pahali' => ['england' => 'GB']],
    services:  ['ucuz' => ['whatsapp' => 'whatsapp'], 'pahali' => ['whatsapp' => 'whatsapp']],
);

// Iki farkli firma, iki farkli protokol, ayni ulke ve servis.
$ucuzHttp = (new FakeHttpClient())
    ->on('/guest/prices', json_encode(['england' => ['whatsapp' => ['any' => ['cost' => 8.0, 'count' => 3]]]]))
    ->on('/user/buy/activation', 'no free phones', 400);

$pahaliHttp = (new FakeHttpClient())
    ->on('/guest/prices', json_encode(['england' => ['whatsapp' => ['any' => ['cost' => 15.0, 'count' => 50]]]]))
    ->on('/user/buy/activation', json_encode(['id' => 555, 'phone' => '+447700900999', 'price' => 15.0]));

$ucuz   = new FiveSimProvider($ucuzHttp, $mapper, 'k', providerName: 'ucuz');
$pahali = new FiveSimProvider($pahaliHttp, $mapper, 'k', providerName: 'pahali');

T::group('ProviderManager — birden fazla firmayi tek arayuz gibi kullanir');

T::it('tum firmalarin katalogunu birlestirir', function () use ($ucuz, $pahali) {
    $registry = (new ProviderRegistry())->register($ucuz, 10)->register($pahali, 20);
    $offers = (new ProviderManager($registry))->catalog();

    T::same(2, count($offers));
});

T::it('en ucuz stoklu teklifi one alir', function () use ($ucuz, $pahali) {
    $registry = (new ProviderRegistry())->register($ucuz, 10)->register($pahali, 20);
    $manager = new ProviderManager($registry, ProviderManager::SELECT_CHEAPEST);

    $ranked = $manager->rank($manager->catalog(), 'GB', 'whatsapp');

    T::same('ucuz', $ranked[0]->provider);
    T::same(800, $ranked[0]->costMinor);
});

T::it('oncelik kipinde kayit sirasi belirleyicidir', function () use ($ucuz, $pahali) {
    $registry = (new ProviderRegistry())->register($ucuz, 99)->register($pahali, 1);
    $manager = new ProviderManager($registry, ProviderManager::SELECT_PRIORITY);

    $ranked = $manager->rank($manager->catalog(), 'GB', 'whatsapp');

    T::same('pahali', $ranked[0]->provider, 'daha dusuk oncelik sayisi once gelir');
});

T::it('ilk firma stok veremezse ikinciye gecer, musteri bunu gormez', function () use ($ucuz, $pahali) {
    $registry = (new ProviderRegistry())->register($ucuz, 10)->register($pahali, 20);
    $manager = new ProviderManager($registry, ProviderManager::SELECT_CHEAPEST);

    $result = $manager->buyWithFailover($manager->rank($manager->catalog(), 'GB', 'whatsapp'));

    T::same('pahali', $result->provider);
    T::same('555', $result->providerOrderId);
    T::same(1, count($manager->lastFailures()), 'basarisiz deneme kaydedilmeli');
    T::same('ucuz', $manager->lastFailures()[0]['provider']);
});

T::it('hicbir firma veremezse anlasilir hata verir', function () use ($ucuz) {
    $registry = (new ProviderRegistry())->register($ucuz, 10);
    $manager = new ProviderManager($registry);

    T::throws(
        OutOfStockException::class,
        fn () => $manager->buyWithFailover($manager->rank($manager->catalog(), 'GB', 'whatsapp'))
    );
});

T::it('bakiyesi biten firma devre disi birakilir', function () use ($mapper, $pahali) {
    $bosHttp = (new FakeHttpClient())
        ->on('/guest/prices', json_encode(['england' => ['whatsapp' => ['any' => ['cost' => 1.0, 'count' => 9]]]]))
        ->on('/user/buy/activation', 'not enough money', 400);
    $bos = new FiveSimProvider($bosHttp, $mapper, 'k', providerName: 'ucuz');

    $registry = (new ProviderRegistry())->register($bos, 10)->register($pahali, 20);
    $manager = new ProviderManager($registry, ProviderManager::SELECT_CHEAPEST);

    $result = $manager->buyWithFailover($manager->rank($manager->catalog(), 'GB', 'whatsapp'));

    T::same('pahali', $result->provider);
    T::same(1, count($registry->enabled()), 'bakiyesi biten saglayici listeden dusmeli');
});

T::it('farkli protokoldeki firmalar ayni listede yarisir', function () {
    $mapper = new CatalogMapper(
        countries: ['5sim' => ['england' => 'GB'], 'sms-activate' => ['16' => 'GB']],
        services:  ['5sim' => ['whatsapp' => 'whatsapp'], 'sms-activate' => ['wa' => 'whatsapp']],
    );

    $restHttp = (new FakeHttpClient())
        ->on('/guest/prices', json_encode(['england' => ['whatsapp' => ['any' => ['cost' => 20.0, 'count' => 5]]]]));
    $duzMetinHttp = (new FakeHttpClient())
        ->on('action=getPrices', json_encode(['16' => ['wa' => ['cost' => 9.0, 'count' => 5]]]))
        ->on('action=getNumber', 'ACCESS_NUMBER:777:447700900777');

    $registry = (new ProviderRegistry())
        ->register(new FiveSimProvider($restHttp, $mapper, 'k'), 10)
        ->register(new SmsActivateProvider($duzMetinHttp, $mapper, 'k'), 20);

    $manager = new ProviderManager($registry, ProviderManager::SELECT_CHEAPEST);
    $ranked = $manager->rank($manager->catalog(), 'GB', 'whatsapp');

    T::same(2, count($ranked));
    T::same('sms-activate', $ranked[0]->provider, 'protokol farki secimde rol oynamamali, fiyat oynamali');
    T::same('777', $manager->buyWithFailover($ranked)->providerOrderId);
});
