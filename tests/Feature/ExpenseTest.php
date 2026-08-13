<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = uniqid('', true);
        foreach (['accounts', 'journals', 'audit', 'expenses', 'transactions'] as $name) {
            $this->paths[$name] = storage_path("framework/testing/expense-{$name}-{$suffix}.json");
            file_put_contents($this->paths[$name], '[]');
        }
        file_put_contents($this->paths['accounts'], json_encode([
            $this->account('1000', 'Business Bank Account', 'Asset', 'Bank', 10000),
            $this->account('2000', 'Accounts Payable', 'Liability', 'Current Liability', 0),
            $this->account('5100', 'Office Supplies Expense', 'Expense', 'Operating Expense', 0),
            $this->account('1200', 'Input Tax Receivable', 'Asset', 'Current Asset', 0),
        ], JSON_THROW_ON_ERROR));
        config()->set('accounting.accounts_path', $this->paths['accounts']);
        config()->set('accounting.journal_entries_path', $this->paths['journals']);
        config()->set('accounting.audit_logs_path', $this->paths['audit']);
        config()->set('accounting.expenses_path', $this->paths['expenses']);
        config()->set('accounting.bank_transactions_path', $this->paths['transactions']);
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

    public function test_page_requires_login_and_contains_functional_expense_controls(): void
    {
        $this->get('/expenses')->assertRedirect('/login');
        $this->withSession($this->demoSession())->get('/expenses')->assertOk()
            ->assertSee('Expense Records')->assertSee('id="expense-form"', false)
            ->assertSee('data-print-page', false)->assertSee('aria-current="page"', false);
    }

    public function test_paid_expense_review_and_approval_post_one_balanced_journal(): void
    {
        $payload = $this->payload();
        $created = $this->withSession($this->demoSession('Encoder / Staff'))->postJson('/expenses', $payload)->assertCreated()->assertJsonPath('expense.status', 'For Review');
        $number = $created->json('expense.expense_number');

        $this->withSession($this->demoSession('Encoder / Staff'))->postJson("/expenses/{$number}/approve")->assertForbidden();
        $this->withSession($this->demoSession())->postJson("/expenses/{$number}/approve")->assertOk()
            ->assertJsonPath('expense.status', 'Approved')->assertJsonPath('journal.total_debit', 1120)->assertJsonPath('journal.total_credit', 1120);

        $journals = json_decode(file_get_contents($this->paths['journals']), true, flags: JSON_THROW_ON_ERROR);
        $accounts = collect(json_decode(file_get_contents($this->paths['accounts']), true, flags: JSON_THROW_ON_ERROR))->keyBy('code');
        $this->assertCount(1, $journals);
        $this->assertSame(1000.0, (float) $accounts['5100']['balance']);
        $this->assertSame(120.0, (float) $accounts['1200']['balance']);
        $this->assertSame(8880.0, (float) $accounts['1000']['balance']);
        $this->assertCount(1, json_decode(file_get_contents($this->paths['transactions']), true, flags: JSON_THROW_ON_ERROR));
        $this->withSession($this->demoSession())->postJson("/expenses/{$number}/approve")->assertStatus(409);
        $this->assertCount(1, json_decode(file_get_contents($this->paths['journals']), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_unpaid_expense_credits_payable_and_drafts_can_be_updated_and_deleted(): void
    {
        $draftPayload = [...$this->payload(), 'request_token' => (string) Str::uuid(), 'action' => 'draft', 'payment_status' => 'Unpaid', 'cash_account_code' => null, 'tax_rate' => 0];
        $created = $this->withSession($this->demoSession())->postJson('/expenses', $draftPayload)->assertCreated()->assertJsonPath('expense.status', 'Draft');
        $number = $created->json('expense.expense_number');
        $this->withSession($this->demoSession())->putJson("/expenses/{$number}", [...$draftPayload, 'payee' => 'Updated Payee'])->assertOk()->assertJsonPath('expense.payee', 'Updated Payee');
        $this->withSession($this->demoSession())->deleteJson("/expenses/{$number}")->assertOk();

        $review = $this->withSession($this->demoSession())->postJson('/expenses', [...$draftPayload, 'request_token' => (string) Str::uuid(), 'action' => 'review'])->assertCreated();
        $this->withSession($this->demoSession())->postJson('/expenses/'.$review->json('expense.expense_number').'/approve')->assertOk();
        $accounts = collect(json_decode(file_get_contents($this->paths['accounts']), true, flags: JSON_THROW_ON_ERROR))->keyBy('code');
        $this->assertSame(1000.0, (float) $accounts['5100']['balance']);
        $this->assertSame(1000.0, (float) $accounts['2000']['balance']);
    }

    public function test_validation_roles_and_csv_export_are_enforced(): void
    {
        $this->withSession($this->demoSession('Viewer / Auditor'))->postJson('/expenses', $this->payload())->assertForbidden();
        $this->withSession($this->demoSession())->postJson('/expenses', [...$this->payload(), 'amount' => -1])->assertUnprocessable();
        $this->withSession($this->demoSession())->get('/expenses/export/csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function payload(): array
    {
        return ['request_token' => (string) Str::uuid(), 'action' => 'review', 'date' => '2026-08-13', 'payee' => 'Office Warehouse', 'category_account_code' => '5100', 'amount' => 1000, 'tax_rate' => 12, 'payment_status' => 'Paid', 'payment_method' => 'Bank Transfer', 'cash_account_code' => '1000', 'memo' => 'Office supplies', 'receipt' => ['name' => 'receipt.pdf', 'type' => 'application/pdf', 'size' => 1024]];
    }

    private function account(string $code, string $name, string $type, string $subType, float $balance): array
    {
        return compact('code', 'name', 'type', 'balance') + ['sub_type' => $subType, 'status' => 'Active'];
    }

    private function demoSession(string $role = 'Administrator'): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.test', 'role' => $role]];
    }
}
