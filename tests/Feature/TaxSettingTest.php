<?php

namespace Tests\Feature;

use Tests\TestCase;

class TaxSettingTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = uniqid('', true);
        foreach (['tax_codes', 'journals', 'audit'] as $name) {
            $this->paths[$name] = storage_path("framework/testing/tax-{$name}-{$suffix}.json");
            file_put_contents($this->paths[$name], '[]');
        }
        file_put_contents($this->paths['tax_codes'], json_encode([
            $this->taxCode(1, 'VAT-STD', 'Standard VAT', 12, 'VAT', true),
            $this->taxCode(2, 'VAT-ZERO', 'Zero-Rated VAT', 0, 'VAT', false),
            $this->taxCode(3, 'EWT-PRO', 'Professional EWT', 10, 'EWT', true),
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->paths['journals'], json_encode([
            $this->journal('JE-2026-0001', '2026-08-01', 'INV-1', 'Posted', [['4000', 'Sales Revenue', 0, 1000], ['2100', 'Output Tax Payable', 0, 120], ['1100', 'Accounts Receivable', 1120, 0]]),
            $this->journal('JE-2026-0002', '2026-08-05', 'BILL-1', 'Posted', [['5100', 'Office Expense', 500, 0], ['1200', 'Input Tax Receivable', 60, 0], ['2000', 'Accounts Payable', 0, 560]]),
            $this->journal('JE-2026-0003', '2026-08-06', 'DRAFT', 'Draft', [['5100', 'Office Expense', 1000, 0], ['1200', 'Input Tax Receivable', 120, 0], ['2000', 'Accounts Payable', 0, 1120]]),
        ], JSON_THROW_ON_ERROR));
        config()->set('accounting.tax_codes_path', $this->paths['tax_codes']);
        config()->set('accounting.journal_entries_path', $this->paths['journals']);
        config()->set('accounting.audit_logs_path', $this->paths['audit']);
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

    public function test_page_requires_login_and_contains_tax_configuration_and_summary_ui(): void
    {
        $this->get('/tax-settings')->assertRedirect('/login');
        $this->withSession($this->demoSession())->get('/tax-settings')->assertOk()
            ->assertSee('Tax / VAT Settings')->assertSee('Demonstration configuration only')
            ->assertSee('data-tax-tab="rates"', false)->assertSee('data-tax-tab="summary"', false)
            ->assertSee('Add Tax Rate')->assertSee('aria-current="page"', false);
        $this->withSession($this->demoSession('Accountant'))->get('/tax-settings')->assertOk()->assertDontSee('id="tax-add"', false);
    }

    public function test_administrator_can_create_edit_set_default_and_disable_non_default_codes(): void
    {
        $created = $this->withSession($this->demoSession())->postJson('/tax-settings', $this->payload())->assertCreated()->assertJsonPath('tax_code.code', 'VAT-RED');
        $id = $created->json('tax_code.id');
        $this->withSession($this->demoSession())->postJson('/tax-settings', $this->payload())->assertStatus(409);
        $this->withSession($this->demoSession())->putJson("/tax-settings/{$id}", [...$this->payload(), 'name' => 'Reduced VAT Updated'])->assertOk()->assertJsonPath('tax_code.name', 'Reduced VAT Updated');
        $this->withSession($this->demoSession())->postJson("/tax-settings/{$id}/default")->assertOk()->assertJsonPath('tax_code.is_default', true);
        $this->withSession($this->demoSession())->patchJson("/tax-settings/{$id}/status", ['status' => 'Inactive'])->assertStatus(409);
        $this->withSession($this->demoSession())->patchJson('/tax-settings/2/status', ['status' => 'Inactive'])->assertOk()->assertJsonPath('tax_code.status', 'Inactive');
    }

    public function test_non_administrators_cannot_change_tax_configuration(): void
    {
        $this->withSession($this->demoSession('Accountant'))->postJson('/tax-settings', $this->payload())->assertForbidden();
        $this->withSession($this->demoSession('Encoder / Staff'))->patchJson('/tax-settings/2/status', ['status' => 'Inactive'])->assertForbidden();
        $this->withSession($this->demoSession('Viewer / Auditor'))->postJson('/tax-settings/2/default')->assertForbidden();
    }

    public function test_vat_summary_uses_posted_journals_filters_and_exports(): void
    {
        $summary = $this->withSession($this->demoSession())->getJson('/tax-settings/summary?date_from=2026-08-01&date_to=2026-08-31')->assertOk()->assertJsonCount(2, 'summary.rows');
        $this->assertSame(120.0, (float) $summary->json('summary.metrics.output'));
        $this->assertSame(60.0, (float) $summary->json('summary.metrics.input'));
        $this->assertSame(60.0, (float) $summary->json('summary.metrics.net'));
        $outputRow = collect($summary->json('summary.rows'))->firstWhere('direction', 'Output VAT');
        $this->assertSame(1000.0, (float) $outputRow['taxable_amount']);
        $this->withSession($this->demoSession())->getJson('/tax-settings/summary?date_from=2026-08-31&date_to=2026-08-01')->assertUnprocessable();
        $this->withSession($this->demoSession())->get('/tax-settings/export/csv?view=rates')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->withSession($this->demoSession())->get('/tax-settings/export/csv?view=summary&date_from=2026-08-01&date_to=2026-08-31')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function payload(): array
    {
        return ['code' => 'vat-red', 'name' => 'Reduced VAT', 'rate' => 5, 'type' => 'VAT', 'applies_to' => 'Selected goods', 'is_default' => false];
    }

    /** @return array<string, mixed> */
    private function taxCode(int $id, string $code, string $name, float $rate, string $type, bool $default): array
    {
        return ['id' => $id, 'code' => $code, 'name' => $name, 'rate' => $rate, 'type' => $type, 'applies_to' => 'Demo transactions', 'is_default' => $default, 'status' => 'Active', 'created_at' => '2026-01-01T00:00:00+00:00', 'updated_at' => '2026-01-01T00:00:00+00:00'];
    }

    /** @param array<int, array{0: string, 1: string, 2: float|int, 3: float|int}> $lines
     * @return array<string, mixed>
     */
    private function journal(string $number, string $date, string $reference, string $status, array $lines): array
    {
        $mapped = array_map(fn (array $line, int $index): array => ['id' => $index + 1, 'account_code' => $line[0], 'account_name' => $line[1], 'description' => '', 'party_reference' => '', 'cost_center' => '', 'debit' => $line[2], 'credit' => $line[3]], $lines, array_keys($lines));
        $debit = array_sum(array_column($mapped, 'debit'));
        $credit = array_sum(array_column($mapped, 'credit'));

        return ['journal_number' => $number, 'date' => $date, 'reference' => $reference, 'description' => $reference, 'source_type' => 'Test', 'status' => $status, 'lines' => $mapped, 'total_debit' => $debit, 'total_credit' => $credit];
    }

    private function demoSession(string $role = 'Administrator'): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.test', 'role' => $role]];
    }
}
