<?php

declare(strict_types=1);

namespace TuskPress\Theme;

use Tusk\Contracts\Attributes\Service;
/**
 * ThemeLoader — Loads the current theme's functions.php (if it exists).
 */
#[Service]
class ThemeLoader
{
    public function __construct(
        private readonly \TuskPress\Plugin\HookBus $hookBus,
        private readonly \TuskPress\Infrastructure\Persistence\MySqlOptionRepository $optionRepo
    ) {}

    public function loadActiveTheme(): void
    {
        $theme = $this->optionRepo->get('template', 'twentytwentyfour');
        $themePath = WP_CONTENT_DIR . '/themes/' . $theme;

        if (file_exists($themePath . '/functions.php')) {
            // In persistent mode, functions.php must be safe to load multiple times?
            // No, ideally we load it once in the bootstrap or hook.
            // To maintain wp compatibility, we load via include_once
            include_once $themePath . '/functions.php';
        }
    }
}
