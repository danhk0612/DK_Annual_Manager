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
        <h1><i class="bi bi-calendar2-event"></i><span>공휴일 관리</span></h1>
        <p>공휴일과 회사 휴무일을 목록 중심으로 확인하고 필요한 작업은 레이어에서 처리합니다.</p>
    </div>
    <a class="button" href="/admin"><i class="bi bi-speedometer2"></i><span>관리자 홈</span></a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><i class="bi bi-check-circle"></i><span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><i class="bi bi-x-circle"></i><span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span></div>
<?php endif; ?>

<section class="panel admin-list-panel">
    <div class="list-toolbar">
        <div>
            <p class="eyebrow">Holidays</p>
            <h2><i class="bi bi-list-ul"></i><span><?= $year ?>년 휴일 목록</span></h2>
            <p class="list-toolbar-meta">
                API <?= $apiConfigured ? '설정됨' : '미설정' ?>
                · 최근 갱신 <?= $lastSyncedAt !== null ? htmlspecialchars($lastSyncedAt, ENT_QUOTES, 'UTF-8') : '없음' ?>
                · 총 <?= count($holidays) ?>건
            </p>
        </div>
        <div class="list-toolbar-actions">
            <form class="toolbar-form" method="get" action="/admin/holidays">
                <label>
                    <span>연도</span>
                    <input type="number" name="year" min="1900" max="2200" value="<?= $year ?>">
                </label>
                <button class="button" type="submit"><i class="bi bi-search"></i><span>조회</span></button>
            </form>
            <button class="button" type="button" data-open-holiday-sync-dialog <?= !$apiConfigured ? 'disabled' : '' ?>><i class="bi bi-cloud-arrow-down"></i><span>공휴일 갱신</span></button>
            <button class="button primary" type="button" data-open-holiday-add-dialog><i class="bi bi-plus-circle"></i><span>휴일 추가</span></button>
        </div>
    </div>

    <?php if (!$apiConfigured): ?>
        <div class="notice warning compact-notice"><i class="bi bi-info-circle"></i><span>공공데이터 ServiceKey가 없어 API 갱신을 사용할 수 없습니다. 환경 설정에서 먼저 등록하세요.</span></div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>날짜</th>
                <th>명칭</th>
                <th>출처</th>
                <th>휴가 계산 제외</th>
                <th>갱신일</th>
                <th class="table-action-column">기능</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($holidays === []): ?>
                <tr><td colspan="6" class="muted">등록된 휴일이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($holidays as $holiday): ?>
                <?php $source = (string) $holiday['source']; ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) $holiday['holiday_date'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars((string) $holiday['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge neutral"><?= htmlspecialchars($sourceLabels[$source] ?? $source, ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= (int) $holiday['is_public_holiday'] === 1 ? '예' : '아니오' ?></td>
                    <td><?= htmlspecialchars((string) $holiday['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (in_array($source, ['manual', 'company'], true)): ?>
                            <form method="post" action="/admin/holidays/delete" data-confirm-message="이 휴일을 삭제하시겠습니까?">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="id" value="<?= (int) $holiday['id'] ?>">
                                <input type="hidden" name="year" value="<?= $year ?>">
                                <button class="button small danger-ghost" type="submit"><i class="bi bi-trash3"></i><span>삭제</span></button>
                            </form>
                        <?php else: ?>
                            <span class="muted">자동 관리</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog class="modal-dialog compact-dialog" data-holiday-sync-dialog>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">Public API</p>
                <h2><i class="bi bi-cloud-arrow-down"></i><span>공휴일 갱신</span></h2>
                <p><?= $year ?>년 공공데이터 공휴일을 다시 받아 기존 API 데이터를 교체합니다.</p>
            </div>
            <button class="icon-button" type="button" data-close-holiday-sync-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="post" action="/admin/holidays/sync">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="year" value="<?= $year ?>">
            <div class="form-actions">
                <button class="button primary" type="submit"><i class="bi bi-arrow-repeat"></i><span><?= $year ?>년 공휴일 업데이트</span></button>
                <button class="button" type="button" data-close-holiday-sync-dialog>취소</button>
            </div>
        </form>
    </div>
</dialog>

<dialog class="modal-dialog compact-dialog" data-holiday-add-dialog>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">Manual holiday</p>
                <h2><i class="bi bi-calendar-plus"></i><span>휴일 추가</span></h2>
                <p>회사 휴무일 또는 수동 휴일을 등록합니다.</p>
            </div>
            <button class="icon-button" type="button" data-close-holiday-add-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>
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
            <div class="form-actions span-2">
                <button class="button primary" type="submit"><i class="bi bi-check2-circle"></i><span>휴일 추가</span></button>
                <button class="button" type="button" data-close-holiday-add-dialog>취소</button>
            </div>
        </form>
    </div>
</dialog>
