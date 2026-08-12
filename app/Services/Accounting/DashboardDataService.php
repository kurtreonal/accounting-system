<?php

namespace App\Services\Accounting;

use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\JournalEntryDataService;
use App\Services\DemoData\PurchaseDataService;
use App\Services\DemoData\SalesDataService;
use Illuminate\Support\Str;

class DashboardDataService
{
    public function __construct(
        private AccountDataService $accounts,
        private JournalEntryDataService $journals,
        private SalesDataService $sales,
        private PurchaseDataService $purchases,
    ) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $accounts = $this->accounts->all();
        $journals = $this->journals->all();
        $invoices = $this->sales->invoices();
        $bills = $this->purchases->bills();
        $accountMap = collect($accounts)->keyBy(fn (array $account): string => (string) $account['code']);
        $postedHistory = array_values(array_filter(
            $journals,
            static fn (array $entry): bool => in_array($entry['status'], ['Posted', 'Reversed'], true),
        ));
        $today = now()->toDateString();
        $month = now()->format('Y-m');
        $year = now()->format('Y');
        $monthly = array_fill(1, 12, ['revenue' => 0.0, 'expenses' => 0.0]);

        $currentRevenue = 0.0;
        $currentExpenses = 0.0;
        foreach ($postedHistory as $entry) {
            foreach ($entry['lines'] as $line) {
                $account = $accountMap->get((string) $line['account_code']);
                if (! $account) {
                    continue;
                }

                $amount = match ($account['type']) {
                    'Revenue' => (float) $line['credit'] - (float) $line['debit'],
                    'Expense' => (float) $line['debit'] - (float) $line['credit'],
                    default => 0.0,
                };
                if (str_starts_with($entry['date'], $month)) {
                    $currentRevenue += $account['type'] === 'Revenue' ? $amount : 0;
                    $currentExpenses += $account['type'] === 'Expense' ? $amount : 0;
                }
                if (str_starts_with($entry['date'], $year.'-')) {
                    $monthNumber = (int) substr($entry['date'], 5, 2);
                    if ($account['type'] === 'Revenue') {
                        $monthly[$monthNumber]['revenue'] += $amount;
                    } elseif ($account['type'] === 'Expense') {
                        $monthly[$monthNumber]['expenses'] += $amount;
                    }
                }
            }
        }

        $cashAccounts = array_values(array_filter($accounts, static function (array $account): bool {
            $search = Str::lower($account['name'].' '.$account['sub_type']);

            return $account['type'] === 'Asset' && (str_contains($search, 'cash') || str_contains($search, 'bank'));
        }));
        $receivableAccounts = array_values(array_filter($accounts, static fn (array $account): bool => str_contains(Str::lower($account['name']), 'receivable')));
        $payableAccounts = array_values(array_filter($accounts, static fn (array $account): bool => str_contains(Str::lower($account['name']), 'payable')));
        $currentInvoices = array_values(array_filter($invoices, static fn (array $invoice): bool => $invoice['status'] !== 'Draft' && (float) $invoice['remaining_balance'] > 0));
        $overdueInvoices = array_values(array_filter($currentInvoices, static fn (array $invoice): bool => $invoice['due_date'] < $today));
        $currentBills = array_values(array_filter($bills, static fn (array $bill): bool => $bill['status'] !== 'Draft' && (float) $bill['remaining_balance'] > 0));
        $overdueBills = array_values(array_filter($currentBills, static fn (array $bill): bool => $bill['due_date'] < $today));
        $hasRevenueAccounts = collect($accounts)->contains('type', 'Revenue');
        $hasExpenseAccounts = collect($accounts)->contains('type', 'Expense');

        $maxChartValue = max(0, ...array_map(
            static fn (array $values): float => max($values['revenue'], $values['expenses']),
            $monthly,
        ));

        return [
            'kpis' => [
                'cash' => $this->metric($cashAccounts !== [], collect($cashAccounts)->sum('balance'), count($cashAccounts).' '.Str::plural('account', count($cashAccounts))),
                'receivables' => $this->metric($receivableAccounts !== [] || $invoices !== [], $invoices !== [] ? collect($currentInvoices)->sum('remaining_balance') : collect($receivableAccounts)->sum('balance'), count($currentInvoices).' open '.Str::plural('invoice', count($currentInvoices))),
                'payables' => $this->metric($payableAccounts !== [] || $bills !== [], $bills !== [] ? collect($currentBills)->sum('remaining_balance') : collect($payableAccounts)->sum('balance'), count($currentBills).' open '.Str::plural('bill', count($currentBills))),
                'revenue' => $this->metric($hasRevenueAccounts, $currentRevenue, 'Current month'),
                'expenses' => $this->metric($hasExpenseAccounts, $currentExpenses, 'Current month'),
                'net_income' => $this->metric($hasRevenueAccounts || $hasExpenseAccounts, $currentRevenue - $currentExpenses, 'Current month'),
                'overdue_receivables' => $this->metric($invoices !== [], collect($overdueInvoices)->sum('remaining_balance'), count($overdueInvoices).' overdue '.Str::plural('invoice', count($overdueInvoices))),
                'overdue_payables' => $this->metric($bills !== [], collect($overdueBills)->sum('remaining_balance'), count($overdueBills).' overdue '.Str::plural('bill', count($overdueBills))),
            ],
            'monthly' => $monthly,
            'chart_max' => $maxChartValue,
            'cash_accounts' => $cashAccounts,
            'recent_journals' => array_slice($journals, 0, 6),
            'recent_customer_payments' => array_slice($this->sales->payments(), 0, 5),
            'recent_vendor_payments' => array_slice($this->purchases->payments(), 0, 5),
        ];
    }

    /** @return array{available: bool, value: float, note: string} */
    private function metric(bool $available, float|int $value, string $note): array
    {
        return ['available' => $available, 'value' => round((float) $value, 2), 'note' => $available ? $note : 'No data available'];
    }
}
