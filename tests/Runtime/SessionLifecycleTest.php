<?php

declare(strict_types=1);

namespace TuskPress\Tests\Runtime;

use Closure;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tusk\Core\Container\Container;
use Tusk\Runtime\LifecycleManager;
use Tusk\Security\Contract\UserInterface;
use Tusk\Security\Contract\UserProviderInterface;
use Tusk\Web\Http\Response;
use TuskPress\Http\Middleware\SessionMiddleware;
use TuskPress\Security\SessionGuard;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SessionLifecycleTest extends TestCase
{
    private string $sessionDirectory;
    private Container $container;
    private LifecycleManager $lifecycle;

    protected function setUp(): void
    {
        $this->sessionDirectory = sys_get_temp_dir().'/tuskpress-session-'.bin2hex(random_bytes(8));
        mkdir($this->sessionDirectory);
        ini_set('session.save_path', $this->sessionDirectory);
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');
        $this->container = new Container();
        $this->container->instance(UserProviderInterface::class, new SessionTestUserProvider());
        $this->container->register(SessionGuard::class);
        $this->lifecycle = new LifecycleManager($this->container);
        $this->lifecycle->applicationStart();
        $this->lifecycle->workerStart();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');
        $_SESSION = [];
        foreach (glob($this->sessionDirectory.'/sess_*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->sessionDirectory);
        $this->lifecycle->workerStop();
        $this->lifecycle->applicationStop();
    }

    public function test_authenticated_then_cookie_less_request_has_fresh_anonymous_identity(): void
    {
        $authenticated = $this->request([], function (): ResponseInterface {
            $this->container->get(SessionGuard::class)->login(new SessionTestUser(1));
            return Response::html('logged in');
        });
        $firstCookie = $authenticated->getHeaderLine('Set-Cookie');

        $anonymous = $this->request([], function (): ResponseInterface {
            $guard = $this->container->get(SessionGuard::class);
            self::assertFalse($guard->check(), 'Cookie-less request inherited the previous user.');
            self::assertArrayNotHasKey('tuskpress_user_id', $_SESSION);
            return Response::html('anonymous');
        });

        self::assertNotSame($firstCookie, $anonymous->getHeaderLine('Set-Cookie'));
        self::assertSame('', session_id());
        self::assertSame([], $_SESSION);
    }

    public function test_user_a_then_user_b_resume_only_their_own_cookie_identity(): void
    {
        $a = $this->request([], function (): ResponseInterface {
            $this->container->get(SessionGuard::class)->login(new SessionTestUser(1));
            return Response::html('A');
        });
        $b = $this->request([], function (): ResponseInterface {
            $this->container->get(SessionGuard::class)->login(new SessionTestUser(2));
            return Response::html('B');
        });
        $aId = $this->cookieId($a);
        $bId = $this->cookieId($b);
        self::assertNotSame($aId, $bId, 'Different clients shared a PHP session.');
        foreach ([$aId => 1, $bId => 2, $aId.'-invalid' => null] as $id => $expected) {
            $this->request(['tuskpress_session' => $id], function () use ($expected): ResponseInterface {
                self::assertSame($expected, $this->container->get(SessionGuard::class)->user()?->getIdentifier());
                return Response::html('checked');
            });
        }
        self::assertSame('', session_id());
    }

    public function test_handler_exception_closes_and_resets_session_before_next_request(): void
    {
        try {
            $this->request([], function (): ResponseInterface {
                $this->container->get(SessionGuard::class)->login(new SessionTestUser(1));
                throw new \RuntimeException('handler failed');
            });
            self::fail('Expected handler exception.');
        } catch (\RuntimeException $exception) {
            self::assertSame('handler failed', $exception->getMessage());
        }
        self::assertSame(PHP_SESSION_NONE, session_status(), 'Exception left a session open.');
        self::assertSame('', session_id());
        self::assertSame([], $_SESSION);
        $this->request([], function (): ResponseInterface {
            self::assertFalse($this->container->get(SessionGuard::class)->check());
            return Response::html('anonymous');
        });
    }

    private function request(array $cookies, Closure $callback): ResponseInterface
    {
        $handler = new class($callback) implements RequestHandlerInterface {
            public function __construct(private Closure $callback) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->callback)();
            }
        };
        $process = $this->lifecycle->wrap(fn ($request) => (new SessionMiddleware())->process($request, $handler));
        return $process((new ServerRequest('GET', '/'))->withCookieParams($cookies));
    }

    private function cookieId(ResponseInterface $response): string
    {
        preg_match('/tuskpress_session=([^;]+)/', $response->getHeaderLine('Set-Cookie'), $matches);
        self::assertArrayHasKey(1, $matches);
        return $matches[1];
    }
}

final class SessionTestUserProvider implements UserProviderInterface
{
    public function loadByIdentifier(string|int $identifier): ?UserInterface
    {
        return new SessionTestUser((int) $identifier);
    }
}

final class SessionTestUser implements UserInterface
{
    public function __construct(private int $id) {}
    public function getIdentifier(): string|int { return $this->id; }
    public function getRoles(): array { return []; }
}
