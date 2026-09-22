# DK Annual Manager 1.3.1

내 정보 페이지의 카드 간격을 조정한 UI 핫픽스입니다.

## 내 정보 레이아웃 수정

`내 휴가 엑셀 출력` 카드 아래와 `계정 정보 / 근무 정보` 영역 사이에 간격이 적용되지 않던 문제를 수정했습니다.

원인은 공통 `.panel + .panel` 규칙이 다음 구조에서는 적용되지 않았기 때문입니다.

```
.panel.export-panel
.content-grid
  .panel
  .panel
```

v1.3.1에서는 프로필의 계정/근무 정보 묶음에 전용 `profile-account-grid` 클래스를 추가하고 상단 여백 20px을 적용했습니다.

다른 `content-grid` 화면에는 영향을 주지 않습니다.

## 데이터베이스

신규 DB migration은 없습니다.

## 업데이트

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
composer audit --locked
php bin/migrate.php
php bin/check.php --production
```

## 라이선스

MIT License
