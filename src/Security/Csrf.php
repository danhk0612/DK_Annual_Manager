<?php

declare(strict_types=1);

namespace DKAnnual\Security;

use DKAnnual\Session\Session;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $expected = $this->session->get(self::SESSION_KEY);

        return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }
}
