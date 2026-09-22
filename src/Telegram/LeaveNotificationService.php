<?php

declare(strict_types=1);

namespace DKAnnual\Telegram;

use DKAnnual\Config;
use DKAnnual\Repository\AppSettingRepository;
use DKAnnual\Repository\UserRepository;
use Throwable;

final class LeaveNotificationService
{
    public function __construct(
        private readonly Config $config,
        private readonly TelegramBotClient $bot,
        private readonly UserRepository $users,
        private readonly AppSettingRepository $settings,
    ) {
    }

    /** @param array<string, mixed> $request */
    public function notifyAdminsOfRequest(array $request): int
    {
        $targets = $this->adminTargets();
        if ($targets === []) {
            return 0;
        }

        $reason = trim((string) ($request['reason'] ?? ''));
        $halfDayPeriod = (string) ($request['half_day_period'] ?? '');
        $halfDayLabel = $halfDayPeriod === 'am' ? '오전 반차' : ($halfDayPeriod === 'pm' ? '오후 반차' : '');
        $balanceWarning = trim((string) ($request['balance_warning'] ?? ''));
        $text = sprintf(
            "[휴가 신청]\n신청번호: #%d\n직원: %s\n종류: %s%s\n기간: %s ~ %s\n차감/기록 일수: %.1f일%s%s",
            (int) $request['id'],
            (string) $request['user_name'],
            (string) $request['leave_type_name'],
            $halfDayLabel !== '' ? ' (' . $halfDayLabel . ')' : '',
            (string) $request['start_date'],
            (string) $request['end_date'],
            (float) $request['requested_amount'],
            $reason !== '' ? "\n사유: " . $reason : '',
            $balanceWarning !== '' ? "\n주의: " . $balanceWarning : '',
        );

        return $this->sendToMany($targets, $text);
    }

    /** @param array<string, mixed> $request */
    public function notifyUserOfDecision(array $request): bool
    {
        $telegramUserId = $request['telegram_user_id'] ?? null;
        if (!is_int($telegramUserId) && !(is_string($telegramUserId) && ctype_digit($telegramUserId))) {
            return false;
        }

        $status = (string) ($request['status'] ?? '');
        $statusLabel = match ($status) {
            'approved' => '승인',
            'cancelled' => '승인 취소',
            default => '반려',
        };
        $reviewNote = trim((string) ($request['review_note'] ?? ''));
        $halfDayPeriod = (string) ($request['half_day_period'] ?? '');
        $halfDayLabel = $halfDayPeriod === 'am' ? '오전 반차' : ($halfDayPeriod === 'pm' ? '오후 반차' : '');
        $text = sprintf(
            "[휴가 %s]\n신청번호: #%d\n종류: %s%s\n기간: %s ~ %s\n일수: %.1f일%s",
            $statusLabel,
            (int) $request['id'],
            (string) $request['leave_type_name'],
            $halfDayLabel !== '' ? ' (' . $halfDayLabel . ')' : '',
            (string) $request['start_date'],
            (string) $request['end_date'],
            (float) $request['requested_amount'],
            $reviewNote !== '' ? "\n관리자 메모: " . $reviewNote : '',
        );

        try {
            $this->bot->sendMessage((int) $telegramUserId, $text);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $request */
    public function notifyCompanyOfApprovedLeave(array $request): bool
    {
        return $this->notifyCompany($request, '휴가 일정 등록');
    }

    /** @param array<string, mixed> $request */
    public function notifyCompanyOfCancelledLeave(array $request): bool
    {
        return $this->notifyCompany($request, '휴가 일정 취소');
    }

    /** @param array<string, mixed> $request */
    private function notifyCompany(array $request, string $heading): bool
    {
        $chatId = $this->companyChatId();
        if ($chatId === null) {
            return false;
        }

        $halfDayPeriod = (string) ($request['half_day_period'] ?? '');
        $halfDayLabel = $halfDayPeriod === 'am' ? '오전 반차' : ($halfDayPeriod === 'pm' ? '오후 반차' : '');
        $text = sprintf(
            "[%s]\n직원: %s\n종류: %s%s\n기간: %s ~ %s\n일수: %.1f일",
            $heading,
            (string) ($request['user_name'] ?? ''),
            (string) ($request['leave_type_name'] ?? ''),
            $halfDayLabel !== '' ? ' (' . $halfDayLabel . ')' : '',
            (string) ($request['start_date'] ?? ''),
            (string) ($request['end_date'] ?? ''),
            (float) ($request['requested_amount'] ?? 0),
        );

        try {
            $this->bot->sendMessage($chatId, $text);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<int|string> */
    private function adminTargets(): array
    {
        $targets = [];
        foreach ($this->users->activeAdminTelegramIds() as $telegramUserId) {
            $targets[] = $telegramUserId;
        }

        $unique = [];
        foreach ($targets as $target) {
            $unique[(string) $target] = $target;
        }

        return array_values($unique);
    }

    private function companyChatId(): int|string|null
    {
        $managed = trim((string) $this->settings->get('telegram.company_chat_id', ''));
        if ($managed !== '' && preg_match('/^-?\d+$/', $managed) === 1) {
            return $managed;
        }

        $configured = trim((string) $this->config->get('telegram.company_chat_id', ''));
        if ($configured !== '' && preg_match('/^-?\d+$/', $configured) === 1) {
            return $configured;
        }

        return null;
    }

    /** @param list<int|string> $targets */
    private function sendToMany(array $targets, string $text): int
    {
        $sent = 0;
        foreach ($targets as $target) {
            try {
                $this->bot->sendMessage($target, $text);
                $sent++;
            } catch (Throwable) {
                // Notification failure must not roll back a leave request or review.
            }
        }

        return $sent;
    }
}
