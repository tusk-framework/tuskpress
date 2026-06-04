<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Api;

use Tusk\Contracts\Attributes\Service;

use Tusk\Web\Attribute\Route;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
use TuskPress\Domain\Post\PostId;
use TuskPress\Domain\Post\PostQuery;
use TuskPress\Domain\Post\PostStatus;
use TuskPress\Domain\Post\PostType;
use TuskPress\Infrastructure\Persistence\MySqlPostRepository;

/**
 * ApiPostController — REST API /wp-json/wp/v2/posts
 * Compatible with the WP REST API v2.
 */
#[Service]
class ApiPostController
{
    public function __construct(
        private readonly MySqlPostRepository $postRepository,
        private readonly \TuskPress\Infrastructure\Persistence\MySqlOptionRepository $optionRepo
    ) {}

    #[Route('/wp-json/wp/v2/posts', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 10)));
        $page    = max(1, (int) ($request->query['page'] ?? 1));
        $status  = PostStatus::tryFrom($request->query['status'] ?? 'publish') ?? PostStatus::Publish;
        $search  = $request->query['search'] ?? null;

        $query = new PostQuery(
            type:    PostType::Post,
            status:  $status,
            perPage: $perPage,
            page:    $page,
            search:  $search,
        );

        $posts = $this->postRepository->findAll($query);
        $total = $this->postRepository->count($query);

        $data = array_map([$this, 'formatPost'], $posts);

        return Response::json($data, 200, [
            'X-WP-Total'      => (string) $total,
            'X-WP-TotalPages' => (string) ceil($total / $perPage),
        ]);
    }

    #[Route('/wp-json/wp/v2/posts/{id}', methods: ['GET'])]
    public function show(int $id, Request $request): Response
    {
        $post = $this->postRepository->findById(new PostId($id));

        if (!$post || !$post->isPublished()) {
            return Response::json(['code' => 'rest_post_invalid_id', 'message' => 'Post not found.'], 404);
        }

        return Response::json($this->formatPost($post));
    }

    private function formatPost(\TuskPress\Domain\Post\Post $post): array
    {
        $siteUrl = rtrim($this->optionRepo->get('siteurl', 'http://localhost:8000'), '/');
        $homeUrl = rtrim($this->optionRepo->get('home', $siteUrl), '/');
        
        $excerptText = $post->excerpt;
        if (!$excerptText && $post->content) {
            $words = explode(' ', strip_tags($post->content));
            $excerptText = implode(' ', array_slice($words, 0, 55));
            if (count($words) > 55) {
                $excerptText .= '...';
            }
        }

        return [
            'id'          => $post->id->value,
            'date'        => $post->createdAt->format('Y-m-d\TH:i:s'),
            'date_gmt'    => $post->createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
            'modified'    => $post->updatedAt->format('Y-m-d\TH:i:s'),
            'modified_gmt' => $post->updatedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
            'slug'        => $post->slug,
            'status'      => $post->status->value,
            'type'        => $post->type->value,
            'link'        => $homeUrl . '/' . $post->slug . '/',
            'title'       => ['rendered' => htmlspecialchars($post->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
            'content'     => ['rendered' => $post->content, 'protected' => $post->isPasswordProtected()],
            'excerpt'     => ['rendered' => $excerptText, 'protected' => false],
            'author'      => $post->authorId->value,
            'comment_status' => $post->commentStatus,
            'ping_status'   => $post->pingStatus,
            'categories'    => [],
            'tags'          => [],
            '_links' => [
                'self'       => [['href' => $siteUrl . '/wp-json/wp/v2/posts/' . $post->id->value]],
                'collection' => [['href' => $siteUrl . '/wp-json/wp/v2/posts']],
                'author'     => [['href' => $siteUrl . '/wp-json/wp/v2/users/' . $post->authorId->value]],
            ],
        ];
    }
}
