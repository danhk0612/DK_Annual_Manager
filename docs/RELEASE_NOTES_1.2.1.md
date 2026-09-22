# DK Annual Manager 1.2.1

v1.2.0 Excel 출력 기능의 호환성 오류와 출력 카드 간격을 수정한 핫픽스입니다.

## Excel 파일 복구 경고 수정

v1.2.0에서 생성한 XLSX의 `xl/styles.xml` 안에 잘못 닫힌 XML 태그가 있어 Microsoft Excel에서 다음과 같은 복구 경고가 나타날 수 있었습니다.

- `/xl/styles.xml` 스타일 XML 오류
- `/xl/worksheets/sheet1.xml` 셀 정보 복구

테두리 스타일의 잘못된 `</bottom/>` 태그를 정상 `</bottom>` 태그로 수정했습니다.

## 회귀 방지

기존에는 XLSX의 ZIP 컨테이너 구조만 검사했기 때문에 내부 XML 문법 오류를 감지하지 못했습니다.

v1.2.1부터 자동 테스트에서 생성된 XLSX의 모든 XML/관계 파일을 실제 XML 파서로 읽어 문법 오류가 없는지 확인합니다.

검증 대상에는 다음이 포함됩니다.

- `[Content_Types].xml`
- `_rels/.rels`
- `xl/workbook.xml`
- `xl/_rels/workbook.xml.rels`
- `xl/styles.xml`
- `xl/worksheets/sheet1.xml`

## UI 간격 개선

다음 두 영역에서 Excel 출력 폼 바로 아래 안내 문구가 입력 영역에 너무 붙어 보이던 문제를 수정했습니다.

- 내 정보 → 내 휴가 엑셀 출력
- 관리 → 휴가 집계 → 휴가 집계 엑셀 출력

공통 안내문 위쪽에 여백을 추가했습니다.

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
