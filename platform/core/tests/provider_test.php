<?php
use Onay\Core\Dto\Offer;
use Onay\Core\Dto\ProviderStatus;
use Onay\Core\Exception\OutOfStockException;
use Onay\Core\Exception\ProviderBalanceException;
use Onay\Core\Http\FakeHttpClient;
use Onay\Core\Provider\CatalogMapper;
use Onay\Core\Provider\FiveSimProvider;
use Onay\Core\Provider\SmsActivateProvider;

$mapper = static fn (): CatalogMapper => new CatalogMapper(
    countries: ['5sim' => ['england' => 'GB'], 'sms-activate' => ['16' => 'GB']],
    services:  ['5sim' => ['whatsapp' => 'whatsapp'], 'sms-activate' => ['wa' => 'whatsapp']],
);

T::group('FiveSimProvider — JSON/REST protokolu');

T::it('katalogu kanonik tekliflere cevirir', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('/guest/prices', json_encode([
        'england' => ['whatsapp' => ['virtual21' => ['cost' => 12.5, 'count' => 40]]],
        'narnia'  => ['whatsapp' => ['any' => ['cost' => 1.0, 'count' => 9]]],
    ]));

    $offers = (new FiveSimProvider($http, $mapper(), 'test-key'))->catalog();

    T::same(1, count($offers), 'eslenmemis ulke katalogdan dusmeli');
    T::same('GB', $offers[0]->countryCode);
    T::same('whatsapp', $offers[0]->serviceCode);
    T::same(1250, $offers[0]->costMinor, 'fiyat minor birime cevrilmeli');
    T::same(40, $offers[0]->stock);
    T::same('england', $offers[0]->providerCountry, 'satin alma icin saglayici kodu saklanmali');
});

T::it('numara satin alir', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('/user/buy/activation', json_encode([
        'id' => 987654, 'phone' => '+447700900123', 'price' => 12.5,
        'expires' => '2026-08-25T19:30:00Z', 'status' => 'PENDING',
    ]));

    $offer = new Offer('5sim', 'GB', 'whatsapp', 1250, 'RUB', 40, 'england', 'whatsapp', 'virtual21');
    $result = (new FiveSimProvider($http, $mapper(), 'test-key'))->buy($offer);

    T::same('987654', $result->providerOrderId);
    T::same('+447700900123', $result->phone);
    T::same(1250, $result->costMinor);
});

T::it('gelen SMS ve kodu okur', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('/user/check/', json_encode([
        'status' => 'RECEIVED',
        'sms' => [['sender' => 'WhatsApp', 'text' => 'Your code is 481523', 'code' => '481523', 'created_at' => '2026-08-25T19:12:00Z']],
    ]));

    $poll = (new FiveSimProvider($http, $mapper(), 'test-key'))->poll('987654');

    T::same(ProviderStatus::Received, $poll->status);
    T::same('481523', $poll->firstCode());
});

T::it('saglayici kodu ayri vermezse metinden cikarir', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('/user/check/', json_encode([
        'status' => 'RECEIVED',
        'sms' => [['sender' => 'Google', 'text' => 'G-77341 dogrulama kodunuz']],
    ]));

    T::same('77341', (new FiveSimProvider($http, $mapper(), 'test-key'))->poll('1')->firstCode());
});

T::it('stok yoksa OutOfStock firlatir', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('/user/buy/activation', 'no free phones', 400);
    $offer = new Offer('5sim', 'GB', 'whatsapp', 1250, 'RUB', 1, 'england', 'whatsapp', 'any');

    T::throws(OutOfStockException::class, fn () => (new FiveSimProvider($http, $mapper(), 'k'))->buy($offer));
});

T::group('SmsActivateProvider — duz metin protokolu');

T::it('ACCESS_NUMBER yanitini ayristirir', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('action=getNumber', 'ACCESS_NUMBER:12345:447700900123');
    $offer = new Offer('sms-activate', 'GB', 'whatsapp', 900, 'RUB', 5, '16', 'wa');

    $result = (new SmsActivateProvider($http, $mapper(), 'k'))->buy($offer);

    T::same('12345', $result->providerOrderId);
    T::same('447700900123', $result->phone);
    T::same(900, $result->costMinor, 'bu protokol fiyati dondurmez, teklifteki fiyat korunur');
});

T::it('STATUS_WAIT_CODE bekleme durumudur', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('action=getStatus', 'STATUS_WAIT_CODE');
    T::same(ProviderStatus::Pending, (new SmsActivateProvider($http, $mapper(), 'k'))->poll('1')->status);
});

T::it('STATUS_OK icinden kodu cikarir', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('action=getStatus', 'STATUS_OK:481523');
    $poll = (new SmsActivateProvider($http, $mapper(), 'k'))->poll('1');

    T::same(ProviderStatus::Received, $poll->status);
    T::same('481523', $poll->firstCode());
});

T::it('HTTP 200 ile gelen NO_NUMBERS hatasini yakalar', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('action=getNumber', 'NO_NUMBERS');
    $offer = new Offer('sms-activate', 'GB', 'whatsapp', 900, 'RUB', 5, '16', 'wa');

    T::throws(OutOfStockException::class, fn () => (new SmsActivateProvider($http, $mapper(), 'k'))->buy($offer));
});

T::it('NO_BALANCE stok hatasi degil, bizim aksakligimizdir', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('action=getNumber', 'NO_BALANCE');
    $offer = new Offer('sms-activate', 'GB', 'whatsapp', 900, 'RUB', 5, '16', 'wa');

    T::throws(ProviderBalanceException::class, fn () => (new SmsActivateProvider($http, $mapper(), 'k'))->buy($offer));
});

T::it('bakiyeyi minor birim olarak okur', function () use ($mapper) {
    $http = (new FakeHttpClient())->on('action=getBalance', 'ACCESS_BALANCE:104.35');
    T::same(10435, (new SmsActivateProvider($http, $mapper(), 'k'))->balanceMinor());
});
