<?php
/** @var string $appName */
/** @var string $primaryColor */
/** @var string $theme */
/** @var string $logoPath */
/** @var string $companyChatId */
/** @var bool $companyChatManaged */
/** @var array<string, mixed>|null $telegramBotInfo */
/** @var list<array{id:string,title:string,type:string}> $telegramChats */
/** @var string|null $telegramProbeError */
/** @var string $telegramClientId */
/** @var string $telegramRedirectUri */
/** @var array<string, bool> $credentialStatus */
/** @var string|null $employeeBotLink */
/** @var string|null $employeeLoginLink */
/** @var list<int> $workingWeekdays */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Settings</p>
        <h1><i class="bi bi-gear-wide-connected"></i> 환경 설정</h1>
        <p>브랜딩, Telegram 연결, 직원 초대와 공휴일 API를 한 곳에서 관리합니다.</p>
    </div>
    <a class="button" href="/admin"><i class="bi bi-speedometer2"></i><span>대시보드</span></a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><i class="bi bi-check-circle"></i><span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><i class="bi bi-x-circle"></i><span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>

<div class="settings-page-grid">
    <section class="panel settings-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Branding</p>
                <h2><i class="bi bi-palette2"></i><span>브랜딩 · 테마</span></h2>
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
                <button class="button primary" type="submit"><i class="bi bi-check2-circle"></i><span>화면 설정 저장</span></button>
            </div>
        </form>

        <div class="settings-divider"></div>

        <div class="logo-upload-card">
            <div class="logo-preview-large">
                <?php if ($logoPath !== ''): ?>
                    <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="현재 회사 로고">
                <?php else: ?>
                    <img src="/assets/app-icon.svg" alt="기본 앱 아이콘">
                <?php endif; ?>
            </div>
            <div class="logo-upload-content">
                <div>
                    <h3>좌상단 회사 로고</h3>
                    <p>이미지 비율과 관계없이 헤더 높이에 맞춰 표시하며 가로 비율은 유지합니다.</p>
                </div>
                <form class="logo-upload-form" method="post" action="/admin/settings/logo" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label class="file-picker">
                        <i class="bi bi-image"></i>
                        <span>PNG / JPG / WEBP · 최대 2MB</span>
                        <input type="file" name="company_logo" accept="image/png,image/jpeg,image/webp" required>
                    </label>
                    <button class="button primary" type="submit"><i class="bi bi-upload"></i><span>로고 업로드</span></button>
                </form>
                <?php if ($logoPath !== ''): ?>
                    <form method="post" action="/admin/settings/logo/remove">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="button danger-ghost small" type="submit"><i class="bi bi-arrow-counterclockwise"></i><span>기본 아이콘으로 복원</span></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel settings-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Work schedule</p>
                <h2><i class="bi bi-calendar-week"></i><span>주 근무 요일</span></h2>
            </div>
        </div>
        <p>실제 근무 요일을 선택합니다. 선택하지 않은 요일은 달력에서 비근무일로 강조되고 휴가 일수 계산에서도 제외됩니다.</p>
        <form class="settings-subsection" method="post" action="/admin/settings/workweek">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="weekday-checkbox-grid">
                <?php foreach ([1 => '월', 2 => '화', 3 => '수', 4 => '목', 5 => '금', 6 => '토', 7 => '일'] as $dayNumber => $dayLabel): ?>
                    <label class="weekday-checkbox">
                        <input
                            type="checkbox"
                            name="working_weekdays[]"
                            value="<?= $dayNumber ?>"
                            <?= in_array($dayNumber, $workingWeekdays, true) ? 'checked' : '' ?>
                        >
                        <span><?= $dayLabel ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="form-hint">기본값은 월~금입니다. 최소 1개 요일은 선택해야 합니다.</p>
            <div class="form-actions settings-subsection">
                <button class="button primary" type="submit"><i class="bi bi-check2-circle"></i><span>근무 요일 저장</span></button>
            </div>
        </form>
    </section>

    <section class="panel settings-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Telegram</p>
                <h2><i class="bi bi-telegram"></i><span>Telegram 연결</span></h2>
            </div>
            <a class="button small" href="/admin/settings?probe_telegram=1"><i class="bi bi-arrow-repeat"></i><span>봇·채팅 자동 확인</span></a>
        </div>

        <div class="credential-status-grid">
            <?php foreach ([
                'client_id' => 'Client ID',
                'client_secret' => 'Client Secret',
                'bot_token' => 'Bot Token',
                'holiday_key' => '공휴일 API',
            ] as $key => $label): ?>
                <div class="credential-item">
                    <i class="bi <?= $credentialStatus[$key] ? 'bi-check-circle-fill ok' : 'bi-exclamation-circle-fill bad' ?>"></i>
                    <span><?= $label ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <form class="form-grid" method="post" action="/admin/settings/telegram-credentials">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label class="span-2">
                Client ID
                <input name="client_id" required value="<?= htmlspecialchars($telegramClientId, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                Client Secret
                <input type="password" name="client_secret" autocomplete="new-password" placeholder="<?= $credentialStatus['client_secret'] ? '저장됨 · 변경할 때만 입력' : '필수 입력' ?>">
            </label>
            <label>
                Bot Token
                <input type="password" name="bot_token" autocomplete="new-password" placeholder="<?= $credentialStatus['bot_token'] ? '저장됨 · 변경할 때만 입력' : '필수 입력' ?>">
            </label>
            <label class="span-2">
                Redirect URI
                <input name="redirect_uri" required value="<?= htmlspecialchars($telegramRedirectUri, ENT_QUOTES, 'UTF-8') ?>">
                <span class="form-hint">현재 접속 호스트를 기준으로 자동 계산합니다. 리버스 프록시 외부 주소와 다를 때만 수정하세요.</span>
            </label>
            <div class="form-actions">
                <button class="button primary" type="submit"><i class="bi bi-plug"></i><span>Telegram 연결 저장·확인</span></button>
                <a class="button" href="https://t.me/BotFather" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i><span>BotFather</span></a>
            </div>
        </form>

        <?php if ($telegramProbeError !== null): ?>
            <div class="notice warning settings-notice"><i class="bi bi-exclamation-triangle"></i><span><?= htmlspecialchars($telegramProbeError, ENT_QUOTES, 'UTF-8') ?></span></div>
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
                <span class="badge approved">연결됨</span>
            </div>
        <?php endif; ?>

        <div class="settings-divider"></div>

        <div class="settings-role-note">
            <div><i class="bi bi-person-lock"></i><strong>관리자 개인 알림</strong><span>휴가 신청 사유, 잔여 연차 경고 등 관리 정보는 활성 관리자에게 개인 Telegram 메시지로 전송합니다.</span></div>
            <div><i class="bi bi-people"></i><strong>회사 공용 그룹</strong><span>관리자와 직원이 함께 보는 그룹이며 승인된 휴가 일정 등록/취소처럼 공개 가능한 일정 정보만 전송합니다.</span></div>
        </div>

        <form class="form-grid settings-subsection" method="post" action="/admin/settings/telegram">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label class="span-2">
                회사 공용 Telegram 그룹 Chat ID
                <input name="company_chat_id" required placeholder="-1001234567890" value="<?= htmlspecialchars($companyChatId, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <p class="form-hint span-2"><?= $companyChatManaged ? '관리자 화면에서 저장한 회사 공용 그룹을 사용 중입니다.' : '아직 DB 관리값이 없으면 config의 company_chat_id를 기본값으로 사용합니다.' ?></p>
            <div class="form-actions">
                <button class="button primary" type="submit"><i class="bi bi-check2-circle"></i><span>회사 그룹 저장</span></button>
                <button class="button" type="submit" formaction="/admin/settings/telegram/test"><i class="bi bi-send-check"></i><span>회사 그룹 테스트</span></button>
            </div>
        </form>

        <?php if ($telegramChats !== []): ?>
            <div class="detected-chat-list settings-subsection">
                <h3>최근 확인된 그룹</h3>
                <?php foreach ($telegramChats as $chat): ?>
                    <?php if (!in_array($chat['type'], ['group', 'supergroup'], true)) { continue; } ?>
                    <div class="detected-chat">
                        <div>
                            <strong><?= htmlspecialchars($chat['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars($chat['type'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($chat['id'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <?php if ($chat['id'] !== $companyChatId): ?>
                            <form method="post" action="/admin/settings/telegram/add-chat">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="chat_id" value="<?= htmlspecialchars($chat['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <button class="button small" type="submit"><i class="bi bi-building-check"></i><span>회사 그룹으로 사용</span></button>
                            </form>
                        <?php else: ?>
                            <span class="badge approved"><i class="bi bi-check2"></i><span>회사 그룹</span></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel settings-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Employee invite</p>
                <h2><i class="bi bi-person-plus"></i><span>직원 초대</span></h2>
            </div>
        </div>
        <p>직원에게 아래 링크를 전달하면 Telegram 봇 또는 서비스 로그인 페이지로 바로 안내할 수 있습니다.</p>
        <div class="invite-link-grid">
            <?php if ($employeeBotLink !== null): ?>
                <div class="invite-link-card">
                    <span><i class="bi bi-telegram"></i> Telegram 봇 링크</span>
                    <code><?= htmlspecialchars($employeeBotLink, ENT_QUOTES, 'UTF-8') ?></code>
                    <div class="invite-actions">
                        <button class="button small" type="button" data-copy-text="<?= htmlspecialchars($employeeBotLink, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-copy"></i><span>복사</span></button>
                        <a class="button small" href="<?= htmlspecialchars($employeeBotLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i><span>열기</span></a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($employeeLoginLink !== null): ?>
                <div class="invite-link-card">
                    <span><i class="bi bi-box-arrow-in-right"></i> 서비스 로그인 링크</span>
                    <code><?= htmlspecialchars($employeeLoginLink, ENT_QUOTES, 'UTF-8') ?></code>
                    <div class="invite-actions">
                        <button class="button small" type="button" data-copy-text="<?= htmlspecialchars($employeeLoginLink, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-copy"></i><span>복사</span></button>
                        <a class="button small" href="<?= htmlspecialchars($employeeLoginLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i><span>열기</span></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($employeeBotLink === null): ?>
            <p class="form-hint"><i class="bi bi-info-circle"></i> Telegram 봇 링크가 없으면 위에서 <strong>봇·채팅 자동 확인</strong>을 한 번 실행하세요.</p>
        <?php endif; ?>
    </section>

    <section class="panel settings-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Public holidays</p>
                <h2><i class="bi bi-calendar2-event"></i><span>공휴일 API</span></h2>
            </div>
            <a class="button small" href="https://www.data.go.kr/data/15012690/openapi.do" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i><span>API 페이지</span></a>
        </div>
        <p>한국천문연구원 특일 정보 ServiceKey를 관리자 화면에서 변경할 수 있습니다. 기존 키는 화면에 다시 노출하지 않습니다.</p>
        <form class="form-grid settings-subsection" method="post" action="/admin/settings/holiday-api">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label class="span-2">
                ServiceKey
                <input type="password" name="service_key" autocomplete="new-password" placeholder="<?= $credentialStatus['holiday_key'] ? '저장됨 · 변경할 때만 새 키 입력' : '공공데이터포털 일반 인증키' ?>" required>
            </label>
            <div class="form-actions">
                <button class="button primary" type="submit"><i class="bi bi-cloud-check"></i><span>저장·연결 확인</span></button>
                <a class="button" href="/admin/holidays"><i class="bi bi-arrow-repeat"></i><span>공휴일 관리·동기화</span></a>
            </div>
        </form>
    </section>
    <section class="panel settings-card danger-zone-card">
        <div class="section-head">
            <div>
                <p class="eyebrow">Reinstall</p>
                <h2><i class="bi bi-arrow-repeat"></i><span>재설치 · 완전 초기화</span></h2>
            </div>
        </div>
        <div class="danger-zone-note">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>모든 휴가관리 데이터를 삭제합니다.</strong>
                <span>직원, 휴가 신청, 연차 원장, 공휴일, 관리자 설정, 업로드 로고가 삭제되고 초기 설치 마법사부터 다시 시작합니다. DB 접속용 config와 소스코드는 유지됩니다.</span>
            </div>
        </div>
        <form class="form-grid settings-subsection" method="post" action="/admin/settings/reset-install" onsubmit="return confirm('모든 휴가관리 데이터를 삭제하고 재설치를 시작하시겠습니까? 이 작업은 되돌릴 수 없습니다.');">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label class="span-2">
                확인을 위해 RESET 입력
                <input name="confirmation" autocomplete="off" placeholder="RESET" required>
            </label>
            <div class="form-actions">
                <button class="button danger" type="submit"><i class="bi bi-trash3"></i><span>완전 초기화 후 재설치</span></button>
            </div>
        </form>
    </section>
</div>

<p class="settings-security-note"><i class="bi bi-shield-lock"></i><span>Secret/Token/API Key는 관리자 화면에서 변경할 수 있지만 기존 값은 다시 표시하지 않으며 감사 로그에도 실제 값은 기록하지 않습니다.</span></p>
