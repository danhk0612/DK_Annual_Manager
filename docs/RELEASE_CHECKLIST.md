# Release Checklist

## 현재 후보 버전

`0.1.0-rc1`

현재 코드는 기능 구현 완료 상태지만 실제 운영 자격 증명과 MariaDB가 연결된 환경에서 통합 검증 전이므로 정식 릴리스 태그는 만들지 않는다.

## 설치 검증

- [ ] PHP 8.2+ 환경에서 `composer install` 성공
- [ ] `php bin/check.php` FAIL 0
- [ ] 신규 MariaDB에 `database/schema.sql` 적용 성공
- [ ] `/health` 정상
- [ ] Apache 또는 Nginx DocumentRoot가 `public/`으로 제한됨

## 인증/권한

- [ ] Telegram OIDC 로그인 성공
- [ ] 최초 관리자 bootstrap 성공
- [ ] pending 사용자는 관리자 승인 전 접근 불가
- [ ] 일반 사용자가 `/admin/*`에 접근 불가
- [ ] 로그아웃 정상

## 연차/휴가

- [ ] 직원 입사일 입력/수정
- [ ] 연차 발생 동기화
- [ ] 1년 미만 월 발생 확인
- [ ] 1년/3년/5년차 가산 확인
- [ ] 이월/수동 조정 확인
- [ ] 연차/반차 신청 및 주말 제외
- [ ] 공휴일 제외 확인
- [ ] 중복 날짜 신청 차단
- [ ] 승인 시 V/H 원장 차감
- [ ] P/S/A 승인 시 연차 원장 미차감
- [ ] 취소/반려 상태 확인

## Telegram

- [ ] 신청 시 관리자 알림
- [ ] 승인/반려 시 사용자 알림
- [ ] Telegram 장애 시 웹 DB 처리 자체는 유지

## 공휴일

- [ ] 실제 ServiceKey로 현재 연도 갱신
- [ ] 다음 연도 데이터가 제공될 경우 다음 연도 갱신
- [ ] public_api 갱신 시 manual/company 데이터 보존
- [ ] 회사 휴무일 추가/삭제
- [ ] 표시 전용 휴일과 휴가 계산 제외 휴일 구분

## 집계/Audit

- [ ] 사용자 홈의 발생/사용/잔여 확인
- [ ] 관리자 연간 직원 집계와 원장 비교
- [ ] 월간 휴가 집계와 승인 데이터 비교
- [ ] 주요 변경 Audit Log 기록 확인
- [ ] 민감 자격 증명이 Audit Log에 남지 않음

## 운영/보안

- [ ] `app.debug=false`
- [ ] HTTPS
- [ ] Secure session cookie
- [ ] `config/config.php` 저장소 미추적
- [ ] DB 계정 최소 필요 권한 적용
- [ ] DB 백업/복원 확인
- [ ] 운영 로그 위치 및 보존 정책 확인

위 항목을 통과하면 `0.1.0` 정식 릴리스 태그/Release를 생성한다.
