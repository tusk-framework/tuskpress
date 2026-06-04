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
        // Closes previous session in case the previous request left it open (isolation)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            session_unset();
        }

        $sessionName = 'tuskpress_session';
        session_name($sessionName);

        // Injects the session ID received in the cookie by PSR-7
        $cookies = $request->getCookieParams();
        if (isset($cookies[$sessionName])) {
            session_id($cookies[$sessionName]);
        }

        session_start();

        $response = $handler->handle($request);

        // Saves the current session, injects Set-Cookie into the PSR-7 response and clears it for the next request
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionId = session_id();
            $cookieStr = sprintf('%s=%s; Path=/; HttpOnly; SameSite=Lax', $sessionName, $sessionId);
            $response = $response->withAddedHeader('Set-Cookie', $cookieStr);
            
            session_write_close();
            $_SESSION = [];
        }

        return $response;
    }
}
