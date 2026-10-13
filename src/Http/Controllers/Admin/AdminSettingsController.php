<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;


use Tusk\Security\Attribute\Authenticated;

use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
#[Service(scope: 'request')]
#[Authenticated]
#[Controller]
class AdminSettingsController {
    public function __construct(
        private readonly \TuskPress\Infrastructure\Persistence\MySqlOptionRepository $optionRepo,
        private readonly \TuskPress\Security\SessionGuard $sessionGuard
    ) {}

    #[Route('/wp-admin/options-general', methods: ['GET', 'POST'])]
    public function index(Request $request): Response {
        $siteName = $this->optionRepo->get('blogname', 'TuskPress');
        $siteDesc = $this->optionRepo->get('blogdescription', 'Just another TuskPress site');
        $siteUrl  = $this->optionRepo->get('siteurl', 'http://localhost:8000');
        $adminEmail = $this->optionRepo->get('admin_email', 'admin@example.com');

        if ($request->method === 'POST') {
            $data = $request->getParsedBody();
            $this->optionRepo->set('blogname', $data['blogname'] ?? $siteName);
            $this->optionRepo->set('blogdescription', $data['blogdescription'] ?? $siteDesc);
            $this->optionRepo->set('admin_email', $data['admin_email'] ?? $adminEmail);
            return Response::redirect('/wp-admin/options-general?updated=true');
        }

        $alert = '';
        if (($request->getQueryParams()['updated'] ?? '') === 'true') {
            $alert = '<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; padding: 12px 20px; border-radius: 10px; margin-bottom: 24px; font-size: 14px; font-weight: 500;">Settings successfully saved.</div>';
        }

        $contentHtml = <<<HTML
        {$alert}
        <div class="card">
            <div class="card-header">
                <div class="card-title">General Settings</div>
            </div>
            <div class="card-body">
                <form method="post" action="/wp-admin/options-general" style="max-width: 600px;">
                    <div class="form-group">
                        <label class="form-label">Site Title</label>
                        <input type="text" name="blogname" class="form-control" value="{$siteName}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tagline</label>
                        <input type="text" name="blogdescription" class="form-control" value="{$siteDesc}">
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">In a few words, explain what this site is about.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">WordPress Address (URL)</label>
                        <input type="text" class="form-control" value="{$siteUrl}" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Administration Email Address</label>
                        <input type="email" name="admin_email" class="form-control" value="{$adminEmail}">
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">This email is used for administrative purposes, such as new user notifications.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 16px;">Save Changes</button>
                </form>
            </div>
        </div>
        HTML;

        $user = $this->sessionGuard->user();
        $username = $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrator';

        return Response::html(AdminView::render('General Settings', $contentHtml, $siteName, $username));
    }
}

