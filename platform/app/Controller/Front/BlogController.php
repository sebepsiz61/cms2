<?php
namespace Onay\App\Controller\Front;

use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\View;
use Onay\App\Repository\ContentRepository;

final class BlogController
{
    public function index(Request $request): Response
    {
        $icerik = new ContentRepository();

        return Response::html(View::render('front/blog', [
            'title'       => 'Blog',
            'description' => 'Sanal numara ve SMS onayi hakkinda rehberler.',
            'yazilar'     => $icerik->posts(),
            'kategoriler' => $icerik->categories(),
            'kategori'    => null,
        ]));
    }

    public function category(Request $request, string $slug): Response
    {
        $icerik = new ContentRepository();
        $kategori = $icerik->categoryBySlug($slug);

        if ($kategori === null) {
            return Response::html(View::render('front/404'), 404);
        }

        return Response::html(View::render('front/blog', [
            'title'       => $kategori['name'],
            'description' => $kategori['name'] . ' kategorisindeki yazilar.',
            'yazilar'     => $icerik->posts(kategoriId: (int) $kategori['id']),
            'kategoriler' => $icerik->categories(),
            'kategori'    => $kategori,
        ]));
    }

    public function show(Request $request, string $slug): Response
    {
        $icerik = new ContentRepository();
        $yazi = $icerik->postBySlug($slug);

        if ($yazi === null) {
            return Response::html(View::render('front/404'), 404);
        }

        return Response::html(View::render('front/post', [
            'title'       => $yazi['title'],
            'description' => $yazi['meta_description'] ?: $yazi['excerpt'],
            'yazi'        => $yazi,
            'digerleri'   => array_slice(array_filter(
                $icerik->posts(limit: 6),
                static fn (array $y): bool => (int) $y['id'] !== (int) $yazi['id']
            ), 0, 3),
        ]));
    }
}
