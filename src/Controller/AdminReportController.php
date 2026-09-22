<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Export\LeaveExportService;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\ReportingRepository;
use DKAnnual\Repository\UserRepository;
use DKAnnual\View\View;

final class AdminReportController
{
    public function __construct(
        private readonly ReportingRepository $reports,
        private readonly UserRepository $users,
        private readonly LeaveExportService $exports,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $year = $this->yearFrom($request->input('year'));
        $query = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');
        $department = trim((string) $request->input('department', ''));
        $leaveType = trim((string) $request->input('leave_type', ''));

        if (!in_array($status, ['', 'active', 'pending', 'inactive'], true)) {
            $status = '';
        }

        $allAnnual = $this->reports->annualUserSummary($year);
        $departments = [];
        foreach ($allAnnual as $row) {
            $value = trim((string) ($row['department'] ?? ''));
            if ($value !== '') {
                $departments[$value] = true;
            }
        }
        $departments = array_keys($departments);
        sort($departments, SORT_NATURAL);

        $annualSummary = array_values(array_filter(
            $allAnnual,
            static function (array $row) use ($query, $status, $department): bool {
                if ($status !== '' && (string) ($row['status'] ?? '') !== $status) {
                    return false;
                }
                if ($department !== '' && (string) ($row['department'] ?? '') !== $department) {
                    return false;
                }
                if ($query === '') {
                    return true;
                }

                $haystack = implode(' ', [
                    (string) ($row['name'] ?? ''),
                    (string) ($row['department'] ?? ''),
                    (string) ($row['position'] ?? ''),
                ]);

                return stripos($haystack, $query) !== false;
            },
        ));

        $monthlyTypeSource = $this->reports->monthlyApprovedLeaveSummary($year);
        $leaveTypes = [];
        foreach ($monthlyTypeSource as $row) {
            $leaveTypes[(string) $row['code']] = (string) $row['name'];
        }

        if ($leaveType !== '' && !isset($leaveTypes[$leaveType])) {
            $leaveType = '';
        }

        $allMonthly = $this->reports->monthlyApprovedLeaveSummary($year, $query, $status, $department);
        $monthlySummary = $leaveType === ''
            ? $allMonthly
            : array_values(array_filter(
                $allMonthly,
                static fn (array $row): bool => (string) $row['code'] === $leaveType,
            ));

        $graphTotals = array_fill(1, 12, 0.0);
        foreach ($monthlySummary as $row) {
            $graphTotals[(int) $row['month_number']] += (float) $row['amount'];
        }

        return Response::html($this->view->render('admin-reports', [
            'title' => '휴가 집계',
            'year' => $year,
            'query' => $query,
            'statusFilter' => $status,
            'departmentFilter' => $department,
            'leaveTypeFilter' => $leaveType,
            'departments' => $departments,
            'leaveTypes' => $leaveTypes,
            'annualSummary' => $annualSummary,
            'monthlySummary' => $monthlySummary,
            'graphTotals' => $graphTotals,
            'exportUsers' => $this->users->all(),
            'error' => $request->input('error'),
        ]));
    }

    public function export(Request $request): Response
    {
        $period = $this->exportPeriod($request->input('period'));
        $year = $this->yearFrom($request->input('year'));
        $month = $this->monthFrom($request->input('month'));
        $userId = $this->userIdFrom($request->input('user_id'));

        $targetLabel = '전체 사용자';
        if ($userId !== null) {
            $user = $this->users->findById($userId);
            if ($user === null) {
                return Response::redirect('/admin/reports?error=' . rawurlencode('내보낼 사용자를 찾지 못했습니다.'));
            }
            $targetLabel = (string) ($user['name'] ?? ('사용자 #' . $userId));
        }

        $file = $this->exports->create(
            $userId,
            $targetLabel,
            $period,
            $year,
            $month,
            true,
            $userId === null ? 'leave-report-all-users' : 'leave-report-user-' . $userId,
        );

        return Response::download(
            $file['content'],
            $file['filename'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    }

    private function exportPeriod(mixed $value): string
    {
        $period = (string) ($value ?? 'year');
        return in_array($period, ['year', 'month', 'all'], true) ? $period : 'year';
    }

    private function monthFrom(mixed $value): int
    {
        $month = filter_var($value, FILTER_VALIDATE_INT);
        return $month !== false && $month >= 1 && $month <= 12 ? (int) $month : (int) date('n');
    }

    private function userIdFrom(mixed $value): ?int
    {
        $value = (string) ($value ?? '');
        if ($value === '' || $value === 'all') {
            return null;
        }

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function yearFrom(mixed $value): int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);
        return $year !== false && $year >= 2000 && $year <= 2100 ? (int) $year : (int) date('Y');
    }
}
