<?php
namespace Onay\App\Controller\Front;

use Onay\App\Kernel\Auth;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\View;
use Onay\App\Repository\CatalogRepository;

final class HomeController
{
    public function index(Request $request): Response
    {
        if (Auth::check()) {
            return Response::redirect(Auth::isAdmin() ? '/yonetim' : '/panel');
        }

        return Response::html(View::render('front/home', [
            'title'     => 'Sanal Numara ve SMS Onay',
            'countries' => (new CatalogRepository())->availableCountries(),
        ]));
    }
}
