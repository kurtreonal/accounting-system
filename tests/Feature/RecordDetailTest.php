<?php

namespace Tests\Feature;

use Tests\TestCase;

class RecordDetailTest extends TestCase
{
    /** @var array<int, string> */
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $invoicePath = $this->fixture('invoices', [
            $this->invoice('INV-2026-0001', 'Posted'),
            $this->invoice('INV-2026-0002', 'Draft'),
        ]);
        config()->set('accounting.invoices_path', $invoicePath);
        config()->set('accounting.customer_payments_path', $this->fixture('payments', []));
        config()->set('accounting.expense_payments_path', $this->fixture('expense-payments', [
            $this->expensePayment('EPY-2026-0001', 'Posted'),
            $this->expensePayment('EPY-2026-0002', 'Draft'),
        ]));
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) unlink($path);
        }
        parent::tearDown();
    }

    public function test_invoice_detail_returns_practical_full_information(): void
    {
        $response = $this->withSession($this->demoSession('Accountant'))->getJson('/record-details/sales_invoice/INV-2026-0001');

        $response->assertOk()
            ->assertJsonPath('record.title', 'Sales Invoice')
            ->assertJsonPath('record.identifier', 'INV-2026-0001')
            ->assertJsonPath('record.status', 'Unpaid')
            ->assertJsonFragment(['label' => 'Customer', 'value' => 'Nexii Client'])
            ->assertJsonFragment(['label' => 'Total', 'value' => '₱3,360.00'])
            ->assertJsonPath('record.sections.0.title', 'Invoice Lines');
    }

    public function test_viewer_cannot_open_draft_record_details(): void
    {
        $this->withSession($this->demoSession('Viewer / Auditor'))
            ->getJson('/record-details/sales_invoice/INV-2026-0002')
            ->assertForbidden();

        $this->flushSession();
        $this->getJson('/record-details/sales_invoice/INV-2026-0001')->assertUnauthorized();
    }

    public function test_expense_payment_detail_is_available_without_page_redirect(): void
    {
        $this->withSession($this->demoSession('Accountant'))->getJson('/record-details/expense_payment/EPY-2026-0001')
            ->assertOk()->assertJsonPath('record.title', 'Expense Payment')
            ->assertJsonPath('record.status', 'Posted')
            ->assertJsonFragment(['label' => 'Expense', 'value' => 'EXP-2026-0002'])
            ->assertJsonFragment(['label' => 'Journal Entry', 'value' => 'JE-2026-0010']);

        $this->withSession($this->demoSession('Viewer / Auditor'))->getJson('/record-details/expense_payment/EPY-2026-0002')->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function invoice(string $number, string $status): array
    {
        return [
            'id' => $number === 'INV-2026-0001' ? 1 : 2, 'invoice_number' => $number,
            'customer_id' => 1, 'customer_code' => 'CUST-001', 'customer_name' => 'Nexii Client',
            'invoice_date' => '2026-08-11', 'due_date' => '2026-09-10', 'reference' => 'PO-001', 'memo' => 'Website service',
            'lines' => [['id' => 1, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 3000, 'tax_rate' => 12, 'subtotal' => 3000, 'tax' => 360, 'total' => 3360]],
            'subtotal' => 3000, 'tax' => 360, 'discount' => 0, 'total' => 3360,
            'created_by' => ['id' => 1, 'name' => 'Admin User'], 'status' => $status,
            'amount_paid' => 0, 'journal_entry_id' => $status === 'Posted' ? 'JE-2026-0001' : null,
            'posted_at' => $status === 'Posted' ? now()->toIso8601String() : null,
            'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function expensePayment(string $number, string $status): array
    {
        return [
            'id' => $number === 'EPY-2026-0001' ? 1 : 2, 'payment_number' => $number,
            'expense_number' => 'EXP-2026-0002', 'payee' => 'Prime Office Supplies',
            'payment_date' => '2026-08-20', 'amount' => 1320, 'payment_method' => 'Bank Transfer',
            'cash_account_code' => '1000', 'reference' => 'PAY-100', 'memo' => 'Full settlement',
            'status' => $status, 'journal_entry_id' => $status === 'Posted' ? 'JE-2026-0010' : null,
            'reversal_journal_entry_id' => null,
        ];
    }

    private function fixture(string $name, array $rows): string
    {
        $path = storage_path('framework/testing/record-detail-'.$name.'-'.uniqid('', true).'.json');
        file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->paths[] = $path;
        return $path;
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(string $role): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.test', 'role' => $role]];
    }
}
