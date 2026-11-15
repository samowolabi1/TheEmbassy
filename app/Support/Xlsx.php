<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * A small Excel (.xlsx) writer: several sheets, a bold header row that stays in view, filters,
 * column widths that fit, and numbers, money and percentages Excel can add up. Text is stored as
 * text, so nothing in a cell is ever run as a formula. Needs PHP's zip extension, which shared
 * hosts include.
 *
 * Each sheet is ['name' => ..., 'columns' => [[label, type], ...], 'rows' => [[...], ...]] where
 * type is text, int, money (naira), percent (0.42 shows as 42%) or date (a Y-m-d string). A
 * sheet can instead be ['name' => ..., 'pairs' => [[label, value, type?], ...]] for a summary.
 */
class Xlsx
{
    private const STYLES = ['text' => 0, 'header' => 1, 'int' => 2, 'money' => 3, 'percent' => 4, 'date' => 5, 'title' => 6, 'label' => 7];

    public static function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Write the workbook to a temporary file and return its path.
     *
     * @param  list<array<string, mixed>>  $sheets
     */
    public static function write(array $sheets, string $title = ''): string
    {
        if (! self::available()) {
            throw new RuntimeException('Excel files need PHP\'s zip extension.');
        }
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $names = [];
        foreach ($sheets as $i => $sheet) {
            $names[] = self::sheetName($sheet['name'] ?? 'Sheet '.($i + 1), $names);
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .implode('', array_map(fn ($i) => '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', array_keys($sheets)))
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'</Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.self::esc($title).'</dc:title><dc:creator>'.self::esc((string) config('church.short_name')).'</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            .implode('', array_map(fn ($i) => '<sheet name="'.self::esc($names[$i]).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>', array_keys($sheets)))
            .'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .implode('', array_map(fn ($i) => '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>', array_keys($sheets)))
            .'<Relationship Id="rId'.(count($sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', self::styles());
        foreach ($sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', isset($sheet['pairs']) ? self::pairsSheet($sheet) : self::tableSheet($sheet));
        }
        $zip->close();

        return $path;
    }

    /**
     * A table: header row, data rows, filters and a frozen header.
     *
     * @param  array<string, mixed>  $sheet
     */
    private static function tableSheet(array $sheet): string
    {
        $columns = $sheet['columns'];
        $types = array_map(fn ($c) => $c[1] ?? 'text', $columns);
        $widths = array_map(fn ($c) => mb_strlen($c[0]) + 4, $columns);
        $xml = '<row r="1">';
        foreach ($columns as $c => $col) {
            $xml .= self::cell(1, $c, $col[0], 'header');
        }
        $xml .= '</row>';
        $r = 1;
        foreach ($sheet['rows'] as $row) {
            $r++;
            $xml .= '<row r="'.$r.'">';
            foreach (array_values($row) as $c => $value) {
                $xml .= self::cell($r, $c, $value, $types[$c] ?? 'text');
                $widths[$c] = max($widths[$c] ?? 8, min(60, self::displayLength($value, $types[$c] ?? 'text') + 2));
            }
            $xml .= '</row>';
        }
        $last = self::col(count($columns) - 1);

        return self::sheetXml($xml, $widths, 'A1:'.$last.max(1, $r), true);
    }

    /**
     * A summary: a title, then label / value pairs (an empty label starts a new heading).
     *
     * @param  array<string, mixed>  $sheet
     */
    private static function pairsSheet(array $sheet): string
    {
        $xml = '';
        $r = 0;
        $widths = [34, 18];
        foreach ($sheet['title'] ?? [] as $line) {
            $r++;
            $xml .= '<row r="'.$r.'">'.self::cell($r, 0, $line, $r === 1 ? 'title' : 'text').'</row>';
        }
        foreach ($sheet['pairs'] as $pair) {
            $r++;
            [$label, $value, $type] = [$pair[0], $pair[1] ?? null, $pair[2] ?? (is_int($pair[1] ?? null) ? 'int' : 'text')];
            if ($value === null && $label !== '') {
                $xml .= '<row r="'.$r.'">'.self::cell($r, 0, $label, 'label').'</row>';
            } else {
                $xml .= '<row r="'.$r.'">'.self::cell($r, 0, $label, 'text').self::cell($r, 1, $value, $type).'</row>';
            }
            $widths[0] = max($widths[0], min(60, mb_strlen((string) $label) + 2));
            $widths[1] = max($widths[1], min(40, self::displayLength($value, $type) + 2));
        }

        return self::sheetXml($xml, $widths, null, false);
    }

    /**
     * @param  list<int>  $widths
     */
    private static function sheetXml(string $rows, array $widths, ?string $filter, bool $freeze): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0">'.($freeze ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' : '').'</sheetView></sheetViews>'
            .'<cols>'.implode('', array_map(fn ($w, $i) => '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>', $widths, array_keys($widths))).'</cols>'
            .'<sheetData>'.$rows.'</sheetData>'
            .($filter ? '<autoFilter ref="'.$filter.'"/>' : '')
            .'</worksheet>';
    }

    private static function cell(int $row, int $col, mixed $value, string $type): string
    {
        $ref = self::col($col).$row;
        $s = self::STYLES[$type] ?? 0;
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', array_filter($value, 'is_scalar')));
        }
        if ($value === null || $value === '') {
            return '<c r="'.$ref.'" s="'.$s.'"/>';
        }
        if (in_array($type, ['int', 'money', 'percent'], true) && is_numeric($value)) {
            return '<c r="'.$ref.'" s="'.$s.'"><v>'.($type === 'int' ? (int) $value : round((float) $value, 4)).'</v></c>';
        }
        if ($type === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $value)) {
            // Excel counts days from 30 December 1899.
            $days = (int) floor((strtotime(substr((string) $value, 0, 10).' UTC') - strtotime('1899-12-30 UTC')) / 86400);

            return '<c r="'.$ref.'" s="'.$s.'"><v>'.$days.'</v></c>';
        }
        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }

        return '<c r="'.$ref.'" s="'.$s.'" t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $value).'</t></is></c>';
    }

    private static function displayLength(mixed $value, string $type): int
    {
        return match ($type) {
            'money' => mb_strlen(number_format((float) $value, 2)) + 2,
            'date' => 11,
            default => max(array_map('mb_strlen', explode("\n", is_array($value) ? implode(', ', array_filter($value, 'is_scalar')) : (string) $value)) ?: [0]),
        };
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    /**
     * @param  list<string>  $taken
     */
    private static function sheetName(string $name, array $taken): string
    {
        $name = mb_substr(trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name)), 0, 31) ?: 'Sheet';
        $base = $name;
        for ($n = 2; in_array($name, $taken, true); $n++) {
            $name = mb_substr($base, 0, 28).' '.$n;
        }

        return $name;
    }

    private static function esc(string $s): string
    {
        // Strip characters XML can't hold, then escape.
        return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;₦&quot;#,##0.00"/><numFmt numFmtId="165" formatCode="d mmm yyyy"/></numFmts>'
            .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF0A1D3F"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0A1D3F"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="0"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="9" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="top"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
