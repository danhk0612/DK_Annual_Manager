<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\LeaveReviewService;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\Repository\LeaveTypeRepository;
use DKAnnual\Repository\UserRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\Telegram\LeaveNotificationService;
use DKAnnual\View\View;

final class AdminLeaveRequestController
{
    public function __construct(
        private readonly LeaveRequestRepository $requests,
        private readonly UserRepository $users,
        private readonly LeaveTypeRepository $leaveTypes,
        private readonly LeaveReviewService $reviewer,
        private readonly LeaveNotificationService $notifications,
        private readonly Auth $auth,
        private readonly AuditLogRepository $audit,
        private readonly View $view,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin-leave-requests', [
            'title' => '휴가 승인',
            'requests' => $this->requests->pendingForAdmin(),
            'approvedRequests' => $this->requests->approvedForAdmin(30),
            'users' => $this->users->active(),
            'leaveTypes' => $this->leaveTypes->active(),
            'reasonCategories' => ['개인 사유', '가족 행사', '병원/건강', '업무 관련', '기타'],
            'today' => date('Y-m-d'),
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'warning' => $request->input('warning'),
            'error' => $request->input('error'),
        ]));
    }

    public function review(Request $request): Response
    {
        $actor = $this->auth->user();
        if ($actor === null) {
            return Response::redirect('/login');
        }

        $requestId = $this->positiveInt($request->input('request_id'));
        $action = (string) $request->input('action', '');
        $note = trim((string) $request->input('review_note', ''));

        if ($requestId === null || !in_array($action, ['approve', 'reject'], true)) {
            return Response::redirect('/admin/requests?error=' . rawurlencode('처리할 신청을 확인해 주세요.'));
        }

        $result = $this->reviewer->review(
            $requestId,
            $action,
            (int) $actor['id'],
            $note !== '' ? $note : null,
        );

        if (!$result['changed'] || $result['request'] === null) {
            return Response::redirect('/admin/requests?error=' . rawurlencode('이미 처리되었거나 존재하지 않는 신청입니다.'));
        }

        $this->audit->record(
            (int) $actor['id'],
            $action === 'approve' ? 'leave.request_approved' : 'leave.request_rejected',
            'leave_request',
            $requestId,
            ['status' => $action === 'approve' ? 'approved' : 'rejected'],
            $this->ip($request),
        );

        $this->notifications->notifyUserOfDecision($result['request']);

        return Response::redirect('/admin/requests?message=' . rawurlencode(
            $action === 'approve' ? '휴가 신청을 승인했습니다.' : '휴가 신청을 반려했습니다.'
        ));
    }

    public function cancelApproved(Request $request): Response
    {
        $actor = $this->auth->user();
        if ($actor === null) {
            return Response::redirect('/login');
        }

        $requestId = $this->positiveInt($request->input('request_id'));
        $note = trim((string) $request->input('review_note', ''));

        if ($requestId === null) {
            return Response::redirect('/admin/requests?error=' . rawurlencode('취소할 승인 내역을 확인해 주세요.'));
        }

        $result = $this->reviewer->cancelApproved(
            $requestId,
            (int) $actor['id'],
            $note !== '' ? $note : '관리자 승인 취소',
        );

        if (!$result['changed'] || $result['request'] === null) {
            return Response::redirect('/admin/requests?error=' . rawurlencode('승인 상태인 휴가를 찾지 못했습니다.'));
        }

        $this->audit->record(
            (int) $actor['id'],
            'leave.request_approval_cancelled',
            'leave_request',
            $requestId,
            ['status' => 'cancelled', 'note' => $note !== '' ? $note : null],
            $this->ip($request),
        );

        $this->notifications->notifyUserOfDecision($result['request']);

        return Response::redirect('/admin/requests?message=' . rawurlencode(
            '승인을 취소하고 차감된 연차를 복원했습니다. 일정 변경이 필요하면 아래 대리 신청으로 새 일정을 등록하세요.'
        ));
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = (string) ($value ?? '');
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function ip(Request $request): ?string
    {
        $ip = trim((string) $request->server('REMOTE_ADDR', ''));
        return $ip !== '' ? $ip : null;
    }
}
