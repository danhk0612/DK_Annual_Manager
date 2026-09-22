<?php

declare(strict_types=1);

namespace DKAnnual\Export;

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
        $records = $this->reports->leaveExportRows($userId, $startDate, $endDate);

        $rows = [
            ['DK Annual Manager 휴가 내보내기'],
            ['대상', $targetLabel],
            ['기간', $periodLabel],
            ['출력일', date('Y-m-d H:i:s')],
            [],
        ];

        $headers = ['신청번호'];
        if ($includeEmployeeColumns) {
            array_push($headers, '직원', '부서', '직책');
        }
        array_push(
            $headers,
            '휴가종류',
            '반차구분',
            '시작일',
            '종료일',
            '신청일수',
            '기간내일수',
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
        $rows[] = $headers;

        foreach ($records as $record) {
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
                $this->halfDayLabel((string) ($record['half_day_period'] ?? '')),
                (string) ($record['start_date'] ?? ''),
                (string) ($record['end_date'] ?? ''),
                (float) ($record['requested_amount'] ?? 0),
                (float) ($record['period_amount'] ?? 0),
                $this->statusLabel((string) ($record['status'] ?? '')),
                (string) ($record['reason'] ?? ''),
                (string) ($record['created_at'] ?? ''),
                (string) ($record['reviewed_by_name'] ?? ''),
                (string) ($record['reviewed_at'] ?? ''),
                (string) ($record['review_note'] ?? ''),
                $this->cancellationSourceLabel((string) ($record['cancellation_source'] ?? '')),
                (string) ($record['cancelled_by_name'] ?? ''),
                (string) ($record['cancelled_at'] ?? ''),
                (string) ($record['cancellation_note'] ?? ''),
            );
            $rows[] = $row;
        }

        $widths = $includeEmployeeColumns
            ? [10, 14, 14, 14, 14, 11, 12, 12, 11, 11, 12, 28, 19, 14, 19, 24, 12, 14, 19, 24]
            : [10, 14, 11, 12, 12, 11, 11, 12, 28, 19, 14, 19, 24, 12, 14, 19, 24];

        return [
            'filename' => $filenamePrefix . '-' . $periodSlug . '.xlsx',
            'content' => $this->xlsx->build(
                '휴가 내역',
                $rows,
                [5],
                [0],
                $widths,
                6,
            ),
        ];
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
}
