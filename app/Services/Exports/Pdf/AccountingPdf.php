<?php

namespace App\Services\Exports\Pdf;

use DateTimeInterface;
use Fpdf\Fpdf;

abstract class AccountingPdf extends Fpdf
{
    public function __construct(
        string $orientation,
        private readonly string $documentTitle,
        DateTimeInterface $generatedAt,
    ) {
        parent::__construct($orientation, 'mm', 'A4');

        $this->SetMargins(12, 12, 12);
        $this->SetAutoPageBreak(true, 15);
        $this->AliasNbPages();
        $this->SetTitle($documentTitle);
    }

    public function Header(): void
    {
        $this->SetTextColor(17, 24, 39);
        $this->SetFont('Helvetica', 'B', 14);
        $this->Cell(0, 8, $this->pdfText($this->documentTitle), 0, 1, 'L');
        $this->Ln(4);

        $this->afterDocumentHeader();
    }

    public function Footer(): void {}

    protected function afterDocumentHeader(): void {}

    /** @param array<int, string> $headers
     * @param  array<int, float|int>  $widths
     * @param  array<int, string>  $alignments
     */
    protected function tableHeader(array $headers, array $widths, array $alignments = []): void
    {
        $this->SetDrawColor(17, 24, 39);
        $this->SetTextColor(17, 24, 39);
        $this->SetFont('Helvetica', 'B', 8);

        foreach ($headers as $index => $header) {
            $this->Cell($widths[$index], 8, $this->pdfText($header), 'TB', 0, $alignments[$index] ?? 'L');
        }

        $this->Ln();
    }

    /** @param array<int, string> $cells
     * @param  array<int, float|int>  $widths
     * @param  array<int, string>  $alignments
     * @param  array<int, int>  $negativeColumns
     */
    protected function tableRow(array $cells, array $widths, array $alignments = [], array $negativeColumns = [], bool $alternate = false): void
    {
        $cells = array_map($this->pdfText(...), $cells);
        $this->SetFont('Helvetica', '', 8);
        $lineCount = 1;

        foreach ($cells as $index => $cell) {
            $lineCount = max($lineCount, $this->lineCount((float) $widths[$index], $cell));
        }

        $height = max(7, $lineCount * 4.5);
        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage();
        }

        $startX = $this->GetX();
        $startY = $this->GetY();

        foreach ($cells as $index => $cell) {
            $width = (float) $widths[$index];
            $this->SetTextColor(17, 24, 39);
            $cellX = $this->GetX();
            $this->MultiCell($width, 4.5, $cell, 0, $alignments[$index] ?? 'L');
            $this->SetXY($cellX + $width, $startY);
        }

        $this->SetDrawColor(209, 213, 219);
        $this->Line($startX, $startY + $height, $startX + array_sum($widths), $startY + $height);
        $this->SetXY($startX, $startY + $height);
    }

    protected function money(float|int $amount, bool $blankZero = false): string
    {
        if ($blankZero && abs((float) $amount) < 0.005) {
            return '-';
        }

        return ((float) $amount < 0 ? '-PHP ' : 'PHP ').number_format(abs((float) $amount), 2, '.', ',');
    }

    protected function pdfText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', trim($value)) ?: '';
    }

    private function lineCount(float $width, string $text): int
    {
        $usableWidth = max(1, $width - (2 * $this->cMargin));
        $count = 0;

        foreach (explode("\n", $text) as $line) {
            $count += max(1, (int) ceil($this->GetStringWidth($line) / $usableWidth));
        }

        return $count;
    }
}
