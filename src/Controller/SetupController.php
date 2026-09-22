<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Config;
use DKAnnual\Holiday\KasiHolidayClient;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\AppSettingRepository;
use DKAnnual\Repository\HolidayRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\Setup\SetupService;
use DKAnnual\Telegram\TelegramBotClient;
use PDO;
use Throwable;

final class SetupController
{
    public function __construct(
        private readonly Config $config,
        private readonly PDO $pdo,
        private readonly SetupService $setup,
        private readonly Csrf $csrf,
        private readonly string $templatePath,
    ) {
    }

    public function index(Request $request): Response
    {
        $status = $this->setup->status();
        $settings = $status['schema'] ? new AppSettingRepository($this->pdo) : null;

        $telegramBotInfo = null;
        $telegramChats = [];
        $probeError = null;

        if ($status['schema'] && $status['telegram'] && (string) $request->input('probe_telegram', '') === '1') {
            try {
                $this->setup->applyManagedConfig();
                $bot = new TelegramBotClient($this->config);
                $telegramBotInfo = $bot->getMe();
                $telegramChats = $bot->recentChats();
            } catch (Throwable $exception) {
                error_log('[DK Annual Setup] Telegram probe failed: ' . $exception::class);
                $probeError = 'Telegram Bot 또는 최근 채팅을 확인하지 못했습니다. 입력값과 Bot 권한을 확인해 주세요.';
            }
        }

        $adminCount = $status['schema']
            ? (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn()
            : 0;

        $botUsername = is_array($telegramBotInfo) ? trim((string) ($telegramBotInfo['username'] ?? '')) : '';
        if ($botUsername === '' && $settings !== null) {
            $botUsername = trim((string) $settings->get('telegram.bot_username', ''));
        }

        $detectedOrigin = $this->detectedOrigin($request);
        $appUrl = $detectedOrigin;
        $redirectUri = $settings?->get('telegram.redirect_uri', null)
            ?? ($detectedOrigin !== '' ? $detectedOrigin . '/auth/telegram/callback' : '');

        return $this->render([
            'status' => $status,
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'error' => $request->input('error'),
            'probeError' => $probeError,
            'telegramBotInfo' => $telegramBotInfo,
            'telegramChats' => $telegramChats,
            'adminCount' => $adminCount,
            'appUrl' => $appUrl,
            'allowedOrigin' => $detectedOrigin,
            'redirectUri' => $redirectUri,
            'configuredClientId' => $settings?->get('telegram.client_id', '') ?? '',
            'hasClientSecret' => trim((string) ($settings?->get('telegram.client_secret', '') ?? '')) !== '',
            'hasBotToken' => trim((string) ($settings?->get('telegram.bot_token', '') ?? '')) !== '',
            'companyChatId' => $status['schema'] && $settings !== null
                ? trim((string) $settings->get('telegram.company_chat_id', ''))
                : '',
            'bootstrapAdminIds' => $status['schema'] && $settings !== null ? $settings->lineList('telegram.bootstrap_admin_telegram_ids') : [],
            'hasHolidayKey' => $status['schema']
                && trim((string) ($settings?->get('holiday_api.service_key', '') ?? '')) !== '',
            'employeeBotLink' => $botUsername !== '' ? 'https://t.me/' . rawurlencode($botUsername) . '?start=employee' : null,
            'employeeLoginLink' => $appUrl !== '' ? $appUrl . '/login' : null,
        ]);
    }

    public function telegramProbe(Request $request): Response
    {
        $status = $this->setup->status();
        if (!$status['schema'] || !$status['telegram']) {
            return Response::json([
                'ok' => false,
                'message' => 'Telegram 설정을 먼저 완료해 주세요.',
                'chats' => [],
            ], 400);
        }

        try {
            $this->setup->applyManagedConfig();
            $bot = new TelegramBotClient($this->config);
            $botInfo = $bot->getMe();
            $chats = $bot->recentChats();

            $privateCount = 0;
            $groupCount = 0;
            foreach ($chats as $chat) {
                if (($chat['type'] ?? '') === 'private') {
                    $privateCount++;
                } elseif (in_array(($chat['type'] ?? ''), ['group', 'supergroup'], true)) {
                    $groupCount++;
                }
            }

            return Response::json([
                'ok' => true,
                'message' => $chats === []
                    ? '최근 Telegram 채팅을 찾지 못했습니다. 봇 개인 채팅에서 /start를 보내거나 회사 공용 그룹에서 메시지를 보낸 뒤 다시 확인하세요.'
                    : sprintf('최근 채팅 %d개를 찾았습니다. 개인 채팅 %d개, 그룹 %d개입니다.', count($chats), $privateCount, $groupCount),
                'bot' => [
                    'name' => (string) ($botInfo['first_name'] ?? ''),
                    'username' => (string) ($botInfo['username'] ?? ''),
                ],
                'chats' => $chats,
            ]);
        } catch (Throwable $exception) {
            error_log('[DK Annual Setup] Telegram chat probe failed: ' . $exception::class);

            return Response::json([
                'ok' => false,
                'message' => '최근 채팅을 확인하지 못했습니다. Bot Token, Bot 권한 또는 Telegram webhook 설정을 확인해 주세요.',
                'chats' => [],
            ], 502);
        }
    }

    public function initializeDatabase(Request $request): Response
    {
        try {
            $this->setup->initializeSchema();
            return $this->message('DB 초기화를 완료했습니다. 다음 단계로 진행하세요.');
        } catch (Throwable $exception) {
            error_log('[DK Annual Setup] Schema initialization failed: ' . $exception::class);
            return $this->error('DB 초기화에 실패했습니다. 서버 오류 로그를 확인해 주세요.');
        }
    }

    public function saveTelegram(Request $request): Response
    {
        if (!$this->setup->schemaReady()) {
            return $this->error('DB 초기화를 먼저 완료해 주세요.');
        }

        $settings = new AppSettingRepository($this->pdo);
        $clientId = trim((string) $request->input('client_id', ''));
        $clientSecretInput = trim((string) $request->input('client_secret', ''));
        $botTokenInput = trim((string) $request->input('bot_token', ''));
        $redirectUri = trim((string) $request->input('redirect_uri', ''));

        $clientSecret = $clientSecretInput !== ''
            ? $clientSecretInput
            : trim((string) $settings->get('telegram.client_secret', ''));
        $botToken = $botTokenInput !== ''
            ? $botTokenInput
            : trim((string) $settings->get('telegram.bot_token', ''));

        if ($clientId === '' || $clientSecret === '' || $botToken === '') {
            return $this->error('Telegram Client ID, Client Secret, Bot Token을 모두 입력해 주세요.');
        }
        if (!str_starts_with($redirectUri, 'https://')) {
            return $this->error('Telegram Redirect URI는 HTTPS 주소여야 합니다.');
        }

        $this->config->set('telegram.client_id', $clientId);
        $this->config->set('telegram.client_secret', $clientSecret);
        $this->config->set('telegram.bot_token', $botToken);
        $this->config->set('telegram.redirect_uri', $redirectUri);

        try {
            $bot = new TelegramBotClient($this->config);
            $botInfo = $bot->getMe();
        } catch (Throwable $exception) {
            error_log('[DK Annual Setup] Telegram verification failed: ' . $exception::class);
            return $this->error('Telegram Bot 연결 확인에 실패했습니다. Client ID/Secret, Bot Token, Allowed URL 설정을 확인해 주세요.');
        }

        $settings->set('telegram.client_id', $clientId, null);
        $settings->set('telegram.client_secret', $clientSecret, null);
        $settings->set('telegram.bot_token', $botToken, null);
        $settings->set('telegram.redirect_uri', $redirectUri, null);

        $username = trim((string) ($botInfo['username'] ?? ''));
        if ($username !== '') {
            $settings->set('telegram.bot_username', $username, null);
        }

        return Response::redirect('/setup?probe_telegram=1&message=' . rawurlencode(
            'Telegram 설정을 저장하고 봇 연결을 확인했습니다: @' . (string) ($botInfo['username'] ?? 'bot')
        ));
    }

    public function saveTelegramTargets(Request $request): Response
    {
        if (!$this->setup->schemaReady()) {
            return $this->error('DB 초기화를 먼저 완료해 주세요.');
        }

        $groupChatId = trim((string) $request->input('company_chat_id', ''));
        $adminTelegramId = trim((string) $request->input('admin_telegram_id', ''));

        if ($groupChatId === '' || preg_match('/^-?\d+$/', $groupChatId) !== 1) {
            return $this->error('회사 공용 그룹 Chat ID를 확인해 주세요.');
        }
        if ($adminTelegramId === '' || preg_match('/^\d+$/', $adminTelegramId) !== 1) {
            return $this->error('최초 관리자 Telegram User ID를 확인해 주세요.');
        }

        $settings = new AppSettingRepository($this->pdo);
        $settings->set('telegram.company_chat_id', $groupChatId, null);
        $settings->delete('telegram.admin_chat_ids');
        $settings->set('telegram.bootstrap_admin_telegram_ids', $adminTelegramId, null);

        return $this->message('회사 공용 그룹과 최초 관리자 계정을 저장했습니다. 이제 Telegram 로그인을 실행하세요.');
    }

    public function saveHoliday(Request $request): Response
    {
        if (!$this->setup->schemaReady()) {
            return $this->error('DB 초기화를 먼저 완료해 주세요.');
        }

        $serviceKey = trim((string) $request->input('service_key', ''));
        if ($serviceKey === '') {
            return $this->error('공휴일 API ServiceKey를 입력해 주세요.');
        }

        $this->config->set('holiday_api.service_key', $serviceKey);

        try {
            $client = new KasiHolidayClient($this->config);
            $holidays = $client->fetchYear((int) date('Y'));
            if ($holidays === []) {
                return $this->error('공휴일 API 연결은 되었지만 현재 연도 데이터가 비어 있습니다.');
            }
        } catch (Throwable $exception) {
            error_log('[DK Annual Setup] Holiday API verification failed: ' . $exception::class);
            return $this->error('공휴일 API 연결 확인에 실패했습니다. ServiceKey 승인 상태와 값을 확인해 주세요.');
        }

        $settings = new AppSettingRepository($this->pdo);
        $settings->set('holiday_api.service_key', $serviceKey, null);

        $repository = new HolidayRepository($this->pdo);
        $repository->replacePublicApiYear((int) date('Y'), $holidays);

        return $this->message(sprintf('공휴일 API 연결 및 %d년 동기화를 완료했습니다.', (int) date('Y')));
    }

    public function finish(Request $request): Response
    {
        $status = $this->setup->status();
        $adminCount = $status['schema']
            ? (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn()
            : 0;

        if (!$status['schema'] || !$status['telegram'] || !$status['chat'] || !$status['admin'] || !$status['holiday']) {
            return $this->error('필수 설정 단계가 모두 완료되지 않았습니다.');
        }
        if ($adminCount < 1) {
            return $this->error('최초 관리자 Telegram 로그인을 먼저 완료해 주세요.');
        }

        $settings = new AppSettingRepository($this->pdo);
        $settings->set('setup.completed', '1', null);
        $settings->set('setup.completed_at', date('c'), null);
        $this->setup->clearSetupKey();

        return Response::redirect('/admin/settings?message=' . rawurlencode('초기 서비스 설정을 완료했습니다.'));
    }

    /** @param array<string, mixed> $data */
    private function render(array $data): Response
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->templatePath . '/setup.php';
        return Response::html((string) ob_get_clean());
    }

    private function detectedOrigin(Request $request): string
    {
        $forwardedProto = strtolower(trim(explode(',', (string) $request->server('HTTP_X_FORWARDED_PROTO', ''))[0] ?? ''));
        $scheme = in_array($forwardedProto, ['http', 'https'], true)
            ? $forwardedProto
            : (((string) $request->server('HTTPS', '') !== '' && (string) $request->server('HTTPS', '') !== 'off') ? 'https' : 'http');

        $forwardedHost = trim(explode(',', (string) $request->server('HTTP_X_FORWARDED_HOST', ''))[0] ?? '');
        $host = $forwardedHost !== '' ? $forwardedHost : trim((string) $request->server('HTTP_HOST', ''));

        if ($host === '' || preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host) !== 1) {
            $configured = rtrim((string) $this->config->get('app.url', ''), '/');
            return filter_var($configured, FILTER_VALIDATE_URL) !== false ? $configured : '';
        }

        return $scheme . '://' . $host;
    }

    /** @return list<string> */
    private function effectiveList(?AppSettingRepository $settings, string $key): array
    {
        if ($settings === null) {
            return [];
        }

        $managed = $settings->get($key, null);
        if ($managed !== null) {
            return $settings->lineList($key);
        }

        $configured = $this->config->get($key, []);
        if (!is_array($configured)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $configured), static fn (string $value): bool => $value !== ''));
    }

    private function message(string $message): Response
    {
        return Response::redirect('/setup?message=' . rawurlencode($message));
    }

    private function error(string $message): Response
    {
        return Response::redirect('/setup?error=' . rawurlencode($message));
    }
}
