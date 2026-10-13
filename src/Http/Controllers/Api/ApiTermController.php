<?php

declare(strict_types=1);
namespace TuskPress\Http\Controllers\Api;

use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
#[Service]
#[Controller]
class ApiTermController {
    #[Route('/wp-json/wp/v2/categories', methods: ['GET'])]
    public function index(Request $r): Response { return Response::json([]); }
}

