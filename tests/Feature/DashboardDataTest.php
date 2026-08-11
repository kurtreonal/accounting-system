<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardDataTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);
        foreach (['accounts', 'journals', 'customers', 'invoices'] as $resource) {
            $this->paths[$resource] = storage_path("framework/testing/dashboard-{$resource}-{$suffix}.json");
            file_put_contents($this->paths[$resource], '[]');
        }
        file_put_contents($this->paths['accounts'], json_encode([
            ['code' => '1000', 'name' => 'Cash on Hand', 'type' => 'Asset', 'sub_type' => 'Cash', 'balance' => 500, 'status' => 'Active'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'Revenue', 'sub_type' => '', 'balance' => 500, 'status' => 'Active'],
            ['code' => '5000', 'name' => 'Office Expense', 'type' => 'Expense', 'sub_type' => '', 'balance' => 100, 'status' => 'Active'],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['journals'], json_encode([
            $this->entry('JE-'.now()->format('Y').'-0001', now()->toDateString(), 'Posted'),
        ], JSON_THROW_ON_ERROR));

        config()->set('accounting.accounts_path', $this->paths['accounts']);
        config()->set('accounting.journal_entries_path', $this->paths['journals']);
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

    public function test_dashboard_uses_current_json_data_instead_of_old_placeholders(): void
    {
        $this->withSession(['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com', 'role' => 'Administrator']])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('₱500.00')
            ->assertSee('₱400.00')
            ->assertSee('Current JSON journal data')
            ->assertDontSee('6,037,751.25')
            ->assertDontSee('JE-2024-0089');
    }

    /** @return array<string, mixed> */
    private function entry(string $number, string $date, string $status): array
    {
        return [
            'journal_number' => $number,
            'date' => $date,
            'reference' => 'SALE-001',
            'description' => 'Current sales activity',
            'source_type' => 'Invoice',
            'status' => $status,
            'total_debit' => 500,
            'total_credit' => 500,
            'lines' => [
                ['account_code' => '1000', 'account_name' => 'Cash on Hand', 'description' => '', 'debit' => 500, 'credit' => 0],
                ['account_code' => '4000', 'account_name' => 'Sales Revenue', 'description' => '', 'debit' => 0, 'credit' => 500],
                ['account_code' => '5000', 'account_name' => 'Office Expense', 'description' => '', 'debit' => 100, 'credit' => 0],
            ],
        ];
    }
}
