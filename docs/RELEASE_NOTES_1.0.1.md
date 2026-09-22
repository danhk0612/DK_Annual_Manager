# DK Annual Manager 1.0.1

재설치·완전 초기화 동작을 명확하게 하고 검증을 강화한 핫픽스입니다.

## 수정 내용

- 완전 초기화 대상에 `schema_migrations`를 포함했습니다.
- 완전 초기화 후 서비스 테이블이 실제로 모두 삭제됐는지 DB에서 다시 확인합니다.
- 휴가 신청 내역이 존재하는 상태에서 완전 초기화 → 재설치 후 `leave_requests`가 0건인지 MariaDB 통합 테스트로 검증합니다.
- 설치 마법사의 혼동되던 **DB 초기화** 표현을 **DB 스키마 생성**으로 변경했습니다.
- 설치 마법사의 DB 스키마 생성은 기존 데이터를 삭제하는 기능이 아니라는 안내를 추가했습니다.
- 기존 설치를 지우려면 관리자 환경 설정의 **재설치 · 완전 초기화** 또는 `php bin/reset-install.php --confirm=RESET-INSTALL`을 사용하도록 문서를 명확히 했습니다.

## 업데이트

DB 백업 후:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
composer audit --locked
php bin/migrate.php
php bin/check.php --production
```

이번 변경은 일반 업데이트 시 기존 업무 데이터를 삭제하지 않습니다.

## 라이선스

MIT License
