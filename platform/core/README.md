# onay/core

Sanal numara platformunun framework'ten bagimsiz cekirdegi. Iki isi yapar:

1. **Saglayici soyutlamasi** — hangi toptanci firma ile calisildigindan bagimsiz, tek bir arayuz.
2. **Cuzdan defteri** — para hareketlerinin kaybolmadigi, cift islenmedigi ve eksiye dusmedigi kayit katmani.

Laravel'e (ya da baska bir PHP catisina) bagimlilik yoktur; catisi HTTP, kuyruk ve ORM kabugu olarak
disarida kalir. Boylece bu iki katman gercek para harcamadan test edilebilir.

## Neden ayri bir paket

Sistemin en pahali iki hatasi burada onlenir: yanlis saglayiciya kilitlenmek ve bakiyeyi yanlis
hesaplamak. Ikisi de sonradan duzeltilmesi en zor seylerdir, bu yuzden catidan once ve catidan
bagimsiz yazildilar.

## Kurulum ve test

Composer gerekmez; `autoload.php` PSR-4 yukleyiciyi kendisi kurar.

```bash
php tests/run.php          # 36 birim testi
php tests/concurrency.php  # 10 surecle gercek cift harcama testi
```

`tests/run.php` sifir donerse hepsi gecmis demektir.

## Yeni bir firma eklemek

Sistemin geri kalani yalnizca `NumberProviderInterface`'i bilir. Yeni firma eklemek icin:

**1. Adaptoru yaz.** `src/Provider/` altina, arayuzun sekiz metodunu uygulayan bir sinif.
Elde iki ornek var ve bilerek iki farkli protokolu temsil ediyorlar:

| Sinif | Protokol | Ornek yanit |
|---|---|---|
| `FiveSimProvider` | REST + JSON + Bearer token | `{"id":987654,"phone":"+4477..."}` |
| `SmsActivateProvider` | tek uc + `action` parametresi + duz metin | `ACCESS_NUMBER:12345:4477...` |

Yeni firma buyuk ihtimalle bu ikisinden birine benziyor; benzemiyorsa da arayuz ayni kalir.

**2. Kod eslemesini gir.** Her firma ulkeyi ve servisi kendi koduyla adlandirir (`england` / `16` /
`GB`). `CatalogMapper` bunu kanonik kodlarimiza cevirir. Eslenmemis kodlar atilmaz, `unmapped()`
listesine yazilir; yonetici panelinde gosterilip elle eslenir.

**3. Kayda ekle.**

```php
$registry = (new ProviderRegistry())
    ->register(new FiveSimProvider($http, $mapper, $key), priority: 10)
    ->register(new YeniFirmaProvider($http, $mapper, $key), priority: 20);

$manager = new ProviderManager($registry, ProviderManager::SELECT_CHEAPEST);
$teklifler = $manager->rank($manager->catalog(), 'GB', 'whatsapp');
$sonuc = $manager->buyWithFailover($teklifler);
```

Baska hicbir yer degismez. Firma kapatilirsa `disable()`, sirasi degisirse `priority` yeter.

## Secim ve failover

`ProviderManager` iki kiple calisir:

- `SELECT_CHEAPEST` — ayni ulke+servis icin en ucuz stoklu teklif (marj icin)
- `SELECT_PRIORITY` — kayittaki oncelik sirasi (guvenilirlik icin)

Satin alma sirasinda bir firma stok veremezse siradakine gecilir; musteri bunu gormez. Bir ayrim
onemli: **bizim o firmadaki bakiyemizin bitmesi** stok sorunu degildir. `ProviderBalanceException`
firmayi devre disi birakir ve `lastFailures()` ile disari tasinir — yonetici uyarilmali, cunku
sessizce pahali firmaya gecmek dogrudan marj kaybidir.

## Iade suresi kurali

`RefundPolicy`, sistemin en pahali hatasini engeller: **musteriye verdigimiz iade suresi,
saglayicinin iptal penceresinden kisa olmali.** Ters kurulursa musteriye iade edilir ama
saglayicidan tahsil edilemez, fark zarar yazilir.

20 dakikalik pencerede musteriye 15 dakika verilir; guvenlik payi yoklama gecikmesi ve iptal
cagrisinin suresi icindir. Guvenli sure birakmayan bir saglayici sessizce kabul edilmez,
`LogicException` firlatir.

> Saglayici pencereleri (`cancelWindowSeconds`, `minCancelDelaySeconds`) kod icinde sabit degil,
> `ProviderCapabilities` uzerinden yapilandirmadan gelir. Varsayilan degerler yaygin uygulamayi
> yansitir ama **secilen firmanin guncel dokumanindan dogrulanmalidir**; firmalar bu kurallari
> degistirir.

## Cuzdan defteri

Bakiye tek bir kolonda tutulmaz. Her hareket `wallet_transactions` tablosuna yazilir ve bakiye
bunlarin toplamidir. `users.balance_minor` yalnizca onbellektir; `verifyIntegrity()` ikisinin
esitligini dogrular.

Uc kural veritabani tarafindan zorlanir, uygulama tarafindan degil:

- **Cift islem yok.** `idempotency_key` uzerindeki benzersiz indeks. Ayni anahtarla ikinci cagri
  yeni hareket uretmez, mevcut hareketin id'sini doner. Odeme webhook'lari ve yeniden denenen
  kuyruk isleri icin sarttir.
- **Cift harcama yok.** Bakiye okunmadan once kullanici satiri kilitlenir (MySQL/Postgres'te
  `FOR UPDATE`, SQLite'ta `BEGIN IMMEDIATE`). `tests/concurrency.php` bunu 10 surecle kanitlar:
  1000 birim bakiyeden 200'er harcamaya calisan 10 surecten tam olarak 5'i gecer.
- **Eksi bakiye yok.** Kilitli okuma sonrasi kontrol edilir; yetmezse `InsufficientBalanceException`.

Kayitlar hicbir zaman silinmez veya guncellenmez. Duzeltme yeni bir `adjust` hareketidir.

Tum tutarlar tam sayi **minor birimdir** (kurus). Para hesabinda ondalik sayi kullanilmaz.

## Laravel tarafina baglanirken

- `HttpClientInterface` → `Http` facade'ini saran ince bir sinif (yeniden deneme, log, zaman asimi)
- `LedgerInterface` → `PdoLedger` oldugu gibi kullanilir; `DB::connection()->getPdo()` gecilir
- `ProviderRegistry` → service provider icinde, veritabanindaki etkin saglayici kayitlarindan kurulur
- Yoklama, katalog senkronu ve zaman asimi iadeleri kuyruk isleri olarak sarilir

Bu paket kuyruk, cron ya da HTTP bilmez; bunlar cati katmaninin isidir.
