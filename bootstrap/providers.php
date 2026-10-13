<?php

declare(strict_types=1);

use Tusk\Cloud\Health\HealthCheckRegistry;
use Tusk\Cloud\Health\HealthCheckInterface;
use Tusk\Contracts\Events\EventDispatcherInterface;
use Tusk\Contracts\Events\ListenerProviderInterface;
use Tusk\Core\Container\Container;
use Tusk\Core\Container\ServiceScanner;
use Tusk\Events\EventDispatcher;
use Tusk\Events\Compiler\EventCompiler;
use Tusk\Events\ListenerProvider;
use Tusk\Web\HttpKernel;
use TuskPress\Http\Middleware\ApiAuthMiddleware;
use TuskPress\Http\Middleware\CsrfMiddleware;
use TuskPress\Http\Middleware\ExceptionMiddleware;
use TuskPress\Http\Middleware\InstallCheckMiddleware;
use TuskPress\Http\Middleware\SessionMiddleware;
use TuskPress\Http\Middleware\RequestScopedSecurityMiddleware;
use TuskPress\Infrastructure\Database\EntityManagerFactory;
use TuskPress\Infrastructure\Health\DatabaseHealthCheck;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;

return static function (Container $container): void {
    $source = dirname(__DIR__).'/src';
    foreach ((new ServiceScanner())->scan([$source]) as $definition) {
        $container->register($definition['class'], $definition['scope']);
    }
    $container->register(RequestScopedSecurityMiddleware::class, 'request');
    $container->register(\Tusk\Security\Authorization\Gate::class, 'request');

    $container->register(EntityManagerFactory::class, 'singleton');
    $listenerMap = (new EventCompiler())->scan([$source.'/Listeners']);
    $container->instance(ListenerProviderInterface::class, new ListenerProvider($container, $listenerMap));
    $container->instance(EventDispatcherInterface::class, new EventDispatcher($container->get(ListenerProviderInterface::class)));

    $container->register(DatabaseHealthCheck::class, 'singleton');
    $healthCheck = $container->get(DatabaseHealthCheck::class);
    $container->instance(HealthCheckInterface::class, $healthCheck);
    $container->get(HealthCheckRegistry::class)->register($healthCheck);

    $kernel = $container->get(HttpKernel::class);
    $kernel->addMiddleware(ExceptionMiddleware::class);
    $kernel->addMiddleware(InstallCheckMiddleware::class);
    $kernel->addMiddleware(SessionMiddleware::class);
    $kernel->addMiddleware(CsrfMiddleware::class);
    $kernel->addMiddleware(RequestScopedSecurityMiddleware::class);
    $kernel->addMiddleware(ApiAuthMiddleware::class);

    $optionRepository = $container->get(MySqlOptionRepository::class);
    try {
        $activePlugins = $optionRepository->get('active_plugins', []);
    } catch (\Doctrine\DBAL\Exception|\PDOException $exception) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TuskPress] Unable to load active plugins before installation: '.$exception->getMessage());
        }
        $activePlugins = [];
    }

    $container->get(\TuskPress\Plugin\PluginManager::class)->loadPlugins($activePlugins);
    try {
        $activeTheme = $optionRepository->get('template', 'twentytwentyfour');
    } catch (\Doctrine\DBAL\Exception|\PDOException $exception) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TuskPress] Unable to load active theme before installation: '.$exception->getMessage());
        }
        $activeTheme = 'twentytwentyfour';
    }
    $container->get(\TuskPress\Theme\ThemeLoader::class)->loadTheme($activeTheme);
};
