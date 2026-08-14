<?php

require 'vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ====== CONFIG ======
$inputPdf = 'input-folder/document.pdf';
$outputExcel = 'output-folder/case_data.xlsx';

// ====== MAKE OUTPUT FOLDER IF NEEDED ======
if (!is_dir(dirname($outputExcel))) {
    mkdir(dirname($outputExcel), 0777, true);
}

// ====== FUNCTION: CLEAN BLOCK ======
function cleanBlock($text)
{
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    $cleaned = '';
    $buffer = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // Clean unwanted terms
        $line = preg_replace('/Relative of/i', '', $line);
        $line = preg_replace('/Age:\d+/i', '', $line);
        $line = preg_replace('/Address[\s:-]*/i', "\nAddress:", $line);

        // If starts with number like 1), 2), etc.
        if (preg_match('/^\d+\)/', $line)) {
            if ($buffer !== '') {
                $cleaned .= trim($buffer) . ' ';
                $buffer = '';
            }
            $buffer = $line;
        } else {
            $buffer .= ' ' . $line;
        }
    }

    if ($buffer !== '') {
        $cleaned .= trim($buffer);
    }

    return trim(preg_replace('/\s+/', ' ', $cleaned));
}

// ====== INIT EXCEL ======
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray(['Case Number', 'Complainant/Prosecution', 'Accused'], NULL, 'A1');

$row = 2;

// ====== READ PDF ======
$pdf = new Fpdi();
$pageCount = $pdf->setSourceFile($inputPdf);

for ($i = 1; $i <= $pageCount; $i++) {
    $tmpPdf = new Fpdi();
    $tmpPdf->setSourceFile($inputPdf);
    $tpl = $tmpPdf->importPage($i);
    $size = $tmpPdf->getTemplateSize($tpl);
    $tmpPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $tmpPdf->useTemplate($tpl);

    $tempFile = "temp_page_$i.pdf";
    $tmpPdf->Output($tempFile, 'F');

    $text = shell_exec("pdftotext \"$tempFile\" -");
    unlink($tempFile);

    if (
        strpos($text, 'Suit Register Report') !== false &&
        strpos($text, 'IN THE COURT OF:4TH JOINT CIVIL JUDGE J.D. AND JMFC PUNE') !== false
    ) {
        // ===== Extract Case Number =====
        preg_match('/Case No\.\s*:(.*)/', $text, $caseMatch);
        $caseNumber = isset($caseMatch[1]) ? trim($caseMatch[1]) : '';

        // ===== Extract Complainant =====
        preg_match('/Complianant\/Prosecution(.*?)Accused/s', $text, $complainantMatch);
        $complainantRaw = $complainantMatch[1] ?? '';
        $complainant = cleanBlock($complainantRaw);

        // ===== Extract Accused =====
        preg_match('/Accused(.*?)Date of Hearing/s', $text, $accusedMatch);
        $accusedRaw = $accusedMatch[1] ?? '';
        $accused = cleanBlock($accusedRaw);

        // ===== Save to Excel =====
        $sheet->setCellValue("A$row", $caseNumber);
        $sheet->setCellValue("B$row", $complainant);
        $sheet->setCellValue("C$row", $accused);
        $row++;
    }
}

// ===== SAVE EXCEL =====
$writer = new Xlsx($spreadsheet);
$writer->save($outputExcel);

echo "✅ Excel saved to: $outputExcel\n";
