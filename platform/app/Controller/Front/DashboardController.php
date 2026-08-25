<?php
namespace Onay\App\Controller\Front;

use Onay\App\Kernel\Auth;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\View;
use Onay\App\Repository\CatalogRepository;
use Onay\App\Repository\OrderRepository;
use Onay\App\Repository\PaymentRepository;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $catalog = new CatalogRepository();
        $selectedCountry = $request->input('ulke');

        return Response::html(View::render('front/dashboard', [
            'title'     => 'Panel',
            'user'      => $user,
            'countries' => $catalog->availableCountries(),
            'selected'  => $selectedCountry,
            'services'  => $selectedCountry === null ? [] : $catalog->availableServices($selectedCountry),
            'orders'    => (new OrderRepository())->forUser((int) $user['id'], 10),
            'payments'  => (new PaymentRepository())->forUser((int) $user['id'], 5),
        ]));
    }
}
