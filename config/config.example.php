<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'DK Annual Manager',
        'url' => 'https://leave.example.com',
        'timezone' => 'Asia/Seoul',
        'session_name' => 'dk_annual_manager',
    ],

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'dk_annual_manager',
        'username' => 'dk_annual_manager',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
    ],

    'telegram' => [
        'client_id' => '',
        'client_secret' => '',
        'bot_token' => '',
        'redirect_uri' => 'https://leave.example.com/auth/telegram/callback',
        'admin_chat_ids' => [],
    ],

    'holiday_api' => [
        'service_key' => '',
        'base_url' => 'https://apis.data.go.kr/B090041/openapi/service/SpcdeInfoService',
    ],

    'leave' => [
        'year_basis' => 'anniversary',
        'max_statutory_days' => 25,
    ],
];
