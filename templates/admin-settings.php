<?php
/** @var string $appName */
/** @var string $primaryColor */
/** @var string $theme */
/** @var string $logoPath */
/** @var list<string> $telegramAdminChats */
/** @var array<string, mixed>|null $telegramBotInfo */
/** @var list<array{id:string,title:string,type:string}> $telegramChats */
/** @var string|null $telegramProbeError */
/** @var array<string, bool> $credentialStatus */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Settings</p>
        <h1>환경 설정</h1>
        <p>브랜딩, 화면 테마와 Telegram 알림 대상을 관리자 화면에서 관리합니다.</p>
    </div>
    <a class="button" href="/admin"><i class="bi bi-speedometer2"></i> 대시보드</a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="settings-grid">
    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Branding</p>
                <h2><i class="bi bi-palette2"></i> 화면·브랜딩</h2>
            </div>
        </div>

        <form class="form-grid" method="post" action="/admin/settings/appearance">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label class="span-2">
                회사 / 프로그램 이름
                <input name="app_name" maxlength="80" required value="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                대표색
                <div class="color-input-row">
                    <input type="color" name="primary_color" value="<?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>">
                    <code><?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?></code>
                </div>
            </label>
            <label>
                기본 테마
                <select name="theme">
                    <option value="system" <?= $theme === 'system' ? 'selected' : '' ?>>시스템 설정 따름</option>
                    <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>라이트</option>
                    <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>다크</option>
                </select>
            </label>
            <div class="form-actions">
                <button class="button primary" type="submit"><i class="bi bi-check2-circle"></i> 화면 설정 저장</button>
            </div>
        </form>

        <div class="settings-divider"></div>

        <div class="logo-setting">
            <div class="logo-preview">
                <?php if ($logoPath !== ''): ?>
                    <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="현재 회사 로고">
                <?php else: ?>
                    <img src="/assets/app-icon.svg" alt="기본 앱 아이콘">
                <?php endif; ?>
            </div>
            <div class="logo-setting-body">
                <h3>좌상단 회사 로고</h3>
                <p>PNG, JPG, WEBP · 최대 2MB. 업로드하지 않으면 기본 캘린더 아이콘을 사용합니다.</p>
                <form class="upload-row" method="post" action="/admin/settings/logo" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="file" name="company_logo" accept="image/png,image/jpeg,image/webp" required>
                    <button class="button" type="submit"><i class="bi bi-upload"></i> 업로드</button>
                </form>
                <?php if ($logoPath !== ''): ?>
                    <form method="post" action="/admin/settings/logo/remove">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="button danger-ghost small" type="submit"><i class="bi bi-arrow-counterclockwise"></i> 기본 아이콘으로 복원</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Telegram</p>
                <h2><i class="bi bi-telegram"></i> Telegram 알림</h2>
            </div>
            <a class="button small" href="/admin/settings?probe_telegram=1"><i class="bi bi-arrow-repeat"></i> 봇·채팅 자동 확인</a>
        </div>

        <div class="credential-status-grid">
            <div class="credential-item">
                <i class="bi <?= $credentialStatus['client_id'] ? 'bi-check-circle-fill ok' : 'bi-exclamation-circle-fill bad' ?>"></i>
                <span>Client ID</span>
            </div>
            <div class="credential-item">
                <i class="bi <?= $credentialStatus['client_secret'] ? 'bi-check-circle-fill ok' : 'bi-exclamation-circle-fill bad' ?>"></i>
                <span>Client Secret</span>
            </div>
            <div class="credential-item">
                <i class="bi <?= $credentialStatus['bot_token'] ? 'bi-check-circle-fill ok' : 'bi-exclamation-circle-fill bad' ?>"></i>
                <span>Bot Token</span>
            </div>
            <div class="credential-item">
                <i class="bi <?= $credentialStatus['holiday_key'] ? 'bi-check-circle-fill ok' : 'bi-exclamation-circle-fill bad' ?>"></i>
                <span>공휴일 API</span>
            </div>
        </div>

        <form class="form-grid" method="post" action="/admin/settings/telegram">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label class="span-2">
                관리자 알림 Chat ID
                <textarea name="admin_chat_ids" rows="5" placeholder="-1001234567890"><?= htmlspecialchars(implode("\n", $telegramAdminChats), ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
            <p class="form-hint span-2">한 줄에 하나씩 입력합니다. 그룹/채널 Chat ID는 보통 음수이며, 저장한 값이 config 파일의 알림 대상보다 우선합니다.</p>
            <div class="form-actions">
                <button class="button primary" type="submit"><i class="bi bi-bell-fill"></i> 알림 대상 저장</button>
            </div>
        </form>

        <?php if ($telegramProbeError !== null): ?>
            <div class="notice warning settings-notice"><?= htmlspecialchars($telegramProbeError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($telegramBotInfo !== null): ?>
            <div class="telegram-bot-card">
                <div class="telegram-bot-icon"><i class="bi bi-robot"></i></div>
                <div>
                    <strong><?= htmlspecialchars((string) ($telegramBotInfo['first_name'] ?? 'Telegram Bot'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (!empty($telegramBotInfo['username'])): ?>
                        <span>@<?= htmlspecialchars((string) $telegramBotInfo['username'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($telegramBotInfo['username'])): ?>
                    <a class="button small" href="https://t.me/<?= rawurlencode((string) $telegramBotInfo['username']) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right"></i> Telegram 열기
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($telegramChats !== []): ?>
            <div class="section-head sub-section-head">
                <div>
                    <h3>최근 확인된 채팅</h3>
                    <p>봇에 최근 메시지가 도착한 채팅을 자동으로 찾았습니다.</p>
                </div>
            </div>
            <div class="detected-chat-list">
                <?php foreach ($telegramChats as $chat): ?>
                    <div class="detected-chat">
                        <div>
                            <strong><?= htmlspecialchars($chat['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars($chat['type'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($chat['id'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <?php if (!in_array($chat['id'], $telegramAdminChats, true)): ?>
                            <form method="post" action="/admin/settings/telegram/add-chat">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="chat_id" value="<?= htmlspecialchars($chat['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <button class="button small" type="submit"><i class="bi bi-plus-lg"></i> 알림 대상 추가</button>
                            </form>
                        <?php else: ?>
                            <span class="badge approved"><i class="bi bi-check2"></i> 등록됨</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="settings-help">
            <a href="https://t.me/BotFather" target="_blank" rel="noopener"><i class="bi bi-telegram"></i> BotFather 열기</a>
            <a href="https://www.data.go.kr/" target="_blank" rel="noopener"><i class="bi bi-building"></i> 공공데이터포털 열기</a>
        </div>

        <p class="form-hint security-note"><i class="bi bi-shield-lock"></i> Client Secret, Bot Token, 공휴일 ServiceKey 같은 비밀값은 보안을 위해 서버 설정 파일에 유지하며 이 화면에는 값 자체를 노출하지 않습니다.</p>
    </section>
</div>
