<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Frontend;

use Tusk\Contracts\Attributes\Service;

use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
use TuskPress\Domain\Post\PostQuery;
use TuskPress\Domain\Post\PostStatus;
use TuskPress\Domain\Post\PostType;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;
use TuskPress\Infrastructure\Persistence\MySqlPostRepository;
use TuskPress\Theme\ThemeEngine;

/**
 * FrontendController — Controls all public routes of the site.
 *
 * Implements WordPress URL hierarchy routing:
 * /, /{slug}/, /category/{slug}/, /tag/{slug}/, /?s=search, /feed/
 */
#[Service(scope: 'request')]
#[Controller]
class FrontendController
{
    public function __construct(
        private readonly MySqlPostRepository   $postRepository,
        private readonly MySqlOptionRepository $optionRepository,
        private readonly ThemeEngine           $themeEngine,
        private readonly \TuskPress\Plugin\HookBus $hookBus,
    ) {}

    // =========================================================
    // Home / Blog
    // =========================================================

    #[Route('/', methods: ['GET'])]
    public function home(Request $request): Response
    {
        $showOnFront  = $this->optionRepository->get('show_on_front', 'posts');
        $pageOnFront  = (int) $this->optionRepository->get('page_on_front', 0);
        $pageForPosts = (int) $this->optionRepository->get('page_for_posts', 0);

        if ($showOnFront === 'page' && $pageOnFront > 0) {
            return $this->renderStaticFrontPage($request, $pageOnFront);
        }

        $perPage = (int) $this->optionRepository->get('posts_per_page', 10);
        $page    = max(1, (int) ($request->query['paged'] ?? 1));

        $query = PostQuery::forFrontend($perPage, $page);
        $posts = $this->postRepository->findAll($query);
        $total = $this->postRepository->count($query);

        $data = [
            'posts'       => $posts,
            'total_posts' => $total,
            'per_page'    => $perPage,
            'paged'       => $page,
            'max_pages'   => ceil($total / $perPage),
            'is_home'     => true,
            'is_front_page' => true,
        ];

        $this->hookBus->dispatch('frontend.dispatch');
        $template = $this->themeEngine->resolveForFrontPage();
        $content  = $this->themeEngine->render($template, $data);

        return Response::html($content);
    }

    // =========================================================
    // Single post / page / custom post type
    // =========================================================

    #[Route('/{slug}', methods: ['GET'])]
    public function single(string $slug, Request $request): Response
    {
        $post = $this->postRepository->findBySlug($slug, PostType::Page);

        if (!$post) {
            $post = $this->postRepository->findBySlug($slug, PostType::Post);
        }

        if (!$post || !$post->isPublished()) {
            return $this->notFound($request);
        }

        $data = ['post' => $post, 'is_singular' => true];

        if ($post->isPage()) {
            $data['is_page'] = true;
            $template = $this->themeEngine->resolveForPage($post);
        } else {
            $data['is_single'] = true;
            $template = $this->themeEngine->resolveForSingle($post);
        }

        $this->hookBus->dispatch('frontend.dispatch');
        $content = $this->themeEngine->render($template, $data);

        return Response::html($content);
    }

    // =========================================================
    // Category archive
    // =========================================================

    #[Route('/category/{slug}', methods: ['GET'])]
    public function category(string $slug, Request $request): Response
    {
        $perPage = (int) $this->optionRepository->get('posts_per_page', 10);
        $page    = max(1, (int) ($request->query['paged'] ?? 1));

        $query = PostQuery::forFrontend($perPage, $page);
        $posts = $this->postRepository->findAll($query);

        $data = [
            'posts'         => $posts,
            'category_slug' => $slug,
            'is_archive'    => true,
            'is_category'   => true,
        ];

        $this->hookBus->dispatch('frontend.dispatch');
        $template = $this->themeEngine->resolveForCategory($slug);
        $content  = $this->themeEngine->render($template, $data);

        return Response::html($content);
    }

    // =========================================================
    // Tag archive
    // =========================================================

    #[Route('/tag/{slug}', methods: ['GET'])]
    public function tag(string $slug, Request $request): Response
    {
        $perPage = (int) $this->optionRepository->get('posts_per_page', 10);
        $page    = max(1, (int) ($request->query['paged'] ?? 1));

        $query = PostQuery::forFrontend($perPage, $page);
        $posts = $this->postRepository->findAll($query);

        $data = [
            'posts'      => $posts,
            'tag_slug'   => $slug,
            'is_archive' => true,
            'is_tag'     => true,
        ];

        $this->hookBus->dispatch('frontend.dispatch');
        $template = $this->themeEngine->resolveForTag($slug);
        $content  = $this->themeEngine->render($template, $data);

        return Response::html($content);
    }

    // =========================================================
    // Search
    // =========================================================

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $searchTerm = trim($request->query['s'] ?? '');
        $perPage    = (int) $this->optionRepository->get('posts_per_page', 10);
        $page       = max(1, (int) ($request->query['paged'] ?? 1));

        $query = new PostQuery(
            type:    PostType::Post,
            status:  PostStatus::Publish,
            perPage: $perPage,
            page:    $page,
            search:  $searchTerm,
        );

        $posts = $searchTerm ? $this->postRepository->findAll($query) : [];
        $total = $searchTerm ? $this->postRepository->count($query) : 0;

        $data = [
            'posts'       => $posts,
            'search_term' => $searchTerm,
            's'           => $searchTerm,
            'total_posts' => $total,
            'is_search'   => true,
        ];

        $this->hookBus->dispatch('frontend.dispatch');
        $template = $this->themeEngine->resolveForSearch();
        $content  = $this->themeEngine->render($template, $data);

        return Response::html($content);
    }

    // =========================================================
    // Private helpers
    // =========================================================

    private function renderStaticFrontPage(Request $request, int $pageId): Response
    {
        $post = $this->postRepository->findById(new \TuskPress\Domain\Post\PostId($pageId));

        if (!$post || !$post->isPublished()) {
            return $this->notFound($request);
        }

        $data = [
            'post' => $post,
            'is_front_page' => true,
            'is_page'       => true,
        ];

        $this->hookBus->dispatch('frontend.dispatch');
        $template = $this->themeEngine->resolveForFrontPage();
        $content  = $this->themeEngine->render($template, $data);

        return Response::html($content);
    }

    private function notFound(Request $request): Response
    {
        $this->hookBus->dispatch('frontend.dispatch');
        $template = $this->themeEngine->resolveFor404();
        $content  = $this->themeEngine->render($template, ['is_404' => true]);

        return Response::html($content, 404);
    }
}
