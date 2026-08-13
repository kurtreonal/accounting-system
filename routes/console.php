<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\Accounting\AccountingPostingService;
use App\Services\Accounting\StaticAccountingTransaction;
use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\ExpenseDataService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:seed-expenses', function () {
    $expenses = app(ExpenseDataService::class);
    $posting = app(AccountingPostingService::class);
    $audit = app(AuditLogDataService::class);
    $transaction = app(StaticAccountingTransaction::class);
    $actor = ['id' => 1, 'name' => 'Matavia', 'role' => 'Administrator'];
    $samples = [
        ['10000000-0000-4000-8000-000000000002', 'Approved', '2026-07-05', '2026-08-04', 'Manila Electric Company', '8', 'Utilities Expense', 2450, 'July electricity service'],
        ['10000000-0000-4000-8000-000000000003', 'Approved', '2026-07-12', '2026-08-11', 'Prime Office Supplies', '6', 'Office Supplies Expense', 1320, 'Printer paper and office supplies'],
        ['10000000-0000-4000-8000-000000000004', 'Approved', '2026-07-20', '2026-08-19', 'Santos Legal Services', '11', 'Professional Fees', 4800, 'Contract review services'],
        ['10000000-0000-4000-8000-000000000005', 'Approved', '2026-08-01', '2026-08-31', 'APM Property Leasing', '9', 'Rent Expense', 8500, 'August office rent'],
        ['10000000-0000-4000-8000-000000000006', 'For Review', '2026-08-03', '2026-09-02', 'Globe Business', '8', 'Utilities Expense', 1790, 'Internet service'],
        ['10000000-0000-4000-8000-000000000007', 'For Review', '2026-08-04', '2026-09-03', 'Metro Waterworks', '8', 'Utilities Expense', 680, 'Water service'],
        ['10000000-0000-4000-8000-000000000008', 'For Review', '2026-08-05', '2026-09-04', 'Office Hub Philippines', '6', 'Office Supplies Expense', 940, 'Desk organizers'],
        ['10000000-0000-4000-8000-000000000009', 'For Review', '2026-08-06', '2026-09-05', 'Reyes Consulting', '11', 'Professional Fees', 2250, 'Accounting consultation'],
        ['10000000-0000-4000-8000-000000000010', 'For Review', '2026-08-07', '2026-09-06', 'APM Property Leasing', '9', 'Rent Expense', 1200, 'Storage unit rent'],
        ['10000000-0000-4000-8000-000000000011', 'Draft', '2026-08-08', '2026-09-07', 'City Office Mart', '6', 'Office Supplies Expense', 520, 'Cleaning supplies'],
        ['10000000-0000-4000-8000-000000000012', 'Draft', '2026-08-09', '2026-09-08', 'North Luzon Telecom', '8', 'Utilities Expense', 1150, 'Backup data connection'],
        ['10000000-0000-4000-8000-000000000013', 'Draft', '2026-08-10', '2026-09-09', 'Dela Cruz Advisory', '11', 'Professional Fees', 3100, 'Policy review'],
        ['10000000-0000-4000-8000-000000000014', 'Draft', '2026-08-11', '2026-09-10', 'Warehouse Depot', '6', 'Office Supplies Expense', 760, 'Packaging materials'],
        ['10000000-0000-4000-8000-000000000015', 'Draft', '2026-08-12', '2026-09-11', 'APM Property Leasing', '9', 'Rent Expense', 950, 'Meeting room rental'],
    ];
    $created = 0;
    foreach ($samples as [$token, $target, $date, $dueDate, $payee, $accountCode, $accountName, $amount, $memo]) {
        if (collect($expenses->all())->contains('request_token', $token)) continue;
        $expense = $expenses->create([
            'request_token' => $token, 'date' => $date, 'payee' => $payee,
            'category_account_code' => $accountCode, 'category_name' => $accountName,
            'subtotal' => $amount, 'tax_rate' => 0, 'tax' => 0, 'total' => $amount,
            'payment_status' => 'Unpaid', 'payment_method' => null, 'due_date' => $dueDate,
            'cash_account_code' => null, 'memo' => $memo, 'receipt' => null, 'status' => 'Draft',
            'created_by' => ['id' => 1, 'name' => 'Matavia'],
        ]);
        $audit->record($actor, 'created_draft', $expense['expense_number'], ['before' => null, 'after' => 'Draft'], 'expense');
        if ($target !== 'Draft') {
            $expense = $expenses->submitForReview($expense['expense_number']);
            $audit->record($actor, 'submitted_for_review', $expense['expense_number'], ['before' => 'Draft', 'after' => 'For Review'], 'expense');
        }
        if ($target === 'Approved') {
            $transaction->run(function () use ($expenses, $posting, $audit, $actor, $expense): void {
                $journal = $posting->postExpense($expense, $actor);
                $expenses->approve($expense['expense_number'], $journal['journal_number'], $actor);
                $audit->record($actor, 'approved', $expense['expense_number'], ['before' => 'For Review', 'after' => 'Approved', 'journal_entry_id' => $journal['journal_number']], 'expense');
            });
        }
        $created++;
    }
    $this->info("Created {$created} expense demo records. Total: ".count($expenses->all()));
})->purpose('Add idempotent mixed-state expense demo records through workflow services');
