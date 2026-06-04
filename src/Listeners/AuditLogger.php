<?php

declare(strict_types=1);

namespace TuskPress\Listeners;

use Tusk\Contracts\Attributes\Service;
use Tusk\Events\Attributes\EventListener;
use TuskPress\Domain\Post\Event\PostSavedEvent;
use TuskPress\Domain\User\Event\UserRegisteredEvent;

#[Service]
class AuditLogger
{
    #[EventListener]
    public function onPostSaved(PostSavedEvent $event): void
    {
        // Example: Log or create an Option for an audit trail
        $msg = sprintf(
            "[%s] Post Saved: ID %d, Title '%s'\n",
            date('Y-m-d H:i:s'),
            $event->post->id->value,
            $event->post->title
        );
        file_put_contents('/tmp/tuskpress_audit.log', $msg, FILE_APPEND);
    }

    #[EventListener]
    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $msg = sprintf(
            "[%s] User Registered: %s (Email: %s)\n",
            date('Y-m-d H:i:s'),
            $event->user->getLogin(),
            $event->user->getEmail()
        );
        file_put_contents('/tmp/tuskpress_audit.log', $msg, FILE_APPEND);
    }
}
