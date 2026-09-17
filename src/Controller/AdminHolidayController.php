<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DateTimeImmutable;
use DKAnnual\Holiday\KasiHolidayClient;
use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\HolidayRepository;
use DKAnnual\Security\Csrf;
use DKAnnual\View\View;
use Throwable;

final class AdminHolidayController
{
    public function __construct(
        private readonly HolidayRepository $holidays,
        private readonly KasiHolidayClient $client,
        private readonly View $view,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        $year = $this->yearFrom($request->input('year'));

        return Response::html($this->view->render('admin-holidays', [
            'title' => '공휴일 관리',
            'year' => $year,
            'holidays' => $this->holidays->allForYear($year),
            'lastSyncedAt' => $this->holidays->latestPublicApiUpdatedAt($year),
            'apiConfigured' => $this->client->isConfigured(),
            'csrfToken' => $this->csrf->token(),
            'message' => $request->input('message'),
            'error' => $request->input('error'),
        ]));
    }

    public function sync(Request $request): Response
    {
        $year = $this->yearFrom($request->input('year'));

        try {
            $items = $this->client->fetchYear($year);
            if ($items === []) {
                return $this->redirect($year, null, 'API에서 조회된 공휴일이 없어 기존 데이터를 유지했습니다.');
            }

            $count = $this->holidays->replacePublicApiYear($year, $items);
            return $this->redirect($year, sprintf('%d년 공휴일 %d건을 갱신했습니다.', $year, $count));
        } catch (Throwable $exception) {
            return $this->redirect($year, null, $exception->getMessage());
        }
    }

    public function save(Request $request): Response
    {
        $date = trim((string) $request->input('holiday_date', ''));
        $name = trim((string) $request->input('name', ''));
        $source = trim((string) $request->input('source', 'manual'));
        $isPublicHoliday = (string) $request->input('is_public_holiday', '1') === '1';
        $year = $this->yearFrom(substr($date, 0, 4));

        if (!$this->isValidDate($date)) {
            return $this->redirect($year, null, '날짜 형식이 올바르지 않습니다.');
        }
        if ($name === '' || preg_match('/^.{1,120}$/us', $name) !== 1) {
            return $this->redirect($year, null, '휴일 명칭을 1~120자로 입력해 주세요.');
        }
        if (!in_array($source, ['manual', 'company'], true)) {
            return $this->redirect($year, null, '휴일 유형이 올바르지 않습니다.');
        }

        try {
            $this->holidays->saveManaged($date, $name, $source, $isPublicHoliday);
            return $this->redirect($year, '휴일을 저장했습니다.');
        } catch (Throwable $exception) {
            return $this->redirect($year, null, $exception->getMessage());
        }
    }

    public function delete(Request $request): Response
    {
        $year = $this->yearFrom($request->input('year'));
        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            return $this->redirect($year, null, '삭제할 휴일을 확인할 수 없습니다.');
        }

        if (!$this->holidays->deleteManaged((int) $id)) {
            return $this->redirect($year, null, '수동 또는 회사 휴일만 삭제할 수 있습니다.');
        }

        return $this->redirect($year, '휴일을 삭제했습니다.');
    }

    private function yearFrom(mixed $value): int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);
        if ($year === false || $year < 1900 || $year > 2200) {
            return (int) date('Y');
        }

        return (int) $year;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        return $parsed instanceof DateTimeImmutable
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $date;
    }

    private function redirect(int $year, ?string $message = null, ?string $error = null): Response
    {
        $query = ['year' => $year];
        if ($message !== null && $message !== '') {
            $query['message'] = $message;
        }
        if ($error !== null && $error !== '') {
            $query['error'] = $error;
        }

        return Response::redirect('/admin/holidays?' . http_build_query($query));
    }
}
