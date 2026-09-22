<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'DK Annual Manager',
        'url' => 'https://leave.example.com',
        'timezone' => 'Asia/Seoul',
        'debug' => false,
        'session_name' => 'dk_annual_manager',
        'session_cookie_secure' => true,
        'session_cookie_samesite' => 'Lax',
    ],

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'dk_annual_manager',
        'username' => 'dk_annual_manager',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
    ],

    // Telegram/OIDC 값은 /setup 또는 관리자 환경설정에서 DB 관리값으로 저장할 수 있습니다.
    // 아래 값은 레거시/비상 기본값으로 둘 수 있으며 신규 설치 마법사는 DB 관리값을 우선합니다.
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
        // 관리자와 직원이 함께 보는 회사 공용 그룹. 신청 사유 등 관리 정보는 이 그룹에 보내지 않습니다.
        'company_chat_id' => '',
    ],

    // ServiceKey는 /setup 또는 관리자 환경설정에서 입력할 수 있습니다.
    'holiday_api' => [
        'service_key' => '',
        'base_url' => 'https://apis.data.go.kr/B090041/openapi/service/SpcdeInfoService',
    ],

    'leave' => [
        'year_basis' => 'anniversary',
        'max_statutory_days' => 25,
    ],
];
