<?php
use Onay\Core\Provider\CatalogMapper;

T::group('CatalogMapper — saglayici kodlarini kanonik kodlara cevirir');

$mapper = new CatalogMapper(
    countries: [
        '5sim'         => ['england' => 'GB', 'turkey' => 'TR'],
        'sms-activate' => ['16' => 'GB', '62' => 'TR'],
    ],
    services: [
        '5sim'         => ['whatsapp' => 'whatsapp', 'telegram' => 'telegram'],
        'sms-activate' => ['wa' => 'whatsapp', 'tg' => 'telegram'],
    ],
);

T::it('iki farkli firmanin ayni ulkesi ayni kanonik koda duser', function () use ($mapper) {
    T::same('GB', $mapper->toCanonicalCountry('5sim', 'england'));
    T::same('GB', $mapper->toCanonicalCountry('sms-activate', '16'));
});

T::it('servis kodlari da normallesir', function () use ($mapper) {
    T::same('whatsapp', $mapper->toCanonicalService('5sim', 'whatsapp'));
    T::same('whatsapp', $mapper->toCanonicalService('sms-activate', 'wa'));
});

T::it('ters yonde saglayicinin kendi kodunu verir', function () use ($mapper) {
    T::same('england', $mapper->toProviderCountry('5sim', 'GB'));
    T::same('16', $mapper->toProviderCountry('sms-activate', 'GB'));
    T::same('wa', $mapper->toProviderService('sms-activate', 'whatsapp'));
});

T::it('eslenmemis kod sessizce yutulmaz, listeye yazilir', function () use ($mapper) {
    T::same(null, $mapper->toCanonicalCountry('5sim', 'narnia'));
    $unmapped = $mapper->unmapped();
    T::same(1, count($unmapped));
    T::same('narnia', $unmapped[0]['code']);
    T::same('country', $unmapped[0]['kind']);
});

T::it('ayni eslenmemis kod tekrar tekrar birikmez', function () use ($mapper) {
    $mapper->toCanonicalCountry('5sim', 'narnia');
    $mapper->toCanonicalCountry('5sim', 'narnia');
    T::same(1, count($mapper->unmapped()));
});
