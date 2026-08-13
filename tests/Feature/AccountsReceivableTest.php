<?php

namespace Tests\Feature;

use App\Services\Accounting\AccountingPostingService;
use App\Services\DemoData\SalesDataService;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountsReceivableTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);
        foreach (['accounts', 'journals', 'audit', 'customers', 'invoices', 'payments'] as $resource) {
            $this->paths[$resource] = storage_path("framework/testing/ar-{$resource}-{$suffix}.json");
            file_put_contents($this->paths[$resource], '[]');
        }

        file_put_contents($this->paths['accounts'], json_encode([
            $this->account('1000', 'Cash on Hand', 'Asset', 'Cash', 0),
            $this->account('1100', 'Accounts Receivable', 'Asset', 'Current Asset', 1000),
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['customers'], json_encode([[
            'id' => 1,
            'code' => 'CUS-001',
            'name' => 'Example Customer',
            'contact_person' => 'Alex Cruz',
            'email' => 'alex@example.test',
            'phone' => '09170000000',
            'billing_address' => 'Manila',
            'tax_id' => '',
            'credit_terms_days' => 30,
            'opening_balance' => 0,
            'status' => 'Active',
        ]], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['invoices'], json_encode([[
            'id' => 1,
            'invoice_number' => 'INV-2026-0001',
            'customer_id' => 1,
            'customer_code' => 'CUS-001',
            'customer_name' => 'Example Customer',
            'invoice_date' => '2026-08-01',
            'due_date' => '2026-09-01',
            'reference' => 'PO-001',
            'memo' => 'Services',
            'lines' => [],
            'subtotal' => 1000,
            'tax' => 0,
            'discount' => 0,
            'total' => 1000,
            'status' => 'Posted',
            'amount_paid' => 0,
            'journal_entry_id' => 'JE-2026-0001',
        ]], JSON_THROW_ON_ERROR));

        config()->set('accounting.accounts_path', $this->paths['accounts']);
        config()->set('accounting.journal_entries_path', $this->paths['journals']);
        config()->set('accounting.audit_logs_path', $this->paths['audit']);
        config()->set('accounting.customers_path', $this->paths['customers']);
        config()->set('accounting.invoices_path', $this->paths['invoices']);
        config()->set('accounting.customer_payments_path', $this->paths['payments']);
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

    public function test_page_requires_login_and_contains_required_ar_sections(): void
    {
        $this->get('/accounts-receivable')->assertRedirect('/login');

        $this->withSession($this->demoSession())->get('/accounts-receivable')
            ->assertOk()
            ->assertSee('Accounts Receivable')
            ->assertSee('data-ar-tab="invoices"', false)
            ->assertSee('data-ar-tab="customers"', false)
            ->assertSee('data-ar-tab="aging"', false)
            ->assertSee('Record Customer Payment')
            ->assertSee('aria-current="page"', false);
    }

    public function test_partial_payment_posts_journal_updates_balances_and_prevents_duplicates(): void
    {
        $requestToken = (string) Str::uuid();
        $payload = [
            'request_token' => $requestToken,
            'customer_id' => 1,
            'payment_date' => '2026-08-11',
            'cash_account_code' => '1000',
            'reference' => 'DEP-001',
            'memo' => 'Partial collection',
            'allocations' => [['invoice_number' => 'INV-2026-0001', 'amount' => 400]],
            'posting' => [
                'engine' => 'accounting-v1',
                'source_key' => 'customer-payment:'.$requestToken,
                'date' => '2026-08-11',
                'source_type' => 'Payment',
                'lines' => [
                    ['account_code' => '1000', 'debit' => 400, 'credit' => 0],
                    ['account_code' => '1100', 'debit' => 0, 'credit' => 400],
                ],
            ],
        ];

        $created = $this->withSession($this->demoSession())->postJson('/accounts-receivable/payments', $payload)
            ->assertCreated()
            ->assertJsonPath('payment.receipt_number', 'RCP-2026-0001')
            ->assertJsonPath('payment.amount', 400)
            ->assertJsonPath('payment.status', 'Draft');
        $receiptNumber = $created->json('payment.receipt_number');

        $this->assertSame(0.0, (float) app(SalesDataService::class)->findInvoice('INV-2026-0001')['amount_paid']);
        $this->withSession($this->demoSession('Encoder / Staff'))->postJson("/accounts-receivable/payments/{$receiptNumber}/submit-review")
            ->assertOk()->assertJsonPath('payment.status', 'For Review');
        $this->withSession($this->demoSession())->postJson("/accounts-receivable/payments/{$receiptNumber}/post", ['posting' => $payload['posting']])
            ->assertOk()
            ->assertJsonPath('payment.status', 'Posted')
            ->assertJsonPath('journal.status', 'Posted')
            ->assertJsonPath('journal.total_debit', 400)
            ->assertJsonPath('journal.total_credit', 400)
            ->assertJsonPath('journal.source_key', 'customer-payment:'.$requestToken)
            ->assertJsonPath('journal.posting_engine', 'accounting-v1');

        $invoice = app(SalesDataService::class)->findInvoice('INV-2026-0001');
        $this->assertSame(400.0, (float) $invoice['amount_paid']);
        $this->assertSame(600.0, (float) $invoice['remaining_balance']);
        $this->assertSame('Partially Paid', $invoice['display_status']);

        $accounts = json_decode(file_get_contents($this->paths['accounts']), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(400.0, (float) $accounts[0]['balance']);
        $this->assertSame(600.0, (float) $accounts[1]['balance']);

        $this->withSession($this->demoSession())->postJson('/accounts-receivable/payments', $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'This payment request already exists.');
        $this->assertCount(1, json_decode(file_get_contents($this->paths['payments']), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_full_payment_marks_invoice_paid_with_zero_balance(): void
    {
        $created = $this->withSession($this->demoSession())->postJson('/accounts-receivable/payments', [
            'request_token' => (string) Str::uuid(),
            'customer_id' => 1,
            'payment_date' => '2026-08-11',
            'cash_account_code' => '1000',
            'reference' => 'DEP-FULL',
            'allocations' => [['invoice_number' => 'INV-2026-0001', 'amount' => 1000]],
        ])->assertCreated();
        $this->withSession($this->demoSession())->postJson('/accounts-receivable/payments/'.$created->json('payment.receipt_number').'/post')->assertOk();

        $invoice = app(SalesDataService::class)->findInvoice('INV-2026-0001');
        $this->assertSame(1000.0, (float) $invoice['amount_paid']);
        $this->assertSame(0.0, (float) $invoice['remaining_balance']);
        $this->assertSame('Paid', $invoice['display_status']);
    }

    public function test_shared_posting_gate_reuses_source_journal_without_applying_balances_twice(): void
    {
        $posting = app(AccountingPostingService::class);
        $customer = app(SalesDataService::class)->customers()[0];
        $payment = [
            'request_token' => (string) Str::uuid(),
            'payment_date' => '2026-08-11',
            'cash_account_code' => '1000',
            'reference' => 'RECOVERY-TEST',
            'amount' => 200,
        ];
        $actor = $this->demoSession()['demo_user'];

        $first = $posting->postCustomerPayment($customer, $payment, $actor);
        $second = $posting->postCustomerPayment($customer, $payment, $actor);

        $this->assertSame($first['journal_number'], $second['journal_number']);
        $this->assertCount(1, json_decode(file_get_contents($this->paths['journals']), true, flags: JSON_THROW_ON_ERROR));
        $accounts = json_decode(file_get_contents($this->paths['accounts']), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(200.0, (float) $accounts[0]['balance']);
        $this->assertSame(800.0, (float) $accounts[1]['balance']);
    }

    public function test_payment_review_return_and_posted_immutability_are_enforced(): void
    {
        $payload = [
            'request_token' => (string) Str::uuid(),
            'customer_id' => 1,
            'payment_date' => '2026-08-11',
            'cash_account_code' => '1000',
            'reference' => 'WORKFLOW-1',
            'allocations' => [['invoice_number' => 'INV-2026-0001', 'amount' => 250]],
        ];
        $created = $this->withSession($this->demoSession('Encoder / Staff'))->postJson('/accounts-receivable/payments', $payload)
            ->assertCreated()->assertJsonPath('payment.status', 'Draft');
        $number = $created->json('payment.receipt_number');

        $this->withSession($this->demoSession('Encoder / Staff'))->postJson("/accounts-receivable/payments/{$number}/submit-review")
            ->assertOk()->assertJsonPath('payment.status', 'For Review');
        $this->withSession($this->demoSession())->postJson("/accounts-receivable/payments/{$number}/return-draft")
            ->assertOk()->assertJsonPath('payment.status', 'Draft');
        $this->withSession($this->demoSession('Encoder / Staff'))->putJson("/accounts-receivable/payments/{$number}", [...$payload, 'memo' => 'Edited draft'])
            ->assertOk()->assertJsonPath('payment.memo', 'Edited draft');
        $this->withSession($this->demoSession())->postJson("/accounts-receivable/payments/{$number}/post")
            ->assertOk()->assertJsonPath('payment.status', 'Posted');

        $this->withSession($this->demoSession())->putJson("/accounts-receivable/payments/{$number}", $payload)->assertStatus(409);
        $this->withSession($this->demoSession())->deleteJson("/accounts-receivable/payments/{$number}")->assertStatus(409);
        $this->withSession($this->demoSession())->postJson("/accounts-receivable/payments/{$number}/post")->assertStatus(409);
    }

    public function test_posting_rejects_stale_draft_allocations_without_creating_an_extra_journal(): void
    {
        $first = $this->withSession($this->demoSession())->postJson('/accounts-receivable/payments', [
            'request_token' => (string) Str::uuid(), 'customer_id' => 1, 'payment_date' => '2026-08-11', 'cash_account_code' => '1000',
            'allocations' => [['invoice_number' => 'INV-2026-0001', 'amount' => 600]],
        ])->assertCreated()->json('payment.receipt_number');
        $second = $this->withSession($this->demoSession())->postJson('/accounts-receivable/payments', [
            'request_token' => (string) Str::uuid(), 'customer_id' => 1, 'payment_date' => '2026-08-11', 'cash_account_code' => '1000',
            'allocations' => [['invoice_number' => 'INV-2026-0001', 'amount' => 500]],
        ])->assertCreated()->json('payment.receipt_number');

        $this->withSession($this->demoSession())->postJson("/accounts-receivable/payments/{$second}/post")->assertOk();
        $before = count(json_decode(file_get_contents($this->paths['journals']), true, flags: JSON_THROW_ON_ERROR));
        $response = $this->withSession($this->demoSession())->postJson("/accounts-receivable/payments/{$first}/post")->assertUnprocessable();
        $this->assertSame('Payment cannot exceed invoice remaining balance.', $response->json('errors')['allocations.0.amount'][0]);
        $this->assertCount($before, json_decode(file_get_contents($this->paths['journals']), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_overpayment_and_roles_are_blocked_and_csv_exports_current_balance(): void
    {
        $payload = [
            'request_token' => (string) Str::uuid(),
            'customer_id' => 1,
            'payment_date' => '2026-08-11',
            'cash_account_code' => '1000',
            'allocations' => [['invoice_number' => 'INV-2026-0001', 'amount' => 1001]],
        ];

        $response = $this->withSession($this->demoSession())->postJson('/accounts-receivable/payments', $payload)
            ->assertUnprocessable();
        $this->assertSame(
            'Payment cannot exceed invoice remaining balance.',
            $response->json('errors')['allocations.0.amount'][0],
        );
        $this->withSession($this->demoSession('Encoder / Staff'))->postJson('/accounts-receivable/payments', $payload)->assertUnprocessable();
        $this->withSession($this->demoSession('Viewer / Auditor'))->postJson('/accounts-receivable/payments', $payload)->assertForbidden();

        $csv = $this->withSession($this->demoSession())->get('/accounts-receivable/export/csv')->streamedContent();
        $this->assertStringContainsString('INV-2026-0001', $csv);
        $this->assertStringContainsString('1000.00', $csv);
    }

    /** @return array<string, mixed> */
    private function account(string $code, string $name, string $type, string $subType, float $balance): array
    {
        return ['code' => $code, 'name' => $name, 'type' => $type, 'sub_type' => $subType, 'balance' => $balance, 'status' => 'Active'];
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(string $role = 'Administrator'): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.test', 'role' => $role]];
    }
}
