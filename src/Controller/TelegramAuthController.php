<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Auth\Auth;
use DKAnnual\Config;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\UserRepository;
use DKAnnual\Session\Session;
use DKAnnual\Setup\SetupService;
use DKAnnual\Telegram\TelegramOidcClient;
use DKAnnual\View\View;
use Throwable;

final class TelegramAuthController
{
    private const SESSION_KEY = 'telegram_oidc';
    private const FLOW_TTL_SECONDS = 600;

    public function __construct(
        private readonly Config $config,
        private readonly Session $session,
        private readonly TelegramOidcClient $telegram,
        private readonly UserRepository $users,
        private readonly Auth $auth,
        private readonly View $view,
        private readonly ?SetupService $setup = null,
    ) {
    }

    public function loginPage(Request $request): Response
    {
        return Response::html($this->view->render('login', [
            'title' => '로그인',
            'configured' => $this->isConfigured(),
            'returnTo' => $this->safeReturnTo((string) $request->input('return_to', '/')),
            'message' => $request->input('message'),
        ]));
    }

    public function start(Request $request): Response
    {
        try {
            $state = $this->randomUrlSafe(32);
            $nonce = $this->randomUrlSafe(32);
            $codeVerifier = $this->randomUrlSafe(64);
            $codeChallenge = $this->base64Url(hash('sha256', $codeVerifier, true));

            $this->session->set(self::SESSION_KEY, [
                'state' => $state,
                'nonce' => $nonce,
                'code_verifier' => $codeVerifier,
                'return_to' => $this->safeReturnTo((string) $request->input('return_to', '/')),
                'created_at' => time(),
            ]);

            return Response::redirect($this->telegram->authorizationUrl($state, $nonce, $codeChallenge));
        } catch (Throwable $exception) {
            return $this->statusResponse('설정 오류', 'Telegram 로그인 설정을 확인해 주세요.', 500);
        }
    }

    public function callback(Request $request): Response
    {
        $stored = $this->session->get(self::SESSION_KEY);
        $this->session->remove(self::SESSION_KEY);

        if (!is_array($stored) || !$this->validStoredFlow($stored)) {
            return $this->statusResponse('로그인 만료', '로그인 요청이 만료되었거나 유효하지 않습니다.', 400);
        }

        $state = $request->input('state');
        $code = $request->input('code');
        $error = $request->input('error');

        if (is_string($error) && $error !== '') {
            return $this->statusResponse('로그인 취소', 'Telegram 로그인이 완료되지 않았습니다.', 400);
        }

        if (!is_string($state) || !hash_equals((string) $stored['state'], $state) || !is_string($code) || $code === '') {
            return $this->statusResponse('잘못된 로그인 요청', 'Telegram 로그인 응답을 확인할 수 없습니다.', 400);
        }

        try {
            $tokens = $this->telegram->exchangeCode($code, (string) $stored['code_verifier']);
            $claims = $this->telegram->verifyIdToken((string) $tokens['id_token'], (string) $stored['nonce']);

            $telegramUserId = $this->telegramUserId($claims);
            $name = $this->telegramName($claims, $telegramUserId);
            $username = isset($claims['preferred_username']) && is_string($claims['preferred_username'])
                ? $claims['preferred_username']
                : null;

            $bootstrapAdmin = $this->isBootstrapAdmin($telegramUserId);
            $user = $this->users->findByTelegramUserId($telegramUserId);

            if ($user === null) {
                $userId = $this->users->createFromTelegram(
                    $telegramUserId,
                    $name,
                    $username,
                    $bootstrapAdmin ? 'admin' : 'user',
                    $bootstrapAdmin ? 'active' : 'pending',
                );
                $user = $this->users->findById($userId);
            } else {
                $this->users->syncTelegramProfile((int) $user['id'], $name, $username);
                if ($bootstrapAdmin && (($user['status'] ?? null) !== 'active' || ($user['role'] ?? null) !== 'admin')) {
                    $this->users->activateAsAdmin((int) $user['id']);
                }
                $user = $this->users->findById((int) $user['id']);
            }

            if ($user === null) {
                return $this->statusResponse('로그인 오류', '사용자 정보를 저장하지 못했습니다.', 500);
            }

            if (($user['status'] ?? null) === 'pending') {
                return $this->statusResponse(
                    '승인 대기',
                    'Telegram 연결은 완료되었습니다. 관리자가 계정을 활성화하면 로그인할 수 있습니다.',
                    403,
                    $telegramUserId,
                );
            }

            if (($user['status'] ?? null) !== 'active') {
                return $this->statusResponse('접근 중지', '현재 비활성화된 계정입니다.', 403);
            }

            $this->auth->login((int) $user['id']);
            if ($this->setup !== null && !$this->setup->completed()) {
                return Response::redirect('/setup?message=' . rawurlencode('최초 관리자 Telegram 로그인을 완료했습니다.'));
            }

            return Response::redirect($this->safeReturnTo((string) ($stored['return_to'] ?? '/')));
        } catch (Throwable $exception) {
            return $this->statusResponse('로그인 검증 실패', 'Telegram 로그인 정보를 검증하지 못했습니다.', 400);
        }
    }

    /** @param array<string, mixed> $stored */
    private function validStoredFlow(array $stored): bool
    {
        return isset($stored['state'], $stored['nonce'], $stored['code_verifier'], $stored['created_at'])
            && is_string($stored['state'])
            && is_string($stored['nonce'])
            && is_string($stored['code_verifier'])
            && is_int($stored['created_at'])
            && (time() - $stored['created_at']) <= self::FLOW_TTL_SECONDS;
    }

    /** @param array<string, mixed> $claims */
    private function telegramUserId(array $claims): int
    {
        $id = $claims['id'] ?? null;
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            throw new \RuntimeException('Telegram profile did not include a valid user ID.');
        }

        return (int) $id;
    }

    /** @param array<string, mixed> $claims */
    private function telegramName(array $claims, int $telegramUserId): string
    {
        foreach (['name', 'given_name', 'preferred_username'] as $key) {
            if (isset($claims[$key]) && is_string($claims[$key]) && trim($claims[$key]) !== '') {
                return trim($claims[$key]);
            }
        }

        return 'Telegram ' . $telegramUserId;
    }

    private function isBootstrapAdmin(int $telegramUserId): bool
    {
        if ($this->setup !== null && $this->setup->completed()) {
            return false;
        }

        $ids = $this->config->get('telegram.bootstrap_admin_telegram_ids', []);
        if (!is_array($ids)) {
            return false;
        }

        return in_array((string) $telegramUserId, array_map('strval', $ids), true);
    }

    private function isConfigured(): bool
    {
        return trim((string) $this->config->get('telegram.client_id', '')) !== ''
            && trim((string) $this->config->get('telegram.client_secret', '')) !== ''
            && trim((string) $this->config->get('telegram.redirect_uri', '')) !== '';
    }

    private function safeReturnTo(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return '/';
        }

        $parts = parse_url($value);
        if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host'])) {
            return '/';
        }

        return $value;
    }

    private function randomUrlSafe(int $bytes): string
    {
        return $this->base64Url(random_bytes($bytes));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function statusResponse(
        string $title,
        string $message,
        int $status,
        ?int $telegramUserId = null,
    ): Response {
        return Response::html($this->view->render('auth-status', [
            'title' => $title,
            'heading' => $title,
            'message' => $message,
            'telegramUserId' => $telegramUserId,
        ]), $status);
    }
}
