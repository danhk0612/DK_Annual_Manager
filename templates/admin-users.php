<?php
/** @var list<array<string, mixed>> $users */
/** @var array<string, mixed>|null $editUser */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
$editing = is_array($editUser);
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>직원 관리</h1>
        <p>직원 정보, 부서·직책, 입사일, Telegram 연결, 권한과 계정 상태를 관리합니다.</p>
    </div>
    <a class="button" href="/admin">관리자 홈</a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="panel">
    <h2><?= $editing ? '직원 수정' : '직원 추가' ?></h2>
    <form class="form-grid" method="post" action="/admin/users/save">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id" value="<?= $editing ? (int) $editUser['id'] : '' ?>">

        <label>
            이름
            <input name="name" required maxlength="100" value="<?= $editing ? htmlspecialchars((string) $editUser['name'], ENT_QUOTES, 'UTF-8') : '' ?>">
        </label>
        <label>
            부서
            <input name="department" maxlength="100" value="<?= $editing ? htmlspecialchars((string) ($editUser['department'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
        </label>
        <label>
            직책
            <input name="position" maxlength="100" value="<?= $editing ? htmlspecialchars((string) ($editUser['position'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
        </label>
        <label>
            입사일
            <input type="date" name="hire_date" value="<?= $editing ? htmlspecialchars((string) ($editUser['hire_date'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
        </label>
        <label>
            퇴사일
            <input type="date" name="employment_end_date" value="<?= $editing ? htmlspecialchars((string) ($editUser['employment_end_date'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
        </label>
        <label>
            Telegram User ID
            <input inputmode="numeric" name="telegram_user_id" value="<?= $editing ? htmlspecialchars((string) ($editUser['telegram_user_id'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
        </label>
        <label>
            권한
            <?php $role = $editing ? (string) $editUser['role'] : 'user'; ?>
            <select name="role">
                <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>사용자</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>관리자</option>
            </select>
        </label>
        <label>
            상태
            <?php $status = $editing ? (string) $editUser['status'] : 'active'; ?>
            <select name="status">
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>승인 대기</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>활성</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>비활성</option>
            </select>
        </label>
        <div class="form-actions">
            <button class="button primary" type="submit"><?= $editing ? '저장' : '추가' ?></button>
            <?php if ($editing): ?><a class="button" href="/admin/users">취소</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="panel">
    <h2>직원 목록</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>이름</th>
                <th>부서</th>
                <th>직책</th>
                <th>입사일</th>
                <th>Telegram</th>
                <th>권한</th>
                <th>상태</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($user['department'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($user['position'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($user['hire_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ($user['telegram_user_id'] !== null): ?>
                            <?= htmlspecialchars((string) $user['telegram_user_id'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($user['telegram_username'])): ?>
                                <span class="muted">@<?= htmlspecialchars((string) $user['telegram_username'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted">미연결</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $user['role'] === 'admin' ? '관리자' : '사용자' ?></td>
                    <td><span class="badge <?= htmlspecialchars((string) $user['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $user['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><a class="button small" href="/admin/users?edit=<?= (int) $user['id'] ?>">수정</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
