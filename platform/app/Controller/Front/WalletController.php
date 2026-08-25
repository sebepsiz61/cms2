<?php
namespace Onay\App\Controller\Front;

use Onay\App\Kernel\Auth;
use Onay\App\Kernel\Config;
use Onay\App\Kernel\Money;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\View;
use Onay\App\Repository\PaymentRepository;
use Onay\App\Service\Container;
use Onay\App\Service\Logger;

final class WalletController
{
    private const ALLOWED_RECEIPT_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf',
    ];

    public function show(Request $request): Response
    {
        return Response::html(View::render('front/wallet', [
            'title'    => 'Bakiye Yukle',
            'user'     => Auth::user(),
            'bank'     => Config::get('bank'),
            'payments' => (new PaymentRepository())->forUser((int) Auth::id()),
        ]));
    }

    public function store(Request $request): Response
    {
        $amount = Money::parse((string) $request->input('tutar', ''));

        if ($amount === null) {
            Session::flash('error', 'Gecerli bir tutar girin.');

            return Response::redirect('/bakiye');
        }

        try {
            $receiptPath = $this->storeReceipt($request);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect('/bakiye');
        }

        $payment = Container::walletService()->requestTopUp((int) Auth::id(), $amount, $receiptPath);

        Session::flash(
            'success',
            'Talebiniz alindi. Havale aciklamasina su referans kodunu yazin: ' . $payment['reference_code']
        );

        return Response::redirect('/bakiye');
    }

    /**
     * Dekont dosyasi dokuman kokunun disina, storage/uploads altina yazilir.
     * Uzanti kullanicidan alinmaz; gercek MIME turune gore belirlenir.
     */
    private function storeReceipt(Request $request): ?string
    {
        $file = $request->files['dekont'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Dekont yuklenemedi.');
        }

        if ($file['size'] > 4 * 1024 * 1024) {
            throw new \RuntimeException('Dekont en fazla 4 MB olabilir.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

        if (!isset(self::ALLOWED_RECEIPT_TYPES[$mime])) {
            throw new \RuntimeException('Dekont yalnizca JPG, PNG veya PDF olabilir.');
        }

        $dir = dirname(__DIR__, 3) . '/storage/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED_RECEIPT_TYPES[$mime];

        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            Logger::error('Dekont tasinamadi', ['name' => $name]);
            throw new \RuntimeException('Dekont kaydedilemedi.');
        }

        return $name;
    }
}
