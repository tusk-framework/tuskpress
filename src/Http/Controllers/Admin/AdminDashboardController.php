<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;



use Tusk\Security\Attribute\Authenticated;

use Tusk\Contracts\Attributes\Service;

use Tusk\Web\Attribute\Route;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
use TuskPress\Domain\Post\PostQuery;
use TuskPress\Domain\Post\PostStatus;
use TuskPress\Domain\Post\PostType;
use TuskPress\Infrastructure\Persistence\MySqlPostRepository;
use TuskPress\Infrastructure\Persistence\MySqlUserRepository;
use TuskPress\Infrastructure\Persistence\MySqlCommentRepository;

/**
 * AdminDashboardController — Painel principal do WordPress.
 * URL: /wp-admin/
 */
#[Service]
#[Authenticated]
class AdminDashboardController
{
    public function __construct(
        private readonly MySqlPostRepository    $postRepository,
        private readonly MySqlUserRepository    $userRepository,
        private readonly MySqlCommentRepository $commentRepository,
        private readonly \Tusk\Security\Contract\GuardInterface $guard,
        private readonly \TuskPress\Infrastructure\Persistence\MySqlOptionRepository $optionRepo
    ) {}

    #[Route('/wp-admin', methods: ['GET'])]
    #[Route('/wp-admin/', methods: ['GET'])]
    #[Route('/wp-admin/index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login?redirect_to=/wp-admin/');
        }

        // Estatísticas do site
        $publishedPosts  = $this->postRepository->count(PostQuery::forAdmin(PostStatus::Publish));
        $draftPosts      = $this->postRepository->count(PostQuery::forAdmin(PostStatus::Draft));
        $publishedPages  = $this->postRepository->count(new PostQuery(type: PostType::Page, status: PostStatus::Publish));
        $totalUsers      = $this->userRepository->count();

        $recentPosts = $this->postRepository->findAll(
            new PostQuery(type: PostType::Post, perPage: 5, orderBy: 'post_modified')
        );

        $user = $this->guard->user();
        $username = $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrador';

        $stats = [
            'posts'    => $publishedPosts,
            'drafts'   => $draftPosts,
            'pages'    => $publishedPages,
            'users'    => $totalUsers];
            
        $siteName = $this->optionRepo->get('blogname', 'TuskPress');

        return Response::html($this->renderDashboard($username, $stats, $recentPosts, $siteName));
    }

    private function renderDashboard(string $username, array $stats, array $recentPosts, string $siteName): string
    {
        $postsHtml = '';
        foreach ($recentPosts as $post) {
            $editUrl   = '/wp-admin/post?post=' . $post->id->value . '&action=edit';
            $title     = htmlspecialchars($post->title ?: '(sem título)', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $date      = $post->updatedAt->format('d/m/Y H:i');
            $statusBadge = match ($post->status) {
                PostStatus::Publish => '<span class="badge badge-success">Published</span>',
                PostStatus::Draft   => '<span class="badge badge-warning">Draft</span>',
                default             => '<span class="badge badge-gray">' . htmlspecialchars($post->status->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>',
            };
            $postsHtml .= <<<HTML
            <tr>
                <td><a href="{$editUrl}" style="color:var(--text);text-decoration:none;font-weight:500;">{$title}</a></td>
                <td>{$statusBadge}</td>
                <td style="color:var(--text-muted);">{$date}</td>
            </tr>
            HTML;
        }

        $phpVer = PHP_VERSION;

        $contentHtml = <<<HTML
        <div class="greeting">
            <h2>Olá, {$username}! 👋</h2>
            <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Bem-vindo ao TuskPress — motor moderno, interface familiar.</p>
        </div>

        <!-- Stats -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px;">
            <div class="card" style="margin-bottom:0;">
                <div class="card-body">
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Posts Publicados</div>
                    <div style="font-size: 36px; font-weight: 700; margin-top: 6px; color: var(--accent);">{$stats['posts']}</div>
                    <a href="/wp-admin/edit" style="font-size: 12px; color: var(--accent); text-decoration: none; margin-top: 8px; display: block;">Ver todos →</a>
                </div>
            </div>
            <div class="card" style="margin-bottom:0;">
                <div class="card-body">
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Páginas</div>
                    <div style="font-size: 36px; font-weight: 700; margin-top: 6px; color: var(--success);">{$stats['pages']}</div>
                    <a href="/wp-admin/edit?post_type=page" style="font-size: 12px; color: var(--success); text-decoration: none; margin-top: 8px; display: block;">Ver todas →</a>
                </div>
            </div>
            <div class="card" style="margin-bottom:0;">
                <div class="card-body">
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Rascunhos</div>
                    <div style="font-size: 36px; font-weight: 700; margin-top: 6px; color: var(--warning);">{$stats['drafts']}</div>
                    <a href="/wp-admin/edit?post_status=draft" style="font-size: 12px; color: var(--warning); text-decoration: none; margin-top: 8px; display: block;">Ver rascunhos →</a>
                </div>
            </div>
            <div class="card" style="margin-bottom:0;">
                <div class="card-body">
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Usuários</div>
                    <div style="font-size: 36px; font-weight: 700; margin-top: 6px; color: #06b6d4;">{$stats['users']}</div>
                    <a href="/wp-admin/users" style="font-size: 12px; color: #06b6d4; text-decoration: none; margin-top: 8px; display: block;">Gerenciar →</a>
                </div>
            </div>
        </div>

        <!-- Recent Posts -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Posts Recentes</div>
                <a href="/wp-admin/post-new" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">+ Novo Post</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Status</th>
                                <th>Modificado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$postsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bottom Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">⚡ TuskPress vs WordPress</div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div style="display: flex; align-items: start; gap: 12px; padding: 16px 24px; border-bottom: 1px solid var(--border);">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--success); margin-top: 5px; flex-shrink: 0;"></div>
                        <div>
                            <div style="font-size: 14px; font-weight: 500;">Container IoC persistente em memória</div>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Zero overhead de bootstrap por request</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: start; gap: 12px; padding: 16px 24px; border-bottom: 1px solid var(--border);">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent); margin-top: 5px; flex-shrink: 0;"></div>
                        <div>
                            <div style="font-size: 14px; font-weight: 500;">PHP 8.2+ com Readonly Classes e Enums</div>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Domain-Driven Design e imutabilidade</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: start; gap: 12px; padding: 16px 24px;">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #06b6d4; margin-top: 5px; flex-shrink: 0;"></div>
                        <div>
                            <div style="font-size: 14px; font-weight: 500;">Schema MySQL 100% compatível com WP</div>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Migração direta de sites existentes</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">🔧 Informações do Sistema</div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 14px;">
                        <span style="color: var(--text-muted);">TuskPress</span>
                        <span style="font-weight: 500; color: var(--accent2);">v1.0.0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 14px;">
                        <span style="color: var(--text-muted);">Compatibilidade WP</span>
                        <span style="font-weight: 500; color: var(--accent2);">6.5 (TuskPress)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 14px;">
                        <span style="color: var(--text-muted);">PHP</span>
                        <span style="font-weight: 500; color: var(--accent2);">{$phpVer}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 14px;">
                        <span style="color: var(--text-muted);">Motor de DB</span>
                        <span style="font-weight: 500; color: var(--accent2);">MySQL + PDO</span>
                    </div>
                </div>
            </div>
        </div>
        HTML;

        return AdminView::render('Painel', $contentHtml, $siteName, $username);
    }
}

