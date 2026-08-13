<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingPostingService;
use App\Services\DemoAccessService;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\AuditLogDataService;
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
    public function index(Request $request, SalesDataService $sales, AccountDataService $accounts, DemoAccessService $access): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $invoices = $sales->invoices();
        $payments = $sales->payments();
        if ($access->isViewer($request)) {
            $invoices = array_values(array_filter($invoices, static fn (array $invoice): bool => $invoice['status'] !== 'Draft'));
            $payments = array_values(array_filter($payments, static fn (array $payment): bool => $payment['status'] === 'Posted'));
        }
        $customers = $sales->customers();
        $today = now()->toDateString();
        $openInvoices = collect($invoices)->filter(static fn (array $invoice): bool => $invoice['status'] !== 'Draft' && (float) $invoice['remaining_balance'] > 0);
        $activeAccounts = collect($accounts->all(['status' => 'Active']));

        return view('accounts-receivable', [
            'user' => $request->session()->get('demo_user'),
            'invoices' => $invoices,
            'customers' => $customers,
            'payments' => $payments,
            'cashAccounts' => $activeAccounts->filter(fn (array $account): bool => $this->isCashOrBank($account))->values()->all(),
            'postingAccounts' => $activeAccounts->values()->all(),
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

    public function storePayment(Request $request, SalesDataService $sales, AccountDataService $accounts, AuditLogDataService $auditLogs): JsonResponse
    {
        $prepared = $this->preparePayment($request->all(), $sales, $accounts);
        if ($prepared instanceof JsonResponse) {
            return $prepared;
        }

        try {
            $payment = $sales->createPayment([...$prepared, 'created_by' => $this->actorSnapshot($request)]);
            $auditLogs->record($this->actor($request), 'created_draft', $payment['receipt_number'], ['amount' => $payment['amount']], 'customer_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Customer payment draft created.', 'payment' => $payment], 201);
    }

    public function updatePayment(Request $request, string $receiptNumber, SalesDataService $sales, AccountDataService $accounts, AuditLogDataService $auditLogs): JsonResponse
    {
        $prepared = $this->preparePayment($request->all(), $sales, $accounts, $receiptNumber);
        if ($prepared instanceof JsonResponse) {
            return $prepared;
        }

        try {
            $payment = $sales->updatePayment($receiptNumber, $prepared);
            $auditLogs->record($this->actor($request), 'updated_draft', $receiptNumber, ['amount' => $payment['amount']], 'customer_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Customer payment draft updated.', 'payment' => $payment]);
    }

    public function deletePayment(Request $request, string $receiptNumber, SalesDataService $sales, AuditLogDataService $auditLogs): JsonResponse
    {
        try {
            $sales->deletePayment($receiptNumber);
            $auditLogs->record($this->actor($request), 'deleted_draft', $receiptNumber, [], 'customer_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Customer payment draft deleted.']);
    }

    public function submitPaymentForReview(Request $request, string $receiptNumber, SalesDataService $sales, AuditLogDataService $auditLogs): JsonResponse
    {
        try {
            $payment = $sales->submitPaymentForReview($receiptNumber, $this->actor($request));
            $auditLogs->record($this->actor($request), 'submitted_for_review', $receiptNumber, [], 'customer_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Customer payment submitted for review.', 'payment' => $payment]);
    }

    public function returnPaymentToDraft(Request $request, string $receiptNumber, SalesDataService $sales, AuditLogDataService $auditLogs): JsonResponse
    {
        try {
            $payment = $sales->returnPaymentToDraft($receiptNumber);
            $auditLogs->record($this->actor($request), 'returned_to_draft', $receiptNumber, [], 'customer_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Customer payment returned to draft.', 'payment' => $payment]);
    }

    public function postPayment(Request $request, string $receiptNumber, SalesDataService $sales, AccountDataService $accounts, AccountingPostingService $posting, AuditLogDataService $auditLogs): JsonResponse
    {
        try {
            $saved = $sales->findPayment($receiptNumber);
            if (! in_array($saved['status'], ['Draft', 'For Review'], true)) {
                throw new RuntimeException('Only draft or for-review customer payments can be posted.');
            }
            $prepared = $this->preparePayment($saved, $sales, $accounts, $receiptNumber);
            if ($prepared instanceof JsonResponse) {
                return $prepared;
            }
            $customer = collect($sales->customers())->firstWhere('id', $prepared['customer_id']);
            $journal = $posting->postCustomerPayment($customer, $prepared, $this->actor($request), $request->input('posting'));
            $payment = $sales->markPaymentPosted($receiptNumber, $journal['journal_number'], $this->actor($request));
            $auditLogs->record($this->actor($request), 'posted', $receiptNumber, ['journal_entry_id' => $journal['journal_number'], 'amount' => $payment['amount']], 'customer_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Customer payment posted.', 'payment' => $payment, 'journal' => $journal]);
    }

    public function csv(Request $request, SalesDataService $sales, DemoAccessService $access): StreamedResponse|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $search = Str::lower(trim((string) $request->query('search', '')));
        $status = trim((string) $request->query('status', ''));
        $viewer = $access->isViewer($request);
        $invoices = array_values(array_filter($sales->invoices(), static function (array $invoice) use ($search, $status, $viewer): bool {
            $haystack = Str::lower(implode(' ', [$invoice['invoice_number'], $invoice['customer_name'], $invoice['reference'] ?? '']));

            return (! $viewer || $invoice['status'] !== 'Draft') && ($search === '' || str_contains($haystack, $search))
                && ($status === '' || $invoice['display_status'] === $status);
        }));

        return response()->streamDownload(static function () use ($invoices): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Invoice Number', 'Date', 'Due Date', 'Customer', 'Amount', 'Paid', 'Balance', 'Status']);
            foreach ($invoices as $invoice) {
                fputcsv($output, [$invoice['invoice_number'], $invoice['invoice_date'], $invoice['due_date'], $invoice['customer_name'], number_format((float) $invoice['total'], 2, '.', ''), number_format((float) $invoice['amount_paid'], 2, '.', ''), number_format((float) $invoice['remaining_balance'], 2, '.', ''), $invoice['display_status']]);
            }
            fclose($output);
        }, 'accounts-receivable.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed>|JsonResponse */
    private function preparePayment(array $input, SalesDataService $sales, AccountDataService $accounts, ?string $exceptReceipt = null): array|JsonResponse
    {
        $validator = Validator::make($input, [
            'request_token' => ['required', 'uuid'], 'customer_id' => ['required', 'integer'], 'payment_date' => ['required', 'date_format:Y-m-d'],
            'cash_account_code' => ['required', 'string'], 'reference' => ['nullable', 'string', 'max:50'], 'memo' => ['nullable', 'string', 'max:255'],
            'allocations' => ['required', 'array', 'min:1'], 'allocations.*.invoice_number' => ['required', 'string'], 'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $validated = $validator->validated();
        if (collect($sales->payments())->contains(fn (array $payment): bool => $payment['receipt_number'] !== $exceptReceipt && ($payment['request_token'] ?? null) === $validated['request_token'])) {
            return response()->json(['message' => 'This payment request already exists.'], 409);
        }
        $customer = collect($sales->customers())->first(fn (array $item): bool => (int) $item['id'] === (int) $validated['customer_id'] && $item['status'] === 'Active');
        if (! $customer) {
            return $this->validationError(['customer_id' => ['Select an active customer.']]);
        }
        $cashAccount = collect($accounts->all(['status' => 'Active']))->first(fn (array $account): bool => (string) $account['code'] === (string) $validated['cash_account_code'] && $this->isCashOrBank($account));
        if (! $cashAccount) {
            return $this->validationError(['cash_account_code' => ['Select an active cash or bank account.']]);
        }
        $invoices = collect($sales->invoices())->keyBy('invoice_number');
        $seen = [];
        $allocations = [];
        $total = 0.0;
        foreach ($validated['allocations'] as $index => $allocation) {
            $number = trim($allocation['invoice_number']);
            $amount = round((float) $allocation['amount'], 2);
            $invoice = $invoices->get($number);
            if (! $invoice || (int) $invoice['customer_id'] !== (int) $customer['id']) {
                return $this->validationError(["allocations.{$index}.invoice_number" => ['Select an open invoice for this customer.']]);
            }
            if (isset($seen[$number])) {
                return $this->validationError(["allocations.{$index}.invoice_number" => ['Allocate each invoice only once.']]);
            }
            if ($invoice['status'] === 'Draft' || (float) $invoice['remaining_balance'] <= 0) {
                return $this->validationError(["allocations.{$index}.invoice_number" => ['Invoice is not open for payment.']]);
            }
            if ($amount > (float) $invoice['remaining_balance'] + 0.004) {
                return $this->validationError(["allocations.{$index}.amount" => ['Payment cannot exceed invoice remaining balance.']]);
            }
            $seen[$number] = true;
            $total += $amount;
            $allocations[] = ['invoice_number' => $number, 'invoice_total' => (float) $invoice['total'], 'amount' => $amount];
        }

        return ['request_token' => $validated['request_token'], 'payment_date' => $validated['payment_date'], 'customer_id' => $customer['id'], 'customer_code' => $customer['code'], 'customer_name' => $customer['name'], 'cash_account_code' => $cashAccount['code'], 'cash_account_name' => $cashAccount['name'], 'reference' => trim((string) ($validated['reference'] ?? '')), 'memo' => trim((string) ($validated['memo'] ?? '')), 'amount' => round($total, 2), 'allocations' => $allocations];
    }

    private function customerBalances(array $customers, array $invoices): array
    {
        return collect($customers)->map(function (array $customer) use ($invoices): array {
            $customerInvoices = collect($invoices)->filter(fn (array $invoice): bool => (int) $invoice['customer_id'] === (int) $customer['id'] && $invoice['status'] !== 'Draft');

            return [...$customer, 'invoice_count' => $customerInvoices->count(), 'outstanding' => round((float) $customerInvoices->sum('remaining_balance'), 2), 'collected' => round((float) $customerInvoices->sum('amount_paid'), 2)];
        })->sortByDesc('outstanding')->values()->all();
    }

    private function isCashOrBank(array $account): bool
    {
        $search = Str::lower($account['name'].' '.($account['sub_type'] ?? ''));

        return $account['type'] === 'Asset' && (str_contains($search, 'cash') || str_contains($search, 'bank'));
    }

    private function actor(Request $request): array
    {
        return (array) $request->session()->get('demo_user', []);
    }

    private function actorSnapshot(Request $request): array
    {
        $actor = $this->actor($request);

        return ['id' => $actor['id'] ?? null, 'name' => (string) ($actor['name'] ?? 'Demo User')];
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['message' => 'Please correct payment fields.', 'errors' => $errors], 422);
    }

    private function persistenceError(RuntimeException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], str_contains($exception->getMessage(), 'could not be found') ? 404 : 409);
    }
}
