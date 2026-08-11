<?php

namespace Tests\Feature;

use Tests\TestCase;

class GeneralLedgerTest extends TestCase
{
    private string $accountsPath;

    private string $journalsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);
        $this->accountsPath = storage_path("framework/testing/ledger-accounts-{$suffix}.json");
        $this->journalsPath = storage_path("framework/testing/ledger-journals-{$suffix}.json");

        file_put_contents($this->accountsPath, json_encode([
            ['code' => '1000', 'name' => 'Cash on Hand', 'type' => 'Asset', 'sub_type' => 'Current Asset', 'balance' => 1300, 'status' => 'Active'],
            ['code' => '4000', 'name' => 'Service Revenue', 'type' => 'Revenue', 'sub_type' => 'Operating Revenue', 'balance' => 0, 'status' => 'Active'],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->journalsPath, json_encode([
            $this->entry('JE-2026-0001', '2026-01-15', 'Posted', 500, 0),
            $this->entry('JE-2026-0002', '2026-02-10', 'Posted', 0, 200),
            $this->entry('JE-2026-0003', '2026-03-01', 'Reversed', 100, 0),
            $this->entry('JE-2026-0004', '2026-03-02', 'Posted', 0, 100),
            $this->entry('JE-2026-0005', '2026-04-01', 'Draft', 999, 0),
            $this->entry('JE-2026-0006', '2026-04-02', 'For Review', 999, 0),
        ], JSON_THROW_ON_ERROR));

        config()->set('accounting.accounts_path', $this->accountsPath);
        config()->set('accounting.journal_entries_path', $this->journalsPath);
    }

    protected function tearDown(): void
    {
        foreach ([$this->accountsPath, $this->journalsPath] as $path) {
            if (isset($path) && is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_ledger_page_requires_login_and_reuses_accounting_shell(): void
    {
        $this->get('/general-ledger')->assertRedirect('/login');

        $this->withSession($this->demoSession())->get('/general-ledger?account=1000')
            ->assertOk()
            ->assertSee('General Ledger')
            ->assertSee('Cash on Hand')
            ->assertSee('Running Balance')
            ->assertSee(route('general-ledger.data'), false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_ledger_uses_only_posted_history_and_calculates_running_balances(): void
    {
        $response = $this->withSession($this->demoSession())->getJson('/general-ledger/data?account=1000');

        $response->assertOk()
            ->assertJsonPath('data.beginning_balance', 1000)
            ->assertJsonPath('data.ending_balance', 1300)
            ->assertJsonCount(4, 'data.rows')
            ->assertJsonPath('data.rows.0.running_balance', 1500)
            ->assertJsonPath('data.rows.1.running_balance', 1300)
            ->assertJsonPath('data.rows.2.status', 'Reversed')
            ->assertJsonPath('data.rows.3.running_balance', 1300);

        $numbers = array_column($response->json('data.rows'), 'journal_number');
        $this->assertNotContains('JE-2026-0005', $numbers);
        $this->assertNotContains('JE-2026-0006', $numbers);
    }

    public function test_date_range_search_and_csv_preserve_ledger_context(): void
    {
        $this->withSession($this->demoSession())->getJson('/general-ledger/data?account=1000&date_from=2026-02-01&date_to=2026-02-28')
            ->assertOk()
            ->assertJsonPath('data.beginning_balance', 1500)
            ->assertJsonPath('data.ending_balance', 1300)
            ->assertJsonCount(1, 'data.rows');

        $csv = $this->withSession($this->demoSession())
            ->get('/general-ledger/export/csv?account=1000&date_from=2026-02-01&date_to=2026-02-28')
            ->streamedContent();

        $this->assertStringContainsString('General Ledger', $csv);
        $this->assertStringContainsString('1000 - Cash on Hand', $csv);
        $this->assertStringContainsString('JE-2026-0002', $csv);
        $this->assertStringNotContainsString('JE-2026-0001', $csv);
        $this->assertStringContainsString('Running Balance', $csv);

        $pdf = $this->withSession($this->demoSession())
            ->get('/general-ledger/export/pdf?account=1000&date_from=2026-02-01&date_to=2026-02-28');
        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('general-ledger-1000-'.now()->format('Y-m-d').'.pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    /** @return array<string, mixed> */
    private function entry(string $number, string $date, string $status, float $debit, float $credit): array
    {
        return [
            'journal_number' => $number,
            'date' => $date,
            'reference' => 'REF-'.$number,
            'description' => 'Ledger transaction '.$number,
            'source_type' => 'Manual',
            'status' => $status,
            'lines' => [[
                'id' => 1,
                'account_code' => '1000',
                'account_name' => 'Cash on Hand',
                'description' => 'Cash movement',
                'party_reference' => '',
                'cost_center' => '',
                'debit' => $debit,
                'credit' => $credit,
            ]],
            'total_debit' => $debit,
            'total_credit' => $credit,
        ];
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com', 'role' => 'Administrator']];
    }
}
