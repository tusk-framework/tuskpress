<?php

declare(strict_types=1);

namespace TuskPress\Http\Middleware;

use Tusk\Contracts\Attributes\Service;
use Tusk\Security\Middleware\SecurityMiddleware;

#[Service(scope: 'request')]
final class RequestScopedSecurityMiddleware extends SecurityMiddleware
{
}
