<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Auth\Auth;
use DKAnnual\Config;
use DKAnnual\Holiday\KasiHolidayClient;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\AppSettingRepository;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\Telegram\TelegramBotClient;
use DKAnnual\View\View;
use RuntimeException;
use Throwable;

final class AdminSettingsController
{
    public function __construct(
        private readonly Config $config,
        private readonly AppSettingRepository $settings,
        private readonly TelegramBotClient $telegramBot,
        private readonly Auth $auth,
        private readonly AuditLogRepository $audit,
        private readonly View $view,
        private readonly Csrf $csrf,
        private readonly string $publicPath,
    ) {
    }

    public function index(Request $request): Response
    {
        $telegramBotInfo = null;
        $telegramChats = [];
        $telegramProbeError = null;

        if ((string) $request->input('probe_telegram', '') === '1') {
            try {
                $telegramBotInfo = $this->telegramBot->getMe();
                $telegramChats = $this->telegramBot->recentChats();
                $username = trim((string) ($telegramBotInfo['username'] ?? ''));
                if ($username !== '') {
                    $this->settings->set('telegram.bot_username', $username, null);
                }
            } catch (Throwable $exception) {
                error_log('[DK Annual Settings] Telegram probe failed: ' . $exception::class);
                $telegramProbeError = 'Telegram Bot 또는 최근 채팅을 확인하지 못했습니다. 입력값과 Bot 권한을 확인해 주세요.';
            }
        }

        $managedTelegramRaw = $this->settings->get('telegram.admin_chat_ids', null);
        $telegramAdminChats = $managedTelegramRaw !== null
            ? $this->settings->lineList('telegram.admin_chat_ids')
            : $this->configuredAdminChats();

        $botUsername = trim((string) $this->settings->get('telegram.bot_username', ''));
        if ($telegramBotInfo !== null && !empty($telegramBotInfo['username'])) {
            $botUsername = (string) $telegramBotInfo['username'];
        }

        $appUrl = rtrim((string) $this->config->get('app.url', ''), '/');
        $redirectUri = (string) $this->settings->get(
            'telegram.redirect_uri',
            (string) $this->config->get('telegram.redirect_uri', $appUrl !== '' ? $appUrl . '/auth/telegram/callback' : ''),
        );

        return Response::html($this->view->render('admin-settings', [
            'title' => '환경 설정',
            'appName' => (string) $this->settings->get(
                'ui.app_name',
                (string) $this->config->get('app.name', 'DK Annual Manager'),
            ),
            'primaryColor' => (string) $this->settings->get('ui.primary_color', '#315efb'),
            'theme' => (string) $this->settings->get('ui.theme', 'system'),
            'logoPath' => (string) $this->settings->get('ui.logo_path', ''),
            'telegramAdminChats' => $telegramAdminChats,
            'telegramChatsManaged' => $managedTelegramRaw !== null,
            'telegramBotInfo' => $telegramBotInfo,
            'telegramChats' => $telegramChats,
            'telegramProbeError' => $telegramProbeError,
            'telegramClientId' => (string) $this->settings->get(
                'telegram.client_id',
                (string) $this->config->get('telegram.client_id', ''),
            ),
            'telegramRedirectUri' => $redirectUri,
            'credentialStatus' => [
                'client_id' => trim((string) $this->config->get('telegram.client_id', '')) !== '',
                'client_secret' => trim((string) $this->config->get('telegram.client_secret', '')) !== '',
                'bot_token' => trim((string) $this->config->get('telegram.bot_token', '')) !== '',
                'holiday_key' => trim((string) $this->config->get('holiday_api.service_key', '')) !== '',
            ],
            'employeeBotLink' => $botUsername !== '' ? 'https://t.me/' . rawurlencode($botUsername) . '?start=employee' : null,
            'employeeLoginLink' => $appUrl !== '' ? $appUrl . '/login' : null,
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'error' => $request->input('error'),
        ]));
    }

    public function saveAppearance(Request $request): Response
    {
        $actor = $this->requireActor();
        $appName = trim((string) $request->input('app_name', ''));
        $primaryColor = strtolower(trim((string) $request->input('primary_color', '#315efb')));
        $theme = (string) $request->input('theme', 'system');

        if ($appName === '' || $this->stringLength($appName) > 80) {
            return $this->error('프로그램 이름은 1~80자로 입력해 주세요.');
        }
        if (preg_match('/^#[0-9a-f]{6}$/', $primaryColor) !== 1) {
            return $this->error('대표색은 #RRGGBB 형식으로 입력해 주세요.');
        }
        if (!in_array($theme, ['light', 'dark', 'system'], true)) {
            return $this->error('테마 설정을 확인해 주세요.');
        }

        $this->settings->setMany([
            'ui.app_name' => $appName,
            'ui.primary_color' => $primaryColor,
            'ui.theme' => $theme,
        ], (int) $actor['id']);

        $this->audit->record((int) $actor['id'], 'settings.appearance_updated', 'app_settings', null, [
            'app_name' => $appName,
            'primary_color' => $primaryColor,
            'theme' => $theme,
        ], $this->ip($request));

        return $this->message('화면 설정을 저장했습니다.');
    }

    public function saveTelegramCredentials(Request $request): Response
    {
        $actor = $this->requireActor();
        $clientId = trim((string) $request->input('client_id', ''));
        $clientSecretInput = trim((string) $request->input('client_secret', ''));
        $botTokenInput = trim((string) $request->input('bot_token', ''));
        $redirectUri = trim((string) $request->input('redirect_uri', ''));

        $clientSecret = $clientSecretInput !== ''
            ? $clientSecretInput
            : trim((string) $this->config->get('telegram.client_secret', ''));
        $botToken = $botTokenInput !== ''
            ? $botTokenInput
            : trim((string) $this->config->get('telegram.bot_token', ''));

        if ($clientId === '' || $clientSecret === '' || $botToken === '') {
            return $this->error('Client ID, Client Secret, Bot Token을 모두 설정해 주세요.');
        }
        if (!str_starts_with($redirectUri, 'https://')) {
            return $this->error('Redirect URI는 HTTPS 주소여야 합니다.');
        }

        $this->config->set('telegram.client_id', $clientId);
        $this->config->set('telegram.client_secret', $clientSecret);
        $this->config->set('telegram.bot_token', $botToken);
        $this->config->set('telegram.redirect_uri', $redirectUri);

        try {
            $bot = new TelegramBotClient($this->config);
            $botInfo = $bot->getMe();
        } catch (Throwable $exception) {
            return $this->error('Bot 연결 확인에 실패해 설정을 저장하지 않았습니다: ' . $exception->getMessage());
        }

        $this->settings->set('telegram.client_id', $clientId, (int) $actor['id']);
        $this->settings->set('telegram.redirect_uri', $redirectUri, (int) $actor['id']);
        if ($clientSecretInput !== '' || $this->settings->get('telegram.client_secret', null) === null) {
            $this->settings->set('telegram.client_secret', $clientSecret, (int) $actor['id']);
        }
        if ($botTokenInput !== '' || $this->settings->get('telegram.bot_token', null) === null) {
            $this->settings->set('telegram.bot_token', $botToken, (int) $actor['id']);
        }

        $username = trim((string) ($botInfo['username'] ?? ''));
        if ($username !== '') {
            $this->settings->set('telegram.bot_username', $username, (int) $actor['id']);
        }

        $this->audit->record((int) $actor['id'], 'settings.telegram_credentials_updated', 'app_settings', null, [
            'client_id_changed' => true,
            'client_secret_changed' => $clientSecretInput !== '',
            'bot_token_changed' => $botTokenInput !== '',
            'redirect_uri' => $redirectUri,
        ], $this->ip($request));

        return Response::redirect('/admin/settings?probe_telegram=1&message=' . rawurlencode('Telegram 연결 정보를 저장하고 Bot 연결을 확인했습니다.'));
    }

    public function saveTelegram(Request $request): Response
    {
        $actor = $this->requireActor();
        $raw = trim((string) $request->input('admin_chat_ids', ''));
        $ids = preg_split('/[\r\n,]+/', $raw) ?: [];
        $normalized = [];

        foreach ($ids as $id) {
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            if (preg_match('/^-?\d+$/', $id) !== 1) {
                return $this->error('Telegram Chat ID는 숫자로 입력해 주세요.');
            }
            $normalized[$id] = $id;
        }

        $value = implode("\n", array_values($normalized));
        $this->settings->set('telegram.admin_chat_ids', $value, (int) $actor['id']);
        $this->config->set('telegram.admin_chat_ids', array_values($normalized));

        $this->audit->record((int) $actor['id'], 'settings.telegram_chats_updated', 'app_settings', null, [
            'chat_ids' => array_values($normalized),
        ], $this->ip($request));

        return $this->message('Telegram 알림 대상을 저장했습니다.');
    }

    public function saveHolidayApi(Request $request): Response
    {
        $actor = $this->requireActor();
        $serviceKey = trim((string) $request->input('service_key', ''));

        if ($serviceKey === '') {
            return $this->error('공휴일 API ServiceKey를 입력해 주세요.');
        }

        $this->config->set('holiday_api.service_key', $serviceKey);

        try {
            $client = new KasiHolidayClient($this->config);
            $count = count($client->fetchYear((int) date('Y')));
        } catch (Throwable $exception) {
            return $this->error('공휴일 API 연결 확인에 실패해 키를 저장하지 않았습니다: ' . $exception->getMessage());
        }

        $this->settings->set('holiday_api.service_key', $serviceKey, (int) $actor['id']);

        $this->audit->record((int) $actor['id'], 'settings.holiday_api_updated', 'app_settings', null, [
            'verified_year' => (int) date('Y'),
            'verified_count' => $count,
        ], $this->ip($request));

        return $this->message(sprintf('공휴일 API 키를 저장하고 현재 연도 %d건을 확인했습니다.', $count));
    }

    public function addTelegramChat(Request $request): Response
    {
        $actor = $this->requireActor();
        $chatId = trim((string) $request->input('chat_id', ''));

        if (preg_match('/^-?\d+$/', $chatId) !== 1) {
            return $this->error('추가할 Telegram Chat ID를 확인해 주세요.');
        }

        $ids = $this->settings->get('telegram.admin_chat_ids', null) !== null
            ? $this->settings->lineList('telegram.admin_chat_ids')
            : $this->configuredAdminChats();
        $ids[] = $chatId;
        $ids = array_values(array_unique($ids));
        $this->settings->set('telegram.admin_chat_ids', implode("\n", $ids), (int) $actor['id']);
        $this->config->set('telegram.admin_chat_ids', $ids);

        $this->audit->record((int) $actor['id'], 'settings.telegram_chat_added', 'app_settings', null, [
            'chat_id' => $chatId,
        ], $this->ip($request));

        return Response::redirect('/admin/settings?probe_telegram=1&message=' . rawurlencode('Telegram 알림 대상을 추가했습니다.'));
    }

    public function testTelegram(Request $request): Response
    {
        $actor = $this->requireActor();
        $targets = $this->settings->get('telegram.admin_chat_ids', null) !== null
            ? $this->settings->lineList('telegram.admin_chat_ids')
            : $this->configuredAdminChats();

        if ($targets === []) {
            return $this->error('테스트할 Telegram 알림 대상이 없습니다.');
        }

        $sent = 0;
        foreach ($targets as $chatId) {
            try {
                $this->telegramBot->sendMessage(
                    $chatId,
                    '[휴가관리 설정 테스트]' . "\n" . '관리자 알림 연결이 정상입니다.',
                );
                $sent++;
            } catch (Throwable) {
                // Continue testing remaining targets.
            }
        }

        $this->audit->record((int) $actor['id'], 'settings.telegram_tested', 'app_settings', null, [
            'target_count' => count($targets),
            'sent_count' => $sent,
        ], $this->ip($request));

        if ($sent === 0) {
            return $this->error('Telegram 테스트 메시지를 전송하지 못했습니다. Bot 권한과 Chat ID를 확인해 주세요.');
        }

        return $this->message(sprintf('Telegram 테스트 메시지 %d/%d건을 전송했습니다.', $sent, count($targets)));
    }

    public function uploadLogo(Request $request): Response
    {
        $actor = $this->requireActor();
        $file = $request->file('company_logo');

        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->error('업로드할 로고 파일을 선택해 주세요.');
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $this->error('로고 업로드 중 오류가 발생했습니다.');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return $this->error('로고는 2MB 이하 파일만 사용할 수 있습니다.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $imageInfo = $tmpName !== '' ? @getimagesize($tmpName) : false;
        $mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';

        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            return $this->error('로고는 PNG, JPG, WEBP 형식만 지원합니다.');
        }

        $directory = $this->publicPath . '/uploads/branding';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('브랜딩 업로드 폴더를 만들 수 없습니다.');
        }

        foreach (glob($directory . '/company-logo.*') ?: [] as $existing) {
            @unlink($existing);
        }

        $filename = 'company-logo.' . $extensions[$mime];
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('로고 파일을 저장하지 못했습니다.');
        }

        $publicUrl = '/uploads/branding/' . $filename;
        $this->settings->set('ui.logo_path', $publicUrl, (int) $actor['id']);

        $this->audit->record((int) $actor['id'], 'settings.logo_updated', 'app_settings', null, [
            'logo_path' => $publicUrl,
        ], $this->ip($request));

        return $this->message('회사 로고를 변경했습니다.');
    }

    public function removeLogo(Request $request): Response
    {
        $actor = $this->requireActor();
        $directory = $this->publicPath . '/uploads/branding';

        foreach (glob($directory . '/company-logo.*') ?: [] as $existing) {
            @unlink($existing);
        }

        $this->settings->delete('ui.logo_path');
        $this->audit->record((int) $actor['id'], 'settings.logo_removed', 'app_settings', null, [], $this->ip($request));

        return $this->message('회사 로고를 기본 아이콘으로 되돌렸습니다.');
    }

    /** @return list<string> */
    private function configuredAdminChats(): array
    {
        $configured = $this->config->get('telegram.admin_chat_ids', []);
        if (!is_array($configured)) {
            return [];
        }

        $result = [];
        foreach ($configured as $chatId) {
            $value = trim((string) $chatId);
            if ($value !== '') {
                $result[$value] = $value;
            }
        }

        return array_values($result);
    }

    /** @return array<string, mixed> */
    private function requireActor(): array
    {
        $actor = $this->auth->user();
        if ($actor === null) {
            throw new RuntimeException('Authenticated administrator is required.');
        }

        return $actor;
    }

    private function message(string $message): Response
    {
        return Response::redirect('/admin/settings?message=' . rawurlencode($message));
    }

    private function error(string $message): Response
    {
        return Response::redirect('/admin/settings?error=' . rawurlencode($message));
    }

    private function stringLength(string $value): int
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($characters) ? count($characters) : strlen($value);
    }

    private function ip(Request $request): ?string
    {
        $ip = trim((string) $request->server('REMOTE_ADDR', ''));
        return $ip !== '' ? $ip : null;
    }
}
