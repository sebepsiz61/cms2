<?php
namespace Onay\App\Controller\Admin;

use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\View;
use Onay\App\Repository\ContentRepository;

/**
 * Sayfa, yazi ve kategori yonetimi. Metin alanlarina HTML kabul edilir; bu
 * ekranlar yalnizca yoneticiye aciktir ve girdi yoneticinin kendi icerigidir.
 */
final class ContentController
{
    // --- Sayfalar -------------------------------------------------------

    public function pages(Request $request): Response
    {
        return Response::html(View::render('admin/pages', [
            'title'    => 'Sayfalar',
            'sayfalar' => (new ContentRepository())->pages(yalnizYayinda: false),
        ], 'layout/admin'));
    }

    public function pageForm(Request $request, string $id = 'yeni'): Response
    {
        $icerik = new ContentRepository();
        $sayfa = $id === 'yeni' ? null : $icerik->page((int) $id);

        if ($id !== 'yeni' && $sayfa === null) {
            return Response::html(View::render('front/404'), 404);
        }

        return Response::html(View::render('admin/page-form', [
            'title' => $sayfa === null ? 'Yeni sayfa' : 'Sayfayi duzenle',
            'sayfa' => $sayfa,
        ], 'layout/admin'));
    }

    public function savePage(Request $request, string $id = 'yeni'): Response
    {
        $baslik = (string) $request->input('title', '');

        if (trim($baslik) === '') {
            Session::flash('error', 'Baslik bos olamaz.');

            return Response::redirect('/yonetim/sayfalar');
        }

        $icerik = new ContentRepository();
        $slug = (string) $request->input('slug', '');

        $icerik->savePage($id === 'yeni' ? null : (int) $id, [
            'title'            => $baslik,
            'slug'             => $slug !== '' ? ContentRepository::slugify($slug) : ContentRepository::slugify($baslik),
            'content'          => (string) ($_POST['content'] ?? ''),
            'meta_description' => (string) $request->input('meta_description', ''),
            'status'           => $request->input('status') === 'published' ? 'published' : 'draft',
            'show_in_menu'     => $request->input('show_in_menu') !== null ? 1 : 0,
            'menu_order'       => $request->int('menu_order'),
        ]);

        Session::flash('success', 'Sayfa kaydedildi.');

        return Response::redirect('/yonetim/sayfalar');
    }

    public function deletePage(Request $request, string $id): Response
    {
        (new ContentRepository())->deletePage((int) $id);
        Session::flash('success', 'Sayfa silindi.');

        return Response::redirect('/yonetim/sayfalar');
    }

    // --- Yazilar --------------------------------------------------------

    public function posts(Request $request): Response
    {
        $icerik = new ContentRepository();

        return Response::html(View::render('admin/posts', [
            'title'       => 'Blog yazilari',
            'yazilar'     => $icerik->posts(yalnizYayinda: false, limit: 200),
            'kategoriler' => $icerik->categories(),
        ], 'layout/admin'));
    }

    public function postForm(Request $request, string $id = 'yeni'): Response
    {
        $icerik = new ContentRepository();
        $yazi = $id === 'yeni' ? null : $icerik->post((int) $id);

        if ($id !== 'yeni' && $yazi === null) {
            return Response::html(View::render('front/404'), 404);
        }

        return Response::html(View::render('admin/post-form', [
            'title'       => $yazi === null ? 'Yeni yazi' : 'Yaziyi duzenle',
            'yazi'        => $yazi,
            'kategoriler' => $icerik->categories(),
        ], 'layout/admin'));
    }

    public function savePost(Request $request, string $id = 'yeni'): Response
    {
        $baslik = (string) $request->input('title', '');

        if (trim($baslik) === '') {
            Session::flash('error', 'Baslik bos olamaz.');

            return Response::redirect('/yonetim/yazilar');
        }

        $icerik = new ContentRepository();
        $slug = (string) $request->input('slug', '');
        $kategori = $request->int('category_id');
        $yayinTarihi = (string) $request->input('published_at', '');

        $icerik->savePost($id === 'yeni' ? null : (int) $id, [
            'category_id'      => $kategori > 0 ? $kategori : null,
            'title'            => $baslik,
            'slug'             => $slug !== '' ? ContentRepository::slugify($slug) : ContentRepository::slugify($baslik),
            'excerpt'          => (string) $request->input('excerpt', ''),
            'content'          => (string) ($_POST['content'] ?? ''),
            'meta_description' => (string) $request->input('meta_description', ''),
            'status'           => $request->input('status') === 'published' ? 'published' : 'draft',
            'published_at'     => $yayinTarihi !== '' ? $yayinTarihi : date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Yazi kaydedildi.');

        return Response::redirect('/yonetim/yazilar');
    }

    public function deletePost(Request $request, string $id): Response
    {
        (new ContentRepository())->deletePost((int) $id);
        Session::flash('success', 'Yazi silindi.');

        return Response::redirect('/yonetim/yazilar');
    }

    // --- Kategoriler ----------------------------------------------------

    public function saveCategory(Request $request): Response
    {
        $ad = (string) $request->input('name', '');

        if (trim($ad) === '') {
            Session::flash('error', 'Kategori adi bos olamaz.');

            return Response::redirect('/yonetim/yazilar');
        }

        $id = $request->int('id');
        (new ContentRepository())->saveCategory(
            $id > 0 ? $id : null,
            $ad,
            ContentRepository::slugify((string) ($request->input('slug') ?: $ad)),
            $request->int('sort_order')
        );

        Session::flash('success', 'Kategori kaydedildi.');

        return Response::redirect('/yonetim/yazilar');
    }

    public function deleteCategory(Request $request, string $id): Response
    {
        (new ContentRepository())->deleteCategory((int) $id);
        Session::flash('success', 'Kategori silindi, yazilar kategorisiz kaldi.');

        return Response::redirect('/yonetim/yazilar');
    }
}
