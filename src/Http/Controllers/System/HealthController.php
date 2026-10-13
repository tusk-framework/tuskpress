<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\System;

use Tusk\Web\Attribute\Controller;
use Tusk\Web\Attribute\Route;
use Tusk\Web\Http\Response;

#[Controller]
final class HealthController
{
    #[Route('/healthz')]
    public function health(): \Psr\Http\Message\ResponseInterface
    {
        return Response::html('ok');
    }
}
