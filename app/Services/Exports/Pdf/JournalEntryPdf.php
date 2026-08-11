<?php

namespace App\Services\Exports\Pdf;

use DateTimeInterface;
use Illuminate\Support\Arr;

class JournalEntryPdf extends AccountingPdf
{
    /** @var array<int, float|int> */
    private array $widths = [48, 52, 30, 30, 30];

    private bool $tableStarted = false;

    /** @param array<string, mixed> $entry */
    public function __construct(DateTimeInterface $generatedAt, private readonly array $entry)
    {
        parent::__construct('P', 'Journal Entry '.$entry['journal_number'], $generatedAt);
    }

    protected function afterDocumentHeader(): void
    {
        if ($this->tableStarted) {
            $this->lineHeader();
        }
    }

    public function render(): self
    {
        $this->AddPage();
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(51, 65, 85);
        $this->Cell(35, 6, 'Journal Number', 0, 0);
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(60, 6, $this->pdfText((string) $this->entry['journal_number']), 0, 0);
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(20, 6, 'Date', 0, 0);
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(0, 6, $this->pdfText((string) $this->entry['date']), 0, 1);
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(35, 6, 'Reference', 0, 0);
        $this->Cell(60, 6, $this->pdfText((string) ($this->entry['reference'] ?: '-')), 0, 0);
        $this->Cell(20, 6, 'Status', 0, 0);
        $this->Cell(0, 6, $this->pdfText((string) $this->entry['status']), 0, 1);
        $this->Cell(35, 6, 'Description', 0, 0);
        $this->MultiCell(0, 6, $this->pdfText((string) $this->entry['description']));
        $this->Ln(4);

        $this->tableStarted = true;
        $this->lineHeader();
        foreach ($this->entry['lines'] as $index => $line) {
            $this->tableRow([
                $line['account_code'].' - '.$line['account_name'],
                (string) ($line['description'] ?: '-'),
                (string) ($line['party_reference'] ?: '-'),
                $this->money((float) $line['debit'], true),
                $this->money((float) $line['credit'], true),
            ], $this->widths, ['', '', '', 'R', 'R'], [], $index % 2 === 1);
        }

        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(array_sum(array_slice($this->widths, 0, 3)), 8, 'Totals', 1, 0, 'R');
        $this->Cell($this->widths[3], 8, $this->money((float) $this->entry['total_debit']), 1, 0, 'R');
        $this->Cell($this->widths[4], 8, $this->money((float) $this->entry['total_credit']), 1, 1, 'R');
        $this->Ln(10);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(63, 5, 'Prepared by: '.Arr::get($this->entry, 'created_by.name', '-'));
        $this->Cell(63, 5, 'Reviewed by: '.Arr::get($this->entry, 'reviewed_by.name', '-'));
        $this->Cell(63, 5, 'Posted by: '.Arr::get($this->entry, 'posted_by.name', '-'));

        return $this;
    }

    private function lineHeader(): void
    {
        $this->tableHeader(['Account', 'Description', 'Reference', 'Debit', 'Credit'], $this->widths, ['', '', '', 'R', 'R']);
    }
}
