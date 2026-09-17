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
- `telegram.client_id`, `client_secret`, `bot_token`
- `telegram.redirect_uri`: `<서비스주소>/auth/telegram/callback`
- `telegram.bootstrap_admin_telegram_ids`: 최초 관리자 Telegram User ID
- `telegram.admin_chat_ids`: 필요 시 추가 관리자 알림 대상
- `holiday_api.service_key`: 공공데이터포털 한국천문연구원 특일정보 ServiceKey

실제 `config/config.php`는 `.gitignore` 대상이며 저장소에 커밋하지 않는다.

## 4. Telegram 최초 관리자

1. BotFather에서 Telegram Login/OIDC에 사용할 Bot/Client를 구성한다.
2. 서비스 HTTPS URL과 callback URL을 허용한다.
3. 일반 Telegram 로그인을 한 번 실행해 pending 계정을 만든다.
4. 화면에 표시되는 Telegram User ID를 확인한다.
5. 해당 ID를 `telegram.bootstrap_admin_telegram_ids`에 넣는다.
6. 다시 로그인하면 관리자 계정으로 활성화된다.
7. 이후 직원/권한 관리는 관리자 화면에서 수행한다.

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

## 7. 운영 전 필수 확인

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
