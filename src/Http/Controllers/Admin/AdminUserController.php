<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;

use Tusk\Security\Attribute\Authenticated;
use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;

use TuskPress\Infrastructure\Persistence\MySqlUserRepository;
use TuskPress\Domain\User\User;
use TuskPress\Domain\User\UserId;
use TuskPress\Domain\User\UserRole;

#[Service]
#[Authenticated]
#[Controller]
class AdminUserController
{
    public function __construct(
        private readonly MySqlUserRepository $userRepository,
        private readonly \Tusk\Security\Contract\GuardInterface $guard,
        private readonly \TuskPress\Infrastructure\Persistence\MySqlOptionRepository $optionRepo
    ) {}

    #[Route('/wp-admin/users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $users = $this->userRepository->findAll(50, 1);
        $usersHtml = '';
        $currentUser = $this->guard->user();

        foreach ($users as $u) {
            $roleLabel = match($u->role->value) {
                'administrator' => '<span class="badge badge-success">Administrator</span>',
                'editor'        => '<span class="badge badge-info">Editor</span>',
                'author'        => '<span class="badge badge-warning">Author</span>',
                default         => '<span class="badge badge-gray">' . ucfirst($u->role->value) . '</span>',
            };

            $isSelf = $currentUser && (string)$currentUser->getIdentifier() === (string)$u->idValue;
            
            $deleteForm = $isSelf ? '' : <<<HTML
                <form method="POST" action="/wp-admin/user-delete" style="display:inline;" data-confirm="Are you sure you want to delete this user?">
                    <input type="hidden" name="id" value="{$u->idValue}">
                    <button type="submit" class="btn btn-ghost" style="padding: 6px 12px; font-size: 12px; color: #ef4444; border-color: rgba(239,68,68,0.2);">Delete</button>
                </form>
            HTML;

            $usersHtml .= <<<HTML
            <tr>
                <td>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <img src="{$u->getAvatar(40)}" style="width:40px; height:40px; border-radius:10px;" alt="">
                        <div>
                            <div style="font-weight:600; color:#fff;">{$u->login}</div>
                            <div style="font-size:12px; color:var(--text-muted);">{$u->email}</div>
                        </div>
                    </div>
                </td>
                <td>{$u->displayName}</td>
                <td>{$roleLabel}</td>
                <td>
                    <div style="display:flex; gap: 8px;">
                        <a href="/wp-admin/user-edit?id={$u->idValue}" class="btn btn-ghost" style="padding: 6px 12px; font-size: 12px;">Edit</a>
                        {$deleteForm}
                    </div>
                </td>
            </tr>
            HTML;
        }

        $alert = '';
        if (($request->getQueryParams()['deleted'] ?? '') === 'true') {
            $alert = '<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; padding: 12px 20px; border-radius: 10px; margin-bottom: 24px; font-size: 14px; font-weight: 500;">User successfully deleted.</div>';
        }

        $contentHtml = <<<HTML
        {$alert}
        <div class="card">
            <div class="card-header">
                <div class="card-title">All Users</div>
                <a href="/wp-admin/user-new" class="btn btn-primary">+ Add New</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Username / Email</th>
                                <th>Display Name</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$usersHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        HTML;

        return Response::html(AdminView::render('Users', $contentHtml, $this->getSiteName(), $this->getUsername()));
    }

    #[Route('/wp-admin/user-new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $alert = '';
        if ($request->method === 'POST') {
            $data = $request->getParsedBody();
            
            if (empty($data['user_login']) || empty($data['user_email']) || empty($data['pass1'])) {
                $alert = '<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 12px 20px; border-radius: 10px; margin-bottom: 24px;">Please fill in all required fields.</div>';
            } elseif ($this->userRepository->findByLogin($data['user_login']) || $this->userRepository->findByEmail($data['user_email'])) {
                $alert = '<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 12px 20px; border-radius: 10px; margin-bottom: 24px;">This login or email is already in use.</div>';
            } else {
                $role = UserRole::tryFrom($data['role'] ?? 'subscriber') ?? UserRole::Subscriber;
                
                $user = User::create(
                    id: null,
                    login: $data['user_login'],
                    email: $data['user_email'],
                    displayName: $data['display_name'] ?: $data['user_login'],
                    role: $role,
                    plainPassword: $data['pass1']
                );
                
                $this->userRepository->save($user);
                return Response::redirect('/wp-admin/users');
            }
        }

        $contentHtml = <<<HTML
        {$alert}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Add New User</div>
            </div>
            <div class="card-body">
                <form method="POST" action="/wp-admin/user-new" style="max-width: 500px;">
                    <div class="form-group">
                        <label class="form-label">Username (Login) *</label>
                        <input type="text" name="user_login" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="user_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Name</label>
                        <input type="text" name="display_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="pass1" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="subscriber">Subscriber</option>
                            <option value="author">Author</option>
                            <option value="editor">Editor</option>
                            <option value="administrator">Administrator</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 16px;">Add New User</button>
                    <a href="/wp-admin/users" class="btn btn-ghost" style="margin-top: 16px; margin-left: 8px;">Cancel</a>
                </form>
            </div>
        </div>
        HTML;

        return Response::html(AdminView::render('Add User', $contentHtml, $this->getSiteName(), $this->getUsername()));
    }

    #[Route('/wp-admin/user-edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $id = (int) ($request->getQueryParams()['id'] ?? 0);
        if ($id <= 0) {
            return Response::redirect('/wp-admin/users');
        }

        $user = $this->userRepository->findById(new UserId($id));
        if (!$user) {
            return Response::redirect('/wp-admin/users');
        }

        $alert = '';
        if ($request->method === 'POST') {
            $data = $request->getParsedBody();
            
            $user->email = $data['user_email'] ?? $user->email;
            $user->displayName = $data['display_name'] ?? $user->displayName;
            $user->role = UserRole::tryFrom($data['role'] ?? $user->role->value) ?? $user->role;
            
            if (!empty($data['pass1'])) {
                $user->passwordHash = password_hash($data['pass1'], PASSWORD_BCRYPT, ['cost' => 12]);
            }
            
            $this->userRepository->save($user);
            $alert = '<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; padding: 12px 20px; border-radius: 10px; margin-bottom: 24px; font-size: 14px; font-weight: 500;">User successfully updated.</div>';
        }

        $isSubscriber = $user->role->value === 'subscriber' ? 'selected' : '';
        $isAuthor = $user->role->value === 'author' ? 'selected' : '';
        $isEditor = $user->role->value === 'editor' ? 'selected' : '';
        $isAdmin = $user->role->value === 'administrator' ? 'selected' : '';

        $contentHtml = <<<HTML
        {$alert}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Edit User: {$user->login}</div>
            </div>
            <div class="card-body">
                <form method="POST" action="/wp-admin/user-edit?id={$id}" style="max-width: 500px;">
                    <div class="form-group">
                        <label class="form-label">Username (Login)</label>
                        <input type="text" class="form-control" value="{$user->login}" disabled>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">Usernames cannot be changed.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="user_email" class="form-control" value="{$user->email}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Name</label>
                        <input type="text" name="display_name" class="form-control" value="{$user->displayName}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password (leave blank to not change)</label>
                        <input type="password" name="pass1" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="subscriber" {$isSubscriber}>Subscriber</option>
                            <option value="author" {$isAuthor}>Author</option>
                            <option value="editor" {$isEditor}>Editor</option>
                            <option value="administrator" {$isAdmin}>Administrator</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 16px;">Update User</button>
                    <a href="/wp-admin/users" class="btn btn-ghost" style="margin-top: 16px; margin-left: 8px;">Back to Users</a>
                </form>
            </div>
        </div>
        HTML;

        return Response::html(AdminView::render('Edit User', $contentHtml, $this->getSiteName(), $this->getUsername()));
    }

    #[Route('/wp-admin/user-delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        $id = (int) ($request->getParsedBody()['id'] ?? 0);
        $currentUser = $this->guard->user();

        // Cannot delete oneself
        if ($id > 0 && $currentUser && (string)$currentUser->getIdentifier() !== (string)$id) {
            $this->userRepository->delete(new UserId($id));
        }

        return Response::redirect('/wp-admin/users?deleted=true');
    }

    private function getSiteName(): string {
        return $this->optionRepo->get('blogname', 'TuskPress');
    }

    private function getUsername(): string {
        $user = $this->guard->user();
        return $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrator';
    }
}

