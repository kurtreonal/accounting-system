<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingPostingService;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\CashBankDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class CashBankController extends Controller
{
    public function index(Request $request, AccountDataService $accounts, CashBankDataService $cashBank): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $allAccounts = $accounts->all(['status' => 'Active']);
        $cashAccounts = collect($allAccounts)->filter(fn (array $account): bool => $this->isCashOrBank($account))->values()->all();
        $transactions = collect($cashBank->transactions())->sortByDesc(fn (array $row): string => $row['date'].'-'.str_pad((string) $row['id'], 8, '0', STR_PAD_LEFT))->values()->all();

        return view('cash-bank', [
            'user' => $request->session()->get('demo_user'),
            'cashAccounts' => $cashAccounts,
            'postingAccounts' => $allAccounts,
            'transactions' => $transactions,
            'reconciliations' => array_reverse($cashBank->reconciliations()),
            'metrics' => [
                'total' => round((float) collect($cashAccounts)->sum('balance'), 2),
                'bank_count' => collect($cashAccounts)->filter(fn (array $account): bool => str_contains(Str::lower($account['sub_type']), 'bank'))->count(),
                'inflow' => round((float) collect($transactions)->filter(fn (array $row): bool => in_array($row['type'], ['deposit', 'interest'], true))->sum('amount'), 2),
                'outflow' => round((float) collect($transactions)->filter(fn (array $row): bool => in_array($row['type'], ['withdrawal', 'charge'], true))->sum('amount'), 2),
            ],
        ]);
    }

    public function storeAccount(Request $request, AccountDataService $accounts, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) return $response;
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'in:Cash,Bank'],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray(), 'Please correct account fields.');
        $validated = $validator->validated();
        if (collect($accounts->all())->contains(fn (array $account): bool => Str::lower($account['name']) === Str::lower(trim($validated['name'])))) {
            return response()->json(['message' => 'An account with this name already exists.'], 409);
        }

        try {
            $account = $accounts->create(['name' => trim($validated['name']), 'type' => 'Asset', 'sub_type' => $validated['kind'], 'balance' => 0, 'status' => 'Active']);
            $auditLogs->record($this->actor($request), 'created', $account['code'], ['module' => 'cash_bank'], 'account');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Cash or bank account created.', 'account' => $account], 201);
    }

    public function storeTransaction(Request $request, AccountDataService $accounts, CashBankDataService $cashBank, AccountingPostingService $posting, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request, 'post cash and bank transactions')) return $response;
        $validator = Validator::make($request->all(), [
            'request_token' => ['required', 'uuid'],
            'type' => ['required', 'in:deposit,withdrawal,transfer,charge,interest'],
            'date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_code' => ['nullable', 'string'],
            'from_account_code' => ['nullable', 'string'],
            'to_account_code' => ['nullable', 'string', 'different:from_account_code'],
            'offset_account_code' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:180'],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray(), 'Please correct transaction fields.');
        $data = $validator->validated();
        $isTransfer = $data['type'] === 'transfer';
        foreach ($isTransfer ? ['from_account_code', 'to_account_code'] : ['account_code', 'offset_account_code'] as $field) {
            if (blank($data[$field] ?? null)) return $this->validationError([$field => ['This field is required.']], 'Please correct transaction fields.');
        }

        $accountMap = collect($accounts->all(['status' => 'Active']))->keyBy('code');
        $cashCodes = $accountMap->filter(fn (array $account): bool => $this->isCashOrBank($account))->keys();
        $cashCode = (string) ($isTransfer ? $data['from_account_code'] : $data['account_code']);
        if (! $cashCodes->contains($cashCode) || ($isTransfer && ! $cashCodes->contains((string) $data['to_account_code']))) {
            return $this->validationError(['account_code' => ['Select active cash or bank accounts.']], 'Please correct transaction fields.');
        }
        if (! $isTransfer && ! $accountMap->has((string) $data['offset_account_code'])) {
            return $this->validationError(['offset_account_code' => ['Select an active offset account.']], 'Please correct transaction fields.');
        }
        if ($isTransfer && $cashCode === (string) $data['to_account_code']) {
            return $this->validationError(['to_account_code' => ['Destination must differ from source.']], 'Please correct transaction fields.');
        }

        $outflow = in_array($data['type'], ['withdrawal', 'charge', 'transfer'], true);
        if ($outflow && (float) $accountMap->get($cashCode)['balance'] < (float) $data['amount']) {
            return $this->validationError(['amount' => ['Amount exceeds the available source balance.']], 'Please correct transaction fields.');
        }

        try {
            $journal = $posting->postCashBankTransaction($data, $this->actor($request));
            $transaction = $cashBank->recordTransaction([
                ...$data,
                'amount' => round((float) $data['amount'], 2),
                'journal_entry_id' => $journal['journal_number'],
                'created_by' => ['id' => $request->session()->get('demo_user.id'), 'name' => $request->session()->get('demo_user.name')],
            ]);
            $auditLogs->record($this->actor($request), 'posted', $transaction['transaction_number'], ['journal_entry_id' => $journal['journal_number']], 'cash_bank_transaction');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Transaction posted and balances updated.', 'transaction' => $transaction, 'journal' => $journal], 201);
    }

    public function reconcile(Request $request, AccountDataService $accounts, CashBankDataService $cashBank, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request, 'complete bank reconciliations')) return $response;
        $validator = Validator::make($request->all(), [
            'account_code' => ['required', 'string'],
            'statement_date' => ['required', 'date_format:Y-m-d'],
            'statement_balance' => ['required', 'numeric'],
            'transaction_ids' => ['array'],
            'transaction_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray(), 'Please correct reconciliation fields.');
        $data = $validator->validated();
        $account = collect($accounts->all(['status' => 'Active']))->first(fn (array $row): bool => $row['code'] === $data['account_code'] && $this->isCashOrBank($row));
        if (! $account) return $this->validationError(['account_code' => ['Select an active cash or bank account.']], 'Please correct reconciliation fields.');
        $selectedIds = array_values(array_unique($data['transaction_ids'] ?? []));
        $remainingUncleared = collect($cashBank->transactions())->filter(
            fn (array $row): bool => ! ($row['cleared'] ?? false)
                && $cashBank->touchesAccount($row, $data['account_code'])
                && ! in_array((int) $row['id'], $selectedIds, true)
        );
        $unclearedAmount = round((float) $remainingUncleared->sum(fn (array $row): float => $this->signedAmount($row, $data['account_code'])), 2);
        $difference = round((float) $data['statement_balance'] + $unclearedAmount - (float) $account['balance'], 2);
        if (abs($difference) > 0.004) return $this->validationError(['statement_balance' => ['Statement balance plus remaining uncleared items must equal the book balance.']], 'Reconciliation has an unresolved difference.');

        try {
            $reconciliation = $cashBank->reconcile($data['account_code'], $selectedIds, [
                'statement_date' => $data['statement_date'],
                'book_balance' => round((float) $account['balance'], 2),
                'statement_balance' => round((float) $data['statement_balance'], 2),
                'uncleared_items' => $unclearedAmount,
                'difference' => $difference,
                'notes' => trim((string) ($data['notes'] ?? '')),
                'completed_by' => ['id' => $request->session()->get('demo_user.id'), 'name' => $request->session()->get('demo_user.name')],
            ]);
            $auditLogs->record($this->actor($request), 'reconciled', $reconciliation['reference'], ['account_code' => $account['code']], 'bank_reconciliation');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Reconciliation completed.', 'reconciliation' => $reconciliation], 201);
    }

    /** @param array<string, mixed> $account */
    private function isCashOrBank(array $account): bool
    {
        $text = Str::lower($account['name'].' '.($account['sub_type'] ?? ''));
        return $account['type'] === 'Asset' && (str_contains($text, 'cash') || str_contains($text, 'bank'));
    }

    /** @param array<string, mixed> $row */
    private function signedAmount(array $row, string $accountCode): float
    {
        $amount = (float) ($row['amount'] ?? 0);
        if (($row['type'] ?? '') === 'transfer') return (string) ($row['to_account_code'] ?? '') === $accountCode ? $amount : -$amount;
        return in_array($row['type'] ?? '', ['deposit', 'interest'], true) ? $amount : -$amount;
    }

    private function denyMutation(Request $request): ?JsonResponse
    {
        if (! $request->session()->has('demo_user')) return response()->json(['message' => 'Authentication is required.'], 401);
        if ($request->session()->get('demo_user.role') === 'Viewer / Auditor') return response()->json(['message' => 'This demo role has read-only access.'], 403);
        return null;
    }

    private function denyApproval(Request $request, string $action): ?JsonResponse
    {
        if ($response = $this->denyMutation($request)) return $response;
        if (! in_array($request->session()->get('demo_user.role'), ['Administrator', 'Accountant'], true)) return response()->json(['message' => "Only Administrators and Accountants can {$action}."], 403);
        return null;
    }

    /** @return array<string, mixed> */
    private function actor(Request $request): array { return (array) $request->session()->get('demo_user', []); }
    private function validationError(array $errors, string $message): JsonResponse { return response()->json(['message' => $message, 'errors' => $errors], 422); }
}
