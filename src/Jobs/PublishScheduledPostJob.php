<?php

declare(strict_types=1);

namespace TuskPress\Jobs;

use TuskPress\Domain\Post\PostId;
use TuskPress\Domain\Post\PostStatus;
use TuskPress\Infrastructure\Persistence\MySqlPostRepository;

/**
 * PublishScheduledPostJob — Background job to publish scheduled posts.
 * Replaces the need for wp-cron.php to be triggered on every site visit.
 */
class PublishScheduledPostJob
{
    /**
     * @param array $payload ['post_id' => int]
     */
    public function handle(array $payload, MySqlPostRepository $postRepository, \TuskPress\Plugin\HookBus $hookBus): void
    {
        $postId = new PostId((int) ($payload['post_id'] ?? 0));
        $post = $postRepository->findById($postId);

        if ($post && $post->status->value === 'future') {
            // Checks if the publication date has passed
            $now = new \DateTimeImmutable();
            if ($post->publishedAt <= $now) {
                // Publishes the post
                $post->publish();

                $postRepository->save($post);
                $hookBus->dispatch('publish_post', ['post_id' => $postId->value, 'post' => $post]);
                $hookBus->dispatch('tuskpress_job_executed', ['job' => 'publish_post', 'post_id' => $postId->value]);
            }
        }
    }
}
