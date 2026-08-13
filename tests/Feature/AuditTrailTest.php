<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    private string $auditPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditPath = storage_path('framework/testing/audit-trail-'.uniqid('', true).'.json');
        file_put_contents($this->auditPath, json_encode([
            ['id' => 1, 'actor_user_id' => 1, 'actor_name' => 'Admin User', 'actor_role' => 'Administrator', 'action' => 'created_draft', 'resource' => 'sales_invoice', 'resource_id' => 'INV-TEST-1', 'details' => ['after' => 'Draft'], 'created_at' => now()->toIso8601String()],
            ['id' => 2, 'actor_user_id' => 2, 'actor_name' => 'Accountant User', 'actor_role' => 'Accountant', 'action' => 'posted', 'resource' => 'journal_entry', 'resource_id' => 'JE-TEST-1', 'details' => ['before' => 'For Review', 'after' => 'Posted'], 'created_at' => now()->subDay()->toIso8601String()],
        ], JSON_THROW_ON_ERROR));
        config()->set('accounting.audit_logs_path', $this->auditPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->auditPath)) {
            unlink($this->auditPath);
        }
        parent::tearDown();
    }

    public function test_page_matches_required_read_only_audit_ui_and_filters(): void
    {
        $this->get('/audit-trail')->assertRedirect('/login');

        $page = $this->withSession($this->demoSession('Viewer / Auditor'))->get('/audit-trail')->assertOk();
        $page->assertSee('Total Events')->assertSee("Today's Activity")
            ->assertSee('data-audit-view="table"', false)->assertSee('data-audit-view="timeline"', false)
            ->assertSee('Export CSV')->assertDontSee('Export PDF')->assertSee('data-print-page', false)
            ->assertSee('data-audit-filters', false)->assertDontSee('>Filter<', false)
            ->assertSee('data-record-resource="sales_invoice"', false)
            ->assertSee('print:hidden', false)->assertSee('INV-TEST-1')->assertSee('JE-TEST-1');

        $filtered = $this->withSession($this->demoSession('Accountant'))->get('/audit-trail?action=posted&role=Accountant&resource=journal_entry')->assertOk();
        $filtered->assertSee('JE-TEST-1')->assertDontSee('INV-TEST-1');
    }

    public function test_csv_export_uses_filters_and_encoder_is_denied(): void
    {
        $csv = $this->withSession($this->demoSession('Administrator'))->get('/audit-trail/export/csv?action=posted');
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv->assertStreamedContent("\xEF\xBB\xBFTimestamp,User,Role,Action,Module,Record,Before,After\n\"".now()->subDay()->format('Y-m-d H:i:s')."\",\"Accountant User\",Accountant,Posted,\"Journal Entry\",JE-TEST-1,\"For Review\",Posted\n");

        $this->withSession($this->demoSession('Encoder / Staff'))->get('/audit-trail')->assertForbidden();
        $this->withSession($this->demoSession('Encoder / Staff'))->get('/audit-trail/export/csv')->assertForbidden();
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(string $role): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.test', 'role' => $role]];
    }
}
