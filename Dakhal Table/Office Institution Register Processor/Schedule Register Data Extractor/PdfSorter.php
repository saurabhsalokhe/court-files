<?php

require 'vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Input/output PDF
$inputPdfPath = 'input-folder/document.pdf';
$outputPdfPath = 'output-folder/filtered-document.pdf';

// Strings to match
$mainString = 'Suit Register Report';
//$subString = 'IN THE COURT OF:3RD JOINT CIVIL JUDGE J.D. AND JMFC PUNE';
$subString = 'IN THE COURT OF:4TH JOINT CIVIL JUDGE J.D. AND JMFC PUNE';

// Read full PDF
$pdf = new Fpdi();
$pageCount = $pdf->setSourceFile($inputPdfPath);

// Array to store pages to be deleted
$pagesToDelete = [];

$markStart = null;
$markEnd = null;

// Go through all pages
for ($i = 1; $i <= $pageCount; $i++) {
   $tempPdf = new Fpdi();
   $tempPdf->setSourceFile($inputPdfPath);
   $templateId = $tempPdf->importPage($i);
   $size = $tempPdf->getTemplateSize($templateId);

   // Create temporary PDF with one page
   $tempPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
   $tempPdf->useTemplate($templateId);

   // Extract text from this single page using shell (if you have `pdftotext`)
   $tmpFile = "temp_page_$i.pdf";
   $tempPdf->Output($tmpFile, 'F');

   // You must have `pdftotext` installed in system
   $text = shell_exec("pdftotext \"$tmpFile\" -");
   unlink($tmpFile);

   if (strpos($text, $mainString) !== false) {
      if (strpos($text, $subString) === false) {
         // Found main string but NOT the correct court string
         $markStart = $i;
         // Keep checking further pages
         for ($j = $i + 1; $j <= $pageCount; $j++) {
            $tmpPdf = new Fpdi();
            $tmpPdf->setSourceFile($inputPdfPath);
            $templateIdJ = $tmpPdf->importPage($j);
            $sizeJ = $tmpPdf->getTemplateSize($templateIdJ);
            $tmpPdf->AddPage($sizeJ['orientation'], [$sizeJ['width'], $sizeJ['height']]);
            $tmpPdf->useTemplate($templateIdJ);

            $tmpFileJ = "temp_page_$j.pdf";
            $tmpPdf->Output($tmpFileJ, 'F');
            $textJ = shell_exec("pdftotext \"$tmpFileJ\" -");
            unlink($tmpFileJ);

            if (strpos($textJ, $subString) !== false) {
               $markEnd = $j - 1;
               break;
            }
         }

         // If we found start, mark those pages for deletion
         if ($markStart !== null) {
            // If markEnd found, delete from markStart to markEnd
            if ($markEnd !== null) {
               for ($k = $markStart; $k <= $markEnd; $k++) {
                  $pagesToDelete[] = $k;
               }
               $i = $markEnd;
            } else {
               // Edge case: no court string found till end => delete till end
               for ($k = $markStart; $k <= $pageCount; $k++) {
                  $pagesToDelete[] = $k;
               }
               break; // no need to continue, end reached
            }

            // Reset markers
            $markStart = null;
            $markEnd = null;
         }
      }
   }
}

// Create final PDF without unwanted pages
$finalPdf = new Fpdi();
$finalPdf->setSourceFile($inputPdfPath);

for ($i = 1; $i <= $pageCount; $i++) {
   if (in_array($i, $pagesToDelete)) continue;

   $templateId = $finalPdf->importPage($i);
   $size = $finalPdf->getTemplateSize($templateId);
   $finalPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
   $finalPdf->useTemplate($templateId);
}

$finalPdf->Output($outputPdfPath, 'F');

echo "Filtered PDF created: $outputPdfPath\n";
