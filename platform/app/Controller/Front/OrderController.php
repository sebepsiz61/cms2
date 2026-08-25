<?php
namespace Onay\App\Controller\Front;

use Onay\App\Kernel\Auth;
use Onay\App\Kernel\Money;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\View;
use Onay\App\Repository\OrderRepository;
use Onay\App\Service\Container;
use Onay\App\Service\Logger;
use Onay\Core\Exception\InsufficientBalanceException;
use Onay\Core\Exception\OutOfStockException;

final class OrderController
{
    public function create(Request $request): Response
    {
        $country = (string) $request->input('ulke', '');
        $service = (string) $request->input('servis', '');

        try {
            $order = Container::orderService()->purchase((int) Auth::id(), $country, $service);
        } catch (InsufficientBalanceException $e) {
            Session::flash('error', 'Bakiyeniz yetersiz. Gereken: ' . Money::format($e->requestedMinor));

            return Response::redirect('/bakiye');
        } catch (OutOfStockException $e) {
            Session::flash('error', 'Bu servis icin su anda numara bulunamadi. Baska bir ulke deneyin.');

            return Response::redirect('/panel');
        } catch (\Throwable $e) {
            Logger::error('Siparis olusturulamadi', ['hata' => $e->getMessage()]);
            Session::flash('error', 'Numara alinamadi. Bakiyeniz etkilenmedi.');

            return Response::redirect('/panel');
        }

        return Response::redirect('/siparis/' . $order['id']);
    }

    public function show(Request $request, string $id): Response
    {
        $order = (new OrderRepository())->findForUser((int) $id, (int) Auth::id());

        if ($order === null) {
            return Response::html(View::render('front/404'), 404);
        }

        return Response::html(View::render('front/order', [
            'title'    => 'Siparis #' . $order['id'],
            'order'    => $order,
            'messages' => (new OrderRepository())->messages((int) $order['id']),
        ]));
    }

    /** Tarayici bu ucu saniyede bir cagirir; sunucu saglayiciya sorar. */
    public function poll(Request $request, string $id): Response
    {
        $orders = new OrderRepository();
        $order = $orders->findForUser((int) $id, (int) Auth::id());

        if ($order === null) {
            return Response::json(['error' => 'Siparis bulunamadi.'], 404);
        }

        try {
            $order = Container::orderService()->poll($order);
        } catch (\Throwable $e) {
            Logger::warn('Yoklama basarisiz', ['order' => $id, 'hata' => $e->getMessage()]);
        }

        return Response::json([
            'status'    => $order['status'],
            'code'      => $order['code'],
            'phone'     => $order['phone'],
            'expiresIn' => max(0, strtotime((string) $order['expires_at']) - time()),
            'messages'  => array_map(
                static fn (array $m): array => ['sender' => $m['sender'], 'body' => $m['body'], 'code' => $m['code']],
                $orders->messages((int) $order['id'])
            ),
        ]);
    }

    public function cancel(Request $request, string $id): Response
    {
        $order = (new OrderRepository())->findForUser((int) $id, (int) Auth::id());

        if ($order === null) {
            return Response::html(View::render('front/404'), 404);
        }

        Container::orderService()->refund((int) $order['id'], 'cancelled');
        Session::flash('success', 'Numara iptal edildi, ucret bakiyenize iade edildi.');

        return Response::redirect('/panel');
    }

    public function complete(Request $request, string $id): Response
    {
        $order = (new OrderRepository())->findForUser((int) $id, (int) Auth::id());

        if ($order === null) {
            return Response::html(View::render('front/404'), 404);
        }

        try {
            Container::orderService()->complete($order);
            Session::flash('success', 'Siparis tamamlandi.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect('/siparis/' . $id);
    }
}
