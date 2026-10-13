<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;

use Psr\Http\Message\ResponseInterface;
use Tusk\Security\Attribute\Authenticated;
use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
use TuskPress\Plugin\PluginManager;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;

#[Service]
#[Authenticated]
#[Controller]
class AdminPluginController {
    public function __construct(
        private readonly \Tusk\Security\Contract\GuardInterface $guard,
        private readonly MySqlOptionRepository $optionRepo,
        private readonly PluginManager $pluginManager
    ) {}

    #[Route('/wp-admin/plugins', methods: ['GET'])]
    public function index(Request $request): ResponseInterface {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $installed = $this->pluginManager->getInstalledPlugins();
        $activePlugins = $this->optionRepo->get('active_plugins', []);

        $rowsHtml = '';

        foreach ($installed as $path => $data) {
            $isActive = in_array($path, $activePlugins, true);
            $name = htmlspecialchars($data['Name'] ?: $path);
            $desc = htmlspecialchars($data['Description'] ?? '');
            $version = htmlspecialchars($data['Version'] ?? '1.0.0');
            $author = htmlspecialchars($data['Author'] ?? 'Unknown');
            
            if ($isActive) {
                // Ativo
                $borderClass = 'border-left: 4px solid var(--accent); background: rgba(108,99,255,0.03);';
                $actions = <<<HTML
                <form method="POST" action="/wp-admin/plugins" style="display:inline;" data-confirm="Are you sure you want to deactivate this plugin?">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="plugin" value="{$path}">
                    <button type="submit" class="btn btn-ghost" style="color: var(--danger); padding: 0;">Deactivate</button>
                </form>
                HTML;
            } else {
                // Inativo
                $borderClass = 'border-left: 4px solid transparent;';
                $actions = <<<HTML
                <form method="POST" action="/wp-admin/plugins" style="display:inline;">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="plugin" value="{$path}">
                    <button type="submit" class="btn btn-ghost" style="color: var(--success); padding: 0;">Activate</button>
                </form>
                HTML;
            }

            $rowsHtml .= <<<HTML
            <tr style="{$borderClass}">
                <td style="width: 300px;">
                    <div style="font-weight: 600; color: #fff; margin-bottom: 8px;">{$name}</div>
                    <div style="display: flex; gap: 8px; font-size: 12px; align-items: center;">
                        {$actions}
                    </div>
                </td>
                <td>
                    <div style="font-size: 14px; margin-bottom: 8px; line-height: 1.5;">{$desc}</div>
                    <div style="font-size: 12px; color: var(--text-muted);">
                        Version {$version} | By {$author}
                    </div>
                </td>
            </tr>
            HTML;
        }

        if (empty($rowsHtml)) {
            $rowsHtml = '<tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 40px;">No plugins found in wp-content/plugins.</td></tr>';
        }

        $contentHtml = <<<HTML
        <div class="card">
            <div class="card-header">
                <div class="card-title">Installed Plugins</div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Plugin</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$rowsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        HTML;

        $siteName = $this->optionRepo->get('blogname', 'TuskPress');
        $user = $this->guard->user();
        $username = $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrator';

        return Response::html(AdminView::render('Plugins', $contentHtml, $siteName, $username));
    }

    #[Route('/wp-admin/plugins', methods: ['POST'])]
    public function process(Request $request): ResponseInterface {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $body = $request->getParsedBody();
        $action = $body['action'] ?? '';
        $plugin = $body['plugin'] ?? '';

        if ($plugin) {
            $activePlugins = $this->optionRepo->get('active_plugins', []);

            if ($action === 'activate') {
                if (!in_array($plugin, $activePlugins, true)) {
                    $activePlugins[] = $plugin;
                    $this->optionRepo->set('active_plugins', array_values($activePlugins));
                }
            } elseif ($action === 'deactivate') {
                $key = array_search($plugin, $activePlugins, true);
                if ($key !== false) {
                    unset($activePlugins[$key]);
                    $this->optionRepo->set('active_plugins', array_values($activePlugins));
                }
            }
        }

        return Response::redirect('/wp-admin/plugins');
    }
}


