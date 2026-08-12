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
        parent::__construct('L', 'Accounts Payable - Bill Register', $generatedAt);
    }

    protected function afterDocumentHeader(): void
    {
        $search = $this->filters['search'] ?: 'All bills';
        $status = $this->filters['status'] ?: 'All statuses';
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, $this->pdfText('Search: '.$search.' | Status: '.$status), 0, 1);
        $this->Ln(2);
        $this->tableHeader(
            ['Bill No.', 'Bill Date', 'Due Date', 'Vendor', 'Amount', 'Paid', 'Balance', 'Status'],
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
            $this->Cell(array_sum($this->widths), 12, 'No vendor bills match selected filters.', 1, 1, 'C');

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
        $this->Cell(array_sum(array_slice($this->widths, 0, 4)), 8, count($bills).' bill(s)', 1, 0, 'R');
        $this->Cell($this->widths[4], 8, $this->money($total), 1, 0, 'R');
        $this->Cell($this->widths[5], 8, $this->money($paid), 1, 0, 'R');
        $this->Cell($this->widths[6], 8, $this->money($balance), 1, 0, 'R');
        $this->Cell($this->widths[7], 8, '', 1, 1);

        return $this;
    }
}
