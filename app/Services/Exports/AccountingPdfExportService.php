<?php

namespace App\Services\Exports;

use App\Services\Exports\Pdf\GeneralLedgerPdf;
use App\Services\Exports\Pdf\JournalEntriesPdf;
use App\Services\Exports\Pdf\JournalEntryPdf;
use DateTimeInterface;

class AccountingPdfExportService
{
    /** @param array<int, array<string, mixed>> $entries
     * @param  array<string, string>  $filters
     */
    public function journalEntries(array $entries, array $filters, DateTimeInterface $generatedAt): string
    {
        $pdf = new JournalEntriesPdf($generatedAt, $filters);

        return $pdf->render($entries)->Output('S');
    }

    /** @param array<string, mixed> $entry */
    public function journalEntry(array $entry, DateTimeInterface $generatedAt): string
    {
        $pdf = new JournalEntryPdf($generatedAt, $entry);

        return $pdf->render()->Output('S');
    }

    /** @param array<string, mixed> $report */
    public function generalLedger(array $report, DateTimeInterface $generatedAt): string
    {
        $pdf = new GeneralLedgerPdf($generatedAt, $report);

        return $pdf->render()->Output('S');
    }
}
