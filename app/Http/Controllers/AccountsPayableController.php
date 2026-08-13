<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingPostingService;
use App\Services\DemoAccessService;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\PurchaseDataService;
use App\Services\DemoData\TaxCodeDataService;
use App\Services\Exports\AccountingPdfExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountsPayableController extends Controller
{
    public function index(Request $request, PurchaseDataService $purchases, AccountDataService $accounts, TaxCodeDataService $taxCodes, DemoAccessService $access): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $vendors = $purchases->vendors();
        $bills = $purchases->bills();
        $payments = $purchases->payments();
        if ($access->isViewer($request)) {
            $bills = array_values(array_filter($bills, static fn (array $bill): bool => $bill['status'] !== 'Draft'));
            $payments = array_values(array_filter($payments, static fn (array $payment): bool => $payment['status'] === 'Posted'));
        }
        $today = now()->toDateString();
        $openBills = collect($bills)->filter(
            static fn (array $bill): bool => $bill['status'] !== 'Draft' && (float) $bill['remaining_balance'] > 0
        );
        $activeAccounts = collect($accounts->all(['status' => 'Active']));

        return view('accounts-payable', [
            'user' => $request->session()->get('demo_user'),
            'vendors' => $vendors,
            'bills' => $bills,
            'payments' => $payments,
            'cashAccounts' => $activeAccounts->filter(fn (array $account): bool => $this->isCashOrBank($account))->values()->all(),
            'purchaseAccounts' => $activeAccounts->filter(fn (array $account): bool => $this->isPurchaseAccount($account))->values()->all(),
            'postingAccounts' => $activeAccounts->values()->all(),
            'metrics' => [
                'payable' => round((float) $openBills->sum('remaining_balance'), 2),
                'overdue' => round((float) $openBills->filter(static fn (array $bill): bool => $bill['due_date'] < $today)->sum('remaining_balance'), 2),
                'bill_count' => count($bills),
                'paid_count' => collect($bills)->where('display_status', 'Paid')->count(),
                'vendor_count' => count($vendors),
                'active_vendor_count' => collect($vendors)->where('status', 'Active')->count(),
            ],
            'vendorBalances' => $this->vendorBalances($vendors, $bills),
            'vatRates' => $taxCodes->activeVatRates(),
        ]);
    }

    public function storeVendor(Request $request, PurchaseDataService $purchases, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $validator = $this->vendorValidator($request);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Please correct vendor fields.');
        }

        try {
            $vendor = $purchases->createVendor([...$this->vendorAttributes($validator->validated()), 'status' => 'Active']);
            $auditLogs->record($this->actor($request), 'created', (string) $vendor['id'], ['code' => $vendor['code']], 'vendor');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor created.', 'vendor' => $vendor], 201);
    }

    public function updateVendor(Request $request, int $id, PurchaseDataService $purchases, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $validator = $this->vendorValidator($request);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Please correct vendor fields.');
        }

        try {
            $vendor = $purchases->updateVendor($id, $this->vendorAttributes($validator->validated()));
            $auditLogs->record($this->actor($request), 'updated', (string) $vendor['id'], ['code' => $vendor['code']], 'vendor');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor updated.', 'vendor' => $vendor]);
    }

    public function vendorStatus(Request $request, int $id, PurchaseDataService $purchases, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), ['status' => ['required', 'in:Active,Inactive']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Select valid vendor status.');
        }

        try {
            $vendor = $purchases->updateVendorStatus($id, $validator->validated()['status']);
            $auditLogs->record($this->actor($request), 'status_changed', (string) $vendor['id'], ['status' => $vendor['status']], 'vendor');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor status updated.', 'vendor' => $vendor]);
    }

    public function storeBill(
        Request $request,
        PurchaseDataService $purchases,
        AccountDataService $accounts,
        TaxCodeDataService $taxCodes,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $prepared = $this->prepareBill($request, $purchases, $accounts, $taxCodes);
        if ($prepared instanceof JsonResponse) {
            return $prepared;
        }

        try {
            $bill = $purchases->createBill([...$prepared, 'created_by' => $this->actorSnapshot($request)]);
            $auditLogs->record($this->actor($request), 'created_draft', $bill['bill_number'], [], 'vendor_bill');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor bill saved as draft.', 'bill' => $bill], 201);
    }

    public function updateBill(
        Request $request,
        string $billNumber,
        PurchaseDataService $purchases,
        AccountDataService $accounts,
        TaxCodeDataService $taxCodes,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $prepared = $this->prepareBill($request, $purchases, $accounts, $taxCodes);
        if ($prepared instanceof JsonResponse) {
            return $prepared;
        }

        try {
            $bill = $purchases->updateBill($billNumber, $prepared);
            $auditLogs->record($this->actor($request), 'updated_draft', $bill['bill_number'], [], 'vendor_bill');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Draft vendor bill updated.', 'bill' => $bill]);
    }

    public function postBill(
        Request $request,
        string $billNumber,
        PurchaseDataService $purchases,
        AccountingPostingService $posting,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyApproval($request, 'post vendor bills')) {
            return $response;
        }

        try {
            $bill = $purchases->findBill($billNumber);
            if ($bill['status'] !== 'Draft') {
                throw new RuntimeException('Only draft vendor bills can be posted.');
            }

            $journal = $posting->postVendorBill($bill, $this->actor($request), $request->input('posting'));

            $bill = $purchases->markPosted($billNumber, $journal['journal_number'], $this->actor($request));
            $auditLogs->record($this->actor($request), 'posted', $billNumber, ['journal_entry_id' => $journal['journal_number']], 'vendor_bill');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor bill posted and journal entry created.', 'bill' => $bill, 'journal' => $journal]);
    }

    public function storePayment(
        Request $request,
        PurchaseDataService $purchases,
        AccountDataService $accounts,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        $prepared = $this->preparePayment($request->all(), $purchases, $accounts);
        if ($prepared instanceof JsonResponse) {
            return $prepared;
        }
        try {
            $payment = $purchases->createPayment([...$prepared, 'created_by' => $this->actorSnapshot($request)]);
            $auditLogs->record($this->actor($request), 'created_draft', $payment['payment_number'], ['amount' => $payment['amount']], 'vendor_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor payment draft created.', 'payment' => $payment], 201);
    }

    public function updatePayment(Request $request, string $paymentNumber, PurchaseDataService $purchases, AccountDataService $accounts, AuditLogDataService $auditLogs): JsonResponse
    {
        $prepared = $this->preparePayment($request->all(), $purchases, $accounts, $paymentNumber);
        if ($prepared instanceof JsonResponse) {
            return $prepared;
        }
        try {
            $payment = $purchases->updatePayment($paymentNumber, $prepared);
            $auditLogs->record($this->actor($request), 'updated_draft', $paymentNumber, ['amount' => $payment['amount']], 'vendor_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor payment draft updated.', 'payment' => $payment]);
    }

    public function deletePayment(Request $request, string $paymentNumber, PurchaseDataService $purchases, AuditLogDataService $auditLogs): JsonResponse
    {
        try {
            $purchases->deletePayment($paymentNumber);
            $auditLogs->record($this->actor($request), 'deleted_draft', $paymentNumber, [], 'vendor_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor payment draft deleted.']);
    }

    public function submitPaymentForReview(Request $request, string $paymentNumber, PurchaseDataService $purchases, AuditLogDataService $auditLogs): JsonResponse
    {
        try {
            $payment = $purchases->submitPaymentForReview($paymentNumber, $this->actor($request));
            $auditLogs->record($this->actor($request), 'submitted_for_review', $paymentNumber, [], 'vendor_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor payment submitted for review.', 'payment' => $payment]);
    }

    public function returnPaymentToDraft(Request $request, string $paymentNumber, PurchaseDataService $purchases, AuditLogDataService $auditLogs): JsonResponse
    {
        try {
            $payment = $purchases->returnPaymentToDraft($paymentNumber);
            $auditLogs->record($this->actor($request), 'returned_to_draft', $paymentNumber, [], 'vendor_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor payment returned to draft.', 'payment' => $payment]);
    }

    public function postPayment(Request $request, string $paymentNumber, PurchaseDataService $purchases, AccountDataService $accounts, AccountingPostingService $posting, AuditLogDataService $auditLogs): JsonResponse
    {
        try {
            $saved = $purchases->findPayment($paymentNumber);
            if (! in_array($saved['status'], ['Draft', 'For Review'], true)) {
                throw new RuntimeException('Only draft or for-review vendor payments can be posted.');
            }
            $prepared = $this->preparePayment($saved, $purchases, $accounts, $paymentNumber);
            if ($prepared instanceof JsonResponse) {
                return $prepared;
            }
            $vendor = collect($purchases->vendors())->firstWhere('id', $prepared['vendor_id']);
            $journal = $posting->postVendorPayment($vendor, $prepared, $this->actor($request), $request->input('posting'));
            $payment = $purchases->markPaymentPosted($paymentNumber, $journal['journal_number'], $this->actor($request));
            $auditLogs->record($this->actor($request), 'posted', $paymentNumber, ['journal_entry_id' => $journal['journal_number'], 'amount' => $payment['amount']], 'vendor_payment');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Vendor payment posted.', 'payment' => $payment, 'journal' => $journal]);
    }

    public function csv(Request $request, PurchaseDataService $purchases): StreamedResponse|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $bills = $this->filteredBills($request, $purchases);

        return response()->streamDownload(function () use ($bills): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Bill Number', 'Reference', 'Bill Date', 'Due Date', 'Vendor', 'Amount', 'Paid', 'Balance', 'Status']);
            foreach ($bills as $bill) {
                fputcsv($output, [
                    $this->csvCell($bill['bill_number']), $this->csvCell($bill['reference']), $bill['bill_date'], $bill['due_date'],
                    $this->csvCell($bill['vendor_name']), number_format((float) $bill['total'], 2, '.', ''),
                    number_format((float) $bill['amount_paid'], 2, '.', ''), number_format((float) $bill['remaining_balance'], 2, '.', ''),
                    $bill['display_status'],
                ]);
            }
            fclose($output);
        }, 'accounts-payable.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(
        Request $request,
        PurchaseDataService $purchases,
        AccountingPdfExportService $exports,
    ): Response|RedirectResponse {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
        ];
        $content = $exports->accountsPayable($this->filteredBills($request, $purchases), $filters, now());

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="accounts-payable-'.now()->format('Y-m-d').'.pdf"',
            'Content-Length' => (string) strlen($content),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function filteredBills(Request $request, PurchaseDataService $purchases): array
    {
        $search = Str::lower(trim((string) $request->query('search', '')));
        $status = trim((string) $request->query('status', ''));
        $viewer = app(DemoAccessService::class)->isViewer($request);

        return array_values(array_filter($purchases->bills(), static function (array $bill) use ($search, $status, $viewer): bool {
            $haystack = Str::lower(implode(' ', [$bill['bill_number'], $bill['vendor_name'], $bill['reference']]));

            return (! $viewer || $bill['status'] !== 'Draft')
                && ($search === '' || str_contains($haystack, $search))
                && ($status === '' || $bill['display_status'] === $status);
        }));
    }

    /** @return array<string, mixed>|JsonResponse */
    private function preparePayment(array $input, PurchaseDataService $purchases, AccountDataService $accounts, ?string $exceptPayment = null): array|JsonResponse
    {
        $validator = Validator::make($input, [
            'request_token' => ['required', 'uuid'], 'vendor_id' => ['required', 'integer'], 'payment_date' => ['required', 'date_format:Y-m-d'],
            'cash_account_code' => ['required', 'string'], 'reference' => ['nullable', 'string', 'max:50'], 'memo' => ['nullable', 'string', 'max:255'],
            'allocations' => ['required', 'array', 'min:1'], 'allocations.*.bill_number' => ['required', 'string'], 'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Please correct payment fields.');
        }
        $validated = $validator->validated();
        if (collect($purchases->payments())->contains(fn (array $payment): bool => $payment['payment_number'] !== $exceptPayment && ($payment['request_token'] ?? null) === $validated['request_token'])) {
            return response()->json(['message' => 'This vendor payment request already exists.'], 409);
        }
        $vendor = collect($purchases->vendors())->first(fn (array $item): bool => (int) $item['id'] === (int) $validated['vendor_id'] && $item['status'] === 'Active');
        if (! $vendor) {
            return $this->validationError(['vendor_id' => ['Select active vendor.']], 'Please correct payment fields.');
        }
        $cashAccount = collect($accounts->all(['status' => 'Active']))->first(fn (array $account): bool => (string) $account['code'] === (string) $validated['cash_account_code'] && $this->isCashOrBank($account));
        if (! $cashAccount) {
            return $this->validationError(['cash_account_code' => ['Select active cash or bank account.']], 'Please correct payment fields.');
        }
        $bills = collect($purchases->bills())->keyBy('bill_number');
        $seen = [];
        $allocations = [];
        $total = 0.0;
        foreach ($validated['allocations'] as $index => $allocation) {
            $number = trim($allocation['bill_number']);
            $amount = round((float) $allocation['amount'], 2);
            $bill = $bills->get($number);
            if (! $bill || (int) $bill['vendor_id'] !== (int) $vendor['id']) {
                return $this->validationError(["allocations.{$index}.bill_number" => ['Select open bill for this vendor.']], 'Please correct payment fields.');
            }
            if (isset($seen[$number])) {
                return $this->validationError(["allocations.{$index}.bill_number" => ['Allocate each bill only once.']], 'Please correct payment fields.');
            }
            if ($bill['status'] === 'Draft' || (float) $bill['remaining_balance'] <= 0) {
                return $this->validationError(["allocations.{$index}.bill_number" => ['Bill is not open for payment.']], 'Please correct payment fields.');
            }
            if ($amount > (float) $bill['remaining_balance'] + 0.004) {
                return $this->validationError(["allocations.{$index}.amount" => ['Payment cannot exceed bill remaining balance.']], 'Please correct payment fields.');
            }
            $seen[$number] = true;
            $total += $amount;
            $allocations[] = ['bill_number' => $number, 'bill_total' => (float) $bill['total'], 'amount' => $amount];
        }

        return ['request_token' => $validated['request_token'], 'payment_date' => $validated['payment_date'], 'vendor_id' => $vendor['id'], 'vendor_code' => $vendor['code'], 'vendor_name' => $vendor['name'], 'cash_account_code' => $cashAccount['code'], 'cash_account_name' => $cashAccount['name'], 'reference' => trim((string) ($validated['reference'] ?? '')), 'memo' => trim((string) ($validated['memo'] ?? '')), 'amount' => round($total, 2), 'allocations' => $allocations];
    }

    private function vendorValidator(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:300'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function vendorAttributes(array $validated): array
    {
        return [
            ...$validated,
            'code' => Str::upper(trim($validated['code'])),
            'name' => trim($validated['name']),
        ];
    }

    /** @return array<string, mixed>|JsonResponse */
    private function prepareBill(Request $request, PurchaseDataService $purchases, AccountDataService $accounts, TaxCodeDataService $taxCodes): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vendor_id' => ['required', 'integer'],
            'reference' => ['required', 'string', 'max:50'],
            'bill_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:bill_date'],
            'memo' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'array'],
            'attachment.name' => ['required_with:attachment', 'string', 'max:180'],
            'attachment.type' => ['nullable', 'string', 'max:100'],
            'attachment.size' => ['nullable', 'integer', 'min:0', 'max:10485760'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_code' => ['required', 'string'],
            'lines.*.description' => ['required', 'string', 'max:180'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Please correct vendor bill fields.');
        }

        $validated = $validator->validated();
        foreach ($validated['lines'] as $index => $line) {
            if (! collect($taxCodes->activeVatRates())->contains(fn (array $code): bool => abs((float) $code['rate'] - (float) $line['tax_rate']) < 0.005)) {
                return $this->validationError(["lines.{$index}.tax_rate" => ['Select an active configured VAT rate.']], 'Please correct vendor bill fields.');
            }
        }
        $vendor = collect($purchases->vendors())->first(
            fn (array $item): bool => (int) $item['id'] === (int) $validated['vendor_id'] && $item['status'] === 'Active'
        );
        if (! $vendor) {
            return $this->validationError(['vendor_id' => ['Select active vendor.']], 'Please correct vendor bill fields.');
        }

        $activeAccounts = collect($accounts->all(['status' => 'Active']))->keyBy('code');
        $subtotal = 0.0;
        $tax = 0.0;
        $lines = [];
        foreach ($validated['lines'] as $index => $line) {
            $account = $activeAccounts->get((string) $line['account_code']);
            if (! $account || ! $this->isPurchaseAccount($account)) {
                return $this->validationError(["lines.{$index}.account_code" => ['Select active expense or asset account.']], 'Please correct vendor bill fields.');
            }
            $lineSubtotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $lineTax = round($lineSubtotal * ((float) $line['tax_rate'] / 100), 2);
            $subtotal += $lineSubtotal;
            $tax += $lineTax;
            $lines[] = [
                'id' => $index + 1,
                'account_code' => (string) $account['code'],
                'account_name' => $account['name'],
                'description' => trim($line['description']),
                'quantity' => round((float) $line['quantity'], 2),
                'unit_price' => round((float) $line['unit_price'], 2),
                'tax_rate' => round((float) $line['tax_rate'], 2),
                'subtotal' => $lineSubtotal,
                'tax' => $lineTax,
                'total' => round($lineSubtotal + $lineTax, 2),
            ];
        }
        if ($subtotal <= 0) {
            return $this->validationError(['lines' => ['Vendor bill total must be greater than zero.']], 'Please correct vendor bill fields.');
        }

        return [
            'vendor_id' => $vendor['id'],
            'vendor_code' => $vendor['code'],
            'vendor_name' => $vendor['name'],
            'reference' => trim($validated['reference']),
            'bill_date' => $validated['bill_date'],
            'due_date' => $validated['due_date'],
            'memo' => trim((string) ($validated['memo'] ?? '')),
            'attachment' => $validated['attachment'] ?? null,
            'lines' => $lines,
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($subtotal + $tax, 2),
        ];
    }

    /** @param array<int, array<string, mixed>> $vendors
     * @param  array<int, array<string, mixed>>  $bills
     * @return array<int, array<string, mixed>>
     */
    private function vendorBalances(array $vendors, array $bills): array
    {
        return collect($vendors)->map(function (array $vendor) use ($bills): array {
            $vendorBills = collect($bills)->filter(
                fn (array $bill): bool => (int) $bill['vendor_id'] === (int) $vendor['id'] && $bill['status'] !== 'Draft'
            );

            return [
                ...$vendor,
                'bill_count' => $vendorBills->count(),
                'outstanding' => round((float) $vendorBills->sum('remaining_balance'), 2),
                'paid' => round((float) $vendorBills->sum('amount_paid'), 2),
            ];
        })->sortByDesc('outstanding')->values()->all();
    }

    /** @param array<string, mixed> $account */
    private function isCashOrBank(array $account): bool
    {
        $search = Str::lower($account['name'].' '.($account['sub_type'] ?? ''));

        return $account['type'] === 'Asset' && (str_contains($search, 'cash') || str_contains($search, 'bank'));
    }

    /** @param array<string, mixed> $account */
    private function isPurchaseAccount(array $account): bool
    {
        return $account['type'] === 'Expense' || ($account['type'] === 'Asset' && ! $this->isCashOrBank($account) && ! str_contains(Str::lower($account['name']), 'receivable') && ! str_contains(Str::lower($account['name']), 'input tax'));
    }

    /** @param array<string, mixed> $account */
    private function isPayable(array $account): bool
    {
        return $account['type'] === 'Liability' && str_contains(Str::lower($account['name']), 'payable') && ! str_contains(Str::lower($account['name']), 'tax');
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

    private function denyApproval(Request $request, string $action): ?JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }
        if (! in_array($request->session()->get('demo_user.role'), ['Administrator', 'Accountant'], true)) {
            return response()->json(['message' => "Only Administrators and Accountants can {$action}."], 403);
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
    private function validationError(array $errors, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => $errors], 422);
    }

    private function persistenceError(RuntimeException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], str_contains($exception->getMessage(), 'could not be found') ? 404 : 409);
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
