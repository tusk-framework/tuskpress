<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';
\Tusk\Config\Env::load(dirname(__DIR__).'/.env');

// Legacy compatibility constants (temporary).
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', filter_var(\Tusk\Config\Env::get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', WP_DEBUG);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', WP_DEBUG);
}
if (!defined('WP_HOME')) {
    define('WP_HOME', \Tusk\Config\Env::get('WP_HOME', 'http://localhost:8000'));
}
if (!defined('WP_SITEURL')) {
    define('WP_SITEURL', \Tusk\Config\Env::get('WP_SITEURL', 'http://localhost:8000'));
}
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__).'/');
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', \Tusk\Config\Env::get('WP_CONTENT_DIR', ABSPATH.'public/wp-content'));
}
if (!defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', WP_HOME.'/wp-content');
}
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', \Tusk\Config\Env::get('WP_PLUGIN_DIR', WP_CONTENT_DIR.'/plugins'));
}
if (!defined('WP_PLUGIN_URL')) {
    define('WP_PLUGIN_URL', WP_CONTENT_URL.'/plugins');
}
if (!defined('TEMPLATEPATH')) {
    define('TEMPLATEPATH', \Tusk\Config\Env::get('TEMPLATEPATH', WP_CONTENT_DIR.'/themes'));
}

return \Tusk\Foundation\Application::configure(dirname(__DIR__))
    ->withControllers('src/Http/Controllers')
    ->withProviders(['bootstrap/providers.php'])
    ->create();
