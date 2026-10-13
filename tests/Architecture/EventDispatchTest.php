<?php

declare(strict_types=1);

namespace TuskPress\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tusk\Contracts\Events\EventDispatcherInterface;
use TuskPress\Domain\Post\Event\PostSavedEvent;
use TuskPress\Domain\Post\Post;
use TuskPress\Domain\Post\PostId;

final class EventDispatchTest extends TestCase
{
    public function test_bootstrapped_dispatcher_invokes_existing_post_saved_audit_listener(): void
    {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $title = 'dispatch-regression-'.bin2hex(random_bytes(8));
        $event = new PostSavedEvent(new Post(id: new PostId(42), title: $title));
        $log = sys_get_temp_dir().'/tuskpress_audit.log';
        $offset = is_file($log) ? filesize($log) : 0;

        $application->container()->get(EventDispatcherInterface::class)->dispatch($event);

        self::assertFileExists($log, 'Dispatch did not invoke AuditLogger.');
        $output = file_get_contents($log, false, null, $offset);
        self::assertStringContainsString("Post Saved: ID 42, Title '{$title}'", $output);
    }
}
