@extends('layouts.accounting', ['pageTitle' => 'Sales and Revenue', 'activePage' => 'sales-revenue'])

@section('content')
<main id="sales-revenue-page" class="p-4 sm:p-5"
    data-customer-url="{{ route('sales-revenue.customers.store') }}"
    data-invoice-url="{{ route('sales-revenue.invoices.store') }}"
    data-post-url-template="{{ route('sales-revenue.invoices.post', ['invoiceNumber' => '__INVOICE__']) }}"
    data-print-url-template="{{ route('sales-revenue.invoices.print', ['invoiceNumber' => '__INVOICE__']) }}"
    data-user-role="{{ $user['role'] }}">
    <div class="dashboard-enter print:hidden">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500"><span>Sales</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Overview</span></nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-xl font-bold text-slate-900">Sales</h1><p class="mt-1 text-sm text-slate-500">Revenue overview and sales performance</p></div>
            <div class="flex gap-2">
                <button id="manage-customers-button" type="button" class="apm-outline-button"><i class="fa-solid fa-users" aria-hidden="true"></i>Add Customers</button>
                <button id="new-invoice-button" type="button" class="apm-primary-button" @disabled($user['role'] === 'Viewer / Auditor') title="{{ $user['role'] === 'Viewer / Auditor' ? 'Viewer role has read-only access.' : 'Create sales invoice' }}"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i> New Invoice</button>
            </div>
        </div>
    </div>

    <section aria-label="Sales summary" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Total Revenue (YTD)', 'value' => $metrics['total_revenue'], 'note' => 'FY '.now()->format('Y')],
            ['label' => 'This Month Revenue', 'value' => $metrics['month_revenue'], 'note' => now()->format('F Y')],
            ['label' => 'Collected', 'value' => $metrics['collected'], 'note' => 'Posted invoices'],
            ['label' => 'Outstanding', 'value' => $metrics['outstanding'], 'note' => 'Open posted invoices'],
        ] as $card)
            <article class="apm-summary-card dashboard-enter" style="animation-delay: {{ 50 + ($loop->index * 50) }}ms">
                <p>{{ $card['label'] }}</p>
                <strong>{{ $hasPostedSales ? '₱'.number_format($card['value'], 2) : '—' }}</strong>
                <span>{{ $hasPostedSales ? $card['note'] : 'No posted sales data' }}</span>
            </article>
        @endforeach
    </section>

    <section class="mt-4 grid gap-4 xl:grid-cols-[2fr_1fr]">
        <article class="dashboard-enter overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:250ms]">
            <header class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Monthly Revenue</h2><p class="mt-0.5 text-xs text-slate-500">January – December {{ now()->format('Y') }}</p></header>
            @if ($hasPostedSales)
                <div class="overflow-x-auto p-5">
                    <div class="flex h-56 min-w-620px items-end gap-3 border-b border-slate-200 px-2">
                        @foreach ($monthlyRevenue as $monthNumber => $value)
                            @php($height = $monthlyRevenueMax > 0 ? max(2, ($value / $monthlyRevenueMax) * 100) : 0)
                            <div class="group flex h-full flex-1 flex-col items-center justify-end gap-2" title="{{ now()->month($monthNumber)->format('F') }}: ₱{{ number_format($value, 2) }}">
                                <span class="pointer-events-none rounded bg-slate-900 px-1.5 py-1 text-[9px] whitespace-nowrap text-white opacity-0 transition group-hover:opacity-100">₱{{ number_format($value, 2) }}</span>
                                <div class="w-full max-w-6 rounded-t bg-blue-600 transition duration-300 group-hover:bg-blue-500" style="height: {{ $height }}%"></div>
                                <span class="pb-2 text-[10px] text-slate-500">{{ now()->month($monthNumber)->format('M') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="grid h-64 place-items-center p-6 text-center"><div><i class="fa-solid fa-chart-column text-3xl text-slate-300" aria-hidden="true"></i><p class="mt-3 text-sm font-medium text-slate-600">No revenue data yet</p><p class="mt-1 text-xs text-slate-400">Posted invoices appear here.</p></div></div>
            @endif
        </article>

        <article class="dashboard-enter overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:300ms]">
            <header class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Revenue by Customer</h2><p class="mt-0.5 text-xs text-slate-500">YTD {{ now()->format('Y') }}</p></header>
            @if ($revenueByCustomer !== [])
                <div class="space-y-4 p-5">
                    @foreach ($revenueByCustomer as $customerRevenue)
                        <div><div class="mb-1.5 flex items-start justify-between gap-3 text-xs"><span class="truncate text-slate-600">{{ $customerRevenue['name'] }}</span><strong class="font-mono text-[11px] text-slate-800">₱{{ number_format($customerRevenue['total'], 2) }}</strong></div><div class="h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-blue-500" style="width: {{ $customerRevenueMax > 0 ? max(2, ($customerRevenue['total'] / $customerRevenueMax) * 100) : 0 }}%"></div></div></div>
                    @endforeach
                </div>
            @else
                <div class="grid h-64 place-items-center p-6 text-center"><div><i class="fa-solid fa-chart-simple text-3xl text-slate-300" aria-hidden="true"></i><p class="mt-3 text-sm font-medium text-slate-600">No customer revenue yet</p><p class="mt-1 text-xs text-slate-400">Post sales invoices to build this summary.</p></div></div>
            @endif
        </article>
    </section>

    <section class="dashboard-enter mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:350ms]">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">Recent Invoices</h2><p class="mt-0.5 text-xs text-slate-500">Latest sales transactions</p></div><span class="text-xs text-slate-500">{{ count($invoices) }} {{ Str::plural('invoice', count($invoices)) }}</span></header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-780px text-left text-xs">
                <thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase"><tr><th class="px-4 py-3 font-semibold">Invoice No.</th><th class="px-4 py-3 font-semibold">Date</th><th class="px-4 py-3 font-semibold">Due Date</th><th class="px-4 py-3 font-semibold">Customer</th><th class="px-4 py-3 text-right font-semibold">Amount</th><th class="px-4 py-3 font-semibold">Status</th></tr></thead>
                <tbody class="text-slate-700">
                    @forelse ($recentInvoices as $invoice)
                        @php($statusClass = match($invoice['display_status']) {'Paid' => 'bg-emerald-100 text-emerald-700', 'Partially Paid' => 'bg-sky-100 text-sky-700', 'Overdue' => 'bg-red-100 text-red-700', 'Draft' => 'bg-slate-100 text-slate-600', default => 'bg-amber-100 text-amber-700'})
                        <tr class="apm-table-row">
                            <td><button type="button" data-view-invoice="{{ $invoice['invoice_number'] }}" class="font-mono text-[11px] font-medium text-blue-600 hover:underline">{{ $invoice['invoice_number'] }}</button></td>
                            <td class="font-mono text-[11px]">{{ $invoice['invoice_date'] }}</td><td class="font-mono text-[11px]">{{ $invoice['due_date'] }}</td>
                            <td class="font-medium text-slate-800">{{ $invoice['customer_name'] }}</td>
                            <td class="apm-money text-right">₱{{ number_format($invoice['total'], 2) }}</td>
                            <td><span class="inline-flex rounded-md px-2 py-1 text-[10px] font-medium {{ $statusClass }}">{{ $invoice['display_status'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center"><i class="fa-regular fa-file-lines text-2xl text-slate-300" aria-hidden="true"></i><p class="mt-2 text-sm font-medium text-slate-600">No invoices yet</p><p class="mt-1 text-xs text-slate-400">Create customer first, then create invoice.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script id="sales-data" type="application/json">{!! Illuminate\Support\Js::encode(['customers' => $customers, 'invoices' => $invoices, 'accounts' => $postingAccounts]) !!}</script>
</main>

<div id="customers-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="customers-modal-title" aria-hidden="true">
    <button type="button" class="absolute inset-0 bg-slate-950/50" data-sales-close aria-label="Close customers"></button>
    <section class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-3xl overflow-y-auto rounded-xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 id="customers-modal-title" class="text-sm font-semibold text-slate-900">Customers</h2><p class="mt-0.5 text-xs text-slate-500">Customer records</p></div><button type="button" class="apm-icon-button" data-sales-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></header>
        <div class="grid lg:grid-cols-[1fr_1.15fr]">
            <div class="border-b border-slate-100 p-5 lg:border-r lg:border-b-0">
                <h3 class="text-xs font-semibold text-slate-700">Existing customers</h3>
                <div class="mt-3 max-h-96 space-y-2 overflow-y-auto">
                    @forelse ($customers as $customer)
                        <div class="rounded-lg border border-slate-200 p-3"><div class="flex justify-between gap-3"><p class="text-xs font-semibold text-slate-800">{{ $customer['name'] }}</p><span class="font-mono text-[10px] text-blue-600">{{ $customer['code'] }}</span></div><p class="mt-1 text-[11px] text-slate-500">{{ $customer['email'] ?: 'No email' }} · {{ $customer['credit_terms_days'] }} day terms</p></div>
                    @empty
                        <p class="rounded-lg bg-slate-50 px-4 py-8 text-center text-xs text-slate-500">No customers available.</p>
                    @endforelse
                </div>
            </div>
            <form id="customer-form" class="p-5" novalidate>
                @csrf
                <h3 class="text-xs font-semibold text-slate-700">New customer</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-xs font-medium text-slate-600">Code <span class="text-red-500">*</span><input name="code" required maxlength="30" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs uppercase outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><span data-error="code" class="mt-1 block text-[10px] text-red-600"></span></label>
                    <label class="text-xs font-medium text-slate-600">Business name <span class="text-red-500">*</span><input name="name" required maxlength="150" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><span data-error="name" class="mt-1 block text-[10px] text-red-600"></span></label>
                    <label class="text-xs font-medium text-slate-600">Contact person<input name="contact_person" maxlength="120" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
                    <label class="text-xs font-medium text-slate-600">Email<input name="email" type="email" maxlength="150" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
                    <label class="text-xs font-medium text-slate-600">Phone<input name="phone" maxlength="40" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
                    <label class="text-xs font-medium text-slate-600">Tax ID<input name="tax_id" maxlength="50" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
                    <label class="text-xs font-medium text-slate-600">Credit terms (days) <span class="text-red-500">*</span><input name="credit_terms_days" type="number" min="0" max="365" value="30" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
                    <label class="text-xs font-medium text-slate-600">Opening balance <span class="text-red-500">*</span><input name="opening_balance" type="number" min="0" step="0.01" value="0" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
                    <label class="text-xs font-medium text-slate-600 sm:col-span-2">Billing address<textarea name="billing_address" rows="2" maxlength="300" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></textarea></label>
                </div>
                <p data-form-message class="mt-3 hidden rounded-lg px-3 py-2 text-xs" role="alert"></p>
                <div class="mt-4 flex justify-end"><button type="submit" class="apm-primary-button" @disabled($user['role'] === 'Viewer / Auditor')>Add Customer</button></div>
            </form>
        </div>
    </section>
</div>

<div id="invoice-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="invoice-modal-title" aria-hidden="true">
    <button type="button" class="absolute inset-0 bg-slate-950/50" data-sales-close aria-label="Close invoice form"></button>
    <section class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 id="invoice-modal-title" class="text-sm font-semibold text-slate-900">New Sales Invoice</h2><p class="mt-0.5 text-xs text-slate-500">Save draft or post after validation</p></div><button type="button" class="apm-icon-button" data-sales-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></header>
        <form id="invoice-form" novalidate>
            @csrf
            <div class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-xs font-medium text-slate-600 lg:col-span-2">Customer <span class="text-red-500">*</span><select name="customer_id" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="">Select customer</option>@foreach($customers as $customer)@if($customer['status'] === 'Active')<option value="{{ $customer['id'] }}">{{ $customer['code'] }} — {{ $customer['name'] }}</option>@endif @endforeach</select><span data-error="customer_id" class="mt-1 block text-[10px] text-red-600"></span></label>
                <label class="text-xs font-medium text-slate-600">Invoice date <span class="text-red-500">*</span><input name="invoice_date" type="date" value="{{ now()->toDateString() }}" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
                <label class="text-xs font-medium text-slate-600">Due date <span class="text-red-500">*</span><input name="due_date" type="date" value="{{ now()->addDays(30)->toDateString() }}" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><span data-error="due_date" class="mt-1 block text-[10px] text-red-600"></span></label>
                <label class="text-xs font-medium text-slate-600">Reference<input name="reference" maxlength="50" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
                <label class="text-xs font-medium text-slate-600 sm:col-span-1 lg:col-span-3">Memo<input name="memo" maxlength="255" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
            </div>
            <div class="border-y border-slate-100">
                <div class="flex items-center justify-between px-5 py-3"><h3 class="text-xs font-semibold text-slate-700">Line items</h3><button id="add-invoice-line" type="button" class="apm-outline-button"><i class="fa-solid fa-plus"></i> Add Line</button></div>
                <div class="overflow-x-auto"><table class="w-full min-w-720px text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="px-4 py-2">Description</th><th class="w-24 px-3 py-2">Qty</th><th class="w-36 px-3 py-2">Unit Price</th><th class="w-24 px-3 py-2">Tax %</th><th class="w-32 px-3 py-2 text-right">Total</th><th class="w-12"></th></tr></thead><tbody id="invoice-lines"></tbody></table></div>
            </div>
            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                <p data-form-message class="hidden rounded-lg px-3 py-2 text-xs" role="alert"></p>
                <dl class="ml-auto grid w-full max-w-xs grid-cols-2 gap-x-5 gap-y-1 text-xs"><dt class="text-slate-500">Subtotal</dt><dd id="invoice-subtotal" class="text-right font-mono">₱0.00</dd><dt class="text-slate-500">Tax</dt><dd id="invoice-tax" class="text-right font-mono">₱0.00</dd><dt class="border-t border-slate-200 pt-2 font-semibold">Total</dt><dd id="invoice-total" class="border-t border-slate-200 pt-2 text-right font-mono font-bold">₱0.00</dd></dl>
            </div>
            <footer class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button type="button" class="apm-outline-button" data-sales-close>Cancel</button><button type="submit" data-invoice-intent="draft" class="apm-outline-button">Save Draft</button><button type="submit" data-invoice-intent="post" class="apm-primary-button">Save &amp; Post</button></footer>
        </form>
    </section>
</div>

<div id="invoice-view-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="invoice-view-title" aria-hidden="true">
    <button type="button" class="absolute inset-0 bg-slate-950/50" data-sales-close aria-label="Close invoice"></button>
    <section class="relative z-10 w-full max-w-lg rounded-xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 id="invoice-view-title" class="text-sm font-semibold text-slate-900">Sales Invoice</h2><p id="invoice-view-status" class="mt-0.5 text-xs text-slate-500"></p></div><button type="button" class="apm-icon-button" data-sales-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></header>
        <div id="invoice-view-content" class="space-y-4 p-5 text-xs"></div>
        <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4"><a id="invoice-view-print" target="_blank" rel="noopener" class="apm-outline-button"><i class="fa-solid fa-print"></i> Print</a><button id="invoice-view-post" type="button" class="apm-primary-button"><i class="fa-solid fa-check"></i> Post Invoice</button></footer>
    </section>
</div>

<template id="invoice-line-template">
    <tr class="border-t border-slate-100">
        <td class="px-4 py-2"><input data-line="description" maxlength="180" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></td>
        <td class="px-3 py-2"><input data-line="quantity" type="number" min="0.01" step="0.01" value="1" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none focus:border-blue-400"></td>
        <td class="px-3 py-2"><input data-line="unit_price" type="number" min="0" step="0.01" value="0" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none focus:border-blue-400"></td>
        <td class="px-3 py-2"><select data-line="tax_rate" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none focus:border-blue-400">@forelse($vatRates as $taxCode)<option value="{{ $taxCode['rate'] }}" @selected($taxCode['is_default'])>{{ number_format($taxCode['rate'], 2) }}%</option>@empty<option value="0">0.00%</option>@endforelse</select></td>
        <td data-line-total class="px-3 py-2 text-right font-mono font-medium">₱0.00</td>
        <td class="px-2 py-2"><button type="button" data-remove-line class="grid size-8 place-items-center rounded-lg bg-red-50 text-red-600 transition hover:bg-red-100 active:scale-90" aria-label="Remove line"><i class="fa-solid fa-trash-can"></i></button></td>
    </tr>
</template>
@endsection
