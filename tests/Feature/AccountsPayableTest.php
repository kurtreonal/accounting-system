<?php

namespace Tests\Feature;

use App\Services\DemoData\PurchaseDataService;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountsPayableTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);
        foreach (['accounts', 'journals', 'audit', 'vendors', 'bills', 'payments'] as $resource) {
            $this->paths[$resource] = storage_path("framework/testing/ap-{$resource}-{$suffix}.json");
            file_put_contents($this->paths[$resource], '[]');
        }

        file_put_contents($this->paths['accounts'], json_encode([
            $this->account('1000', 'Business Checking Account', 'Asset', 'Bank', 5000),
            $this->account('2000', 'Accounts Payable', 'Liability', 'Current Liability', 0),
            $this->account('5100', 'Office Supplies Expense', 'Expense', 'Operating Expense', 0),
            $this->account('1200', 'Computer Equipment', 'Asset', 'Fixed Asset', 0),
            $this->account('1300', 'Input Tax Receivable', 'Asset', 'Current Asset', 0),
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['vendors'], json_encode([[
            'id' => 1,
            'code' => 'VEN-001',
            'name' => 'Example Vendor',
            'contact_person' => 'Ana Santos',
            'email' => 'vendor@example.test',
            'phone' => '09170000000',
            'address' => 'Manila',
            'tax_id' => '',
            'payment_terms_days' => 30,
            'opening_balance' => 0,
            'status' => 'Active',
        ]], JSON_THROW_ON_ERROR));

        config()->set('accounting.accounts_path', $this->paths['accounts']);
        config()->set('accounting.journal_entries_path', $this->paths['journals']);
        config()->set('accounting.audit_logs_path', $this->paths['audit']);
        config()->set('accounting.vendors_path', $this->paths['vendors']);
        config()->set('accounting.bills_path', $this->paths['bills']);
        config()->set('accounting.vendor_payments_path', $this->paths['payments']);
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

    public function test_page_requires_login_and_contains_required_ap_sections(): void
    {
        $this->get('/accounts-payable')->assertRedirect('/login');

        $response = $this->withSession($this->demoSession())->get('/accounts-payable')
            ->assertOk()
            ->assertSee('Accounts Payable')
            ->assertSee('data-ap-tab="bills"', false)
            ->assertSee('data-ap-tab="vendors"', false)
            ->assertSee('data-ap-tab="aging"', false)
            ->assertSee('Export PDF')
            ->assertSee('data-print-page', false)
            ->assertDontSee('JSON.parse(', false)
            ->assertSee('Record Vendor Payment')
            ->assertSee('aria-current="page"', false);

        $this->assertSame(1, preg_match('/<script id="ap-data" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $match));
        $pageData = json_decode($match[1], true);
        $this->assertIsArray($pageData, json_last_error_msg().' | '.substr($match[1], 0, 160));
        $this->assertSame([], $pageData['bills']);
        $this->assertSame('Example Vendor', $pageData['vendors'][0]['name']);
    }

    public function test_vendor_bill_posts_balanced_journal_and_updates_accounts_once(): void
    {
        $this->withSession($this->demoSession())->postJson('/accounts-payable/vendors', [
            'code' => 'VEN-002',
            'name' => 'Second Vendor',
            'contact_person' => '',
            'email' => 'second@example.test',
            'phone' => '',
            'address' => 'Makati',
            'tax_id' => '',
            'payment_terms_days' => 15,
            'opening_balance' => 0,
        ])->assertCreated()->assertJsonPath('vendor.code', 'VEN-002');

        $billResponse = $this->withSession($this->demoSession())->postJson('/accounts-payable/bills', [
            'vendor_id' => 1,
            'reference' => 'SUP-1001',
            'bill_date' => '2026-08-01',
            'due_date' => '2026-08-31',
            'memo' => 'Office supplies',
            'attachment' => ['name' => 'invoice.pdf', 'type' => 'application/pdf', 'size' => 1000],
            'lines' => [[
                'account_code' => '5100',
                'description' => 'Paper and ink',
                'quantity' => 2,
                'unit_price' => 500,
                'tax_rate' => 12,
            ]],
        ])->assertCreated()->assertJsonPath('bill.total', 1120);
        $billNumber = $billResponse->json('bill.bill_number');

        $this->withSession($this->demoSession())->postJson("/accounts-payable/bills/{$billNumber}/post", ['posting' => [
            'engine' => 'accounting-v1',
            'source_key' => 'bill:'.$billNumber,
            'date' => '2026-08-01',
            'source_type' => 'Bill',
            'lines' => [
                ['account_code' => '5100', 'debit' => 1000, 'credit' => 0],
                ['account_code' => '1300', 'debit' => 120, 'credit' => 0],
                ['account_code' => '2000', 'debit' => 0, 'credit' => 1120],
            ],
        ]])
            ->assertOk()
            ->assertJsonPath('bill.status', 'Posted')
            ->assertJsonPath('journal.status', 'Posted')
            ->assertJsonPath('journal.total_debit', 1120)
            ->assertJsonPath('journal.total_credit', 1120);

        $accounts = collect(json_decode(file_get_contents($this->paths['accounts']), true, flags: JSON_THROW_ON_ERROR))->keyBy('code');
        $this->assertSame(1120.0, (float) $accounts['2000']['balance']);
        $this->assertSame(1000.0, (float) $accounts['5100']['balance']);
        $this->assertSame(120.0, (float) $accounts['1300']['balance']);

        $this->withSession($this->demoSession())->postJson("/accounts-payable/bills/{$billNumber}/post")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only draft vendor bills can be posted.');
        $this->assertCount(1, json_decode(file_get_contents($this->paths['journals']), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_partial_payment_updates_bill_and_accounts_and_blocks_duplicates_and_overpayment(): void
    {
        $billNumber = $this->createAndPostBill(1000);
        $requestToken = (string) Str::uuid();
        $payload = [
            'request_token' => $requestToken,
            'vendor_id' => 1,
            'payment_date' => '2026-08-11',
            'cash_account_code' => '1000',
            'reference' => 'CHK-001',
            'memo' => 'Partial payment',
            'allocations' => [['bill_number' => $billNumber, 'amount' => 400]],
            'posting' => [
                'engine' => 'accounting-v1',
                'source_key' => 'vendor-payment:'.$requestToken,
                'date' => '2026-08-11',
                'source_type' => 'Vendor Payment',
                'lines' => [
                    ['account_code' => '2000', 'debit' => 400, 'credit' => 0],
                    ['account_code' => '1000', 'debit' => 0, 'credit' => 400],
                ],
            ],
        ];

        $this->withSession($this->demoSession())->postJson('/accounts-payable/payments', $payload)
            ->assertCreated()
            ->assertJsonPath('payment.payment_number', 'VPY-2026-0001')
            ->assertJsonPath('payment.amount', 400)
            ->assertJsonPath('journal.status', 'Posted')
            ->assertJsonPath('journal.total_debit', 400)
            ->assertJsonPath('journal.total_credit', 400)
            ->assertJsonPath('journal.source_key', 'vendor-payment:'.$requestToken)
            ->assertJsonPath('journal.posting_engine', 'accounting-v1');

        $bill = app(PurchaseDataService::class)->findBill($billNumber);
        $this->assertSame(400.0, (float) $bill['amount_paid']);
        $this->assertSame(600.0, (float) $bill['remaining_balance']);
        $this->assertSame('Partially Paid', $bill['display_status']);

        $accounts = collect(json_decode(file_get_contents($this->paths['accounts']), true, flags: JSON_THROW_ON_ERROR))->keyBy('code');
        $this->assertSame(600.0, (float) $accounts['2000']['balance']);
        $this->assertSame(4600.0, (float) $accounts['1000']['balance']);

        $this->withSession($this->demoSession())->postJson('/accounts-payable/payments', $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'This vendor payment request was already posted.');
        $this->assertCount(1, json_decode(file_get_contents($this->paths['payments']), true, flags: JSON_THROW_ON_ERROR));

        $payload['request_token'] = (string) Str::uuid();
        $payload['allocations'][0]['amount'] = 601;
        $response = $this->withSession($this->demoSession())->postJson('/accounts-payable/payments', $payload)->assertUnprocessable();
        $this->assertSame('Payment cannot exceed bill remaining balance.', $response->json('errors')['allocations.0.amount'][0]);
    }

    public function test_invalid_dates_duplicate_references_roles_and_csv_are_handled(): void
    {
        $invalid = $this->billPayload(1000);
        $invalid['due_date'] = '2026-07-31';
        $this->withSession($this->demoSession())->postJson('/accounts-payable/bills', $invalid)->assertUnprocessable();

        $valid = $this->billPayload(1000);
        $this->withSession($this->demoSession())->postJson('/accounts-payable/bills', $valid)->assertCreated();
        $this->withSession($this->demoSession())->postJson('/accounts-payable/bills', $valid)
            ->assertStatus(409)
            ->assertJsonPath('message', 'This vendor bill reference already exists.');

        $this->withSession($this->demoSession('Viewer / Auditor'))->postJson('/accounts-payable/vendors', [])->assertForbidden();
        $this->withSession($this->demoSession('Encoder / Staff'))->postJson('/accounts-payable/bills/BILL-2026-0001/post')->assertForbidden();

        $csv = $this->withSession($this->demoSession())->get('/accounts-payable/export/csv')->streamedContent();
        $this->assertStringContainsString('BILL-2026-0001', $csv);
        $this->assertStringContainsString('1000.00', $csv);

        $page = $this->withSession($this->demoSession())->get('/accounts-payable')->assertOk();
        $page->assertSee('BILL-2026-0001')->assertSee('Example Vendor');

        $pdf = $this->withSession($this->demoSession())->get('/accounts-payable/export/pdf?status=Draft');
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_one_vendor_payment_can_allocate_multiple_bills(): void
    {
        $firstBill = $this->createAndPostBill(300, 'SUP-2001');
        $secondBill = $this->createAndPostBill(700, 'SUP-2002');

        $this->withSession($this->demoSession())->postJson('/accounts-payable/payments', [
            'request_token' => (string) Str::uuid(),
            'vendor_id' => 1,
            'payment_date' => '2026-08-12',
            'cash_account_code' => '1000',
            'reference' => 'CHK-002',
            'allocations' => [
                ['bill_number' => $firstBill, 'amount' => 250],
                ['bill_number' => $secondBill, 'amount' => 500],
            ],
        ])->assertCreated()
            ->assertJsonPath('payment.amount', 750)
            ->assertJsonCount(2, 'payment.allocations');

        $this->assertSame(50.0, (float) app(PurchaseDataService::class)->findBill($firstBill)['remaining_balance']);
        $this->assertSame(200.0, (float) app(PurchaseDataService::class)->findBill($secondBill)['remaining_balance']);
    }

    private function createAndPostBill(float $total, string $reference = 'SUP-1001'): string
    {
        $payload = $this->billPayload($total);
        $payload['reference'] = $reference;
        $response = $this->withSession($this->demoSession())->postJson('/accounts-payable/bills', $payload)->assertCreated();
        $billNumber = $response->json('bill.bill_number');
        $this->withSession($this->demoSession())->postJson("/accounts-payable/bills/{$billNumber}/post")->assertOk();

        return $billNumber;
    }

    /** @return array<string, mixed> */
    private function billPayload(float $total): array
    {
        return [
            'vendor_id' => 1,
            'reference' => 'SUP-1001',
            'bill_date' => '2026-08-01',
            'due_date' => '2026-08-31',
            'memo' => 'Supplies',
            'lines' => [[
                'account_code' => '5100',
                'description' => 'Supplies',
                'quantity' => 1,
                'unit_price' => $total,
                'tax_rate' => 0,
            ]],
        ];
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
