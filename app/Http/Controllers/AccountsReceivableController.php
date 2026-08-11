<?php

namespace App\Http\Controllers;

use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\JournalEntryDataService;
use App\Services\DemoData\SalesDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountsReceivableController extends Controller
{
    public function index(Request $request, SalesDataService $sales, AccountDataService $accounts): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $invoices = $sales->invoices();
        $customers = $sales->customers();
        $today = now()->toDateString();
        $openInvoices = collect($invoices)->filter(
            static fn (array $invoice): bool => $invoice['status'] !== 'Draft' && (float) $invoice['remaining_balance'] > 0
        );
        $cashAccounts = collect($accounts->all(['status' => 'Active']))
            ->filter(fn (array $account): bool => $this->isCashOrBank($account))
            ->values()
            ->all();

        return view('accounts-receivable', [
            'user' => $request->session()->get('demo_user'),
            'invoices' => $invoices,
            'customers' => $customers,
            'payments' => $sales->payments(),
            'cashAccounts' => $cashAccounts,
            'metrics' => [
                'receivable' => round((float) $openInvoices->sum('remaining_balance'), 2),
                'overdue' => round((float) $openInvoices->filter(static fn (array $invoice): bool => $invoice['due_date'] < $today)->sum('remaining_balance'), 2),
                'invoice_count' => count($invoices),
                'paid_count' => collect($invoices)->where('display_status', 'Paid')->count(),
                'customer_count' => count($customers),
                'active_customer_count' => collect($customers)->where('status', 'Active')->count(),
            ],
            'customerBalances' => $this->customerBalances($customers, $invoices),
        ]);
    }

    public function storePayment(
        Request $request,
        SalesDataService $sales,
        AccountDataService $accounts,
        JournalEntryDataService $journals,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyApproval($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'request_token' => ['required', 'uuid'],
            'customer_id' => ['required', 'integer'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'cash_account_code' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:50'],
            'memo' => ['nullable', 'string', 'max:255'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_number' => ['required', 'string'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $validated = $validator->validated();
        if (collect($sales->payments())->contains('request_token', $validated['request_token'])) {
            return response()->json(['message' => 'This payment request was already posted.'], 409);
        }

        $customer = collect($sales->customers())->first(
            fn (array $item): bool => (int) $item['id'] === (int) $validated['customer_id'] && $item['status'] === 'Active'
        );
        if (! $customer) {
            return $this->validationError(['customer_id' => ['Select an active customer.']]);
        }

        $activeAccounts = collect($accounts->all(['status' => 'Active']));
        $cashAccount = $activeAccounts->first(
            fn (array $account): bool => (string) $account['code'] === (string) $validated['cash_account_code'] && $this->isCashOrBank($account)
        );
        $receivableAccount = $activeAccounts->first(
            fn (array $account): bool => $account['type'] === 'Asset' && str_contains(Str::lower($account['name']), 'receivable')
        );
        if (! $cashAccount) {
            return $this->validationError(['cash_account_code' => ['Select an active cash or bank account.']]);
        }
        if (! $receivableAccount) {
            return response()->json(['message' => 'Posting payment needs an active Accounts Receivable account.'], 409);
        }

        $invoices = collect($sales->invoices())->keyBy('invoice_number');
        $seen = [];
        $allocations = [];
        $total = 0.0;
        foreach ($validated['allocations'] as $index => $allocation) {
            $invoiceNumber = trim($allocation['invoice_number']);
            $amount = round((float) $allocation['amount'], 2);
            $invoice = $invoices->get($invoiceNumber);
            if (! $invoice || (int) $invoice['customer_id'] !== (int) $customer['id']) {
                return $this->validationError(["allocations.{$index}.invoice_number" => ['Select an open invoice for this customer.']]);
            }
            if (isset($seen[$invoiceNumber])) {
                return $this->validationError(["allocations.{$index}.invoice_number" => ['Allocate each invoice only once.']]);
            }
            if ($invoice['status'] === 'Draft' || (float) $invoice['remaining_balance'] <= 0) {
                return $this->validationError(["allocations.{$index}.invoice_number" => ['Invoice is not open for payment.']]);
            }
            if ($amount > (float) $invoice['remaining_balance'] + 0.004) {
                return $this->validationError(["allocations.{$index}.amount" => ['Payment cannot exceed invoice remaining balance.']]);
            }

            $seen[$invoiceNumber] = true;
            $total += $amount;
            $allocations[] = [
                'invoice_number' => $invoiceNumber,
                'invoice_total' => (float) $invoice['total'],
                'amount' => $amount,
            ];
        }
        $total = round($total, 2);

        try {
            $journal = $journals->create([
                'date' => $validated['payment_date'],
                'reference' => trim((string) ($validated['reference'] ?? '')) ?: 'Customer payment',
                'description' => 'Customer payment - '.$customer['name'],
                'source_type' => 'Payment',
                'lines' => [[
                    'id' => 1,
                    'account_code' => (string) $cashAccount['code'],
                    'account_name' => $cashAccount['name'],
                    'description' => 'Customer payment received',
                    'party_reference' => $customer['code'],
                    'cost_center' => '',
                    'debit' => $total,
                    'credit' => 0,
                ], [
                    'id' => 2,
                    'account_code' => (string) $receivableAccount['code'],
                    'account_name' => $receivableAccount['name'],
                    'description' => 'Accounts receivable collection',
                    'party_reference' => $customer['code'],
                    'cost_center' => '',
                    'debit' => 0,
                    'credit' => $total,
                ]],
                'total_debit' => $total,
                'total_credit' => $total,
                'created_by' => $this->actorSnapshot($request),
            ]);
            $journals->submitForReview($journal['journal_number']);
            $journal = $journals->post($journal['journal_number'], $this->actor($request));
            $accounts->applyJournalLines($journal['lines']);
            $payment = $sales->createPayment([
                'request_token' => $validated['request_token'],
                'payment_date' => $validated['payment_date'],
                'customer_id' => $customer['id'],
                'customer_code' => $customer['code'],
                'customer_name' => $customer['name'],
                'cash_account_code' => $cashAccount['code'],
                'cash_account_name' => $cashAccount['name'],
                'reference' => trim((string) ($validated['reference'] ?? '')),
                'memo' => trim((string) ($validated['memo'] ?? '')),
                'amount' => $total,
                'allocations' => $allocations,
                'journal_entry_id' => $journal['journal_number'],
                'posted_by' => $this->actorSnapshot($request),
            ]);
            $auditLogs->record($this->actor($request), 'posted_customer_payment', $payment['receipt_number'], [
                'journal_entry_id' => $journal['journal_number'],
                'amount' => $total,
                'allocations' => $allocations,
            ], 'customer_payment');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Payment posted. Receipt '.$payment['receipt_number'].' created.',
            'payment' => $payment,
            'journal' => $journal,
        ], 201);
    }

    public function csv(Request $request, SalesDataService $sales): StreamedResponse|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $search = Str::lower(trim((string) $request->query('search', '')));
        $status = trim((string) $request->query('status', ''));
        $invoices = array_values(array_filter($sales->invoices(), static function (array $invoice) use ($search, $status): bool {
            $haystack = Str::lower(implode(' ', [$invoice['invoice_number'], $invoice['customer_name'], $invoice['reference'] ?? '']));

            return ($search === '' || str_contains($haystack, $search))
                && ($status === '' || $invoice['display_status'] === $status);
        }));

        return response()->streamDownload(static function () use ($invoices): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Invoice Number', 'Date', 'Due Date', 'Customer', 'Amount', 'Paid', 'Balance', 'Status']);
            foreach ($invoices as $invoice) {
                fputcsv($output, [
                    $invoice['invoice_number'], $invoice['invoice_date'], $invoice['due_date'], $invoice['customer_name'],
                    number_format((float) $invoice['total'], 2, '.', ''),
                    number_format((float) $invoice['amount_paid'], 2, '.', ''),
                    number_format((float) $invoice['remaining_balance'], 2, '.', ''),
                    $invoice['display_status'],
                ]);
            }
            fclose($output);
        }, 'accounts-receivable.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param array<int, array<string, mixed>> $customers
     * @param  array<int, array<string, mixed>>  $invoices
     * @return array<int, array<string, mixed>>
     */
    private function customerBalances(array $customers, array $invoices): array
    {
        return collect($customers)->map(function (array $customer) use ($invoices): array {
            $customerInvoices = collect($invoices)->filter(
                fn (array $invoice): bool => (int) $invoice['customer_id'] === (int) $customer['id'] && $invoice['status'] !== 'Draft'
            );

            return [
                ...$customer,
                'invoice_count' => $customerInvoices->count(),
                'outstanding' => round((float) $customerInvoices->sum('remaining_balance'), 2),
                'collected' => round((float) $customerInvoices->sum('amount_paid'), 2),
            ];
        })->sortByDesc('outstanding')->values()->all();
    }

    /** @param array<string, mixed> $account */
    private function isCashOrBank(array $account): bool
    {
        $search = Str::lower($account['name'].' '.($account['sub_type'] ?? ''));

        return $account['type'] === 'Asset' && (str_contains($search, 'cash') || str_contains($search, 'bank'));
    }

    private function denyApproval(Request $request): ?JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }
        if (! in_array($request->session()->get('demo_user.role'), ['Administrator', 'Accountant'], true)) {
            return response()->json(['message' => 'Only Administrators and Accountants can post customer payments.'], 403);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function actor(Request $request): array
    {
        return (array) $request->session()->get('demo_user', []);
    }

    /** @return array{id: int|string|null, name: string} */
    private function actorSnapshot(Request $request): array
    {
        $actor = $this->actor($request);

        return ['id' => $actor['id'] ?? null, 'name' => (string) ($actor['name'] ?? 'Demo User')];
    }

    /** @param array<string, array<int, string>> $errors */
    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['message' => 'Please correct payment fields.', 'errors' => $errors], 422);
    }
}
