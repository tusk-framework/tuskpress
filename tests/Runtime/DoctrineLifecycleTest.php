<?php

declare(strict_types=1);

namespace TuskPress\Tests\Runtime;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Tusk\Core\Container\Container;
use Tusk\Runtime\LifecycleManager;
use TuskPress\Domain\Option\Option;
use TuskPress\Infrastructure\Database\EntityManagerFactory;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;

final class DoctrineLifecycleTest extends TestCase
{
    private Container $container;
    private LifecycleManager $lifecycle;
    private string $database;

    protected function setUp(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $this->container = $application->container();
        $this->database = tempnam(sys_get_temp_dir(), 'tuskpress-orm-');
        $factory = new SqliteEntityManagerFactory($this->database);
        $manager = $factory();
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(Option::class)]);
        $manager->getConnection()->close();
        $this->container->instance(EntityManagerFactory::class, $factory);
        // Replace bootstrap's manager/repository with a real SQLite fixture.
        $this->container->instance(EntityManagerInterface::class, $manager);
        $this->container->instance(MySqlOptionRepository::class, new MySqlOptionRepository($manager));
        $this->container->resetScope('request');
        $this->lifecycle = new LifecycleManager($this->container);
        $this->lifecycle->applicationStart();
        $this->lifecycle->workerStart();
    }

    protected function tearDown(): void
    {
        $this->container->get(EntityManagerInterface::class)->getConnection()->close();
        $this->lifecycle->workerStop();
        $this->lifecycle->applicationStop();
        unlink($this->database);
    }

    public function test_managed_entity_is_detached_and_database_changes_are_visible_next_request(): void
    {
        $entity = null;
        $manager = null;
        ($this->lifecycle->wrap(function () use (&$entity, &$manager): void {
            $manager = $this->container->get(EntityManagerInterface::class);
            $entity = new Option('blogname', 'first');
            $manager->persist($entity);
            $manager->flush();
            self::assertTrue($manager->contains($entity));
        }))();
        self::assertFalse($manager->contains($entity), 'Identity map survived the request boundary.');
        ($this->lifecycle->wrap(function () use ($entity): void {
            $next = $this->container->get(EntityManagerInterface::class);
            $next->getConnection()->executeStatement("UPDATE options SET option_value = 'external' WHERE option_name = 'blogname'");
            $loaded = $next->find(Option::class, $entity->getId());
            self::assertNotSame($entity, $loaded);
            self::assertSame('external', $loaded->getValue());
        }))();
    }

    public function test_option_cache_is_invalidated_between_requests(): void
    {
        ($this->lifecycle->wrap(function (): void {
            $repository = $this->container->get(MySqlOptionRepository::class);
            $repository->set('blogname', 'first');
            self::assertSame('first', $repository->get('blogname'));
            $this->container->get(EntityManagerInterface::class)->getConnection()->executeStatement("UPDATE options SET option_value = 'external' WHERE option_name = 'blogname'");
        }))();
        ($this->lifecycle->wrap(function (): void {
            self::assertSame('external', $this->container->get(MySqlOptionRepository::class)->get('blogname'));
        }))();
    }

    public function test_closed_manager_after_failed_request_is_replaced_for_repository_writes(): void
    {
        try {
            ($this->lifecycle->wrap(function (): void {
                $this->container->get(EntityManagerInterface::class)->close();
                throw new \RuntimeException('database operation failed');
            }))();
            self::fail('Expected request failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('database operation failed', $exception->getMessage());
        }
        ($this->lifecycle->wrap(function (): void {
            self::assertTrue($this->container->get(EntityManagerInterface::class)->isOpen(), 'Next request retained a closed manager.');
            $repository = $this->container->get(MySqlOptionRepository::class);
            $repository->set('blogname', 'recovered');
            self::assertSame('recovered', $repository->get('blogname'));
        }))();
    }
}

final class SqliteEntityManagerFactory extends EntityManagerFactory
{
    public function __construct(private string $path) {}
    public function __invoke(): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Domain'], true);
        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->path]), $config);
    }
}
