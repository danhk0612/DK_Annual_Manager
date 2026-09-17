<?php

declare(strict_types=1);

namespace DKAnnual\Session;

use DKAnnual\Config;

final class Session
{
    public function start(Config $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) $config->get('app.session_name', 'dk_annual_manager'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (bool) $config->get('app.session_cookie_secure', true),
            'httponly' => true,
            'samesite' => (string) $config->get('app.session_cookie_samesite', 'Lax'),
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
