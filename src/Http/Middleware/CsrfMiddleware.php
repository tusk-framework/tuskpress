<?php

declare(strict_types=1);
namespace TuskPress\Http\Middleware;

use Tusk\Contracts\Attributes\Service;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
#[Service]
class CsrfMiddleware implements MiddlewareInterface {
    public function process(Request $req, RequestHandlerInterface $handler): Response { return $handler->handle($req); }
}

