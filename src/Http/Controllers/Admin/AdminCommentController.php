<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;


use Tusk\Security\Attribute\Authenticated;

use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
#[Service]
#[Authenticated]
#[Controller]
class AdminCommentController {
    public function __construct(
        private readonly \Tusk\Security\Contract\GuardInterface $guard
    ) {}

    #[Route('/wp-admin/edit-comments', methods: ['GET'])]
    public function index(Request $r): Response {
        if (!$this->guard->check()) return Response::redirect('/wp-login');
        return Response::redirect('/wp-admin/');
    }
}


