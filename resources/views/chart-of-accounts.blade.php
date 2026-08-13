@extends('layouts.accounting', ['pageTitle' => 'Chart of Accounts', 'activePage' => 'chart-of-accounts'])

@section('content')
<main id="chart-of-accounts-page" class="p-4 sm:p-5"
    data-store-url="{{ route('chart-of-accounts.store') }}"
    data-update-url-template="{{ route('chart-of-accounts.update', ['code' => '__CODE__']) }}"
    data-status-url-template="{{ route('chart-of-accounts.status', ['code' => '__CODE__']) }}"
    data-delete-url-template="{{ route('chart-of-accounts.destroy', ['code' => '__CODE__']) }}">
    <div class="dashboard-enter print:hidden">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500"><span>Accounting</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Chart of Accounts</span></nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-xl font-bold text-slate-900">Chart of Accounts</h1><p class="mt-1 text-sm text-slate-500">Manage your accounting structure and account codes</p></div>
            @if ($demoCan('configuration.manage'))
                <button id="new-account-button" type="button" aria-haspopup="dialog" aria-controls="new-account-modal" class="apm-primary-button self-start"><span class="text-base leading-none" aria-hidden="true">+</span>New Account</button>
            @endif
        </div>
    </div>

    <section class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:50ms]">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-sm font-semibold text-slate-900">All Accounts</h2><p id="account-count" class="mt-0.5 text-xs text-slate-500">{{ $totalAccounts }} accounts</p></div>
            <div class="flex gap-2">
                <button type="button" class="apm-outline-button" data-print-page>Print</button>
                <a id="accounts-export-csv" href="{{ route('chart-of-accounts.export.csv') }}" class="apm-outline-button">Export CSV</a>
                <a id="accounts-export-pdf" href="{{ route('chart-of-accounts.export.pdf') }}" target="_blank" rel="noopener" class="apm-outline-button">Export PDF</a>
            </div>
        </header>
        <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row print:hidden">
            <label class="relative sm:w-60"><span class="sr-only">Search accounts</span><svg class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg><input id="account-search" type="search" class="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-xs outline-none transition duration-150 focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
            <select id="account-type-filter" aria-label="Account type" class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs text-slate-700 outline-none transition duration-150 focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="">All Types</option><option>Asset</option><option>Liability</option><option>Equity</option><option>Revenue</option><option>Expense</option></select>
            <select id="account-status-filter" aria-label="Account status" class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs text-slate-700 outline-none transition duration-150 focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="">All Status</option><option>Active</option><option>Inactive</option></select>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-250 border-collapse text-left">
                <thead><tr class="border-b border-slate-100 text-[11px] font-semibold tracking-wide text-slate-500 uppercase"><th class="w-22 px-4 py-3">Code</th><th class="px-4 py-3">Account Name</th><th class="w-23 px-4 py-3">Type</th><th class="w-40 px-4 py-3">Sub-Type</th><th class="w-41 px-4 py-3">Balance</th><th class="w-28 px-4 py-3">Status</th><th class="w-47 px-4 py-3">Actions</th></tr></thead>
                <tbody id="accounts-table-body" class="text-xs text-slate-700">
                    @forelse ($accounts as $account)
                        <tr class="apm-table-row dashboard-enter">
                            <td class="apm-code">{{ $account['code'] }}</td>
                            <td class="apm-account-name">{{ $account['name'] }}</td>
                            <td>{{ $account['type'] }}</td>
                            <td>{{ $account['sub_type'] }}</td>
                            <td class="apm-money {{ $account['balance'] < 0 ? 'text-red-600' : '' }}">{{ $account['balance'] < 0 ? '-' : '' }}&#8369;{{ number_format(abs($account['balance']), 2) }}</td>
                            <td><span class="apm-active-badge">{{ $account['status'] }}</span></td>
                            <td class="apm-actions">—</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between print:hidden"><p id="accounts-record-count" class="text-xs text-slate-500">Showing 1–{{ count($accounts) }} of {{ $totalAccounts }} records</p><div id="accounts-pagination" class="flex items-center gap-1" aria-label="Account table pagination"></div></footer>
    </section>
    <section aria-label="Account type summaries" class="mx-auto mt-4 grid max-w-272 grid-cols-1 gap-3 pb-8 sm:grid-cols-2 xl:grid-cols-5">
        @foreach (['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'] as $type)
            <article class="apm-summary-card dashboard-enter {{ $loop->last ? 'sm:col-span-2 xl:col-span-1' : '' }}" style="animation-delay: {{ 50 + ($loop->iteration * 50) }}ms"><p>{{ $type }}</p><strong>&#8369;{{ number_format($accountSummaries[$type]['balance'], 2) }}</strong><span>{{ $accountSummaries[$type]['count'] }} {{ Str::plural('account', $accountSummaries[$type]['count']) }}</span></article>
        @endforeach
    </section>
</main>

<div id="new-account-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="new-account-modal-title" aria-hidden="true">
    <button type="button" tabindex="-1" class="absolute inset-0 cursor-default bg-slate-950/50" data-modal-close aria-label="Close create account modal"></button>
    <section id="new-account-modal-panel" class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 id="new-account-modal-title" class="text-sm font-semibold text-slate-900">New Account</h2>
            <button type="button" class="apm-icon-button" data-modal-close aria-label="Close"><svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
        </header>
        <form id="new-account-form" novalidate>
            @csrf
            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                <div><label for="account-code" class="mb-1.5 block text-xs font-medium text-slate-700">Code</label><input id="account-code" name="code" type="text" readonly class="h-10 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 font-mono text-xs text-slate-600 outline-none"></div>
                <div><label for="account-name" class="mb-1.5 block text-xs font-medium text-slate-700">Account Name <span class="text-red-500">*</span></label><input id="account-name" name="accountName" type="text" required maxlength="100" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><p class="mt-1 min-h-4 text-[11px] text-red-600" data-error="accountName"></p></div>
                <div><label for="account-type" class="mb-1.5 block text-xs font-medium text-slate-700">Type <span class="text-red-500">*</span></label><select id="account-type" name="type" required class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="">Select type</option><option>Asset</option><option>Liability</option><option>Equity</option><option>Revenue</option><option>Expense</option></select><p class="mt-1 min-h-4 text-[11px] text-red-600" data-error="type"></p></div>
                <div><label for="account-sub-type" class="mb-1.5 block text-xs font-medium text-slate-700">Sub-Type</label><input id="account-sub-type" name="subType" type="text" maxlength="100" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><p class="mt-1 min-h-4 text-[11px] text-red-600" data-error="subType"></p></div>
                <div><label for="account-balance" class="mb-1.5 block text-xs font-medium text-slate-700">Posted Balance</label><input id="account-balance" name="balance" type="number" step="0.01" value="0" readonly class="h-10 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 text-xs text-slate-600 outline-none"><p class="mt-1 min-h-4 text-[11px] text-slate-500">Balances change through posted journals or Cash &amp; Bank adjustments.</p><p class="min-h-4 text-[11px] text-red-600" data-error="balance"></p></div>
                <div><label for="account-status" class="mb-1.5 block text-xs font-medium text-slate-700">Status <span class="text-red-500">*</span></label><select id="account-status" name="status" required class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="Active">Active</option><option value="Inactive">Inactive</option></select><p class="mt-1 min-h-4 text-[11px] text-red-600" data-error="status"></p></div>
            </div>
            <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button type="button" class="apm-outline-button" data-modal-close>Cancel</button><button id="create-account-button" type="submit" class="apm-primary-button">Add Account</button></footer>
        </form>
    </section>
</div>

<div id="account-toast" class="fixed right-4 bottom-4 z-50 hidden rounded-lg border border-emerald-200 bg-white px-4 py-3 text-xs font-medium text-emerald-700 shadow-lg" role="status">Account added successfully.</div>
<script id="chart-of-accounts-data" type="application/json">@json($accountDataset)</script>
@endsection
