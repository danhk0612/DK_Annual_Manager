# DK Annual Manager 1.4.3

변경 이력 페이지의 테이블 레이아웃을 수정한 UI 핫픽스입니다.

## 문제

공통 데이터 테이블은 기본적으로:

- 셀 내용을 한 줄로 유지
- 첫 번째 열을 sticky 고정
- 내용이 길어지면 가로 스크롤

하도록 구성되어 있습니다.

변경 이력은 상세 정보가 길기 때문에 이 규칙과 맞지 않아 테이블 폭이 과도하게 커지고, 가로 스크롤 이동 시 고정된 첫 열과 다른 데이터가 겹쳐 보일 수 있었습니다.

## 수정

변경 이력 표에 전용 레이아웃을 적용했습니다.

- 전체 폭 안에서 고정 레이아웃 사용
- 가로 스크롤 제거
- 첫 번째 열 sticky 해제
- 모든 셀에 자연스러운 줄바꿈 허용
- 긴 문자열도 셀 폭 안에서 강제 줄바꿈
- 상세 내용은 key=value 항목 단위로 wrapping
- 좁은 화면에서는 열 비율과 패딩을 조정

따라서 변경 이력 페이지는 이제 화면 너비 안에서 내용을 표시하며, 데이터가 서로 겹치지 않습니다.

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
