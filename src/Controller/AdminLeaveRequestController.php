<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\LeaveReviewService;
use DKAnnual\Repository\LeaveRequestRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\Telegram\LeaveNotificationService;
use DKAnnual\View\View;

final class AdminLeaveRequestController
{
    public function __construct(
        private readonly LeaveRequestRepository $requests,
        private readonly LeaveReviewService $reviewer,
        private readonly LeaveNotificationService $notifications,
        private readonly Auth $auth,
        private readonly View $view,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('admin-leave-requests', [
            'title' => '휴가 승인',
            'requests' => $this->requests->pendingForAdmin(),
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
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

        $this->notifications->notifyUserOfDecision($result['request']);

        return Response::redirect('/admin/requests?message=' . rawurlencode(
            $action === 'approve' ? '휴가 신청을 승인했습니다.' : '휴가 신청을 반려했습니다.'
        ));
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = (string) ($value ?? '');
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
