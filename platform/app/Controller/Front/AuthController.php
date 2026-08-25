<?php
namespace Onay\App\Controller\Front;

use Onay\App\Kernel\Auth;
use Onay\App\Kernel\Request;
use Onay\App\Kernel\Response;
use Onay\App\Kernel\Session;
use Onay\App\Kernel\View;
use Onay\App\Repository\UserRepository;
use Onay\App\Service\Logger;

final class AuthController
{
    public function showLogin(Request $request): Response
    {
        return Auth::check()
            ? Response::redirect('/panel')
            : Response::html(View::render('auth/login', ['title' => 'Giris Yap']));
    }

    public function login(Request $request): Response
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        if (!Auth::attempt($email, $password)) {
            Logger::warn('Basarisiz giris denemesi', ['email' => $email, 'ip' => $request->ip()]);
            Session::flash('error', 'E-posta veya sifre hatali.');

            return Response::redirect('/giris');
        }

        return Response::redirect(Auth::isAdmin() ? '/yonetim' : '/panel');
    }

    public function showRegister(Request $request): Response
    {
        return Auth::check()
            ? Response::redirect('/panel')
            : Response::html(View::render('auth/register', ['title' => 'Kayit Ol']));
    }

    public function register(Request $request): Response
    {
        $name = (string) $request->input('name', '');
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        $errors = [];

        if (mb_strlen($name) < 2) {
            $errors[] = 'Ad soyad en az 2 karakter olmali.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Gecerli bir e-posta adresi girin.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Sifre en az 8 karakter olmali.';
        }

        $users = new UserRepository();

        if ($errors === [] && $users->emailExists($email)) {
            $errors[] = 'Bu e-posta adresi zaten kayitli.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                Session::flash('error', $error);
            }

            return Response::redirect('/kayit');
        }

        // Kayit her zaman 'customer' rolu ile acilir. Yonetici rolu yalnizca
        // veritabanindan ya da kurulum betiginden verilir.
        $id = $users->create($name, $email, $password);
        Auth::login($id);

        return Response::redirect('/panel');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        return Response::redirect('/');
    }
}
