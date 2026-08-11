<?php

namespace Tests\Feature;

use Tests\TestCase;

class SalesRevenueTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);
        foreach (['accounts', 'journals', 'audit', 'customers', 'invoices'] as $resource) {
            $this->paths[$resource] = storage_path("framework/testing/sales-{$resource}-{$suffix}.json");
            file_put_contents($this->paths[$resource], '[]');
        }
        file_put_contents($this->paths['accounts'], json_encode([
            $this->account('1100', 'Accounts Receivable', 'Asset'),
            $this->account('4000', 'Sales Revenue', 'Revenue'),
        ], JSON_THROW_ON_ERROR));

        config()->set('accounting.accounts_path', $this->paths['accounts']);
        config()->set('accounting.journal_entries_path', $this->paths['journals']);
        config()->set('accounting.audit_logs_path', $this->paths['audit']);
        config()->set('accounting.customers_path', $this->paths['customers']);
        config()->set('accounting.invoices_path', $this->paths['invoices']);
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

    public function test_sales_page_requires_login_and_shows_real_empty_state(): void
    {
        $this->get('/sales-revenue')->assertRedirect('/login');
        $this->withSession($this->demoSession())->get('/sales-revenue')
            ->assertOk()
            ->assertSee('Sales and Revenue')
            ->assertSee('No invoices yet')
            ->assertSee('No posted sales data')
            ->assertSee('aria-current="page"', false);
    }

    public function test_customer_and_invoice_can_be_created_and_posted_to_journal(): void
    {
        $customer = $this->withSession($this->demoSession())->postJson('/sales-revenue/customers', [
            'code' => 'cus-001',
            'name' => 'Example Customer',
            'contact_person' => 'Alex Cruz',
            'email' => 'alex@example.test',
            'phone' => '09170000000',
            'billing_address' => 'Manila',
            'tax_id' => '',
            'credit_terms_days' => 30,
            'opening_balance' => 0,
        ])->assertCreated()
            ->assertJsonPath('customer.code', 'CUS-001')
            ->json('customer');

        $invoice = $this->withSession($this->demoSession())->postJson('/sales-revenue/invoices', [
            'customer_id' => $customer['id'],
            'invoice_date' => '2026-08-11',
            'due_date' => '2026-09-10',
            'reference' => 'PO-100',
            'memo' => 'Consulting services',
            'lines' => [[
                'description' => 'Consulting services',
                'quantity' => 2,
                'unit_price' => 500,
                'tax_rate' => 0,
            ]],
        ])->assertCreated()
            ->assertJsonPath('invoice.invoice_number', 'INV-2026-0001')
            ->assertJsonPath('invoice.total', 1000)
            ->assertJsonPath('invoice.status', 'Draft')
            ->json('invoice');

        $posted = $this->withSession($this->demoSession())->postJson("/sales-revenue/invoices/{$invoice['invoice_number']}/post")
            ->assertOk()
            ->assertJsonPath('invoice.status', 'Posted')
            ->assertJsonPath('journal.reference', 'INV-2026-0001')
            ->assertJsonPath('journal.status', 'Posted')
            ->json();

        $this->assertSame($posted['journal']['journal_number'], $posted['invoice']['journal_entry_id']);
        $accounts = json_decode(file_get_contents($this->paths['accounts']), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1000, $accounts[0]['balance']);
        $this->assertSame(1000, $accounts[1]['balance']);
        $this->withSession($this->demoSession())->postJson("/sales-revenue/invoices/{$invoice['invoice_number']}/post")
            ->assertStatus(409);

        $this->withSession($this->demoSession())->get("/sales-revenue/invoices/{$invoice['invoice_number']}/print")
            ->assertOk()
            ->assertSee('Example Customer')
            ->assertSee('INV-2026-0001');
    }

    public function test_sales_validation_and_roles_are_enforced(): void
    {
        $this->withSession($this->demoSession('Viewer / Auditor'))->postJson('/sales-revenue/customers', [])->assertForbidden();

        $this->withSession($this->demoSession())->postJson('/sales-revenue/invoices', [
            'customer_id' => 999,
            'invoice_date' => '2026-08-11',
            'due_date' => '2026-08-10',
            'lines' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date', 'lines']);
    }

    /** @return array<string, mixed> */
    private function account(string $code, string $name, string $type): array
    {
        return ['code' => $code, 'name' => $name, 'type' => $type, 'sub_type' => '', 'balance' => 0, 'status' => 'Active'];
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(string $role = 'Administrator'): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com', 'role' => $role]];
    }
}
