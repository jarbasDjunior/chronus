<?php

namespace App\Services;

class XlsxService
{
    public function build(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'chronus_').'.zip';
        $zip = new \PharData($path);
        $zip->addFromString('[Content_Types].xml', $this->types(count($sheets)));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook(array_keys($sheets)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->rels(count($sheets)));
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellXfs></styleSheet>');
        $i = 1;
        foreach ($sheets as $rows) {
            $zip->addFromString("xl/worksheets/sheet$i.xml", $this->sheet($rows));
            $i++;
        }unset($zip);

        return $path;
    }

    private function e($v)
    {
        return htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function col($n)
    {
        $s = '';
        while ($n) {
            $n--;
            $s = chr(65 + $n % 26).$s;
            $n = intdiv($n, 26);
        }

return $s;
    }

    private function sheet($rows)
    {
        $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
        $r = 1;
        foreach ($rows as $row) {
            $xml .= "<row r=\"$r\">";
            $c = 1;
            foreach ($row as $v) {
                $ref = $this->col($c++).$r;
                $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.$this->e($v).'</t></is></c>';
            }$xml .= '</row>';
            $r++;
        }

return $xml.'</sheetData><autoFilter ref="A1:Z'.max(1, $r - 1).'"/></worksheet>';
    }

    private function workbook($names)
    {
        $x = '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $i = 1;
        foreach ($names as $n) {
            $x .= '<sheet name="'.$this->e(substr($n, 0, 31)).'" sheetId="'.$i.'" r:id="rId'.$i++.'"/>';
        }

return $x.'</sheets></workbook>';
    }

    private function rels($n)
    {
        $x = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $n; $i++) {
            $x .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }$x .= '<Relationship Id="rId'.($n + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';

        return $x;
    }

    private function types($n)
    {
        $x = '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $n; $i++) {
            $x .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

return $x.'</Types>';
    }
}
