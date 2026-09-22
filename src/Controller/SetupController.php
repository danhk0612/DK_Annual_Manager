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
                $probeError = $exception->getMessage();
            }
        }

        $adminCount = $status['schema']
            ? (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn()
            : 0;

        $botUsername = is_array($telegramBotInfo) ? trim((string) ($telegramBotInfo['username'] ?? '')) : '';
        $appUrl = rtrim((string) $this->config->get('app.url', ''), '/');
        $redirectUri = $settings?->get('telegram.redirect_uri', null)
            ?? (string) $this->config->get('telegram.redirect_uri', ($appUrl !== '' ? $appUrl . '/auth/telegram/callback' : ''));

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
            'redirectUri' => $redirectUri,
            'configuredClientId' => $settings?->get('telegram.client_id', '') ?? '',
            'hasClientSecret' => trim((string) ($settings?->get('telegram.client_secret', '') ?? '')) !== '',
            'hasBotToken' => trim((string) ($settings?->get('telegram.bot_token', '') ?? '')) !== '',
            'adminChats' => $status['schema'] && $settings !== null ? $settings->lineList('telegram.admin_chat_ids') : [],
            'bootstrapAdminIds' => $status['schema'] && $settings !== null ? $settings->lineList('telegram.bootstrap_admin_telegram_ids') : [],
            'hasHolidayKey' => $status['schema']
                && trim((string) ($settings?->get('holiday_api.service_key', '') ?? '')) !== '',
            'employeeBotLink' => $botUsername !== '' ? 'https://t.me/' . rawurlencode($botUsername) . '?start=employee' : null,
            'employeeLoginLink' => $appUrl !== '' ? $appUrl . '/login' : null,
        ]);
    }

    public function initializeDatabase(Request $request): Response
    {
        try {
            $this->setup->initializeSchema();
            return $this->message('DB 초기화를 완료했습니다. 다음 단계로 진행하세요.');
        } catch (Throwable $exception) {
            return $this->error('DB 초기화 실패: ' . $exception->getMessage());
        }
    }

    public function saveTelegram(Request $request): Response
    {
        if (!$this->setup->schemaReady()) {
            return $this->error('DB 초기화를 먼저 완료해 주세요.');
        }

        $settings = new AppSettingRepository($this->pdo);
        $clientId = trim((string) $request->input('client_id', ''));
        $clientSecret = trim((string) $request->input('client_secret', ''));
        $botToken = trim((string) $request->input('bot_token', ''));
        $redirectUri = trim((string) $request->input('redirect_uri', ''));

        if ($clientId === '') {
            return $this->error('Telegram Client ID를 입력해 주세요.');
        }
        if ($clientSecret === '' && trim((string) $settings->get('telegram.client_secret', '')) === '') {
            return $this->error('Telegram Client Secret을 입력해 주세요.');
        }
        if ($botToken === '' && trim((string) $settings->get('telegram.bot_token', '')) === '') {
            return $this->error('Telegram Bot Token을 입력해 주세요.');
        }
        if (!str_starts_with($redirectUri, 'https://')) {
            return $this->error('Telegram Redirect URI는 HTTPS 주소여야 합니다.');
        }

        $settings->set('telegram.client_id', $clientId, null);
        if ($clientSecret !== '') {
            $settings->set('telegram.client_secret', $clientSecret, null);
        }
        if ($botToken !== '') {
            $settings->set('telegram.bot_token', $botToken, null);
        }
        $settings->set('telegram.redirect_uri', $redirectUri, null);

        try {
            $this->setup->applyManagedConfig();
            $bot = new TelegramBotClient($this->config);
            $botInfo = $bot->getMe();
        } catch (Throwable $exception) {
            return $this->error('Telegram Bot 확인 실패: ' . $exception->getMessage());
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

        $groupChatId = trim((string) $request->input('group_chat_id', ''));
        $adminTelegramId = trim((string) $request->input('admin_telegram_id', ''));

        if ($groupChatId === '' || preg_match('/^-?\d+$/', $groupChatId) !== 1) {
            return $this->error('관리자 알림 그룹 Chat ID를 확인해 주세요.');
        }
        if ($adminTelegramId === '' || preg_match('/^\d+$/', $adminTelegramId) !== 1) {
            return $this->error('최초 관리자 Telegram User ID를 확인해 주세요.');
        }

        $settings = new AppSettingRepository($this->pdo);
        $settings->set('telegram.admin_chat_ids', $groupChatId, null);
        $settings->set('telegram.bootstrap_admin_telegram_ids', $adminTelegramId, null);

        return $this->message('관리자 그룹과 최초 관리자 계정을 저장했습니다. 이제 Telegram 로그인을 실행하세요.');
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

        $settings = new AppSettingRepository($this->pdo);
        $settings->set('holiday_api.service_key', $serviceKey, null);

        try {
            $this->setup->applyManagedConfig();
            $client = new KasiHolidayClient($this->config);
            $holidays = $client->fetchYear((int) date('Y'));
            if ($holidays === []) {
                return $this->error('공휴일 API 연결은 되었지만 현재 연도 데이터가 비어 있습니다.');
            }

            $repository = new HolidayRepository($this->pdo);
            $repository->replacePublicApiYear((int) date('Y'), $holidays);
        } catch (Throwable $exception) {
            return $this->error('공휴일 API 확인 실패: ' . $exception->getMessage());
        }

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
