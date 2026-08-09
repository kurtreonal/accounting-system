<?php

namespace Tests\Feature;

use Tests\TestCase;

class ChartOfAccountsManagementTest extends TestCase
{
    private string $accountsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountsPath = storage_path('framework/testing/chart-accounts-'.uniqid('', true).'.json');
        file_put_contents($this->accountsPath, json_encode([$this->sampleAccount()], JSON_THROW_ON_ERROR));
        config()->set('accounting.accounts_path', $this->accountsPath);
    }

    protected function tearDown(): void
    {
        if (isset($this->accountsPath) && is_file($this->accountsPath)) {
            unlink($this->accountsPath);
        }

        parent::tearDown();
    }

    public function test_chart_page_preserves_current_navigation_and_export_features(): void
    {
        $session = $this->demoSession();

        $this->withSession($session)->get('/dashboard')->assertOk();
        $this->withSession($session)->get('/journal-entries')->assertOk()->assertSee('Journal Entries');
        $this->withSession($session)->get('/chart-of-accounts')
            ->assertOk()
            ->assertSee('Cash on Hand')
            ->assertSee(route('chart-of-accounts.export.csv'), false)
            ->assertSee(route('chart-of-accounts.export.pdf'), false)
            ->assertSee('id="new-account-modal"', false);
    }

    public function test_accounts_are_created_updated_deactivated_exported_and_deleted_in_shared_json(): void
    {
        $session = $this->demoSession();

        $created = $this->withSession($session)->postJson('/chart-of-accounts', [
            'name' => 'New Demo Account',
            'type' => 'Expense',
            'sub_type' => 'Operating Expense',
            'balance' => 125.50,
            'status' => 'Active',
        ])->assertCreated()->json('account');

        $this->assertSame('1', $created['code']);
        $this->withSession($session)->putJson('/chart-of-accounts/1', [
            'name' => 'Updated Demo Account',
            'type' => 'Expense',
            'sub_type' => 'Other Expense',
            'balance' => 200,
        ])->assertOk()->assertJsonPath('account.name', 'Updated Demo Account');

        $this->withSession($session)->patchJson('/chart-of-accounts/1/status', ['status' => 'Inactive'])
            ->assertOk()->assertJsonPath('account.status', 'Inactive');

        $csv = $this->withSession($session)->get(route('chart-of-accounts.export.csv'))->streamedContent();
        $this->assertStringContainsString('Updated Demo Account', $csv);
        $this->withSession($session)->get(route('chart-of-accounts.export.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withSession($session)->deleteJson('/chart-of-accounts/1')->assertOk();

        $accounts = json_decode(file_get_contents($this->accountsPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $accounts);
        $this->assertSame('1000', $accounts[0]['code']);
    }

    public function test_viewer_role_cannot_change_accounts(): void
    {
        $this->withSession(['demo_user' => [...$this->demoSession()['demo_user'], 'role' => 'Viewer / Auditor']])
            ->postJson('/chart-of-accounts', [
                'name' => 'Blocked Account',
                'type' => 'Asset',
                'sub_type' => 'Current Asset',
                'balance' => 0,
                'status' => 'Active',
            ])->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function sampleAccount(): array
    {
        return [
            'code' => '1000',
            'name' => 'Cash on Hand',
            'type' => 'Asset',
            'sub_type' => 'Current Asset',
            'balance' => 100,
            'status' => 'Active',
        ];
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(): array
    {
        return [
            'demo_user' => [
                'id' => 1,
                'name' => 'Maria Santos',
                'email' => 'admin@gmail.com',
                'role' => 'Administrator',
            ],
        ];
    }
}
