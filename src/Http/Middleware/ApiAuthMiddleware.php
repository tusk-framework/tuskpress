<?php

declare(strict_types=1);

namespace TuskPress\Http\Middleware;

use Tusk\Contracts\Attributes\Service;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tusk\Security\Authentication\JwtGuard;
use Tusk\Web\Http\Request as TuskRequest;
use Tusk\Web\Http\Response as TuskResponse;
use TuskPress\Infrastructure\Persistence\MySqlUserRepository;

#[Service(scope: 'request')]
class ApiAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly MySqlUserRepository $userRepository
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Applies JWT middleware only to API routes
        $path = $request->getUri()->getPath();
        
        if (!str_starts_with($path, '/wp-json/')) {
            return $handler->handle($request);
        }

        // If the route is for public reading of posts, maybe we can let it pass
        // But let's implement the check for all, and if it's not authenticated, we set it globally
        $guard = new JwtGuard($this->userRepository, new TuskRequest($request));
        
        if ($guard->check()) {
            // If we need to pass it to the request, we can do it later.
            // For the MVP, route protection already checks $guard internally if necessary
            // Ideally, we should create a request with the user attribute.
            $request = $request->withAttribute('user', $guard->user());
        }

        // Example: if it is POST in wp-json, we require auth
        if ($request->getMethod() !== 'GET' && !$guard->check()) {
            return TuskResponse::json([
                'code' => 'rest_unauthorized',
                'message' => 'Sorry, you are not allowed to do that.',
                'data' => ['status' => 401]
            ], 401);
        }

        return $handler->handle($request);
    }
}
