<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Tusk\Cloud\Health\HealthCheckRegistry;
use Tusk\Cloud\Health\HealthCheckInterface;
use Tusk\Contracts\Events\EventDispatcherInterface;
use Tusk\Contracts\Events\ListenerProviderInterface;
use Tusk\Core\Container\Container;
use Tusk\Core\Container\ServiceScanner;
use Tusk\Events\EventDispatcher;
use Tusk\Events\ListenerProvider;
use Tusk\Security\Contract\UserProviderInterface;
use Tusk\Web\HttpKernel;
use TuskPress\Http\Middleware\ApiAuthMiddleware;
use TuskPress\Http\Middleware\CsrfMiddleware;
use TuskPress\Http\Middleware\ExceptionMiddleware;
use TuskPress\Http\Middleware\InstallCheckMiddleware;
use TuskPress\Http\Middleware\SessionMiddleware;
use TuskPress\Infrastructure\Database\EntityManagerFactory;
use TuskPress\Infrastructure\Health\DatabaseHealthCheck;
use TuskPress\Infrastructure\Persistence\MySqlUserRepository;
use TuskPress\Security\SessionGuard;

return static function (Container $container): void {
    $source = dirname(__DIR__).'/src';
    foreach ((new ServiceScanner())->scan([$source]) as $definition) {
        $container->register($definition['class'], $definition['scope']);
    }
    $container->register(\Tusk\Security\Middleware\SecurityMiddleware::class, 'singleton');
    $container->register(\Tusk\Security\Authorization\Gate::class, 'singleton');

    $container->register(EntityManagerFactory::class, 'singleton');
    $entityManager = $container->get(EntityManagerFactory::class)();
    $container->instance(EntityManagerInterface::class, $entityManager);
    $container->instance(ListenerProviderInterface::class, new ListenerProvider($container));
    $container->instance(EventDispatcherInterface::class, new EventDispatcher($container->get(ListenerProviderInterface::class)));
    $container->instance(UserProviderInterface::class, $container->get(MySqlUserRepository::class));
    $container->instance(\Tusk\Security\Contract\GuardInterface::class, $container->get(SessionGuard::class));

    $container->register(DatabaseHealthCheck::class, 'singleton');
    $healthCheck = $container->get(DatabaseHealthCheck::class);
    $container->instance(HealthCheckInterface::class, $healthCheck);
    $container->get(HealthCheckRegistry::class)->register($healthCheck);

    $kernel = $container->get(HttpKernel::class);
    $kernel->addMiddleware(ExceptionMiddleware::class);
    $kernel->addMiddleware(InstallCheckMiddleware::class);
    $kernel->addMiddleware(SessionMiddleware::class);
    $kernel->addMiddleware(CsrfMiddleware::class);
    $kernel->addMiddleware(\Tusk\Security\Middleware\SecurityMiddleware::class);
    $kernel->addMiddleware(ApiAuthMiddleware::class);

    try {
        $optionRepository = $container->get(\TuskPress\Infrastructure\Persistence\MySqlOptionRepository::class);
        $container->get(\TuskPress\Plugin\PluginManager::class)->loadPlugins($optionRepository->get('active_plugins', []));
        $container->get(\TuskPress\Theme\ThemeLoader::class)->loadActiveTheme();
    } catch (\Throwable $exception) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TuskPress] Plugin/Theme loading failed: '.$exception->getMessage());
        }
    }
};
