<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;

use Tusk\Contracts\Attributes\Service;

use Tusk\Web\Attribute\Route;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
use TuskPress\Infrastructure\Persistence\MySqlUserRepository;

use TuskPress\Security\SessionGuard;

/**
 * AdminAuthController — Admin panel login and logout.
 * Keeps /wp-admin/ and /wp-login URLs for compatibility.
 */
#[Service]
class AdminAuthController
{
    public function __construct(
        private readonly MySqlUserRepository $userRepository,
        private readonly \Tusk\Security\Contract\GuardInterface $guard,
        private readonly \TuskPress\Infrastructure\Persistence\MySqlOptionRepository $optionRepo,
        private readonly \TuskPress\Plugin\HookBus $hookBus
    ) {}

    #[Route('/wp-login', methods: ['GET'])]
    #[Route('/wp-admin/login', methods: ['GET'])]
    public function loginForm(Request $request): Response
    {
        if ($this->guard->check()) {
            return Response::redirect('/wp-admin/');
        }

        $error    = $request->query['error'] ?? null;
        $redirect = $request->query['redirect_to'] ?? '/wp-admin/';

        return Response::html($this->renderLoginPage($error, $redirect));
    }

    #[Route('/wp-login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $login    = trim($request->request['log'] ?? '');
        $password = $request->request['pwd'] ?? '';
        $redirect = $request->request['redirect_to'] ?? '/wp-admin/';

        if (empty($login) || empty($password)) {
            return Response::redirect('/wp-login?error=empty_fields');
        }

        // Tries by login or email
        $user = str_contains($login, '@')
            ? $this->userRepository->findByEmail($login)
            : $this->userRepository->findByLogin($login);

        if (!$user || !$user->verifyPassword($password)) {
            $this->hookBus->dispatch('wp_login_failed', ['login' => $login]);
            return Response::redirect('/wp-login?error=incorrect_password');
        }

        // Checks minimum capabilities for admin
        if (!$user->can('edit_posts')) {
            return Response::redirect('/wp-login?error=no_access');
        }

        // Creates session
        session_regenerate_id(true);
        $this->guard->login($user);

        $this->hookBus->dispatch('wp_login', ['user_login' => $user->login, 'user' => $user]);

        return Response::redirect(filter_var($redirect, FILTER_SANITIZE_URL) ?: '/wp-admin/');
    }

    #[Route('/wp-login?action=logout', methods: ['GET'])]
    #[Route('/wp-admin/logout', methods: ['GET'])]
    public function logout(Request $request): Response
    {
        $user = $this->guard->user();
        if ($user) {
            $this->hookBus->dispatch('wp_logout', ['user_id' => $user->getIdentifier()]);
        }

        $this->guard->logout();
        session_destroy();

        return Response::redirect('/wp-login?loggedout=true');
    }

    private function renderLoginPage(string|null $error, string $redirect): string
    {
        $siteName  = $this->optionRepo->get('blogname', 'TuskPress');
        $errorMsg  = match ($error) {
            'empty_fields'      => 'Please fill in username and password.',
            'incorrect_password' => 'Incorrect username or password.',
            'no_access'          => 'You do not have permission to access the panel.',
            default              => '',
        };

        $siteUrl = rtrim($this->optionRepo->get('siteurl', 'http://localhost:8000'), '/');
        $template = $this->optionRepo->get('template', 'twentytwentyfour');
        $logoUrl = $siteUrl . '/wp-content/themes/' . $template . '/screenshot.png';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en-US">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Login — {$siteName}</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .login-box {
                    background: rgba(255,255,255,0.05);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255,255,255,0.1);
                    border-radius: 20px;
                    padding: 48px;
                    width: 380px;
                    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
                }
                .logo {
                    text-align: center;
                    margin-bottom: 32px;
                }
                .logo h1 {
                    font-size: 28px;
                    font-weight: 700;
                    color: #fff;
                    letter-spacing: -0.5px;
                }
                .logo span { color: #6c63ff; }
                .logo p { color: rgba(255,255,255,0.5); font-size: 13px; margin-top: 4px; }
                .error-msg {
                    background: rgba(239,68,68,0.15);
                    border: 1px solid rgba(239,68,68,0.3);
                    border-radius: 8px;
                    color: #fca5a5;
                    font-size: 13px;
                    padding: 10px 14px;
                    margin-bottom: 20px;
                }
                .field { margin-bottom: 16px; }
                label { display: block; color: rgba(255,255,255,0.7); font-size: 13px; margin-bottom: 6px; }
                input[type=text],
                input[type=password] {
                    width: 100%;
                    background: rgba(255,255,255,0.08);
                    border: 1px solid rgba(255,255,255,0.12);
                    border-radius: 10px;
                    color: #fff;
                    font-size: 15px;
                    padding: 12px 16px;
                    transition: all .2s;
                    outline: none;
                }
                input[type=text]:focus,
                input[type=password]:focus {
                    border-color: #6c63ff;
                    background: rgba(108,99,255,0.1);
                    box-shadow: 0 0 0 3px rgba(108,99,255,0.2);
                }
                input[type=text]::placeholder,
                input[type=password]::placeholder { color: rgba(255,255,255,0.3); }
                .remember {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    color: rgba(255,255,255,0.6);
                    font-size: 13px;
                    margin-bottom: 24px;
                }
                .btn {
                    width: 100%;
                    background: linear-gradient(135deg, #6c63ff, #a78bfa);
                    border: none;
                    border-radius: 10px;
                    color: #fff;
                    cursor: pointer;
                    font-size: 15px;
                    font-weight: 600;
                    padding: 13px;
                    transition: all .2s;
                }
                .btn:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 8px 20px rgba(108,99,255,0.4);
                }
                .btn:active { transform: translateY(0); }
                .back-link {
                    text-align: center;
                    margin-top: 20px;
                }
                .back-link a {
                    color: rgba(255,255,255,0.4);
                    font-size: 12px;
                    text-decoration: none;
                }
                .back-link a:hover { color: rgba(255,255,255,0.8); }
                .powered {
                    text-align: center;
                    margin-top: 32px;
                    color: rgba(255,255,255,0.2);
                    font-size: 11px;
                }
            </style>
        </head>
        <body>
            <div class="login-box">
                <div class="logo">
                    <h1>Tusk<span>Press</span></h1>
                    <p>Administrative Panel</p>
                </div>
                {$this->errorHtml($errorMsg)}
                <form method="POST" action="/wp-login" autocomplete="on">
                    <input type="hidden" name="redirect_to" value="{$redirect}">
                    <div class="field">
                        <label for="user_login">Username or Email</label>
                        <input type="text" id="user_login" name="log" placeholder="admin" autofocus autocomplete="username">
                    </div>
                    <div class="field">
                        <label for="user_pass">Password</label>
                        <input type="password" id="user_pass" name="pwd" placeholder="••••••••" autocomplete="current-password">
                    </div>
                    <div class="remember">
                        <input type="checkbox" id="rememberme" name="rememberme">
                        <label for="rememberme" style="margin:0">Remember me</label>
                    </div>
                    <button type="submit" class="btn" id="wp-submit">Log in to Panel</button>
                </form>
                <div class="back-link">
                    <a href="/">← Back to site</a>
                </div>
                <div class="powered">Powered by TuskPress + Tusk Framework</div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function errorHtml(string $msg): string
    {
        if (!$msg) {
            return '';
        }
        return '<div class="error-msg">' . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
    }
}
