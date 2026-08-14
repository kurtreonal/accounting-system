<?php

namespace App\Services\Exports\Pdf;

use DateTimeInterface;

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
        $this->SetDrawColor(17, 24, 39);
        $this->Cell(array_sum(array_slice($this->widths, 0, 3)), 8, 'Totals', 'TB', 0, 'R');
        $this->Cell($this->widths[3], 8, $this->money((float) $this->entry['total_debit']), 'TB', 0, 'R');
        $this->Cell($this->widths[4], 8, $this->money((float) $this->entry['total_credit']), 'TB', 1, 'R');

        return $this;
    }

    private function lineHeader(): void
    {
        $this->tableHeader(['Account', 'Description', 'Reference', 'Debit', 'Credit'], $this->widths, ['', '', '', 'R', 'R']);
    }
}
