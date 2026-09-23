<?php

declare(strict_types=1);

namespace DKAnnual\Export;

use DateTimeImmutable;
use DateTimeZone;
use DKAnnual\Repository\ReportingRepository;

final class LeaveExportService
{
    public function __construct(
        private readonly ReportingRepository $reports,
        private readonly XlsxWriter $xlsx,
    ) {
    }

    /**
     * @return array{filename:string, content:string}
     */
    public function create(
        ?int $userId,
        string $targetLabel,
        string $period,
        int $year,
        int $month,
        bool $includeEmployeeColumns,
        string $filenamePrefix,
    ): array {
        [$startDate, $endDate, $periodLabel, $periodSlug] = $this->period($period, $year, $month);
        $dayRows = $this->reports->leaveExportDayRows($userId, $startDate, $endDate);
        $requestRows = $this->groupRequestRows($dayRows);

        $sheets = [
            $this->summarySheet($dayRows, $requestRows, $targetLabel, $periodLabel, $includeEmployeeColumns),
            $this->yearStatisticsSheet($dayRows),
            $this->monthStatisticsSheet($dayRows),
            $this->leaveTypeStatisticsSheet($dayRows),
        ];

        if ($includeEmployeeColumns) {
            $sheets[] = $this->employeeStatisticsSheet($dayRows);
            $sheets[] = $this->departmentStatisticsSheet($dayRows);
        }

        $sheets[] = $this->annualLeaveBalanceSheet($userId, $year, $includeEmployeeColumns);

        if ($includeEmployeeColumns) {
            $sheets[] = $this->reviewSheet($dayRows, $requestRows);
        }

        $sheets[] = $this->requestDetailSheet(
            '신청 기록',
            '선택 범위 전체 신청 기록',
            $requestRows,
            $includeEmployeeColumns,
            $periodLabel . ' 기준으로 실제 휴가일이 포함된 신청을 표시합니다.',
        );
        $sheets[] = $this->dayDetailSheet(
            '일자별 기록',
            '실제 휴가일 기준 상세 기록',
            $dayRows,
            $includeEmployeeColumns,
        );

        foreach ($this->groupDaysByYear($dayRows) as $recordYear => $rows) {
            $sheets[] = $this->requestDetailSheet(
                '연도 ' . $recordYear,
                $recordYear . '년 신청 기록',
                $this->groupRequestRows($rows),
                $includeEmployeeColumns,
                '해당 연도에 실제 휴가일이 포함된 신청만 표시합니다.',
            );
        }

        foreach ($this->groupDaysByMonth($dayRows) as $yearMonth => $rows) {
            $sheets[] = $this->requestDetailSheet(
                '월 ' . $yearMonth,
                $yearMonth . ' 신청 기록',
                $this->groupRequestRows($rows),
                $includeEmployeeColumns,
                '해당 월에 실제 휴가일이 포함된 신청만 표시합니다.',
            );
        }

        if ($includeEmployeeColumns && $userId === null) {
            foreach ($this->groupDaysByUser($dayRows) as $group) {
                $label = trim((string) ($group['name'] ?? '직원'));
                $department = trim((string) ($group['department'] ?? ''));
                $position = trim((string) ($group['position'] ?? ''));
                $meta = implode(' · ', array_values(array_filter([$department, $position], static fn (string $v): bool => $v !== '')));
                $sheets[] = $this->requestDetailSheet(
                    '직원 ' . $label . ' #' . (int) $group['id'],
                    $label . ' 휴가 기록',
                    $this->groupRequestRows($group['rows']),
                    false,
                    $meta !== '' ? $meta . ' · ' . $periodLabel : $periodLabel,
                );
            }
        }

        return [
            'filename' => $filenamePrefix . '-' . $periodSlug . '.xlsx',
            'content' => $this->xlsx->buildWorkbook($sheets),
        ];
    }

    /**
     * @param list<array<string,mixed>> $dayRows
     * @param list<array<string,mixed>> $requestRows
     * @return array<string,mixed>
     */
    private function summarySheet(
        array $dayRows,
        array $requestRows,
        string $targetLabel,
        string $periodLabel,
        bool $isAdmin,
    ): array {
        $approvedDays = 0.0;
        $deductedDays = 0.0;
        $nonDeductedDays = 0.0;
        $users = [];
        $typeTotals = [];

        foreach ($dayRows as $row) {
            $users[(int) ($row['user_id'] ?? 0)] = true;
            if ((string) ($row['status'] ?? '') !== 'approved') {
                continue;
            }

            $amount = (float) ($row['day_amount'] ?? 0);
            $approvedDays += $amount;
            if ((int) ($row['deducts_annual_leave'] ?? 0) === 1) {
                $deductedDays += $amount;
            } else {
                $nonDeductedDays += $amount;
            }

            $code = (string) ($row['leave_code'] ?? '');
            if (!isset($typeTotals[$code])) {
                $typeTotals[$code] = [
                    'name' => (string) ($row['leave_type_name'] ?? $code),
                    'amount' => 0.0,
                ];
            }
            $typeTotals[$code]['amount'] += $amount;
        }

        $statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];
        foreach ($requestRows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        uasort($typeTotals, static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        $rows = [
            ['DK Annual Manager 휴가 보고서', null, null, null, null, null, null, null],
            ['단순 기록 나열이 아니라 통계·기간별·일자별 원본을 함께 제공하는 업무용 Excel 보고서입니다.', null, null, null, null, null, null, null],
            [],
            ['대상', $targetLabel, '기간', $periodLabel, '출력일', date('Y-m-d H:i:s'), '구분', $isAdmin ? '관리자 보고서' : '개인 보고서'],
            [],
            ['핵심 지표', null, null, null, null, null, null, null],
            ['총 신청', count($requestRows), '승인 휴가일', $approvedDays, '연차 차감일', $deductedDays, '비차감 휴가일', $nonDeductedDays],
            ['승인 대기', $statusCounts['pending'], '승인', $statusCounts['approved'], '반려', $statusCounts['rejected'], '취소', $statusCounts['cancelled']],
            ['대상 직원', count(array_filter(array_keys($users), static fn (int $id): bool => $id > 0)), '실제 휴가일 행', count($dayRows), null, null, null, null],
            [],
            ['휴가 종류별 승인 사용', null, null, null, null, null, null, null],
            ['휴가 종류', '승인 사용일', null, null, null, null, null, null],
        ];

        foreach ($typeTotals as $item) {
            $rows[] = [(string) $item['name'], (float) $item['amount'], null, null, null, null, null, null];
        }

        if ($typeTotals === []) {
            $rows[] = ['승인된 휴가가 없습니다.', null, null, null, null, null, null, null];
        }

        $rows[] = [];
        $rows[] = ['시트 안내', null, null, null, null, null, null, null];
        $rows[] = ['연도별 통계', '실제 휴가일을 연도별로 집계', null, null, null, null, null, null];
        $rows[] = ['월별 통계', '실제 휴가일을 연월별로 집계', null, null, null, null, null, null];
        $rows[] = ['휴가종류 통계', '휴가 종류별 승인 사용량과 신청건수', null, null, null, null, null, null];
        if ($isAdmin) {
            $rows[] = ['직원별 통계', '직원별 사용량과 상태별 신청건수', null, null, null, null, null, null];
            $rows[] = ['부서별 통계', '부서별 승인 사용량과 신청건수', null, null, null, null, null, null];
        }
        $rows[] = ['연차 현황', '선택 연도의 발생·이월·조정·사용·잔여 확인', null, null, null, null, null, null];
        if ($isAdmin) {
            $rows[] = ['검토 필요', '승인 대기·소급 입력·동일 일자 중복 승인 확인', null, null, null, null, null, null];
        }
        $rows[] = ['신청 기록', '신청 단위 상세 기록', null, null, null, null, null, null];
        $rows[] = ['일자별 기록', '실제 휴가 날짜 단위 원본', null, null, null, null, null, null];
        $rows[] = ['연도/월/직원 시트', '선택 범위를 바로 찾아볼 수 있도록 자동 분리', null, null, null, null, null, null];

        $rowStyles = [
            0 => 'title',
            1 => 'note',
            5 => 'section',
            10 => 'section',
            11 => 'header',
        ];
        $sectionIndex = count($rows) - ($isAdmin ? 11 : 8);
        $rowStyles[$sectionIndex] = 'section';

        $cellStyles = [
            'A4' => 'meta_label', 'B4' => 'meta_value',
            'C4' => 'meta_label', 'D4' => 'meta_value',
            'E4' => 'meta_label', 'F4' => 'meta_value',
            'G4' => 'meta_label', 'H4' => 'meta_value',
        ];

        foreach ([7, 8, 9] as $excelRow) {
            foreach (['A', 'C', 'E', 'G'] as $column) {
                $cellStyles[$column . $excelRow] = 'kpi_label';
            }
            foreach (['B', 'D', 'F', 'H'] as $column) {
                $cellStyles[$column . $excelRow] = 'kpi_value';
            }
        }

        $typeStart = 13;
        for ($row = $typeStart; $row <= $typeStart + max(0, count($typeTotals) - 1); $row++) {
            $cellStyles['A' . $row] = 'data';
            $cellStyles['B' . $row] = 'number';
        }

        return [
            'name' => '요약',
            'rows' => $rows,
            'row_styles' => $rowStyles,
            'cell_styles' => $cellStyles,
            'column_widths' => [20, 18, 20, 18, 20, 21, 20, 18],
            'freeze_rows' => 4,
            'merge_cells' => ['A1:H1', 'A2:H2', 'A6:H6', 'A11:H11', 'A' . ($sectionIndex + 1) . ':H' . ($sectionIndex + 1)],
            'row_heights' => [0 => 28, 1 => 28],
        ];
    }

    /** @param list<array<string,mixed>> $dayRows @return array<string,mixed> */
    private function yearStatisticsSheet(array $dayRows): array
    {
        $buckets = [];
        foreach ($dayRows as $row) {
            $key = substr((string) ($row['leave_date'] ?? ''), 0, 4);
            if ($key === '') {
                continue;
            }
            $this->addToStatisticsBucket($buckets[$key], $row);
        }
        ksort($buckets, SORT_NATURAL);

        $headers = ['연도', '신청건수', '승인건수', '승인일수', '연차차감일', '비차감일', '승인대기', '반려', '취소'];
        $data = [];
        foreach ($buckets as $key => $bucket) {
            $data[] = [
                $key,
                count($bucket['request_ids']),
                count($bucket['approved_ids']),
                $bucket['approved_days'],
                $bucket['deducted_days'],
                $bucket['non_deducted_days'],
                count($bucket['pending_ids']),
                count($bucket['rejected_ids']),
                count($bucket['cancelled_ids']),
            ];
        }

        return $this->statisticsSheet('연도별 통계', '연도별 휴가 통계', $headers, $data, [11, 12, 12, 12, 13, 13, 12, 10, 10]);
    }

    /** @param list<array<string,mixed>> $dayRows @return array<string,mixed> */
    private function monthStatisticsSheet(array $dayRows): array
    {
        $buckets = [];
        foreach ($dayRows as $row) {
            $key = substr((string) ($row['leave_date'] ?? ''), 0, 7);
            if ($key === '') {
                continue;
            }
            $this->addToStatisticsBucket($buckets[$key], $row);
        }
        ksort($buckets, SORT_NATURAL);

        $headers = ['연월', '신청건수', '승인건수', '승인일수', '연차차감일', '비차감일', '승인대기', '반려', '취소'];
        $data = [];
        foreach ($buckets as $key => $bucket) {
            $data[] = [
                $key,
                count($bucket['request_ids']),
                count($bucket['approved_ids']),
                $bucket['approved_days'],
                $bucket['deducted_days'],
                $bucket['non_deducted_days'],
                count($bucket['pending_ids']),
                count($bucket['rejected_ids']),
                count($bucket['cancelled_ids']),
            ];
        }

        return $this->statisticsSheet('월별 통계', '연월별 휴가 통계', $headers, $data, [12, 12, 12, 12, 13, 13, 12, 10, 10]);
    }

    /** @param list<array<string,mixed>> $dayRows @return array<string,mixed> */
    private function leaveTypeStatisticsSheet(array $dayRows): array
    {
        $buckets = [];
        foreach ($dayRows as $row) {
            $code = (string) ($row['leave_code'] ?? '');
            if (!isset($buckets[$code])) {
                $buckets[$code] = [
                    'name' => (string) ($row['leave_type_name'] ?? $code),
                    'deducts' => (int) ($row['deducts_annual_leave'] ?? 0),
                    'request_ids' => [],
                    'approved_ids' => [],
                    'approved_days' => 0.0,
                    'pending_ids' => [],
                    'rejected_ids' => [],
                    'cancelled_ids' => [],
                ];
            }
            $id = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $buckets[$code]['request_ids'][$id] = true;
            if ($status === 'approved') {
                $buckets[$code]['approved_ids'][$id] = true;
                $buckets[$code]['approved_days'] += (float) ($row['day_amount'] ?? 0);
            } elseif ($status === 'pending') {
                $buckets[$code]['pending_ids'][$id] = true;
            } elseif ($status === 'rejected') {
                $buckets[$code]['rejected_ids'][$id] = true;
            } elseif ($status === 'cancelled') {
                $buckets[$code]['cancelled_ids'][$id] = true;
            }
        }

        uasort($buckets, static fn (array $a, array $b): int => $b['approved_days'] <=> $a['approved_days']);

        $headers = ['휴가종류', '코드', '연차차감', '신청건수', '승인건수', '승인일수', '승인대기', '반려', '취소'];
        $data = [];
        foreach ($buckets as $code => $bucket) {
            $data[] = [
                $bucket['name'],
                $code,
                $bucket['deducts'] === 1 ? '차감' : '비차감',
                count($bucket['request_ids']),
                count($bucket['approved_ids']),
                $bucket['approved_days'],
                count($bucket['pending_ids']),
                count($bucket['rejected_ids']),
                count($bucket['cancelled_ids']),
            ];
        }

        return $this->statisticsSheet('휴가종류 통계', '휴가 종류별 통계', $headers, $data, [18, 10, 12, 12, 12, 12, 12, 10, 10]);
    }

    /** @param list<array<string,mixed>> $dayRows @return array<string,mixed> */
    private function employeeStatisticsSheet(array $dayRows): array
    {
        $buckets = [];
        foreach ($dayRows as $row) {
            $id = (int) ($row['user_id'] ?? 0);
            if (!isset($buckets[$id])) {
                $buckets[$id] = [
                    'name' => (string) ($row['user_name'] ?? ''),
                    'department' => (string) ($row['department'] ?? ''),
                    'position' => (string) ($row['position'] ?? ''),
                    'request_ids' => [],
                    'approved_ids' => [],
                    'approved_days' => 0.0,
                    'deducted_days' => 0.0,
                    'non_deducted_days' => 0.0,
                    'pending_ids' => [],
                    'rejected_ids' => [],
                    'cancelled_ids' => [],
                ];
            }

            $requestId = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $buckets[$id]['request_ids'][$requestId] = true;
            if ($status === 'approved') {
                $buckets[$id]['approved_ids'][$requestId] = true;
                $amount = (float) ($row['day_amount'] ?? 0);
                $buckets[$id]['approved_days'] += $amount;
                if ((int) ($row['deducts_annual_leave'] ?? 0) === 1) {
                    $buckets[$id]['deducted_days'] += $amount;
                } else {
                    $buckets[$id]['non_deducted_days'] += $amount;
                }
            } elseif ($status === 'pending') {
                $buckets[$id]['pending_ids'][$requestId] = true;
            } elseif ($status === 'rejected') {
                $buckets[$id]['rejected_ids'][$requestId] = true;
            } elseif ($status === 'cancelled') {
                $buckets[$id]['cancelled_ids'][$requestId] = true;
            }
        }

        uasort($buckets, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        $headers = ['직원', '부서', '직책', '신청건수', '승인건수', '승인일수', '연차차감일', '비차감일', '승인대기', '반려', '취소'];
        $data = [];
        foreach ($buckets as $bucket) {
            $data[] = [
                $bucket['name'],
                $bucket['department'],
                $bucket['position'],
                count($bucket['request_ids']),
                count($bucket['approved_ids']),
                $bucket['approved_days'],
                $bucket['deducted_days'],
                $bucket['non_deducted_days'],
                count($bucket['pending_ids']),
                count($bucket['rejected_ids']),
                count($bucket['cancelled_ids']),
            ];
        }

        return $this->statisticsSheet('직원별 통계', '직원별 휴가 통계', $headers, $data, [16, 16, 14, 12, 12, 12, 13, 12, 12, 10, 10]);
    }

    /** @param list<array<string,mixed>> $dayRows @return array<string,mixed> */
    private function departmentStatisticsSheet(array $dayRows): array
    {
        $buckets = [];

        foreach ($dayRows as $row) {
            $department = trim((string) ($row['department'] ?? ''));
            if ($department === '') {
                $department = '미지정';
            }

            if (!isset($buckets[$department])) {
                $buckets[$department] = [
                    'users' => [],
                    'request_ids' => [],
                    'approved_ids' => [],
                    'approved_days' => 0.0,
                    'deducted_days' => 0.0,
                    'non_deducted_days' => 0.0,
                    'pending_ids' => [],
                    'rejected_ids' => [],
                    'cancelled_ids' => [],
                ];
            }

            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId > 0) {
                $buckets[$department]['users'][$userId] = true;
            }

            $requestId = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $buckets[$department]['request_ids'][$requestId] = true;

            if ($status === 'approved') {
                $buckets[$department]['approved_ids'][$requestId] = true;
                $amount = (float) ($row['day_amount'] ?? 0);
                $buckets[$department]['approved_days'] += $amount;

                if ((int) ($row['deducts_annual_leave'] ?? 0) === 1) {
                    $buckets[$department]['deducted_days'] += $amount;
                } else {
                    $buckets[$department]['non_deducted_days'] += $amount;
                }
            } elseif ($status === 'pending') {
                $buckets[$department]['pending_ids'][$requestId] = true;
            } elseif ($status === 'rejected') {
                $buckets[$department]['rejected_ids'][$requestId] = true;
            } elseif ($status === 'cancelled') {
                $buckets[$department]['cancelled_ids'][$requestId] = true;
            }
        }

        uasort(
            $buckets,
            static fn (array $a, array $b): int => $b['approved_days'] <=> $a['approved_days'],
        );

        $headers = ['부서', '대상직원', '신청건수', '승인건수', '승인일수', '연차차감일', '비차감일', '승인대기', '반려', '취소'];
        $data = [];
        foreach ($buckets as $department => $bucket) {
            $data[] = [
                $department,
                count($bucket['users']),
                count($bucket['request_ids']),
                count($bucket['approved_ids']),
                $bucket['approved_days'],
                $bucket['deducted_days'],
                $bucket['non_deducted_days'],
                count($bucket['pending_ids']),
                count($bucket['rejected_ids']),
                count($bucket['cancelled_ids']),
            ];
        }

        return $this->statisticsSheet(
            '부서별 통계',
            '부서별 휴가 통계',
            $headers,
            $data,
            [18, 12, 12, 12, 12, 13, 12, 12, 10, 10],
            '부서가 비어 있는 사용자는 미지정으로 묶습니다. 승인일수는 실제 휴가 날짜 기준입니다.',
        );
    }

    /** @return array<string,mixed> */
    private function annualLeaveBalanceSheet(?int $userId, int $year, bool $isAdmin): array
    {
        if (!$isAdmin && $userId !== null) {
            $summary = $this->reports->userAnnualSummary($userId, $year);
            $headers = ['연도', '발생', '이월', '조정', '복원', '사용', '순사용', '잔여', '총가용', '소진율'];
            $total = (float) ($summary['total'] ?? 0);
            $netUsed = (float) ($summary['net_used'] ?? 0);
            $data = [[
                $year,
                (float) ($summary['granted'] ?? 0),
                (float) ($summary['carryover'] ?? 0),
                (float) ($summary['adjustment'] ?? 0),
                (float) ($summary['reversal'] ?? 0),
                (float) ($summary['used'] ?? 0),
                $netUsed,
                (float) ($summary['balance'] ?? 0),
                $total,
                $total > 0 ? round(($netUsed / $total) * 100, 1) . '%' : '0.0%',
            ]];

            return $this->statisticsSheet(
                '연차 현황',
                $year . '년 연차 현황',
                $headers,
                $data,
                [10, 11, 11, 11, 11, 11, 11, 11, 11, 11],
                '연차 원장 기준입니다. 비차감 휴가(공가·병가 등)는 연차 사용량에 포함되지 않습니다.',
            );
        }

        $rows = $this->reports->annualUserSummary($year);
        if ($userId !== null) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => (int) ($row['id'] ?? 0) === $userId,
            ));
        }

        $headers = ['직원', '부서', '직책', '상태', '입사일', '발생', '이월', '조정', '복원', '사용', '순사용', '잔여', '총가용', '소진율'];
        $data = [];
        foreach ($rows as $row) {
            $used = (float) ($row['used'] ?? 0);
            $reversal = (float) ($row['reversal'] ?? 0);
            $balance = (float) ($row['balance'] ?? 0);
            $netUsed = max(0.0, $used - $reversal);
            $data[] = [
                (string) ($row['name'] ?? ''),
                (string) ($row['department'] ?? ''),
                (string) ($row['position'] ?? ''),
                (string) ($row['status'] ?? ''),
                (string) ($row['hire_date'] ?? ''),
                (float) ($row['granted'] ?? 0),
                (float) ($row['carryover'] ?? 0),
                (float) ($row['adjustment'] ?? 0),
                $reversal,
                $used,
                $netUsed,
                $balance,
                $balance + $netUsed,
                ($balance + $netUsed) > 0 ? round(($netUsed / ($balance + $netUsed)) * 100, 1) . '%' : '0.0%',
            ];
        }

        return $this->statisticsSheet(
            '연차 현황',
            $year . '년 직원별 연차 현황',
            $headers,
            $data,
            [16, 15, 14, 11, 12, 11, 11, 11, 11, 11, 11, 11, 11, 11],
            '연차 원장 기준입니다. 상태 열은 계정 상태이며, 휴가 신청 상태와는 별개입니다.',
        );
    }

    /**
     * @param list<array<string,mixed>> $dayRows
     * @param list<array<string,mixed>> $requestRows
     * @return array<string,mixed>
     */
    private function reviewSheet(array $dayRows, array $requestRows): array
    {
        $issues = [];

        foreach ($requestRows as $row) {
            if ((string) ($row['status'] ?? '') === 'pending') {
                $issues[] = [
                    '승인 대기',
                    (int) $row['id'],
                    (string) ($row['user_name'] ?? ''),
                    (string) ($row['start_date'] ?? ''),
                    (string) ($row['end_date'] ?? ''),
                    '아직 승인 또는 반려되지 않은 신청입니다.',
                ];
            }

            $createdDate = substr((string) ($row['created_at'] ?? ''), 0, 10);
            $firstLeaveDate = (string) ($row['first_leave_date'] ?? '');
            if ($createdDate !== '' && $firstLeaveDate !== '' && $firstLeaveDate < $createdDate) {
                $issues[] = [
                    '과거일 소급 입력',
                    (int) $row['id'],
                    (string) ($row['user_name'] ?? ''),
                    (string) ($row['start_date'] ?? ''),
                    (string) ($row['end_date'] ?? ''),
                    '신청 생성일보다 앞선 실제 휴가일이 포함되어 있습니다.',
                ];
            }
        }

        $duplicates = [];
        foreach ($dayRows as $row) {
            if ((string) ($row['status'] ?? '') !== 'approved') {
                continue;
            }
            $key = (int) ($row['user_id'] ?? 0) . '|' . (string) ($row['leave_date'] ?? '');
            $duplicates[$key][] = $row;
        }
        foreach ($duplicates as $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $ids = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
            $issues[] = [
                '동일 일자 중복 승인',
                implode(', ', $ids),
                (string) ($rows[0]['user_name'] ?? ''),
                (string) ($rows[0]['leave_date'] ?? ''),
                (string) ($rows[0]['leave_date'] ?? ''),
                '동일 직원의 같은 날짜에 승인 휴가가 여러 건 존재합니다.',
            ];
        }

        $headers = ['검토 항목', '신청번호', '직원', '시작/일자', '종료일', '설명'];
        return $this->statisticsSheet(
            '검토 필요',
            '업무 검토 필요 항목',
            $headers,
            $issues,
            [20, 14, 16, 14, 14, 46],
            '자동 판정 결과이므로 최종 판단은 담당자가 원본 신청 기록과 함께 확인해 주세요.',
        );
    }

    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $data
     * @param list<int|float> $widths
     * @return array<string,mixed>
     */
    private function statisticsSheet(
        string $sheetName,
        string $title,
        array $headers,
        array $data,
        array $widths,
        string $note = '승인 사용량은 실제 휴가 날짜(leave_request_days) 기준으로 집계합니다.',
    ): array {
        $rows = [
            [$title],
            [$note],
            [],
            $headers,
        ];

        foreach ($data as $row) {
            $rows[] = $row;
        }
        if ($data === []) {
            $rows[] = ['데이터가 없습니다.'];
        }

        $lastColumn = $this->columnName(count($headers));
        $cellStyles = [];
        foreach ($data as $rowIndex => $dataRow) {
            $excelRow = $rowIndex + 5;
            foreach ($dataRow as $columnIndex => $value) {
                $style = is_int($value) ? 'integer' : (is_float($value) ? 'number' : 'data');
                $cellStyles[$this->columnName($columnIndex + 1) . $excelRow] = $style;
            }
        }

        return [
            'name' => $sheetName,
            'rows' => $rows,
            'row_styles' => [0 => 'title', 1 => 'note', 3 => 'header'],
            'cell_styles' => $cellStyles,
            'column_widths' => $widths,
            'freeze_rows' => 4,
            'auto_filter' => 'A4:' . $lastColumn . max(4, 4 + count($data)),
            'merge_cells' => ['A1:' . $lastColumn . '1', 'A2:' . $lastColumn . '2'],
            'row_heights' => [0 => 26, 1 => 24],
        ];
    }

    /**
     * @param list<array<string,mixed>> $requestRows
     * @return array<string,mixed>
     */
    private function requestDetailSheet(
        string $sheetName,
        string $title,
        array $requestRows,
        bool $includeEmployeeColumns,
        string $note,
    ): array {
        $headers = ['신청번호'];
        if ($includeEmployeeColumns) {
            array_push($headers, '직원', '부서', '직책');
        }
        array_push(
            $headers,
            '휴가종류',
            '연차차감',
            '반차',
            '시작일',
            '종료일',
            '원신청일수',
            '범위내일수',
            '범위내실제일자',
            '상태',
            '사유',
            '신청시각',
            '승인자',
            '승인시각',
            '관리자메모',
            '취소구분',
            '취소자',
            '취소시각',
            '취소사유',
        );

        $rows = [[$title], [$note], [], $headers];
        $cellStyles = [];

        foreach ($requestRows as $index => $record) {
            $row = [(int) $record['id']];
            if ($includeEmployeeColumns) {
                array_push(
                    $row,
                    (string) ($record['user_name'] ?? ''),
                    (string) ($record['department'] ?? ''),
                    (string) ($record['position'] ?? ''),
                );
            }

            array_push(
                $row,
                (string) ($record['leave_type_name'] ?? ''),
                (int) ($record['deducts_annual_leave'] ?? 0) === 1 ? '차감' : '비차감',
                $this->halfDayLabel((string) ($record['half_day_period'] ?? '')),
                $this->excelDate((string) ($record['start_date'] ?? '')),
                $this->excelDate((string) ($record['end_date'] ?? '')),
                (float) ($record['requested_amount'] ?? 0),
                (float) ($record['period_amount'] ?? 0),
                implode(', ', $record['leave_dates'] ?? []),
                $this->statusLabel((string) ($record['status'] ?? '')),
                (string) ($record['reason'] ?? ''),
                $this->excelDateTime((string) ($record['created_at'] ?? '')),
                (string) ($record['reviewed_by_name'] ?? ''),
                $this->excelDateTime((string) ($record['reviewed_at'] ?? '')),
                (string) ($record['review_note'] ?? ''),
                $this->cancellationSourceLabel((string) ($record['cancellation_source'] ?? '')),
                (string) ($record['cancelled_by_name'] ?? ''),
                $this->excelDateTime((string) ($record['cancelled_at'] ?? '')),
                (string) ($record['cancellation_note'] ?? ''),
            );
            $rows[] = $row;

            $excelRow = $index + 5;
            $offset = $includeEmployeeColumns ? 4 : 1;
            $cellStyles[$this->columnName($offset + 4) . $excelRow] = 'date';
            $cellStyles[$this->columnName($offset + 5) . $excelRow] = 'date';
            $cellStyles[$this->columnName($offset + 6) . $excelRow] = 'number';
            $cellStyles[$this->columnName($offset + 7) . $excelRow] = 'number';
            $cellStyles[$this->columnName($offset + 8) . $excelRow] = 'data_wrap';
            $cellStyles[$this->columnName($offset + 9) . $excelRow] = $this->statusStyle((string) ($record['status'] ?? ''));
            $cellStyles[$this->columnName($offset + 10) . $excelRow] = 'data_wrap';
            $cellStyles[$this->columnName($offset + 11) . $excelRow] = 'datetime';
            $cellStyles[$this->columnName($offset + 13) . $excelRow] = 'datetime';
            $cellStyles[$this->columnName($offset + 14) . $excelRow] = 'data_wrap';
            $cellStyles[$this->columnName($offset + 17) . $excelRow] = 'datetime';
            $cellStyles[$this->columnName($offset + 18) . $excelRow] = 'data_wrap';

            for ($column = 1; $column <= count($headers); $column++) {
                $ref = $this->columnName($column) . $excelRow;
                if (!isset($cellStyles[$ref])) {
                    $cellStyles[$ref] = 'data';
                }
            }
        }

        $lastColumn = $this->columnName(count($headers));
        $widths = $includeEmployeeColumns
            ? [10, 15, 15, 14, 15, 11, 9, 12, 12, 12, 12, 34, 12, 30, 19, 15, 19, 26, 14, 15, 19, 28]
            : [10, 15, 11, 9, 12, 12, 12, 12, 34, 12, 30, 19, 15, 19, 26, 14, 15, 19, 28];

        return [
            'name' => $sheetName,
            'rows' => $rows,
            'row_styles' => [0 => 'title', 1 => 'note', 3 => 'header'],
            'cell_styles' => $cellStyles,
            'column_widths' => $widths,
            'freeze_rows' => 4,
            'freeze_columns' => 1,
            'auto_filter' => 'A4:' . $lastColumn . max(4, 4 + count($requestRows)),
            'merge_cells' => ['A1:' . $lastColumn . '1', 'A2:' . $lastColumn . '2'],
            'row_heights' => [0 => 26, 1 => 24],
        ];
    }

    /** @param list<array<string,mixed>> $dayRows @return array<string,mixed> */
    private function dayDetailSheet(
        string $sheetName,
        string $title,
        array $dayRows,
        bool $includeEmployeeColumns,
    ): array {
        $headers = [];
        if ($includeEmployeeColumns) {
            $headers = ['직원', '부서', '직책'];
        }
        array_push($headers, '휴가일', '일수', '휴가종류', '코드', '연차차감', '반차', '상태', '신청번호', '신청 시작일', '신청 종료일', '사유');

        $rows = [[$title], ['통계와 기간 분리는 이 실제 휴가일 데이터를 기준으로 계산됩니다.'], [], $headers];
        $cellStyles = [];

        foreach ($dayRows as $index => $record) {
            $row = [];
            if ($includeEmployeeColumns) {
                array_push(
                    $row,
                    (string) ($record['user_name'] ?? ''),
                    (string) ($record['department'] ?? ''),
                    (string) ($record['position'] ?? ''),
                );
            }
            array_push(
                $row,
                $this->excelDate((string) ($record['leave_date'] ?? '')),
                (float) ($record['day_amount'] ?? 0),
                (string) ($record['leave_type_name'] ?? ''),
                (string) ($record['leave_code'] ?? ''),
                (int) ($record['deducts_annual_leave'] ?? 0) === 1 ? '차감' : '비차감',
                $this->halfDayLabel((string) ($record['half_day_period'] ?? '')),
                $this->statusLabel((string) ($record['status'] ?? '')),
                (int) ($record['id'] ?? 0),
                $this->excelDate((string) ($record['start_date'] ?? '')),
                $this->excelDate((string) ($record['end_date'] ?? '')),
                (string) ($record['reason'] ?? ''),
            );
            $rows[] = $row;

            $excelRow = $index + 5;
            $offset = $includeEmployeeColumns ? 3 : 0;
            $cellStyles[$this->columnName($offset + 1) . $excelRow] = 'date';
            $cellStyles[$this->columnName($offset + 2) . $excelRow] = 'number';
            $cellStyles[$this->columnName($offset + 7) . $excelRow] = $this->statusStyle((string) ($record['status'] ?? ''));
            $cellStyles[$this->columnName($offset + 9) . $excelRow] = 'date';
            $cellStyles[$this->columnName($offset + 10) . $excelRow] = 'date';
            $cellStyles[$this->columnName($offset + 11) . $excelRow] = 'data_wrap';

            for ($column = 1; $column <= count($headers); $column++) {
                $ref = $this->columnName($column) . $excelRow;
                if (!isset($cellStyles[$ref])) {
                    $cellStyles[$ref] = 'data';
                }
            }
        }

        $lastColumn = $this->columnName(count($headers));
        $widths = $includeEmployeeColumns
            ? [15, 15, 14, 12, 9, 15, 9, 11, 9, 12, 11, 12, 12, 30]
            : [12, 9, 15, 9, 11, 9, 12, 11, 12, 12, 30];

        return [
            'name' => $sheetName,
            'rows' => $rows,
            'row_styles' => [0 => 'title', 1 => 'note', 3 => 'header'],
            'cell_styles' => $cellStyles,
            'column_widths' => $widths,
            'freeze_rows' => 4,
            'freeze_columns' => $includeEmployeeColumns ? 1 : 0,
            'auto_filter' => 'A4:' . $lastColumn . max(4, 4 + count($dayRows)),
            'merge_cells' => ['A1:' . $lastColumn . '1', 'A2:' . $lastColumn . '2'],
            'row_heights' => [0 => 26, 1 => 24],
        ];
    }

    /** @param array<string,mixed>|null $bucket */
    private function addToStatisticsBucket(?array &$bucket, array $row): void
    {
        if ($bucket === null) {
            $bucket = [
                'request_ids' => [],
                'approved_ids' => [],
                'approved_days' => 0.0,
                'deducted_days' => 0.0,
                'non_deducted_days' => 0.0,
                'pending_ids' => [],
                'rejected_ids' => [],
                'cancelled_ids' => [],
            ];
        }

        $id = (int) ($row['id'] ?? 0);
        $status = (string) ($row['status'] ?? '');
        $bucket['request_ids'][$id] = true;

        if ($status === 'approved') {
            $bucket['approved_ids'][$id] = true;
            $amount = (float) ($row['day_amount'] ?? 0);
            $bucket['approved_days'] += $amount;
            if ((int) ($row['deducts_annual_leave'] ?? 0) === 1) {
                $bucket['deducted_days'] += $amount;
            } else {
                $bucket['non_deducted_days'] += $amount;
            }
        } elseif ($status === 'pending') {
            $bucket['pending_ids'][$id] = true;
        } elseif ($status === 'rejected') {
            $bucket['rejected_ids'][$id] = true;
        } elseif ($status === 'cancelled') {
            $bucket['cancelled_ids'][$id] = true;
        }
    }

    /** @param list<array<string,mixed>> $dayRows @return list<array<string,mixed>> */
    private function groupRequestRows(array $dayRows): array
    {
        $grouped = [];

        foreach ($dayRows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if (!isset($grouped[$id])) {
                $grouped[$id] = $row;
                $grouped[$id]['period_amount'] = 0.0;
                $grouped[$id]['leave_dates'] = [];
                $grouped[$id]['first_leave_date'] = '';
            }

            $date = (string) ($row['leave_date'] ?? '');
            $grouped[$id]['period_amount'] += (float) ($row['day_amount'] ?? 0);
            if ($date !== '') {
                $grouped[$id]['leave_dates'][] = $date;
                if ($grouped[$id]['first_leave_date'] === '' || $date < $grouped[$id]['first_leave_date']) {
                    $grouped[$id]['first_leave_date'] = $date;
                }
            }
        }

        usort(
            $grouped,
            static fn (array $a, array $b): int =>
                strcmp((string) ($a['first_leave_date'] ?? ''), (string) ($b['first_leave_date'] ?? ''))
                ?: strnatcasecmp((string) ($a['user_name'] ?? ''), (string) ($b['user_name'] ?? ''))
                ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0)),
        );

        return array_values($grouped);
    }

    /** @param list<array<string,mixed>> $dayRows @return array<string,list<array<string,mixed>>> */
    private function groupDaysByYear(array $dayRows): array
    {
        $groups = [];
        foreach ($dayRows as $row) {
            $key = substr((string) ($row['leave_date'] ?? ''), 0, 4);
            if ($key !== '') {
                $groups[$key][] = $row;
            }
        }
        ksort($groups, SORT_NATURAL);
        return $groups;
    }

    /** @param list<array<string,mixed>> $dayRows @return array<string,list<array<string,mixed>>> */
    private function groupDaysByMonth(array $dayRows): array
    {
        $groups = [];
        foreach ($dayRows as $row) {
            $key = substr((string) ($row['leave_date'] ?? ''), 0, 7);
            if ($key !== '') {
                $groups[$key][] = $row;
            }
        }
        ksort($groups, SORT_NATURAL);
        return $groups;
    }

    /**
     * @param list<array<string,mixed>> $dayRows
     * @return list<array{id:int,name:string,department:string,position:string,rows:list<array<string,mixed>>}>
     */
    private function groupDaysByUser(array $dayRows): array
    {
        $groups = [];
        foreach ($dayRows as $row) {
            $id = (int) ($row['user_id'] ?? 0);
            if (!isset($groups[$id])) {
                $groups[$id] = [
                    'id' => $id,
                    'name' => (string) ($row['user_name'] ?? ''),
                    'department' => (string) ($row['department'] ?? ''),
                    'position' => (string) ($row['position'] ?? ''),
                    'rows' => [],
                ];
            }
            $groups[$id]['rows'][] = $row;
        }

        uasort($groups, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return array_values($groups);
    }

    /** @return array{0:?string,1:?string,2:string,3:string} */
    private function period(string $period, int $year, int $month): array
    {
        if ($period === 'month') {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));

            return [$start, $end, sprintf('%d년 %d월', $year, $month), sprintf('%04d-%02d', $year, $month)];
        }

        if ($period === 'all') {
            return [null, null, '전체 기간', 'all'];
        }

        return [
            sprintf('%04d-01-01', $year),
            sprintf('%04d-12-31', $year),
            sprintf('%d년', $year),
            (string) $year,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => '승인 대기',
            'approved' => '승인',
            'rejected' => '반려',
            'cancelled' => '취소',
            default => $status,
        };
    }

    private function statusStyle(string $status): string
    {
        return match ($status) {
            'approved' => 'status_approved',
            'pending' => 'status_pending',
            'rejected' => 'status_rejected',
            'cancelled' => 'status_cancelled',
            default => 'data',
        };
    }

    private function halfDayLabel(string $value): string
    {
        return match ($value) {
            'am' => '오전',
            'pm' => '오후',
            default => '',
        };
    }

    private function cancellationSourceLabel(string $source): string
    {
        return match ($source) {
            'user' => '사용자 취소',
            'admin' => '관리자 취소',
            default => '',
        };
    }

    private function excelDate(string $value): float|string|null
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10), new DateTimeZone('UTC'));
        if ($date === false) {
            return $value;
        }

        $origin = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
        return (float) $origin->diff($date)->days;
    }

    private function excelDateTime(string $value): float|string|null
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (\Throwable) {
            return $value;
        }

        $origin = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
        $seconds = $date->getTimestamp() - $origin->getTimestamp();
        return $seconds / 86400;
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)) . $name;
            $column = intdiv($column, 26);
        }
        return $name;
    }
}
