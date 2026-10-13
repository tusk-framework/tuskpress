<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;



use Tusk\Security\Attribute\Authenticated;

use Tusk\Contracts\Attributes\Service;

use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
use Psr\Http\Message\ResponseInterface;
use TuskPress\Domain\Post\Post;
use TuskPress\Domain\Post\PostId;
use TuskPress\Domain\Post\PostQuery;
use TuskPress\Domain\Post\PostStatus;
use TuskPress\Domain\Post\PostType;
use TuskPress\Infrastructure\Persistence\MySqlPostRepository;

/**
 * AdminPostController — CRUD de posts no painel admin.
 * Mantém URLs compatíveis com WordPress (/wp-admin/edit, /wp-admin/post-new, etc.)
 */
#[Service(scope: 'request')]
#[Authenticated]
#[Controller]
class AdminPostController
{
    public function __construct(
        private readonly MySqlPostRepository $postRepository,
        private readonly \Tusk\Security\Contract\GuardInterface $guard,
        private readonly \TuskPress\Infrastructure\Persistence\MySqlOptionRepository $optionRepo
    ) {}

    // =========================================================
    // Lista de posts
    // =========================================================

    #[Route('/wp-admin/edit', methods: ['GET'])]
    public function index(Request $request): ResponseInterface
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login?redirect_to=/wp-admin/edit');
        }

        $type    = PostType::tryFrom($request->query['post_type'] ?? 'post') ?? PostType::Post;
        $status  = PostStatus::tryFrom($request->query['post_status'] ?? '') ?? null;
        $page    = max(1, (int) ($request->query['paged'] ?? 1));
        $search  = trim($request->query['s'] ?? '');

        $query = new PostQuery(
            type:    $type,
            status:  $status,
            perPage: 20,
            page:    $page,
            search:  $search ?: null,
            orderBy: 'post_modified');

        $posts = $this->postRepository->findAll($query);
        $total = $this->postRepository->count($query);

        $counts = [
            'all'     => $this->postRepository->count(new PostQuery(type: $type, status: null)),
            'publish' => $this->postRepository->count(new PostQuery(type: $type, status: PostStatus::Publish)),
            'draft'   => $this->postRepository->count(new PostQuery(type: $type, status: PostStatus::Draft)),
            'trash'   => $this->postRepository->count(new PostQuery(type: $type, status: PostStatus::Trash))];

        $siteName = $this->optionRepo->get('blogname', 'TuskPress');
        $user = $this->guard->user();
        $username = $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrator';

        return Response::html($this->renderPostList($posts, $counts, $type, $status, $page, ceil($total / 20), $search, $siteName, $username));
    }

    // =========================================================
    // Novo post
    // =========================================================

    #[Route('/wp-admin/post-new', methods: ['GET'])]
    public function create(Request $request): ResponseInterface
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $type = PostType::tryFrom($request->query['post_type'] ?? 'post') ?? PostType::Post;
        
        $siteName = $this->optionRepo->get('blogname', 'TuskPress');
        $user = $this->guard->user();
        $username = $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrator';
        
        return Response::html($this->renderEditor(null, $type, $siteName, $username));
    }

    // =========================================================
    // Editar post
    // =========================================================

    #[Route('/wp-admin/post', methods: ['GET'])]
    public function edit(Request $request): ResponseInterface
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        if (($request->query['action'] ?? '') === 'trash') {
            return $this->trash($request);
        }

        $postId = (int) ($request->query['post'] ?? 0);
        $post   = $postId > 0 ? $this->postRepository->findById(new PostId($postId)) : null;

        if (!$post) {
            return Response::redirect('/wp-admin/edit');
        }

        $siteName = $this->optionRepo->get('blogname', 'TuskPress');
        $user = $this->guard->user();
        $username = $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrator';

        return Response::html($this->renderEditor($post, $post->type, $siteName, $username));
    }

    // =========================================================
    // Salvar post (POST)
    // =========================================================

    #[Route('/wp-admin/post', methods: ['POST'])]
    public function save(Request $request): ResponseInterface
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $body = $request->getParsedBody();

        $postId  = (int) ($body['post_ID'] ?? 0);
        $title   = $body['post_title'] ?? '';
        $content = $body['content'] ?? '';
        $excerpt = $body['excerpt'] ?? '';
        $status  = PostStatus::tryFrom($body['post_status'] ?? 'draft') ?? PostStatus::Draft;
        $type    = PostType::tryFrom($body['post_type'] ?? 'post') ?? PostType::Post;
        
        $rawSlug = $body['post_name'] ?? '';
        $slug = $rawSlug ? preg_replace('/[^a-z0-9]+/', '-', strtolower($rawSlug)) : preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
        $slug = trim($slug, '-');

        $user = $this->guard->user();
        $userId = new \TuskPress\Domain\User\UserId($user ? $user->getIdentifier() : 1);

        if ($postId > 0) {
            $existing = $this->postRepository->findById(new PostId($postId));
            if ($existing) {
                $existing->title = $title;
                $existing->content = $content;
                $existing->excerpt = $excerpt;
                $existing->slug = $slug;
                $existing->status = $status;
                $existing->type = $type;
                $existing->updatedAt = new \DateTimeImmutable();
                $existing->updatedAtGmt = new \DateTimeImmutable();
                
                $saved = $this->postRepository->save($existing);
                return Response::redirect('/wp-admin/post?post=' . $saved->id->value . '&action=edit&message=1');
            }
        }

        // Novo post
        $post = new Post(
            id:          new PostId(0),
            title:       $title,
            content:     $content,
            excerpt:     $excerpt,
            slug:        $slug,
            authorId:    $userId,
            type:        $type,
            status:      $status
        );

        $saved = $this->postRepository->save($post);
        return Response::redirect('/wp-admin/post?post=' . $saved->id->value . '&action=edit&message=6');
    }

    // =========================================================
    // Trash
    // =========================================================

    #[Route('/wp-admin/post?action=trash', methods: ['GET'])]
    public function trash(Request $request): ResponseInterface
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $postId = (int) ($request->query['post'] ?? 0);
        if ($postId > 0) {
            $this->postRepository->trash(new PostId($postId));
        }

        return Response::redirect('/wp-admin/edit');
    }

    // =========================================================
    // Renderização (Admin UI)
    // =========================================================

    private function renderPostList(
        array $posts,
        array $counts,
        PostType $type,
        ?PostStatus $status,
        int $page,
        float $maxPages,
        string $search,
        string $siteName,
        string $username): string {
        $typeLabel  = $type === PostType::Page ? 'Pages' : 'Posts';
        $newUrl     = $type === PostType::Page ? '/wp-admin/post-new?post_type=page' : '/wp-admin/post-new';

        $rowsHtml = '';
        foreach ($posts as $post) {
            $editUrl = '/wp-admin/post?post=' . $post->id->value . '&action=edit';
            $viewUrl = '/' . $post->slug;
            $title   = htmlspecialchars($post->title ?: '(untitled)', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $date    = $post->createdAt->format('d/m/Y');
            $modified = $post->updatedAt->format('d/m/Y H:i');

            $statusBadge = match ($post->status) {
                PostStatus::Publish => '<span class="badge badge-success">Published</span>',
                PostStatus::Draft   => '<span class="badge badge-warning">Draft</span>',
                PostStatus::Pending => '<span class="badge badge-info">Pending</span>',
                PostStatus::Trash   => '<span class="badge badge-danger">Trash</span>',
                default             => '<span class="badge badge-gray">' . htmlspecialchars($post->status->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>',
            };

            $rowsHtml .= <<<HTML
            <tr>
                <td>
                    <div style="font-weight: 600; color: #fff; margin-bottom: 6px;">
                        <a href="{$editUrl}">{$title}</a>
                    </div>
                    <div style="font-size: 12px; display: flex; gap: 8px;">
                        <a href="{$editUrl}" style="color: var(--text-muted);">Edit</a>
                        <span style="color: var(--border);">|</span>
                        <a href="/wp-admin/post?post={$post->id->value}&action=trash" style="color: var(--danger);" data-confirm="Move to trash?">Trash</a>
                        <span style="color: var(--border);">|</span>
                        <a href="{$viewUrl}" target="_blank" style="color: var(--accent2);">View</a>
                    </div>
                </td>
                <td>{$statusBadge}</td>
                <td style="color: var(--text-muted);">{$date}</td>
                <td style="color: var(--text-muted);">{$modified}</td>
            </tr>
            HTML;
        }

        if (!$rowsHtml) {
            $rowsHtml = '<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-muted)">No posts found.</td></tr>';
        }

        $allActive     = $status === null ? 'color: #fff; background: rgba(255,255,255,0.1);' : 'color: var(--text-muted);';
        $pubActive     = $status === PostStatus::Publish ? 'color: #fff; background: rgba(255,255,255,0.1);' : 'color: var(--text-muted);';
        $draftActive   = $status === PostStatus::Draft ? 'color: #fff; background: rgba(255,255,255,0.1);' : 'color: var(--text-muted);';
        $trashActive   = $status === PostStatus::Trash ? 'color: #fff; background: rgba(255,255,255,0.1);' : 'color: var(--text-muted);';

        $contentHtml = <<<HTML
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div style="display: flex; gap: 8px;">
                <a href="?post_type={$type->value}" style="text-decoration: none; font-size: 13px; padding: 6px 12px; border-radius: 8px; {$allActive}">All ({$counts['all']})</a>
                <a href="?post_type={$type->value}&post_status=publish" style="text-decoration: none; font-size: 13px; padding: 6px 12px; border-radius: 8px; {$pubActive}">Published ({$counts['publish']})</a>
                <a href="?post_type={$type->value}&post_status=draft" style="text-decoration: none; font-size: 13px; padding: 6px 12px; border-radius: 8px; {$draftActive}">Drafts ({$counts['draft']})</a>
                <a href="?post_type={$type->value}&post_status=trash" style="text-decoration: none; font-size: 13px; padding: 6px 12px; border-radius: 8px; {$trashActive}">Trash ({$counts['trash']})</a>
            </div>
            
            <form method="GET" style="display: flex; gap: 8px;">
                <input type="hidden" name="post_type" value="{$type->value}">
                <input type="text" name="s" value="{$search}" placeholder="Search {$typeLabel}..." class="form-control" style="width: 250px; padding: 8px 12px;">
                <button type="submit" class="btn btn-ghost">Search</button>
            </form>
        </div>

        <div class="card">
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Modified</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$rowsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-muted);">
                <div>Page {$page} of {$maxPages}</div>
                <div style="display: flex; gap: 8px;">
                    <a href="?post_type={$type->value}&paged=1" class="btn btn-ghost" style="padding: 6px 10px;">«</a>
                    <a href="?post_type={$type->value}&paged=" . max(1, $page - 1) . "" class="btn btn-ghost" style="padding: 6px 10px;">‹</a>
                    <a href="?post_type={$type->value}&paged=" . min((int)$maxPages, $page + 1) . "" class="btn btn-ghost" style="padding: 6px 10px;">›</a>
                    <a href="?post_type={$type->value}&paged={$maxPages}" class="btn btn-ghost" style="padding: 6px 10px;">»</a>
                </div>
            </div>
        </div>
        HTML;

        return AdminView::render($typeLabel, $contentHtml, $siteName, $username);
    }

    private function renderEditor(?Post $post, PostType $type, string $siteName, string $username): string
    {
        $title       = $post ? htmlspecialchars($post->title, ENT_QUOTES, 'UTF-8') : '';
        $slug        = $post ? htmlspecialchars($post->slug, ENT_QUOTES, 'UTF-8') : '';
        $excerpt     = $post ? htmlspecialchars($post->excerpt, ENT_QUOTES, 'UTF-8') : '';
        $postId      = $post ? $post->id->value : 0;
        $postType    = $type->value;
        $typeLabel   = $type === PostType::Page ? 'Page' : 'Post';

        $publishSelected = ($post?->status === PostStatus::Publish) ? 'selected' : '';
        $draftSelected   = (!$post || $post->status === PostStatus::Draft) ? 'selected' : '';
        $privateSelected = ($post?->status === PostStatus::Private) ? 'selected' : '';

        // The content must be JSON for Editor.js, or an empty string.
        // If it's not valid JSON (legacy), let's encapsulate it in a paragraph block.
        $contentRaw = $post?->content ?? '';
        $editorData = $contentRaw;
        if (empty($contentRaw)) {
            $editorData = '{"time":'.time().',"blocks":,"version":"2.28.0"}';
        } elseif (!json_decode($contentRaw)) {
            $safeHtml = json_encode($contentRaw);
            $editorData = '{"time":'.time().',"blocks":[{"type":"paragraph","data":{"text":'.$safeHtml.'}}],"version":"2.28.0"}';
        }
        
        $nonce = 'nonce_bypass';
        $siteUrl = $this->getSiteUrl();
        $slugEdited = $this->slugEdited($slug);

        $contentHtml = <<<HTML
        <style>
            /* Reset Editor.js to match Dark Mode */
            .ce-block__content, .ce-toolbar__content { max-width: 100%; }
            .cdx-block { color: var(--text); padding: 0.5em 0; }
            .ce-toolbar__actions { background: rgba(0,0,0,0.5); border-radius: 8px; backdrop-filter: blur(10px); }
            .ce-popover { background: var(--bg-sidebar); border: 1px solid var(--border); }
            .ce-popover__item { color: var(--text); }
            .ce-popover__item:hover { background: rgba(108,99,255,0.2) !important; color: #fff; }
            .ce-inline-toolbar { background: var(--bg-sidebar); border: 1px solid var(--border); }
            .ce-inline-toolbar__buttons, .ce-inline-toolbar__dropdown { color: var(--text); }
            .ce-inline-tool:hover, .ce-inline-toolbar__dropdown:hover { background: rgba(108,99,255,0.2); }
            .tc-wrap { --color-border: var(--border); --color-background: transparent; }
            .tc-table { border-color: var(--border); }
            .tc-cell { border-color: var(--border); color: var(--text); }

            .editor-container { display: flex; gap: 24px; align-items: flex-start; }
            .editor-main { flex: 1; min-width: 0; }
            .editor-sidebar { width: 320px; flex-shrink: 0; }
            
            .title-input { width: 100%; background: transparent; border: none; color: #fff; font-size: 32px; font-weight: 700; outline: none; margin-bottom: 24px; font-family: 'Inter', sans-serif; }
            .title-input::placeholder { color: rgba(255,255,255,0.2); }
            
            #editorjs { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 32px 40px; min-height: 500px; font-size: 16px; line-height: 1.6; }
            
            .sidebar-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
            .sidebar-title { font-size: 14px; font-weight: 600; margin-bottom: 16px; color: #fff; text-transform: uppercase; letter-spacing: 0.5px; }
        </style>

        <form method="POST" action="/wp-admin/post" id="post-form">
            <input type="hidden" name="_wpnonce" value="{$nonce}">
            <input type="hidden" name="post_ID" value="{$postId}">
            <input type="hidden" name="post_type" value="{$postType}">
            <input type="hidden" name="content" id="post_content" value="">

            <div class="editor-container">
                <!-- MAIN CONTENT -->
                <div class="editor-main">
                    <input type="text" name="post_title" value="{$title}" placeholder="Add title" class="title-input" id="title" autocomplete="off">
                    <div id="editorjs"></div>
                </div>

                <!-- SIDEBAR -->
                <div class="editor-sidebar">
                    <div class="sidebar-panel">
                        <div class="sidebar-title">Publish</div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="post_status" class="form-control" style="background: rgba(0,0,0,0.3);">
                                <option value="publish" {$publishSelected}>Published</option>
                                <option value="draft" {$draftSelected}>Draft</option>
                                <option value="private" {$privateSelected}>Private</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 8px; margin-top: 24px;">
                            <button type="submit" name="post_status" value="draft" class="btn btn-ghost" style="flex: 1; justify-content: center;">Save Draft</button>
                            <button type="submit" name="post_status" value="publish" class="btn btn-primary" style="flex: 1; justify-content: center;">Publish</button>
                        </div>
                    </div>

                    <div class="sidebar-panel">
                        <div class="sidebar-title">Permalink</div>
                        <div class="form-group">
                            <input type="text" name="post_name" value="{$slug}" placeholder="friendly-url" class="form-control" id="post_slug" style="background: rgba(0,0,0,0.3);">
                            <div style="font-size: 12px; color: var(--accent2); margin-top: 8px; word-break: break-all;" id="url_preview">{$siteUrl}/</div>
                        </div>
                    </div>

                    <div class="sidebar-panel">
                        <div class="sidebar-title">Excerpt</div>
                        <textarea name="excerpt" class="form-control" rows="4" placeholder="Write an excerpt (optional)..." style="background: rgba(0,0,0,0.3); resize: none;">{$excerpt}</textarea>
                    </div>
                </div>
            </div>
        </form>

        <!-- Editor.js Core and Tools -->
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/simple-image@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/code@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/table@latest"></script>

        <script>
            // Initialize Editor.js
            let editorData = {$editorData};
            const editor = new EditorJS({
                holder: 'editorjs',
                placeholder: 'Click here and type / to choose a block...',
                data: editorData,
                tools: {
                    header: Header,
                    list: EditorjsList,
                    image: SimpleImage, // Placeholder for real image tool later
                    quote: Quote,
                    code: CodeTool,
                    table: Table
                }
            });

            // Handle form submission properly
            const form = document.getElementById('post-form');
            const hiddenContent = document.getElementById('post_content');
            let clickedButtonValue = 'draft'; // Default

            // Capture which button was clicked
            const submitButtons = form.querySelectorAll('button[type="submit"]');
            submitButtons.forEach(button => {
                button.addEventListener('click', function() {
                    clickedButtonValue = this.value;
                });
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Add the post_status as a hidden input since form.submit() won't include button values
                let statusInput = form.querySelector('input[name="post_status"]');
                if (!statusInput) {
                    statusInput = document.createElement('input');
                    statusInput.type = 'hidden';
                    statusInput.name = 'post_status';
                    form.appendChild(statusInput);
                }
                statusInput.value = clickedButtonValue;

                editor.save().then((outputData) => {
                    hiddenContent.value = JSON.stringify(outputData);
                    form.submit();
                }).catch((error) => {
                    console.log('Editor.js saving failed: ', error);
                });
            });

            // URL preview logic
            const slugInput  = document.getElementById('post_slug');
            const urlPreview = document.getElementById('url_preview');
            const siteUrl    = '{$siteUrl}';

            function updateUrlPreview() {
                const slug = slugInput.value.trim();
                urlPreview.textContent = slug ? siteUrl + '/' + slug + '/' : siteUrl + '/';
            }

            slugInput.addEventListener('input', updateUrlPreview);
            updateUrlPreview();

            // Auto-generate slug from title
            const titleInput = document.getElementById('title');
            let slugEdited = {$slugEdited};

            titleInput.addEventListener('input', function() {
                if (!slugEdited) {
                    const generated = this.value.toLowerCase()
                        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9\s-]/g, '')
                        .trim()
                        .replace(/[\s]+/g, '-');
                    slugInput.value = generated;
                    updateUrlPreview();
                }
            });

            slugInput.addEventListener('input', function() {
                slugEdited = this.value.length > 0;
            });
        </script>
        HTML;

        return AdminView::render('Edit ' . $typeLabel, $contentHtml, $siteName, $username);
    }

    private function getSiteUrl(): string
    {
        return $this->optionRepo->get('siteurl', 'http://localhost:8000');
    }

    private function slugEdited(string $slug): string
    {
        return $slug ? 'true' : 'false';
    }
}

