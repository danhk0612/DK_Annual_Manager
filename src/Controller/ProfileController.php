<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Auth\Auth;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Leave\AnnualLeaveService;
use DKAnnual\Repository\AuditLogRepository;
use DKAnnual\Repository\UserRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\View\View;

final class ProfileController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly UserRepository $users,
        private readonly AnnualLeaveService $annualLeave,
        private readonly AuditLogRepository $audit,
        private readonly View $view,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('profile', [
            'title' => '내 정보',
            'user' => $this->auth->user(),
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'error' => $request->input('error'),
        ]));
    }

    public function saveProfile(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $value = trim((string) $request->input('hire_date', ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return Response::redirect('/profile?error=' . rawurlencode('입사일을 확인해 주세요.'));
        }

        $department = $this->optionalText($request->input('department'), 100);
        $position = $this->optionalText($request->input('position'), 100);

        $this->users->updateOwnProfile((int) $user['id'], $value, $department, $position);

        $updated = $this->users->findById((int) $user['id']);
        $added = 0;
        if ($updated !== null) {
            $hireDateChanged = ($user['hire_date'] ?? null) !== $value;
            $added = $hireDateChanged
                ? $this->annualLeave->resyncAccruals($updated, new DateTimeImmutable('today'), (int) $user['id'])
                : $this->annualLeave->syncAccruals($updated, new DateTimeImmutable('today'), (int) $user['id']);
        }

        $this->audit->record((int) $user['id'], 'profile.updated', 'user', (int) $user['id'], [
            'hire_date' => $value,
            'department' => $department,
            'position' => $position,
            'annual_leave_entries_added' => $added,
        ], $this->ip($request));

        return Response::redirect('/profile?message=' . rawurlencode(
            $added > 0 ? sprintf('내 정보를 저장하고 연차 발생분 %d건을 반영했습니다.', $added) : '내 정보를 저장했습니다.'
        ));
    }

    private function optionalText(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function ip(Request $request): ?string
    {
        $ip = trim((string) $request->server('REMOTE_ADDR', ''));
        return $ip !== '' ? $ip : null;
    }
}
