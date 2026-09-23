<?php

declare(strict_types=1);

namespace DKAnnual\Export;

final class XlsxWriter
{
    /** @var array<string, int> */
    private const STYLE_IDS = [
        'normal' => 0,
        'header' => 1,
        'title' => 2,
        'section' => 3,
        'meta_label' => 4,
        'meta_value' => 5,
        'number' => 6,
        'integer' => 7,
        'date' => 8,
        'datetime' => 9,
        'status_approved' => 10,
        'status_pending' => 11,
        'status_rejected' => 12,
        'status_cancelled' => 13,
        'kpi_label' => 14,
        'kpi_value' => 15,
        'total' => 16,
        'note' => 17,
        'data' => 18,
        'data_wrap' => 19,
    ];

    /**
     * Backward-compatible single-sheet writer.
     *
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
        $rowStyles = [];
        foreach ($headerRows as $row) {
            $rowStyles[$row] = 'header';
        }
        foreach ($titleRows as $row) {
            $rowStyles[$row] = 'title';
        }

        return $this->buildWorkbook([[
            'name' => $sheetName,
            'rows' => $rows,
            'row_styles' => $rowStyles,
            'column_widths' => $columnWidths,
            'freeze_rows' => $freezeRows,
        ]]);
    }

    /**
     * @param list<array{
     *   name:string,
     *   rows:list<list<mixed>>,
     *   row_styles?:array<int,string>,
     *   cell_styles?:array<string,string>,
     *   column_widths?:list<float|int>,
     *   freeze_rows?:int,
     *   freeze_columns?:int,
     *   merge_cells?:list<string>,
     *   auto_filter?:?string,
     *   row_heights?:array<int,float|int>
     * }> $sheets
     */
    public function buildWorkbook(array $sheets): string
    {
        if ($sheets === []) {
            $sheets = [['name' => 'Sheet1', 'rows' => [[]]]];
        }

        $safeNames = $this->uniqueSheetNames(array_map(
            static fn (array $sheet): string => (string) ($sheet['name'] ?? 'Sheet'),
            $sheets,
        ));

        $files = [
            '[Content_Types].xml' => $this->contentTypesXml(count($sheets)),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'xl/workbook.xml' => $this->workbookXml($safeNames),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(count($sheets)),
            'xl/styles.xml' => $this->stylesXml(),
        ];

        foreach ($sheets as $index => $sheet) {
            $files['xl/worksheets/sheet' . ($index + 1) . '.xml'] = $this->sheetXml($sheet);
        }

        return $this->zip($files);
    }

    /**
     * @param array{
     *   rows?:list<list<mixed>>,
     *   row_styles?:array<int,string>,
     *   cell_styles?:array<string,string>,
     *   column_widths?:list<float|int>,
     *   freeze_rows?:int,
     *   freeze_columns?:int,
     *   merge_cells?:list<string>,
     *   auto_filter?:?string,
     *   row_heights?:array<int,float|int>
     * } $sheet
     */
    private function sheetXml(array $sheet): string
    {
        $rows = $sheet['rows'] ?? [[]];
        $rowStyles = $sheet['row_styles'] ?? [];
        $cellStyles = $sheet['cell_styles'] ?? [];
        $columnWidths = $sheet['column_widths'] ?? [];
        $freezeRows = max(0, (int) ($sheet['freeze_rows'] ?? 0));
        $freezeColumns = max(0, (int) ($sheet['freeze_columns'] ?? 0));
        $mergeCells = $sheet['merge_cells'] ?? [];
        $autoFilter = isset($sheet['auto_filter']) ? trim((string) $sheet['auto_filter']) : '';
        $rowHeights = $sheet['row_heights'] ?? [];

        $maxColumns = 1;
        foreach ($rows as $row) {
            $maxColumns = max($maxColumns, count($row));
        }
        $maxRows = max(1, count($rows));

        $columns = '';
        foreach ($columnWidths as $index => $width) {
            $column = $index + 1;
            $safeWidth = max(5.0, min(70.0, (float) $width));
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
            $rowStyle = (string) ($rowStyles[$rowIndex] ?? 'normal');
            $height = isset($rowHeights[$rowIndex])
                ? max(10.0, min(120.0, (float) $rowHeights[$rowIndex]))
                : null;
            $rowAttributes = ' r="' . $excelRow . '"';
            if ($height !== null) {
                $rowAttributes .= ' ht="' . rtrim(rtrim(number_format($height, 2, '.', ''), '0'), '.')
                    . '" customHeight="1"';
            }

            $cellXml = '';
            foreach ($cells as $columnIndex => $rawValue) {
                $reference = $this->columnName($columnIndex + 1) . $excelRow;
                $styleName = (string) ($cellStyles[$reference] ?? $rowStyle);
                $value = $rawValue;
                if (is_array($rawValue) && array_key_exists('value', $rawValue)) {
                    $value = $rawValue['value'];
                    if (isset($rawValue['style'])) {
                        $styleName = (string) $rawValue['style'];
                    }
                }

                $styleId = self::STYLE_IDS[$styleName] ?? self::STYLE_IDS['normal'];
                $styleAttribute = $styleId > 0 ? ' s="' . $styleId . '"' : '';

                if ($value === null || $value === '') {
                    $cellXml .= '<c r="' . $reference . '"' . $styleAttribute . '/>';
                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    $number = is_float($value)
                        ? rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.')
                        : (string) $value;
                    $cellXml .= '<c r="' . $reference . '"' . $styleAttribute . '><v>' . $number . '</v></c>';
                    continue;
                }

                $text = $this->xmlText((string) $value);
                $cellXml .= '<c r="' . $reference . '" t="inlineStr"' . $styleAttribute . '>'
                    . '<is><t xml:space="preserve">' . $text . '</t></is></c>';
            }

            $sheetRows .= '<row' . $rowAttributes . '>' . $cellXml . '</row>';
        }

        $sheetView = '<sheetView workbookViewId="0">';
        if ($freezeRows > 0 || $freezeColumns > 0) {
            $topLeft = $this->columnName($freezeColumns + 1) . ($freezeRows + 1);
            $pane = $freezeRows > 0 && $freezeColumns > 0
                ? 'bottomRight'
                : ($freezeColumns > 0 ? 'topRight' : 'bottomLeft');
            $sheetView .= '<pane'
                . ($freezeColumns > 0 ? ' xSplit="' . $freezeColumns . '"' : '')
                . ($freezeRows > 0 ? ' ySplit="' . $freezeRows . '"' : '')
                . ' topLeftCell="' . $topLeft . '" activePane="' . $pane . '" state="frozen"/>';
        }
        $sheetView .= '</sheetView>';

        $mergeXml = '';
        if ($mergeCells !== []) {
            $refs = '';
            foreach ($mergeCells as $reference) {
                $reference = trim((string) $reference);
                if ($reference !== '') {
                    $refs .= '<mergeCell ref="' . $this->xmlText($reference) . '"/>';
                }
            }
            if ($refs !== '') {
                $mergeXml = '<mergeCells count="' . count($mergeCells) . '">' . $refs . '</mergeCells>';
            }
        }

        $filterXml = $autoFilter !== ''
            ? '<autoFilter ref="' . $this->xmlText($autoFilter) . '"/>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $this->columnName($maxColumns) . $maxRows . '"/>'
            . '<sheetViews>' . $sheetView . '</sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . ($columns !== '' ? '<cols>' . $columns . '</cols>' : '')
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . $filterXml
            . $mergeXml
            . '<pageMargins left="0.35" right="0.35" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '</worksheet>';
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $sheetOverrides = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $sheetOverrides .= '<Override PartName="/xl/worksheets/sheet' . $index
                . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $sheetOverrides
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

    /** @param list<string> $sheetNames */
    private function workbookXml(array $sheetNames): string
    {
        $sheets = '';
        foreach ($sheetNames as $index => $sheetName) {
            $sheetId = $index + 1;
            $sheets .= '<sheet name="' . $this->xmlText($sheetName) . '" sheetId="' . $sheetId
                . '" r:id="rId' . $sheetId . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView/></bookViews>'
            . '<sheets>' . $sheets . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelationshipsXml(int $sheetCount): string
    {
        $relationships = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId' . $index
                . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . $index . '.xml"/>';
        }

        $relationships .= '<Relationship Id="rId' . ($sheetCount + 1)
            . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2">'
            . '<numFmt numFmtId="164" formatCode="yyyy-mm-dd"/>'
            . '<numFmt numFmtId="165" formatCode="yyyy-mm-dd\ hh:mm"/>'
            . '</numFmts>'
            . '<fonts count="9">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FF18794E"/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FF9A6700"/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FFB42318"/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FF667085"/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FF173A7A"/><sz val="14"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="10">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF315EFB"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF152640"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEEF3FF"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAF8EF"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF7DD"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFDECEC"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F3F5"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF7F9FC"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFDDE3EC"/></left><right style="thin"><color rgb="FFDDE3EC"/></right>'
            . '<top style="thin"><color rgb="FFDDE3EC"/></top><bottom style="thin"><color rgb="FFDDE3EC"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="20">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="6" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="7" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="2" fontId="8" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="2" fontId="3" fillId="9" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="9" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
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

    /** @param list<string> $names @return list<string> */
    private function uniqueSheetNames(array $names): array
    {
        $used = [];
        $result = [];

        foreach ($names as $name) {
            $base = $this->safeSheetName($name);
            $candidate = $base;
            $suffix = 2;

            while (isset($used[$candidate])) {
                $tail = ' (' . $suffix . ')';
                $candidate = $this->truncateSheetName($base, 31 - strlen($tail)) . $tail;
                $suffix++;
            }

            $used[$candidate] = true;
            $result[] = $candidate;
        }

        return $result;
    }

    private function safeSheetName(string $name): string
    {
        $name = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', trim($name));
        if ($name === '') {
            return 'Sheet';
        }

        return $this->truncateSheetName($name, 31);
    }

    private function truncateSheetName(string $name, int $maxCharacters): string
    {
        $characters = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($characters) && count($characters) > $maxCharacters) {
            return implode('', array_slice($characters, 0, $maxCharacters));
        }

        return $name;
    }

    private function xmlText(string $value): string
    {
        $value = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
