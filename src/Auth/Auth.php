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
            $this->session->remove('user_id');
            return null;
        }

        $this->resolvedUser = $user;
        return $user;
    }

    public function login(int $userId): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', $userId);
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
}
