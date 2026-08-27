# Dagitim: adresin `example.com/giris` gorunmesi

Adresteki `/public` PHP koduyla kaldirilamaz. `example.com/` istegi dogrudan
**dokuman kokune** duser; orada uygulamanin giris dosyasi yoksa hangi dosyanin
calisacagina web sunucusu karar verir, uygulamanin haberi olmaz.

Uc secenek var. Ilki en temizi.

---

## 1. Dokuman kokunu degistirin (onerilen)

cPanel > Domains > alan adi > Document Root:

```
/home/hesapadi/platform/public
```

Baska hicbir dosya gerekmez. `app/`, `core/`, `config/` ve `storage/` web'den
erisilemez hale gelir.

---

## 2. Dokuman kokune giris dosyasi koyun

Dokuman kokunu degistiremiyorsaniz `docroot/` klasorundeki iki dosyayi
`public_html/` icine kopyalayin:

```
public_html/index.php     <- docroot/index.php
public_html/.htaccess     <- docroot/.htaccess
```

Iki yerlesimden birini kullanin:

```
A) platform, public_html ICINDE            B) platform, public_html YANINDA (daha guvenli)

   public_html/                               public_html/
     index.php                                  index.php
     .htaccess                                  .htaccess
     platform/                                platform/
       public/                                  public/
       app/  core/  config/ ...                 app/  core/  config/ ...
```

`index.php` uygulamayi iki yerde de arar; ek ayar gerekmez. **B tercih edilmeli**:
uygulama dosyalari dokuman kokunun disinda kalir ve web'den hic gorulemez.
A kullaniyorsaniz `.htaccess` icindeki `RedirectMatch 404` satirlari
`platform/app`, `platform/config` gibi yollara erisimi kapatir.

Sonuc her iki durumda da: `example.com/giris`, `example.com/blog`,
`example.com/sayfa/hakkimizda`. Varliklar `example.com/assets/style.css`
adresinden sunulur.

---

## 3. Hicbiri olmuyorsa

Uygulama alt klasorden de calisir (`example.com/platform/public/`). Taban yolu
kendiliginden tespit edilir; adres uzun gorunur ama islevsellik degismez.

`mod_rewrite` kapaliysa `index.php` de adreste kalir
(`example.com/public/index.php/giris`); bu bicim de desteklenir. Kalici cozum
modulu acmaktir: WHM > EasyApache 4 > Apache Modules > `mod_rewrite`.

---

## Kontrol

Kurulumdan sonra `kurulum-kontrol.php` sayfasindaki **Adres yapisi** bolumu
kurulumun kokte mi alt klasorde mi oldugunu ve `mod_rewrite` durumunu soyler.
Kontrol bitince o dosyayi sunucudan silin.
