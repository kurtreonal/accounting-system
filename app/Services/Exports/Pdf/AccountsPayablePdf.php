<?php

namespace App\Services\Exports\Pdf;

use DateTimeInterface;

class AccountsPayablePdf extends AccountingPdf
{
    /** @var array<int, float|int> */
    private array $widths = [30, 24, 24, 55, 32, 32, 32, 26];

    /** @param array<string, string> $filters */
    public function __construct(DateTimeInterface $generatedAt, private readonly array $filters)
    {
        parent::__construct('L', 'Accounts Payable', $generatedAt);
    }

    protected function afterDocumentHeader(): void
    {
        $this->tableHeader(
            ['Document', 'Document Date', 'Due Date', 'Payee', 'Amount', 'Paid', 'Balance', 'Status'],
            $this->widths,
            ['L', 'L', 'L', 'L', 'R', 'R', 'R', 'L'],
        );
    }

    /** @param array<int, array<string, mixed>> $bills */
    public function render(array $bills): self
    {
        $this->AddPage();

        if ($bills === []) {
            $this->SetFont('Helvetica', 'I', 10);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(array_sum($this->widths), 12, 'No open payables match selected filters.', 'TB', 1, 'C');

            return $this;
        }

        $total = 0.0;
        $paid = 0.0;
        $balance = 0.0;
        foreach ($bills as $index => $bill) {
            $total += (float) $bill['total'];
            $paid += (float) $bill['amount_paid'];
            $balance += (float) $bill['remaining_balance'];
            $this->tableRow([
                (string) $bill['bill_number'],
                (string) $bill['bill_date'],
                (string) $bill['due_date'],
                (string) $bill['vendor_name'],
                $this->money((float) $bill['total']),
                $this->money((float) $bill['amount_paid'], true),
                $this->money((float) $bill['remaining_balance']),
                (string) $bill['display_status'],
            ], $this->widths, ['L', 'L', 'L', 'L', 'R', 'R', 'R', 'L'], [], $index % 2 === 1);
        }

        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(15, 23, 42);
        $this->SetDrawColor(17, 24, 39);
        $this->Cell(array_sum(array_slice($this->widths, 0, 4)), 8, count($bills).' payable(s)', 'TB', 0, 'R');
        $this->Cell($this->widths[4], 8, $this->money($total), 'TB', 0, 'R');
        $this->Cell($this->widths[5], 8, $this->money($paid), 'TB', 0, 'R');
        $this->Cell($this->widths[6], 8, $this->money($balance), 'TB', 0, 'R');
        $this->Cell($this->widths[7], 8, '', 'TB', 1);

        return $this;
    }
}
