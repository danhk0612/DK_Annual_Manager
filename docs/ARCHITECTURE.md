# 아키텍처

## 기본 구성

```text
Browser
  |
  v
PHP Web App
  |-- Telegram OIDC
  |-- Telegram Bot API
  |-- Holiday API
  |
  v
MariaDB
```

## 디렉터리 방향

```text
config/              환경 설정
public/              웹 공개 루트
src/                 PHP 애플리케이션 코드
database/            초기 스키마 및 이후 migration
docs/                기준 문서
vendor/               Composer 의존성(Git 제외)
```

기능 구현 단계에서 `src/`는 역할별로 확장한다.

```text
src/
  Auth/
  Controller/
  Leave/
  Holiday/
  Telegram/
  Repository/
  Setup/
  Middleware/
  View/
```

## 데이터 모델 핵심

### users

직원과 관리자 계정. 이름, Telegram ID, 입사일, 재직상태, 권한을 가진다.

### leave_types

현재 신규 신청 유형 V/H/G/S/A를 DB 데이터로 관리한다. V/H만 `deducts_annual_leave=1`이고 G/S/A는 미차감이다. 원본 Excel의 P는 신규 신청에서 비활성화한다.

### leave_requests

사용자가 제출한 신청 단위. 신청 기간, 유형, 사유, 상태, 검토자를 저장한다. 승인된 신청의 취소는 `cancelled_by`, `cancelled_at`, `cancellation_source`, `cancellation_note`에 별도로 기록해 원래 승인자·승인시각·승인메모를 보존한다.

### leave_request_days

기간 신청을 실제 날짜별 사용량으로 펼친 데이터. 주말/공휴일 제외와 반차 처리를 명확하게 한다.

### annual_leave_ledger

연차 잔액의 원장. 발생/이월/조정/사용/취소를 각각 거래로 기록한다.

잔액은 원칙적으로 원장의 합으로 계산한다.

```text
grant + carryover + adjustment + usage + reversal
```

`usage`는 음수, `reversal`은 기존 사용 차감의 반대 부호로 기록한다.

### holidays

공공 API, 관리자 수동 등록, 회사 자체 휴무일을 한 테이블에서 source로 구분한다.

### audit_logs

관리자 변경과 중요 상태 전환을 추적한다.

## 인증 흐름

```text
로그인 클릭
 -> Telegram OIDC Authorization Code + PKCE
 -> callback
 -> ID Token 검증
 -> telegram_user_id 조회
 -> active 사용자: 세션 생성
 -> 미등록/대기 사용자: 접근 제한 및 관리자 연결 대기
```

## 휴가 신청 흐름

```text
사용자 날짜 선택
 -> 휴가 유형 선택
 -> 주말/공휴일 계산
 -> 실제 차감 날짜 생성
 -> leave_requests + leave_request_days 저장
 -> 활성 관리자 개인 Telegram 신청 알림
 -> 관리자 승인
 -> annual_leave_ledger usage 기록
 -> 사용자 Telegram 결과 알림
 -> 승인 일정은 회사 공용 Telegram 그룹에 공유
```

승인 전 사용자 취소는 `leave_requests`를 삭제하며 `leave_request_days`는 FK cascade로 함께 삭제한다. 별도 관리자 취소 알림은 보내지 않는다.

승인 후 사용자 취소는 기존 신청을 `cancelled` 상태로 유지하고 취소 메타데이터를 기록한다. 연차/반차 usage가 있으면 reversal을 추가한 뒤 관리자 개인 Telegram과 회사 공용 그룹에 일정 취소를 알린다. 관리자 승인 취소도 같은 취소 메타데이터 구조를 사용한다.

DB 상태 변경을 먼저 확정한 후 외부 Telegram 전송을 수행한다. Telegram 장애가 핵심 업무 데이터의 일관성을 깨지 않도록 한다.


## 설정 계층

`config/config.php`에는 DB 접속 및 웹 실행에 필요한 최소 기본값을 둔다. 서비스 연결/운영 설정은 `app_settings`로 관리할 수 있고, 요청 시작 시 SetupService가 다음 값을 런타임 Config에 overlay한다.

- Telegram Client ID / Client Secret / Bot Token / Redirect URI
- 최초 관리자 Telegram ID
- 회사 공용 Telegram 그룹 Chat ID
- 공휴일 ServiceKey

회사명, 로고 경로, 대표색, 테마도 `app_settings`에서 읽는다.

## Setup 흐름

```text
CLI setup key 생성
 -> 보호된 /setup
 -> 최신 schema 초기화
 -> Telegram Bot/OIDC 검증
 -> private 최초 관리자 채팅 + 회사 공용 그룹 탐색
 -> 최초 관리자 OIDC 로그인
 -> 공휴일 API 검증/동기화
 -> setup.completed
 -> setup key 삭제
```

신규 schema는 재실행 가능하도록 구성한다. 기존 운영 DB는 활성 관리자 계정이 존재하고 fresh setup marker가 없으면 legacy 설치로 자동 이행한다.


## Telegram 알림 경계

- **관리자 개인 알림**: 활성 관리자 `telegram_user_id`에 휴가 신청, 신청 사유, 잔여 연차 경고, 사용자의 승인 휴가 취소 등 관리 정보를 전송한다. 승인 전 사용자 취소·삭제에는 추가 알림을 보내지 않는다.
- **신청자 개인 알림**: 승인/반려/승인 취소 결과와 관리자 메모를 신청자에게 전송한다.
- **회사 공용 그룹**: 관리자와 직원이 함께 보는 그룹이다. 승인된 휴가 일정 등록과 승인 취소만 공유하며 신청 사유, 잔여 연차, 관리자 메모는 포함하지 않는다.


## 권한 변경과 세션 무효화

로그인 시 세션에 현재 사용자의 `role`과 `status`를 함께 저장한다. 각 인증 요청에서 DB의 현재 값과 세션 claim을 비교하며, 권한이나 상태가 달라졌으면 사용자 ID와 claim을 제거해 재로그인을 요구한다.

활성 관리자 수가 1명뿐인 경우 그 계정의 관리자 권한 제거 또는 비활성화를 서버 측에서 차단한다. Bootstrap 관리자 자동 승격은 Setup Wizard가 완료되기 전 최초 관리자 연결에만 사용한다.

## 주 근무 요일

`app_settings.work.weekdays`에 ISO-8601 요일 번호(월=1 ... 일=7)를 저장한다. 기본값은 `1,2,3,4,5`이다.

- CalendarController는 이 값을 달력에 전달해 비근무 요일을 강조한다.
- LeaveController는 LeaveDateCalculator에 같은 값을 전달해 신규 휴가의 실제 차감 날짜를 계산한다.
- 기존에 저장된 휴가 신청 날짜는 설정 변경만으로 소급 재작성하지 않는다.

## 재설치

관리자 환경설정과 CLI `bin/reset-install.php` 모두 `SetupService::resetInstallation()`을 사용한다. 서비스 테이블과 업로드 브랜딩 파일을 제거하고 새로운 Setup Key를 생성한다. 웹에서 실행한 경우 현재 브라우저 세션에 Setup 접근 권한을 유지하여 즉시 설치 마법사로 이동한다.
