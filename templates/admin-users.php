<?php
/** @var list<array<string, mixed>> $users */
/** @var int $activeAdminCount */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */

$statusLabels = [
    'pending' => '승인 대기',
    'active' => '활성',
    'inactive' => '비활성',
];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Administration</p>
        <h1><i class="bi bi-people"></i><span>직원 관리</span></h1>
        <p>직원 정보와 Telegram 연결, 권한, 계정 상태를 목록에서 관리합니다.</p>
    </div>
    <div class="page-actions">
        <button class="button primary" type="button" data-open-user-dialog data-user-mode="create"><i class="bi bi-person-plus"></i><span>직원 추가</span></button>
        <a class="button" href="/admin"><i class="bi bi-speedometer2"></i><span>관리자 홈</span></a>
    </div>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><i class="bi bi-check-circle"></i><span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><i class="bi bi-x-circle"></i><span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>

<section class="panel admin-list-panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Employees</p>
            <h2><i class="bi bi-card-list"></i><span>직원 목록</span></h2>
        </div>
        <span class="count-badge"><?= count($users) ?>명</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>이름</th>
                <th>부서 · 직책</th>
                <th>입사일</th>
                <th>Telegram</th>
                <th>권한</th>
                <th>상태</th>
                <th class="table-action-column">기능</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr><td colspan="7" class="muted">등록된 직원이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <?php
                $soleAdmin = $activeAdminCount === 1
                    && ($user['role'] ?? null) === 'admin'
                    && ($user['status'] ?? null) === 'active';
                $telegramLabel = $user['telegram_user_id'] !== null
                    ? (string) $user['telegram_user_id']
                    : '미연결';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td>
                        <?= htmlspecialchars((string) ($user['department'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        <span class="muted">· <?= htmlspecialchars((string) ($user['position'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td><?= htmlspecialchars((string) ($user['hire_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?= htmlspecialchars($telegramLabel, ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($user['telegram_username'])): ?>
                            <span class="muted">@<?= htmlspecialchars((string) $user['telegram_username'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $user['role'] === 'admin' ? 'approved' : 'neutral' ?>"><?= $user['role'] === 'admin' ? '관리자' : '사용자' ?></span></td>
                    <td><span class="badge <?= htmlspecialchars((string) $user['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabels[(string) $user['status']] ?? (string) $user['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <button
                            class="button small"
                            type="button"
                            data-open-user-dialog
                            data-user-mode="edit"
                            data-user-id="<?= (int) $user['id'] ?>"
                            data-user-name="<?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-user-department="<?= htmlspecialchars((string) ($user['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-position="<?= htmlspecialchars((string) ($user['position'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-hire-date="<?= htmlspecialchars((string) ($user['hire_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-end-date="<?= htmlspecialchars((string) ($user['employment_end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-telegram-id="<?= htmlspecialchars((string) ($user['telegram_user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-user-role="<?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?>"
                            data-user-status="<?= htmlspecialchars((string) $user['status'], ENT_QUOTES, 'UTF-8') ?>"
                            data-user-sole-admin="<?= $soleAdmin ? '1' : '0' ?>"
                        ><i class="bi bi-pencil-square"></i><span>수정</span></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog class="modal-dialog" data-user-dialog>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">Employee</p>
                <h2 data-user-dialog-title><i class="bi bi-person-gear"></i><span>직원 추가</span></h2>
                <p>직원 기본 정보, Telegram ID, 권한과 상태를 저장합니다.</p>
            </div>
            <button class="icon-button" type="button" data-close-user-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="notice warning" data-user-sole-admin-warning hidden>
            <i class="bi bi-shield-exclamation"></i>
            <span>현재 유일한 활성 관리자입니다. 다른 관리자를 먼저 지정하기 전에는 사용자로 변경하거나 비활성화할 수 없습니다.</span>
        </div>

        <form class="form-grid" method="post" action="/admin/users/save" data-user-form>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" value="" data-user-id>

            <label>
                이름
                <input name="name" required maxlength="100" data-user-name>
            </label>
            <label>
                부서
                <input name="department" maxlength="100" data-user-department>
            </label>
            <label>
                직책
                <input name="position" maxlength="100" data-user-position>
            </label>
            <label>
                입사일
                <input type="date" name="hire_date" data-user-hire-date>
            </label>
            <label>
                퇴사일
                <input type="date" name="employment_end_date" data-user-end-date>
            </label>
            <label>
                Telegram User ID
                <input inputmode="numeric" name="telegram_user_id" data-user-telegram-id>
            </label>
            <label>
                권한
                <select name="role" data-user-role>
                    <option value="user">사용자</option>
                    <option value="admin">관리자</option>
                </select>
            </label>
            <label>
                상태
                <select name="status" data-user-status>
                    <option value="pending">승인 대기</option>
                    <option value="active">활성</option>
                    <option value="inactive">비활성</option>
                </select>
            </label>
            <div class="form-actions span-2">
                <button class="button primary" type="submit"><i class="bi bi-check2-circle"></i><span data-user-submit-label>추가</span></button>
                <button class="button" type="button" data-close-user-dialog>취소</button>
            </div>
        </form>
    </div>
</dialog>
