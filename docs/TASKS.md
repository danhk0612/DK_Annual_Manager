# 개발 작업

## T01. 프로젝트 기반 및 기준 문서

상태: 진행 중

- [x] 저장소 초기화
- [x] Excel 업무 구조 분석
- [x] 요구사항 문서화
- [x] 기본 데이터 모델 설계
- [x] `config.example.php` 작성
- [x] 초기 MariaDB schema 작성
- [x] PHP/Composer 최소 골격 작성
- [x] PHP 구문 검사
- [ ] Composer validate / install 검증
- [ ] MariaDB schema 실제 적용 검증

현재 작업 환경에는 Composer 및 MariaDB 서버가 없어 마지막 두 항목은 실제 실행 환경에서 검증한다.

## T02. 애플리케이션 공통 기반

상태: 구현 완료

- [x] 라우팅
- [x] 세션 초기화
- [x] 공통 레이아웃
- [x] CSRF 보호
- [x] 로그인 필요/관리자 필요 middleware
- [x] 공통 DB repository 기반
- [x] `/health` DB 연결 확인 endpoint

## T03. Telegram 로그인

상태: 구현 완료 / 실제 자격 증명 검증 대기

- [x] OIDC Authorization Code + PKCE
- [x] callback
- [x] JWKS 기반 ID Token 검증
- [x] `iss`, `aud`, `exp`, `nonce` 검증
- [x] 사용자 매핑
- [x] 신규 사용자 pending 등록
- [x] bootstrap 관리자 활성화
- [x] pending/active/inactive 처리
- [x] 로그아웃
- [ ] 실제 BotFather Client ID/Secret으로 로그인 검증

## T04. 직원/권한 관리

상태: 구현 완료

- [x] 관리자 직원 목록
- [x] 직원 추가/수정
- [x] active/pending/inactive 상태 관리
- [x] 입사일/퇴사일 관리
- [x] 사용자 본인 입사일 입력
- [x] 관리자/사용자 권한 관리
- [x] Telegram User ID 연결 및 연결 상태 표시
- [x] T04 PHP 구문 검사

## T05. 연차 계산 엔진

상태: 구현 완료 / DB 통합 검증 대기

- [x] 법정 연차 계산 서비스
- [x] 1년 미만 월 개근 발생 구조
- [x] 1년 이상 기본/가산 연차
- [x] 최대 25일 제한
- [x] 말일/윤년 입사일 보정
- [x] 중복 방지 가능한 연차 원장 생성
- [x] 이월/수동 조정
- [x] 관리자 연차 원장 화면
- [x] PHPUnit 테스트 작성
- [x] 별도 순수 PHP 계산 검증
- [ ] MariaDB migration 및 원장 통합 검증
- [ ] Composer 설치 후 PHPUnit 실행

## T06. 달력 및 휴가 신청

상태: 구현 완료 / DB 통합 검증 대기

- [x] 월간 달력
- [x] 사용자별 일정 표시
- [x] 휴가 유형 V/H/P/S/A
- [x] 단일 날짜/기간 신청
- [x] 주말/DB 등록 공휴일 제외
- [x] 동일 사용자 중복 신청 방지
- [x] 신청 내역 조회
- [x] 승인 대기 신청 취소
- [x] 휴가 날짜 계산 PHPUnit 테스트 작성
- [x] PHP 구문 검사
- [ ] MariaDB 통합 검증

## T07. 관리자 승인 및 Telegram 알림

상태: 구현 완료 / 실제 Bot 통합 검증 대기

- [x] 관리자 승인/반려 화면
- [x] 승인 처리 트랜잭션 및 중복 처리 방지
- [x] 연차/반차 승인 시 연도별 사용 원장 반영
- [x] 비차감 휴가(P/S/A)는 원장 미차감
- [x] 신청 시 관리자 Telegram 알림
- [x] 승인/반려 시 사용자 Telegram 알림
- [x] 설정 admin_chat_ids + 활성 관리자 Telegram ID 알림 대상
- [x] Telegram 실패가 신청/승인 트랜잭션을 되돌리지 않도록 분리
- [ ] 실제 Bot Token으로 메시지 발송 검증
- [ ] MariaDB 승인/원장 통합 검증

## T08. 한국 공휴일 업데이트

상태: 구현 완료 / 실제 API·DB 통합 검증 대기

- [x] 한국천문연구원 특일 API client
- [x] 연도 단위 갱신
- [x] public_api 데이터 트랜잭션 교체 및 중복 정리
- [x] API 오류/빈 결과 시 기존 데이터 보존
- [x] 관리자 수동 공휴일/회사 휴무일 관리
- [x] 휴가일수 계산 제외 여부 설정
- [x] 최근 업데이트 상태 표시
- [x] T08 PHP 구문 검사
- [ ] 실제 공공데이터포털 ServiceKey 호출 검증
- [ ] MariaDB 공휴일 동기화 통합 검증

## T09. 집계 및 감사 로그

상태: 구현 완료 / DB 통합 검증 대기

- [x] 직원별 발생/이월/조정/사용/잔여 집계
- [x] 휴가 종류별 월간 승인 사용량 집계
- [x] 연도 선택 보고서
- [x] 관리자 대시보드 요약 지표
- [x] 최근 변경 이력 표시
- [x] Audit Log 저장소 및 관리자 조회 화면
- [x] 직원/입사일/휴가/승인/연차/공휴일 주요 변경 기록
- [x] 민감 설정값을 Audit Log에서 제외
- [ ] MariaDB 집계 쿼리 및 Audit Log 통합 검증

## T10. 배포 준비

상태: 대기

- 설치 문서
- 운영 설정 점검
- 오류 화면
- 보안 점검
- 기본 테스트
- 첫 릴리스
