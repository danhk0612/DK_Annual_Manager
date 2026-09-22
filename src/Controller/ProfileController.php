<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Auth\Auth;
use DKAnnual\Export\LeaveExportService;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\AnnualLeaveService;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\Repository\ReportingRepository;
use DKAnnual\Repository\UserRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\View\View;

final class ProfileController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly UserRepository $users,
        private readonly AnnualLeaveService $annualLeave,
        private readonly ReportingRepository $reports,
        private readonly LeaveExportService $exports,
        private readonly AuditLogRepository $audit,
        private readonly View $view,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $this->annualLeave->syncAccruals($user, new DateTimeImmutable('today'), null);
        $year = (int) date('Y');

        return Response::html($this->view->render('profile', [
            'title' => '내 정보',
            'user' => $this->users->findById((int) $user['id']),
            'year' => $year,
            'annualSummary' => $this->reports->userAnnualSummary((int) $user['id'], $year),
            'monthlyLeaveSummary' => $this->reports->userMonthlyLeaveSummary((int) $user['id'], $year),
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'error' => $request->input('error'),
        ]));
    }

    public function export(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $period = $this->exportPeriod($request->input('period'));
        $year = $this->exportYear($request->input('year'));
        $month = $this->exportMonth($request->input('month'));

        $file = $this->exports->create(
            (int) $user['id'],
            (string) ($user['name'] ?? '내 휴가'),
            $period,
            $year,
            $month,
            false,
            'my-leave',
        );

        return Response::download(
            $file['content'],
            $file['filename'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    }

    public function saveProfile(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '' || $this->textLength($name) > 100) {
            return Response::redirect('/profile?error=' . rawurlencode('이름은 1~100자로 입력해 주세요.'));
        }

        $value = trim((string) $request->input('hire_date', ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return Response::redirect('/profile?error=' . rawurlencode('입사일을 확인해 주세요.'));
        }

        $department = $this->optionalText($request->input('department'), 100);
        $position = $this->optionalText($request->input('position'), 100);

        $this->users->updateOwnProfile((int) $user['id'], $name, $value, $department, $position);

        $updated = $this->users->findById((int) $user['id']);
        $added = 0;
        if ($updated !== null) {
            $hireDateChanged = ($user['hire_date'] ?? null) !== $value;
            $added = $hireDateChanged
                ? $this->annualLeave->resyncAccruals($updated, new DateTimeImmutable('today'), (int) $user['id'])
                : $this->annualLeave->syncAccruals($updated, new DateTimeImmutable('today'), (int) $user['id']);
        }

        $this->audit->record((int) $user['id'], 'profile.updated', 'user', (int) $user['id'], [
            'name' => $name,
            'hire_date' => $value,
            'department' => $department,
            'position' => $position,
            'annual_leave_entries_added' => $added,
        ], $this->ip($request));

        return Response::redirect('/profile?message=' . rawurlencode(
            $added > 0 ? sprintf('내 정보를 저장하고 연차 발생분 %d건을 반영했습니다.', $added) : '내 정보를 저장했습니다.'
        ));
    }

    private function exportPeriod(mixed $value): string
    {
        $period = (string) ($value ?? 'year');
        return in_array($period, ['year', 'month', 'all'], true) ? $period : 'year';
    }

    private function exportYear(mixed $value): int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);
        return $year !== false && $year >= 2000 && $year <= 2100 ? (int) $year : (int) date('Y');
    }

    private function exportMonth(mixed $value): int
    {
        $month = filter_var($value, FILTER_VALIDATE_INT);
        return $month !== false && $month >= 1 && $month <= 12 ? (int) $month : (int) date('n');
    }

    private function textLength(string $value): int
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($characters) ? count($characters) : strlen($value);
    }

    private function optionalText(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($characters) && count($characters) > $maxLength) {
            return implode('', array_slice($characters, 0, $maxLength));
        }

        return $value;
    }

    private function ip(Request $request): ?string
    {
        $ip = trim((string) $request->server('REMOTE_ADDR', ''));
        return $ip !== '' ? $ip : null;
    }
}
