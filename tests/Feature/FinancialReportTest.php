<?php

namespace Tests\Feature;

use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = uniqid('', true);
        foreach (['accounts', 'journals', 'customers', 'invoices', 'payments', 'expenses', 'tax_codes'] as $name) {
            $this->paths[$name] = storage_path("framework/testing/report-{$name}-{$suffix}.json");
            file_put_contents($this->paths[$name], '[]');
        }
        file_put_contents($this->paths['accounts'], json_encode([
            $this->account('1000', 'Business Bank', 'Asset', 'Bank', 1336),
            $this->account('1100', 'Accounts Receivable', 'Asset', 'Current Asset', 0),
            $this->account('1200', 'Input Tax Receivable', 'Asset', 'Current Asset', 24),
            $this->account('2000', 'Accounts Payable', 'Liability', 'Current Liability', 0),
            $this->account('2100', 'Output Tax Payable', 'Liability', 'Current Liability', 60),
            $this->account('3000', 'Owner Capital', 'Equity', 'Owner Equity', 1000),
            $this->account('4000', 'Service Revenue', 'Revenue', 'Operating Revenue', 500),
            $this->account('5100', 'Office Expense', 'Expense', 'Operating Expense', 200),
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['journals'], json_encode([
            $this->journal('JE-2026-0001', '2026-08-01', 'INV-1', 'Invoice', [['1100', 'Accounts Receivable', 560, 0], ['4000', 'Service Revenue', 0, 500], ['2100', 'Output Tax Payable', 0, 60]]),
            $this->journal('JE-2026-0002', '2026-08-05', 'RCP-1', 'Payment', [['1000', 'Business Bank', 560, 0], ['1100', 'Accounts Receivable', 0, 560]]),
            $this->journal('JE-2026-0003', '2026-08-10', 'EXP-1', 'Expense', [['5100', 'Office Expense', 200, 0], ['1200', 'Input Tax Receivable', 24, 0], ['1000', 'Business Bank', 0, 224]]),
            [...$this->journal('JE-2026-0004', '2026-08-11', 'DRAFT', 'Manual', [['5100', 'Office Expense', 999, 0], ['1000', 'Business Bank', 0, 999]]), 'status' => 'Draft'],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['customers'], json_encode([['id' => 1, 'code' => 'CUST-001', 'name' => 'Demo Customer', 'status' => 'Active']], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['invoices'], json_encode([['id' => 1, 'invoice_number' => 'INV-1', 'customer_id' => 1, 'customer_code' => 'CUST-001', 'customer_name' => 'Demo Customer', 'invoice_date' => '2026-08-01', 'due_date' => '2026-08-31', 'subtotal' => 500, 'tax' => 60, 'discount' => 0, 'total' => 560, 'status' => 'Posted', 'journal_entry_id' => 'JE-2026-0001']], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['expenses'], json_encode([['id' => 1, 'expense_number' => 'EXP-1', 'date' => '2026-08-10', 'payee' => 'Office Store', 'category_account_code' => '5100', 'category_name' => 'Office Expense', 'subtotal' => 200, 'tax' => 24, 'total' => 224, 'status' => 'Approved', 'journal_entry_id' => 'JE-2026-0003']], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['tax_codes'], json_encode([['id' => 1, 'code' => 'VAT-STD', 'name' => 'Standard VAT', 'rate' => 12, 'type' => 'VAT', 'applies_to' => 'Goods & Services', 'is_default' => true, 'status' => 'Active']], JSON_THROW_ON_ERROR));

        config()->set('accounting.accounts_path', $this->paths['accounts']);
        config()->set('accounting.journal_entries_path', $this->paths['journals']);
        config()->set('accounting.customers_path', $this->paths['customers']);
        config()->set('accounting.invoices_path', $this->paths['invoices']);
        config()->set('accounting.customer_payments_path', $this->paths['payments']);
        config()->set('accounting.expenses_path', $this->paths['expenses']);
        config()->set('accounting.tax_codes_path', $this->paths['tax_codes']);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_page_requires_login_and_exposes_all_functional_reports(): void
    {
        $this->get('/financial-reports')->assertRedirect('/login');
        $this->withSession($this->demoSession())->get('/financial-reports')->assertOk()
            ->assertSee('Financial Reports')->assertSee('Trial Balance')->assertSee('Income Statement')
            ->assertSee('Balance Sheet')->assertSee('Cash Flow Summary')->assertSee('Sales Report')
            ->assertSee('Expense Report')->assertSee('Tax Summary')->assertSee('data-print-page', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_trial_balance_and_financial_statements_are_calculated_from_posted_journals(): void
    {
        $trial = $this->report('trial-balance')->assertOk()->assertJsonPath('report.balanced', true);
        $this->assertSame(1560.0, (float) $trial->json('report.totals.debit'));
        $this->assertSame(1560.0, (float) $trial->json('report.totals.credit'));

        $income = $this->report('income-statement')->assertOk();
        $this->assertSame(500.0, (float) $income->json('report.kpis.revenue'));
        $this->assertSame(200.0, (float) $income->json('report.kpis.expenses'));
        $this->assertSame(300.0, (float) $income->json('report.kpis.net_income'));

        $balance = $this->report('balance-sheet')->assertOk()->assertJsonPath('report.balanced', true);
        $this->assertSame(1360.0, (float) $balance->json('report.kpis.assets'));
        $this->assertSame(1360.0, (float) $balance->json('report.totals.secondary_amount'));
    }

    public function test_cash_sales_expense_and_tax_reports_return_drill_down_data(): void
    {
        $cash = $this->report('cash-flow')->assertOk();
        $this->assertSame(336.0, (float) $cash->json('report.kpis.net_change'));
        $this->assertStringContainsString('/journal-entries', (string) $cash->json('report.rows.0.link'));

        $this->report('sales-report')->assertOk()->assertJsonPath('report.totals.total', 560)->assertJsonCount(1, 'report.rows');
        $this->report('expense-report')->assertOk()->assertJsonPath('report.totals.total', 224)->assertJsonCount(1, 'report.rows');
        $tax = $this->report('tax-summary')->assertOk();
        $this->assertSame(60.0, (float) $tax->json('report.kpis.output_tax'));
        $this->assertSame(24.0, (float) $tax->json('report.kpis.input_tax'));
        $this->assertSame(36.0, (float) $tax->json('report.kpis.net_tax'));
    }

    public function test_report_filters_validation_empty_states_and_csv_export_work(): void
    {
        $this->withSession($this->demoSession())->getJson('/financial-reports/data?report=income-statement&date_from=2026-09-01&date_to=2026-09-30')->assertOk()->assertJsonPath('report.record_count', 0);
        $this->withSession($this->demoSession())->getJson('/financial-reports/data?report=invalid')->assertUnprocessable();
        $this->withSession($this->demoSession())->getJson('/financial-reports/data?report=trial-balance&date_from=2026-09-10&date_to=2026-09-01')->assertUnprocessable();
        $this->withSession($this->demoSession())->get('/financial-reports/export/csv?report=trial-balance&date_from=2026-01-01&date_to=2026-08-13')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function report(string $report)
    {
        return $this->withSession($this->demoSession())->getJson("/financial-reports/data?report={$report}&date_from=2026-01-01&date_to=2026-08-13");
    }

    private function account(string $code, string $name, string $type, string $subType, float $balance): array
    {
        return compact('code', 'name', 'type', 'balance') + ['sub_type' => $subType, 'status' => 'Active'];
    }

    /** @param array<int, array{0: string, 1: string, 2: float|int, 3: float|int}> $lines */
    private function journal(string $number, string $date, string $reference, string $source, array $lines): array
    {
        $mapped = array_map(fn (array $line, int $index): array => ['id' => $index + 1, 'account_code' => $line[0], 'account_name' => $line[1], 'description' => '', 'party_reference' => '', 'cost_center' => '', 'debit' => $line[2], 'credit' => $line[3]], $lines, array_keys($lines));
        $total = array_sum(array_column($mapped, 'debit'));

        return ['journal_number' => $number, 'date' => $date, 'reference' => $reference, 'description' => $reference, 'source_type' => $source, 'status' => 'Posted', 'lines' => $mapped, 'total_debit' => $total, 'total_credit' => $total];
    }

    private function demoSession(): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.test', 'role' => 'Administrator']];
    }
}
