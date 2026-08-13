@extends('layouts.accounting', ['pageTitle' => 'Accounts Receivable', 'activePage' => 'accounts-receivable'])

@section('content')
<main
    id="accounts-receivable-page"
    class="p-4 sm:p-5"
    data-payment-url="{{ route('accounts-receivable.payments.store') }}"
    data-export-url="{{ route('accounts-receivable.export.csv') }}"
    data-sales-url="{{ route('sales-revenue') }}"
    data-user-role="{{ $user['role'] }}"
>
    <div class="dashboard-enter print:hidden">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500">
            <span>Sales</span><span class="text-slate-300">/</span>
            <span class="font-medium text-slate-700">Accounts Receivable</span>
        </nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Accounts Receivable</h1>
                <p class="mt-1 text-sm text-slate-500">Manage customer invoices and incoming payments</p>
            </div>
            @if ($demoCan('drafts.manage'))
                <a href="{{ route('sales-revenue', ['new' => 'invoice']) }}" class="apm-primary-button">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> New Invoice
                </a>
            @endif
        </div>
    </div>

    <section aria-label="Receivable summary" class="mx-auto mt-5 grid max-w-3xl grid-cols-1 gap-3 sm:grid-cols-2 xl:max-w-none xl:grid-cols-4">
        <article class="apm-summary-card dashboard-enter [animation-delay:50ms]">
            <p>Total Receivable</p>
            <strong>₱{{ number_format($metrics['receivable'], 2) }}</strong>
            <span>Open posted balances</span>
        </article>
        <article class="apm-summary-card dashboard-enter [animation-delay:100ms]">
            <p>Overdue</p>
            <strong>₱{{ number_format($metrics['overdue'], 2) }}</strong>
            <span>Past due balances</span>
        </article>
        <article class="apm-summary-card dashboard-enter [animation-delay:150ms]">
            <p>Invoices</p>
            <strong>{{ $metrics['invoice_count'] }}</strong>
            <span>{{ $metrics['paid_count'] }} paid</span>
        </article>
        <article class="apm-summary-card dashboard-enter [animation-delay:200ms]">
            <p>Customers</p>
            <strong>{{ $metrics['customer_count'] }}</strong>
            <span>{{ $metrics['active_customer_count'] }} active</span>
        </article>
    </section>

    <div class="mt-5 flex overflow-x-auto border-b border-slate-200 print:hidden" role="tablist" aria-label="Accounts Receivable sections">
        <button type="button" class="apm-tab border-blue-600 text-blue-600" data-ar-tab="invoices" role="tab" aria-selected="true">Invoices</button>
        <button type="button" class="apm-tab" data-ar-tab="customers" role="tab" aria-selected="false">Customers</button>
        <button type="button" class="apm-tab" data-ar-tab="payments" role="tab" aria-selected="false">Payments</button>
        <button type="button" class="apm-tab" data-ar-tab="aging" role="tab" aria-selected="false">Aging Report</button>
    </div>

    <section data-ar-panel="invoices" class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:250ms]">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">All Invoices</h2>
                <p class="mt-0.5 text-xs text-slate-500"><span id="ar-invoice-count">{{ count($invoices) }}</span> records</p>
            </div>
            <a id="ar-export" href="{{ route('accounts-receivable.export.csv') }}" class="apm-outline-button">
                <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV
            </a>
        </header>
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row">
            <label class="relative max-w-sm flex-1">
                <span class="sr-only">Search invoices</span>
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-3 left-3 text-xs text-slate-400" aria-hidden="true"></i>
                <input id="ar-search" type="search" placeholder="Search invoice or customer..." class="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-8 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100">
            </label>
            <label>
                <span class="sr-only">Filter by status</span>
                <select id="ar-status" class="h-9 min-w-32 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400">
                    <option value="">All Statuses</option>
                    <option>Draft</option>
                    <option>Unpaid</option>
                    <option>Partially Paid</option>
                    <option>Paid</option>
                    <option>Overdue</option>
                </select>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Invoice No.</th>
                        <th class="px-4 py-3 font-semibold">Date</th>
                        <th class="px-4 py-3 font-semibold">Due Date</th>
                        <th class="px-4 py-3 font-semibold">Customer</th>
                        <th class="px-4 py-3 text-right font-semibold">Amount</th>
                        <th class="px-4 py-3 text-right font-semibold">Paid</th>
                        <th class="px-4 py-3 text-right font-semibold">Balance</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody id="ar-invoice-rows" class="text-slate-700"></tbody>
            </table>
        </div>
        <footer class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <span id="ar-page-summary">Showing 0 records</span>
            <div class="flex gap-1">
                <button id="ar-prev" type="button" class="apm-page-button">‹ Prev</button>
                <span id="ar-page-number" class="grid min-w-8 place-items-center rounded bg-blue-600 px-2 text-[10px] text-white">1</span>
                <button id="ar-next" type="button" class="apm-page-button">Next ›</button>
            </div>
        </footer>
    </section>

    <section data-ar-panel="payments" hidden class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Customer Payments</h2><p class="mt-0.5 text-xs text-slate-500">Draft, review, and posted receipts</p></header>
        <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="px-4 py-3">Receipt</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Journal</th><th class="px-4 py-3">Actions</th></tr></thead><tbody>
            @forelse ($payments as $payment)<tr class="apm-table-row"><td class="apm-code">{{ $payment['receipt_number'] }}</td><td>{{ $payment['payment_date'] }}</td><td>{{ $payment['customer_name'] }}</td><td class="apm-money text-right">₱{{ number_format($payment['amount'], 2) }}</td><td>{{ $payment['status'] }}</td><td class="apm-code">{{ $payment['journal_entry_id'] ?? '—' }}</td><td class="apm-actions">
                @if ($payment['status'] === 'Draft' && $demoCan('drafts.manage'))<button data-ar-edit-payment="{{ $payment['receipt_number'] }}">Edit</button><button data-ar-payment-action="delete" data-payment-number="{{ $payment['receipt_number'] }}">Delete</button>@endif
                @if ($payment['status'] === 'Draft' && $demoCan('drafts.submit'))<button data-ar-payment-action="submit-review" data-payment-number="{{ $payment['receipt_number'] }}">Submit</button>@endif
                @if ($payment['status'] === 'For Review' && $demoCan('transactions.approve'))<button data-ar-payment-action="return-draft" data-payment-number="{{ $payment['receipt_number'] }}">Return</button><button data-ar-payment-action="post" data-payment-number="{{ $payment['receipt_number'] }}">Post</button>@endif
                @if ($payment['status'] === 'Posted')<span class="text-slate-400">Immutable</span>@endif
            </td></tr>@empty<tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No customer payments.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section data-ar-panel="customers" hidden class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Customers</h2>
                <p class="mt-0.5 text-xs text-slate-500">Customer balances from posted invoices</p>
            </div>
            <a href="{{ route('sales-revenue') }}" class="apm-outline-button"><i class="fa-solid fa-users" aria-hidden="true"></i> Manage Customers</a>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase">
                    <tr><th class="px-4 py-3">Code</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Terms</th><th class="px-4 py-3 text-right">Invoices</th><th class="px-4 py-3 text-right">Outstanding</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($customerBalances as $customer)
                        <tr class="apm-table-row">
                            <td class="apm-code text-blue-600">{{ $customer['code'] }}</td>
                            <td class="font-medium text-slate-800">{{ $customer['name'] }}<p class="mt-1 text-[10px] font-normal text-slate-500">{{ $customer['email'] ?: 'No email' }}</p></td>
                            <td>{{ $customer['contact_person'] ?: '—' }}</td>
                            <td>{{ $customer['credit_terms_days'] }} days</td>
                            <td class="apm-money text-right">{{ $customer['invoice_count'] }}</td>
                            <td class="apm-money text-right">₱{{ number_format($customer['outstanding'], 2) }}</td>
                            <td><span class="{{ $customer['status'] === 'Active' ? 'apm-active-badge' : 'inline-flex rounded-md bg-slate-100 px-2 py-1 text-[10px] text-slate-600' }}">{{ $customer['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-14 text-center"><i class="fa-solid fa-users text-2xl text-slate-300" aria-hidden="true"></i><p class="mt-2 text-sm font-medium text-slate-600">No customers yet</p><a href="{{ route('sales-revenue') }}" class="mt-2 inline-block text-xs text-blue-600 hover:underline">Create customer in Sales</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section data-ar-panel="aging" hidden class="dashboard-enter mt-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 class="text-sm font-semibold text-slate-900">Accounts Receivable Aging</h2><p class="mt-1 text-xs text-slate-500">Outstanding invoices grouped by due date</p></div>
            <label class="text-xs font-medium text-slate-600">As of date
                <input id="ar-as-of" type="date" value="{{ now()->toDateString() }}" class="mt-1 h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400 sm:mt-0 sm:ml-2">
            </label>
        </div>
        <div id="ar-aging-cards" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5"></div>
        <article class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase"><tr><th class="px-4 py-3">Customer</th><th class="px-4 py-3 text-right">Current</th><th class="px-4 py-3 text-right">1–30</th><th class="px-4 py-3 text-right">31–60</th><th class="px-4 py-3 text-right">61–90</th><th class="px-4 py-3 text-right">Over 90</th><th class="px-4 py-3 text-right">Total</th></tr></thead>
                    <tbody id="ar-aging-rows"></tbody>
                </table>
            </div>
        </article>
    </section>

    <script id="ar-data" type="application/json">{!! Illuminate\Support\Js::encode(['invoices' => $invoices, 'customers' => $customers, 'payments' => $payments, 'cashAccounts' => $cashAccounts, 'accounts' => $postingAccounts]) !!}</script>
</main>

<div id="ar-payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ar-payment-title" aria-hidden="true">
    <button type="button" class="absolute inset-0 bg-slate-950/50" data-ar-close aria-label="Close payment form"></button>
    <section class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-3xl overflow-y-auto rounded-xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div><h2 id="ar-payment-title" class="text-sm font-semibold text-slate-900">Record Customer Payment</h2><p class="mt-0.5 text-xs text-slate-500">Apply full or partial payment to open invoices</p></div>
            <button type="button" class="apm-icon-button" data-ar-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <form id="ar-payment-form" novalidate>
            @csrf
            <input name="request_token" type="hidden">
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                <label class="text-xs font-medium text-slate-600">Customer <span class="text-red-500">*</span>
                    <select name="customer_id" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400">
                        <option value="">Select customer</option>
                        @foreach ($customers as $customer)
                            @if ($customer['status'] === 'Active')
                                <option value="{{ $customer['id'] }}">{{ $customer['code'] }} — {{ $customer['name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                    <span data-error="customer_id" class="mt-1 block text-[10px] text-red-600"></span>
                </label>
                <label class="text-xs font-medium text-slate-600">Payment date <span class="text-red-500">*</span>
                    <input name="payment_date" type="date" value="{{ now()->toDateString() }}" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400">
                    <span data-error="payment_date" class="mt-1 block text-[10px] text-red-600"></span>
                </label>
                <label class="text-xs font-medium text-slate-600">Cash / bank account <span class="text-red-500">*</span>
                    <select name="cash_account_code" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400">
                        <option value="">Select account</option>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account['code'] }}">{{ $account['code'] }} — {{ $account['name'] }}</option>
                        @endforeach
                    </select>
                    <span data-error="cash_account_code" class="mt-1 block text-[10px] text-red-600"></span>
                </label>
                <label class="text-xs font-medium text-slate-600">Reference
                    <input name="reference" maxlength="50" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400">
                </label>
                <label class="text-xs font-medium text-slate-600 sm:col-span-2">Memo
                    <input name="memo" maxlength="255" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400">
                </label>
            </div>
            <div class="border-y border-slate-100">
                <div class="flex items-center justify-between px-5 py-3"><div><h3 class="text-xs font-semibold text-slate-700">Invoice allocations <span class="text-red-500">*</span></h3><span data-error="allocations" class="mt-1 block text-[10px] text-red-600"></span></div><button id="ar-add-allocation" type="button" class="apm-outline-button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Invoice</button></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[620px] text-xs"><thead class="bg-slate-50 text-[10px] text-slate-500 uppercase"><tr><th class="px-5 py-2 text-left">Open Invoice</th><th class="px-3 py-2 text-right">Remaining</th><th class="w-40 px-3 py-2 text-left">Amount</th><th class="w-14"></th></tr></thead><tbody id="ar-allocation-rows"></tbody></table></div>
            </div>
            <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                <p data-ar-message class="hidden rounded-lg px-3 py-2 text-xs" role="alert"></p>
                <div class="ml-auto text-right"><p class="text-xs text-slate-500">Payment total</p><strong id="ar-payment-total" class="font-mono text-base text-slate-900">₱0.00</strong></div>
            </div>
            <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4">
                <button type="button" class="apm-outline-button" data-ar-close>Cancel</button>
                <button id="ar-payment-submit" type="submit" class="apm-primary-button" @disabled(! $demoCan('drafts.manage') || $cashAccounts === [])><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Draft</button>
            </footer>
        </form>
    </section>
</div>

<template id="ar-allocation-template">
    <tr class="border-t border-slate-100">
        <td class="px-5 py-2"><select data-allocation-invoice class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400"></select><span data-allocation-error class="mt-1 block text-[10px] text-red-600"></span></td>
        <td data-allocation-remaining class="px-3 py-2 text-right font-mono text-[11px] text-slate-600">₱0.00</td>
        <td class="px-3 py-2"><input data-allocation-amount type="number" min="0.01" step="0.01" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-allocation-amount-error class="mt-1 block text-[10px] text-red-600"></span></td>
        <td class="px-2 py-2"><button type="button" data-remove-allocation class="grid size-8 place-items-center rounded-lg bg-red-50 text-red-600 transition hover:bg-red-100 active:scale-90" aria-label="Remove allocation"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></td>
    </tr>
</template>
@endsection
