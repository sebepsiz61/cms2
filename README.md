# Sanal Numara Platformu

Saf PHP 8.2 ile yazilmis SMS onay / sanal numara platformu. Cerceve yok, Composer
bagimliligi yok, kalici isci sureci yok — dosyalari sunucuya yukleyip calistirmak
yeterli.

Uygulamanin tamami [`platform/`](platform/) klasorundedir; kurulum, kullanim ve
sorun giderme [`platform/README.md`](platform/README.md) icinde anlatilir.

## Hizli bakis

| Ne | Nerede |
|---|---|
| Kurulum adimlari | `platform/README.md` |
| Dagitim secenekleri (adresten `/public` gizleme) | `platform/deploy/README.md` |
| Cerceveden bagimsiz cekirdek (saglayici + cuzdan) | `platform/core/` |
| Uygulama katmani | `platform/app/` |
| Yardimci betikler | `platform/bin/` |

## Ne yapar

Musteri bakiye yukler, ulke ve servis secer, toptanci saglayicidan tek kullanimlik
numara alir, gelen SMS'i ekranda gorur. SMS gelmezse ucret otomatik iade edilir.
Yaninda sayfa/blog yonetimi ve site ayarlari ile birlikte gelir.

Saglayici degistirilebilir: sistem tek bir arayuz bilir, yeni firma eklemek bir
adaptor sinifi yazmak demektir. Gercek API anahtari olmadan denemek icin sahte
numara ve sahte SMS ureten bir demo saglayici vardir.

## Testler

```bash
cd platform
php core/tests/run.php          # 43 birim testi
php core/tests/concurrency.php  # 10 surecle cift harcama testi
php tests/app_test.php          # 14 uctan uca test
php tests/cms_test.php          # 12 icerik testi
```

## Gecmis

Bu depo daha once Laravel 5.8 ile yazilmis bir kurumsal site CMS'i barindiriyordu.
O uygulama kullanimdan kaldirildi ve depodan cikarildi; gecmisi git kayitlarinda
durur. Su anki sistem onunla kod paylasmaz.
