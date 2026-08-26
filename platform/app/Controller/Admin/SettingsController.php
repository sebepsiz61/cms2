<?php
namespace Onay\App\Controller\Admin;

use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\View;
use Onay\App\Repository\SettingsRepository;

final class SettingsController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/settings', [
            'title'   => 'Site ayarlari',
            'ayarlar' => (new SettingsRepository())->all(),
        ], 'layout/admin'));
    }

    public function save(Request $request): Response
    {
        $degerler = [];

        foreach (array_keys(SettingsRepository::VARSAYILAN) as $anahtar) {
            $degerler[$anahtar] = (string) ($_POST[$anahtar] ?? '');
        }

        (new SettingsRepository())->save($degerler);
        Session::flash('success', 'Ayarlar kaydedildi.');

        return Response::redirect('/yonetim/ayarlar');
    }
}
