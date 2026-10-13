<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
use Psr\Http\Message\ResponseInterface;
use TuskPress\Domain\User\User;
use TuskPress\Domain\User\UserRole;
use TuskPress\Domain\User\UserId;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;
use TuskPress\Infrastructure\Database\InstallationLock;
use Tusk\Contracts\Events\EventDispatcherInterface;

#[Service(scope: 'request')]
#[Controller]
class InstallController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MySqlOptionRepository $optionRepo,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    #[Route('/wp-admin/install.php', methods: ['GET'])]
    public function form(Request $request): ResponseInterface
    {
        // Check if already installed
        try {
            if ($this->optionRepo->exists('siteurl')) {
                return Response::redirect('/wp-admin');
            }
        } catch (\Throwable $e) {
            // Tables might not exist yet, which is expected
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en-US">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>TuskPress Installation</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                :root {
                    --bg-deep: #0a0b10;
                    --bg-card: rgba(20, 22, 39, 0.6);
                    --accent: #6c63ff;
                    --accent-hover: #5b52e0;
                    --text: #f1f5f9;
                    --text-muted: #94a3b8;
                    --border: rgba(255, 255, 255, 0.08);
                    --glass-blur: blur(12px);
                }
                body {
                    font-family: 'Inter', sans-serif;
                    background: var(--bg-deep) radial-gradient(circle at top, rgba(108,99,255,0.15), transparent 60%);
                    color: var(--text);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }
                .install-box {
                    background: var(--bg-card);
                    backdrop-filter: var(--glass-blur);
                    -webkit-backdrop-filter: var(--glass-blur);
                    border: 1px solid var(--border);
                    border-radius: 20px;
                    padding: 48px;
                    width: 100%;
                    max-width: 480px;
                    box-shadow: 0 16px 48px rgba(0,0,0,0.4);
                }
                .logo {
                    text-align: center;
                    margin-bottom: 32px;
                }
                .logo h1 { font-size: 32px; font-weight: 700; letter-spacing: -1px; }
                .logo h1 span { color: var(--accent); }
                .logo p { font-size: 14px; color: var(--text-muted); margin-top: 8px; }
                
                .form-group { margin-bottom: 24px; }
                .form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-muted); }
                .form-control { width: 100%; padding: 14px 16px; background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 12px; color: #fff; font-size: 14px; transition: border-color 0.2s; }
                .form-control:focus { outline: none; border-color: var(--accent); background: rgba(0,0,0,0.3); }
                
                .btn { width: 100%; padding: 14px; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; background: var(--accent); color: #fff; box-shadow: 0 4px 12px rgba(108,99,255,0.25); }
                .btn:hover { background: var(--accent-hover); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(108,99,255,0.35); }
            </style>
        </head>
        <body>
            <div class="install-box">
                <div class="logo">
                    <h1>Tusk<span>Press</span></h1>
                    <p>Database and Administrator Setup</p>
                </div>
                <form method="POST" action="/wp-admin/install.php">
                    <div class="form-group">
                        <label class="form-label">Site Title</label>
                        <input type="text" name="site_title" class="form-control" required placeholder="My Awesome Site">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username (Admin)</label>
                        <input type="text" name="admin_user" class="form-control" required placeholder="admin">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="admin_pass" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Administrator Email</label>
                        <input type="email" name="admin_email" class="form-control" required placeholder="email@domain.com">
                    </div>
                    <button type="submit" class="btn">Complete Installation</button>
                </form>
            </div>
        </body>
        </html>
        HTML;

        return Response::html($html);
    }

    #[Route('/wp-admin/install.php', methods: ['POST'])]
    public function process(Request $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $siteTitle = $body['site_title'] ?? 'TuskPress';
        $adminUser = $body['admin_user'] ?? 'admin';
        $adminPass = $body['admin_pass'] ?? '123456';
        $adminEmail = $body['admin_email'] ?? 'admin@localhost';

        $lock = new InstallationLock($this->em->getConnection());
        try {
            if (!$lock->acquire()) {
                return Response::html('Installation is already in progress.', 409);
            }
            try {
                if ($this->optionRepo->exists('siteurl')) {
                    return Response::html('TuskPress is already installed.', 409);
                }
            } catch (TableNotFoundException $e) {
                // Only a missing options table is an expected first-run state.
            }
            // 1. Generate Schema
            $schemaTool = new SchemaTool($this->em);
            $classes = $this->em->getMetadataFactory()->getAllMetadata();
            
            // Drop existing schema to guarantee clean install if tables are mangled
            // $schemaTool->dropSchema($classes);
            
            $schemaTool->updateSchema($classes);

            return $this->em->wrapInTransaction(function () use ($siteTitle, $adminUser, $adminPass, $adminEmail): ResponseInterface {
                // Schema DDL is outside this transaction (MySQL implicitly commits DDL).
                // Settings, administrator, and completion marker commit together.
                $this->optionRepo->set('home', 'http://localhost:8080');
                $this->optionRepo->set('blogname', $siteTitle);
                $this->optionRepo->set('admin_email', $adminEmail);
                $this->optionRepo->set('posts_per_page', 10);
                $this->optionRepo->set('active_plugins', []);
                $this->optionRepo->set('template', 'tusk-modern');
                $this->optionRepo->set('stylesheet', 'tusk-modern');

                // 3. Create initial admin user
                $user = User::create(
                    id: new UserId(1),
                    login: $adminUser,
                    email: $adminEmail,
                    displayName: ucfirst($adminUser),
                    role: UserRole::Administrator,
                    plainPassword: $adminPass
                );

                $this->em->persist($user);
                $this->em->flush();
                $this->optionRepo->set('siteurl', 'http://localhost:8080');

                return Response::redirect('/wp-login');
            });

        } catch (\Throwable $e) {
            $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en-US">
            <head><meta charset="UTF-8"><title>Installation Error</title><style>body{background:#0a0b10;color:#ef4444;font-family:sans-serif;padding:40px;}</style></head>
            <body>
                <h1>Failed to Install TuskPress</h1>
                <p>An error occurred while generating the database tables:</p>
                <pre style="background:rgba(255,255,255,0.05);padding:20px;border-radius:10px;color:#fca5a5;">{$e->getMessage()}</pre>
                <a href="/wp-admin/install.php" style="color:#6c63ff;">Try Again</a>
            </body>
            </html>
            HTML;
            return Response::html($html, 500);
        } finally {
            $lock->release();
        }
    }
}
