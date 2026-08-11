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

class SalesRevenueController extends Controller
{
    public function index(Request $request, SalesDataService $sales): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $customers = $sales->customers();
        $invoices = $sales->invoices();
        $posted = collect($invoices)->reject(fn (array $invoice): bool => $invoice['status'] === 'Draft');
        $year = now()->format('Y');
        $month = now()->format('Y-m');
        $monthlyRevenue = collect(range(1, 12))->mapWithKeys(fn (int $monthNumber): array => [
            $monthNumber => round((float) $posted->filter(
                fn (array $invoice): bool => str_starts_with($invoice['invoice_date'], sprintf('%s-%02d', $year, $monthNumber))
            )->sum('total'), 2),
        ])->all();
        $revenueByCustomer = $posted->groupBy('customer_id')->map(function ($customerInvoices): array {
            $first = $customerInvoices->first();

            return [
                'name' => $first['customer_name'],
                'total' => round((float) $customerInvoices->sum('total'), 2),
            ];
        })->sortByDesc('total')->take(5)->values()->all();

        return view('sales-revenue', [
            'user' => $request->session()->get('demo_user'),
            'customers' => $customers,
            'invoices' => $invoices,
            'recentInvoices' => array_slice($invoices, 0, 6),
            'metrics' => [
                'total_revenue' => round((float) $posted->filter(fn (array $invoice): bool => str_starts_with($invoice['invoice_date'], $year))->sum('total'), 2),
                'month_revenue' => round((float) $posted->filter(fn (array $invoice): bool => str_starts_with($invoice['invoice_date'], $month))->sum('total'), 2),
                'collected' => round((float) $posted->sum('amount_paid'), 2),
                'outstanding' => round((float) $posted->sum('remaining_balance'), 2),
            ],
            'hasPostedSales' => $posted->isNotEmpty(),
            'monthlyRevenue' => $monthlyRevenue,
            'monthlyRevenueMax' => max([0, ...array_values($monthlyRevenue)]),
            'revenueByCustomer' => $revenueByCustomer,
            'customerRevenueMax' => max([0, ...array_column($revenueByCustomer, 'total')]),
        ]);
    }

    public function storeCustomer(Request $request, SalesDataService $sales, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'billing_address' => ['nullable', 'string', 'max:300'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'credit_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Please correct the customer fields.');
        }

        try {
            $customer = $sales->createCustomer([
                ...$validator->validated(),
                'code' => Str::upper(trim($validator->validated()['code'])),
                'name' => trim($validator->validated()['name']),
                'status' => 'Active',
            ]);
            $auditLogs->record($this->actor($request), 'created', (string) $customer['id'], ['code' => $customer['code']], 'customer');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Customer created.', 'customer' => $customer], 201);
    }

    public function storeInvoice(Request $request, SalesDataService $sales, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', 'integer'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'reference' => ['nullable', 'string', 'max:50'],
            'memo' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:180'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), 'Please correct the invoice fields.');
        }

        $validated = $validator->validated();
        $customer = collect($sales->customers())->first(
            fn (array $item): bool => (int) $item['id'] === (int) $validated['customer_id'] && $item['status'] === 'Active'
        );
        if (! $customer) {
            return $this->validationError(['customer_id' => ['Select an active customer.']], 'Please correct the invoice fields.');
        }

        $subtotal = 0.0;
        $tax = 0.0;
        $lines = collect($validated['lines'])->values()->map(function (array $line, int $index) use (&$subtotal, &$tax): array {
            $lineSubtotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $lineTax = round($lineSubtotal * ((float) $line['tax_rate'] / 100), 2);
            $subtotal += $lineSubtotal;
            $tax += $lineTax;

            return [
                'id' => $index + 1,
                'description' => trim($line['description']),
                'quantity' => round((float) $line['quantity'], 2),
                'unit_price' => round((float) $line['unit_price'], 2),
                'tax_rate' => round((float) $line['tax_rate'], 2),
                'subtotal' => $lineSubtotal,
                'tax' => $lineTax,
                'total' => round($lineSubtotal + $lineTax, 2),
            ];
        })->all();
        if ($subtotal <= 0) {
            return $this->validationError(['lines' => ['Invoice total must be greater than zero.']], 'Please correct the invoice fields.');
        }

        try {
            $invoice = $sales->createInvoice([
                'customer_id' => $customer['id'],
                'customer_code' => $customer['code'],
                'customer_name' => $customer['name'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'reference' => trim((string) ($validated['reference'] ?? '')),
                'memo' => trim((string) ($validated['memo'] ?? '')),
                'lines' => $lines,
                'subtotal' => round($subtotal, 2),
                'tax' => round($tax, 2),
                'discount' => 0,
                'total' => round($subtotal + $tax, 2),
                'created_by' => $this->actorSnapshot($request),
            ]);
            $auditLogs->record($this->actor($request), 'created_draft', $invoice['invoice_number'], [], 'sales_invoice');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Invoice saved as draft.', 'invoice' => $invoice], 201);
    }

    public function postInvoice(
        Request $request,
        string $invoiceNumber,
        SalesDataService $sales,
        AccountDataService $accounts,
        JournalEntryDataService $journals,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyApproval($request)) {
            return $response;
        }

        try {
            $invoice = $sales->findInvoice($invoiceNumber);
            if ($invoice['status'] !== 'Draft') {
                throw new RuntimeException('Only draft invoices can be posted.');
            }

            $activeAccounts = collect($accounts->all(['status' => 'Active']));
            $receivable = $activeAccounts->first(fn (array $account): bool => $account['type'] === 'Asset' && str_contains(Str::lower($account['name']), 'receivable'));
            $revenue = $activeAccounts->first(fn (array $account): bool => $account['type'] === 'Revenue');
            $outputTax = $activeAccounts->first(fn (array $account): bool => str_contains(Str::lower($account['name']), 'output tax'));
            if (! $receivable || ! $revenue) {
                throw new RuntimeException('Posting needs active Accounts Receivable and Revenue accounts in Chart of Accounts.');
            }
            if ((float) $invoice['tax'] > 0 && ! $outputTax) {
                throw new RuntimeException('Posting a taxed invoice needs an active Output Tax account.');
            }

            $existingJournal = collect($journals->all())->first(
                fn (array $entry): bool => $entry['source_type'] === 'Invoice' && $entry['reference'] === $invoiceNumber
            );
            if ($existingJournal) {
                if ($existingJournal['status'] !== 'Posted') {
                    throw new RuntimeException('A journal entry already exists for this invoice but is not posted. Review it in Journal Entries.');
                }
                $journal = $existingJournal;
            } else {
                $lines = [[
                    'id' => 1,
                    'account_code' => (string) $receivable['code'],
                    'account_name' => $receivable['name'],
                    'description' => 'Invoice '.$invoiceNumber,
                    'party_reference' => $invoice['customer_code'],
                    'cost_center' => '',
                    'debit' => (float) $invoice['total'],
                    'credit' => 0,
                ], [
                    'id' => 2,
                    'account_code' => (string) $revenue['code'],
                    'account_name' => $revenue['name'],
                    'description' => 'Sales revenue '.$invoiceNumber,
                    'party_reference' => $invoice['customer_code'],
                    'cost_center' => '',
                    'debit' => 0,
                    'credit' => (float) $invoice['subtotal'],
                ]];
                if ((float) $invoice['tax'] > 0) {
                    $lines[] = [
                        'id' => 3,
                        'account_code' => (string) $outputTax['code'],
                        'account_name' => $outputTax['name'],
                        'description' => 'Output tax '.$invoiceNumber,
                        'party_reference' => $invoice['customer_code'],
                        'cost_center' => '',
                        'debit' => 0,
                        'credit' => (float) $invoice['tax'],
                    ];
                }

                $journal = $journals->create([
                    'date' => $invoice['invoice_date'],
                    'reference' => $invoiceNumber,
                    'description' => 'Sales invoice '.$invoiceNumber.' - '.$invoice['customer_name'],
                    'source_type' => 'Invoice',
                    'lines' => $lines,
                    'total_debit' => (float) $invoice['total'],
                    'total_credit' => (float) $invoice['total'],
                    'created_by' => $this->actorSnapshot($request),
                ]);
                $journals->submitForReview($journal['journal_number']);
                $journal = $journals->post($journal['journal_number'], $this->actor($request));
                $accounts->applyJournalLines($journal['lines']);
                $auditLogs->record($this->actor($request), 'posted_from_invoice', $journal['journal_number'], ['invoice_number' => $invoiceNumber]);
            }

            $invoice = $sales->markPosted($invoiceNumber, $journal['journal_number'], $this->actor($request));
            $auditLogs->record($this->actor($request), 'posted', $invoiceNumber, ['journal_entry_id' => $journal['journal_number']], 'sales_invoice');
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Invoice posted and journal entry created.', 'invoice' => $invoice, 'journal' => $journal]);
    }

    public function printInvoice(Request $request, string $invoiceNumber, SalesDataService $sales): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        try {
            $invoice = $sales->findInvoice($invoiceNumber);
        } catch (RuntimeException) {
            abort(404, 'The sales invoice could not be found.');
        }

        return view('sales-invoice-print', ['invoice' => $invoice]);
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
            return response()->json(['message' => 'Only Administrators and Accountants can post invoices.'], 403);
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
        $status = $exception->getMessage() === 'The sales invoice could not be found.' ? 404 : 409;

        return response()->json(['message' => $exception->getMessage()], $status);
    }
}
