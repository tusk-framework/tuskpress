<?php

declare(strict_types=1);

namespace TuskPress\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tusk\Foundation\Application;
use Tusk\Web\Router\RouterInterface;

final class ApplicationBootstrapTest extends TestCase
{
    public function test_bootstrap_returns_a_framework_application(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        self::assertInstanceOf(Application::class, $application);
    }

    public function test_health_route_is_available_without_database_tables(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $response = $application->handle(new \Nyholm\Psr7\ServerRequest('GET', '/healthz'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', (string) $response->getBody());
    }

    public function test_existing_admin_install_route_is_registered(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $router = $application->container()->get(RouterInterface::class);
        self::assertNotNull($router->match('GET', '/wp-admin/install.php'));

        $response = $application->handle(new \Nyholm\Psr7\ServerRequest('GET', '/wp-admin/install.php'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('TuskPress Installation', (string) $response->getBody());
    }

    public function test_legacy_admin_trailing_slash_redirects_to_canonical_route(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $response = $application->handle(new \Nyholm\Psr7\ServerRequest('GET', '/wp-admin/'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/wp-admin', $response->getHeaderLine('Location'));
    }

    public function test_entity_manager_factory_does_not_connect_during_bootstrap(): void
    {
        $entityManager = (new \TuskPress\Infrastructure\Database\EntityManagerFactory())();
        $connection = $entityManager->getConnection();

        self::assertInstanceOf(\Doctrine\DBAL\Connection::class, $connection);
        self::assertFalse($connection->isConnected());
    }

    public function test_representative_frontend_admin_and_api_routes_are_registered(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';

        foreach ([
            ['GET', '/'],
            ['GET', '/wp-admin'],
            ['GET', '/wp-json/wp/v2/posts'],
        ] as [$method, $path]) {
            self::assertNotNull(
                $application->container()->get(RouterInterface::class)->match($method, $path),
                "{$method} {$path} should match a registered route.",
            );
        }
    }

    public function test_provider_registers_framework_middleware_and_core_services(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $container = $application->container();

        foreach ([
            \Tusk\Contracts\Events\EventDispatcherInterface::class,
            \Tusk\Contracts\Events\ListenerProviderInterface::class,
            \Tusk\Security\Contract\UserProviderInterface::class,
            \Tusk\Cloud\Health\HealthCheckRegistry::class,
            \Tusk\Web\HttpKernel::class,
            \TuskPress\Plugin\PluginManager::class,
            \TuskPress\Theme\ThemeLoader::class,
        ] as $service) {
            self::assertTrue($container->has($service), "{$service} should be registered.");
        }
    }

    public function test_duplicate_routes_are_rejected_during_application_creation(): void
    {
        $root = dirname(__DIR__, 2);
        $source = <<<'PHP'
<?php
namespace TuskPress\Tests\Architecture\Fixtures;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Attribute\Route;
#[Controller]
final class DuplicateRouteController
{
    #[Route('/collision')]
    public function first(): string { return 'first'; }
    #[Route('/collision')]
    public function second(): string { return 'second'; }
}
PHP;

        eval(substr($source, 5));
        $fixtureDirectory = $root.DIRECTORY_SEPARATOR.'.test-fixtures'.DIRECTORY_SEPARATOR.'duplicate-route-'.bin2hex(random_bytes(8));
        $fixtureCreated = false;
        $classFile = $fixtureDirectory.DIRECTORY_SEPARATOR.'DuplicateRouteController.php';
        try {
            self::assertTrue(mkdir($fixtureDirectory, 0777, true), 'Expected unique fixture directory to be created.');
            $fixtureCreated = true;
            self::assertSame(strlen($source), file_put_contents($classFile, $source), 'Expected complete fixture source to be written.');

            $builder = Application::configure($root)
                ->withControllers('src/Http/Controllers', '.test-fixtures/'.basename($fixtureDirectory));

            try {
                $builder->create();
                self::fail('Expected duplicate route to fail during application creation.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Duplicate discovered controller route', $exception->getMessage());
            }
        } finally {
            if (is_file($classFile)) {
                unlink($classFile);
            }
            if ($fixtureCreated && is_dir($fixtureDirectory)) {
                rmdir($fixtureDirectory);
            }
            $parentDirectory = dirname($fixtureDirectory);
            if ($fixtureCreated && is_dir($parentDirectory) && count(scandir($parentDirectory) ?: []) === 2) {
                rmdir($parentDirectory);
            }
        }
    }
}
