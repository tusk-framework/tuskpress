<?php

declare(strict_types=1);

namespace TuskPress\Http\Middleware;

use Tusk\Contracts\Attributes\Service;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * SessionMiddleware — Initializes the PHP session for each request.
 * In Tusk Engine (persistent) mode, manages sessions between requests.
 */
#[Service]
class SessionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $this->closeAndReset();
        $sessionName = 'tuskpress_session';
        session_name($sessionName);

        // PSR-7 cookies are the only identity source in a persistent worker.
        $cookies = $request->getCookieParams();
        $cookie = $cookies[$sessionName] ?? null;
        if (is_string($cookie) && preg_match('/\A[a-zA-Z0-9,-]{1,256}\z/', $cookie) === 1) {
            session_id($cookie);
        }

        try {
            if (!session_start(['use_cookies' => false, 'cache_limiter' => '', 'use_strict_mode' => true])) {
                throw new \RuntimeException('Unable to start the session.');
            }
            $response = $handler->handle($request);
            if (session_status() === PHP_SESSION_ACTIVE) {
                $cookieStr = sprintf('%s=%s; Path=/; HttpOnly; SameSite=Lax', $sessionName, session_id());
                $response = $response->withAddedHeader('Set-Cookie', $cookieStr);
            }
            return $response;
        } finally {
            $this->closeAndReset();
        }
    }

    private function closeAndReset(): void
    {
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        } finally {
            $_SESSION = [];
            session_id('');
        }
    }
}
