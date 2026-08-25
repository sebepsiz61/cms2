<?php
namespace Onay\App\Kernel;

final class RequireLogin
{
    public static function handle(Request $request): ?Response
    {
        return Auth::check() ? null : Response::redirect('/giris');
    }
}
