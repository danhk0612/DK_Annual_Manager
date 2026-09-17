# 한국 공휴일 동기화 정책

## 데이터 소스

공공데이터포털의 `한국천문연구원_특일 정보` OpenAPI를 사용한다.

- 서비스: `SpcdeInfoService`
- 공휴일 조회: `getRestDeInfo`
- 기본 URL: `https://apis.data.go.kr/B090041/openapi/service/SpcdeInfoService`
- 응답: XML
- 설정: `config/config.php`의 `holiday_api.service_key`

서비스키는 공공데이터포털에서 받은 Encoding/Decoding 키 어느 쪽을 입력해도 클라이언트에서 URL decoding 후 요청한다.

## 동기화 방식

관리자가 연도를 선택해 수동으로 갱신한다.

1. 선택 연도의 1~12월 공휴일을 API에서 조회한다.
2. `isHoliday=Y` 항목만 취급한다.
3. 전체 월 조회가 성공하고 1건 이상 수집된 경우에만 DB를 갱신한다.
4. 해당 연도의 `source=public_api` 행만 트랜잭션으로 교체한다.
5. `manual`, `company` 행은 자동 동기화에서 변경하거나 삭제하지 않는다.

API가 오류를 반환하거나 결과가 비어 있으면 기존 `public_api` 데이터도 유지한다.

## 수동 휴일

관리자 화면에서 다음 두 유형을 추가할 수 있다.

- `company`: 회사 자체 휴무일
- `manual`: 별도 수동 공휴일

각 항목은 `is_public_holiday`를 설정할 수 있다. 값이 1이면 휴가 신청의 근무일 계산에서 제외되고, 0이면 달력에만 표시된다.

## 운영 확인

공휴일 데이터는 휴가 신청 기간의 주말/공휴일 제외 계산에 직접 사용되므로, 새 연도 사용 전 해당 연도의 API 갱신 상태를 확인한다.
