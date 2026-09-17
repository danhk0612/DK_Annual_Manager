<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'DK Annual Manager',
        'url' => 'https://leave.example.com',
        'timezone' => 'Asia/Seoul',
        'session_name' => 'dk_annual_manager',
        'session_cookie_secure' => true,
        'session_cookie_samesite' => 'Lax',
    ],

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'dk_annual_manager',
        'username' => 'dk_annual_manager',
        'password' => '',
        'charset' => 'utf8mb4',
    ],

    'telegram' => [
        'client_id' => '',
        'client_secret' => '',
        'bot_token' => '',
        'redirect_uri' => 'https://leave.example.com/auth/telegram/callback',
        'authorization_url' => 'https://oauth.telegram.org/auth',
        'token_url' => 'https://oauth.telegram.org/token',
        'jwks_url' => 'https://oauth.telegram.org/.well-known/jwks.json',
        'issuer' => 'https://oauth.telegram.org',
        'scopes' => ['openid', 'profile', 'telegram:bot_access'],
        'bot_api_base_url' => 'https://api.telegram.org',
        'bootstrap_admin_telegram_ids' => [],
        'admin_chat_ids' => [],
    ],

    'holiday_api' => [
        'service_key' => '',
    ],
];
