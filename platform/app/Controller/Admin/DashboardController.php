<?php
namespace Onay\App\Controller\Admin;

use Onay\App\Kernel\Database;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\View;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $pdo = Database::pdo();

        $satis = $pdo->query(
            "SELECT COUNT(*) AS adet, COALESCE(SUM(price_minor),0) AS ciro
             FROM number_orders WHERE status = 'completed'"
        )->fetch();

        $iade = $pdo->query(
            "SELECT COUNT(*) AS adet FROM number_orders WHERE status IN ('cancelled','expired')"
        )->fetch();

        $bekleyen = $pdo->query("SELECT COUNT(*) FROM payment_requests WHERE status = 'pending'")->fetchColumn();
        $acik = $pdo->query("SELECT COUNT(*) FROM number_orders WHERE status IN ('waiting_sms','received')")->fetchColumn();
        $eslenmemis = $pdo->query('SELECT COUNT(*) FROM unmapped_codes')->fetchColumn();

        $toplam = (int) $satis['adet'] + (int) $iade['adet'];

        return Response::html(View::render('admin/index', [
            'title'          => 'Yonetim',
            'ciro'           => (int) $satis['ciro'],
            'tamamlanan'     => (int) $satis['adet'],
            'iadeAdet'       => (int) $iade['adet'],
            'iadeOrani'      => $toplam === 0 ? 0.0 : round((int) $iade['adet'] / $toplam * 100, 1),
            'bekleyenOdeme'  => (int) $bekleyen,
            'acikSiparis'    => (int) $acik,
            'eslenmemis'     => (int) $eslenmemis,
        ], 'layout/admin'));
    }
}
