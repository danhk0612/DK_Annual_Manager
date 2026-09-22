# DK Annual Manager 1.0.0

첫 번째 정식 안정 버전입니다.

## 주요 기능

- Telegram OpenID Connect 기반 로그인
- 관리자 / 일반 사용자 권한 분리 및 관리자 교체 안전장치
- 직원 정보와 입사일 기반 연차 관리
- 연차·반차·공가·병가·대체휴가 신청
- 휴가 승인·반려·승인 취소 및 연차 원장 자동 반영
- 관리자 직원 직접 등록 및 즉시 승인
- 월간 휴가 달력과 직원별·월별 집계
- 한국 공휴일 API 및 회사 휴무일 관리
- 회사명·로고·대표색·테마·주 근무 요일 설정
- Audit Log
- Telegram 관리자/사용자 개인 알림
- Telegram 회사 공용 그룹 일정 알림
- Telegram 알림의 승인/상세 바로가기 버튼
- 설치 마법사와 완전 초기화/재설치
- 안전한 DB migration 추적 및 운영 preflight

## Telegram 바로가기

- 관리자는 휴가 신청 알림의 **신청 확인 · 승인** 버튼으로 해당 신청에 바로 이동할 수 있습니다.
- 사용자는 승인·반려·승인 취소 알림의 **휴가 상세 보기** 버튼으로 해당 신청을 바로 확인할 수 있습니다.
- 로그인이 필요한 경우 Telegram 인증 후 원래 대상 화면으로 복귀합니다.

## 배포 검증

v1.0.0은 다음 자동 검증을 통과한 소스를 기준으로 배포됩니다.

- PHP 8.2 / 8.4
- MariaDB 10.6
- Composer lock 기반 의존성 설치
- Composer security audit
- 전체 PHP syntax 검사
- PHPUnit
- 최신 schema 및 migration 반복 실행 검증
- 휴가 승인 → 연차 차감 → 승인 취소 → 연차 복원 DB 통합 테스트

운영 서버에서도 `php bin/check.php --production`의 **FAIL 0 / WARN 0**을 확인했습니다.

## 업그레이드

먼저 DB를 백업한 뒤:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
composer audit --locked
php bin/migrate.php
php bin/check.php --production
```

## 라이선스

MIT License
