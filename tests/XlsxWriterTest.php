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

        $entries = $this->assertZipStructure($xlsx);
        $this->assertXmlEntriesAreWellFormed($entries);
    }

    /**
     * @return array<string, string>
     */
    private function assertZipStructure(string $zip): array
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

        self::assertSame(6, $localCount);
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
