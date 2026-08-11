<?php

namespace App\Services\Exports\Pdf;

use DateTimeInterface;

class GeneralLedgerPdf extends AccountingPdf
{
    /** @var array<int, float|int> */
    private array $widths = [25, 36, 30, 70, 34, 34, 36];

    /** @param array<string, mixed> $report */
    public function __construct(DateTimeInterface $generatedAt, private readonly array $report)
    {
        parent::__construct('L', 'General Ledger', $generatedAt);
    }

    protected function afterDocumentHeader(): void
    {
        $account = $this->report['account'];
        $filters = $this->report['filters'];
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(150, 6, $this->pdfText($account['code'].' - '.$account['name']), 0, 0);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 6, $this->pdfText(($filters['date_from'] ?: 'Beginning').' to '.($filters['date_to'] ?: 'Present')), 0, 1, 'R');
        $this->Ln(2);
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
        $this->Cell(array_sum(array_slice($this->widths, 0, 4)), 8, count($this->report['rows']).' transactions', 1, 0);
        $this->Cell($this->widths[4], 8, $this->money((float) $this->report['total_debit']), 1, 0, 'R');
        $this->Cell($this->widths[5], 8, $this->money((float) $this->report['total_credit']), 1, 0, 'R');
        $this->Cell($this->widths[6], 8, $this->money((float) $this->report['ending_balance']), 1, 1, 'R');

        return $this;
    }
}
