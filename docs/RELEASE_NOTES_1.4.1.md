# DK Annual Manager 1.4.1

관리자 직접 휴가 등록 화면에서 입사일이 정상 등록된 직원에게도 **입사일 미등록 경고가 보이던 UI 오류**를 수정한 핫픽스입니다.

## 원인

입사일 판정과 서버 검증은 정상 동작하고 있었습니다.

- 선택한 직원의 `hire_date`를 정상 읽음
- 등록 버튼 정상 활성화
- 서버에서도 대상 직원의 입사일을 정상 확인
- 실제 휴가 등록도 정상 처리

문제는 경고 요소에 HTML `hidden` 속성이 적용되어 있어도 `.notice` 등의 컴포넌트 스타일 때문에 브라우저에서 화면에 표시될 수 있다는 점이었습니다.

## 수정

전역 CSS에 다음 규칙을 추가했습니다.

```css
[hidden] {
    display: none !important;
}
```

따라서 JavaScript 또는 서버 템플릿에서 `hidden`으로 지정한 요소는 다른 스타일 규칙과 관계없이 확실하게 숨겨집니다.

이번 수정은 관리자 직접 등록의 입사일 경고뿐 아니라 동일한 방식의 숨김 UI 재발도 방지합니다.

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
