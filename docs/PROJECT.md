# 프로젝트 정의

## 목적

기존 Excel 휴가 일정표를 대체하는 PHP + MariaDB 기반 웹 애플리케이션을 만든다.

단순한 일정표 복제가 아니라 다음 흐름을 하나로 연결한다.

`직원 등록 → 입사일 기반 연차 계산 → 휴가 신청 → 관리자 승인/반려 → Telegram 알림 → 사용량 차감 → 달력/집계`

## Excel 기준자료에서 유지할 개념

기준 Excel 파일은 다음 개념을 사용한다.

- 월별 1월~12월 휴가 일정
- 직원 이름별 월 사용량 및 연간 누적
- 휴가 코드
  - `V`: 휴가/연차
  - `H`: 반차
  - `P`: 개인(원본 Excel 호환 개념, 신규 신청에서는 비활성)
  - `S`: 병가
  - `A`: 대체휴가(기타)
- 웹 신규 신청 유형은 `V/H/G/S/A`이며 `G` 공가를 추가한다.
- `V`는 1일, `H`는 0.5일의 연차 차감이고 `G/S/A`는 연차를 차감하지 않는다.
- 재직연차에 따른 전체 휴가일수 및 잔여 휴가일수
- 연도별 공휴일 목록

웹에서는 사람×날짜 셀 구조를 그대로 복제하지 않고 날짜 단위 데이터로 정규화한다.

## 법정 연차 기본 규칙

2026-09-17 현재 대한민국 근로기준법 제60조를 기본 계산 규칙으로 사용한다.

- 1년간 80% 이상 출근: 15일
- 계속 근로 1년 미만: 1개월 개근 시 1일
- 3년 이상 계속 근로: 최초 1년을 초과한 계속근로연수 매 2년마다 1일 가산
- 법정 가산 포함 최대 25일

참고: https://www.law.go.kr/LSW/lsLinkCommonInfo.do?lsJoLnkSeq=1012792285

법정 기본 계산과 실제 회사의 이월·특별휴가·수동 보정은 분리한다.

## 인증

웹 로그인은 Telegram Login의 OpenID Connect 방식을 사용한다.

- BotFather에서 Allowed URL 등록
- Authorization Code Flow + PKCE
- 서버에서 ID Token 서명 및 `iss`, `aud`, `exp`, `nonce` 검증
- Telegram 사용자 ID와 내부 `users` 레코드 연결
- 미등록 사용자는 `pending`으로 생성하고 관리자가 활성화한다.
- 입사일은 최초 Telegram 연결 시 필수가 아니며 이후 관리자 또는 사용자가 입력한다.

최초 관리자 bootstrap은 Setup Wizard에서 진행한다.

1. @BotFather에서 Bot과 Login Widget/OIDC를 구성한다.
2. Setup이 현재 host에서 Allowed Origin과 Redirect URI를 자동 계산한다.
3. 최초 관리자가 Bot 개인 채팅에 `/start`를 보내고, 관리자와 직원이 함께 사용하는 회사 공용 그룹에도 메시지를 1회 보낸다.
4. 최근 채팅 자동 탐색으로 최초 관리자 User ID와 회사 공용 그룹 Chat ID를 선택한다.
5. Telegram OIDC 로그인 결과가 저장된 최초 관리자 User ID와 일치하면 `admin + active`로 활성화된다.

일반 직원은 설치 완료 후 서비스 로그인 링크를 통해 Telegram User ID 기준으로 연결한다.

참고: https://core.telegram.org/bots/telegram-login

## 공휴일

한국 공휴일은 공공데이터포털의 한국천문연구원 특일 정보 API에서 갱신한다.

- API 갱신
- DB 저장
- 관리자의 수동 추가/수정 지원
- 회사 자체 휴무일도 별도 source로 저장

참고: https://www.data.go.kr/dataset/15012690/openapi.do


## 설치/운영 방향

신규 설치는 migration을 누적 적용하지 않고 최신 `database/schema.sql`을 사용한다. `/setup`은 CLI에서 생성한 설치 키로 보호하고 DB → Telegram → 채팅/관리자 → 공휴일 순서로 진행한다.

운영 중에는 회사명/로고/대표색/테마, Telegram 연결 및 회사 공용 그룹, 공휴일 ServiceKey를 관리자 환경설정에서 변경할 수 있다.

Telegram 알림은 역할별로 분리한다. 휴가 신청/잔여 경고 등 관리용 정보는 활성 관리자 개인 Telegram으로만 전송하고, 승인된 휴가 일정과 일정 취소만 회사 공용 그룹에 공유한다.
