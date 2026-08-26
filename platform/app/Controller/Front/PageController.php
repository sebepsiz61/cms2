<?php
namespace Onay\App\Controller\Front;

use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\View;
use Onay\App\Repository\ContentRepository;

final class PageController
{
    public function show(Request $request, string $slug): Response
    {
        $sayfa = (new ContentRepository())->pageBySlug($slug);

        if ($sayfa === null) {
            return Response::html(View::render('front/404'), 404);
        }

        return Response::html(View::render('front/page', [
            'title'       => $sayfa['title'],
            'description' => $sayfa['meta_description'],
            'sayfa'       => $sayfa,
        ]));
    }
}
