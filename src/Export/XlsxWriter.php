<?php

declare(strict_types=1);

namespace DKAnnual\Export;

final class XlsxWriter
{
    /**
     * @param list<list<string|int|float|null>> $rows
     * @param list<int> $headerRows Zero-based row indexes.
     * @param list<int> $titleRows Zero-based row indexes.
     * @param list<float|int> $columnWidths
     */
    public function build(
        string $sheetName,
        array $rows,
        array $headerRows = [],
        array $titleRows = [],
        array $columnWidths = [],
        int $freezeRows = 0,
    ): string {
        $sheetName = $this->safeSheetName($sheetName);
        $sheetXml = $this->sheetXml($rows, $headerRows, $titleRows, $columnWidths, $freezeRows);

        return $this->zip([
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'xl/workbook.xml' => $this->workbookXml($sheetName),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ]);
    }

    /** @param list<list<string|int|float|null>> $rows */
    private function sheetXml(
        array $rows,
        array $headerRows,
        array $titleRows,
        array $columnWidths,
        int $freezeRows,
    ): string {
        $headerLookup = array_fill_keys($headerRows, true);
        $titleLookup = array_fill_keys($titleRows, true);

        $columns = '';
        foreach ($columnWidths as $index => $width) {
            $column = $index + 1;
            $safeWidth = max(5.0, min(60.0, (float) $width));
            $columns .= sprintf(
                '<col min="%d" max="%d" width="%s" customWidth="1"/>',
                $column,
                $column,
                rtrim(rtrim(number_format($safeWidth, 2, '.', ''), '0'), '.'),
            );
        }

        $sheetRows = '';
        foreach ($rows as $rowIndex => $cells) {
            $excelRow = $rowIndex + 1;
            $style = isset($titleLookup[$rowIndex]) ? 2 : (isset($headerLookup[$rowIndex]) ? 1 : 0);
            $cellXml = '';

            foreach ($cells as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1) . $excelRow;
                $styleAttribute = $style > 0 ? ' s="' . $style . '"' : '';

                if (is_int($value) || is_float($value)) {
                    $number = is_float($value)
                        ? rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.')
                        : (string) $value;
                    $cellXml .= '<c r="' . $reference . '"' . $styleAttribute . '><v>' . $number . '</v></c>';
                    continue;
                }

                $text = $this->xmlText((string) ($value ?? ''));
                $cellXml .= '<c r="' . $reference . '" t="inlineStr"' . $styleAttribute . '>'
                    . '<is><t xml:space="preserve">' . $text . '</t></is></c>';
            }

            $sheetRows .= '<row r="' . $excelRow . '">' . $cellXml . '</row>';
        }

        $sheetView = '<sheetView workbookViewId="0">';
        if ($freezeRows > 0) {
            $topLeft = 'A' . ($freezeRows + 1);
            $sheetView .= '<pane ySplit="' . $freezeRows . '" topLeftCell="' . $topLeft
                . '" activePane="bottomLeft" state="frozen"/>';
        }
        $sheetView .= '</sheetView>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews>' . $sheetView . '</sheetViews>'
            . ($columns !== '' ? '<cols>' . $columns . '</cols>' : '')
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '</worksheet>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xmlText($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF315EFB"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFD3DAE7"/></bottom/><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        $count = 0;
        [$dosTime, $dosDate] = $this->dosTimestamp();

        foreach ($files as $name => $content) {
            $nameLength = strlen($name);
            $size = strlen($content);
            $crc = crc32($content);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                $nameLength,
                0,
            );
            $local .= $localHeader . $name . $content;

            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                $nameLength,
                0,
                0,
                0,
                0,
                0,
                $offset,
            );
            $central .= $centralHeader . $name;

            $offset += strlen($localHeader) + $nameLength + $size;
            $count++;
        }

        $end = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            strlen($central),
            strlen($local),
            0,
        );

        return $local . $central . $end;
    }

    /** @return array{0:int,1:int} */
    private function dosTimestamp(): array
    {
        $now = getdate();
        $year = max(1980, (int) $now['year']);

        $time = ((int) $now['hours'] << 11)
            | ((int) $now['minutes'] << 5)
            | intdiv((int) $now['seconds'], 2);
        $date = (($year - 1980) << 9)
            | ((int) $now['mon'] << 5)
            | (int) $now['mday'];

        return [$time, $date];
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)) . $name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function safeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\/\?\*\[\]:]/u', ' ', trim($name)) ?? 'Sheet1';
        if ($name === '') {
            return 'Sheet1';
        }

        $characters = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($characters) && count($characters) > 31) {
            return implode('', array_slice($characters, 0, 31));
        }

        return $name;
    }

    private function xmlText(string $value): string
    {
        $value = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
