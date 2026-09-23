<?php

declare(strict_types=1);

use DKAnnual\Export\XlsxWriter;
use PHPUnit\Framework\TestCase;

final class XlsxWriterTest extends TestCase
{
    public function testBuildCreatesValidStoredZipWithSpreadsheetParts(): void
    {
        $writer = new XlsxWriter();
        $xlsx = $writer->build(
            '휴가 내역',
            [
                ['휴가 내보내기'],
                [],
                ['이름', '일수', '사유'],
                ['홍길동', 1.5, '=FORMULA가 아닌 문자열'],
            ],
            [2],
            [0],
            [16, 10, 28],
            3,
        );

        self::assertStringStartsWith("PK\x03\x04", $xlsx);
        self::assertSame("PK\x05\x06", substr($xlsx, -22, 4));
        self::assertStringContainsString('[Content_Types].xml', $xlsx);
        self::assertStringContainsString('xl/workbook.xml', $xlsx);
        self::assertStringContainsString('xl/worksheets/sheet1.xml', $xlsx);
        self::assertStringContainsString('휴가 내역', $xlsx);
        self::assertStringContainsString('=FORMULA가 아닌 문자열', $xlsx);
        self::assertStringContainsString("PK\x05\x06", $xlsx);

        $entries = $this->assertZipStructure($xlsx, 6);
        $this->assertXmlEntriesAreWellFormed($entries);
    }

    public function testBuildWorkbookCreatesMultipleStyledSheets(): void
    {
        $writer = new XlsxWriter();
        $xlsx = $writer->buildWorkbook([
            [
                'name' => '요약',
                'rows' => [
                    ['보고서', null, null],
                    ['설명', null, null],
                    [],
                    ['항목', '값', '비고'],
                    ['승인일수', 2.5, '정상'],
                ],
                'row_styles' => [0 => 'title', 1 => 'note', 3 => 'header'],
                'cell_styles' => ['B5' => 'number', 'C5' => 'status_approved'],
                'column_widths' => [18, 12, 20],
                'freeze_rows' => 4,
                'merge_cells' => ['A1:C1', 'A2:C2'],
                'auto_filter' => 'A4:C5',
            ],
            [
                'name' => '월 2026-09',
                'rows' => [
                    ['월별 기록'],
                    [],
                    ['날짜', '일수'],
                    [46283, 1.0],
                ],
                'row_styles' => [0 => 'title', 2 => 'header'],
                'cell_styles' => ['A4' => 'date', 'B4' => 'number'],
                'freeze_rows' => 3,
            ],
        ]);

        self::assertStringContainsString('요약', $xlsx);
        self::assertStringContainsString('월 2026-09', $xlsx);
        self::assertStringContainsString('xl/worksheets/sheet2.xml', $xlsx);

        $entries = $this->assertZipStructure($xlsx, 7);
        $this->assertXmlEntriesAreWellFormed($entries);

        self::assertArrayHasKey('xl/worksheets/sheet2.xml', $entries);
        self::assertStringContainsString('<autoFilter ref="A4:C5"/>', $entries['xl/worksheets/sheet1.xml']);
        self::assertStringContainsString('<mergeCell ref="A1:C1"/>', $entries['xl/worksheets/sheet1.xml']);
        self::assertStringContainsString('state="frozen"', $entries['xl/worksheets/sheet1.xml']);
        self::assertStringContainsString('numFmtId="166" formatCode="0.0"', $entries['xl/styles.xml']);
    }

    /**
     * @return array<string, string>
     */
    private function assertZipStructure(string $zip, int $expectedEntries): array
    {
        $eocd = strrpos($zip, "PK\x05\x06");
        self::assertNotFalse($eocd);

        $end = unpack('Vsignature/vdisk/vcentral_disk/ventries_disk/ventries/Vcentral_size/Vcentral_offset/vcomment_length', substr($zip, $eocd, 22));
        self::assertIsArray($end);
        self::assertSame(0x06054b50, $end['signature']);
        self::assertSame($expectedEntries, $end['entries']);
        self::assertSame($expectedEntries, $end['entries_disk']);
        self::assertSame($eocd - $end['central_size'], $end['central_offset']);

        $position = 0;
        $localCount = 0;
        $entries = [];
        while ($position < $end['central_offset']) {
            $header = unpack(
                'Vsignature/vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vname_length/vextra_length',
                substr($zip, $position, 30),
            );
            self::assertIsArray($header);
            self::assertSame(0x04034b50, $header['signature']);
            self::assertSame(0, $header['method']);

            $nameStart = $position + 30;
            $name = substr($zip, $nameStart, $header['name_length']);
            $dataStart = $nameStart + $header['name_length'] + $header['extra_length'];
            $data = substr($zip, $dataStart, $header['compressed']);
            self::assertSame($header['uncompressed'], strlen($data));
            self::assertSame($header['crc'], crc32($data));

            $entries[$name] = $data;
            $position = $dataStart + $header['compressed'];
            $localCount++;
        }

        self::assertSame($expectedEntries, $localCount);
        self::assertSame($end['central_offset'], $position);
        self::assertSame("PK\x01\x02", substr($zip, $end['central_offset'], 4));

        return $entries;
    }

    /**
     * @param array<string, string> $entries
     */
    private function assertXmlEntriesAreWellFormed(array $entries): void
    {
        foreach ($entries as $name => $content) {
            if (!str_ends_with($name, '.xml') && !str_ends_with($name, '.rels')) {
                continue;
            }

            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();
            $parsed = simplexml_load_string($content);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            self::assertNotFalse(
                $parsed,
                sprintf(
                    '%s is not well-formed XML: %s',
                    $name,
                    implode(' | ', array_map(
                        static fn (LibXMLError $error): string => trim($error->message),
                        $errors,
                    )),
                ),
            );
        }

        self::assertArrayHasKey('xl/styles.xml', $entries);
        self::assertStringContainsString('</bottom><diagonal/>', $entries['xl/styles.xml']);
        self::assertStringNotContainsString('</bottom/>', $entries['xl/styles.xml']);
    }
}
