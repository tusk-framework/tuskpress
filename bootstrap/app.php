<?php

/**
 * bootstrap/app.php — TuskPress application bootstrap
 *
 * Initializes the IoC container, registers all services and returns the HTTP Kernel.
 * In Tusk Engine mode (persistent), this file is executed ONCE.
 */

declare(strict_types=1);

// use TuskPress\Http\Middleware\AdminAuthMiddleware;
use TuskPress\Http\Middleware\ApiAuthMiddleware;
use TuskPress\Http\Middleware\CsrfMiddleware;
use TuskPress\Http\Middleware\SessionMiddleware;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;
use TuskPress\Plugin\PluginManager;
use Tusk\Core\Container\ContainerCompiler;
use Tusk\Core\Container\ServiceScanner;
use Tusk\Core\Container\CompilerPipeline;
use Tusk\Web\HttpKernel;
use Tusk\Web\Router\RouteCompiler;

// Loads Tusk native environment
require_once dirname(__DIR__) . '/vendor/autoload.php';
\Tusk\Config\Env::load(dirname(__DIR__) . '/.env');

// Legacy compatibility constants (temporary)
define('WP_DEBUG', filter_var(\Tusk\Config\Env::get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
define('WP_DEBUG_LOG', WP_DEBUG);
define('WP_DEBUG_DISPLAY', WP_DEBUG);
define('WP_HOME', \Tusk\Config\Env::get('WP_HOME', 'http://localhost:8000'));
define('WP_SITEURL', \Tusk\Config\Env::get('WP_SITEURL', 'http://localhost:8000'));
define('ABSPATH', dirname(__DIR__) . '/');
define('WP_CONTENT_DIR', \Tusk\Config\Env::get('WP_CONTENT_DIR', ABSPATH . 'public/wp-content'));
define('WP_CONTENT_URL', WP_HOME . '/wp-content');
define('WP_PLUGIN_DIR', \Tusk\Config\Env::get('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins'));
define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');
define('TEMPLATEPATH', \Tusk\Config\Env::get('TEMPLATEPATH', WP_CONTENT_DIR . '/themes'));

// =========================================================
// 1. COMPILERS AND CACHE
// =========================================================
$cacheDir = __DIR__ . '/cache';
$containerCache = $cacheDir . '/CompiledContainer.php';
$routerCache = $cacheDir . '/CompiledRouter.php';
$eventsCache = $cacheDir . '/CompiledListenerProvider.php';
$srcDir = dirname(__DIR__) . '/src';
$frameworkDir = dirname(__DIR__) . '/vendor/tusk/framework';

if (!file_exists($containerCache)) {
    $pipeline = new CompilerPipeline(new ServiceScanner(), new ContainerCompiler());
    $pipeline->compileAndDump([$srcDir, $frameworkDir], $containerCache, 'Tusk\Compiled', 'CompiledContainer');
}

if (!file_exists($routerCache)) {
    $routeCompiler = new RouteCompiler();
    $routeCompiler->compileAndDump([$srcDir], $routerCache, 'Tusk\Compiled', 'CompiledRouter');
}

if (!file_exists($eventsCache)) {
    $eventCompiler = new \Tusk\Events\Compiler\EventCompiler();
    $eventCompiler->compileAndDump([$srcDir], $eventsCache, 'Tusk\Compiled', 'CompiledListenerProvider');
}

require_once $containerCache;
require_once $routerCache;
require_once $eventsCache;

// =========================================================
// 2. OPTIMIZED INITIALIZATION
// =========================================================
class RuntimeCompiledContainer extends \Tusk\Compiled\CompiledContainer {
    private array $customInstances = [];
    
    public function instance(string $id, object $instance): void {
        $this->customInstances[$id] = $instance;
    }
    
    public function get(string $id): object {
        if (isset($this->customInstances[$id])) {
            return $this->customInstances[$id];
        }
        return parent::get($id);
    }
    
    public function has(string $id): bool {
        if (isset($this->customInstances[$id])) {
            return true;
        }
        return parent::has($id);
    }
}

$container = new RuntimeCompiledContainer();
$router = new \Tusk\Compiled\CompiledRouter();
$listenerProvider = new \Tusk\Compiled\CompiledListenerProvider($container);
$eventDispatcher = new \Tusk\Events\EventDispatcher($listenerProvider);

$container->instance(\Tusk\Contracts\Events\ListenerProviderInterface::class, $listenerProvider);
$container->instance(\Tusk\Contracts\Events\EventDispatcherInterface::class, $eventDispatcher);

// TuskPress specific aliases
$container->instance(
    \Tusk\Security\Contract\UserProviderInterface::class, 
    $container->get(\TuskPress\Infrastructure\Persistence\MySqlUserRepository::class)
);
$container->instance(
    \Tusk\Security\Contract\GuardInterface::class, 
    $container->get(\TuskPress\Security\SessionGuard::class)
);

// Tusk Cloud: Health Checks
$healthRegistry = new \Tusk\Cloud\Health\HealthCheckRegistry();
// Resolving DatabaseHealthCheck from container so it gets the EntityManager injected
try {
    $dbHealthCheck = $container->get(\Tusk\Cloud\Health\HealthCheckInterface::class);
    $healthRegistry->register($dbHealthCheck);
} catch (\Throwable $e) {}
$container->instance(\Tusk\Cloud\Health\HealthCheckRegistry::class, $healthRegistry);

// =========================================================
// 3. HTTP KERNEL — Middleware pipeline
// =========================================================
$kernel = new HttpKernel($container, $router);

// Middleware stack (order matters — executes top to bottom)
$kernel->addMiddleware(\TuskPress\Http\Middleware\ExceptionMiddleware::class); // Handles exceptions
$kernel->addMiddleware(\TuskPress\Http\Middleware\InstallCheckMiddleware::class); // Redirects to install if DB is missing
$kernel->addMiddleware(SessionMiddleware::class);    // 1. Session
$kernel->addMiddleware(CsrfMiddleware::class);       // 2. CSRF
$kernel->addMiddleware(\Tusk\Security\Middleware\SecurityMiddleware::class); // 3. Global Auth (attribute based)
$kernel->addMiddleware(ApiAuthMiddleware::class);    // 4. API Auth

// =========================================================
// 4. LOAD ACTIVE PLUGINS AND THEME
// =========================================================
$pluginManager = $container->get(PluginManager::class);
$optionRepo    = $container->get(MySqlOptionRepository::class);

try {
    $activePlugins = $optionRepo->get('active_plugins', []);
    $pluginManager->loadPlugins($activePlugins);

    $themeLoader = $container->get(\TuskPress\Theme\ThemeLoader::class);
    $themeLoader->loadActiveTheme();
} catch (\Throwable $e) {
    // DB might not be available during installation
    if (WP_DEBUG) {
        error_log('[TuskPress] Plugin/Theme loading failed: ' . $e->getMessage());
    }
}

return $kernel;
