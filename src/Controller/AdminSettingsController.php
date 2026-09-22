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

        $managedCompanyChat = trim((string) $this->settings->get('telegram.company_chat_id', ''));
        $companyChatId = $managedCompanyChat !== ''
            ? $managedCompanyChat
            : $this->configuredCompanyChatId();

        $botUsername = trim((string) $this->settings->get('telegram.bot_username', ''));
        if ($telegramBotInfo !== null && !empty($telegramBotInfo['username'])) {
            $botUsername = (string) $telegramBotInfo['username'];
        }

        $appUrl = $this->detectedOrigin($request);
        $redirectUri = (string) $this->settings->get(
            'telegram.redirect_uri',
            $appUrl !== '' ? $appUrl . '/auth/telegram/callback' : (string) $this->config->get('telegram.redirect_uri', ''),
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
            'companyChatId' => $companyChatId,
            'companyChatManaged' => $managedCompanyChat !== '',
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
            error_log('[DK Annual Settings] Telegram verification failed: ' . $exception::class);
            return $this->error('Telegram Bot 연결 확인에 실패했습니다. Client ID/Secret, Bot Token, Allowed URL 설정을 확인해 주세요.');
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
        $chatId = trim((string) $request->input('company_chat_id', ''));

        if ($chatId === '' || preg_match('/^-?\d+$/', $chatId) !== 1) {
            return $this->error('회사 공용 Telegram 그룹 Chat ID를 확인해 주세요.');
        }

        $this->settings->set('telegram.company_chat_id', $chatId, (int) $actor['id']);
        $this->settings->delete('telegram.admin_chat_ids');
        $this->config->set('telegram.company_chat_id', $chatId);

        $this->audit->record((int) $actor['id'], 'settings.telegram_company_chat_updated', 'app_settings', null, [
            'company_chat_id' => $chatId,
        ], $this->ip($request));

        return $this->message('회사 공용 Telegram 그룹을 저장했습니다.');
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
            error_log('[DK Annual Settings] Holiday API verification failed: ' . $exception::class);
            return $this->error('공휴일 API 연결 확인에 실패했습니다. ServiceKey 승인 상태와 값을 확인해 주세요.');
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

        if ($chatId === '' || preg_match('/^-?\d+$/', $chatId) !== 1) {
            return $this->error('회사 공용 Telegram 그룹 Chat ID를 확인해 주세요.');
        }

        $this->settings->set('telegram.company_chat_id', $chatId, (int) $actor['id']);
        $this->settings->delete('telegram.admin_chat_ids');
        $this->config->set('telegram.company_chat_id', $chatId);

        $this->audit->record((int) $actor['id'], 'settings.telegram_company_chat_selected', 'app_settings', null, [
            'company_chat_id' => $chatId,
        ], $this->ip($request));

        return Response::redirect('/admin/settings?probe_telegram=1&message=' . rawurlencode('회사 공용 Telegram 그룹을 선택했습니다.'));
    }

    public function testTelegram(Request $request): Response
    {
        $actor = $this->requireActor();
        $chatId = trim((string) $this->settings->get('telegram.company_chat_id', ''));
        if ($chatId === '') {
            $chatId = $this->configuredCompanyChatId();
        }

        if ($chatId === '') {
            return $this->error('테스트할 회사 공용 Telegram 그룹이 설정되지 않았습니다.');
        }

        try {
            $this->telegramBot->sendMessage(
                $chatId,
                '[휴가관리 설정 테스트]' . "\n" . '회사 공용 그룹 연결이 정상입니다.',
            );
        } catch (Throwable) {
            return $this->error('회사 공용 그룹으로 테스트 메시지를 전송하지 못했습니다. Bot 권한과 Chat ID를 확인해 주세요.');
        }

        $this->audit->record((int) $actor['id'], 'settings.telegram_company_chat_tested', 'app_settings', null, [
            'company_chat_id' => $chatId,
        ], $this->ip($request));

        return $this->message('회사 공용 Telegram 그룹으로 테스트 메시지를 전송했습니다.');
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

    private function detectedOrigin(Request $request): string
    {
        $forwardedProto = strtolower(trim(explode(',', (string) $request->server('HTTP_X_FORWARDED_PROTO', ''))[0] ?? ''));
        $scheme = in_array($forwardedProto, ['http', 'https'], true)
            ? $forwardedProto
            : (((string) $request->server('HTTPS', '') !== '' && (string) $request->server('HTTPS', '') !== 'off') ? 'https' : 'http');

        $forwardedHost = trim(explode(',', (string) $request->server('HTTP_X_FORWARDED_HOST', ''))[0] ?? '');
        $host = $forwardedHost !== '' ? $forwardedHost : trim((string) $request->server('HTTP_HOST', ''));

        if ($host !== '' && preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host) === 1) {
            return $scheme . '://' . $host;
        }

        $configured = rtrim((string) $this->config->get('app.url', ''), '/');
        return filter_var($configured, FILTER_VALIDATE_URL) !== false ? $configured : '';
    }

    private function configuredCompanyChatId(): string
    {
        $configured = trim((string) $this->config->get('telegram.company_chat_id', ''));
        return preg_match('/^-?\d+$/', $configured) === 1 ? $configured : '';
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
