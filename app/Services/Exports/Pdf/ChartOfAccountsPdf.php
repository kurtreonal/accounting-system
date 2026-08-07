<?php

namespace App\Services\Exports\Pdf;

use DateTimeInterface;
use Fpdf\Fpdf;

class ChartOfAccountsPdf extends Fpdf
{
    /** @var array<int, float> */
    private array $columnWidths = [20, 72, 30, 52, 55, 30];

    /** @var array<int, string> */
    private array $columnHeaders = ['Code', 'Account Name', 'Type', 'Sub-Type', 'Balance', 'Status'];

    public function __construct(private readonly DateTimeInterface $generatedAt)
    {
        parent::__construct('L', 'mm', 'A4');

        $this->SetMargins(12, 12, 12);
        $this->SetAutoPageBreak(true, 15);
        $this->AliasNbPages();
        $this->SetTitle('Chart of Accounts');
        $this->SetAuthor('APM Customs Accounting System');
    }

    public function Header(): void
    {
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(150, 8, 'Chart of Accounts', 0, 0, 'L');

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(109, 8, 'Generated: '.$this->generatedAt->format('Y-m-d H:i:s'), 0, 1, 'R');
        $this->Ln(3);

        $this->tableHeader();
    }

    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, 'Page '.$this->PageNo().' of {nb}', 0, 0, 'C');
    }

    /** @param array<int, array<string, mixed>> $accounts */
    public function render(array $accounts): self
    {
        $this->AddPage();

        if ($accounts === []) {
            $this->SetFont('Helvetica', 'I', 10);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(array_sum($this->columnWidths), 12, 'No records found.', 1, 1, 'C');

            return $this;
        }

        foreach ($accounts as $index => $account) {
            $balance = (float) $account['balance'];
            $formattedBalance = ($balance < 0 ? '-PHP ' : 'PHP ').number_format(abs($balance), 2, '.', ',');

            $this->row([
                (string) $account['code'],
                (string) $account['name'],
                (string) $account['type'],
                (string) $account['sub_type'],
                $formattedBalance,
                (string) $account['status'],
            ], $balance < 0, $index % 2 === 1);
        }

        return $this;
    }

    private function tableHeader(): void
    {
        $this->SetFillColor(37, 99, 235);
        $this->SetDrawColor(203, 213, 225);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 8);

        foreach ($this->columnHeaders as $index => $header) {
            $align = $header === 'Balance' ? 'R' : 'L';
            $this->Cell($this->columnWidths[$index], 8, $header, 1, 0, $align, true);
        }

        $this->Ln();
    }

    /** @param array<int, string> $cells */
    private function row(array $cells, bool $negativeBalance, bool $alternate): void
    {
        $cells = array_map($this->pdfText(...), $cells);
        $this->SetFont('Helvetica', '', 8);

        $lineCount = 1;
        foreach ($cells as $index => $cell) {
            $lineCount = max($lineCount, $this->lineCount($this->columnWidths[$index], $cell));
        }

        $height = max(7, $lineCount * 4.5);

        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage();
        }

        $startX = $this->GetX();
        $startY = $this->GetY();

        foreach ($cells as $index => $cell) {
            $width = $this->columnWidths[$index];
            $align = $index === 4 ? 'R' : 'L';

            $this->SetFillColor($alternate ? 248 : 255, $alternate ? 250 : 255, $alternate ? 252 : 255);
            $this->SetDrawColor(226, 232, 240);
            $this->Rect($this->GetX(), $startY, $width, $height, 'DF');

            if ($negativeBalance && $index === 4) {
                $this->SetTextColor(220, 38, 38);
            } else {
                $this->SetTextColor(51, 65, 85);
            }

            $cellX = $this->GetX();
            $this->MultiCell($width, 4.5, $cell, 0, $align);
            $this->SetXY($cellX + $width, $startY);
        }

        $this->SetXY($startX, $startY + $height);
    }

    private function lineCount(float $width, string $text): int
    {
        $usableWidth = max(1, $width - (2 * $this->cMargin));
        $explicitLines = explode("\n", $text);
        $count = 0;

        foreach ($explicitLines as $line) {
            $count += max(1, (int) ceil($this->GetStringWidth($line) / $usableWidth));
        }

        return $count;
    }

    private function pdfText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);

        return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: '';
    }
}
