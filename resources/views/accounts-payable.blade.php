@extends('layouts.accounting', ['pageTitle' => 'Accounts Payable', 'activePage' => 'accounts-payable'])

@section('content')
<main
    id="accounts-payable-page"
    class="p-4 sm:p-5"
    data-vendor-url="{{ route('accounts-payable.vendors.store') }}"
    data-bill-url="{{ route('accounts-payable.bills.store') }}"
    data-payment-url="{{ route('accounts-payable.payments.store') }}"
    data-export-url="{{ route('accounts-payable.export.csv') }}"
    data-pdf-url="{{ route('accounts-payable.export.pdf') }}"
    data-user-role="{{ $user['role'] }}"
>
    <div class="dashboard-enter print:hidden">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500">
            <span>Purchases</span><span class="text-slate-300">/</span>
            <span class="font-medium text-slate-700">Accounts Payable</span>
        </nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Accounts Payable</h1>
                <p class="mt-1 text-sm text-slate-500">Manage vendor bills and outgoing payments</p>
            </div>
            <button id="ap-new-bill" type="button" class="apm-primary-button" @disabled($user['role'] === 'Viewer / Auditor') title="{{ $user['role'] === 'Viewer / Auditor' ? 'Viewer role has read-only access.' : 'Create vendor bill' }}">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> New Bill
            </button>
        </div>
    </div>

    <section aria-label="Payable summary" class="mx-auto mt-5 grid max-w-3xl grid-cols-1 gap-3 sm:grid-cols-2 xl:max-w-none xl:grid-cols-4">
        <article class="apm-summary-card dashboard-enter [animation-delay:50ms]">
            <p>Total Payable</p><strong>&#8369;{{ number_format($metrics['payable'], 2) }}</strong><span>Open posted balances</span>
        </article>
        <article class="apm-summary-card dashboard-enter [animation-delay:100ms]">
            <p>Overdue</p><strong>&#8369;{{ number_format($metrics['overdue'], 2) }}</strong><span>Past due balances</span>
        </article>
        <article class="apm-summary-card dashboard-enter [animation-delay:150ms]">
            <p>Bills</p><strong>{{ $metrics['bill_count'] }}</strong><span>{{ $metrics['paid_count'] }} paid</span>
        </article>
        <article class="apm-summary-card dashboard-enter [animation-delay:200ms]">
            <p>Vendors</p><strong>{{ $metrics['vendor_count'] }}</strong><span>{{ $metrics['active_vendor_count'] }} active</span>
        </article>
    </section>

    <div class="mt-5 flex overflow-x-auto border-b border-slate-200 print:hidden" role="tablist" aria-label="Accounts Payable sections">
        <button type="button" class="apm-tab border-blue-600 text-blue-600" data-ap-tab="bills" role="tab" aria-selected="true">Bills</button>
        <button type="button" class="apm-tab" data-ap-tab="vendors" role="tab" aria-selected="false">Vendors</button>
        <button type="button" class="apm-tab" data-ap-tab="aging" role="tab" aria-selected="false">Aging Report</button>
    </div>

    <section data-ap-panel="bills" class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:250ms]">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-sm font-semibold text-slate-900">All Bills</h2><p class="mt-0.5 text-xs text-slate-500"><span id="ap-bill-count">{{ count($bills) }}</span> records</p></div>
            <div class="flex flex-wrap gap-2 print:hidden">
                <a id="ap-export" href="{{ route('accounts-payable.export.csv') }}" class="apm-outline-button"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV</a>
                <a id="ap-export-pdf" href="{{ route('accounts-payable.export.pdf') }}" class="apm-outline-button" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Export PDF</a>
                <button type="button" class="apm-outline-button" data-print-page><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
            </div>
        </header>
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row print:hidden">
            <label class="relative max-w-sm flex-1">
                <span class="sr-only">Search bills</span><i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-3 left-3 text-xs text-slate-400" aria-hidden="true"></i>
                <input id="ap-search" type="search" placeholder="Search bill or vendor..." class="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-8 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100">
            </label>
            <label><span class="sr-only">Filter by status</span>
                <select id="ap-status" class="h-9 min-w-36 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400">
                    <option value="">All Statuses</option><option>Draft</option><option>Unpaid</option><option>Partially Paid</option><option>Paid</option><option>Overdue</option>
                </select>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1080px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase"><tr>
                    <th class="px-4 py-3 font-semibold">Bill No.</th><th class="px-4 py-3 font-semibold">Date</th><th class="px-4 py-3 font-semibold">Due Date</th><th class="px-4 py-3 font-semibold">Vendor</th><th class="px-4 py-3 text-right font-semibold">Amount</th><th class="px-4 py-3 text-right font-semibold">Paid</th><th class="px-4 py-3 text-right font-semibold">Balance</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 font-semibold">Actions</th>
                </tr></thead>
                <tbody id="ap-bill-rows" class="text-slate-700">
                    @forelse ($bills as $bill)
                        <tr class="apm-table-row">
                            <td><button type="button" data-view-bill="{{ $bill['bill_number'] }}" class="apm-code text-left text-blue-600 hover:underline">{{ $bill['bill_number'] }}</button><p class="mt-1 text-[10px] text-slate-400">{{ $bill['reference'] }}</p></td>
                            <td class="apm-code">{{ $bill['bill_date'] }}</td><td class="apm-code">{{ $bill['due_date'] }}</td>
                            <td class="font-medium text-slate-800">{{ $bill['vendor_name'] }}</td>
                            <td class="apm-money text-right">&#8369;{{ number_format($bill['total'], 2) }}</td>
                            <td class="apm-money text-right">@if ($bill['amount_paid'] > 0)&#8369;{{ number_format($bill['amount_paid'], 2) }}@else-@endif</td>
                            <td class="apm-money text-right">&#8369;{{ number_format($bill['remaining_balance'], 2) }}</td>
                            <td><span class="inline-flex rounded-md px-2 py-1 text-[10px] font-medium">{{ $bill['display_status'] }}</span></td>
                            <td class="apm-actions"><button type="button" data-view-bill="{{ $bill['bill_number'] }}">View</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-14 text-center text-slate-500">No vendor bills found. Create first bill.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <span id="ap-page-summary">Showing {{ count($bills) ? '1-'.count($bills).' of '.count($bills).' records' : '0 records' }}</span>
            <div class="flex gap-1"><button id="ap-prev" type="button" class="apm-page-button">&lsaquo; Prev</button><span id="ap-page-number" class="grid min-w-8 place-items-center rounded bg-blue-600 px-2 text-[10px] text-white">1</span><button id="ap-next" type="button" class="apm-page-button">Next &rsaquo;</button></div>
        </footer>
    </section>

    <section data-ap-panel="vendors" hidden class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-sm font-semibold text-slate-900">Vendors</h2><p class="mt-0.5 text-xs text-slate-500">Contact details, terms, and posted balances</p></div>
            <button id="ap-new-vendor" type="button" class="apm-outline-button" @disabled($user['role'] === 'Viewer / Auditor')><i class="fa-solid fa-plus" aria-hidden="true"></i> New Vendor</button>
        </header>
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row">
            <label class="relative max-w-sm flex-1"><span class="sr-only">Search vendors</span><i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-3 left-3 text-xs text-slate-400" aria-hidden="true"></i><input id="ap-vendor-search" type="search" placeholder="Search vendor or code..." class="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-8 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
            <label><span class="sr-only">Filter vendor status</span><select id="ap-vendor-status" class="h-9 min-w-32 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400"><option value="">All Statuses</option><option>Active</option><option>Inactive</option></select></label>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase"><tr><th class="px-4 py-3">Code</th><th class="px-4 py-3">Vendor</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Terms</th><th class="px-4 py-3 text-right">Bills</th><th class="px-4 py-3 text-right">Outstanding</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
                <tbody id="ap-vendor-rows">
                    @forelse ($vendorBalances as $vendor)
                        <tr class="apm-table-row" data-vendor-id="{{ $vendor['id'] }}">
                            <td class="apm-code text-blue-600">{{ $vendor['code'] }}</td>
                            <td class="font-medium text-slate-800">{{ $vendor['name'] }}<p class="mt-1 text-[10px] font-normal text-slate-500">{{ $vendor['email'] ?: 'No email' }}</p></td>
                            <td>{{ $vendor['contact_person'] ?: '-' }}</td><td>{{ $vendor['payment_terms_days'] }} days</td><td class="apm-money text-right">{{ $vendor['bill_count'] }}</td><td class="apm-money text-right">&#8369;{{ number_format($vendor['outstanding'], 2) }}</td>
                            <td><span class="{{ $vendor['status'] === 'Active' ? 'apm-active-badge' : 'inline-flex rounded-md bg-slate-100 px-2 py-1 text-[10px] text-slate-600' }}">{{ $vendor['status'] }}</span></td>
                            <td class="apm-actions">
                                <button type="button" data-edit-vendor="{{ $vendor['id'] }}" @disabled($user['role'] === 'Viewer / Auditor')>Edit</button>
                                <button type="button" data-toggle-vendor="{{ $vendor['id'] }}" data-next-status="{{ $vendor['status'] === 'Active' ? 'Inactive' : 'Active' }}" @disabled($user['role'] === 'Viewer / Auditor')>{{ $vendor['status'] === 'Active' ? 'Deactivate' : 'Activate' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-14 text-center text-slate-500">No vendors yet. Create first vendor to record bills.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section data-ap-panel="aging" hidden class="dashboard-enter mt-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 class="text-sm font-semibold text-slate-900">Accounts Payable Aging</h2><p class="mt-1 text-xs text-slate-500">Outstanding posted bills grouped by due date</p></div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <label class="text-xs font-medium text-slate-600">Vendor<select id="ap-aging-vendor" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400 sm:min-w-48"><option value="">All vendors</option>@foreach ($vendors as $vendor)<option value="{{ $vendor['id'] }}">{{ $vendor['name'] }}</option>@endforeach</select></label>
                <label class="text-xs font-medium text-slate-600">As of date<input id="ap-as-of" type="date" value="{{ now()->toDateString() }}" class="mt-1 h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400"></label>
            </div>
        </div>
        <div id="ap-aging-cards" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5"></div>
        <article class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-xs"><thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase"><tr><th class="px-4 py-3">Vendor</th><th class="px-4 py-3 text-right">Current</th><th class="px-4 py-3 text-right">1-30</th><th class="px-4 py-3 text-right">31-60</th><th class="px-4 py-3 text-right">61-90</th><th class="px-4 py-3 text-right">Over 90</th><th class="px-4 py-3 text-right">Total</th></tr></thead><tbody id="ap-aging-rows"></tbody></table></div></article>
    </section>

    <script id="ap-data" type="application/json">{!! Illuminate\Support\Js::encode([
        'bills' => $bills,
        'vendors' => $vendors,
        'payments' => $payments,
        'cashAccounts' => $cashAccounts,
        'purchaseAccounts' => $purchaseAccounts,
        'accounts' => $postingAccounts,
    ]) !!}</script>
</main>

<div id="ap-bill-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ap-bill-title" aria-hidden="true">
    <button type="button" class="absolute inset-0 bg-slate-950/50" data-ap-close="bill" aria-label="Close vendor bill form"></button>
    <section class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 id="ap-bill-title" class="text-sm font-semibold text-slate-900">New Vendor Bill</h2><p class="mt-0.5 text-xs text-slate-500">Save draft, then post when reviewed</p></div><button type="button" class="apm-icon-button" data-ap-close="bill" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
        <form id="ap-bill-form" novalidate>
            @csrf
            <input name="bill_number" type="hidden">
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                <label class="text-xs font-medium text-slate-600">Vendor <span class="text-red-500">*</span><select name="vendor_id" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400"><option value="">Select vendor</option>@foreach ($vendors as $vendor)@if ($vendor['status'] === 'Active')<option value="{{ $vendor['id'] }}">{{ $vendor['code'] }} - {{ $vendor['name'] }}</option>@endif @endforeach</select><span data-bill-error="vendor_id" class="mt-1 block text-[10px] text-red-600"></span></label>
                <label class="text-xs font-medium text-slate-600">Vendor reference <span class="text-red-500">*</span><input name="reference" maxlength="50" required placeholder="Invoice or statement no." class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-bill-error="reference" class="mt-1 block text-[10px] text-red-600"></span></label>
                <label class="text-xs font-medium text-slate-600">Bill date <span class="text-red-500">*</span><input name="bill_date" type="date" value="{{ now()->toDateString() }}" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-bill-error="bill_date" class="mt-1 block text-[10px] text-red-600"></span></label>
                <label class="text-xs font-medium text-slate-600">Due date <span class="text-red-500">*</span><input name="due_date" type="date" value="{{ now()->addDays(30)->toDateString() }}" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-bill-error="due_date" class="mt-1 block text-[10px] text-red-600"></span></label>
                <label class="text-xs font-medium text-slate-600 lg:col-span-2">Memo<input name="memo" maxlength="255" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"></label>
                <label class="text-xs font-medium text-slate-600 sm:col-span-2 lg:col-span-3">Attachment <span class="font-normal text-slate-400">(demo metadata only, PDF/image, max 10 MB)</span><input name="attachment_file" type="file" accept="application/pdf,image/*" class="mt-1 block w-full rounded-lg border border-slate-200 p-2 text-xs file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-blue-700"><span id="ap-attachment-current" class="mt-1 hidden text-[10px] text-slate-500"></span><span data-bill-error="attachment" class="mt-1 block text-[10px] text-red-600"></span></label>
            </div>
            <div class="border-y border-slate-100">
                <div class="flex items-center justify-between px-5 py-3"><div><h3 class="text-xs font-semibold text-slate-700">Expense / asset lines <span class="text-red-500">*</span></h3><span data-bill-error="lines" class="mt-1 block text-[10px] text-red-600"></span></div><button id="ap-add-bill-line" type="button" class="apm-outline-button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Line</button></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-xs"><thead class="bg-slate-50 text-[10px] text-slate-500 uppercase"><tr><th class="px-4 py-2 text-left">Account</th><th class="px-3 py-2 text-left">Description</th><th class="w-24 px-3 py-2 text-right">Qty</th><th class="w-32 px-3 py-2 text-right">Unit Price</th><th class="w-24 px-3 py-2 text-right">Tax %</th><th class="w-32 px-3 py-2 text-right">Total</th><th class="w-12"></th></tr></thead><tbody id="ap-bill-lines"></tbody></table></div>
            </div>
            <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"><p data-bill-message class="hidden rounded-lg px-3 py-2 text-xs" role="alert"></p><div class="ml-auto grid grid-cols-2 gap-x-6 gap-y-1 text-right text-xs"><span class="text-slate-500">Subtotal</span><strong id="ap-bill-subtotal" class="font-mono text-slate-800">&#8369;0.00</strong><span class="text-slate-500">Tax</span><strong id="ap-bill-tax" class="font-mono text-slate-800">&#8369;0.00</strong><span class="font-semibold text-slate-700">Total</span><strong id="ap-bill-total" class="font-mono text-base text-slate-900">&#8369;0.00</strong></div></div>
            <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button type="button" class="apm-outline-button" data-ap-close="bill">Cancel</button><button id="ap-bill-submit" type="submit" class="apm-primary-button"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Draft</button></footer>
        </form>
    </section>
</div>

<div id="ap-vendor-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ap-vendor-title" aria-hidden="true">
    <button type="button" class="absolute inset-0 bg-slate-950/50" data-ap-close="vendor" aria-label="Close vendor form"></button>
    <section class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 id="ap-vendor-title" class="text-sm font-semibold text-slate-900">New Vendor</h2><p class="mt-0.5 text-xs text-slate-500">Add supplier details and payment terms</p></div><button type="button" class="apm-icon-button" data-ap-close="vendor" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
        <form id="ap-vendor-form" novalidate>@csrf<input name="vendor_id" type="hidden"><div class="grid gap-3 p-5 sm:grid-cols-2">
            <label class="text-xs font-medium text-slate-600">Vendor code <span class="text-red-500">*</span><input name="code" maxlength="30" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs uppercase outline-none focus:border-blue-400"><span data-vendor-error="code" class="mt-1 block text-[10px] text-red-600"></span></label>
            <label class="text-xs font-medium text-slate-600">Vendor name <span class="text-red-500">*</span><input name="name" maxlength="150" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-vendor-error="name" class="mt-1 block text-[10px] text-red-600"></span></label>
            <label class="text-xs font-medium text-slate-600">Contact person<input name="contact_person" maxlength="120" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"></label>
            <label class="text-xs font-medium text-slate-600">Email<input name="email" type="email" maxlength="150" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-vendor-error="email" class="mt-1 block text-[10px] text-red-600"></span></label>
            <label class="text-xs font-medium text-slate-600">Phone<input name="phone" maxlength="40" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"></label>
            <label class="text-xs font-medium text-slate-600">Tax ID<input name="tax_id" maxlength="50" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"></label>
            <label class="text-xs font-medium text-slate-600">Payment terms <span class="text-red-500">*</span><input name="payment_terms_days" type="number" min="0" max="365" value="30" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-vendor-error="payment_terms_days" class="mt-1 block text-[10px] text-red-600"></span></label>
            <label class="text-xs font-medium text-slate-600">Opening balance <span class="text-red-500">*</span><input name="opening_balance" type="number" min="0" step="0.01" value="0" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-vendor-error="opening_balance" class="mt-1 block text-[10px] text-red-600"></span></label>
            <label class="text-xs font-medium text-slate-600 sm:col-span-2">Address<textarea name="address" maxlength="300" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs outline-none focus:border-blue-400"></textarea></label>
            <p data-vendor-message class="hidden rounded-lg px-3 py-2 text-xs sm:col-span-2" role="alert"></p>
        </div><footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button type="button" class="apm-outline-button" data-ap-close="vendor">Cancel</button><button id="ap-vendor-submit" type="submit" class="apm-primary-button">Save Vendor</button></footer></form>
    </section>
</div>

<div id="ap-payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ap-payment-title" aria-hidden="true">
    <button type="button" class="absolute inset-0 bg-slate-950/50" data-ap-close="payment" aria-label="Close payment form"></button>
    <section class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-3xl overflow-y-auto rounded-xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 id="ap-payment-title" class="text-sm font-semibold text-slate-900">Record Vendor Payment</h2><p class="mt-0.5 text-xs text-slate-500">Apply full or partial payment to open bills</p></div><button type="button" class="apm-icon-button" data-ap-close="payment" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
        <form id="ap-payment-form" novalidate>@csrf<input name="request_token" type="hidden"><div class="grid gap-3 p-5 sm:grid-cols-2">
            <label class="text-xs font-medium text-slate-600">Vendor <span class="text-red-500">*</span><select name="vendor_id" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400"><option value="">Select vendor</option>@foreach ($vendors as $vendor)@if ($vendor['status'] === 'Active')<option value="{{ $vendor['id'] }}">{{ $vendor['code'] }} - {{ $vendor['name'] }}</option>@endif @endforeach</select><span data-payment-error="vendor_id" class="mt-1 block text-[10px] text-red-600"></span></label>
            <label class="text-xs font-medium text-slate-600">Payment date <span class="text-red-500">*</span><input name="payment_date" type="date" value="{{ now()->toDateString() }}" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-payment-error="payment_date" class="mt-1 block text-[10px] text-red-600"></span></label>
            <label class="text-xs font-medium text-slate-600">Cash / bank account <span class="text-red-500">*</span><select name="cash_account_code" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400"><option value="">Select account</option>@foreach ($cashAccounts as $account)<option value="{{ $account['code'] }}">{{ $account['code'] }} - {{ $account['name'] }}</option>@endforeach</select><span data-payment-error="cash_account_code" class="mt-1 block text-[10px] text-red-600"></span></label>
            <label class="text-xs font-medium text-slate-600">Reference<input name="reference" maxlength="50" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"></label>
            <label class="text-xs font-medium text-slate-600 sm:col-span-2">Memo<input name="memo" maxlength="255" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"></label>
        </div><div class="border-y border-slate-100"><div class="flex items-center justify-between px-5 py-3"><div><h3 class="text-xs font-semibold text-slate-700">Bill allocations <span class="text-red-500">*</span></h3><span data-payment-error="allocations" class="mt-1 block text-[10px] text-red-600"></span></div><button id="ap-add-allocation" type="button" class="apm-outline-button"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Bill</button></div><div class="overflow-x-auto"><table class="w-full min-w-[620px] text-xs"><thead class="bg-slate-50 text-[10px] text-slate-500 uppercase"><tr><th class="px-5 py-2 text-left">Open Bill</th><th class="px-3 py-2 text-right">Remaining</th><th class="w-40 px-3 py-2 text-left">Amount</th><th class="w-14"></th></tr></thead><tbody id="ap-allocation-rows"></tbody></table></div></div>
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"><p data-payment-message class="hidden rounded-lg px-3 py-2 text-xs" role="alert"></p><div class="ml-auto text-right"><p class="text-xs text-slate-500">Payment total</p><strong id="ap-payment-total" class="font-mono text-base text-slate-900">&#8369;0.00</strong></div></div>
        <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button type="button" class="apm-outline-button" data-ap-close="payment">Cancel</button><button id="ap-payment-submit" type="submit" class="apm-primary-button" @disabled(! in_array($user['role'], ['Administrator', 'Accountant'], true) || $cashAccounts === [])><i class="fa-solid fa-check" aria-hidden="true"></i> Post Payment</button></footer></form>
    </section>
</div>

<div id="ap-detail-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ap-detail-title" aria-hidden="true"><button type="button" class="absolute inset-0 bg-slate-950/50" data-ap-close="detail" aria-label="Close bill details"></button><section class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-2xl"><header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 id="ap-detail-title" class="text-sm font-semibold text-slate-900">Vendor Bill</h2><p id="ap-detail-subtitle" class="mt-0.5 text-xs text-slate-500"></p></div><button type="button" class="apm-icon-button" data-ap-close="detail" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header><div id="ap-detail-content" class="p-5"></div><footer class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button type="button" class="apm-outline-button" data-ap-close="detail">Close</button></footer></section></div>

<template id="ap-bill-line-template"><tr class="border-t border-slate-100"><td class="px-4 py-2"><select data-bill-account class="h-9 w-full min-w-44 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400"><option value="">Select account</option>@foreach ($purchaseAccounts as $account)<option value="{{ $account['code'] }}">{{ $account['code'] }} - {{ $account['name'] }}</option>@endforeach</select><span data-line-error="account_code" class="mt-1 block text-[10px] text-red-600"></span></td><td class="px-3 py-2"><input data-bill-description maxlength="180" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-line-error="description" class="mt-1 block text-[10px] text-red-600"></span></td><td class="px-3 py-2"><input data-bill-quantity type="number" min="0.01" step="0.01" value="1" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-right text-xs outline-none focus:border-blue-400"></td><td class="px-3 py-2"><input data-bill-price type="number" min="0" step="0.01" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-right text-xs outline-none focus:border-blue-400"></td><td class="px-3 py-2"><input data-bill-tax-rate type="number" min="0" max="100" step="0.01" value="0" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-right text-xs outline-none focus:border-blue-400"></td><td data-bill-line-total class="px-3 py-2 text-right font-mono text-[11px] text-slate-700">&#8369;0.00</td><td class="px-2 py-2"><button type="button" data-remove-bill-line class="grid size-8 place-items-center rounded-lg bg-red-50 text-red-600 transition hover:bg-red-100 active:scale-90" aria-label="Remove line"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></td></tr></template>

<template id="ap-allocation-template"><tr class="border-t border-slate-100"><td class="px-5 py-2"><select data-allocation-bill class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400"></select><span data-allocation-error class="mt-1 block text-[10px] text-red-600"></span></td><td data-allocation-remaining class="px-3 py-2 text-right font-mono text-[11px] text-slate-600">&#8369;0.00</td><td class="px-3 py-2"><input data-allocation-amount type="number" min="0.01" step="0.01" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400"><span data-allocation-amount-error class="mt-1 block text-[10px] text-red-600"></span></td><td class="px-2 py-2"><button type="button" data-remove-allocation class="grid size-8 place-items-center rounded-lg bg-red-50 text-red-600 transition hover:bg-red-100 active:scale-90" aria-label="Remove allocation"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></td></tr></template>
@endsection
