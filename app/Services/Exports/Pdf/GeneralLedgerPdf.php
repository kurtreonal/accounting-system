<?php

namespace App\Services\Exports\Pdf;

use DateTimeInterface;

class GeneralLedgerPdf extends AccountingPdf
{
    /** @var array<int, float|int> */
    private array $widths = [18, 25, 22, 50, 24, 24, 27];

    /** @param array<string, mixed> $report */
    public function __construct(DateTimeInterface $generatedAt, private readonly array $report)
    {
        parent::__construct('P', 'General Ledger', $generatedAt);
    }

    protected function afterDocumentHeader(): void
    {
        $this->tableHeader(['Date', 'Journal Entry', 'Reference', 'Description', 'Debit', 'Credit', 'Running Balance'], $this->widths, ['', '', '', '', 'R', 'R', 'R']);
    }

    public function render(): self
    {
        $this->AddPage();
        $this->tableRow(['', '', '', 'Beginning Balance', '', '', $this->money((float) $this->report['beginning_balance'])], $this->widths, ['', '', '', '', 'R', 'R', 'R']);

        foreach ($this->report['rows'] as $index => $row) {
            $runningBalance = (float) $row['running_balance'];
            $this->tableRow([
                (string) $row['date'],
                (string) $row['journal_number'],
                (string) ($row['reference'] ?: '-'),
                (string) ($row['line_description'] ?: $row['description']),
                $this->money((float) $row['debit'], true),
                $this->money((float) $row['credit'], true),
                $this->money($runningBalance),
            ], $this->widths, ['', '', '', '', 'R', 'R', 'R'], $runningBalance < 0 ? [6] : [], $index % 2 === 1);
        }

        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(15, 23, 42);
        $this->SetDrawColor(17, 24, 39);
        $this->Cell(array_sum(array_slice($this->widths, 0, 4)), 8, count($this->report['rows']).' transactions', 'TB', 0);
        $this->Cell($this->widths[4], 8, $this->money((float) $this->report['total_debit']), 'TB', 0, 'R');
        $this->Cell($this->widths[5], 8, $this->money((float) $this->report['total_credit']), 'TB', 0, 'R');
        $this->Cell($this->widths[6], 8, $this->money((float) $this->report['ending_balance']), 'TB', 1, 'R');

        return $this;
    }
}
