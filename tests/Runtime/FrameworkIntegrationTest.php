<?php

declare(strict_types=1);

namespace TuskPress\Tests\Runtime;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tusk\Contracts\Events\EventDispatcherInterface;
use Tusk\Web\Http\Response;
use TuskPress\Domain\User\Event\UserRegisteredEvent;
use TuskPress\Domain\User\User;
use TuskPress\Domain\User\UserId;
use TuskPress\Http\Middleware\ApiAuthMiddleware;
use TuskPress\Infrastructure\Database\EntityManagerFactory;
use TuskPress\Infrastructure\Health\DatabaseHealthCheck;
use TuskPress\Infrastructure\Persistence\MySqlUserRepository;

final class FrameworkIntegrationTest extends TestCase
{
    public function test_api_index_preserves_pagination_headers_through_framework_response_api(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $manager = $this->manager();
        (new \Doctrine\ORM\Tools\SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $controller = new \TuskPress\Http\Controllers\Api\ApiPostController(
            new \TuskPress\Infrastructure\Persistence\MySqlPostRepository($manager, $application->container()->get(EventDispatcherInterface::class)),
            new \TuskPress\Infrastructure\Persistence\MySqlOptionRepository($manager),
        );
        $response = $controller->index(new \Tusk\Web\Http\Request(new ServerRequest('GET', '/wp-json/wp/v2/posts')));
        self::assertSame('0', $response->getHeaderLine('X-WP-Total'));
        self::assertSame('0', $response->getHeaderLine('X-WP-TotalPages'));
        self::assertSame([], json_decode((string) $response->getBody(), true));
    }

    public function test_feed_preserves_xml_content_type_through_framework_response_api(): void
    {
        $response = (new \TuskPress\Http\Controllers\Frontend\FeedController())->rss(new \Tusk\Web\Http\Request(new ServerRequest('GET', '/feed')));
        self::assertSame('application/rss+xml', $response->getHeaderLine('Content-Type'));
    }

    public function test_database_health_uses_public_dbal_api_and_releases_connection(): void
    {
        $manager = $this->manager();
        $factory = new class($manager) extends EntityManagerFactory {
            public function __construct(private EntityManagerInterface $manager) {}
            public function __invoke(): EntityManagerInterface { return $this->manager; }
        };
        self::assertTrue((new DatabaseHealthCheck($factory))->check());
        self::assertFalse($manager->getConnection()->isConnected());
    }

    public function test_anonymous_psr_api_post_is_rejected_before_handler(): void
    {
        $middleware = new ApiAuthMiddleware(new MySqlUserRepository($this->manager()));
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return Response::html('handler reached');
            }
        };
        $response = $middleware->process(new ServerRequest('POST', '/wp-json/wp/v2/posts'), $handler);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('rest_unauthorized', json_decode((string) $response->getBody(), true)['code']);
    }

    public function test_registered_user_event_invokes_existing_listener_without_absent_getters(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $login = 'registered-'.bin2hex(random_bytes(8));
        $user = new User(id: new UserId(1), login: $login, email: 'listener@example.test');
        $log = sys_get_temp_dir().'/tuskpress_audit.log';
        $offset = is_file($log) ? filesize($log) : 0;
        $application->container()->get(EventDispatcherInterface::class)->dispatch(new UserRegisteredEvent($user));
        self::assertStringContainsString("User Registered: {$login} (Email: listener@example.test)", file_get_contents($log, false, null, $offset));
    }

    private function manager(): EntityManagerInterface
    {
        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Domain'], true));
    }
}
