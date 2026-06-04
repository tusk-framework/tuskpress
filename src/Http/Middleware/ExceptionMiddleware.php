<?php

declare(strict_types=1);

namespace TuskPress\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Http\Response as TuskResponse;

#[Service]
class ExceptionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 401 && $e->getMessage() === 'Unauthenticated') {
                return TuskResponse::redirect('/wp-login');
            }
            throw $e;
        }
    }
}
