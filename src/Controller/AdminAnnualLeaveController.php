<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\AnnualLeaveService;
use DKAnnual\Repository\AnnualLeaveLedgerRepository;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\Repository\UserRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\View\View;

final class AdminAnnualLeaveController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AnnualLeaveLedgerRepository $ledger,
        private readonly AnnualLeaveService $annualLeave,
        private readonly Auth $auth,
        private readonly AuditLogRepository $audit,
        private readonly View $view,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        $users = $this->users->all();
        $selectedUser = $this->selectedUser($request, $users);
        $year = $this->selectedYear($request);

        $entries = [];
        $balance = 0.0;
        $totalEntitlement = 0.0;
        $overrideAmount = null;
        if ($selectedUser !== null) {
            if (!empty($selectedUser['hire_date'])) {
                $actor = $this->auth->user();
                $this->annualLeave->syncAccruals(
                    $selectedUser,
                    new DateTimeImmutable('today'),
                    $actor !== null ? (int) $actor['id'] : null,
                );
            }
            $entries = $this->ledger->entriesForUserYear((int) $selectedUser['id'], $year);
            $balance = $this->ledger->balanceForUserYear((int) $selectedUser['id'], $year);
            $totalEntitlement = $this->ledger->nonUsageTotalForUserYear((int) $selectedUser['id'], $year);
            $overrideAmount = $this->annualLeave->overrideAmount((int) $selectedUser['id'], $year);
        }

        return Response::html($this->view->render('admin-annual-leave', [
            'title' => '연차 관리',
            'users' => $users,
            'selectedUser' => $selectedUser,
            'year' => $year,
            'entries' => $entries,
            'balance' => $balance,
            'totalEntitlement' => $totalEntitlement,
            'overrideAmount' => $overrideAmount,
            'manageOpen' => $selectedUser !== null && (string) $request->input('manage', '') === '1',
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'error' => $request->input('error'),
        ]));
    }

    public function sync(Request $request): Response
    {
        $userId = $this->requiredPositiveInt($request->input('user_id'));
        if ($userId === null) {
            return $this->redirectError('직원을 선택해 주세요.');
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return $this->redirectError('직원을 찾을 수 없습니다.');
        }

        $actor = $this->auth->user();
        $count = $this->annualLeave->syncAccruals(
            $user,
            new DateTimeImmutable('today'),
            $actor !== null ? (int) $actor['id'] : null,
        );

        $this->audit->record(
            $actor !== null ? (int) $actor['id'] : null,
            'annual_leave.synced',
            'user',
            $userId,
            ['added_entries' => $count],
            $this->ip($request),
        );

        return Response::redirect(
            '/admin/annual-leave?user_id=' . $userId
            . '&year=' . (int) date('Y')
            . '&manage=1'
            . '&message=' . rawurlencode(sprintf('발생 원장 %d건을 추가했습니다.', $count))
        );
    }

    public function adjust(Request $request): Response
    {
        $userId = $this->requiredPositiveInt($request->input('user_id'));
        $year = $this->requiredPositiveInt($request->input('year'));
        $type = (string) $request->input('transaction_type', 'adjustment');
        $amountValue = trim((string) $request->input('amount', ''));
        $note = trim((string) $request->input('note', ''));

        if ($userId === null || $year === null || $year < 2000 || $year > 2100) {
            return $this->redirectError('직원과 연도를 확인해 주세요.');
        }
        if (!in_array($type, ['carryover', 'adjustment'], true)) {
            return $this->redirectError('조정 유형을 확인해 주세요.', $userId, $year);
        }
        if (!is_numeric($amountValue) || (float) $amountValue === 0.0) {
            return $this->redirectError('0이 아닌 조정 일수를 입력해 주세요.', $userId, $year);
        }

        $amount = (float) $amountValue;
        if ($type === 'carryover' && $amount < 0) {
            return $this->redirectError('이월 일수는 양수로 입력해 주세요.', $userId, $year);
        }

        if ($this->users->findById($userId) === null) {
            return $this->redirectError('직원을 찾을 수 없습니다.');
        }

        $actor = $this->auth->user();
        if ($actor === null) {
            return Response::redirect('/login');
        }

        $this->ledger->addManual(
            $userId,
            $year,
            $type,
            $amount,
            $note !== '' ? $note : null,
            (int) $actor['id'],
        );

        $this->audit->record((int) $actor['id'], 'annual_leave.adjusted', 'user', $userId, [
            'leave_year' => $year,
            'transaction_type' => $type,
            'amount' => $amount,
        ], $this->ip($request));

        return Response::redirect(
            '/admin/annual-leave?user_id=' . $userId
            . '&year=' . $year
            . '&manage=1'
            . '&message=' . rawurlencode('연차 원장을 조정했습니다.')
        );
    }

    public function setTotal(Request $request): Response
    {
        $userId = $this->requiredPositiveInt($request->input('user_id'));
        $year = $this->requiredPositiveInt($request->input('year'));
        $targetValue = trim((string) $request->input('total_amount', ''));
        $note = trim((string) $request->input('note', ''));

        if ($userId === null || $year === null || $year < 2000 || $year > 2100) {
            return $this->redirectError('직원과 연도를 확인해 주세요.');
        }
        if (!is_numeric($targetValue) || (float) $targetValue < 0 || (float) $targetValue > 365) {
            return $this->redirectError('총 연차는 0~365일 범위로 입력해 주세요.', $userId, $year);
        }
        if ($this->users->findById($userId) === null) {
            return $this->redirectError('직원을 찾을 수 없습니다.', $userId, $year);
        }

        $actor = $this->auth->user();
        if ($actor === null) {
            return Response::redirect('/login');
        }

        $target = round((float) $targetValue, 2);
        $previousOverride = $this->annualLeave->overrideAmount($userId, $year);
        $delta = $this->annualLeave->setOverride($userId, $year, $target, (int) $actor['id']);

        $this->audit->record((int) $actor['id'], 'annual_leave.total_override_set', 'user', $userId, [
            'leave_year' => $year,
            'previous_override' => $previousOverride,
            'target_total' => $target,
            'override_adjustment' => $delta,
            'note' => $note !== '' ? $note : null,
        ], $this->ip($request));

        return Response::redirect(
            '/admin/annual-leave?user_id=' . $userId
            . '&year=' . $year
            . '&manage=1'
            . '&message=' . rawurlencode(sprintf('총 연차를 %.1f일로 설정했습니다.', $target))
        );
    }

    public function clearTotalOverride(Request $request): Response
    {
        $userId = $this->requiredPositiveInt($request->input('user_id'));
        $year = $this->requiredPositiveInt($request->input('year'));
        if ($userId === null || $year === null || $year < 2000 || $year > 2100) {
            return $this->redirectError('직원과 연도를 확인해 주세요.');
        }

        $actor = $this->auth->user();
        if ($actor === null) {
            return Response::redirect('/login');
        }

        $previous = $this->annualLeave->overrideAmount($userId, $year);
        $this->annualLeave->clearOverride($userId, $year);

        $this->audit->record((int) $actor['id'], 'annual_leave.total_override_cleared', 'user', $userId, [
            'leave_year' => $year,
            'previous_override' => $previous,
        ], $this->ip($request));

        return Response::redirect(
            '/admin/annual-leave?user_id=' . $userId
            . '&year=' . $year
            . '&manage=1'
            . '&message=' . rawurlencode('총 연차 고정을 해제하고 자동 계산값으로 복귀했습니다.')
        );
    }

    /** @param list<array<string, mixed>> $users */
    private function selectedUser(Request $request, array $users): ?array
    {
        $requested = $this->requiredPositiveInt($request->input('user_id'));
        if ($requested !== null) {
            foreach ($users as $user) {
                if ((int) $user['id'] === $requested) {
                    return $user;
                }
            }
        }

        return null;
    }

    private function selectedYear(Request $request): int
    {
        $year = $this->requiredPositiveInt($request->input('year'));
        return $year !== null && $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
    }

    private function requiredPositiveInt(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $string = (string) $value;
        return ctype_digit($string) && (int) $string > 0 ? (int) $string : null;
    }

    private function redirectError(string $message, ?int $userId = null, ?int $year = null): Response
    {
        $query = [];
        if ($userId !== null) {
            $query['user_id'] = $userId;
        }
        if ($year !== null) {
            $query['year'] = $year;
        }
        if ($userId !== null) {
            $query['manage'] = '1';
        }
        $query['error'] = $message;

        return Response::redirect('/admin/annual-leave?' . http_build_query($query));
    }

    private function ip(Request $request): ?string
    {
        $ip = trim((string) $request->server('REMOTE_ADDR', ''));
        return $ip !== '' ? $ip : null;
    }
}
