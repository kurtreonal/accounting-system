<?php

namespace App\Services\Accounting;

use Throwable;

class StaticAccountingTransaction
{
    /** @template TResult
     * @param callable(): TResult $callback
     * @return TResult
     */
    public function run(callable $callback): mixed
    {
        $paths = array_values(array_unique(array_filter([
            config('accounting.accounts_path'), config('accounting.journal_entries_path'),
            config('accounting.audit_logs_path'), config('accounting.expenses_path'),
            config('accounting.expense_payments_path'), config('accounting.bank_transactions_path'),
            config('accounting.bank_reconciliations_path'),
        ], 'is_string')));
        $snapshots = [];
        foreach ($paths as $path) {
            $snapshots[$path] = is_file($path) ? file_get_contents($path) : null;
        }

        try {
            return $callback();
        } catch (Throwable $exception) {
            foreach ($snapshots as $path => $contents) {
                if ($contents === null) {
                    if (is_file($path)) unlink($path);
                } else {
                    file_put_contents($path, $contents, LOCK_EX);
                }
            }
            throw $exception;
        }
    }
}
