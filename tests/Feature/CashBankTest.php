<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class CashBankTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = uniqid('', true);
        foreach (['accounts', 'journals', 'audit', 'transactions', 'reconciliations'] as $name) {
            $this->paths[$name] = storage_path("framework/testing/cb-{$name}-{$suffix}.json");
            file_put_contents($this->paths[$name], '[]');
        }
        file_put_contents($this->paths['accounts'], json_encode([
            $this->account('1000', 'Business Bank Account', 'Asset', 'Bank', 5000),
            $this->account('1010', 'Cash on Hand', 'Asset', 'Cash', 500),
            $this->account('3000', 'Owner Capital', 'Equity', 'Equity', 5000),
            $this->account('6100', 'Bank Charges Expense', 'Expense', 'Expense', 0),
        ], JSON_THROW_ON_ERROR));
        config()->set('accounting.accounts_path', $this->paths['accounts']);
        config()->set('accounting.journal_entries_path', $this->paths['journals']);
        config()->set('accounting.audit_logs_path', $this->paths['audit']);
        config()->set('accounting.bank_transactions_path', $this->paths['transactions']);
        config()->set('accounting.bank_reconciliations_path', $this->paths['reconciliations']);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) if (is_file($path)) unlink($path);
        parent::tearDown();
    }

    public function test_page_requires_login_and_contains_all_cash_bank_sections(): void
    {
        $this->get('/cash-bank')->assertRedirect('/login');
        $this->withSession($this->demoSession())->get('/cash-bank')->assertOk()
            ->assertSee('Cash &amp; Bank', false)
            ->assertSee('&#8369;5,500.00', false)
            ->assertSee('data-cb-tab="accounts"', false)
            ->assertSee('data-cb-tab="transactions"', false)
            ->assertSee('data-cb-tab="reconciliation"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_deposit_posts_balanced_journal_updates_balance_once_and_records_transaction(): void
    {
        $token = (string) Str::uuid();
        $payload = ['request_token' => $token, 'type' => 'deposit', 'date' => '2026-08-13', 'amount' => 1000, 'account_code' => '1000', 'offset_account_code' => '3000', 'reference' => 'DEP-1', 'description' => 'Capital deposit'];
        $this->withSession($this->demoSession())->postJson('/cash-bank/transactions', $payload)->assertCreated()
            ->assertJsonPath('journal.status', 'Posted')->assertJsonPath('journal.total_debit', 1000)->assertJsonPath('journal.total_credit', 1000);
        $accounts = collect(json_decode(file_get_contents($this->paths['accounts']), true, flags: JSON_THROW_ON_ERROR))->keyBy('code');
        $this->assertSame(6000.0, (float) $accounts['1000']['balance']);
        $this->assertSame(6000.0, (float) $accounts['3000']['balance']);
        $this->assertCount(1, json_decode(file_get_contents($this->paths['transactions']), true, flags: JSON_THROW_ON_ERROR));
        $this->withSession($this->demoSession())->postJson('/cash-bank/transactions', $payload)->assertStatus(409);
        $this->assertCount(1, json_decode(file_get_contents($this->paths['journals']), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_transfer_and_reconciliation_validation_work(): void
    {
        $payload = ['request_token' => (string) Str::uuid(), 'type' => 'transfer', 'date' => '2026-08-13', 'amount' => 200, 'from_account_code' => '1000', 'to_account_code' => '1010', 'reference' => 'TRF-1', 'description' => 'Cash top up'];
        $response = $this->withSession($this->demoSession())->postJson('/cash-bank/transactions', $payload)->assertCreated();
        $accounts = collect(json_decode(file_get_contents($this->paths['accounts']), true, flags: JSON_THROW_ON_ERROR))->keyBy('code');
        $this->assertSame(4800.0, (float) $accounts['1000']['balance']);
        $this->assertSame(700.0, (float) $accounts['1010']['balance']);

        $this->withSession($this->demoSession())->postJson('/cash-bank/reconciliations', ['account_code' => '1000', 'statement_date' => '2026-08-13', 'statement_balance' => 4799, 'transaction_ids' => [$response->json('transaction.id')]])->assertUnprocessable();
        $this->withSession($this->demoSession())->postJson('/cash-bank/reconciliations', ['account_code' => '1000', 'statement_date' => '2026-08-13', 'statement_balance' => 4800, 'transaction_ids' => [$response->json('transaction.id')]])->assertCreated()->assertJsonPath('reconciliation.difference', 0);
    }

    public function test_roles_and_insufficient_funds_are_enforced(): void
    {
        $payload = ['request_token' => (string) Str::uuid(), 'type' => 'withdrawal', 'date' => '2026-08-13', 'amount' => 9999, 'account_code' => '1000', 'offset_account_code' => '6100', 'description' => 'Too large'];
        $this->withSession($this->demoSession())->postJson('/cash-bank/transactions', $payload)->assertUnprocessable();
        $this->withSession($this->demoSession('Viewer / Auditor'))->postJson('/cash-bank/accounts', ['name' => 'New Bank', 'kind' => 'Bank'])->assertForbidden();
        $this->withSession($this->demoSession('Encoder / Staff'))->postJson('/cash-bank/transactions', $payload)->assertForbidden();
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
