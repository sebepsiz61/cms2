<?php
namespace Onay\App\Controller\Admin;

use Onay\App\Kernel\Database;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\View;
use Onay\App\Repository\CatalogRepository;
use Onay\App\Service\Container;

final class CatalogController
{
    public function index(Request $request): Response
    {
        $catalog = new CatalogRepository();
        $pdo = Database::pdo();

        return Response::html(View::render('admin/catalog', [
            'title'      => 'Katalog',
            'unmapped'   => $catalog->unmapped(),
            'countries'  => $pdo->query('SELECT * FROM countries ORDER BY name')->fetchAll(),
            'services'   => $pdo->query('SELECT * FROM services ORDER BY name')->fetchAll(),
            'offerCount' => (int) $pdo->query('SELECT COUNT(*) FROM provider_offers WHERE stock > 0')->fetchColumn(),
        ], 'layout/admin'));
    }

    /** Eslenmemis bir saglayici kodunu bizim kanonik kodumuza baglar. */
    public function map(Request $request): Response
    {
        $provider = (string) $request->input('provider', '');
        $kind = (string) $request->input('kind', '');
        $providerCode = (string) $request->input('provider_code', '');
        $canonical = (string) $request->input('canonical_code', '');

        if (!in_array($kind, ['country', 'service'], true) || $provider === '' || $providerCode === '' || $canonical === '') {
            Session::flash('error', 'Eksik alan var.');

            return Response::redirect('/yonetim/katalog');
        }

        (new CatalogRepository())->map($provider, $kind, $providerCode, $canonical);
        Session::flash('success', 'Eslesme kaydedildi. Bir sonraki senkronda katalogda gorunecek.');

        return Response::redirect('/yonetim/katalog');
    }

    public function addCountry(Request $request): Response
    {
        $stmt = Database::pdo()->prepare(
            Database::upsert('countries', ['code', 'name', 'is_active'], ['code'], ['name', 'is_active'])
        );
        $stmt->execute([strtoupper((string) $request->input('code', '')), (string) $request->input('name', ''), 1]);
        Session::flash('success', 'Ulke kaydedildi.');

        return Response::redirect('/yonetim/katalog');
    }

    public function addService(Request $request): Response
    {
        $stmt = Database::pdo()->prepare(
            Database::upsert('services', ['code', 'name', 'is_active'], ['code'], ['name', 'is_active'])
        );
        $stmt->execute([strtolower((string) $request->input('code', '')), (string) $request->input('name', ''), 1]);
        Session::flash('success', 'Servis kaydedildi.');

        return Response::redirect('/yonetim/katalog');
    }

    /** Cron'u beklemeden elle senkron. */
    public function sync(Request $request): Response
    {
        $report = Container::catalogSync()->sync();

        Session::flash(
            'success',
            $report === []
                ? 'Etkin saglayici yok. config/config.php icinde en az bir saglayiciyi acin.'
                : 'Senkron tamam: ' . json_encode($report, JSON_UNESCAPED_UNICODE)
        );

        return Response::redirect('/yonetim/katalog');
    }
}
