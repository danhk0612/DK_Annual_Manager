# 설치 및 운영 설정

## 요구 환경

- PHP 8.2 이상
- MariaDB 10.6 이상
- Composer 2
- PHP 확장: `curl`, `json`, `pdo`, `pdo_mysql`, `simplexml`
- HTTPS 사용 권장(운영 Telegram 로그인에는 사실상 필수)
- Apache 사용 시 `mod_rewrite`

웹 서버의 DocumentRoot는 저장소 루트가 아니라 반드시 `public/`으로 지정한다.

## 1. 소스 및 Composer

```bash
git clone https://github.com/danhk0612/DK_Annual_Manager.git
cd DK_Annual_Manager
composer install --no-dev --optimize-autoloader
```

개발/테스트 환경에서는 `--no-dev`를 빼고 설치한다.

## 2. MariaDB

빈 데이터베이스와 전용 사용자를 만든다. 애플리케이션 계정에는 해당 DB에 대한 일반적인 DML/DDL 권한이 필요하지만 DB 자체를 생성하는 권한은 필요하지 않다.

신규 설치는 최신 `database/schema.sql`만 적용한다.

```bash
mysql -u <관리자계정> -p <DB명> < database/schema.sql
```

`database/migrations/`은 이전 버전으로 이미 설치한 DB를 최신 구조로 올릴 때만 순서대로 적용한다. 신규 설치 시 schema에 최신 변경이 포함되어 있으므로 migrations를 다시 적용하지 않는다.

## 3. 설정 파일

```bash
cp config/config.example.php config/config.php
```

`config/config.php`에서 다음을 설정한다.

- `app.url`: 실제 HTTPS 서비스 주소
- `app.timezone`: 기본 `Asia/Seoul`
- `app.debug`: 운영에서는 `false`
- `database.*`: MariaDB 접속 정보
- 신규 설치에서는 Telegram/OIDC, 관리자 Chat ID, 최초 관리자 ID, 공휴일 ServiceKey를 `/setup`에서 입력한다.
- `telegram.*`, `holiday_api.service_key`의 config 값은 레거시/비상 기본값으로만 사용할 수 있다.

실제 `config/config.php`는 `.gitignore` 대상이며 저장소에 커밋하지 않는다.

설치 후 회사명, 로고, 대표색, 테마, Telegram Client ID/Secret/Bot Token, 관리자 알림 Chat ID, 공휴일 ServiceKey를 **관리자 → 환경 설정**에서 관리할 수 있다. Secret/Token/API Key는 저장 후 화면에 기존 값을 다시 노출하지 않으며 Audit Log에도 실제 값을 기록하지 않는다.

## 4. Telegram 최초 관리자

신규 설치에서는 `/setup`이 현재 접속 host를 기준으로 Allowed Origin과 Redirect URI를 자동 계산한다.

1. @BotFather에서 `/newbot`으로 Bot 생성 후 Bot Token 확보
2. 해당 Bot의 **Login Widget**에서 자동 계산된 Allowed Origin / Redirect URI 등록
3. 같은 화면에서 Client ID / Client Secret 확인
4. Setup Step 2에 값을 입력하고 Bot 연결 확인
5. 최초 관리자 개인 채팅에서 `/start`, 관리자 그룹에서도 메시지 1회 전송
6. Setup Step 3의 최근 채팅 자동 확인으로 개인 User ID와 그룹 Chat ID 선택
7. Setup Step 4에서 Telegram 로그인하면 최초 관리자 활성화

## 5. 웹 서버

### Apache

DocumentRoot를 `/path/to/DK_Annual_Manager/public`으로 지정하고 `AllowOverride All` 또는 동등한 rewrite 설정을 허용한다. `public/.htaccess`가 front-controller 라우팅을 처리한다.

### Nginx 예시

```nginx
root /path/to/DK_Annual_Manager/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
}
```

PHP-FPM 소켓 경로는 서버 환경에 맞게 변경한다.

## 6. 환경 점검

설정이 끝나면 읽기 전용 점검 명령을 실행한다.

```bash
php bin/check.php
```

이 명령은 PHP 버전/확장, 설정파일, DB 연결, 필수 테이블, HTTPS/세션 설정, Telegram 및 공휴일 API 설정 여부를 확인하며 데이터를 변경하지 않는다.

웹에서도 `/health`가 `{ "status": "ok" }`를 반환하는지 확인한다.

## 7. 브랜딩 업로드 권한

회사 로고는 `public/uploads/branding/`에 저장된다. Web Station/PHP-FPM 실행 계정이 이 디렉터리에 파일을 생성/교체할 수 있어야 한다.

`php bin/check.php`는 CLI 기준 쓰기 가능 여부도 확인한다. CLI 사용자는 쓰기 가능하지만 웹에서 업로드가 실패하면 Web Station/PHP-FPM 실행 계정의 폴더 권한을 별도로 확인한다.

## 8. 운영 전 필수 확인

- `app.debug=false`
- HTTPS 적용
- `session_cookie_secure=true`
- `config/config.php` 웹 직접 접근 불가(DocumentRoot가 public인지 확인)
- Telegram 실제 로그인 성공
- 관리자/일반 사용자 권한 분리 확인
- 공휴일 현재/다음 연도 업데이트
- 연차 발생 동기화 후 직원별 잔여 검토
- 휴가 신청 → 관리자 승인 → 연차 차감 → Telegram 알림 흐름 확인
- Audit Log 기록 확인
- DB 백업 정책 설정

## 업그레이드

1. DB 백업
2. 코드 업데이트
3. `composer install --no-dev --optimize-autoloader`
4. 새 migration이 있으면 파일명 순서대로 적용
5. `php bin/check.php`
6. `/health`와 핵심 업무 흐름 확인


## 신규 설치 마법사

DB 접속이 가능한 최소 `config/config.php`와 Composer 의존성 설치가 끝나면 먼저 설치 접근 키를 생성한다.

```bash
php84 bin/setup-key.php
```

출력된 `/setup?setup_key=...` 주소로 접속한다. 키는 첫 접속 후 URL에서 제거되고 설치 완료 시 파일도 삭제된다. 신규 설치에서는 별도 migration을 적용하지 않고 최신 `database/schema.sql`을 사용한다.

설치 마법사는 다음 순서로 진행한다.

1. DB schema 생성
2. Telegram Bot/OIDC 연결
3. 최근 Telegram 채팅에서 관리자 그룹과 최초 관리자 계정 선택
4. 최초 관리자 Telegram 로그인
5. 공휴일 API ServiceKey 연결 및 현재 연도 동기화
6. 초기 설정 완료

Telegram Client Secret, Bot Token, 공휴일 API ServiceKey 등 서비스 연결 정보는 설치 완료 후 **관리자 → 환경 설정**에서 변경할 수 있다. 기존 값 자체는 화면에 다시 표시하지 않는다.

### 테스트 환경 완전 초기화

아래 명령은 휴가관리 DB의 모든 테이블과 업로드한 회사 로고를 삭제한다. `config/config.php`, Composer 파일, 소스코드는 유지된다.

```bash
php84 bin/reset-install.php --confirm=RESET-INSTALL
```

초기화 명령이 새 setup key와 보호된 Setup URL을 함께 출력한다. 그 URL로 신규 설치 흐름을 처음부터 검증한다. 운영 데이터가 있는 환경에서는 이 명령을 사용하지 않는다.
