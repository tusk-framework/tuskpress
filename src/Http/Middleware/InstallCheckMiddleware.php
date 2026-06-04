<?php

declare(strict_types=1);

namespace TuskPress\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Http\Response as TuskResponse;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;

#[Service]
class InstallCheckMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly MySqlOptionRepository $optionRepo) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($request->getUri()->getPath() === '/wp-admin/install.php') {
            return $handler->handle($request);
        }

        try {
            if (!$this->optionRepo->exists('siteurl')) {
                return TuskResponse::redirect('/wp-admin/install.php');
            }
        } catch (\Throwable $e) {
            // If options table doesn't exist or DB error occurs, it is not installed
            return TuskResponse::redirect('/wp-admin/install.php');
        }

        return $handler->handle($request);
    }
}
