# DK Annual Manager

소규모 회사·팀에서 **직원 연차와 휴가를 웹으로 관리**하기 위한 PHP + MariaDB 애플리케이션입니다.

직원은 Telegram으로 로그인해 휴가를 신청하고 자신의 연차와 일정을 확인할 수 있습니다. 관리자는 직원·연차·휴가 승인·공휴일·회사 설정을 한 곳에서 관리할 수 있으며, 주요 신청과 처리 결과는 Telegram으로 바로 확인할 수 있습니다.

**현재 안정 버전: 1.2.0**

## 무엇을 할 수 있나요?

### 직원

- Telegram 계정으로 간편 로그인
- 본인의 전체 / 사용 / 잔여 연차 확인
- 연차·반차·공가·병가·대체휴가 신청
- 오전/오후 반차 구분
- 월간 달력에서 본인 휴가 일정 확인
- 올해 월별 휴가 사용량 확인
- 승인 전 신청은 취소 시 신청 기록 자체를 삭제
- 승인된 휴가는 본인이 직접 취소 가능하며 취소 이력과 사유는 유지
- 승인·반려·관리자 승인 취소 결과를 Telegram으로 수신
- Telegram의 **휴가 상세 보기** 버튼으로 해당 신청 화면에 바로 이동
- 내 정보에서 내 휴가 내역을 연간·월간·전체 기간 Excel(.xlsx)로 출력
- 내 정보 월별 요약을 누르면 해당 월 달력으로 바로 이동

### 관리자

- 직원 추가·수정, 활성/비활성 및 관리자 권한 관리
- 직원별 연차 발생·사용·잔여 확인
- 입사일 기준 연차 자동 발생
- 총 연차 고정, 이월, 수동 조정 및 연차 원장 조회
- 휴가 승인·반려·승인 취소
- 직원 휴가 직접 등록 및 즉시 승인
- 전체 직원 월간 휴가 달력과 월별 집계 확인
- 휴가 집계에서 특정 사용자/전체 사용자 × 연간/월간/전체 기간 Excel(.xlsx) 출력
- 한국 공휴일 API 동기화 및 회사 휴무일 관리
- 회사명·로고·대표색·테마 설정
- 회사 주 근무 요일 설정
- 주요 변경 Audit Log 확인
- Telegram 관리자 알림에서 **신청 확인 · 승인** 버튼으로 해당 신청에 바로 이동

### Telegram 알림

Telegram 알림은 공개 정보와 관리 정보를 구분합니다.

- **관리자 개인 메시지**: 휴가 신청, 신청 사유, 잔여 연차 경고, 사용자의 승인 휴가 취소
- **신청자 개인 메시지**: 승인·반려·승인 취소 결과
- **회사 공용 그룹**: 승인된 휴가 일정 등록 및 취소
- 개인 메시지의 버튼을 누르면 로그인이 필요한 경우 인증 후 원래 화면으로 돌아갑니다.

회사 공용 그룹은 설치 후에도 **관리자 → 환경 설정 → Telegram 연결**에서 최근 그룹을 다시 검색해 쉽게 변경할 수 있습니다.

## 설치 환경

- PHP 8.2 이상
- MariaDB 10.6 이상
- Composer 2
- PHP 확장: `curl`, `json`, `pdo`, `pdo_mysql`, `simplexml`
- HTTPS
- Apache 또는 Nginx

웹 서버의 DocumentRoot는 반드시 저장소의 **`public/` 디렉터리**로 지정해야 합니다.

## 빠른 설치

```bash
git clone https://github.com/danhk0612/DK_Annual_Manager.git
cd DK_Annual_Manager
composer install --no-dev --optimize-autoloader
cp config/config.example.php config/config.php
```

`config/config.php`에 최소한 다음 항목을 설정합니다.

- 서비스 HTTPS 주소
- MariaDB 접속 정보
- 운영 환경에서는 `app.debug=false`
- 운영 환경에서는 Secure Cookie 사용

그다음 설치 접근 키를 생성합니다.

```bash
php bin/setup-key.php
```

출력된 보호된 Setup URL에 접속하면 설치 마법사가 다음 순서로 안내합니다.

1. 데이터베이스 스키마 생성
2. Telegram Bot / Login 연결
3. 최초 관리자와 회사 공용 Telegram 그룹 선택
4. 최초 관리자 Telegram 로그인
5. 공휴일 API 연결
6. 설치 완료

자세한 설치 방법은 [설치 및 운영 설정](docs/INSTALL.md)을 참고하세요.

## 설치 후 점검

운영 서버에서는 다음 명령으로 설정 상태를 확인할 수 있습니다.

```bash
php bin/check.php --production
```

정상 운영 준비가 끝난 상태라면 마지막에 다음과 같이 표시됩니다.

```text
모드: production
결과: FAIL 0 / WARN 0
```

웹에서는 `/health`가 정상 응답하는지도 확인하세요.

## 처음 사용하는 순서

### 1. 직원을 연결합니다

직원이 Telegram으로 처음 로그인하면 계정이 생성됩니다. 관리자는 **직원 관리**에서 해당 사용자를 활성화하고 이름·부서·직책·입사일 등을 설정할 수 있습니다.

필요하면 일반 사용자를 관리자로 변경할 수 있습니다. 활성 관리자가 최소 1명은 항상 남도록 보호되어 있으므로, 관리자 교체 시에는 새 관리자를 먼저 지정한 뒤 기존 관리자를 일반 사용자로 변경하면 됩니다.

### 2. 연차를 확인합니다

입사일을 기준으로 연차 발생 원장이 자동으로 동기화됩니다.

회사의 실제 부여 기준과 차이가 있다면 관리자가 직원별로 다음 기능을 사용할 수 있습니다.

- 총 연차 고정
- 이월
- 수동 가감 조정
- 연차 원장 확인

### 3. 휴가를 신청하고 승인합니다

직원은 달력 또는 신청 화면에서 휴가를 등록합니다. 달력 제목의 연도·월 선택기를 이용하면 원하는 기간으로 바로 이동할 수 있습니다.

관리자는 Telegram 알림 또는 관리자 승인 화면에서 내용을 확인하고 승인·반려할 수 있습니다. 승인 전 신청은 사용자가 취소하면 신청 기록이 삭제됩니다. 승인 후에는 사용자가 직접 취소할 수 있으며, 취소된 휴가는 이력으로 남고 연차·반차의 차감분은 자동 복원됩니다. 이 경우 관리자 개인 Telegram과 회사 공용 그룹에도 취소 사실이 전달됩니다.

## 연차 계산 기준

시스템은 입사일을 기준으로 연차를 계산하며 다음을 지원합니다.

- 1년 미만 월 단위 발생
- 1년 이상 연차 발생
- 장기근속 가산
- 최대 25일 제한
- 연차/반차 차감
- 공가·병가·대체휴가 연차 미차감

다만 이 프로그램은 별도의 근태 시스템이 아니므로 **월 개근 및 연간 80% 출근 요건을 충족한 것으로 전제**해 연차를 발생시킵니다. 실제 회사의 근태 결과에 따른 예외는 관리자 조정 기능으로 보정해야 합니다.

상세 기준은 [연차 계산 정책](docs/ANNUAL_LEAVE_POLICY.md)을 참고하세요.

## 운영 중 자주 하는 설정

관리자는 **환경 설정**에서 다음 항목을 다시 변경할 수 있습니다.

- 프로그램 이름
- 회사 로고
- 대표색 및 테마
- 주 근무 요일
- Telegram Client ID / Secret / Bot Token
- 회사 공용 Telegram 그룹
- 공휴일 API ServiceKey

Secret, Token, API Key는 저장 후 기존 값을 화면에 다시 표시하지 않습니다.

## 업데이트

기존 설치를 업데이트하기 전에는 먼저 DB를 백업하세요.

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
composer audit --locked
php bin/migrate.php
php bin/check.php --production
```

`bin/migrate.php`는 이미 적용된 migration을 다시 실행하지 않고 필요한 변경만 적용합니다.

## 보안 관련 권장사항

- HTTPS 사용
- `app.debug=false`
- Secure Session Cookie 사용
- 웹 DocumentRoot를 `public/`으로 제한
- `config/config.php` 외부 접근 차단
- MariaDB 전용 계정 사용
- DB 정기 백업
- 운영 로그 접근 권한 제한

Telegram Client Secret, Bot Token, 공휴일 ServiceKey 등의 민감정보는 오류 화면 및 감사 로그에 원문이 노출되지 않도록 처리합니다.

## 문서

사용 및 운영에 필요한 문서:

- [설치 및 운영 설정](docs/INSTALL.md)
- [연차 계산 정책](docs/ANNUAL_LEAVE_POLICY.md)
- [공휴일 동기화](docs/HOLIDAY_SYNC.md)
- [설치 마법사 흐름](docs/SETUP_FLOW.md)

개발 및 구조 관련 문서:

- [프로젝트 범위](docs/PROJECT.md)
- [요구사항](docs/REQUIREMENTS.md)
- [아키텍처](docs/ARCHITECTURE.md)
- [작업 기록](docs/TASKS.md)
- [집계 및 Audit 기준](docs/REPORTING_AND_AUDIT.md)
- [릴리스 체크리스트](docs/RELEASE_CHECKLIST.md)

## 라이선스

DK Annual Manager는 [MIT License](LICENSE)로 배포됩니다.
