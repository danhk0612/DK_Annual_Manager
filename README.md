# DK Annual Manager

PHP + MariaDB 기반의 소규모 조직용 연차·휴가 관리 웹 애플리케이션입니다.

기존 Excel 휴가 일정표의 월별 기록과 직원별 집계를 웹으로 이전하고, 입사일 기반 연차 계산부터 휴가 신청/승인, Telegram 로그인·알림, 한국 공휴일 업데이트, 집계와 변경 이력까지 하나의 흐름으로 관리합니다.

현재 버전: **0.1.0-rc1**

> 기능 구현은 완료된 릴리스 후보 상태입니다. 실제 MariaDB, Telegram 자격 증명, 공공데이터포털 ServiceKey를 연결한 운영 환경 통합 검증 후 `0.1.0` 정식 릴리스를 생성합니다.

## 핵심 기능

- Telegram OpenID Connect 로그인 및 내부 사용자 연결
- 관리자 / 일반 사용자 권한 분리
- 직원 입사일·퇴사일·활성 상태 관리
- 입사일 기준 법정 연차 발생 계산
- 1년 미만 월 발생, 장기근속 가산, 최대 25일 처리
- 연차·반차·공가·병가·대체휴가 관리
- 날짜/기간 단위 휴가 신청 및 중복 신청 방지
- 주말·공휴일 자동 제외
- 관리자 승인·반려 및 연차 원장 자동 차감
- 신청 시 관리자 개인 알림, 승인/반려 신청자 개인 알림, 승인 일정 회사 공용 그룹 공유
- 반응형 월간 휴가 달력, 월별 신청 내역 및 휴가 상세 팝업
- 사용자 본인 발생·사용·잔여 연차 조회
- 관리자 직원별 연간 연차 및 월별 휴가 집계
- 한국 공휴일 OpenAPI 갱신 및 회사 휴무일 수동 관리
- 연차 이월 및 관리자 수동 조정
- 관리자 브랜딩/테마/Telegram 회사 공용 그룹 설정
- 회사 로고·프로그램 이름·대표색·라이트/다크 테마
- 주요 변경 Audit Log
- 운영용 오류 화면 및 기본 보안 헤더

## 기술 스택

- PHP 8.2+
- MariaDB 10.6+
- HTML / CSS / JavaScript
- Composer
- Telegram Login (OpenID Connect)
- Telegram Bot API
- 공공데이터포털 한국천문연구원 특일 정보 API

## 빠른 설치

```bash
git clone https://github.com/danhk0612/DK_Annual_Manager.git
cd DK_Annual_Manager
composer install --no-dev --optimize-autoloader
cp config/config.example.php config/config.php
```

1. MariaDB 데이터베이스와 전용 계정을 준비하고 `config/config.php`에 DB 접속 정보와 서비스 URL을 입력합니다.
2. 웹 서버 DocumentRoot를 반드시 `public/`으로 지정합니다.
3. `php bin/setup-key.php`로 설치 접근 키를 생성하고 출력된 보호 URL로 `/setup`에 접속합니다.
4. DB schema, Telegram, 최초 관리자, 공휴일 API를 순서대로 설정합니다. Telegram Allowed Origin과 Redirect URI는 현재 접속 주소에서 자동 계산합니다.
5. 신규 설치는 최신 `database/schema.sql`을 사용하며 별도 migration이 필요하지 않습니다.
6. 아래 명령으로 환경을 점검합니다.

```bash
php bin/check.php
```

상세 절차는 [`docs/INSTALL.md`](docs/INSTALL.md)를 참고하세요.

## 설정 보안

- DB 접속정보와 웹 서버 기본값은 `config/config.php`에서 관리합니다.
- Telegram 연결정보, 회사 공용 그룹, 공휴일 API, 회사명·로고·대표색·테마는 초기 설치 후 관리자 화면에서 관리할 수 있습니다.
- 휴가 신청 사유와 잔여 연차 경고는 관리자 개인 Telegram으로만 전달하며, 회사 공용 그룹에는 승인 일정과 일정 취소만 공유합니다.
- `config/config.php`는 `.gitignore` 대상이며 저장소에 커밋하지 않습니다.
- 운영 환경은 HTTPS와 `session_cookie_secure=true` 사용을 권장합니다.
- `app.debug`는 운영에서 `false`로 유지합니다.
- 저장소 루트가 아니라 `public/`만 웹에 노출해야 합니다.

## 연차 계산 전제

근로기준법상 연차 발생 요건을 기준으로 계산하지만, 현재 시스템 자체가 별도 근태 데이터를 수집하지 않으므로 **월 개근 및 연간 80% 출근 요건을 충족한 것으로 전제**하여 발생 원장을 생성합니다. 실제 미충족이나 회사별 예외는 관리자 조정 원장으로 보정합니다.

자세한 정책은 [`docs/ANNUAL_LEAVE_POLICY.md`](docs/ANNUAL_LEAVE_POLICY.md)를 참고하세요.

## 주요 문서

- [`docs/PROJECT.md`](docs/PROJECT.md) — 프로젝트 범위
- [`docs/REQUIREMENTS.md`](docs/REQUIREMENTS.md) — 요구사항
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — 구조와 데이터 모델
- [`docs/TASKS.md`](docs/TASKS.md) — 단계별 개발 상태
- [`docs/ANNUAL_LEAVE_POLICY.md`](docs/ANNUAL_LEAVE_POLICY.md) — 연차 계산 정책
- [`docs/HOLIDAY_SYNC.md`](docs/HOLIDAY_SYNC.md) — 한국 공휴일 동기화 정책
- [`docs/REPORTING_AND_AUDIT.md`](docs/REPORTING_AND_AUDIT.md) — 집계/Audit 기준
- [`docs/INSTALL.md`](docs/INSTALL.md) — 설치 및 운영 설정
- [`docs/SETUP_FLOW.md`](docs/SETUP_FLOW.md) — 보안 설치 마법사 및 Telegram 연결 흐름
- [`docs/RELEASE_CHECKLIST.md`](docs/RELEASE_CHECKLIST.md) — 정식 릴리스 전 검증 항목

## 개발 상태

T01~T16 주요 기능 구현 완료. 실제 서버 환경에서 완전 초기화 설치 마법사와 Telegram/공휴일 통합 검증 단계입니다.
