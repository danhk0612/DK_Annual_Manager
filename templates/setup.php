<?php
/** @var array<string, bool> $status */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
/** @var string|null $probeError */
/** @var array<string, mixed>|null $telegramBotInfo */
/** @var list<array{id:string,title:string,type:string}> $telegramChats */
/** @var int $adminCount */
/** @var string $appUrl */
/** @var string $allowedOrigin */
/** @var string $redirectUri */
/** @var string $configuredClientId */
/** @var bool $hasClientSecret */
/** @var bool $hasBotToken */
/** @var list<string> $adminChats */
/** @var list<string> $bootstrapAdminIds */
/** @var bool $hasHolidayKey */
/** @var string|null $employeeBotLink */
/** @var string|null $employeeLoginLink */

$stepStates = [
    1 => $status['schema'],
    2 => $status['telegram'],
    3 => $status['chat'] && $status['admin'],
    4 => $adminCount > 0,
    5 => $status['holiday'],
];
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#315efb">
    <title>초기 서비스 설정 · DK Annual Manager</title>
    <link rel="icon" type="image/svg+xml" href="/assets/app-icon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="setup-body">
<main class="setup-shell">
    <section class="setup-hero">
        <div class="setup-brand">
            <img src="/assets/app-icon.svg" alt="">
            <div>
                <p class="eyebrow">First-run setup</p>
                <h1>휴가관리 서비스 초기 설정</h1>
                <p>DB 초기화부터 Telegram, 최초 관리자, 공휴일 API까지 순서대로 연결합니다.</p>
            </div>
        </div>
        <div class="setup-progress">
            <?php foreach ($stepStates as $number => $done): ?>
                <div class="setup-progress-item <?= $done ? 'done' : '' ?>">
                    <span><?= $done ? '<i class="bi bi-check-lg"></i>' : $number ?></span>
                    <strong><?= ['DB', 'Telegram', '알림/관리자', '관리자 로그인', '공휴일'][$number - 1] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (is_string($message) && $message !== ''): ?>
        <div class="notice success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (is_string($error) && $error !== ''): ?>
        <div class="notice error"><i class="bi bi-x-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="setup-step <?= $status['schema'] ? 'complete' : 'current' ?>">
        <div class="setup-step-number"><?= $status['schema'] ? '<i class="bi bi-check-lg"></i>' : '1' ?></div>
        <div class="setup-step-main">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Step 1</p>
                    <h2><i class="bi bi-database"></i> DB 초기화</h2>
                </div>
                <span class="badge <?= $status['schema'] ? 'approved' : 'pending' ?>"><?= $status['schema'] ? '완료' : '필요' ?></span>
            </div>
            <p>현재 config의 DB 연결 정보를 사용해 최신 schema를 직접 생성합니다. 별도 migration은 사용하지 않습니다.</p>
            <?php if (!$status['schema']): ?>
                <form method="post" action="/setup/database" class="setup-action-row">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="button primary" type="submit"><i class="bi bi-database-add"></i> 최신 DB schema 생성</button>
                </form>
            <?php else: ?>
                <div class="setup-complete-line"><i class="bi bi-check-circle-fill"></i> 필수 8개 테이블이 준비되었습니다.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="setup-step <?= $status['telegram'] ? 'complete' : ($status['schema'] ? 'current' : 'locked') ?>">
        <div class="setup-step-number"><?= $status['telegram'] ? '<i class="bi bi-check-lg"></i>' : '2' ?></div>
        <div class="setup-step-main">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Step 2</p>
                    <h2><i class="bi bi-telegram"></i> Telegram 봇 · 로그인 연결</h2>
                </div>
                <span class="badge <?= $status['telegram'] ? 'approved' : 'pending' ?>"><?= $status['telegram'] ? '저장됨' : '설정 필요' ?></span>
            </div>
            <p>Bot Token과 Login용 Client ID/Secret의 발급 자체는 Telegram에서 진행해야 합니다. 이 화면은 필요한 주소를 현재 접속 호스트에서 자동 계산하고, 입력값을 즉시 검증합니다.</p>
            <div class="setup-mini-steps">
                <span><b>1</b> BotFather → <code>/newbot</code>으로 Bot 생성 · Bot Token 복사</span>
                <span><b>2</b> Bot 선택 → <strong>Login Widget</strong> 열기</span>
                <span><b>3</b> 아래 Allowed Origin / Redirect URI 등록</span>
                <span><b>4</b> 같은 화면의 Client ID / Client Secret 복사</span>
            </div>
            <div class="setup-links">
                <a class="button primary" href="https://t.me/BotFather" target="_blank" rel="noopener"><i class="bi bi-telegram"></i><span>BotFather 열기</span></a>
                <a class="button" href="https://core.telegram.org/bots/telegram-login" target="_blank" rel="noopener"><i class="bi bi-book"></i><span>Telegram 공식 Login 안내</span></a>
            </div>

            <div class="setup-address-grid">
                <div class="setup-address-card">
                    <span>Allowed Origin · 자동 감지</span>
                    <code><?= htmlspecialchars($allowedOrigin, ENT_QUOTES, 'UTF-8') ?></code>
                    <button class="button small" type="button" data-copy-text="<?= htmlspecialchars($allowedOrigin, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-copy"></i><span>복사</span></button>
                </div>
                <div class="setup-address-card">
                    <span>Redirect URI · 자동 감지</span>
                    <code><?= htmlspecialchars($redirectUri, ENT_QUOTES, 'UTF-8') ?></code>
                    <button class="button small" type="button" data-copy-text="<?= htmlspecialchars($redirectUri, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-copy"></i><span>복사</span></button>
                </div>
            </div>

            <?php if ($status['schema']): ?>
                <form class="form-grid setup-form" method="post" action="/setup/telegram">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label class="span-2">
                        Client ID
                        <input name="client_id" required value="<?= htmlspecialchars($configuredClientId, ENT_QUOTES, 'UTF-8') ?>" placeholder="BotFather에서 발급">
                    </label>
                    <label>
                        Client Secret
                        <input type="password" name="client_secret" autocomplete="new-password" placeholder="<?= $hasClientSecret ? '저장됨 · 변경할 때만 입력' : '필수 입력' ?>">
                    </label>
                    <label>
                        Bot Token
                        <input type="password" name="bot_token" autocomplete="new-password" placeholder="<?= $hasBotToken ? '저장됨 · 변경할 때만 입력' : '필수 입력' ?>">
                    </label>
                    <label class="span-2">
                        Redirect URI
                        <input name="redirect_uri" required value="<?= htmlspecialchars($redirectUri, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="form-hint">현재 접속 주소에서 자동 계산했습니다. 리버스 프록시 주소와 다를 때만 수정하세요.</span>
                    </label>
                    <div class="form-actions">
                        <button class="button primary" type="submit"><i class="bi bi-plug"></i> 저장하고 봇 연결 확인</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($telegramBotInfo !== null): ?>
                <div class="setup-bot-result">
                    <i class="bi bi-robot"></i>
                    <div>
                        <strong><?= htmlspecialchars((string) ($telegramBotInfo['first_name'] ?? 'Telegram Bot'), ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>@<?= htmlspecialchars((string) ($telegramBotInfo['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <span class="badge approved">연결 성공</span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="setup-step <?= ($status['chat'] && $status['admin']) ? 'complete' : ($status['telegram'] ? 'current' : 'locked') ?>">
        <div class="setup-step-number"><?= ($status['chat'] && $status['admin']) ? '<i class="bi bi-check-lg"></i>' : '3' ?></div>
        <div class="setup-step-main">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Step 3</p>
                    <h2><i class="bi bi-people"></i> 관리자 그룹 · 최초 관리자 연결</h2>
                </div>
                <a class="button small <?= !$status['telegram'] ? 'disabled-link' : '' ?>" href="<?= $status['telegram'] ? '/setup?probe_telegram=1' : '#' ?>"><i class="bi bi-arrow-repeat"></i> 최근 채팅 자동 확인</a>
            </div>
            <p>이 단계에서는 <strong>최초 관리자 개인 채팅</strong>과 <strong>관리자 알림 그룹</strong>을 각각 연결합니다. 둘 다 봇이 메시지를 한 번 받아야 자동 탐색할 수 있습니다.</p>

            <div class="setup-connection-grid">
                <article class="setup-connection-card">
                    <span class="setup-substep">3A</span>
                    <div>
                        <h3><i class="bi bi-person"></i> 최초 관리자 개인 채팅</h3>
                        <p>관리자 본인이 봇 개인 채팅을 열고 <code>/start</code>를 보냅니다. 이후 <strong>최근 채팅 자동 확인</strong>을 누르면 private 채팅의 User ID가 후보로 표시됩니다.</p>
                    </div>
                </article>
                <article class="setup-connection-card">
                    <span class="setup-substep">3B</span>
                    <div>
                        <h3><i class="bi bi-people"></i> 관리자 알림 그룹</h3>
                        <p>봇을 휴가 알림용 그룹에 추가한 뒤 그룹에서 메시지를 한 번 보냅니다. 자동 확인 후 group/supergroup Chat ID를 선택합니다.</p>
                    </div>
                </article>
            </div>

            <?php if ($probeError !== null): ?>
                <div class="notice warning"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($probeError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($status['telegram']): ?>
                <form class="form-grid setup-form" method="post" action="/setup/telegram-targets">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <label>
                        관리자 알림 그룹 Chat ID
                        <input
                            name="group_chat_id"
                            list="setup-group-chats"
                            required
                            value="<?= htmlspecialchars($adminChats[0] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="-1001234567890"
                        >
                        <datalist id="setup-group-chats">
                            <?php foreach ($telegramChats as $chat): ?>
                                <?php if (in_array($chat['type'], ['group', 'supergroup', 'channel'], true)): ?>
                                    <option value="<?= htmlspecialchars($chat['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($chat['title'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </datalist>
                    </label>

                    <label>
                        최초 관리자 Telegram User ID
                        <input
                            name="admin_telegram_id"
                            list="setup-private-chats"
                            required
                            value="<?= htmlspecialchars($bootstrapAdminIds[0] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="123456789"
                        >
                        <datalist id="setup-private-chats">
                            <?php foreach ($telegramChats as $chat): ?>
                                <?php if ($chat['type'] === 'private'): ?>
                                    <option value="<?= htmlspecialchars($chat['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($chat['title'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </datalist>
                    </label>

                    <div class="form-actions">
                        <button class="button primary" type="submit"><i class="bi bi-check2-circle"></i> 그룹·최초 관리자 저장</button>
                    </div>
                </form>

                <?php if ($telegramChats === []): ?>
                    <p class="setup-help"><i class="bi bi-info-circle"></i> 목록이 비어 있으면 Telegram에서 봇 개인 채팅과 관리자 그룹에 메시지를 보낸 뒤 <strong>최근 채팅 자동 확인</strong>을 다시 누르세요.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="setup-step <?= $adminCount > 0 ? 'complete' : (($status['chat'] && $status['admin']) ? 'current' : 'locked') ?>">
        <div class="setup-step-number"><?= $adminCount > 0 ? '<i class="bi bi-check-lg"></i>' : '4' ?></div>
        <div class="setup-step-main">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Step 4</p>
                    <h2><i class="bi bi-person-check"></i> 최초 관리자 로그인</h2>
                </div>
                <span class="badge <?= $adminCount > 0 ? 'approved' : 'pending' ?>"><?= $adminCount > 0 ? '관리자 연결 완료' : '로그인 필요' ?></span>
            </div>
            <p>Step 3A에서 확인한 Telegram User ID와 실제 Telegram 로그인 계정이 일치하면 해당 계정을 최초 관리자로 활성화합니다. 이후 일반 직원은 서비스 로그인 링크로 로그인하면 사용자 계정이 자동 연결됩니다.</p>
            <?php if ($status['chat'] && $status['admin'] && $adminCount === 0): ?>
                <a class="button primary" href="/auth/telegram/start"><i class="bi bi-telegram"></i> Telegram으로 관리자 연결</a>
            <?php elseif ($adminCount > 0): ?>
                <div class="setup-complete-line"><i class="bi bi-check-circle-fill"></i> 활성 관리자 <?= $adminCount ?>명이 연결되어 있습니다.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="setup-step <?= $status['holiday'] ? 'complete' : ($adminCount > 0 ? 'current' : 'locked') ?>">
        <div class="setup-step-number"><?= $status['holiday'] ? '<i class="bi bi-check-lg"></i>' : '5' ?></div>
        <div class="setup-step-main">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Step 5</p>
                    <h2><i class="bi bi-calendar2-event"></i> 한국 공휴일 API 연결</h2>
                </div>
                <span class="badge <?= $status['holiday'] ? 'approved' : 'pending' ?>"><?= $status['holiday'] ? '연결됨' : '설정 필요' ?></span>
            </div>
            <p>공공데이터포털에서 한국천문연구원 특일 정보 ServiceKey를 발급한 뒤 입력합니다. 저장과 동시에 현재 연도 공휴일을 동기화합니다.</p>
            <div class="setup-links">
                <a class="button" href="https://www.data.go.kr/data/15012690/openapi.do" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> 공휴일 API 페이지</a>
            </div>
            <?php if ($adminCount > 0): ?>
                <form class="form-grid setup-form" method="post" action="/setup/holiday">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label class="span-2">
                        ServiceKey
                        <input type="password" name="service_key" autocomplete="new-password" placeholder="<?= $hasHolidayKey ? '저장됨 · 재검증/변경 시 새 키 입력' : '공공데이터포털 일반 인증키' ?>" required>
                    </label>
                    <div class="form-actions">
                        <button class="button primary" type="submit"><i class="bi bi-cloud-download"></i> 저장 · 연결 확인 · 현재 연도 동기화</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="setup-finish panel">
        <div>
            <p class="eyebrow">Finish</p>
            <h2><i class="bi bi-rocket-takeoff"></i> 서비스 시작</h2>
            <p>필수 단계가 모두 완료되면 초기 설정을 잠그고 일반 서비스 화면으로 전환합니다.</p>
        </div>
        <form method="post" action="/setup/finish">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button class="button primary" type="submit" <?= ($status['schema'] && $status['telegram'] && $status['chat'] && $status['admin'] && $status['holiday'] && $adminCount > 0) ? '' : 'disabled' ?>>
                <i class="bi bi-check2-circle"></i> 초기 설정 완료
            </button>
        </form>
    </section>

    <?php if ($employeeBotLink !== null || $employeeLoginLink !== null): ?>
        <section class="panel setup-invite-panel">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Employee invite</p>
                    <h2><i class="bi bi-person-plus"></i> 직원 초대 링크</h2>
                </div>
            </div>
            <p>초기 설정 후 직원에게 아래 링크를 전달합니다. <strong>서비스 로그인 링크가 계정 연결의 기본 경로</strong>이며, 로그인 시 <code>telegram:bot_access</code> 권한을 통해 개인 알림 권한도 함께 요청합니다. Telegram 봇 링크는 봇 채팅을 미리 열어보는 보조 경로입니다.</p>
            <div class="invite-link-grid">
                <?php if ($employeeBotLink !== null): ?>
                    <div class="invite-link-card">
                        <span>Telegram 봇</span>
                        <code><?= htmlspecialchars($employeeBotLink, ENT_QUOTES, 'UTF-8') ?></code>
                        <button class="button small" type="button" data-copy-text="<?= htmlspecialchars($employeeBotLink, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-copy"></i> 복사</button>
                    </div>
                <?php endif; ?>
                <?php if ($employeeLoginLink !== null): ?>
                    <div class="invite-link-card">
                        <span>서비스 로그인</span>
                        <code><?= htmlspecialchars($employeeLoginLink, ENT_QUOTES, 'UTF-8') ?></code>
                        <button class="button small" type="button" data-copy-text="<?= htmlspecialchars($employeeLoginLink, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-copy"></i> 복사</button>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<script src="/assets/app.js" defer></script>
</body>
</html>
