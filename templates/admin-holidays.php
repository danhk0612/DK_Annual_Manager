<?php
/** @var int $year */
/** @var list<array<string, mixed>> $holidays */
/** @var string|null $lastSyncedAt */
/** @var bool $apiConfigured */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $error */
$sourceLabels = [
    'public_api' => '공공 API',
    'manual' => '수동',
    'company' => '회사 휴무',
];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>공휴일 관리</h1>
        <p>한국 공휴일을 갱신하고 회사 휴무일이나 별도 휴일을 추가합니다.</p>
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
    <h2>공공데이터 갱신</h2>
    <form class="form-grid compact" method="get" action="/admin/holidays">
        <label>
            조회 연도
            <input type="number" name="year" min="1900" max="2200" value="<?= $year ?>">
        </label>
        <div class="form-actions">
            <button class="button" type="submit">조회</button>
        </div>
    </form>

    <dl class="details">
        <div><dt>API 설정</dt><dd><?= $apiConfigured ? '설정됨' : '서비스키 미설정' ?></dd></div>
        <div><dt>최근 갱신</dt><dd><?= $lastSyncedAt !== null ? htmlspecialchars($lastSyncedAt, ENT_QUOTES, 'UTF-8') : '아직 없음' ?></dd></div>
    </dl>

    <form method="post" action="/admin/holidays/sync">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="year" value="<?= $year ?>">
        <button class="button primary" type="submit" <?= !$apiConfigured ? 'disabled' : '' ?>><?= $year ?>년 한국 공휴일 업데이트</button>
    </form>
    <?php if (!$apiConfigured): ?>
        <p class="muted">config.php의 holiday_api.service_key에 공공데이터포털 서비스키를 입력하면 사용할 수 있습니다.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>수동 휴일 추가</h2>
    <form class="form-grid" method="post" action="/admin/holidays/save">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label>
            날짜
            <input type="date" name="holiday_date" required value="<?= $year ?>-01-01">
        </label>
        <label>
            명칭
            <input name="name" required maxlength="120" placeholder="예: 창립기념일">
        </label>
        <label>
            유형
            <select name="source">
                <option value="company">회사 휴무</option>
                <option value="manual">수동 공휴일</option>
            </select>
        </label>
        <label>
            휴가일수 계산
            <select name="is_public_holiday">
                <option value="1">휴무일로 제외</option>
                <option value="0">표시만 하고 제외하지 않음</option>
            </select>
        </label>
        <div class="form-actions">
            <button class="button primary" type="submit">추가</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2><?= $year ?>년 휴일 목록</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>날짜</th>
                <th>명칭</th>
                <th>출처</th>
                <th>휴가 계산 제외</th>
                <th>갱신일</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($holidays === []): ?>
                <tr><td colspan="6" class="muted">등록된 휴일이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($holidays as $holiday): ?>
                <?php $source = (string) $holiday['source']; ?>
                <tr>
                    <td><?= htmlspecialchars((string) $holiday['holiday_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $holiday['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($sourceLabels[$source] ?? $source, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $holiday['is_public_holiday'] === 1 ? '예' : '아니오' ?></td>
                    <td><?= htmlspecialchars((string) $holiday['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (in_array($source, ['manual', 'company'], true)): ?>
                            <form method="post" action="/admin/holidays/delete">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="id" value="<?= (int) $holiday['id'] ?>">
                                <input type="hidden" name="year" value="<?= $year ?>">
                                <button class="button small" type="submit">삭제</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
