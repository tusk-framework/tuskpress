<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Frontend;

use Tusk\Contracts\Attributes\Service;

use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;

#[Service]
#[Controller]
class FeedController {
    #[Route('/feed', methods: ['GET'])]
    public function rss(Request $r): Response { 
        return Response::html('<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title></channel></rss>', 200, ['Content-Type' => 'application/rss+xml']); 
    }
}
