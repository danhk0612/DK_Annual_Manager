<?php
/** @var list<array<string, mixed>> $requests */
/** @var list<array<string, mixed>> $approvedRequests */
/** @var list<array<string, mixed>> $users */
/** @var list<array<string, mixed>> $leaveTypes */
/** @var list<string> $reasonCategories */
/** @var string $today */
/** @var string $csrfToken */
/** @var mixed $message */
/** @var mixed $warning */
/** @var mixed $error */

$returnTo = '/admin/requests';
$targetUsers = $users;
$annualBalance = null;
$allowAdminDateException = false;
$adminDirectEntry = true;
$submitLabel = '휴가 등록 · 즉시 승인';
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Leave approvals</p>
        <h1>휴가 승인</h1>
        <p>승인 대기 처리, 승인 취소, 일정 변경을 위한 대리 신청을 한 화면에서 관리합니다.</p>
    </div>
    <a class="button" href="/admin">관리자 대시보드</a>
</section>

<?php if (is_string($message) && $message !== ''): ?>
    <div class="notice success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($warning) && $warning !== ''): ?>
    <div class="notice warning"><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="content-grid two-column admin-request-grid">
    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Pending</p>
                <h2>승인 대기</h2>
            </div>
            <span class="count-badge"><?= count($requests) ?>건</span>
        </div>

        <?php if ($requests === []): ?>
            <div class="empty-state">승인 대기 중인 신청이 없습니다.</div>
        <?php endif; ?>

        <div class="approval-list" data-paginate data-page-size="10">
        <?php foreach ($requests as $item): ?>
            <article class="approval-card" id="request-<?= (int) $item['id'] ?>" data-page-item>
                <div class="approval-summary">
                    <div>
                        <span class="muted">#<?= (int) $item['id'] ?></span>
                        <?php
                        $halfDayPeriod = (string) ($item['half_day_period'] ?? '');
                        $halfDayLabel = $halfDayPeriod === 'am' ? ' · 오전' : ($halfDayPeriod === 'pm' ? ' · 오후' : '');
                        $annualBalanceItem = (float) ($item['annual_balance'] ?? 0);
                        $isInsufficient = (int) ($item['deducts_annual_leave'] ?? 0) === 1
                            && (float) $item['requested_amount'] > $annualBalanceItem;
                        ?>
                        <h3><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?></h3>
                        <p class="request-period"><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?> ~ <?= htmlspecialchars((string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?> · <strong><?= number_format((float) $item['requested_amount'], 1) ?>일</strong></p>
                        <?php if ((int) ($item['deducts_annual_leave'] ?? 0) === 1): ?>
                            <p class="muted-line">현재 잔여 연차 <?= number_format($annualBalanceItem, 1) ?>일</p>
                        <?php endif; ?>
                        <?php if ($isInsufficient): ?>
                            <div class="notice warning compact-notice">잔여 연차보다 신청 일수가 많습니다. 관리자 재량으로 승인할 수 있습니다.</div>
                        <?php endif; ?>
                        <?php if (!empty($item['reason'])): ?>
                            <p class="reason-line">사유: <?= htmlspecialchars((string) $item['reason'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="badge pending">승인 대기</span>
                </div>

                <div class="form-actions approval-card-actions">
                    <button
                        class="button primary"
                        type="button"
                        data-open-review-dialog
                        data-review-id="<?= (int) $item['id'] ?>"
                        data-review-action="approve"
                        data-review-user="<?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?>"
                        data-review-type="<?= htmlspecialchars((string) $item['leave_type_name'] . $halfDayLabel, ENT_QUOTES, 'UTF-8') ?>"
                        data-review-period="<?= htmlspecialchars((string) $item['start_date'] . ' ~ ' . (string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?>"
                    >승인</button>
                    <button
                        class="button danger-ghost"
                        type="button"
                        data-open-review-dialog
                        data-review-id="<?= (int) $item['id'] ?>"
                        data-review-action="reject"
                        data-review-user="<?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?>"
                        data-review-type="<?= htmlspecialchars((string) $item['leave_type_name'] . $halfDayLabel, ENT_QUOTES, 'UTF-8') ?>"
                        data-review-period="<?= htmlspecialchars((string) $item['start_date'] . ' ~ ' . (string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?>"
                    >반려</button>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Admin entry</p>
                <h2>직원 대신 휴가 등록</h2>
            </div>
        </div>
        <p class="form-hint">관리자가 직원 대신 등록한 휴가는 근무요일·공휴일·중복 일정·잔여 연차 조건을 검사하지 않고 입력한 날짜 그대로 즉시 승인됩니다. 연차/반차는 승인과 동시에 원장에 반영됩니다.</p>
        <?php require __DIR__ . '/_leave-form.php'; ?>
    </section>
</div>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Approved</p>
            <h2>전체 승인 목록</h2>
        </div>
        <span class="count-badge"><?= count($approvedRequests) ?>건</span>
    </div>

    <div class="table-wrap">
        <table class="data-table" data-paginate data-page-size="20">
            <thead>
            <tr>
                <th>직원</th>
                <th>종류</th>
                <th>기간</th>
                <th>일수</th>
                <th>사유</th>
                <th>처리</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($approvedRequests === []): ?>
                <tr><td colspan="6" class="muted">승인된 휴가가 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($approvedRequests as $item): ?>
                <?php
                $halfDayPeriod = (string) ($item['half_day_period'] ?? '');
                $halfDayLabel = $halfDayPeriod === 'am' ? ' · 오전' : ($halfDayPeriod === 'pm' ? ' · 오후' : '');
                ?>
                <tr id="request-<?= (int) $item['id'] ?>" data-page-item>
                    <td><strong><?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars((string) $item['leave_type_name'], ENT_QUOTES, 'UTF-8') ?><?= $halfDayLabel ?></td>
                    <td><?= htmlspecialchars((string) $item['start_date'], ENT_QUOTES, 'UTF-8') ?> ~ <?= htmlspecialchars((string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= number_format((float) $item['requested_amount'], 1) ?></td>
                    <td><?= htmlspecialchars((string) ($item['reason'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <button
                            class="button small danger-ghost"
                            type="button"
                            data-open-admin-cancel-dialog
                            data-cancel-id="<?= (int) $item['id'] ?>"
                            data-cancel-user="<?= htmlspecialchars((string) $item['user_name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-cancel-type="<?= htmlspecialchars((string) $item['leave_type_name'] . $halfDayLabel, ENT_QUOTES, 'UTF-8') ?>"
                            data-cancel-period="<?= htmlspecialchars((string) $item['start_date'] . ' ~ ' . (string) $item['end_date'], ENT_QUOTES, 'UTF-8') ?>"
                        >승인 취소</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>


<dialog class="modal-dialog compact-dialog" data-review-dialog>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">Review leave</p>
                <h2 data-review-title>휴가 승인</h2>
                <p data-review-summary></p>
            </div>
            <button class="icon-button" type="button" data-close-review-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>
        <form class="form-grid" method="post" action="/admin/requests/review" data-review-form>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="request_id" value="" data-review-request-id>
            <input type="hidden" name="action" value="" data-review-action-input>
            <label class="span-2">
                관리자 메모
                <input name="review_note" maxlength="1000" placeholder="선택 입력">
            </label>
            <div class="form-actions span-2">
                <button class="button primary" type="submit" data-review-submit>처리</button>
                <button class="button" type="button" data-close-review-dialog>취소</button>
            </div>
        </form>
    </div>
</dialog>

<dialog class="modal-dialog compact-dialog" data-admin-cancel-dialog>
    <div class="modal-card">
        <div class="section-head modal-head">
            <div>
                <p class="eyebrow">Cancel approved leave</p>
                <h2>승인 휴가 취소</h2>
                <p data-admin-cancel-summary></p>
            </div>
            <button class="icon-button" type="button" data-close-admin-cancel-dialog aria-label="닫기"><i class="bi bi-x-lg"></i></button>
        </div>
        <form class="form-grid" method="post" action="/admin/requests/cancel-approved">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="request_id" value="" data-admin-cancel-id>
            <label class="span-2">
                취소 사유
                <input name="cancellation_note" maxlength="1000" placeholder="선택 입력">
            </label>
            <div class="form-actions span-2">
                <button class="button danger-ghost" type="submit">승인 취소</button>
                <button class="button" type="button" data-close-admin-cancel-dialog>닫기</button>
            </div>
        </form>
    </div>
</dialog>
