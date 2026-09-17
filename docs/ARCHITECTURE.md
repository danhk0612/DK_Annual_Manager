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
  View/
```

## 데이터 모델 핵심

### users

직원과 관리자 계정. 이름, Telegram ID, 입사일, 재직상태, 권한을 가진다.

### leave_types

Excel의 V/H/P/S/A를 DB 데이터로 관리한다. `deducts_annual_leave`와 `default_amount`로 차감 규칙을 결정한다.

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
