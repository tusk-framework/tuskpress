<?php

declare(strict_types=1);

namespace TuskPress\Domain\Post\Event;

use TuskPress\Domain\Post\Post;

class PostSavedEvent
{
    public function __construct(
        public readonly Post $post
    ) {}
}
