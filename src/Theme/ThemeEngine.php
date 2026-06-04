<?php

declare(strict_types=1);

namespace TuskPress\Theme;

use Tusk\Contracts\Attributes\Service;

use TuskPress\Domain\Post\Post;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;
use TuskPress\Plugin\HookBus;

/**
 * ThemeEngine — TuskPress template engine.
 *
 * Implements the WordPress template hierarchy:
 * Resolves which PHP template to use based on the request type,
 * exposes global variables for compatibility and renders the output.
 */
#[Service]
class ThemeEngine
{
    private string $themeDir;
    private string $themeName;

    public function __construct(
        private readonly MySqlOptionRepository $optionRepository,
        private readonly HookBus              $hookBus,
    ) {
        $this->themeName = $this->optionRepository->get('template', 'tusk-modern');
        $this->themeDir  = WP_CONTENT_DIR . '/themes/' . $this->themeName;

        if (file_exists($this->themeDir . '/functions.php')) {
            require_once $this->themeDir . '/functions.php';
        }
    }

    // =========================================================
    // Template Resolution (WordPress Template Hierarchy)
    // =========================================================

    /**
     * Resolves the template for a single post view.
     */
    public function resolveForSingle(Post $post): string
    {
        $type = $post->type->value;
        $slug = $post->slug;

        $candidates = [
            "single-{$type}-{$slug}.php",
            "single-{$type}.php",
            'single.php',
            'singular.php',
            'index.php',
        ];

        return $this->findTemplate($candidates);
    }

    /**
     * Resolves the template for a page.
     */
    public function resolveForPage(Post $post): string
    {
        $slug = $post->slug;

        $candidates = [
            "page-{$slug}.php",
            "page-{$post->id->value}.php",
            'page.php',
            'singular.php',
            'index.php',
        ];

        return $this->findTemplate($candidates);
    }

    /**
     * Resolves the template for a category archive.
     */
    public function resolveForCategory(string $slug = ''): string
    {
        $candidates = $slug
            ? ["category-{$slug}.php", 'category.php', 'archive.php', 'index.php']
            : ['category.php', 'archive.php', 'index.php'];

        return $this->findTemplate($candidates);
    }

    /**
     * Resolves the template for a tag archive.
     */
    public function resolveForTag(string $slug = ''): string
    {
        $candidates = $slug
            ? ["tag-{$slug}.php", 'tag.php', 'archive.php', 'index.php']
            : ['tag.php', 'archive.php', 'index.php'];

        return $this->findTemplate($candidates);
    }

    /**
     * Resolves the template for the home (post list).
     */
    public function resolveForHome(): string
    {
        return $this->findTemplate(['home.php', 'index.php']);
    }

    /**
     * Resolves the template for the front page.
     */
    public function resolveForFrontPage(): string
    {
        return $this->findTemplate(['front-page.php', 'home.php', 'index.php']);
    }

    /**
     * Resolves the search template.
     */
    public function resolveForSearch(): string
    {
        return $this->findTemplate(['search.php', 'index.php']);
    }

    /**
     * Resolves the 404 template.
     */
    public function resolveFor404(): string
    {
        return $this->findTemplate(['404.php', 'index.php']);
    }

    // =========================================================
    // Rendering
    // =========================================================

    /**
     * Renders a template with variables.
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        if (!file_exists($template)) {
            return $this->renderFallback($data);
        }

        // Passes hookBus so modern templates can use it
        $data['hookBus'] = $this->hookBus;

        // Exposes variables locally to the template
        extract($data, EXTR_SKIP);

        ob_start();
        require $template;
        return ob_get_clean() ?: '';
    }

    /**
     * Renders a partial.
     * @param array<string, mixed> $args
     */
    public function renderPart(string $slug, ?string $name = null, array $args = []): string
    {
        $candidates = $name
            ? ["{$slug}-{$name}.php", "{$slug}.php"]
            : ["{$slug}.php"];

        $template = $this->findTemplate($candidates);

        $args['hookBus'] = $this->hookBus;
        extract($args, EXTR_SKIP);

        ob_start();
        if (file_exists($template)) {
            require $template;
        }
        return ob_get_clean() ?: '';
    }

    // =========================================================
    // Theme info
    // =========================================================

    public function getThemeDir(): string
    {
        return $this->themeDir;
    }

    public function getThemeName(): string
    {
        return $this->themeName;
    }

    public function getThemeUri(): string
    {
        return WP_CONTENT_URL . '/themes/' . $this->themeName;
    }

    public function getThemeData(): array
    {
        $styleFile = $this->themeDir . '/style.css';
        if (!file_exists($styleFile)) {
            return ['Name' => $this->themeName];
        }

        $content = file_get_contents($styleFile, false, null, 0, 4096);
        $headers = [
            'Name'        => 'Theme Name',
            'Version'     => 'Version',
            'Description' => 'Description',
            'Author'      => 'Author',
            'AuthorURI'   => 'Author URI',
            'ThemeURI'    => 'Theme URI',
        ];

        $data = [];
        foreach ($headers as $field => $regex) {
            preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':\s*(.+)$/mi', $content, $match);
            $data[$field] = isset($match[1]) ? trim($match[1]) : '';
        }

        return $data;
    }

    // =========================================================
    // Private helpers
    // =========================================================

    private function findTemplate(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $path = $this->themeDir . '/' . $candidate;
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback: theme index.php
        $indexPath = $this->themeDir . '/index.php';
        if (file_exists($indexPath)) {
            return $indexPath;
        }

        // Final fallback: TuskPress internal template
        return dirname(__DIR__, 2) . '/templates/fallback/index.php';
    }

    private function renderFallback(array $data = []): string
    {
        return '<!DOCTYPE html><html><head><title>TuskPress</title></head><body>
            <h1>TuskPress</h1>
            <p>Configure a theme in <a href="/wp-admin/themes.php">Appearance → Themes</a>.</p>
        </body></html>';
    }
}
