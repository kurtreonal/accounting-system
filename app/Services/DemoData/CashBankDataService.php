<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class CashBankDataService
{
    /** @return array<int, array<string, mixed>> */
    public function transactions(): array
    {
        return $this->load($this->transactionsPath());
    }

    /** @return array<int, array<string, mixed>> */
    public function reconciliations(): array
    {
        return $this->load($this->reconciliationsPath());
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function recordTransaction(array $attributes): array
    {
        return $this->mutate($this->transactionsPath(), function (array &$rows) use ($attributes): array {
            if (collect($rows)->contains('request_token', $attributes['request_token'])) {
                throw new RuntimeException('This cash transaction request was already posted.');
            }

            $year = substr((string) $attributes['date'], 0, 4);
            $sequence = collect($rows)->filter(fn (array $row): bool => str_starts_with((string) ($row['transaction_number'] ?? ''), "CB-{$year}-"))->count() + 1;
            $transaction = [
                'id' => collect($rows)->max('id') + 1,
                'transaction_number' => sprintf('CB-%s-%04d', $year, $sequence),
                'cleared' => false,
                'reconciliation_id' => null,
                'created_at' => now()->toIso8601String(),
                ...$attributes,
            ];
            $rows[] = $transaction;

            return $transaction;
        });
    }

    /** @param array<int, int> $transactionIds
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function reconcile(string $accountCode, array $transactionIds, array $attributes): array
    {
        $transactionsPath = $this->transactionsPath();
        $reconciliationsPath = $this->reconciliationsPath();
        $transactionHandle = $this->openLocked($transactionsPath);
        $reconciliationHandle = $this->openLocked($reconciliationsPath);

        try {
            $transactions = $this->decodeStream($transactionHandle);
            $reconciliations = $this->decodeStream($reconciliationHandle);
            $eligibleIds = collect($transactions)
                ->filter(fn (array $row): bool => $this->touchesAccount($row, $accountCode) && ! ($row['cleared'] ?? false))
                ->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if (array_diff($transactionIds, $eligibleIds) !== []) {
                throw new RuntimeException('One or more selected transactions cannot be reconciled.');
            }

            $id = (int) collect($reconciliations)->max('id') + 1;
            foreach ($transactions as &$transaction) {
                if (in_array((int) $transaction['id'], $transactionIds, true)) {
                    $transaction['cleared'] = true;
                    $transaction['reconciliation_id'] = $id;
                }
            }
            unset($transaction);

            $reconciliation = [
                'id' => $id,
                'reference' => sprintf('REC-%s-%04d', now()->format('Y'), $id),
                'account_code' => $accountCode,
                'transaction_ids' => $transactionIds,
                'created_at' => now()->toIso8601String(),
                ...$attributes,
            ];
            $reconciliations[] = $reconciliation;
            $this->writeStream($transactionHandle, $transactions);
            $this->writeStream($reconciliationHandle, $reconciliations);

            return $reconciliation;
        } finally {
            $this->unlock($reconciliationHandle);
            $this->unlock($transactionHandle);
        }
    }

    /** @param array<string, mixed> $row */
    public function touchesAccount(array $row, string $accountCode): bool
    {
        return (string) ($row['account_code'] ?? '') === $accountCode
            || (string) ($row['from_account_code'] ?? '') === $accountCode
            || (string) ($row['to_account_code'] ?? '') === $accountCode;
    }

    /** @return array<int, array<string, mixed>> */
    private function load(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $rows = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Cash and bank demo data is invalid.', previous: $exception);
        }

        return is_array($rows) ? $rows : [];
    }

    private function mutate(string $path, callable $callback): mixed
    {
        $handle = $this->openLocked($path);
        try {
            $rows = $this->decodeStream($handle);
            $result = $callback($rows);
            $this->writeStream($handle, $rows);

            return $result;
        } finally {
            $this->unlock($handle);
        }
    }

    /** @return resource */
    private function openLocked(string $path)
    {
        $handle = fopen($path, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock cash and bank demo data.');
        }

        return $handle;
    }

    /** @param resource $handle
     * @return array<int, array<string, mixed>>
     */
    private function decodeStream($handle): array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);
        if ($contents === false || trim($contents) === '') {
            return [];
        }

        try {
            $rows = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Cash and bank demo data is invalid.', previous: $exception);
        }

        return is_array($rows) ? $rows : [];
    }

    /** @param resource $handle
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeStream($handle, array $rows): void
    {
        $encoded = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        rewind($handle);
        if (! ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || ! fflush($handle)) {
            throw new RuntimeException('Unable to save cash and bank demo data.');
        }
    }

    /** @param resource $handle */
    private function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function transactionsPath(): string
    {
        return (string) config('accounting.bank_transactions_path', storage_path('demo-data/bank_transactions.json'));
    }

    private function reconciliationsPath(): string
    {
        return (string) config('accounting.bank_reconciliations_path', storage_path('demo-data/bank_reconciliations.json'));
    }
}
