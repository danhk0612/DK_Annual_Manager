<?php

declare(strict_types=1);

namespace DKAnnual\Holiday;

use DKAnnual\Config;
use GuzzleHttp\Client;
use RuntimeException;
use SimpleXMLElement;

final class KasiHolidayClient
{
    private readonly Client $http;

    public function __construct(private readonly Config $config)
    {
        $this->http = new Client([
            'timeout' => 15.0,
            'http_errors' => true,
        ]);
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->config->get('holiday_api.service_key', '')) !== '';
    }

    /** @return list<array{holiday_date: string, name: string, external_key: string}> */
    public function fetchYear(int $year): array
    {
        if ($year < 1900 || $year > 2200) {
            throw new RuntimeException('조회 연도가 올바르지 않습니다.');
        }

        $serviceKey = rawurldecode(trim((string) $this->config->get('holiday_api.service_key', '')));
        if ($serviceKey === '') {
            throw new RuntimeException('공휴일 API 서비스키가 설정되지 않았습니다.');
        }

        $baseUrl = rtrim((string) $this->config->get(
            'holiday_api.base_url',
            'https://apis.data.go.kr/B090041/openapi/service/SpcdeInfoService',
        ), '/');
        $url = $baseUrl . '/getRestDeInfo';

        /** @var array<string, array{holiday_date: string, name: string, external_key: string}> $collected */
        $collected = [];

        for ($month = 1; $month <= 12; $month++) {
            $response = $this->http->get($url, [
                'query' => [
                    'ServiceKey' => $serviceKey,
                    'pageNo' => 1,
                    'numOfRows' => 100,
                    'solYear' => sprintf('%04d', $year),
                    'solMonth' => sprintf('%02d', $month),
                ],
            ]);

            foreach ($this->parseResponse((string) $response->getBody()) as $holiday) {
                $key = $holiday['holiday_date'] . '|' . $holiday['name'];
                $collected[$key] = $holiday;
            }
        }

        $holidays = array_values($collected);
        usort($holidays, static function (array $left, array $right): int {
            return [$left['holiday_date'], $left['name']] <=> [$right['holiday_date'], $right['name']];
        });

        return $holidays;
    }

    /** @return list<array{holiday_date: string, name: string, external_key: string}> */
    private function parseResponse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$document instanceof SimpleXMLElement) {
            throw new RuntimeException('공휴일 API XML 응답을 해석하지 못했습니다.');
        }

        $resultCode = trim((string) ($document->header->resultCode ?? ''));
        $resultMessage = trim((string) ($document->header->resultMsg ?? ''));
        if (!in_array($resultCode, ['00', '0000'], true)) {
            throw new RuntimeException(sprintf(
                '공휴일 API 오류: %s%s',
                $resultCode !== '' ? $resultCode : 'UNKNOWN',
                $resultMessage !== '' ? ' - ' . $resultMessage : '',
            ));
        }

        if (!isset($document->body->items->item)) {
            return [];
        }

        $result = [];
        foreach ($document->body->items->item as $item) {
            if (strtoupper(trim((string) ($item->isHoliday ?? 'N'))) !== 'Y') {
                continue;
            }

            $locdate = preg_replace('/\D/', '', (string) ($item->locdate ?? '')) ?? '';
            $name = trim((string) ($item->dateName ?? ''));
            if (strlen($locdate) !== 8 || $name === '') {
                continue;
            }

            $date = substr($locdate, 0, 4) . '-' . substr($locdate, 4, 2) . '-' . substr($locdate, 6, 2);
            $seq = trim((string) ($item->seq ?? '0'));
            $dateKind = trim((string) ($item->dateKind ?? '01'));

            $result[] = [
                'holiday_date' => $date,
                'name' => mb_substr($name, 0, 120),
                'external_key' => sprintf('KASI:%s:%s:%s', $locdate, $seq, $dateKind),
            ];
        }

        return $result;
    }
}
