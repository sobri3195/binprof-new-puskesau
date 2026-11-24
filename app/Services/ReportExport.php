<?php
declare(strict_types=1);
namespace Binprof\Services;

use ZipArchive;
use function Binprof\{config,fail};

final class ReportExport
{
    public static function formats(): array
    {
        $formats = ['csv'=>'CSV'];
        if (class_exists(ZipArchive::class)) {
            $formats['xlsx'] = 'XLSX';
        }
        $formats['pdf'] = 'PDF';
        return $formats;
    }

    public static function format(mixed $requested): string
    {
        if (!is_string($requested) || !array_key_exists($requested,self::formats())) {
            fail(422,'Format ekspor tidak tersedia pada server.');
        }
        return $requested;
    }

    public static function limit(string $format): int
    {
        return $format === 'pdf' ? 250 : 5000;
    }

    public static function send(string $format, string $name, array $headers, array $rows): never
    {
        if ($format === 'pdf') {
            $content = self::pdf($name,$headers,$rows);
            self::headers($name,'pdf','application/pdf');
            echo $content;
        } elseif ($format === 'xlsx') {
            $storage = realpath(config()['storage']);
            $path = $storage !== false ? tempnam($storage,'report-') : false;
            if ($path === false) {
                throw new \RuntimeException('Cannot create private XLSX');
            }
            try {
                if (dirname($path) !== $storage || !chmod($path,0600)) {
                    throw new \RuntimeException('Invalid XLSX location');
                }
                self::xlsx($path,[$headers,...$rows]);
                $stream = fopen($path,'rb');
                if ($stream === false) {
                    throw new \RuntimeException('Cannot read workbook');
                }
            } finally {
                unlink($path);
            }
            self::headers($name,'xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            fpassthru($stream);
            fclose($stream);
        } else {
            self::headers($name,'csv','text/csv; charset=utf-8');
            $stream = fopen('php://output','wb');
            fputcsv($stream,$headers,',','"','');
            foreach ($rows as $row) {
                fputcsv($stream,array_map(static function ($value): string {
                    $value = (string) $value;
                    return preg_match('/^(?:[\s\p{Cf}]*[=+@-]|[\t\r\n])/u',$value) ? "'" . $value : $value;
                },$row),',','"','');
            }
            fclose($stream);
        }
        exit;
    }

    private static function headers(string $name, string $extension, string $mime): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $name . '.' . $extension . '"');
    }

    private static function xlsx(string $path, array $records): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path,ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create workbook');
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($records as $i=>$row) {
            $sheet .= '<row r="' . ($i + 1) . '">';
            foreach ($row as $value) {
                // Explicit string cells preserve zero prefixes and are never spreadsheet formulas.
                $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',(string) $value);
                $sheet .= '<c t="inlineStr"><is><t xml:space="preserve">' .
                    htmlspecialchars($text ?? '',ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8') . '</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $parts = [
            '[Content_Types].xml'=>'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="BINPROFKES" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml'=>$sheet . '</sheetData></worksheet>',
        ];
        foreach ($parts as $part=>$content) {
            if (!$zip->addFromString($part,$content)) {
                throw new \RuntimeException('Cannot write workbook part');
            }
        }
        if (!$zip->close()) {
            throw new \RuntimeException('Cannot finish workbook');
        }
    }

    private static function pdf(string $name, array $headers, array $rows): string
    {
        $root = dirname(__DIR__,2) . '/vendor-exports/tfpdf';
        require_once $root . '/tfpdf.php';
        require_once $root . '/font/unifont/ttfonts.php';
        // Use the packaged font without generating executable font caches in the release tree.
        define('FPDF_FONTPATH',$root . '/');
        define('_SYSTEM_TTFONTS',$root . '/font/unifont/');
        $pdf = new \tFPDF();
        $pdf->SetTitle('BINPROFKES ' . str_replace('-',' ',$name));
        $pdf->AddFont('DejaVu','','DejaVuSans.ttf',true);
        $pdf->AddPage();
        $pdf->SetFont('DejaVu','',14);
        $pdf->MultiCell(0,8,'BINPROFKES · ' . str_replace('-',' ',$name));
        $pdf->SetFont('DejaVu','',10);
        if (!$rows) {
            $pdf->MultiCell(0,5,'Tidak ada hasil sesuai filter.');
        }
        foreach ($rows as $index=>$row) {
            $pdf->Ln(3);
            $pdf->MultiCell(0,5,'#' . ($index + 1));
            foreach (array_values($row) as $column=>$value) {
                $pdf->MultiCell(0,5,$headers[$column] . ': ' . ($value ?? '—'));
            }
        }
        return $pdf->Output('S');
    }
}
