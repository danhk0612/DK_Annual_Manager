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

사용자가 제출한 신청 단위. 신청 기간, 유형, 사유, 상태, 검토자를 저장한다.

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
 -> 관리자 Telegram 알림
 -> 관리자 승인
 -> annual_leave_ledger usage 기록
 -> 사용자 Telegram 결과 알림
```

DB 상태 변경을 먼저 확정한 후 외부 Telegram 전송을 수행한다. Telegram 장애가 핵심 업무 데이터의 일관성을 깨지 않도록 한다.


## 설정 계층

`config/config.php`에는 DB 접속 및 웹 실행에 필요한 최소 기본값을 둔다. 서비스 연결/운영 설정은 `app_settings`로 관리할 수 있고, 요청 시작 시 SetupService가 다음 값을 런타임 Config에 overlay한다.

- Telegram Client ID / Client Secret / Bot Token / Redirect URI
- 최초 관리자 Telegram ID
- 관리자 알림 Chat ID
- 공휴일 ServiceKey

회사명, 로고 경로, 대표색, 테마도 `app_settings`에서 읽는다.

## Setup 흐름

```text
CLI setup key 생성
 -> 보호된 /setup
 -> 최신 schema 초기화
 -> Telegram Bot/OIDC 검증
 -> private 관리자 채팅 + 관리자 그룹 탐색
 -> 최초 관리자 OIDC 로그인
 -> 공휴일 API 검증/동기화
 -> setup.completed
 -> setup key 삭제
```

신규 schema는 재실행 가능하도록 구성한다. 기존 운영 DB는 활성 관리자 계정이 존재하고 fresh setup marker가 없으면 legacy 설치로 자동 이행한다.
