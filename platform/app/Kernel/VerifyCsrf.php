<?php
namespace Onay\App\Kernel;

final class VerifyCsrf
{
    public static function handle(Request $request): ?Response
    {
        if (!$request->isPost() || Csrf::check($request->input('_token'))) {
            return null;
        }

        if ($request->wantsJson()) {
            return Response::json(['error' => 'Oturum dogrulamasi basarisiz, sayfayi yenileyin.'], 419);
        }

        Session::flash('error', 'Oturum dogrulamasi basarisiz. Lutfen tekrar deneyin.');

        return Response::redirect($request->path);
    }
}
