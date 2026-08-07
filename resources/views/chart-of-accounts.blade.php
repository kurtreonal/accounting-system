@extends('layouts.accounting', ['pageTitle' => 'Chart of Accounts', 'activePage' => 'chart-of-accounts'])

@section('content')
<main class="p-4 sm:p-5">
    <div class="dashboard-enter">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500"><span>Accounting</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Chart of Accounts</span></nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-xl font-bold text-slate-900">Chart of Accounts</h1><p class="mt-1 text-sm text-slate-500">Manage your accounting structure and account codes</p></div>
            <button id="new-account-button" type="button" aria-haspopup="dialog" aria-controls="new-account-modal" class="apm-primary-button self-start"><span class="text-base leading-none" aria-hidden="true">+</span>New Account</button>
        </div>
    </div>

    <section class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:50ms]">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-sm font-semibold text-slate-900">All Accounts</h2><p class="mt-0.5 text-xs text-slate-500">{{ $totalAccounts }} accounts</p></div>
            <div class="flex gap-2">
                <a href="{{ route('chart-of-accounts.export.csv', request()->only(['search', 'type', 'status'])) }}" class="apm-outline-button">Export CSV</a>
                <a href="{{ route('chart-of-accounts.export.pdf', request()->only(['search', 'type', 'status'])) }}" target="_blank" rel="noopener" class="apm-outline-button">Export PDF</a>
            </div>
        </header>
        <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row">
            <label class="relative sm:w-60"><span class="sr-only">Search accounts</span><svg class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg><input id="account-search" type="search" placeholder="Search account code or name..." class="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-xs outline-none transition duration-150 placeholder:text-slate-400 focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
            <select id="account-type-filter" aria-label="Account type" class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs text-slate-700 outline-none transition duration-150 focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="">All Types</option><option>Asset</option><option>Liability</option><option>Equity</option><option>Revenue</option><option>Expense</option></select>
            <select id="account-status-filter" aria-label="Account status" class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs text-slate-700 outline-none transition duration-150 focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="">All Status</option><option>Active</option><option>Inactive</option></select>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-250 border-collapse text-left">
                <thead><tr class="border-b border-slate-100 text-[11px] font-semibold tracking-wide text-slate-500 uppercase"><th class="w-22 px-4 py-3">Code</th><th class="px-4 py-3">Account Name</th><th class="w-23 px-4 py-3">Type</th><th class="w-40 px-4 py-3">Sub-Type</th><th class="w-41 px-4 py-3">Balance</th><th class="w-28 px-4 py-3">Status</th><th class="w-47 px-4 py-3">Actions</th></tr></thead>
                <tbody class="text-xs text-slate-700">
                    @forelse ($accounts as $account)
                        <tr class="apm-table-row">
                            <td class="apm-code">{{ $account['code'] }}</td>
                            <td class="apm-account-name">{{ $account['name'] }}</td>
                            <td>{{ $account['type'] }}</td>
                            <td>{{ $account['sub_type'] }}</td>
                            <td class="apm-money {{ $account['balance'] < 0 ? 'text-red-600' : '' }}">{{ $account['balance'] < 0 ? '-' : '' }}&#8369;{{ number_format(abs($account['balance']), 2) }}</td>
                            <td><span class="apm-active-badge">{{ $account['status'] }}</span></td>
                            <td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-500">Showing 1–{{ count($accounts) }} of {{ $totalAccounts }} records</p><div class="flex items-center gap-1"><button type="button" disabled class="apm-page-button text-slate-300">‹ Prev</button><button type="button" class="apm-page-button border-blue-600 bg-blue-600 text-white hover:bg-blue-500">1</button><button type="button" class="apm-page-button">2</button><button type="button" class="apm-page-button">3</button><button type="button" class="apm-page-button">4</button><button type="button" class="apm-page-button">Next ›</button></div></footer>
    </section>
    <section aria-label="Account type summaries" class="mx-auto mt-4 grid max-w-[1088px] grid-cols-1 gap-3 pb-8 sm:grid-cols-2 xl:grid-cols-5">
        <article class="apm-summary-card dashboard-enter [animation-delay:100ms]"><p>Asset</p><strong>&#8369;0.00</strong><span>0 accounts</span></article><article class="apm-summary-card dashboard-enter [animation-delay:150ms]"><p>Liability</p><strong>&#8369;0.00</strong><span>0 accounts</span></article><article class="apm-summary-card dashboard-enter [animation-delay:200ms]"><p>Equity</p><strong>&#8369;0.00</strong><span>0 accounts</span></article><article class="apm-summary-card dashboard-enter [animation-delay:250ms]"><p>Revenue</p><strong>&#8369;0.00</strong><span>0 accounts</span></article><article class="apm-summary-card dashboard-enter [animation-delay:300ms] sm:col-span-2 xl:col-span-1"><p>Expense</p><strong>&#8369;0.00</strong><span>0 accounts</span></article>
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
            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                <div><label for="account-code" class="mb-1.5 block text-xs font-medium text-slate-700">Code</label><input id="account-code" name="code" type="text" value="1" readonly class="h-10 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 font-mono text-xs text-slate-600 outline-none"></div>
                <div><label for="account-name" class="mb-1.5 block text-xs font-medium text-slate-700">Account Name <span class="text-red-500">*</span></label><input id="account-name" name="accountName" type="text" required maxlength="100" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><p class="mt-1 min-h-4 text-[11px] text-red-600" data-error="accountName"></p></div>
                <div><label for="account-type" class="mb-1.5 block text-xs font-medium text-slate-700">Type <span class="text-red-500">*</span></label><select id="account-type" name="type" required class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="">Select type</option><option>Asset</option><option>Liability</option><option>Equity</option><option>Revenue</option><option>Expense</option></select><p class="mt-1 min-h-4 text-[11px] text-red-600" data-error="type"></p></div>
                <div><label for="account-sub-type" class="mb-1.5 block text-xs font-medium text-slate-700">Sub-Type <span class="text-red-500">*</span></label><input id="account-sub-type" name="subType" type="text" required maxlength="100" placeholder="e.g. Current Asset" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><p class="mt-1 min-h-4 text-[11px] text-red-600" data-error="subType"></p></div>
                <div><label for="account-balance" class="mb-1.5 block text-xs font-medium text-slate-700">Balance <span class="text-red-500">*</span></label><input id="account-balance" name="balance" type="number"  min="0" step="0.01" required class="h-10 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><p class="mt-1 min-h-4 text-[11px] text-red-600" data-error="balance"></p></div>
                <div><label for="account-status" class="mb-1.5 block text-xs font-medium text-slate-700">Status <span class="text-red-500">*</span></label><select id="account-status" name="status" required class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="Active">Active</option><option value="Inactive">Inactive</option></select><p class="mt-1 min-h-4 text-[11px] text-red-600" data-error="status"></p></div>
            </div>
            <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4"><button type="button" class="apm-outline-button" data-modal-close>Cancel</button><button id="create-account-button" type="submit" class="apm-primary-button">Add Account</button></footer>
        </form>
    </section>
</div>

<div id="account-toast" class="fixed right-4 bottom-4 z-50 hidden rounded-lg border border-emerald-200 bg-white px-4 py-3 text-xs font-medium text-emerald-700 shadow-lg" role="status">Account added successfully.</div>
@endsection
