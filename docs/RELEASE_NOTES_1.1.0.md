# DK Annual Manager 1.1.0

사용자가 휴가 승인 전·후 상태에 맞게 직접 취소할 수 있도록 취소 흐름을 정리한 기능 릴리스입니다.

## 승인 전 신청 취소

- 본인의 승인 대기 신청을 직접 취소할 수 있습니다.
- 취소하면 `leave_requests`와 연결된 신청 일자 데이터가 삭제됩니다.
- 승인 전 취소는 별도 휴가 이력으로 남기지 않습니다.
- 이미 신청 시 관리자에게 전송된 원래 신청 메시지는 회수하지 않지만, 취소 시 별도의 관리자 Telegram 알림은 보내지 않습니다.
- 삭제 작업 자체는 Audit Log에 남깁니다.

## 승인된 휴가 사용자 취소

- 신청자가 본인의 승인된 휴가를 직접 취소할 수 있습니다.
- 승인된 연차/반차는 취소 시 reversal 원장으로 차감분을 즉시 복원합니다.
- 기존 신청은 삭제하지 않고 `cancelled` 상태로 유지합니다.
- 사용자/관리자 취소 여부, 취소 시각, 취소 사유를 별도 메타데이터로 기록합니다.
- 원래 승인자, 승인 시각, 승인 메모는 취소 과정에서 덮어쓰지 않습니다.
- 활성 관리자 개인 Telegram에 사용자 취소 알림을 전송합니다.
- 회사 공용 Telegram 그룹에도 기존 승인 일정의 취소를 공유합니다.

## 화면 개선

- 신청 내역에서 승인 전 신청 삭제와 승인 휴가 취소를 구분해 제공합니다.
- 달력의 휴가 상세 팝업에서도 본인 신청을 바로 취소할 수 있습니다.
- 취소된 휴가는 사용자 취소/관리자 취소, 취소 시각, 취소 사유를 확인할 수 있습니다.
- 신청 내역 검색에 취소 사유도 포함됩니다.

## 데이터베이스

신규 migration:

`20260922_004_leave_cancellation_metadata.sql`

추가 컬럼:

- `leave_requests.cancelled_by`
- `leave_requests.cancelled_at`
- `leave_requests.cancellation_source`
- `leave_requests.cancellation_note`

## 업데이트

기존 설치는 DB 백업 후 다음 순서로 업데이트하세요.

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
composer audit --locked
php bin/migrate.php
php bin/check.php --production
```

`php bin/migrate.php` 실행 전에는 새 취소 메타데이터 컬럼이 없으므로 반드시 migration을 적용해야 합니다.

## 라이선스

MIT License
