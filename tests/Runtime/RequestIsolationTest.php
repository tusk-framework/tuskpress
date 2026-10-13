<?php

declare(strict_types=1);

namespace TuskPress\Tests\Runtime;

use PHPUnit\Framework\TestCase;
use Tusk\Core\Container\Container;
use Tusk\Security\Contract\UserInterface;
use Tusk\Security\Contract\UserProviderInterface;
use Tusk\Security\Middleware\SecurityMiddleware;
use TuskPress\Security\SessionGuard;

final class RequestIsolationTest extends TestCase
{
    public function test_session_guard_instance_is_recreated_after_request_scope_reset(): void
    {
        unset($_SESSION['tuskpress_user_id']);

        $container = new Container();
        $container->register(FakeUserProvider::class, 'singleton');
        $container->register(SessionGuard::class);

        $firstGuard = $container->get(SessionGuard::class);
        $firstGuard->setUser(new FakeUser('first-request-user'));

        $container->resetScope('request');

        $secondGuard = $container->get(SessionGuard::class);

        self::assertNotSame($firstGuard, $secondGuard);
        self::assertFalse($secondGuard->check());
    }

    public function test_security_middleware_does_not_retain_a_guard_between_requests(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $container = $application->container();
        $middlewareClass = class_exists(\TuskPress\Http\Middleware\RequestScopedSecurityMiddleware::class)
            ? \TuskPress\Http\Middleware\RequestScopedSecurityMiddleware::class
            : SecurityMiddleware::class;

        $firstMiddleware = $container->get($middlewareClass);
        $container->resetScope('request');
        $secondMiddleware = $container->get($middlewareClass);

        self::assertNotSame($firstMiddleware, $secondMiddleware);
    }

    public function test_project_does_not_own_a_runtime_worker(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__, 2).'/worker.php');
    }
}

final class FakeUserProvider implements UserProviderInterface
{
    public function loadByIdentifier(string|int $identifier): ?UserInterface
    {
        return null;
    }
}

final class FakeUser implements UserInterface
{
    public function __construct(private readonly string $identifier) {}

    public function getIdentifier(): string|int
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        return [];
    }
}
