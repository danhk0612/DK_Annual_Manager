<?php

declare(strict_types=1);

namespace DKAnnual\Auth;

use DKAnnual\Repository\UserRepository;
use DKAnnual\Session\Session;

final class Auth
{
    /** @var array<string, mixed>|null */
    private ?array $resolvedUser = null;
    private bool $resolved = false;

    public function __construct(
        private readonly Session $session,
        private readonly UserRepository $users,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->resolvedUser;
        }

        $this->resolved = true;
        $userId = $this->session->get('user_id');
        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        $user = $this->users->findById((int) $userId);
        if ($user === null || ($user['status'] ?? null) !== 'active') {
            $this->clearIdentity();
            return null;
        }

        $sessionRole = $this->session->get('auth_role');
        $sessionStatus = $this->session->get('auth_status');
        if ($sessionRole === null || $sessionStatus === null) {
            $this->session->set('auth_role', (string) $user['role']);
            $this->session->set('auth_status', (string) $user['status']);
        } elseif ($sessionRole !== (string) $user['role'] || $sessionStatus !== (string) $user['status']) {
            $this->clearIdentity();
            return null;
        }

        $this->resolvedUser = $user;
        return $user;
    }

    public function login(int $userId): void
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            return;
        }

        $this->session->regenerate();
        $this->session->set('user_id', $userId);
        $this->session->set('auth_role', (string) $user['role']);
        $this->session->set('auth_status', (string) $user['status']);
        $this->resolved = false;
        $this->resolvedUser = null;
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->resolved = true;
        $this->resolvedUser = null;
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? null) === 'admin';
    }

    private function clearIdentity(): void
    {
        $this->session->remove('user_id');
        $this->session->remove('auth_role');
        $this->session->remove('auth_status');
        $this->resolved = true;
        $this->resolvedUser = null;
    }
}
