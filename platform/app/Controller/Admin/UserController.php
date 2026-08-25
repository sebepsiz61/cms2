<?php
namespace Onay\App\Controller\Admin;

use Onay\App\Kernel\Auth;
use Onay\App\Kernel\Database;
use Onay\App\Kernel\Money;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\View;
use Onay\App\Repository\UserRepository;
use Onay\App\Service\Container;
use Onay\Core\Contract\LedgerInterface;

final class UserController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/users', [
            'title' => 'Kullanicilar',
            'users' => (new UserRepository())->all(),
        ], 'layout/admin'));
    }

    public function update(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $role = (string) $request->input('role', 'customer');
        $status = (string) $request->input('status', 'active');

        if (!in_array($role, ['admin', 'reseller', 'customer'], true)
            || !in_array($status, ['active', 'suspended'], true)) {
            Session::flash('error', 'Gecersiz rol veya durum.');

            return Response::redirect('/yonetim/kullanicilar');
        }

        // Kendi yonetici rolunu dusurup panelden kilitlenmeyi engelle.
        if ($userId === Auth::id() && $role !== 'admin') {
            Session::flash('error', 'Kendi yonetici rolunuzu kaldiramazsiniz.');

            return Response::redirect('/yonetim/kullanicilar');
        }

        $stmt = Database::pdo()->prepare('UPDATE users SET role = ?, status = ? WHERE id = ?');
        $stmt->execute([$role, $status, $userId]);
        Session::flash('success', 'Kullanici guncellendi.');

        return Response::redirect('/yonetim/kullanicilar');
    }

    /**
     * Elle bakiye duzeltmesi. Defterde 'adjust' hareketi olarak gorunur; hicbir
     * kayit silinmez, duzeltme de bir harekettir.
     */
    public function adjust(Request $request, string $id): Response
    {
        $amount = Money::parse((string) $request->input('tutar', ''));
        $direction = (string) $request->input('yon', 'credit');
        $note = (string) $request->input('not', '');

        if ($amount === null) {
            Session::flash('error', 'Gecerli bir tutar girin.');

            return Response::redirect('/yonetim/kullanicilar');
        }

        $ledger = Container::ledger();
        $key = 'adjust-' . $id . '-' . bin2hex(random_bytes(6));

        try {
            if ($direction === 'debit') {
                $ledger->debit((int) $id, $amount, LedgerInterface::TYPE_ADJUST, $key, 'admin_adjust', Auth::id());
            } else {
                $ledger->credit((int) $id, $amount, LedgerInterface::TYPE_ADJUST, $key, 'admin_adjust', Auth::id());
            }

            Session::flash('success', 'Bakiye duzeltmesi islendi. Not: ' . $note);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect('/yonetim/kullanicilar');
    }
}
