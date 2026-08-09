<?php

namespace App\Services\Exports;

use App\Services\Exports\Pdf\ChartOfAccountsPdf;
use DateTimeInterface;

class ChartOfAccountsExportService
{
    /** @param array<int, array<string, mixed>> $accounts */
    public function pdf(array $accounts, DateTimeInterface $generatedAt): string
    {
        $pdf = new ChartOfAccountsPdf($generatedAt);
        $pdf->render($accounts);

        return $pdf->Output('S');
    }

    /** @param resource $stream
     * @param  array<int, array<string, mixed>>  $accounts
     */
    public function writeCsv($stream, array $accounts): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Account Code', 'Account Name', 'Type', 'Sub-Type', 'Balance (PHP)', 'Status']);

        foreach ($accounts as $account) {
            fputcsv($stream, [
                "\t".$this->safeText((string) $account['code']),
                $this->safeText((string) $account['name']),
                $this->safeText((string) $account['type']),
                $this->safeText((string) $account['sub_type']),
                number_format((float) $account['balance'], 2, '.', ''),
                $this->safeText((string) $account['status']),
            ]);
        }
    }

    private function safeText(string $value): string
    {
        $value = str_replace("\0", '', $value);

        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
