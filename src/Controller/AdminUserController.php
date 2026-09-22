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
use PDOException;

final class AdminUserController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AnnualLeaveService $annualLeave,
        private readonly Auth $auth,
        private readonly AuditLogRepository $audit,
        private readonly View $view,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        $editUser = null;
        $editId = $request->input('edit');
        if (is_string($editId) && ctype_digit($editId)) {
            $editUser = $this->users->findById((int) $editId);
        }

        return Response::html($this->view->render('admin-users', [
            'title' => '직원 관리',
            'users' => $this->users->all(),
            'editUser' => $editUser,
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'error' => $request->input('error'),
        ]));
    }

    public function save(Request $request): Response
    {
        $id = $this->optionalId($request->input('id'));
        $name = trim((string) $request->input('name', ''));
        $department = $this->optionalText($request->input('department'), 100);
        $position = $this->optionalText($request->input('position'), 100);
        $hireDate = $this->optionalDate($request->input('hire_date'));
        $employmentEndDate = $this->optionalDate($request->input('employment_end_date'));
        $telegramUserId = $this->optionalTelegramId($request->input('telegram_user_id'));
        $role = (string) $request->input('role', 'user');
        $status = (string) $request->input('status', 'pending');

        if ($name === '') {
            return Response::redirect('/admin/users?error=' . rawurlencode('이름을 입력해 주세요.'));
        }
        if ($hireDate === false || $employmentEndDate === false) {
            return Response::redirect('/admin/users?error=' . rawurlencode('날짜 형식을 확인해 주세요.'));
        }
        if ($telegramUserId === false) {
            return Response::redirect('/admin/users?error=' . rawurlencode('Telegram User ID는 숫자로 입력해 주세요.'));
        }
        if (!in_array($role, ['admin', 'user'], true) || !in_array($status, ['pending', 'active', 'inactive'], true)) {
            return Response::redirect('/admin/users?error=' . rawurlencode('권한 또는 상태 값이 올바르지 않습니다.'));
        }

        $actor = $this->auth->user();
        $actorId = $actor !== null ? (int) $actor['id'] : null;

        try {
            if ($id === null) {
                $newId = $this->users->createManaged(
                    $name,
                    $department,
                    $position,
                    $hireDate,
                    $employmentEndDate,
                    $telegramUserId,
                    $role,
                    $status,
                );
                $createdUser = $this->users->findById($newId);
                if ($createdUser !== null && !empty($createdUser['hire_date'])) {
                    $this->annualLeave->syncAccruals($createdUser, new DateTimeImmutable('today'), $actorId);
                }
                $this->audit->record($actorId, 'user.created', 'user', $newId, [
                    'name' => $name,
                    'department' => $department,
                    'position' => $position,
                    'hire_date' => $hireDate,
                    'employment_end_date' => $employmentEndDate,
                    'role' => $role,
                    'status' => $status,
                ], $this->ip($request));
                return Response::redirect('/admin/users?message=' . rawurlencode('직원을 추가했습니다.'));
            }

            $existing = $this->users->findById($id);
            if ($existing === null) {
                return Response::redirect('/admin/users?error=' . rawurlencode('직원을 찾을 수 없습니다.'));
            }

            $removesActiveAdmin = ($existing['role'] ?? null) === 'admin'
                && ($existing['status'] ?? null) === 'active'
                && ($role !== 'admin' || $status !== 'active');

            if ($removesActiveAdmin && $this->users->activeAdminCount($id) === 0) {
                return Response::redirect('/admin/users?edit=' . $id . '&error=' . rawurlencode(
                    '활성 관리자가 1명뿐일 때는 해당 계정을 사용자로 변경하거나 비활성화할 수 없습니다. 다른 관리자를 먼저 지정해 주세요.'
                ));
            }

            $roleOrStatusChanged = ($existing['role'] ?? null) !== $role
                || ($existing['status'] ?? null) !== $status;

            $this->users->updateManaged(
                $id,
                $name,
                $department,
                $position,
                $hireDate,
                $employmentEndDate,
                $telegramUserId,
                $role,
                $status,
            );
            $updatedUser = $this->users->findById($id);
            if ($updatedUser !== null && !empty($updatedUser['hire_date'])) {
                if (($existing['hire_date'] ?? null) !== ($updatedUser['hire_date'] ?? null)) {
                    $this->annualLeave->resyncAccruals($updatedUser, new DateTimeImmutable('today'), $actorId);
                } else {
                    $this->annualLeave->syncAccruals($updatedUser, new DateTimeImmutable('today'), $actorId);
                }
            }

            $this->audit->record($actorId, 'user.updated', 'user', $id, [
                'name' => $name,
                'department' => $department,
                'position' => $position,
                'hire_date' => $hireDate,
                'employment_end_date' => $employmentEndDate,
                'role' => $role,
                'status' => $status,
            ], $this->ip($request));

            if ($roleOrStatusChanged && $actorId === $id) {
                $this->auth->logout();
                return Response::redirect('/login?message=' . rawurlencode('권한 또는 계정 상태가 변경되어 다시 로그인해야 합니다.'));
            }

            return Response::redirect('/admin/users?message=' . rawurlencode(
                $roleOrStatusChanged
                    ? '직원 정보를 저장했습니다. 권한/상태가 변경된 계정은 다음 요청에서 자동 로그아웃됩니다.'
                    : '직원 정보를 저장했습니다.'
            ));
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return Response::redirect('/admin/users?error=' . rawurlencode('이미 연결된 Telegram User ID입니다.'));
            }

            throw $exception;
        }
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

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    private function optionalTelegramId(mixed $value): int|false|null
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return ctype_digit($value) ? (int) $value : false;
    }

    private function optionalDate(mixed $value): string|false|null
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : false;
    }

    private function ip(Request $request): ?string
    {
        $ip = trim((string) $request->server('REMOTE_ADDR', ''));
        return $ip !== '' ? $ip : null;
    }
}
