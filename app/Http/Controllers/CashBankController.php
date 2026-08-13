<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingPostingService;
use App\Services\Accounting\CashBankActivityService;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\CashBankDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashBankController extends Controller
{
    public function index(Request $request, AccountDataService $accounts, CashBankDataService $cashBank, CashBankActivityService $activity): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) return redirect()->route('login');

        $cashAccounts = $activity->cashAccounts();
        $activeCash = collect($cashAccounts)->where('status', 'Active')->values();
        $movements = $activity->movements();
        $currentMovements = collect($movements)->where('status', '!=', 'Reversed');
        return view('cash-bank', [
            'user' => $request->session()->get('demo_user'),
            'cashAccounts' => $cashAccounts,
            'activeCashAccounts' => $activeCash->all(),
            'postingAccounts' => $accounts->all(['status' => 'Active']),
            'transactions' => $movements,
            'reconciliations' => array_reverse($cashBank->reconciliations()),
            'metrics' => [
                'total' => round((float) $activeCash->sum('balance'), 2),
                'bank_count' => $activeCash->where('sub_type', 'Bank')->count(),
                'inflow' => round((float) $currentMovements->whereIn('type', ['deposit', 'interest'])->sum('amount'), 2),
                'outflow' => round((float) $currentMovements->whereIn('type', ['withdrawal', 'charge'])->sum('amount'), 2),
            ],
        ]);
    }

    public function storeAccount(Request $request, AccountDataService $accounts, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request, 'manage cash and bank accounts')) return $response;
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['Cash', 'Bank'])],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray(), 'Please correct account fields.');
        $data = $validator->validated();
        if ($this->duplicateName($accounts, $data['name'])) return response()->json(['message' => 'An account with this name already exists.'], 409);

        try {
            $account = $accounts->create(['name' => trim($data['name']), 'type' => 'Asset', 'sub_type' => $data['kind'], 'balance' => 0, 'status' => 'Active']);
            $auditLogs->record($this->actor($request), 'created', $account['code'], ['module' => 'cash_bank'], 'account');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        return response()->json(['message' => 'Cash or bank account created with a zero opening balance.', 'account' => $account], 201);
    }

    public function updateAccount(Request $request, string $code, AccountDataService $accounts, CashBankActivityService $activity, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request, 'manage cash and bank accounts')) return $response;
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['Cash', 'Bank'])],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray(), 'Please correct account fields.');
        $existing = collect($activity->cashAccounts())->firstWhere('code', $code);
        if (! $existing) return response()->json(['message' => 'Cash or bank account was not found.'], 404);
        $data = $validator->validated();
        if ($this->duplicateName($accounts, $data['name'], $code)) return response()->json(['message' => 'An account with this name already exists.'], 409);

        try {
            $account = $accounts->update($code, ['name' => trim($data['name']), 'type' => 'Asset', 'sub_type' => $data['kind']]);
            $auditLogs->record($this->actor($request), 'updated', $code, ['before' => ['name' => $existing['name'], 'sub_type' => $existing['sub_type']], 'after' => ['name' => $account['name'], 'sub_type' => $account['sub_type']]], 'account');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        return response()->json(['message' => 'Account details updated. Balance was not changed.', 'account' => $account]);
    }

    public function accountStatus(Request $request, string $code, AccountDataService $accounts, CashBankActivityService $activity, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request, 'manage cash and bank accounts')) return $response;
        $validator = Validator::make($request->all(), ['status' => ['required', Rule::in(['Active', 'Inactive'])]]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray(), 'Select a valid account status.');
        $account = collect($activity->cashAccounts())->firstWhere('code', $code);
        if (! $account) return response()->json(['message' => 'Cash or bank account was not found.'], 404);
        $status = $validator->validated()['status'];
        if ($status === 'Inactive') {
            if (abs((float) $account['balance']) > 0.004) return response()->json(['message' => 'Move or adjust the account balance to zero before deactivation.'], 409);
            if (collect($activity->movements())->contains(fn (array $row): bool => ! ($row['cleared'] ?? false) && $activity->touches($row, $code))) {
                return response()->json(['message' => 'Clear or reconcile all account movements before deactivation.'], 409);
            }
        }
        try {
            $updated = $accounts->updateStatus($code, $status);
            $auditLogs->record($this->actor($request), Str::lower($status), $code, ['module' => 'cash_bank'], 'account');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        return response()->json(['message' => "Account {$status}.", 'account' => $updated]);
    }

    public function adjustBalance(Request $request, string $code, AccountDataService $accounts, CashBankDataService $cashBank, CashBankActivityService $activity, AccountingPostingService $posting, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request, 'adjust cash and bank balances')) return $response;
        $validator = Validator::make($request->all(), [
            'request_token' => ['required', 'uuid'],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date_format:Y-m-d'],
            'purpose' => ['required', 'string', 'max:50'],
            'offset_account_code' => ['required', 'string'],
            'reference' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'max:180'],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray(), 'Please correct adjustment fields.');
        $data = $validator->validated();
        $account = collect($activity->cashAccounts(true))->firstWhere('code', $code);
        if (! $account) return $this->validationError(['account_code' => ['Select an active cash or bank account.']], 'Please correct adjustment fields.');
        $type = 'adjustment_'.$data['direction'];
        if (! in_array($data['purpose'], $activity->purposesFor($type), true)) return $this->validationError(['purpose' => ['Select a valid adjustment purpose.']], 'Please correct adjustment fields.');
        if ($data['direction'] === 'decrease' && (float) $data['amount'] > (float) $account['balance'] + 0.004) {
            return $this->validationError(['amount' => ['Adjustment would make the cash or bank balance negative.']], 'Please correct adjustment fields.');
        }
        try {
            $activity->assertEligibleOffset($type, $data['purpose'], $code, $data['offset_account_code']);
            $journal = $posting->postCashBankTransaction([
                ...$data, 'type' => $type, 'account_code' => $code, 'description' => $data['reason'],
            ], $this->actor($request));
            $transaction = $cashBank->recordTransaction([
                'request_token' => $data['request_token'], 'type' => 'adjustment', 'direction' => $data['direction'],
                'purpose' => $data['purpose'], 'date' => $data['date'], 'amount' => round((float) $data['amount'], 2),
                'account_code' => $code, 'offset_account_code' => $data['offset_account_code'], 'reference' => trim($data['reference']),
                'description' => trim($data['reason']), 'journal_entry_id' => $journal['journal_number'], 'created_by' => $this->actorSnapshot($request),
            ]);
            $updated = collect($accounts->all())->firstWhere('code', $code);
            $auditLogs->record($this->actor($request), 'adjusted_balance', $transaction['transaction_number'], ['journal_entry_id' => $journal['journal_number'], 'direction' => $data['direction'], 'amount' => $data['amount']], 'cash_bank_transaction');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        return response()->json(['message' => 'Balance adjustment posted.', 'transaction' => $transaction, 'journal' => $journal, 'account' => $updated], 201);
    }

    public function storeTransaction(Request $request, CashBankDataService $cashBank, CashBankActivityService $activity, AccountingPostingService $posting, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request, 'post cash and bank transactions')) return $response;
        $validator = Validator::make($request->all(), [
            'request_token' => ['required', 'uuid'],
            'type' => ['required', Rule::in(['deposit', 'withdrawal', 'transfer', 'charge', 'interest'])],
            'purpose' => ['nullable', 'string', 'max:50'],
            'date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_code' => ['nullable', 'string'], 'from_account_code' => ['nullable', 'string'],
            'to_account_code' => ['nullable', 'string', 'different:from_account_code'], 'offset_account_code' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:50'], 'description' => ['required', 'string', 'max:180'],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray(), 'Please correct transaction fields.');
        $data = $validator->validated();
        $isTransfer = $data['type'] === 'transfer';
        foreach ($isTransfer ? ['from_account_code', 'to_account_code'] : ['account_code', 'purpose', 'offset_account_code'] as $field) {
            if (blank($data[$field] ?? null)) return $this->validationError([$field => ['This field is required.']], 'Please correct transaction fields.');
        }
        $cashMap = collect($activity->cashAccounts(true))->keyBy('code');
        $cashCode = (string) ($isTransfer ? $data['from_account_code'] : $data['account_code']);
        if (! $cashMap->has($cashCode) || ($isTransfer && ! $cashMap->has((string) $data['to_account_code']))) {
            return $this->validationError(['account_code' => ['Select active cash or bank accounts from Chart of Accounts.']], 'Please correct transaction fields.');
        }
        if (! $isTransfer) {
            if (! in_array($data['purpose'], $activity->purposesFor($data['type']), true)) return $this->validationError(['purpose' => ['Select a valid transaction purpose.']], 'Please correct transaction fields.');
            try { $activity->assertEligibleOffset($data['type'], $data['purpose'], $cashCode, (string) $data['offset_account_code']); }
            catch (RuntimeException $exception) { return $this->validationError(['offset_account_code' => [$exception->getMessage()]], 'Please correct transaction fields.'); }
        }
        if ($isTransfer && $cashCode === (string) $data['to_account_code']) return $this->validationError(['to_account_code' => ['Destination must differ from source.']], 'Please correct transaction fields.');
        if (in_array($data['type'], ['withdrawal', 'charge', 'transfer'], true) && (float) $cashMap->get($cashCode)['balance'] < (float) $data['amount']) {
            return $this->validationError(['amount' => ['Amount exceeds the available source balance.']], 'Please correct transaction fields.');
        }

        try {
            $journal = $posting->postCashBankTransaction($data, $this->actor($request));
            $transaction = $cashBank->recordTransaction([
                ...$data, 'amount' => round((float) $data['amount'], 2), 'journal_entry_id' => $journal['journal_number'], 'created_by' => $this->actorSnapshot($request),
            ]);
            $auditLogs->record($this->actor($request), 'posted', $transaction['transaction_number'], ['journal_entry_id' => $journal['journal_number']], 'cash_bank_transaction');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        return response()->json(['message' => 'Transaction posted and balances updated.', 'transaction' => $transaction, 'journal' => $journal], 201);
    }

    public function activity(Request $request, string $code, CashBankActivityService $activity): JsonResponse
    {
        if (! $request->session()->has('demo_user')) return response()->json(['message' => 'Authentication is required.'], 401);
        try { $ledger = $activity->activity($code); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 404); }
        $search = Str::lower(trim((string) $request->query('search', '')));
        $from = trim((string) $request->query('date_from', '')); $to = trim((string) $request->query('date_to', ''));
        $ledger['rows'] = array_values(array_filter($ledger['rows'], static function (array $row) use ($search, $from, $to): bool {
            $haystack = Str::lower(implode(' ', [$row['reference'] ?? '', $row['description'] ?? '', $row['journal_entry_id'] ?? '', $row['counterpart'] ?? '']));
            return ($search === '' || str_contains($haystack, $search)) && ($from === '' || $row['date'] >= $from) && ($to === '' || $row['date'] <= $to);
        }));
        return response()->json($ledger);
    }

    public function reverseTransaction(Request $request, string $identifier, CashBankDataService $cashBank, AccountingPostingService $posting, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request, 'reverse cash and bank transactions')) return $response;
        try {
            $original = $cashBank->findTransaction($identifier);
            if (($original['cleared'] ?? false) || ($original['reversed_at'] ?? null)) throw new RuntimeException('Cleared or already reversed transactions cannot be reversed.');
            if (blank($original['journal_entry_id'] ?? null)) throw new RuntimeException('This legacy transaction has no posted journal to reverse.');
            $result = $posting->reverseCashBank((string) $original['journal_entry_id'], $this->actor($request));
            $amount = round((float) $original['amount'], 2);
            $effects = [];
            if (($original['type'] ?? '') === 'transfer') {
                $effects[(string) $original['from_account_code']] = $amount;
                $effects[(string) $original['to_account_code']] = -$amount;
            } else {
                $originalInflow = in_array($original['type'] ?? '', ['deposit', 'interest'], true) || (($original['type'] ?? '') === 'adjustment' && ($original['direction'] ?? '') === 'increase');
                $effects[(string) $original['account_code']] = $originalInflow ? -$amount : $amount;
            }
            $reversal = $cashBank->recordTransaction([
                'request_token' => (string) Str::uuid(), 'type' => 'reversal', 'date' => $result['reversal']['date'], 'amount' => $amount,
                'account_code' => $original['account_code'] ?? null, 'from_account_code' => $original['from_account_code'] ?? null, 'to_account_code' => $original['to_account_code'] ?? null,
                'offset_account_code' => $original['offset_account_code'] ?? null, 'reference' => $original['transaction_number'],
                'description' => 'Reversal of '.$original['transaction_number'].': '.$original['description'], 'journal_entry_id' => $result['reversal']['journal_number'],
                'reversal_of' => $original['transaction_number'], 'cash_effects' => $effects, 'created_by' => $this->actorSnapshot($request),
            ]);
            $cashBank->markReversed((int) $original['id'], $result['reversal']['journal_number'], $reversal['transaction_number']);
            $auditLogs->record($this->actor($request), 'reversed', $original['transaction_number'], ['reversal_transaction' => $reversal['transaction_number'], 'reversal_journal' => $result['reversal']['journal_number']], 'cash_bank_transaction');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        return response()->json(['message' => 'Transaction reversed through an offsetting journal.', 'transaction' => $original, 'reversal' => $reversal, 'journal' => $result['reversal']]);
    }

    public function reconcile(Request $request, CashBankDataService $cashBank, CashBankActivityService $activity, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request, 'complete bank reconciliations')) return $response;
        $validator = Validator::make($request->all(), [
            'account_code' => ['required', 'string'], 'statement_date' => ['required', 'date_format:Y-m-d'],
            'statement_balance' => ['required', 'numeric'], 'movement_ids' => ['array'], 'movement_ids.*' => ['string', 'max:120'],
            'transaction_ids' => ['array'], 'transaction_ids.*' => ['integer'], 'notes' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray(), 'Please correct reconciliation fields.');
        $data = $validator->validated();
        $account = collect($activity->cashAccounts(true))->firstWhere('code', $data['account_code']);
        if (! $account) return $this->validationError(['account_code' => ['Select an active cash or bank account.']], 'Please correct reconciliation fields.');
        $selected = array_values(array_unique(array_map('strval', $data['movement_ids'] ?? array_map(fn ($id): string => 'transaction:'.$id, $data['transaction_ids'] ?? []))));
        $eligible = collect($activity->movements())->filter(fn (array $row): bool => ! ($row['cleared'] ?? false) && $row['date'] <= $data['statement_date'] && $activity->touches($row, $data['account_code']));
        if (array_diff($selected, $eligible->pluck('movement_id')->all()) !== []) return $this->validationError(['movement_ids' => ['One or more selected movements cannot be reconciled.']], 'Please correct reconciliation fields.');
        $remaining = $eligible->reject(fn (array $row): bool => in_array($row['movement_id'], $selected, true));
        $uncleared = round((float) $remaining->sum(fn (array $row): float => $activity->signedAmount($row, $data['account_code'])), 2);
        $difference = round((float) $data['statement_balance'] + $uncleared - (float) $account['balance'], 2);
        if (abs($difference) > 0.004) return $this->validationError(['statement_balance' => ['Statement balance plus remaining uncleared items must equal the book balance.']], 'Reconciliation has an unresolved difference.');
        try {
            $reconciliation = $cashBank->reconcile($data['account_code'], $selected, [
                'statement_date' => $data['statement_date'], 'book_balance' => round((float) $account['balance'], 2),
                'statement_balance' => round((float) $data['statement_balance'], 2), 'uncleared_items' => $uncleared, 'difference' => $difference,
                'notes' => trim((string) ($data['notes'] ?? '')), 'completed_by' => $this->actorSnapshot($request),
            ]);
            $auditLogs->record($this->actor($request), 'reconciled', $reconciliation['reference'], ['account_code' => $account['code']], 'bank_reconciliation');
        } catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], 409); }
        return response()->json(['message' => 'Reconciliation completed.', 'reconciliation' => $reconciliation], 201);
    }

    public function csv(Request $request, CashBankActivityService $activity): StreamedResponse|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) return redirect()->route('login');
        $search = Str::lower(trim((string) $request->query('search', ''))); $account = trim((string) $request->query('account', ''));
        $type = trim((string) $request->query('type', '')); $status = trim((string) $request->query('status', ''));
        $from = trim((string) $request->query('date_from', '')); $to = trim((string) $request->query('date_to', ''));
        $rows = array_values(array_filter($activity->movements(), static function (array $row) use ($search, $account, $type, $status, $from, $to): bool {
            $haystack = Str::lower(implode(' ', [$row['reference'] ?? '', $row['description'] ?? '', $row['transaction_number'] ?? '', $row['source_label'] ?? '']));
            $accounts = array_map('strval', array_filter([$row['account_code'] ?? null, $row['from_account_code'] ?? null, $row['to_account_code'] ?? null]));
            return ($search === '' || str_contains($haystack, $search)) && ($account === '' || in_array($account, $accounts, true))
                && ($type === '' || $row['type'] === $type) && ($status === '' || $row['status'] === $status)
                && ($from === '' || $row['date'] >= $from) && ($to === '' || $row['date'] <= $to);
        }));
        return response()->streamDownload(static function () use ($rows): void {
            $out = fopen('php://output', 'wb'); fputcsv($out, ['Date', 'Reference', 'Journal', 'Type', 'Source', 'Account', 'Offset Account', 'Description', 'Amount', 'Status']);
            foreach ($rows as $row) fputcsv($out, [$row['date'], $row['reference'] ?? '', $row['journal_entry_id'] ?? '', $row['type'], $row['source_label'] ?? '', $row['account_code'] ?? $row['from_account_code'] ?? '', $row['offset_account_name'] ?? $row['offset_account_code'] ?? $row['to_account_code'] ?? '', $row['description'], $row['amount'], $row['status']]);
            fclose($out);
        }, 'cash-bank-transactions-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function duplicateName(AccountDataService $accounts, string $name, ?string $except = null): bool
    {
        return collect($accounts->all())->contains(fn (array $account): bool => (string) $account['code'] !== (string) $except && Str::lower(trim($account['name'])) === Str::lower(trim($name)));
    }
    private function denyApproval(Request $request, string $action): ?JsonResponse
    {
        if (! $request->session()->has('demo_user')) return response()->json(['message' => 'Authentication is required.'], 401);
        if (! in_array($request->session()->get('demo_user.role'), ['Administrator', 'Accountant'], true)) return response()->json(['message' => "Only Administrators and Accountants can {$action}."], 403);
        return null;
    }
    /** @return array<string, mixed> */ private function actor(Request $request): array { return (array) $request->session()->get('demo_user', []); }
    /** @return array{id: mixed, name: string} */ private function actorSnapshot(Request $request): array { return ['id' => $request->session()->get('demo_user.id'), 'name' => (string) $request->session()->get('demo_user.name', 'Demo User')]; }
    private function validationError(array $errors, string $message): JsonResponse { return response()->json(['message' => $message, 'errors' => $errors], 422); }
}
