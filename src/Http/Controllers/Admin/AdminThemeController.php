<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;


use Tusk\Security\Attribute\Authenticated;

use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Attribute\Route;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
#[Service]
#[Authenticated]
class AdminThemeController {
    public function __construct(
        private readonly \Tusk\Security\Contract\GuardInterface $guard,
        private readonly \TuskPress\Infrastructure\Persistence\MySqlOptionRepository $optionRepo
    ) {}

    #[Route('/wp-admin/themes', methods: ['GET'])]
    public function index(Request $request): Response {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $activeTheme = $this->optionRepo->get('template', 'tusk-modern');
        $themesDir = dirname(__DIR__, 4) . '/public/wp-content/themes';
        $themesHtml = '';

        if (is_dir($themesDir)) {
            $folders = array_diff(scandir($themesDir), ['..', '.']);
            foreach ($folders as $folder) {
                if (is_dir($themesDir . '/' . $folder)) {
                    $isActive = ($folder === $activeTheme);
                    $themeName = ucfirst($folder);
                    $screenshot = '/wp-content/themes/' . $folder . '/screenshot.png';
                    
                    // Fallback to random unsplash if no screenshot
                    if (!file_exists($themesDir . '/' . $folder . '/screenshot.png') && !file_exists($themesDir . '/' . $folder . '/screenshot.jpg')) {
                        $screenshot = 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80';
                    }

                    if ($isActive) {
                        $themesHtml = $this->renderActiveThemeCard($themeName, $screenshot, $folder) . $themesHtml;
                    } else {
                        $themesHtml .= $this->renderThemeCard($themeName, $screenshot, $folder);
                    }
                }
            }
        }

        $contentHtml = <<<HTML
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 style="font-size: 20px; font-weight: 600;">Installed Themes</h2>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
            {$themesHtml}
        </div>
        HTML;

        $siteName = $this->optionRepo->get('blogname', 'TuskPress');
        $user = $this->guard->user();
        $username = $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrator';

        return Response::html(AdminView::render('Themes', $contentHtml, $siteName, $username));
    }

    #[Route('/wp-admin/themes/activate', methods: ['POST'])]
    public function activate(Request $request): Response {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $theme = $request->request['theme'] ?? '';
        if ($theme) {
            $this->optionRepo->set('template', $theme);
        }

        return Response::redirect('/wp-admin/themes');
    }

    #[Route('/wp-admin/customize', methods: ['GET'])]
    public function customize(Request $request): Response {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $activeTheme = $this->optionRepo->get('template', 'tusk-modern');
        
        $contentHtml = <<<HTML
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Customize: {$activeTheme}</h2>
            </div>
            <div class="card-body">
                <p style="color: var(--text-muted); margin-bottom: 24px;">The real-time Visual Customizer is planned for upcoming versions of TuskPress.</p>
                
                <div style="background: rgba(108,99,255,0.05); border: 1px solid rgba(108,99,255,0.2); padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                    <h3 style="font-size: 16px; margin-bottom: 12px; color: #fff;">Quick Settings</h3>
                    <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px;">To change basic visual identity (Site Title and Tagline), go to general settings.</p>
                    <a href="/wp-admin/options-general" class="btn btn-primary">Go to General Settings</a>
                </div>
                
                <a href="/wp-admin/themes" class="btn btn-ghost">← Back to Themes</a>
            </div>
        </div>
        HTML;

        $siteName = $this->optionRepo->get('blogname', 'TuskPress');
        $user = $this->guard->user();
        $username = $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrator';

        return Response::html(AdminView::render('Customize', $contentHtml, $siteName, $username));
    }

    private function renderActiveThemeCard(string $themeName, string $screenshot, string $folder): string {
        return <<<HTML
        <div class="card" style="border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent);">
            <div style="aspect-ratio: 16/9; background: #222; position: relative;">
                <img src="{$screenshot}" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.8;" alt="">
                <div style="position: absolute; top: 12px; right: 12px; background: var(--accent); color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">Active</div>
            </div>
            <div class="card-body">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">{$themeName}</h3>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px; line-height: 1.5;">The current default theme. ({$folder})</p>
                <div style="display: flex; gap: 8px;">
                    <a href="/wp-admin/customize" class="btn btn-primary" style="flex: 1; justify-content: center; text-decoration: none;">Customize</a>
                    <a href="/" target="_blank" class="btn btn-ghost" style="flex: 1; justify-content: center; text-decoration: none;">View Site</a>
                </div>
            </div>
        </div>
        HTML;
    }

    private function renderThemeCard(string $themeName, string $screenshot, string $folder): string {
        return <<<HTML
        <div class="card">
            <div style="aspect-ratio: 16/9; background: #111; position: relative;">
                <img src="{$screenshot}" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.6;" alt="">
            </div>
            <div class="card-body">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">{$themeName}</h3>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px; line-height: 1.5;">Theme installed in the {$folder} directory.</p>
                <div style="display: flex; gap: 8px;">
                    <form method="POST" action="/wp-admin/themes/activate" style="flex: 1; display: flex;">
                        <input type="hidden" name="theme" value="{$folder}">
                        <button type="submit" class="btn btn-ghost" style="flex: 1; justify-content: center;">Activate</button>
                    </form>
                </div>
            </div>
        </div>
        HTML;
    }
}


