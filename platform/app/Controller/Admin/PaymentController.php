<?php
namespace Onay\App\Controller\Admin;

use Onay\App\Kernel\Auth;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\View;
use Onay\App\Repository\PaymentRepository;
use Onay\App\Service\Container;

final class PaymentController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/payments', [
            'title'    => 'Bekleyen Havaleler',
            'payments' => (new PaymentRepository())->pending(),
        ], 'layout/admin'));
    }

    public function approve(Request $request, string $id): Response
    {
        $ok = Container::walletService()->approve((int) $id, (int) Auth::id(), $request->input('not'));

        Session::flash(
            $ok ? 'success' : 'error',
            $ok ? 'Bakiye yuklendi.' : 'Talep zaten sonuclandirilmis; bakiye ikinci kez yuklenmedi.'
        );

        return Response::redirect('/yonetim/havaleler');
    }

    public function reject(Request $request, string $id): Response
    {
        $ok = Container::walletService()->reject((int) $id, (int) Auth::id(), $request->input('not'));

        Session::flash($ok ? 'success' : 'error', $ok ? 'Talep reddedildi.' : 'Talep zaten sonuclandirilmis.');

        return Response::redirect('/yonetim/havaleler');
    }

    /** Dekont dokuman kokunun disinda durur; yalnizca yoneticiye burada sunulur. */
    public function receipt(Request $request, string $id): Response
    {
        $payment = (new PaymentRepository())->find((int) $id);

        if ($payment === null || $payment['receipt_path'] === null) {
            return Response::html(View::render('front/404'), 404);
        }

        $path = dirname(__DIR__, 3) . '/storage/uploads/' . basename((string) $payment['receipt_path']);

        if (!is_file($path)) {
            return Response::html(View::render('front/404'), 404);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="dekont-' . (int) $id . '"',
        ]);
    }
}
