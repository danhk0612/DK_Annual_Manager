# 초기 설치 흐름

신규 설치는 migration 없이 현재 `database/schema.sql`을 기준으로 진행한다.

## 0. 설치 접근 보호

웹에 `/setup`이 그대로 노출되지 않도록 설치 키를 사용한다.

```bash
php bin/setup-key.php
```

출력된 `/setup?setup_key=...` 주소로 한 번 접속하면 현재 브라우저 세션만 설치 권한을 얻고, 키는 URL에서 제거된다. 원본 키는 파일에 저장하지 않고 SHA-256 해시만 `storage/setup.key`에 보관한다. 이 파일은 웹 공개 루트 밖에 두고 웹 서버/PHP 실행 계정이 읽을 수 있게 한다. 설치 완료 시 `storage/setup.key`는 삭제된다.

테스트용 완전 초기화:

```bash
php bin/reset-install.php --confirm=RESET-INSTALL
```

이 명령은 DB 테이블과 업로드 로고를 삭제하고 새 setup key/URL을 함께 출력한다.

## 1. DB

Setup Step 1에서 최신 schema를 생성한다. schema는 `CREATE TABLE IF NOT EXISTS`, 초기 휴가 종류는 `INSERT IGNORE`를 사용해 중간 실패 후 다시 실행해도 복구 가능하도록 한다.

## 2. Telegram Bot / OIDC

Telegram에서 직접 발급해야 하는 항목:

- Bot Token: @BotFather에서 `/newbot`
- Client ID / Client Secret: BotFather → 해당 Bot → Login Widget

앱에서 자동 처리하는 항목:

- 현재 접속 host/proxy 정보를 이용한 Allowed Origin 계산
- `/auth/telegram/callback` Redirect URI 계산
- 복사 버튼
- Bot Token을 이용한 `getMe` 연결 검증
- Client Secret/Bot Token 기존값 비노출

Telegram에 등록해야 하는 주소 예:

```text
Allowed Origin: https://leave.example.com
Redirect URI:   https://leave.example.com/auth/telegram/callback
```

리버스 프록시 감지가 실제 외부 주소와 다른 경우 Redirect URI만 직접 수정할 수 있다.

## 3. 최초 관리자 개인 채팅 / 회사 공용 그룹

### 3A. 최초 관리자 개인 채팅

1. 최초 관리자가 Bot 개인 채팅을 연다.
2. `/start`를 보낸다.
3. Setup에서 **최근 채팅 자동 확인**을 실행한다.
4. private 채팅 후보에서 Telegram User ID를 선택한다.

### 3B. 회사 공용 그룹

1. 관리자와 직원이 함께 사용할 회사 Telegram 그룹을 준비한다.
2. Bot을 그룹에 추가한다.
3. 그룹에 메시지를 한 번 보낸다.
4. Setup에서 **최근 채팅 자동 확인**을 실행한다.
5. group/supergroup Chat ID를 회사 공용 그룹으로 선택한다.

회사 공용 그룹에는 승인된 휴가 일정/일정 취소만 공유한다. 신청 사유, 잔여 연차 경고 등 관리 정보는 활성 관리자 개인 Telegram으로만 전송한다.

Bot API만으로 Telegram 그룹 자체를 생성하거나 Bot을 스스로 그룹에 가입시키지는 않는다. 이를 자동화하려면 사용자 MTProto 세션이 필요해 설치 구조가 크게 복잡해지므로 현재 프로젝트에서는 기존 그룹 연결 방식으로 유지한다.

자동 탐색이 되지 않으면 User ID/Chat ID를 직접 입력할 수 있다.

## 4. 최초 관리자 웹 로그인

Step 3에서 저장한 Telegram User ID와 OIDC 로그인 결과가 일치하면 해당 사용자를 `admin + active`로 활성화한다.

## 5. 공휴일 API

공공데이터포털 한국천문연구원 특일 정보 ServiceKey를 입력하면 저장 전 연결을 확인하고 현재 연도를 즉시 동기화한다.

## 6. 설치 완료

필수 단계가 모두 정상이고 활성 관리자 계정이 존재할 때만 완료할 수 있다. 완료 후 setup key를 삭제하고 일반 서비스 화면으로 전환한다.

기존 버전에서 이미 운영 중이고 활성 관리자가 있는 DB는 최초 T15 실행 시 legacy 설치로 인식해 `setup.completed`을 자동 생성한다.

## 직원 연결

설치 완료 후 관리자 → 환경 설정의 직원 초대 링크를 사용한다.

- **서비스 로그인 링크**: 기본 연결 경로. Telegram OIDC의 `telegram:bot_access` scope를 포함해 로그인과 개인 알림 권한을 함께 요청한다.
- **Telegram Bot 링크**: Bot 개인 채팅을 미리 여는 보조 경로.

직원은 Telegram 로그인 후 `telegram_user_id` 기준으로 사용자 계정이 연결된다.
