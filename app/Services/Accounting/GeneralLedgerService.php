<?php

namespace App\Services\Accounting;

use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\JournalEntryDataService;
use RuntimeException;

class GeneralLedgerService
{
    public function __construct(
        private AccountDataService $accounts,
        private JournalEntryDataService $journals,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function accounts(): array
    {
        return $this->accounts->all();
    }

    /** @return array<string, mixed> */
    public function forAccount(string $accountCode, ?string $dateFrom = null, ?string $dateTo = null, string $search = ''): array
    {
        $account = collect($this->accounts())->first(
            static fn (array $item): bool => (string) $item['code'] === $accountCode
        );

        if ($account === null) {
            throw new RuntimeException('The ledger account could not be found.');
        }

        $dateFrom = $this->date($dateFrom);
        $dateTo = $this->date($dateTo);

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new RuntimeException('The start date must be before or equal to the end date.');
        }

        $debitNormal = in_array($account['type'], ['Asset', 'Expense'], true);
        $transactions = $this->transactions($accountCode, $debitNormal);
        $baseBalance = round((float) $account['balance'] - array_sum(array_column($transactions, 'effect')), 2);
        $beginningBalance = $baseBalance;

        foreach ($transactions as $transaction) {
            if ($dateFrom !== null && $transaction['date'] < $dateFrom) {
                $beginningBalance = round($beginningBalance + $transaction['effect'], 2);
            }
        }

        $runningBalance = $beginningBalance;
        $periodRows = [];

        foreach ($transactions as $transaction) {
            if (($dateFrom !== null && $transaction['date'] < $dateFrom)
                || ($dateTo !== null && $transaction['date'] > $dateTo)) {
                continue;
            }

            $runningBalance = round($runningBalance + $transaction['effect'], 2);
            $periodRows[] = [...$transaction, 'running_balance' => $runningBalance];
        }

        $endingBalance = $runningBalance;
        $needle = mb_strtolower(trim($search));
        $rows = array_values(array_filter($periodRows, static function (array $row) use ($needle): bool {
            if ($needle === '') {
                return true;
            }

            return str_contains(mb_strtolower(implode(' ', [
                $row['journal_number'],
                $row['reference'],
                $row['description'],
                $row['line_description'],
            ])), $needle);
        }));

        return [
            'account' => $account,
            'beginning_balance' => $beginningBalance,
            'ending_balance' => $endingBalance,
            'rows' => $rows,
            'total_debit' => round((float) array_sum(array_column($rows, 'debit')), 2),
            'total_credit' => round((float) array_sum(array_column($rows, 'credit')), 2),
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => trim($search)],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function transactions(string $accountCode, bool $debitNormal): array
    {
        $transactions = [];

        foreach ($this->journals->all() as $entry) {
            // Reversed entries were posted before reversal and remain part of ledger history.
            if (! in_array($entry['status'], ['Posted', 'Reversed'], true)) {
                continue;
            }

            foreach ($entry['lines'] as $line) {
                if ((string) $line['account_code'] !== $accountCode) {
                    continue;
                }

                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);
                $transactions[] = [
                    'date' => $entry['date'],
                    'journal_number' => $entry['journal_number'],
                    'reference' => (string) ($entry['reference'] ?? ''),
                    'description' => (string) $entry['description'],
                    'line_description' => (string) ($line['description'] ?? ''),
                    'status' => $entry['status'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'effect' => $debitNormal ? $debit - $credit : $credit - $debit,
                ];
            }
        }

        usort($transactions, static fn (array $left, array $right): int => [
            $left['date'], $left['journal_number'],
        ] <=> [
            $right['date'], $right['journal_number'],
        ]);

        return $transactions;
    }

    private function date(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Use a valid date in YYYY-MM-DD format.');
        }

        return $value;
    }
}
