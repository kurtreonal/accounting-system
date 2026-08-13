<?php

namespace Tests\Feature;

use Tests\TestCase;

class JournalEntryManagementTest extends TestCase
{
    private string $accountsPath;

    private string $journalsPath;

    private string $auditLogsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);
        $this->accountsPath = storage_path("framework/testing/journal-accounts-{$suffix}.json");
        $this->journalsPath = storage_path("framework/testing/journal-entries-{$suffix}.json");
        $this->auditLogsPath = storage_path("framework/testing/journal-audit-{$suffix}.json");

        file_put_contents($this->accountsPath, json_encode([
            $this->account('1000', 'Cash on Hand', 'Asset'),
            $this->account('4000', 'Service Revenue', 'Revenue'),
            [...$this->account('9999', 'Inactive Account', 'Expense'), 'status' => 'Inactive'],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->journalsPath, '[]');
        file_put_contents($this->auditLogsPath, '[]');

        config()->set('accounting.accounts_path', $this->accountsPath);
        config()->set('accounting.journal_entries_path', $this->journalsPath);
        config()->set('accounting.audit_logs_path', $this->auditLogsPath);
    }

    protected function tearDown(): void
    {
        foreach ([$this->accountsPath, $this->journalsPath, $this->auditLogsPath] as $path) {
            if (isset($path) && is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_authenticated_user_can_open_functional_journal_page(): void
    {
        $this->withSession($this->demoSession())->get('/journal-entries')
            ->assertOk()
            ->assertSee('id="journal-modal"', false)
            ->assertSee('Save &amp; Submit for Review', false)
            ->assertSee('Cash on Hand')
            ->assertSee(route('journal-entries.export.csv'), false);
    }

    public function test_journal_entry_follows_draft_review_post_and_reversal_workflow(): void
    {
        $session = $this->demoSession();
        $draft = $this->withSession($session)->postJson('/journal-entries', $this->payload(500, 450))
            ->assertCreated()
            ->assertJsonPath('entry.status', 'Draft')
            ->json('entry');

        $number = $draft['journal_number'];
        $this->assertSame('JE-2026-0001', $number);
        $this->withSession($session)->postJson("/journal-entries/{$number}/submit-review")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Balance the journal entry before submitting it for review.');

        $this->withSession($session)->putJson("/journal-entries/{$number}", $this->payload(500, 500))
            ->assertOk()
            ->assertJsonPath('entry.total_credit', 500);
        $this->withSession($session)->postJson("/journal-entries/{$number}/submit-review")
            ->assertOk()
            ->assertJsonPath('entry.status', 'For Review');
        $this->withSession($session)->postJson("/journal-entries/{$number}/return-draft")
            ->assertOk()
            ->assertJsonPath('entry.status', 'Draft');
        $this->withSession($session)->postJson("/journal-entries/{$number}/submit-review")
            ->assertOk()
            ->assertJsonPath('entry.status', 'For Review');
        $this->withSession($this->demoSession('Encoder / Staff'))->postJson("/journal-entries/{$number}/post")
            ->assertForbidden();
        $this->withSession($session)->postJson("/journal-entries/{$number}/post")
            ->assertOk()
            ->assertJsonPath('entry.status', 'Posted');

        $postedAccounts = json_decode(file_get_contents($this->accountsPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(500, $postedAccounts[0]['balance']);
        $this->assertSame(500, $postedAccounts[1]['balance']);

        $this->withSession($session)->putJson("/journal-entries/{$number}", $this->payload(600, 600))
            ->assertStatus(409);
        $this->withSession($session)->deleteJson("/journal-entries/{$number}")
            ->assertStatus(409);

        $reversed = $this->withSession($session)->postJson("/journal-entries/{$number}/reverse")
            ->assertOk()
            ->assertJsonPath('entry.status', 'Reversed')
            ->assertJsonPath('reversal.status', 'Posted')
            ->json();

        $this->assertSame(500, $reversed['reversal']['lines'][0]['credit']);
        $this->assertSame(500, $reversed['reversal']['lines'][1]['debit']);
        $this->assertSame($number, $reversed['reversal']['reversal_of']);

        $reversedAccounts = json_decode(file_get_contents($this->accountsPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, $reversedAccounts[0]['balance']);
        $this->assertSame(0, $reversedAccounts[1]['balance']);

        $auditLogs = json_decode(file_get_contents($this->auditLogsPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([
            'created',
            'updated',
            'submitted_for_review',
            'returned_to_draft',
            'submitted_for_review',
            'posted',
            'reversed',
        ], array_column($auditLogs, 'action'));
    }

    public function test_inactive_accounts_and_invalid_lines_are_rejected(): void
    {
        $payload = $this->payload(500, 500);
        $payload['lines'][0]['account_code'] = '9999';
        $payload['lines'][1]['debit'] = 500;
        $payload['lines'][1]['credit'] = 500;

        $this->withSession($this->demoSession())->postJson('/journal-entries', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.account_code', 'lines.1.amount']);
    }

    public function test_roles_export_and_print_are_enforced(): void
    {
        $viewer = $this->demoSession('Viewer / Auditor');
        $this->withSession($viewer)->postJson('/journal-entries', $this->payload(500, 500))->assertForbidden();

        $entry = $this->withSession($this->demoSession())->postJson('/journal-entries', $this->payload(500, 500))
            ->assertCreated()->json('entry');
        $number = $entry['journal_number'];

        $csv = $this->withSession($viewer)->get(route('journal-entries.export.csv'))->streamedContent();
        $this->assertStringNotContainsString($number, $csv);
        $this->withSession($viewer)->get(route('journal-entries.print', $number))
            ->assertNotFound();
        $this->withSession($viewer)->get(route('journal-entries.pdf', $number))->assertNotFound();

        $this->withSession($this->demoSession())->postJson(route('journal-entries.submit-review', $number))->assertOk();
        $this->withSession($this->demoSession())->postJson(route('journal-entries.post', $number))->assertOk();
        $csv = $this->withSession($viewer)->get(route('journal-entries.export.csv'))->streamedContent();
        $this->assertStringContainsString($number, $csv);
        $this->withSession($viewer)->get(route('journal-entries.print', $number))
            ->assertOk()
            ->assertSee($number)
            ->assertSee('PHP 500.00');

        $listPdf = $this->withSession($viewer)->get(route('journal-entries.export.pdf'));
        $listPdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('journal-entries-'.now()->format('Y-m-d').'.pdf');
        $this->assertStringStartsWith('%PDF-', $listPdf->getContent());

        $entryPdf = $this->withSession($viewer)->get(route('journal-entries.pdf', $number));
        $entryPdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload($number.'.pdf');
        $this->assertStringStartsWith('%PDF-', $entryPdf->getContent());
    }

    /** @return array<string, mixed> */
    private function payload(float $debit, float $credit): array
    {
        return [
            'date' => '2026-08-10',
            'reference' => 'MANUAL-001',
            'description' => 'Cash service revenue',
            'source_type' => 'Manual',
            'lines' => [
                ['account_code' => '1000', 'description' => 'Cash received', 'party_reference' => '', 'cost_center' => '', 'debit' => $debit, 'credit' => 0],
                ['account_code' => '4000', 'description' => 'Revenue earned', 'party_reference' => '', 'cost_center' => '', 'debit' => 0, 'credit' => $credit],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function account(string $code, string $name, string $type): array
    {
        return ['code' => $code, 'name' => $name, 'type' => $type, 'sub_type' => '', 'balance' => 0, 'status' => 'Active'];
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(string $role = 'Administrator'): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com', 'role' => $role]];
    }
}
