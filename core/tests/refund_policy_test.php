<?php
use Onay\Core\Dto\ProviderCapabilities;
use Onay\Core\Order\RefundPolicy;

T::group('RefundPolicy — musteri suresi saglayici penceresinden kisa olmali');

T::it('20 dakikalik pencerede musteriye 15 dakika verir', function () {
    $caps = new ProviderCapabilities('RUB', cancelWindowSeconds: 1200, minCancelDelaySeconds: 120);
    T::same(900, (new RefundPolicy())->customerTimeoutSeconds($caps));
});

T::it('musteri suresi her zaman saglayici penceresinden kisadir', function () {
    foreach ([600, 900, 1200, 1800] as $pencere) {
        $caps = new ProviderCapabilities('RUB', cancelWindowSeconds: $pencere, minCancelDelaySeconds: 60);
        $sure = (new RefundPolicy())->customerTimeoutSeconds($caps);
        T::true($sure < $pencere, "pencere {$pencere} sn icin musteri suresi kisa olmali");
    }
});

T::it('guvenli sure birakmayan saglayici sessizce kabul edilmez', function () {
    $caps = new ProviderCapabilities('RUB', cancelWindowSeconds: 300, minCancelDelaySeconds: 120);
    T::throws(LogicException::class, fn () => (new RefundPolicy())->customerTimeoutSeconds($caps));
});

T::it('cok erken iptal saglayici tarafindan reddedilir', function () {
    $caps = new ProviderCapabilities('RUB', cancelWindowSeconds: 1200, minCancelDelaySeconds: 120);
    $policy = new RefundPolicy();
    $alindi = new DateTimeImmutable('2026-08-25 12:00:00');

    T::same(false, $policy->canCancelAt($caps, $alindi, new DateTimeImmutable('2026-08-25 12:01:00')));
    T::same(true,  $policy->canCancelAt($caps, $alindi, new DateTimeImmutable('2026-08-25 12:05:00')));
    T::same(false, $policy->canCancelAt($caps, $alindi, new DateTimeImmutable('2026-08-25 12:25:00')));
});
