<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingPostingService;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\CashBankDataService;
use App\Services\DemoData\ExpenseDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request, ExpenseDataService $expenses, AccountDataService $accounts): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }
        $active = collect($accounts->all(['status' => 'Active']));
        $records = collect($expenses->all())->sortByDesc(fn (array $row): string => $row['date'].'-'.$row['id'])->values()->all();

        return view('expenses', [
            'user' => $request->session()->get('demo_user'),
            'expenses' => $records,
            'expenseAccounts' => $active->where('type', 'Expense')->values()->all(),
            'cashAccounts' => $active->filter(fn (array $account): bool => $this->isCashOrBank($account))->values()->all(),
            'metrics' => [
                'total' => round((float) collect($records)->sum('total'), 2),
                'approved' => round((float) collect($records)->where('status', 'Approved')->sum('total'), 2),
                'pending' => round((float) collect($records)->where('status', 'For Review')->sum('total'), 2),
                'count' => count($records),
                'receipts' => collect($records)->filter(fn (array $row): bool => filled($row['receipt']['name'] ?? null))->count(),
            ],
        ]);
    }

    public function store(Request $request, ExpenseDataService $expenses, AccountDataService $accounts, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }
        $validator = validator($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $data = $validator->validated();
        if ($response = $this->validateAccounts($data, $accounts)) {
            return $response;
        }
        $attributes = $this->attributes($data, $request);

        try {
            $expense = $expenses->create($attributes);
            if ($data['action'] === 'review') {
                $expense = $expenses->submitForReview($expense['expense_number']);
            }
            $auditLogs->record($this->actor($request), $data['action'] === 'review' ? 'created_for_review' : 'created_draft', $expense['expense_number'], ['module' => 'expenses'], 'expense');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => $data['action'] === 'review' ? 'Expense submitted for review.' : 'Expense draft saved.', 'expense' => $expense], 201);
    }

    public function update(Request $request, string $expenseNumber, ExpenseDataService $expenses, AccountDataService $accounts, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }
        $validator = validator($request->all(), $this->rules(false));
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $data = $validator->validated();
        if ($response = $this->validateAccounts($data, $accounts)) {
            return $response;
        }

        try {
            $expense = $expenses->update($expenseNumber, $this->attributes($data, $request, false));
            if ($data['action'] === 'review') {
                $expense = $expenses->submitForReview($expenseNumber);
            }
            $auditLogs->record($this->actor($request), $data['action'] === 'review' ? 'updated_for_review' : 'updated_draft', $expenseNumber, [], 'expense');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => $data['action'] === 'review' ? 'Expense updated and submitted for review.' : 'Expense draft updated.', 'expense' => $expense]);
    }

    public function submitForReview(Request $request, string $expenseNumber, ExpenseDataService $expenses, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }
        try {
            $expense = $expenses->submitForReview($expenseNumber);
            $auditLogs->record($this->actor($request), 'submitted_for_review', $expenseNumber, [], 'expense');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Expense submitted for review.', 'expense' => $expense]);
    }

    public function approve(Request $request, string $expenseNumber, ExpenseDataService $expenses, AccountingPostingService $posting, CashBankDataService $cashBank, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyApproval($request)) {
            return $response;
        }
        try {
            $expense = $expenses->find($expenseNumber);
            if (($expense['status'] ?? '') !== 'For Review') {
                throw new RuntimeException('Only expenses for review can be approved.');
            }
            $journal = $posting->postExpense($expense, $this->actor($request));
            if ($expense['payment_status'] === 'Paid') {
                $cashBank->recordTransaction([
                    'request_token' => $expense['request_token'],
                    'type' => 'withdrawal',
                    'date' => $expense['date'],
                    'amount' => $expense['total'],
                    'account_code' => $expense['cash_account_code'],
                    'offset_account_code' => $expense['category_account_code'],
                    'reference' => $expense['expense_number'],
                    'description' => $expense['payee'].' - '.$expense['memo'],
                    'journal_entry_id' => $journal['journal_number'],
                    'created_by' => ['id' => $request->session()->get('demo_user.id'), 'name' => $request->session()->get('demo_user.name')],
                ]);
            }
            $expense = $expenses->approve($expenseNumber, $journal['journal_number'], $this->actor($request));
            $auditLogs->record($this->actor($request), 'approved', $expenseNumber, ['journal_entry_id' => $journal['journal_number']], 'expense');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Expense approved and posted to the ledger.', 'expense' => $expense, 'journal' => $journal]);
    }

    public function destroy(Request $request, string $expenseNumber, ExpenseDataService $expenses, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }
        try {
            $expenses->delete($expenseNumber);
            $auditLogs->record($this->actor($request), 'deleted_draft', $expenseNumber, [], 'expense');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Expense draft deleted.']);
    }

    public function csv(Request $request, ExpenseDataService $expenses): StreamedResponse|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }
        $rows = $expenses->all();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Expense No.', 'Date', 'Payee', 'Category', 'Payment Status', 'Payment Method', 'Subtotal', 'Tax', 'Total', 'Workflow Status', 'Journal Entry']);
            foreach ($rows as $row) {
                fputcsv($output, [$row['expense_number'], $row['date'], $row['payee'], $row['category_name'], $row['payment_status'], $row['payment_method'], number_format((float) $row['subtotal'], 2, '.', ''), number_format((float) $row['tax'], 2, '.', ''), number_format((float) $row['total'], 2, '.', ''), $row['status'], $row['journal_entry_id'] ?? '']);
            }
            fclose($output);
        }, 'expenses-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(bool $create = true): array
    {
        return [
            'request_token' => [$create ? 'required' : 'sometimes', 'uuid'],
            'action' => ['required', Rule::in(['draft', 'review'])],
            'date' => ['required', 'date_format:Y-m-d'],
            'payee' => ['required', 'string', 'max:100'],
            'category_account_code' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payment_status' => ['required', Rule::in(['Paid', 'Unpaid'])],
            'payment_method' => ['required', Rule::in(['Cash', 'Credit Card', 'Bank Transfer', 'Other'])],
            'cash_account_code' => ['nullable', 'string'],
            'memo' => ['required', 'string', 'max:180'],
            'receipt' => ['nullable', 'array'],
            'receipt.name' => ['nullable', 'string', 'max:150'],
            'receipt.type' => ['nullable', Rule::in(['image/jpeg', 'image/png', 'application/pdf'])],
            'receipt.size' => ['nullable', 'integer', 'max:5242880'],
        ];
    }

    private function validateAccounts(array $data, AccountDataService $accounts): ?JsonResponse
    {
        $map = collect($accounts->all(['status' => 'Active']))->keyBy('code');
        if (! $map->has($data['category_account_code']) || $map->get($data['category_account_code'])['type'] !== 'Expense') {
            return $this->validationError(['category_account_code' => ['Select an active expense category.']]);
        }
        if ($data['payment_status'] === 'Paid') {
            $cash = $map->get((string) ($data['cash_account_code'] ?? ''));
            if (! $cash || ! $this->isCashOrBank($cash)) {
                return $this->validationError(['cash_account_code' => ['Select the cash or bank account used.']]);
            }
            $total = round((float) $data['amount'] * (1 + (float) $data['tax_rate'] / 100), 2);
            if ((float) $cash['balance'] < $total) {
                return $this->validationError(['amount' => ['Total exceeds the available cash or bank balance.']]);
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function attributes(array $data, Request $request, bool $includeToken = true): array
    {
        $subtotal = round((float) $data['amount'], 2);
        $tax = round($subtotal * (float) $data['tax_rate'] / 100, 2);
        $attributes = [
            'date' => $data['date'], 'payee' => trim($data['payee']),
            'category_account_code' => $data['category_account_code'],
            'category_name' => collect(app(AccountDataService::class)->all())->firstWhere('code', $data['category_account_code'])['name'],
            'subtotal' => $subtotal, 'tax_rate' => (float) $data['tax_rate'], 'tax' => $tax, 'total' => round($subtotal + $tax, 2),
            'payment_status' => $data['payment_status'], 'payment_method' => $data['payment_method'],
            'cash_account_code' => $data['payment_status'] === 'Paid' ? $data['cash_account_code'] : null,
            'memo' => trim($data['memo']), 'receipt' => $data['receipt'] ?? null,
            'status' => 'Draft',
            'created_by' => ['id' => $request->session()->get('demo_user.id'), 'name' => $request->session()->get('demo_user.name')],
        ];

        return $includeToken ? ['request_token' => $data['request_token'], ...$attributes] : $attributes;
    }

    /** @param array<string, mixed> $account */
    private function isCashOrBank(array $account): bool
    {
        $text = Str::lower($account['name'].' '.($account['sub_type'] ?? ''));

        return $account['type'] === 'Asset' && (str_contains($text, 'cash') || str_contains($text, 'bank'));
    }

    private function denyMutation(Request $request): ?JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }
        if ($request->session()->get('demo_user.role') === 'Viewer / Auditor') {
            return response()->json(['message' => 'This demo role has read-only access.'], 403);
        }

        return null;
    }

    private function denyApproval(Request $request): ?JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }
        if (! in_array($request->session()->get('demo_user.role'), ['Administrator', 'Accountant'], true)) {
            return response()->json(['message' => 'Only Administrators and Accountants can approve expenses.'], 403);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function actor(Request $request): array
    {
        return (array) $request->session()->get('demo_user', []);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['message' => 'Please correct the expense fields.', 'errors' => $errors], 422);
    }
}
