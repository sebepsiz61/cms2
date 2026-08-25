<?php
namespace Onay\App\Kernel;

/**
 * Yonetici sayfalari "giris yapmis olmak" ile korunmaz, admin rolu ister.
 * Eski cms2'de /admin yalnizca auth ile korundugu ve kayit acik oldugu icin
 * kayit olan herkes panele girebiliyordu; burada rol rotanin sartidir.
 */
final class RequireAdmin
{
    public static function handle(Request $request): ?Response
    {
        if (!Auth::check()) {
            return Response::redirect('/giris');
        }

        return Auth::isAdmin() ? null : Response::html(View::render('front/403'), 403);
    }
}
