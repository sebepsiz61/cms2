<?php
/**
 * Icerik yonetimi testi: sayfa/yazi/kategori kaydetme, yayin filtresi ve
 * site ayarlari. Yayinda olmayan icerik ziyaretciye sizmamali.
 */
require __DIR__ . '/../autoload.php';
require __DIR__ . '/../core/tests/bootstrap.php';
require __DIR__ . '/../app/Kernel/helpers.php';

use Onay\App\Kernel\Config;
use Onay\App\Kernel\Database;
use Onay\App\Repository\ContentRepository;
use Onay\App\Repository\SettingsRepository;

$dbFile = sys_get_temp_dir() . '/onay_cms_test_' . getmypid() . '.sqlite';
@unlink($dbFile);
putenv('TEST_DB=' . $dbFile);

Config::load(__DIR__ . '/config.test.php');
Database::runSchema(__DIR__ . '/../schema/sqlite.sql');

$icerik = new ContentRepository();

T::group('Slug uretimi');

T::it('Turkce harfleri karsiligina cevirir', function () {
    T::same('sikca-sorulan-sorular', ContentRepository::slugify('Sıkça Sorulan Sorular'));
    T::same('iade-politikasi', ContentRepository::slugify('İade Politikası'));
    T::same('guvenlik-ve-gizlilik', ContentRepository::slugify('Güvenlik ve Gizlilik'));
});

T::it('noktalama ve fazla bosluklari temizler', function () {
    T::same('sms-onay-nedir', ContentRepository::slugify('SMS Onay:  Nedir?'));
});

T::group('Sayfalar');

T::it('sayfa kaydedilir ve slug ile bulunur', function () use ($icerik) {
    $id = $icerik->savePage(null, [
        'title' => 'Hakkimizda', 'slug' => 'hakkimizda', 'content' => '<p>Merhaba</p>',
        'meta_description' => 'tanitim', 'status' => 'published', 'show_in_menu' => 1, 'menu_order' => 1,
    ]);

    $sayfa = $icerik->pageBySlug('hakkimizda');
    T::same($id, (int) $sayfa['id']);
    T::same('<p>Merhaba</p>', $sayfa['content']);
});

T::it('taslak sayfa ziyaretciye gorunmez', function () use ($icerik) {
    $icerik->savePage(null, [
        'title' => 'Gizli', 'slug' => 'gizli', 'content' => 'x',
        'meta_description' => '', 'status' => 'draft', 'show_in_menu' => 1, 'menu_order' => 9,
    ]);

    T::same(null, $icerik->pageBySlug('gizli'), 'taslak sayfa acilmamali');

    $menu = array_column($icerik->menuPages(), 'slug');
    T::true(!in_array('gizli', $menu, true), 'taslak sayfa menude gorunmemeli');
    T::true(in_array('hakkimizda', $menu, true), 'yayindaki sayfa menude olmali');
});

T::it('yonetici taslaklari da gorur', function () use ($icerik) {
    T::same(2, count($icerik->pages(yalnizYayinda: false)));
    T::same(1, count($icerik->pages()));
});

T::it('duzenleme yeni kayit olusturmaz', function () use ($icerik) {
    $sayfa = $icerik->pageBySlug('hakkimizda');
    $id = (int) $sayfa['id'];

    $icerik->savePage($id, [
        'title' => 'Hakkimizda v2', 'slug' => 'hakkimizda', 'content' => '<p>Yeni</p>',
        'meta_description' => '', 'status' => 'published', 'show_in_menu' => 1, 'menu_order' => 1,
    ]);

    T::same(2, count($icerik->pages(yalnizYayinda: false)), 'sayfa sayisi artmamali');
    T::same('Hakkimizda v2', $icerik->page($id)['title']);
});

T::group('Blog');

T::it('kategori ve yazi kaydedilir', function () use ($icerik) {
    $kategoriId = $icerik->saveCategory(null, 'Rehberler', 'rehberler', 1);

    $icerik->savePost(null, [
        'category_id' => $kategoriId, 'title' => 'SMS onay nedir', 'slug' => 'sms-onay-nedir',
        'excerpt' => 'ozet', 'content' => '<p>govde</p>', 'meta_description' => '',
        'status' => 'published', 'published_at' => date('Y-m-d H:i:s', time() - 3600),
    ]);

    $yazi = $icerik->postBySlug('sms-onay-nedir');
    T::same('Rehberler', $yazi['category_name'], 'kategori adi yaziyla birlikte gelmeli');
});

T::it('taslak yazi listede ve adresinde gorunmez', function () use ($icerik) {
    $icerik->savePost(null, [
        'category_id' => null, 'title' => 'Taslak', 'slug' => 'taslak-yazi',
        'excerpt' => '', 'content' => 'x', 'meta_description' => '',
        'status' => 'draft', 'published_at' => date('Y-m-d H:i:s'),
    ]);

    T::same(null, $icerik->postBySlug('taslak-yazi'));
    T::same(1, count($icerik->posts()));
    T::same(2, count($icerik->posts(yalnizYayinda: false)));
});

T::it('gelecek tarihli yazi henuz yayinlanmaz', function () use ($icerik) {
    $icerik->savePost(null, [
        'category_id' => null, 'title' => 'Yarin', 'slug' => 'yarin',
        'excerpt' => '', 'content' => 'x', 'meta_description' => '',
        'status' => 'published', 'published_at' => date('Y-m-d H:i:s', time() + 86400),
    ]);

    $sluglar = array_column($icerik->posts(), 'slug');
    T::true(!in_array('yarin', $sluglar, true), 'ileri tarihli yazi listede olmamali');
});

T::it('kategori silinince yazilar silinmez, kategorisiz kalir', function () use ($icerik) {
    $kategori = $icerik->categoryBySlug('rehberler');
    $oncekiYazi = count($icerik->posts(yalnizYayinda: false));

    $icerik->deleteCategory((int) $kategori['id']);

    T::same($oncekiYazi, count($icerik->posts(yalnizYayinda: false)), 'yazi sayisi degismemeli');
    T::same(null, $icerik->postBySlug('sms-onay-nedir')['category_name']);
});

T::group('Site ayarlari');

T::it('kaydedilir ve okunur', function () {
    $ayar = new SettingsRepository();
    $ayar->save(['site_title' => 'NumaraOnay', 'contact_email' => 'destek@ornek.com']);
    SettingsRepository::unutOnbellek();

    $taze = new SettingsRepository();
    T::same('NumaraOnay', $taze->get('site_title'));
    T::same('destek@ornek.com', $taze->get('contact_email'));
});

T::it('tanimsiz anahtar varsayilana duser', function () {
    SettingsRepository::unutOnbellek();
    T::same('Sanal Numara', (new SettingsRepository())->get('site_title_yok', 'Sanal Numara'));
});

echo "\n" . str_repeat('-', 52) . "\n";
printf("%d gecti, %d basarisiz\n", T::$passed, T::$failed);

@unlink($dbFile);
@unlink($dbFile . '-wal');
@unlink($dbFile . '-shm');

exit(T::$failed === 0 ? 0 : 1);
