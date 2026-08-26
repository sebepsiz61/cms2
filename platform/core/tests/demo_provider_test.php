<?php
use Onay\Core\Dto\ProviderStatus;
use Onay\Core\Provider\DemoProvider;

T::group('DemoProvider — API anahtari olmadan uctan uca deneme');

T::it('katalog dondurur ve esleme gerektirmez', function () {
    $offers = (new DemoProvider())->catalog();

    T::true(count($offers) >= 8, 'birden fazla ulke ve servis olmali');
    T::same('GB', $offers[0]->countryCode);
    T::same('TRY', $offers[0]->currency);
    T::true($offers[0]->inStock());
});

T::it('numara satin alir', function () {
    $demo = new DemoProvider();
    $teklif = $demo->catalog()[0];
    $sonuc = $demo->buy($teklif);

    T::same('demo', $sonuc->provider);
    T::true(str_starts_with($sonuc->providerOrderId, 'demo-'));
    T::true(str_starts_with($sonuc->phone, '+44'), 'GB numarasi +44 ile baslamali');
    T::same($teklif->costMinor, $sonuc->costMinor);
});

T::it('SMS gecikmeden once bekleme durumunda kalir', function () {
    $demo = new DemoProvider(smsGecikmesi: 60);
    $sonuc = $demo->buy($demo->catalog()[0]);

    T::same(ProviderStatus::Pending, $demo->poll($sonuc->providerOrderId)->status);
});

T::it('gecikme dolunca SMS ve kod gelir', function () {
    $demo = new DemoProvider(smsGecikmesi: 0);
    $sonuc = $demo->buy($demo->catalog()[0]);
    $poll = $demo->poll($sonuc->providerOrderId);

    T::same(ProviderStatus::Received, $poll->status);
    T::same(6, strlen((string) $poll->firstCode()), 'alti haneli kod');
    T::true(str_contains($poll->messages[0]->text, 'DEMO'), 'metin sahte oldugunu soylemeli');
});

T::it('ayni siparis her yoklamada ayni kodu verir', function () {
    $demo = new DemoProvider(smsGecikmesi: 0);
    $sonuc = $demo->buy($demo->catalog()[0]);

    T::same(
        $demo->poll($sonuc->providerOrderId)->firstCode(),
        $demo->poll($sonuc->providerOrderId)->firstCode()
    );
});

T::it('sessiz servise SMS hic gelmez — iade akisi denenebilir', function () {
    $demo = new DemoProvider(smsGecikmesi: 0, sessizServis: 'instagram');

    $instagram = null;
    foreach ($demo->catalog() as $teklif) {
        if ($teklif->serviceCode === 'instagram') {
            $instagram = $teklif;
            break;
        }
    }

    $sonuc = $demo->buy($instagram);
    T::same(ProviderStatus::Pending, $demo->poll($sonuc->providerOrderId)->status);
});

T::it('gercek numaralarla karismayan test bloklari kullanir', function () {
    $demo = new DemoProvider();

    foreach ($demo->catalog() as $teklif) {
        $telefon = $demo->buy($teklif)->phone;
        T::true(
            str_starts_with($telefon, '+4479460') || str_starts_with($telefon, '+15550')
            || str_starts_with($telefon, '+90532') || str_starts_with($telefon, '+49151')
            || str_starts_with($telefon, '+9999'),
            'ayrilmis blok disinda numara uretilmemeli: ' . $telefon
        );
    }
});
