<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingPostingService;
use App\Services\Accounting\StaticAccountingTransaction;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\CashBankDataService;
use App\Services\DemoData\ExpenseDataService;
use App\Services\DemoData\ExpensePaymentDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class ExpensePaymentController extends Controller
{
    public function store(Request $request, ExpenseDataService $expenses, ExpensePaymentDataService $payments, AccountDataService $accounts, AuditLogDataService $audit): JsonResponse
    {
        $prepared = $this->prepare($request, $expenses, $accounts);
        if ($prepared instanceof JsonResponse) return $prepared;
        try {
            $payment = $payments->create([...$prepared, 'created_by' => $this->actor($request)]);
            $audit->record($this->actor($request), 'created_draft', $payment['payment_number'], ['before' => null, 'after' => 'Draft', 'expense_number' => $payment['expense_number']], 'expense_payment');
            return response()->json(['message' => 'Expense payment draft created.', 'payment' => $payment], 201);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function update(Request $request, string $number, ExpenseDataService $expenses, ExpensePaymentDataService $payments, AccountDataService $accounts, AuditLogDataService $audit): JsonResponse
    {
        $prepared = $this->prepare($request, $expenses, $accounts);
        if ($prepared instanceof JsonResponse) return $prepared;
        try {
            $payment = $payments->update($number, $prepared);
            $audit->record($this->actor($request), 'updated_draft', $number, ['before' => 'Draft', 'after' => 'Draft'], 'expense_payment');
            return response()->json(['message' => 'Expense payment draft updated.', 'payment' => $payment]);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function destroy(Request $request, string $number, ExpensePaymentDataService $payments, AuditLogDataService $audit): JsonResponse
    {
        try { $payments->delete($number); $audit->record($this->actor($request), 'deleted_draft', $number, ['before' => 'Draft', 'after' => null], 'expense_payment'); return response()->json(['message' => 'Expense payment draft deleted.']); }
        catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function submit(Request $request, string $number, ExpensePaymentDataService $payments, AuditLogDataService $audit): JsonResponse
    {
        try { $payment = $payments->submit($number, $this->actor($request)); $audit->record($this->actor($request), 'submitted_for_review', $number, ['before' => 'Draft', 'after' => 'For Review'], 'expense_payment'); return response()->json(['message' => 'Expense payment submitted for review.', 'payment' => $payment]); }
        catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function returnToDraft(Request $request, string $number, ExpensePaymentDataService $payments, AuditLogDataService $audit): JsonResponse
    {
        try { $payment = $payments->returnToDraft($number); $audit->record($this->actor($request), 'returned_to_draft', $number, ['before' => 'For Review', 'after' => 'Draft'], 'expense_payment'); return response()->json(['message' => 'Expense payment returned to draft.', 'payment' => $payment]); }
        catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function post(Request $request, string $number, ExpenseDataService $expenses, ExpensePaymentDataService $payments, AccountDataService $accounts, AccountingPostingService $posting, CashBankDataService $cash, AuditLogDataService $audit, StaticAccountingTransaction $transaction): JsonResponse
    {
        try {
            $result = $transaction->run(function () use ($request, $number, $expenses, $payments, $accounts, $posting, $cash, $audit): array {
                $payment = $payments->find($number);
                if ($payment['status'] !== 'For Review') throw new RuntimeException('Only expense payments for review can be posted.');
                $expense = $expenses->find($payment['expense_number']);
                $this->assertPayable($expense);
                $account = collect($accounts->all(['status' => 'Active']))->firstWhere('code', $payment['cash_account_code']);
                if (! $account || ! $this->isCash($account)) throw new RuntimeException('Select an active cash or bank account.');
                if ((float) $account['balance'] < (float) $expense['total']) throw new RuntimeException('Expense payment exceeds the available cash or bank balance.');
                if (abs((float) $payment['amount'] - (float) $expense['total']) > .004) throw new RuntimeException('Expense payment must equal the full unpaid amount.');
                $journal = $posting->postExpensePayment($expense, $payment, $this->actor($request));
                $movement = $cash->recordTransaction(['request_token' => $payment['request_token'], 'type' => 'withdrawal', 'date' => $payment['payment_date'], 'amount' => $payment['amount'],
                    'account_code' => $payment['cash_account_code'], 'offset_account_code' => $this->payableCode($accounts), 'reference' => $payment['reference'] ?: $payment['payment_number'],
                    'description' => 'Expense payment '.$expense['expense_number'].' - '.$expense['payee'], 'journal_entry_id' => $journal['journal_number'], 'created_by' => $this->actor($request)]);
                $payment = $payments->post($number, $journal['journal_number'], $this->actor($request), ['cash_transaction_id' => $movement['id']]);
                $expense = $expenses->markPaid($expense['expense_number'], $number, $journal['journal_number'], $this->actor($request), $payment);
                $audit->record($this->actor($request), 'posted', $number, ['before' => 'For Review', 'after' => 'Posted', 'journal_entry_id' => $journal['journal_number']], 'expense_payment');
                return compact('payment', 'expense', 'journal');
            });
            return response()->json(['message' => 'Expense payment posted.', ...$result]);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function reverse(Request $request, string $number, ExpenseDataService $expenses, ExpensePaymentDataService $payments, AccountingPostingService $posting, CashBankDataService $cash, AuditLogDataService $audit, StaticAccountingTransaction $transaction): JsonResponse
    {
        try {
            $result = $transaction->run(function () use ($request, $number, $expenses, $payments, $posting, $cash, $audit): array {
                $payment = $payments->find($number);
                if ($payment['status'] !== 'Posted') throw new RuntimeException('Only posted expense payments can be reversed.');
                $movement = $cash->findTransaction((string) $payment['cash_transaction_id']);
                if (($movement['cleared'] ?? false) || ($movement['reconciliation_id'] ?? null)) throw new RuntimeException('Reconciled expense payments cannot be reversed.');
                $reversal = $posting->reverseExpenseSource($payment['journal_entry_id'], $this->actor($request), 'Expense Payment');
                $cash->markReversed((int) $movement['id'], $reversal['reversal']['journal_number'], $reversal['reversal']['journal_number']);
                $payment = $payments->reverse($number, $reversal['reversal']['journal_number'], $this->actor($request));
                $expense = $expenses->markPaymentReversed($payment['expense_number']);
                $audit->record($this->actor($request), 'reversed', $number, ['before' => 'Posted', 'after' => 'Reversed', 'reversal_journal_entry_id' => $reversal['reversal']['journal_number']], 'expense_payment');
                return ['payment' => $payment, 'expense' => $expense, 'reversal' => $reversal['reversal']];
            });
            return response()->json(['message' => 'Expense payment reversed.', ...$result]);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    private function prepare(Request $request, ExpenseDataService $expenses, AccountDataService $accounts): array|JsonResponse
    {
        $validator = validator($request->all(), ['request_token' => ['required', 'uuid'], 'expense_number' => ['required', 'string'], 'payment_date' => ['required', 'date_format:Y-m-d'],
            'cash_account_code' => ['required', 'string'], 'payment_method' => ['required', 'in:Cash,Credit Card,Bank Transfer,Other'], 'reference' => ['nullable', 'string', 'max:50'], 'memo' => ['nullable', 'string', 'max:180']]);
        if ($validator->fails()) return response()->json(['message' => 'Please correct expense payment fields.', 'errors' => $validator->errors()], 422);
        try { $expense = $expenses->find($validator->validated()['expense_number']); $this->assertPayable($expense); }
        catch (RuntimeException $e) { return $this->problem($e); }
        $account = collect($accounts->all(['status' => 'Active']))->firstWhere('code', $validator->validated()['cash_account_code']);
        if (! $account || ! $this->isCash($account)) return response()->json(['message' => 'Please correct expense payment fields.', 'errors' => ['cash_account_code' => ['Select an active cash or bank account.']]], 422);
        return [...$validator->validated(), 'amount' => round((float) $expense['total'], 2), 'payee' => $expense['payee']];
    }

    private function assertPayable(array $expense): void { if (($expense['status'] ?? '') !== 'Approved' || ($expense['payment_status'] ?? '') !== 'Unpaid') throw new RuntimeException('Only approved unpaid expenses can be paid.'); }
    private function isCash(array $account): bool { $name = Str::lower($account['name'].' '.($account['sub_type'] ?? '')); return $account['type'] === 'Asset' && (str_contains($name, 'cash') || str_contains($name, 'bank')); }
    private function payableCode(AccountDataService $accounts): string { $account = collect($accounts->all(['status' => 'Active']))->first(fn (array $a): bool => $a['type'] === 'Liability' && str_contains(Str::lower($a['name']), 'payable') && ! str_contains(Str::lower($a['name']), 'tax')); if (! $account) throw new RuntimeException('Active Accounts Payable account is required.'); return (string) $account['code']; }
    private function actor(Request $request): array { return (array) $request->session()->get('demo_user', []); }
    private function problem(RuntimeException $e): JsonResponse { return response()->json(['message' => $e->getMessage()], 409); }
}
