<?php

namespace App\Services\Accounting;

use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\ExpenseDataService;
use App\Services\DemoData\JournalEntryDataService;
use App\Services\DemoData\SalesDataService;
use App\Services\DemoData\TaxCodeDataService;
use Illuminate\Support\Str;
use RuntimeException;

class FinancialReportService
{
    public const REPORTS = [
        'trial-balance' => 'Trial Balance',
        'income-statement' => 'Income Statement',
        'balance-sheet' => 'Balance Sheet',
        'cash-flow' => 'Cash Flow Summary',
        'sales-report' => 'Sales Report',
        'expense-report' => 'Expense Report',
        'tax-summary' => 'Tax Summary',
    ];

    public function __construct(
        private AccountDataService $accounts,
        private JournalEntryDataService $journals,
        private SalesDataService $sales,
        private ExpenseDataService $expenses,
        private TaxCodeDataService $taxCodes,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(string $report, array $filters = []): array
    {
        if (! array_key_exists($report, self::REPORTS)) {
            throw new RuntimeException('Select a valid financial report.');
        }

        $dateFrom = $this->date($filters['date_from'] ?? null) ?? now()->startOfYear()->toDateString();
        $dateTo = $this->date($filters['date_to'] ?? null) ?? now()->toDateString();
        if ($dateFrom > $dateTo) {
            throw new RuntimeException('The start date must be before or equal to the end date.');
        }
        $filters = [...$filters, 'date_from' => $dateFrom, 'date_to' => $dateTo];

        $data = match ($report) {
            'trial-balance' => $this->trialBalance($filters),
            'income-statement' => $this->incomeStatement($filters),
            'balance-sheet' => $this->balanceSheet($filters),
            'cash-flow' => $this->cashFlow($filters),
            'sales-report' => $this->salesReport($filters),
            'expense-report' => $this->expenseReport($filters),
            'tax-summary' => $this->taxSummary($filters),
        };

        return [
            'key' => $report,
            'title' => self::REPORTS[$report],
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
            ...$data,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function summaries(array $filters = []): array
    {
        return collect(array_keys(self::REPORTS))->map(function (string $key) use ($filters): array {
            $report = $this->generate($key, $filters);

            return ['key' => $key, 'title' => $report['title'], 'summary' => $report['summary'], 'count' => $report['record_count']];
        })->all();
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function trialBalance(array $filters): array
    {
        $rows = [];
        foreach ($this->accountBalances($filters['date_to']) as $balance) {
            $debit = $balance['normal'] === 'debit' ? max($balance['balance'], 0) : max(-$balance['balance'], 0);
            $credit = $balance['normal'] === 'credit' ? max($balance['balance'], 0) : max(-$balance['balance'], 0);
            $rows[] = [
                'account_code' => $balance['account']['code'],
                'account_name' => $balance['account']['name'],
                'account_type' => $balance['account']['type'],
                'debit' => $this->money($debit),
                'credit' => $this->money($credit),
                'link' => route('general-ledger', ['account' => $balance['account']['code'], 'date_to' => $filters['date_to']]),
            ];
        }
        $totalDebit = $this->money(collect($rows)->sum('debit'));
        $totalCredit = $this->money(collect($rows)->sum('credit'));

        return $this->table(
            'As of '.$filters['date_to'],
            [['key' => 'account_code', 'label' => 'Account'], ['key' => 'account_name', 'label' => 'Account Name'], ['key' => 'account_type', 'label' => 'Type'], ['key' => 'debit', 'label' => 'Debit', 'money' => true], ['key' => 'credit', 'label' => 'Credit', 'money' => true]],
            $rows,
            ['label' => 'TOTAL', 'debit' => $totalDebit, 'credit' => $totalCredit],
            $this->money($totalDebit - $totalCredit) === 0.0 ? 'Balanced' : 'Out of balance',
            abs($totalDebit - $totalCredit) < 0.005,
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function incomeStatement(array $filters): array
    {
        $movements = $this->accountMovements($filters['date_from'], $filters['date_to']);
        $rows = [];
        foreach (['Revenue', 'Expense'] as $type) {
            $rows[] = ['row_type' => 'group', 'account_name' => $type === 'Revenue' ? 'Revenue' : 'Operating Expenses'];
            foreach ($this->accounts->all(['type' => $type]) as $account) {
                $movement = $movements[(string) $account['code']] ?? ['debit' => 0.0, 'credit' => 0.0];
                $amount = $type === 'Revenue' ? $movement['credit'] - $movement['debit'] : $movement['debit'] - $movement['credit'];
                if (abs($amount) < 0.005) {
                    continue;
                }
                $rows[] = ['account_code' => $account['code'], 'account_name' => $account['name'], 'amount' => $this->money($amount), 'link' => route('general-ledger', ['account' => $account['code'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']])];
            }
        }
        $revenue = $this->money(collect($rows)->filter(fn (array $row): bool => isset($row['account_code']) && collect($this->accounts->all())->firstWhere('code', $row['account_code'])['type'] === 'Revenue')->sum('amount'));
        $expenses = $this->money(collect($rows)->filter(fn (array $row): bool => isset($row['account_code']) && collect($this->accounts->all())->firstWhere('code', $row['account_code'])['type'] === 'Expense')->sum('amount'));

        return $this->table(
            $filters['date_from'].' to '.$filters['date_to'],
            [['key' => 'account_code', 'label' => 'Account'], ['key' => 'account_name', 'label' => 'Description'], ['key' => 'amount', 'label' => 'Amount', 'money' => true]],
            $rows,
            ['label' => 'NET INCOME', 'amount' => $this->money($revenue - $expenses)],
            'Net income '.$this->plainMoney($revenue - $expenses),
            null,
            ['revenue' => $revenue, 'expenses' => $expenses, 'net_income' => $this->money($revenue - $expenses)],
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function balanceSheet(array $filters): array
    {
        $balances = collect($this->accountBalances($filters['date_to']))->keyBy(fn (array $row): string => (string) $row['account']['code']);
        $rows = [];
        $totals = ['Asset' => 0.0, 'Liability' => 0.0, 'Equity' => 0.0];
        foreach (['Asset', 'Liability', 'Equity'] as $type) {
            $rows[] = ['row_type' => 'group', 'account_name' => Str::plural($type)];
            foreach ($this->accounts->all(['type' => $type]) as $account) {
                $amount = (float) ($balances->get((string) $account['code'])['balance'] ?? 0);
                if (abs($amount) < 0.005) {
                    continue;
                }
                $totals[$type] += $amount;
                $rows[] = ['account_code' => $account['code'], 'account_name' => $account['name'], 'amount' => $this->money($amount), 'link' => route('general-ledger', ['account' => $account['code'], 'date_to' => $filters['date_to']])];
            }
        }
        $currentEarnings = 0.0;
        foreach (['Revenue', 'Expense'] as $type) {
            foreach ($this->accounts->all(['type' => $type]) as $account) {
                $amount = (float) ($balances->get((string) $account['code'])['balance'] ?? 0);
                $currentEarnings += $type === 'Revenue' ? $amount : -$amount;
            }
        }
        $rows[] = ['row_type' => 'group', 'account_name' => 'Current Earnings'];
        $rows[] = ['account_code' => '', 'account_name' => 'Unclosed current earnings', 'amount' => $this->money($currentEarnings)];
        $rightSide = $this->money($totals['Liability'] + $totals['Equity'] + $currentEarnings);
        $assets = $this->money($totals['Asset']);

        return $this->table(
            'As of '.$filters['date_to'],
            [['key' => 'account_code', 'label' => 'Account'], ['key' => 'account_name', 'label' => 'Description'], ['key' => 'amount', 'label' => 'Balance', 'money' => true]],
            $rows,
            ['label' => 'ASSETS / LIABILITIES & EQUITY', 'amount' => $assets, 'secondary_amount' => $rightSide],
            abs($assets - $rightSide) < 0.005 ? 'Accounting equation balanced' : 'Accounting equation out of balance',
            abs($assets - $rightSide) < 0.005,
            ['assets' => $assets, 'liabilities' => $this->money($totals['Liability']), 'equity_and_earnings' => $this->money($totals['Equity'] + $currentEarnings)],
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function cashFlow(array $filters): array
    {
        $accountMap = collect($this->accounts->all())->keyBy('code');
        $cashCodes = $accountMap->filter(fn (array $account): bool => $this->isCashOrBank($account))->keys()->map(fn ($code): string => (string) $code)->all();
        $rows = [];
        foreach ($this->postedEntries($filters['date_from'], $filters['date_to']) as $entry) {
            $amount = $this->money(collect($entry['lines'])->filter(fn (array $line): bool => in_array((string) $line['account_code'], $cashCodes, true))->sum(fn (array $line): float => (float) $line['debit'] - (float) $line['credit']));
            if (abs($amount) < 0.005) {
                continue;
            }
            $counterparts = collect($entry['lines'])->filter(fn (array $line): bool => ! in_array((string) $line['account_code'], $cashCodes, true))->map(fn (array $line) => $accountMap->get((string) $line['account_code']))->filter();
            $activity = $counterparts->contains(fn (array $account): bool => $account['type'] === 'Equity' || ($account['type'] === 'Liability' && ! str_contains(Str::lower($account['sub_type']), 'current')))
                ? 'Financing' : ($counterparts->contains(fn (array $account): bool => $account['type'] === 'Asset' && ! $this->isCashOrBank($account) && ! str_contains(Str::lower($account['name']), 'receivable')) ? 'Investing' : 'Operating');
            $rows[] = ['date' => $entry['date'], 'reference' => $entry['reference'] ?: $entry['journal_number'], 'description' => $entry['description'], 'activity' => $activity, 'amount' => $amount, 'link' => route('journal-entries', ['entry' => $entry['journal_number']])];
        }
        $totals = collect($rows)->groupBy('activity')->map(fn ($group): float => $this->money($group->sum('amount')));
        $net = $this->money(collect($rows)->sum('amount'));

        return $this->table(
            $filters['date_from'].' to '.$filters['date_to'],
            [['key' => 'date', 'label' => 'Date'], ['key' => 'reference', 'label' => 'Reference'], ['key' => 'description', 'label' => 'Description'], ['key' => 'activity', 'label' => 'Activity'], ['key' => 'amount', 'label' => 'Cash Movement', 'money' => true]],
            $rows,
            ['label' => 'NET CHANGE IN CASH', 'amount' => $net],
            'Net cash movement '.$this->plainMoney($net),
            null,
            ['operating' => $totals->get('Operating', 0), 'investing' => $totals->get('Investing', 0), 'financing' => $totals->get('Financing', 0), 'net_change' => $net],
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function salesReport(array $filters): array
    {
        $customer = trim((string) ($filters['party'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $rows = collect($this->sales->invoices())->filter(fn (array $invoice): bool => $invoice['invoice_date'] >= $filters['date_from'] && $invoice['invoice_date'] <= $filters['date_to']
            && ($customer === '' || (string) $invoice['customer_code'] === $customer)
            && ($status === '' || $invoice['status'] === $status))->map(fn (array $invoice): array => [
                'date' => $invoice['invoice_date'], 'reference' => $invoice['invoice_number'], 'party' => $invoice['customer_name'], 'status' => $invoice['status'],
                'subtotal' => $this->money($invoice['subtotal']), 'tax' => $this->money($invoice['tax']), 'total' => $this->money($invoice['total']),
                'link' => $invoice['status'] === 'Draft' ? route('sales-revenue') : route('sales-revenue.invoices.print', $invoice['invoice_number']),
            ])->values()->all();
        $total = $this->money(collect($rows)->sum('total'));

        return $this->table($filters['date_from'].' to '.$filters['date_to'], [['key' => 'date', 'label' => 'Date'], ['key' => 'reference', 'label' => 'Invoice'], ['key' => 'party', 'label' => 'Customer'], ['key' => 'status', 'label' => 'Status'], ['key' => 'subtotal', 'label' => 'Subtotal', 'money' => true], ['key' => 'tax', 'label' => 'Tax', 'money' => true], ['key' => 'total', 'label' => 'Total', 'money' => true]], $rows, ['label' => 'TOTAL SALES', 'total' => $total], 'Sales '.$this->plainMoney($total));
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function expenseReport(array $filters): array
    {
        $category = trim((string) ($filters['category'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $rows = collect($this->expenses->all())->filter(fn (array $expense): bool => $expense['date'] >= $filters['date_from'] && $expense['date'] <= $filters['date_to']
            && ($category === '' || (string) $expense['category_account_code'] === $category)
            && ($status === '' || $expense['status'] === $status))->map(fn (array $expense): array => [
                'date' => $expense['date'], 'reference' => $expense['expense_number'], 'party' => $expense['payee'], 'category' => $expense['category_name'], 'status' => $expense['status'],
                'subtotal' => $this->money($expense['subtotal']), 'tax' => $this->money($expense['tax']), 'total' => $this->money($expense['total']), 'link' => route('expenses').'#'.$expense['expense_number'],
            ])->values()->all();
        $total = $this->money(collect($rows)->sum('total'));

        return $this->table($filters['date_from'].' to '.$filters['date_to'], [['key' => 'date', 'label' => 'Date'], ['key' => 'reference', 'label' => 'Expense'], ['key' => 'party', 'label' => 'Payee'], ['key' => 'category', 'label' => 'Category'], ['key' => 'status', 'label' => 'Status'], ['key' => 'subtotal', 'label' => 'Subtotal', 'money' => true], ['key' => 'tax', 'label' => 'Tax', 'money' => true], ['key' => 'total', 'label' => 'Total', 'money' => true]], $rows, ['label' => 'TOTAL EXPENSES', 'total' => $total], 'Expenses '.$this->plainMoney($total));
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function taxSummary(array $filters): array
    {
        $taxCode = trim((string) ($filters['tax_code'] ?? ''));
        $vatCode = $this->taxCodes->defaultVat();
        $vatRate = (float) ($vatCode['rate'] ?? 0);
        $vatLabel = $vatCode ? $vatCode['code'].' · '.$vatCode['name'] : 'No active VAT code';
        $rows = [];
        foreach ($this->postedEntries($filters['date_from'], $filters['date_to']) as $entry) {
            foreach ($entry['lines'] as $line) {
                $name = Str::lower((string) $line['account_name']);
                $code = str_contains($name, 'input tax') ? 'INPUT' : (str_contains($name, 'output tax') ? 'OUTPUT' : null);
                if ($code === null || ($taxCode !== '' && $taxCode !== $code)) {
                    continue;
                }
                $amount = $code === 'INPUT' ? (float) $line['debit'] - (float) $line['credit'] : (float) $line['credit'] - (float) $line['debit'];
                $rows[] = ['date' => $entry['date'], 'reference' => $entry['reference'] ?: $entry['journal_number'], 'tax_code' => $code === 'INPUT' ? 'Input VAT' : 'Output VAT', 'tax_rate' => $vatLabel, 'source' => $entry['source_type'], 'taxable_amount' => $vatRate > 0 ? $this->money($amount / ($vatRate / 100)) : 0.0, 'tax_amount' => $this->money($amount), 'link' => route('journal-entries', ['entry' => $entry['journal_number']])];
            }
        }
        $input = $this->money(collect($rows)->where('tax_code', 'Input VAT')->sum('tax_amount'));
        $output = $this->money(collect($rows)->where('tax_code', 'Output VAT')->sum('tax_amount'));

        return $this->table($filters['date_from'].' to '.$filters['date_to'].' · Demo tax configuration', [['key' => 'date', 'label' => 'Date'], ['key' => 'reference', 'label' => 'Reference'], ['key' => 'tax_code', 'label' => 'Tax Code'], ['key' => 'tax_rate', 'label' => 'Configured Rate'], ['key' => 'source', 'label' => 'Source'], ['key' => 'taxable_amount', 'label' => 'Taxable Amount', 'money' => true], ['key' => 'tax_amount', 'label' => 'Tax Amount', 'money' => true]], $rows, ['label' => 'NET OUTPUT TAX', 'tax_amount' => $this->money($output - $input)], 'Net output tax '.$this->plainMoney($output - $input), null, ['input_tax' => $input, 'output_tax' => $output, 'net_tax' => $this->money($output - $input)]);
    }

    /** @return array<int, array<string, mixed>> */
    private function accountBalances(string $dateTo): array
    {
        $allEntries = $this->postedEntries();
        $effects = [];
        $effectsToDate = [];
        foreach ($allEntries as $entry) {
            foreach ($entry['lines'] as $line) {
                $code = (string) $line['account_code'];
                $account = collect($this->accounts->all())->firstWhere('code', $code);
                if (! $account) {
                    continue;
                }
                $normalDebit = in_array($account['type'], ['Asset', 'Expense'], true);
                $effect = $normalDebit ? (float) $line['debit'] - (float) $line['credit'] : (float) $line['credit'] - (float) $line['debit'];
                $effects[$code] = ($effects[$code] ?? 0) + $effect;
                if ($entry['date'] <= $dateTo) {
                    $effectsToDate[$code] = ($effectsToDate[$code] ?? 0) + $effect;
                }
            }
        }

        return collect($this->accounts->all())->map(fn (array $account): array => [
            'account' => $account,
            'normal' => in_array($account['type'], ['Asset', 'Expense'], true) ? 'debit' : 'credit',
            'balance' => $this->money((float) $account['balance'] - ($effects[(string) $account['code']] ?? 0) + ($effectsToDate[(string) $account['code']] ?? 0)),
        ])->all();
    }

    /** @return array<string, array{debit: float, credit: float}> */
    private function accountMovements(string $dateFrom, string $dateTo): array
    {
        $movements = [];
        foreach ($this->postedEntries($dateFrom, $dateTo) as $entry) {
            foreach ($entry['lines'] as $line) {
                $code = (string) $line['account_code'];
                $movements[$code] ??= ['debit' => 0.0, 'credit' => 0.0];
                $movements[$code]['debit'] += (float) $line['debit'];
                $movements[$code]['credit'] += (float) $line['credit'];
            }
        }

        return $movements;
    }

    /** @return array<int, array<string, mixed>> */
    private function postedEntries(?string $dateFrom = null, ?string $dateTo = null): array
    {
        return collect($this->journals->all())->filter(fn (array $entry): bool => in_array($entry['status'], ['Posted', 'Reversed'], true)
            && ($dateFrom === null || $entry['date'] >= $dateFrom) && ($dateTo === null || $entry['date'] <= $dateTo))->sortBy(fn (array $entry): string => $entry['date'].'-'.$entry['journal_number'])->values()->all();
    }

    /** @param array<int, array<string, mixed>> $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     * @param  array<string, mixed>  $kpis
     * @return array<string, mixed>
     */
    private function table(string $period, array $columns, array $rows, array $totals, string $summary, ?bool $balanced = null, array $kpis = []): array
    {
        return compact('period', 'columns', 'rows', 'totals', 'summary', 'balanced', 'kpis') + ['record_count' => collect($rows)->where('row_type', null)->count()];
    }

    /** @param array<string, mixed> $account */
    private function isCashOrBank(array $account): bool
    {
        $text = Str::lower($account['name'].' '.$account['sub_type']);

        return $account['type'] === 'Asset' && (str_contains($text, 'cash') || str_contains($text, 'bank'));
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Use valid report dates in YYYY-MM-DD format.');
        }

        return $value;
    }

    private function money(float|int $value): float
    {
        return round((float) $value, 2);
    }

    private function plainMoney(float|int $value): string
    {
        return 'PHP '.number_format((float) $value, 2, '.', ',');
    }
}
