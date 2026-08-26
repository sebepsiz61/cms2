<?php
/**
 * Tek giris noktasi. cPanel'de dokuman kokü bu klasor olmalidir; boylece
 * app/, core/, config/ ve storage/ web'den erisilemez.
 */
declare(strict_types=1);

use Onay\App\Controller\Admin;
use Onay\App\Controller\Front;
use Onay\App\Kernel\Config;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\RequireAdmin;
use Onay\App\Kernel\RequireLogin;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Router;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\VerifyCsrf;
use Onay\App\Kernel\View;
use Onay\App\Service\Logger;

$root = dirname(__DIR__);

require $root . '/autoload.php';
require $root . '/app/Kernel/helpers.php';

Config::load($root . '/config/config.php');
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Istanbul'));

$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

Session::start();

$router = new Router();

// Genel
$router->get('/', [Front\HomeController::class, 'index']);
$router->get('/sayfa/{slug}', [Front\PageController::class, 'show']);
$router->get('/blog', [Front\BlogController::class, 'index']);
$router->get('/blog/kategori/{slug}', [Front\BlogController::class, 'category']);
$router->get('/blog/{slug}', [Front\BlogController::class, 'show']);
$router->get('/giris', [Front\AuthController::class, 'showLogin']);
$router->post('/giris', [Front\AuthController::class, 'login'], [VerifyCsrf::class]);
$router->get('/kayit', [Front\AuthController::class, 'showRegister']);
$router->post('/kayit', [Front\AuthController::class, 'register'], [VerifyCsrf::class]);
$router->post('/cikis', [Front\AuthController::class, 'logout'], [VerifyCsrf::class]);

// Musteri
$router->get('/panel', [Front\DashboardController::class, 'index'], [RequireLogin::class]);
$router->get('/bakiye', [Front\WalletController::class, 'show'], [RequireLogin::class]);
$router->post('/bakiye', [Front\WalletController::class, 'store'], [RequireLogin::class, VerifyCsrf::class]);
$router->post('/siparis', [Front\OrderController::class, 'create'], [RequireLogin::class, VerifyCsrf::class]);
$router->get('/siparis/{id}', [Front\OrderController::class, 'show'], [RequireLogin::class]);
$router->get('/siparis/{id}/durum', [Front\OrderController::class, 'poll'], [RequireLogin::class]);
$router->post('/siparis/{id}/iptal', [Front\OrderController::class, 'cancel'], [RequireLogin::class, VerifyCsrf::class]);
$router->post('/siparis/{id}/tamamla', [Front\OrderController::class, 'complete'], [RequireLogin::class, VerifyCsrf::class]);

// Yonetim
$admin = [RequireAdmin::class];
$adminPost = [RequireAdmin::class, VerifyCsrf::class];

$router->get('/yonetim', [Admin\DashboardController::class, 'index'], $admin);
$router->get('/yonetim/havaleler', [Admin\PaymentController::class, 'index'], $admin);
$router->get('/yonetim/havaleler/{id}/dekont', [Admin\PaymentController::class, 'receipt'], $admin);
$router->post('/yonetim/havaleler/{id}/onayla', [Admin\PaymentController::class, 'approve'], $adminPost);
$router->post('/yonetim/havaleler/{id}/reddet', [Admin\PaymentController::class, 'reject'], $adminPost);
$router->get('/yonetim/siparisler', [Admin\OrderController::class, 'index'], $admin);
$router->post('/yonetim/siparisler/{id}/iade', [Admin\OrderController::class, 'refund'], $adminPost);
$router->get('/yonetim/katalog', [Admin\CatalogController::class, 'index'], $admin);
$router->post('/yonetim/katalog/esle', [Admin\CatalogController::class, 'map'], $adminPost);
$router->post('/yonetim/katalog/ulke', [Admin\CatalogController::class, 'addCountry'], $adminPost);
$router->post('/yonetim/katalog/servis', [Admin\CatalogController::class, 'addService'], $adminPost);
$router->post('/yonetim/katalog/senkron', [Admin\CatalogController::class, 'sync'], $adminPost);
// Icerik yonetimi
$router->get('/yonetim/sayfalar', [Admin\ContentController::class, 'pages'], $admin);
$router->get('/yonetim/sayfalar/{id}', [Admin\ContentController::class, 'pageForm'], $admin);
$router->post('/yonetim/sayfalar/{id}', [Admin\ContentController::class, 'savePage'], $adminPost);
$router->post('/yonetim/sayfalar/{id}/sil', [Admin\ContentController::class, 'deletePage'], $adminPost);
$router->get('/yonetim/yazilar', [Admin\ContentController::class, 'posts'], $admin);
$router->get('/yonetim/yazilar/{id}', [Admin\ContentController::class, 'postForm'], $admin);
$router->post('/yonetim/yazilar/{id}', [Admin\ContentController::class, 'savePost'], $adminPost);
$router->post('/yonetim/yazilar/{id}/sil', [Admin\ContentController::class, 'deletePost'], $adminPost);
$router->post('/yonetim/kategoriler', [Admin\ContentController::class, 'saveCategory'], $adminPost);
$router->post('/yonetim/kategoriler/{id}/sil', [Admin\ContentController::class, 'deleteCategory'], $adminPost);
$router->get('/yonetim/ayarlar', [Admin\SettingsController::class, 'index'], $admin);
$router->post('/yonetim/ayarlar', [Admin\SettingsController::class, 'save'], $adminPost);

$router->get('/yonetim/kullanicilar', [Admin\UserController::class, 'index'], $admin);
$router->post('/yonetim/kullanicilar/{id}/guncelle', [Admin\UserController::class, 'update'], $adminPost);
$router->post('/yonetim/kullanicilar/{id}/bakiye', [Admin\UserController::class, 'adjust'], $adminPost);

try {
    $router->dispatch(Request::capture())->send();
} catch (\Throwable $e) {
    Logger::error('Yakalanmamis hata', [
        'mesaj' => $e->getMessage(),
        'dosya' => $e->getFile() . ':' . $e->getLine(),
    ]);

    if ($debug) {
        throw $e;
    }

    Response::html(View::render('front/500', ['title' => 'Hata']), 500)->send();
}
