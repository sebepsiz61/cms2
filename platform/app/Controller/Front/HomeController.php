<?php
namespace Onay\App\Controller\Front;

use Onay\App\Kernel\Auth;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\View;
use Onay\App\Repository\CatalogRepository;
use Onay\App\Repository\ContentRepository;
use Onay\App\Repository\SettingsRepository;

final class HomeController
{
    public function index(Request $request): Response
    {
        if (Auth::check()) {
            return Response::redirect(Auth::isAdmin() ? '/yonetim' : '/panel');
        }

        $ayar = new SettingsRepository();

        return Response::html(View::render('front/home', [
            'title'       => $ayar->get('site_tagline'),
            'description' => $ayar->get('site_description'),
            'ayar'        => $ayar,
            'countries'   => (new CatalogRepository())->availableCountries(),
            'yazilar'     => array_slice((new ContentRepository())->posts(limit: 3), 0, 3),
        ]));
    }
}
