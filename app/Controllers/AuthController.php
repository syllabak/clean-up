<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Auth, Audit, RateLimiter, Request, Session};

final class AuthController extends Controller
{
    public function form(): void
    {
        if (Auth::check()) redirect(self::home());
        $this->view('public/login', ['title' => 'Connexion']);
    }

    public function login(): void
    {
        // Verrouillage par compte (en plus de la limite par IP) : 10 échecs en 15 minutes bloquent l'identifiant, même depuis plusieurs IP.
        $acct = 'login_acct|' . mb_strtolower(Request::str('email'));
        if (RateLimiter::blocked($acct, 10, 900)) {
            Audit::log('login_locked', 'user', null, ['email' => mb_substr(Request::str('email'), 0, 120)], null);
            $this->err('Trop de tentatives pour ce compte. Réessayez dans 15 minutes.');
            redirect('/connexion');
        }
        if (!Auth::attempt(Request::str('email'), (string) Request::input('password', ''))) {
            RateLimiter::hit($acct, 10, 900);
            Audit::log('login_failed', 'user', null, ['email' => mb_substr(Request::str('email'), 0, 120)], null);
            $this->err('Email ou mot de passe incorrect.');
            $_SESSION['_old'] = ['email' => Request::str('email')];
            redirect('/connexion');
        }
        RateLimiter::clear($acct);
        $to = $_SESSION['intended'] ?? null;
        unset($_SESSION['intended']);
        redirect($to && str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : self::home());
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/connexion');
    }

    public static function home(): string { return Auth::isAgentOnly() ? '/agent' : '/admin'; }
}
