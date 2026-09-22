# Release Checklist

## 현재 후보 버전

`0.1.0-rc1`

현재 코드는 기능 구현 완료 상태지만 실제 운영 자격 증명과 MariaDB가 연결된 환경에서 통합 검증 전이므로 정식 릴리스 태그는 만들지 않는다.

## 설치 검증

- [ ] 테스트 환경에서 `php bin/reset-install.php --confirm=RESET-INSTALL` 실행
- [ ] 초기화 직후 setup key/URL 출력
- [ ] setup key 없는 외부 `/setup` 접근 차단
- [ ] 유효한 setup key 접속 후 URL에서 key 제거
- [ ] 초기화 직후 일반 화면 대신 보호된 `/setup`으로 안내
- [ ] Setup Step 1에서 최신 `database/schema.sql` 자동 적용
- [ ] 별도 migration 없이 신규 설치 완료
- [ ] Setup Step 2 현재 host 기반 Allowed Origin / Redirect URI 자동 계산
- [ ] BotFather에서 Bot Token과 Login Widget Client ID/Secret 발급 안내 확인
- [ ] Setup Step 2 Telegram Bot/OIDC 저장 및 Bot 연결 확인
- [ ] Setup Step 3A 최초 관리자 private 채팅 자동 탐색
- [ ] Setup Step 3B 회사 공용 group/supergroup 자동 탐색
- [ ] Setup Step 4 최초 관리자 Telegram 로그인 및 자동 admin 활성화
- [ ] Setup Step 5 공휴일 API 저장 및 현재 연도 동기화
- [ ] Setup 완료 후 setup key 파일 삭제
- [ ] 완료된 Setup Step은 기본 접힘 상태이며 클릭/키보드로 다시 펼칠 수 있음
- [ ] Setup 완료 후 `/setup` 일반 접근 차단
- [ ] 기존 활성 관리자 운영 DB는 setup.completed 자동 이행

- [ ] PHP 8.2+ 환경에서 `composer install` 성공
- [ ] `php bin/check.php` FAIL 0
- [ ] 신규 MariaDB에 `database/schema.sql` 적용 성공
- [ ] 기존 DB는 `database/migrations/20260922_003_leave_usability.sql` 적용 성공
- [ ] `/health` 정상
- [ ] Apache 또는 Nginx DocumentRoot가 `public/`으로 제한됨

## 인증/권한

- [ ] Telegram OIDC 로그인 성공
- [ ] 최초 관리자 bootstrap 성공
- [ ] pending 사용자는 관리자 승인 전 접근 불가
- [ ] 일반 사용자가 `/admin/*`에 접근 불가
- [ ] 로그아웃 정상

## 연차/휴가

- [ ] 직원 이름·입사일·부서·직책 입력/수정
- [ ] 입사일 저장 및 기본 달력 진입 시 연차 발생 자동 동기화
- [ ] 관리자 연간 총 연차 고정 설정 및 자동 발생 후에도 고정값 유지
- [ ] 총 연차 고정 해제 후 자동 계산 복귀
- [ ] 1년 미만 월 발생 확인
- [ ] 1년/3년/5년차 가산 확인
- [ ] 이월/수동 조정 확인
- [ ] 연차/반차 신청 및 주말 제외
- [ ] 반차 오전/오후 구분 및 반차 선택 시 종료일 비활성화
- [ ] 일반 휴가 선택 시 반차 구분 비활성화
- [ ] 신청일 기본값 오늘 / 종료일 시작일 미만 자동 보정
- [ ] 사유 선택 + 추가 사유 입력
- [ ] 공휴일 제외 확인
- [ ] 중복 날짜 신청 차단
- [ ] 승인 시 V/H 원장 차감
- [ ] G/S/A(공가/병가/대체휴가) 승인 시 연차 원장 미차감
- [ ] 잔여 부족 상태에서도 신청 가능하고 사용자·관리자에 경고 표시
- [ ] 취소/반려 상태 확인
- [ ] 승인된 연차/반차 관리자 승인 취소 시 reversal 복원 확인
- [ ] 관리자 직원 대리 신청 확인

## Telegram

- [ ] 휴가 신청 시 활성 관리자 개인 Telegram에 신청 정보/사유/잔여 경고 전달
- [ ] 휴가 신청 단계에서 회사 공용 그룹에는 메시지가 전송되지 않음
- [ ] 승인 시 신청자 개인 Telegram에 결과 전달
- [ ] 승인 시 회사 공용 그룹에 직원/종류/기간/일수만 공유
- [ ] 반려 시 신청자 개인 Telegram에만 결과 전달
- [ ] 승인 취소 시 신청자 개인 Telegram에 결과 전달
- [ ] 승인 취소 시 회사 공용 그룹에 일정 취소 공유
- [ ] 회사 공용 그룹 메시지에 신청 사유/잔여 연차/관리자 메모가 포함되지 않음
- [ ] Telegram 장애 시 웹 DB 처리 자체는 유지

## 공휴일

- [ ] 실제 ServiceKey로 현재 연도 갱신
- [ ] 다음 연도 데이터가 제공될 경우 다음 연도 갱신
- [ ] public_api 갱신 시 manual/company 데이터 보존
- [ ] 회사 휴무일 추가/삭제
- [ ] 표시 전용 휴일과 휴가 계산 제외 휴일 구분

## 집계/Audit

- [ ] 사용자 휴가 신청 화면의 잔여 연차 확인
- [ ] 일반 사용자는 달력에서 본인 일정만, 관리자는 전체 일정 확인
- [ ] 달력 우측/하단 이달 신청 내역 확인
- [ ] 달력 휴가 항목 상세 팝업 확인
- [ ] 공휴일·회사 휴무일·오늘 배경 강조 확인
- [ ] 달력 팝업에서 휴가 신청 및 날짜 클릭 사전 선택 확인
- [ ] 내 정보 전체/사용/잔여 연차 확인
- [ ] 휴가 집계 검색·필터·월별 그래프 확인
- [ ] 관리자 대시보드 승인대기·다가오는 휴가·관리 메뉴 균형 배치 확인
- [ ] 관리자 연간 직원 집계와 원장 비교
- [ ] 월간 휴가 집계와 승인 데이터 비교
- [ ] 주요 변경 Audit Log 기록 확인
- [ ] 민감 자격 증명이 Audit Log에 남지 않음
- [ ] Telegram/API 연결 실패 화면에 Secret/Token/ServiceKey 또는 원문 HTTP 오류가 노출되지 않음

## 브랜딩/설정

- [ ] 회사/프로그램 이름 변경 후 헤더·문서 제목 반영
- [ ] 회사 로고 업로드/삭제 및 좌상단 표시
- [ ] 대표색 변경 반영
- [ ] 라이트/다크/시스템 테마 확인
- [ ] 현재 메뉴 활성 상태 강조
- [ ] Bootstrap Icons 정상 로드
- [ ] Telegram Client ID/Secret/Bot Token을 관리자 환경설정에서 변경
- [ ] Telegram 회사 공용 그룹 Chat ID를 관리자 환경설정에서 변경
- [ ] 직원용 Telegram Bot/서비스 로그인 초대 링크 복사
- [ ] 공휴일 API ServiceKey를 관리자 환경설정에서 변경 및 연결 확인
- [ ] Telegram Bot 자동 확인 및 최근 채팅 탐색
- [ ] 회사 공용 Telegram 그룹 테스트 메시지 발송
- [ ] 관리자 설정 화면에서 비밀 자격 증명 값이 노출되지 않음
- [ ] public/uploads/branding 웹 실행 계정 쓰기 권한 확인

## 운영/보안

- [ ] `app.debug=false`
- [ ] HTTPS
- [ ] Secure session cookie
- [ ] `config/config.php` 저장소 미추적
- [ ] DB 계정 최소 필요 권한 적용
- [ ] DB 백업/복원 확인
- [ ] 운영 로그 위치 및 보존 정책 확인
- [ ] 모바일 화면에서 네비게이션·달력·팝업·표 반응형 확인

위 항목을 통과하면 `0.1.0` 정식 릴리스 태그/Release를 생성한다.
