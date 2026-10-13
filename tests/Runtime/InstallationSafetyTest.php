<?php

declare(strict_types=1);

namespace TuskPress\Tests\Runtime;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Tusk\Contracts\Events\EventDispatcherInterface;
use Tusk\Web\Http\Request;
use TuskPress\Http\Controllers\Admin\InstallController;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;

final class InstallationSafetyTest extends TestCase
{
    public function test_first_run_still_installs_and_creates_a_working_administrator(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Domain'], true);
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        $options = new MySqlOptionRepository($em);
        $controller = new InstallController($em, $options, $application->container()->get(EventDispatcherInterface::class));
        $request = new Request((new \Nyholm\Psr7\ServerRequest('POST', '/wp-admin/install.php'))->withParsedBody([
            'site_title' => 'First Site', 'admin_user' => 'owner',
            'admin_pass' => 'first-run-password', 'admin_email' => 'owner@example.test',
        ]));
        self::assertSame(302, $controller->process($request)->getStatusCode());
        $em->clear();
        self::assertSame('First Site', (new MySqlOptionRepository($em))->get('blogname'));
        $user = $em->getRepository(\TuskPress\Domain\User\User::class)->findOneBy(['login' => 'owner']);
        self::assertNotNull($user);
        self::assertTrue($user->verifyPassword('first-run-password'));
        self::assertSame(409, $controller->process($request)->getStatusCode());
        self::assertSame(1, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM wp_users'));
    }

    public function test_contending_install_attempt_is_rejected_before_schema_creation(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $database = tempnam(sys_get_temp_dir(), 'tuskpress-install-');
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Domain'], true);
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $database]), $config);
        $lock = fopen(sys_get_temp_dir().'/tuskpress-install-'.hash('sha256', $database).'.lock', 'c');
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        try {
            $controller = new InstallController($em, new MySqlOptionRepository($em), $application->container()->get(EventDispatcherInterface::class));
            $response = $controller->process(new Request(new \Nyholm\Psr7\ServerRequest('POST', '/wp-admin/install.php')));
            self::assertSame(409, $response->getStatusCode(), 'Installer ignored another active installation.');
            self::assertSame([], $em->getConnection()->createSchemaManager()->listTableNames());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            $em->getConnection()->close();
            unlink($database);
        }
    }

    public function test_second_unauthenticated_install_post_cannot_mutate_existing_site(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Domain'], true);
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $options = new MySqlOptionRepository($em);
        $options->set('siteurl', 'https://original.example');
        $options->set('blogname', 'Original Site');
        $options->set('admin_email', 'owner@example.test');
        $controller = new InstallController($em, $options, $application->container()->get(EventDispatcherInterface::class));
        $request = new Request((new \Nyholm\Psr7\ServerRequest('POST', '/wp-admin/install.php'))->withParsedBody([
            'site_title' => 'Attacker Site', 'admin_user' => 'attacker',
            'admin_pass' => 'changed-password', 'admin_email' => 'attacker@example.test',
        ]));
        $schemaBefore = $em->getConnection()->createSchemaManager()->introspectSchema();

        $response = $controller->process($request);

        self::assertSame(409, $response->getStatusCode(), 'Installed site accepted a second installer POST.');
        $em->clear();
        $freshOptions = new MySqlOptionRepository($em);
        self::assertSame('Original Site', $freshOptions->get('blogname'));
        self::assertSame('owner@example.test', $freshOptions->get('admin_email'));
        self::assertSame('https://original.example', $freshOptions->get('siteurl'));
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM wp_users'));
        $schemaAfter = $em->getConnection()->createSchemaManager()->introspectSchema();
        self::assertTrue($em->getConnection()->createSchemaManager()->createComparator()->compareSchemas($schemaBefore, $schemaAfter)->isEmpty());
    }
}
