# Dagitim secenekleri

Adresin `example.com/giris` gorunmesi icin uc yol var. Sirayla deneyin;
ilki en temizidir.

## 1. Dokuman kokunu degistirin (onerilen)

cPanel > Domains > alan adi > Document Root:

```
/home/hesapadi/platform/public
```

Baska hicbir sey gerekmez. `app/`, `core/`, `config/` ve `storage/` web'den
erisilemez hale gelir. Adres `example.com/giris` olur.

## 2. Dokuman kokune yonlendirme koyun

Dokuman kokunu degistiremiyorsaniz:

1. `platform/` klasorunu `public_html`'in **yanina** koyun
   (`/home/hesapadi/platform`), icine degil.
2. Bu klasordeki `public_html.htaccess` dosyasini `public_html/.htaccess`
   olarak kopyalayin.
3. Icindeki `../platform/public` yolunu kendi yerlesiminize gore duzeltin.

Adres yine `example.com/giris` olur; ziyaretci `platform` klasorunu gormez.

## 3. Hicbiri olmuyorsa

Uygulama alt klasorden de calisir (`example.com/platform/public/`). Taban yolu
kendiliginden tespit edilir, ek ayar gerekmez. Adres uzun gorunur ama sistem
duzgun calisir.

`mod_rewrite` kapaliysa `index.php` de adreste kalir
(`example.com/public/index.php/giris`); bu da desteklenir. Kalici cozum
mod_rewrite'i acmaktir: WHM > EasyApache 4 > Apache Modules.
