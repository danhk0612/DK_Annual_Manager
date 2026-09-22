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
        self::assertStringEndsWith(substr($xlsx, -22), $xlsx);
        self::assertStringContainsString('[Content_Types].xml', $xlsx);
        self::assertStringContainsString('xl/workbook.xml', $xlsx);
        self::assertStringContainsString('xl/worksheets/sheet1.xml', $xlsx);
        self::assertStringContainsString('휴가 내역', $xlsx);
        self::assertStringContainsString('=FORMULA가 아닌 문자열', $xlsx);
        self::assertStringContainsString("PK\x05\x06", $xlsx);

        $this->assertZipStructure($xlsx);
    }

    private function assertZipStructure(string $zip): void
    {
        $eocd = strrpos($zip, "PK\x05\x06");
        self::assertNotFalse($eocd);

        $end = unpack('Vsignature/vdisk/vcentral_disk/ventries_disk/ventries/Vcentral_size/Vcentral_offset/vcomment_length', substr($zip, $eocd, 22));
        self::assertIsArray($end);
        self::assertSame(0x06054b50, $end['signature']);
        self::assertSame(6, $end['entries']);
        self::assertSame(6, $end['entries_disk']);
        self::assertSame($eocd - $end['central_size'], $end['central_offset']);

        $position = 0;
        $localCount = 0;
        while ($position < $end['central_offset']) {
            $header = unpack(
                'Vsignature/vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vname_length/vextra_length',
                substr($zip, $position, 30),
            );
            self::assertIsArray($header);
            self::assertSame(0x04034b50, $header['signature']);
            self::assertSame(0, $header['method']);

            $nameStart = $position + 30;
            $dataStart = $nameStart + $header['name_length'] + $header['extra_length'];
            $data = substr($zip, $dataStart, $header['compressed']);
            self::assertSame($header['uncompressed'], strlen($data));
            self::assertSame($header['crc'], crc32($data));

            $position = $dataStart + $header['compressed'];
            $localCount++;
        }

        self::assertSame(6, $localCount);
        self::assertSame($end['central_offset'], $position);
        self::assertSame("PK\x01\x02", substr($zip, $end['central_offset'], 4));
    }
}
