<?php

declare(strict_types=1);

require getcwd().'/vendor/autoload.php';

use Tusk\Events\EventDispatcher;
use Tusk\Events\ListenerProvider;
use Tusk\Core\Container\Container;
use Tusk\Web\Http\Request;
use TuskPress\Http\Controllers\Admin\InstallController;
use TuskPress\Infrastructure\Database\EntityManagerFactory;
use TuskPress\Infrastructure\Database\InstallationLock;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;

$first = (new EntityManagerFactory())();
$second = (new EntityManagerFactory())();
$lock = new InstallationLock($first->getConnection());
try {
    if (!$lock->acquire()) {
        throw new RuntimeException('Cannot acquire smoke installation lock.');
    }
    $before = $second->getConnection()->createSchemaManager()->listTableNames();
    $controller = new InstallController($second, new MySqlOptionRepository($second), new EventDispatcher(new ListenerProvider(new Container())));
    $response = $controller->process(new Request(new Nyholm\Psr7\ServerRequest('POST', '/wp-admin/install.php')));
    if ($response->getStatusCode() !== 409 || $before !== $second->getConnection()->createSchemaManager()->listTableNames()) {
        throw new RuntimeException('Concurrent MySQL installer was not rejected before schema changes.');
    }
    echo "MySQL installation lock: competing connection rejected without schema changes.\n";
} finally {
    $lock->release();
    $first->getConnection()->close();
    $second->getConnection()->close();
}
