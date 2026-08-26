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
        $katalog = new CatalogRepository();
        $ulkeler = $katalog->availableCountries();

        // Vitrin: en cok servisi olan ulke secilir. Ilk ulkeyi almak, alfabetik
        // olarak basta ama tek servisi olan bir ulkeyi one cikarabiliyordu.
        $vitrinUlke = null;
        $vitrin = [];

        foreach ($ulkeler as $u) {
            $servisler = $katalog->availableServices((string) $u['code']);

            if (count($servisler) > count($vitrin)) {
                $vitrin = $servisler;
                $vitrinUlke = (string) $u['name'];
            }
        }

        $vitrin = array_slice($vitrin, 0, 6);

        return Response::html(View::render('front/home', [
            'title'       => $ayar->get('site_tagline'),
            'description' => $ayar->get('site_description'),
            'ayar'        => $ayar,
            'countries'   => $ulkeler,
            'vitrin'      => $vitrin,
            'vitrinUlke'  => $vitrinUlke,
            'yazilar'     => array_slice((new ContentRepository())->posts(limit: 3), 0, 3),
        ]));
    }
}
