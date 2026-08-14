<?php

namespace App\Services\Exports\Pdf;

use DateTimeInterface;

class ChartOfAccountsPdf extends AccountingPdf
{
    /** @var array<int, float|int> */
    private array $widths = [15, 52, 24, 38, 38, 23];

    public function __construct(DateTimeInterface $generatedAt)
    {
        parent::__construct('P', 'Chart of Accounts', $generatedAt);
    }

    protected function afterDocumentHeader(): void
    {
        $this->tableHeader(
            ['Code', 'Account Name', 'Type', 'Sub-Type', 'Balance', 'Status'],
            $this->widths,
            ['', '', '', '', 'R', ''],
        );
    }

    /** @param array<int, array<string, mixed>> $accounts */
    public function render(array $accounts): self
    {
        $this->AddPage();

        if ($accounts === []) {
            $this->SetFont('Helvetica', 'I', 9);
            $this->SetTextColor(75, 85, 99);
            $this->Cell(array_sum($this->widths), 12, 'No records found.', 'TB', 1, 'C');

            return $this;
        }

        foreach ($accounts as $account) {
            $this->tableRow([
                (string) $account['code'],
                (string) $account['name'],
                (string) $account['type'],
                (string) $account['sub_type'],
                $this->money((float) $account['balance']),
                (string) $account['status'],
            ], $this->widths, ['', '', '', '', 'R', '']);
        }

        return $this;
    }
}
