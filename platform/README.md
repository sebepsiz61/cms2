# Sanal Numara Platformu

Saf PHP 8.2. Framework yok, Composer bagimliligi yok, daemon yok. cPanel'e dosyalari
yukleyip calistirmak yeterli.

## Ne yapar

Musteri bakiye yukler, ulke ve servis secer, toptanci saglayicidan tek kullanimlik numara
alir, gelen SMS'i ekranda gorur. SMS gelmezse ucret otomatik iade edilir.

Saglayici **degistirilebilir**: sistem tek bir arayuz bilir, firma eklemek bir adaptor
sinifi yazmak demektir. Ayrintilar icin `core/README.md`.

## Dizin duzeni

| Yol | Icerik |
|---|---|
| `public/` | Tek giris noktasi ve varliklar. **cPanel'de dokuman koku burasi olmali.** |
| `app/Kernel/` | Yonlendirici, istek/yanit, oturum, CSRF, yetki, sablon, veritabani |
| `app/Repository/` | Veri erisimi |
| `app/Service/` | Is mantigi: siparis dongusu, fiyatlandirma, cuzdan, katalog senkronu |
| `app/Controller/` | Front (musteri) ve Admin (yonetim) |
| `core/` | Cerceveden bagimsiz cekirdek: saglayici soyutlamasi + cuzdan defteri |
| `views/` | Sablonlar |
| `schema/` | `mysql.sql` (uretim), `sqlite.sql` (testten uretilir) |
| `bin/` | Kurulum, cron, sema ureticisi |
| `storage/` | Gunluk, dekont yuklemeleri, kilit dosyalari |

## Icerik yonetimi

Yonetim panelinden duzenlenir, kod degistirmeye gerek yoktur:

- **Sayfalar** — Hakkimizda, SSS, sozlesmeler. Yayinda/taslak, ust menude gosterme
  ve siralama secenekleriyle. Adres basliktan uretilir (Turkce harfler cevrilir).
- **Blog** — kategorili yazilar, ozet, yayin tarihi. Ileri tarihli yazi zamani
  gelene kadar listede gorunmez.
- **Ayarlar** — site adi, slogan, aciklama, duyuru bandi, iletisim ve sosyal
  baglantilar, alt bilgi metni. Ust menu, alt bilgi ve ana sayfa bunlardan beslenir.

Taslak icerik ziyaretciye hicbir yerde gorunmez; bu filtre depo katmaninda uygulanir.

## Kurulum (cPanel + VPS)

**1. Dosyalari yukleyin.** Ornegin `/home/kullanici/platform`.

**2. Dokuman kokunu ayarlayin.** cPanel > Domains > doküman kökü `/home/kullanici/platform/public`.

> Dokuman kokunu degistiremiyorsaniz sistem yine calisir: `app/`, `core/`, `config/` ve
> `storage/` klasorlerinde erisimi kapatan `.htaccess` dosyalari var. Yine de dogru olan
> dokuman kokunu `public/` yapmaktir.

**3. PHP surumunu 8.2 yapin.** cPanel > MultiPHP Manager. Gerekli eklentiler: `pdo_mysql`,
`curl`, `mbstring`, `fileinfo`, `json`.

**4. Veritabani olusturun.** cPanel > MySQL Databases; kullanici acip tum yetkileri verin.

**5. Yapilandirin.**
```bash
cp config/config.example.php config/config.php
```
`config/config.php` icinde doldurulacaklar: veritabani bilgileri, banka/IBAN, kur carpanlari,
marj, en az bir saglayicinin API anahtari.

**6. Kurun.**
```bash
php bin/install.php admin@alanadiniz.com "en-az-10-karakter-sifre"
```
Semayi yukler ve ilk yonetici hesabini acar. Yonetici rolu yalnizca buradan ya da
veritabanindan verilir; kayit formundan asla admin olunamaz.

**7. Cron ekleyin.** cPanel > Cron Jobs, dakikada bir:
```
* * * * * /usr/local/bin/php /home/kullanici/platform/bin/cron.php >> /home/kullanici/platform/storage/logs/cron.log 2>&1
```
Cron iki isi yapar: suresi dolan siparisleri kapatip iade eder, ve belirlenen aralikta
katalogu senkronlar. Kilit dosyasi ust uste binmeyi engeller.

**8. Izinler.** `storage/` yazilabilir olmali (`755` yeterli, `777` gerekmez).

**9. Katalogu kurun.** Yonetim > Katalog: ulke ve servisleri ekleyin, senkronu calistirin,
eslenmemis saglayici kodlarini ayni ekrandan eslestirin.

## Neden kuyruk yok

cPanel'de kalici bir isci sureci yok. Bunun yerine:

- **Canli SMS takibi** tarayicidan yapilir: siparis ekrani `/siparis/{id}/durum` ucunu 3
  saniyede bir yoklar, sunucu da saglayiciya sorar. `min_poll_interval` ayari saglayiciya
  gereginden sik gidilmesini engeller.
- **Zaman asimi ve iadeler** cron'a birakilir; musteri tarayiciyi kapatsa bile iade yapilir.

## Sorun giderme

Kurulumda bir sey calismazsa once teshis betigini calistirin:

```bash
php bin/doctor.php
```

SSH erisiminiz yoksa ya da tarayicidan bakmak istiyorsaniz ayni kontrolu
`public/kurulum-kontrol.php` yapar — hicbir seye bagimli degildir, proje kurulmadan
once de calisir:

```
https://alanadiniz.com/kurulum-kontrol.php
```

Eksik olan her madde icin hem cPanel hem WHM yolunu yazar.
**Kontrol bittiginde bu dosyayi sunucudan silin.**

PHP surumu, eklentiler, PDO suruculeri, yapilandirma, yazma izinleri ve veritabani
baglantisini tek tek dener; eksik olan her madde icin ne yapilacagini yazar.

### API anahtari olmadan sistemi denemek (demo kipi)

Gercek saglayici anahtari hazir degilse sistemin geri kalanini demo saglayici ile
uctan uca calistirabilirsiniz. Disari hic cikmaz, sahte numara ve sahte SMS uretir,
para harcamaz.

`config/config.php` icinde:

```php
'demo' => [
    'enabled'  => true,               // false idi
    'driver'   => 'demo',
    'sms_delay_seconds' => 15,        // SMS kac saniye sonra gelsin
    'silent_service'    => 'instagram', // bu servise SMS hic gelmez
    ...
],
```

Ardindan:

```bash
php bin/demo-seed.php    # yalnizca ulke ve servisleri ekler
php bin/demo-data.php    # gercekci veri: musteriler, gecmis siparisler, iadeler
```

`demo-data.php` gorsel ve icerik testi icindir: bos bir sistemde tasarim
degerlendirilemez. 6 musteri, onayli ve bekleyen havale talepleri, son 30 gune
yayilmis ~45 siparis (tamamlanan, iade edilen, iptal, acik) ve gelen SMS'ler uretir.
Para hareketleri dogrudan tabloya yazilmaz, defter uzerinden islenir; uretilen veri
gercek sistemin uretecegiyle ayni tutarliliktadir. `--temizle` ile once mevcut demo
verisini siler.

Musteri girisi: `ayse@ornek.com` / `demo12345`

Yonetim > Katalog > "Katalogu simdi senkronla" deyip musteri panelinden numara
alabilirsiniz. `silent_service` olarak isaretli servis SMS getirmez; sure dolunca
otomatik iadenin calistigini boyle gorursunuz.

**Demo etkinken her sayfada uyari bandi cikar.** Gercek satisa gecmeden once
`'enabled' => false` yapin — sattigi numaralar gercek degildir.

### Saglayici anahtari calisiyor mu

Katalog senkronu iki farkli sorunu ayni hataya dusurur: anahtar gecersiz mi, yoksa
kodlar eslenmemis mi? Bu betik ikisini ayirir ve numara satin almadan, para
harcamadan test eder:

```bash
php bin/provider-test.php            # acik olan tum saglayicilar
php bin/provider-test.php fivesim    # yalnizca biri
```

Uc adimda rapor verir: bakiye sorgusu (anahtar gecerli mi), katalog (kac teklif
geliyor), kod eslemesi (hangi saglayici kodlari eslenmeyi bekliyor). "Katalog bos"
sonucu neredeyse her zaman ucuncu adimdaki eksikliktir, anahtar sorunu degil.

### "PDO surucusu yuklu degil" / "could not find driver"

En sik karsilasilan kurulum sorunu. Her PHP surumunun kendi eklenti seti vardir;
surumu degistirdiginizde eklentiler sifirlanir.

**cPanel'de (son kullanici paneli):**

1. cPanel > **Select PHP Version**
2. Surum **8.2** secili olsun
3. **Extensions** sekmesinde su kutulari isaretleyin: `pdo`, `nd_pdo_mysql`
   (bazi sunucularda `pdo_mysql` adiyla gorunur), `curl`, `mbstring`, `fileinfo`
4. **Save** deyin

**WHM'de (root paneli) yapi farklidir** — orada "Select PHP Version" yoktur,
eklentiler EasyApache 4 uzerinden kurulur:

1. WHM > **EasyApache 4** > yuklu profilde **Customize**
2. **PHP Versions** sekmesinde `ea-php82` isaretli olsun
3. **PHP Extensions** sekmesinde arama kutusuna eklenti adini yazin ve cikan
   `ea-php82-php-*` paketini isaretleyin
4. **Review** > **Provision** (birkac dakika surer)
5. WHM > **MultiPHP Manager** ile alan adini `ea-php82` yapin

EasyApache 4 paket adlari:

| Eklenti | Paket |
|---|---|
| `pdo` | `ea-php82-php-pdo` |
| `pdo_mysql` | `ea-php82-php-mysqlnd` (mysqli ile ayni pakettedir) |
| `curl` | `ea-php82-php-curl` |
| `mbstring` | `ea-php82-php-mbstring` |
| `fileinfo` | `ea-php82-php-fileinfo` |
| `json` | PHP 8 cekirdeginde, ayri paket yok |

Komut satirini tercih ederseniz:

```bash
yum install -y ea-php82-php-mysqlnd ea-php82-php-curl ea-php82-php-mbstring
systemctl restart httpd
systemctl restart ea-php82-php-fpm   # PHP-FPM kullaniliyorsa
```

Her iki durumda da sonucu `php bin/doctor.php` ya da tarayicidan
`kurulum-kontrol.php` ile dogrulayin.

### Komut satiri farkli PHP surumu kullaniyor

cPanel'de SSH'daki `php` genelde eski bir surumdur. Tam yol kullanin:

```bash
/opt/cpanel/ea-php82/root/usr/bin/php bin/doctor.php
```

Cron isini de bu tam yolla yazin.

### Adreste "/public" gorunuyor

Uygulama alt klasorden yayinlaniyor demektir. Uc secenek `deploy/README.md`
icinde anlatiliyor; ozeti:

1. **Dokuman kokunu `platform/public` yapin** (en temizi, ek dosya gerekmez).
2. Yapamiyorsaniz `deploy/public_html.htaccess` dosyasini alan adinizin dokuman
   kokune `.htaccess` olarak kopyalayin; istekler arka planda uygulamaya gider,
   adres `example.com/giris` gorunur.
3. Hicbiri olmuyorsa sistem alt klasorden de calisir; adres uzun kalir ama
   islevsellik degismez.

`index.php` de adreste gorunuyorsa (`/public/index.php/giris`) mod_rewrite
kapalidir. Bu bicim de desteklenir, ancak kalici cozum modulu acmaktir.

### Her sayfada "Sayfa bulunamadi"

Iki sebebi olabilir:

**1. Uygulama alt klasorde.** Dokuman kokunu `platform/public` yapamadiysaniz
uygulama ornegin `alanadiniz.com/platform/public/` altindan yayinlanir. Sistem
bunu kendi tespit eder ve baglantilari buna gore uretir — ek ayar gerekmez.
Yine de dogru olan dokuman kokunu `public/` yapmaktir; ustelik o zaman
`app/`, `core/`, `config/` ve `storage/` web'den hic erisilemez.

**2. mod_rewrite kapali.** `public/.htaccess` tum istekleri `index.php`'ye
yonlendirir; modul kapaliysa Apache dosyayi arar ve bulamaz. WHM > EasyApache 4 >
Apache Modules bolumunden `mod_rewrite` isaretlenmeli. `AllowOverride` degeri
`None` ise `.htaccess` hic okunmaz; sanal host ayarinda `All` yapilmali.

Hangisi oldugunu `kurulum-kontrol.php` sayfasi "Adres yapisi" bolumunde soyler.

### "Access denied" veya "Unknown database"

cPanel veritabani ve kullanici adlarini hesap adinizla on ekler. Hesap adiniz `ornek`
ise veritabani `ornek_sanalnumara`, kullanici `ornek_kullanici` olur. `config/config.php`
icine bu **on ekli tam adlari** yazin. Ayrica cPanel > MySQL Databases ekraninda
kullaniciyi veritabanina ekleyip **ALL PRIVILEGES** verdiginizden emin olun.

### MySQL hic yoksa

Sistem SQLite ile de calisir; `config/config.php` icinde:

```php
'db' => ['driver' => 'sqlite', 'database' => __DIR__ . '/../storage/veri.sqlite'],
```

Dusuk trafikte sorunsuz calisir, tum testler zaten SQLite uzerinde kosuyor. Ancak
uretim icin MySQL tercih edin: es zamanli yazma yukunde SQLite tek yazici ile sinirlidir
ve veritabani dosyasi yedekleme disi kalmamalidir.

## Guvenlik notlari

- Kayit her zaman `customer` rolu ile acilir.
- Yonetim rotalari `RequireAdmin` ile korunur — "giris yapmis olmak" yetmez.
- Tum POST istekleri CSRF dogrulamasindan gecer.
- Dekontlar dokuman kokunun disinda (`storage/uploads`) saklanir, uzanti kullanicidan
  alinmaz, gercek MIME turune gore belirlenir ve yalnizca yoneticiye controller uzerinden sunulur.
- `config/config.php` surum kontrolune girmez.

## Para

Tum tutarlar tam sayi **kurustur**; ondalik sayi yalnizca ekrana basarken olusur. Bakiye tek
bir kolonda tutulmaz: her hareket `wallet_transactions` tablosuna yazilir, `users.balance_minor`
onbellektir. Ayrintilar ve degismezler `core/README.md` icinde.

Fiyat siparis aninda dondurulur: sonradan kur ya da marj degisse de gecmis siparis etkilenmez.

## Testler

```bash
php core/tests/run.php          # 36 birim testi (saglayici, katalog, defter, iade kurali)
php core/tests/concurrency.php  # 10 surecle gercek cift harcama testi
php tests/app_test.php          # 14 uctan uca test (bakiye -> numara -> SMS -> iade)
```

Hicbiri gercek saglayiciya cikmaz; `FakeHttpClient` senaryolari uretir. Test veritabani
SQLite, uretim MySQL — ikisi de ayni `schema/mysql.sql` kaynagindan gelir
(`php bin/make-sqlite-schema.php`).

## Bilinmesi gerekenler

**Saglayici adaptorleri gercek API'ye karsi hic calismadi.** Protokol sekilleri dogru ama
ilk canli denemede uc adresi ya da alan adi farki cikabilir. Kucuk bakiyeyle deneyin.

**Saglayici pencereleri dogrulanmali.** `cancel_window_seconds` gibi degerler
`config/config.php` uzerinden gelir; varsayilanlar yaygin uygulamayi yansitir ama secilen
firmanin guncel dokumanindan teyit edilmelidir. Bu deger yanlissa musteriye iade edip
saglayicidan tahsil edememe riski dogar — `RefundPolicy` bunu engellemek icin musteri
suresini pencereden kisa tutar ve guvenli sure kalmiyorsa hata firlatir.

## Henuz yok

Kiralama (`number_orders.type = 'rental'` alani hazir), bayi API'si (`users.api_token`
alani hazir), cok dilli icerik/blog katmani, otomatik odeme (su an yalnizca havale/EFT ve
yonetici onayi).
