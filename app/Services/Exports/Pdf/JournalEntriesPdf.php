<?php

namespace App\Services\Exports\Pdf;

use DateTimeInterface;
use Illuminate\Support\Arr;

class JournalEntriesPdf extends AccountingPdf
{
    /** @var array<int, float|int> */
    private array $widths = [34, 24, 74, 34, 34, 28, 28];

    /** @param array<string, string> $filters */
    public function __construct(DateTimeInterface $generatedAt, private readonly array $filters = [])
    {
        parent::__construct('L', 'Journal Entries', $generatedAt);
    }

    protected function afterDocumentHeader(): void
    {
        $filter = trim(implode(' | ', array_filter([
            ($this->filters['status'] ?? '') !== '' ? 'Status: '.$this->filters['status'] : '',
            ($this->filters['search'] ?? '') !== '' ? 'Search: '.$this->filters['search'] : '',
        ])));

        if ($filter !== '') {
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 5, $this->pdfText($filter), 0, 1);
            $this->Ln(2);
        }

        $this->tableHeader(
            ['JE No.', 'Date', 'Description', 'Reference', 'Total Debit', 'Prepared By', 'Status'],
            $this->widths,
            ['', '', '', '', 'R'],
        );
    }

    /** @param array<int, array<string, mixed>> $entries */
    public function render(array $entries): self
    {
        $this->AddPage();

        if ($entries === []) {
            $this->SetFont('Helvetica', 'I', 9);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(array_sum($this->widths), 12, 'No journal entries found.', 1, 1, 'C');

            return $this;
        }

        foreach ($entries as $index => $entry) {
            $this->tableRow([
                (string) $entry['journal_number'],
                (string) $entry['date'],
                (string) $entry['description'],
                (string) ($entry['reference'] ?: '-'),
                $this->money((float) $entry['total_debit']),
                (string) Arr::get($entry, 'created_by.name', '-'),
                (string) $entry['status'],
            ], $this->widths, ['', '', '', '', 'R'], [], $index % 2 === 1);
        }

        return $this;
    }
}
