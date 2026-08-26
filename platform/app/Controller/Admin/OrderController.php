<?php
namespace Onay\App\Controller\Admin;

use Onay\App\Kernel\Database;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\View;
use Onay\App\Repository\OrderRepository;
use Onay\App\Service\Container;

final class OrderController
{
    public function index(Request $request): Response
    {
        $stmt = Database::pdo()->prepare(
            'SELECT o.*, u.email,
                    COALESCE(s.name, o.service_code) AS service_name,
                    COALESCE(c.name, o.country_code) AS country_name
             FROM number_orders o
             JOIN users u ON u.id = o.user_id
             LEFT JOIN services  s ON s.code = o.service_code
             LEFT JOIN countries c ON c.code = o.country_code
             ORDER BY o.id DESC LIMIT ?'
        );
        $stmt->bindValue(1, 100, \PDO::PARAM_INT);
        $stmt->execute();

        return Response::html(View::render('admin/orders', [
            'title'  => 'Siparisler',
            'orders' => $stmt->fetchAll(),
        ], 'layout/admin'));
    }

    /** Elle iade: musteri destek talep ettiginde ya da saglayici sorununda. */
    public function refund(Request $request, string $id): Response
    {
        $order = (new OrderRepository())->find((int) $id);

        if ($order === null) {
            Session::flash('error', 'Siparis bulunamadi.');

            return Response::redirect('/yonetim/siparisler');
        }

        Container::orderService()->refund((int) $id, 'cancelled');
        Session::flash('success', 'Siparis iade edildi.');

        return Response::redirect('/yonetim/siparisler');
    }
}
