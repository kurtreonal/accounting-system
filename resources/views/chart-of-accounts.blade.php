@extends('layouts.accounting', ['pageTitle' => 'Chart of Accounts', 'activePage' => 'chart-of-accounts'])

@section('content')
<main class="p-4 sm:p-5">
    <div class="dashboard-enter">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500"><span>Accounting</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Chart of Accounts</span></nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-xl font-bold text-slate-900">Chart of Accounts</h1><p class="mt-1 text-sm text-slate-500">Manage your accounting structure and account codes</p></div>
            <button type="button" class="apm-primary-button self-start"><svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>New Account</button>
        </div>
    </div>

    <section class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:50ms]">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-sm font-semibold text-slate-900">All Accounts</h2><p class="mt-0.5 text-xs text-slate-500">38 accounts</p></div>
            <div class="flex gap-2"><button type="button" class="apm-outline-button">Export CSV</button><button type="button" class="apm-outline-button">Print</button></div>
        </header>
        <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row">
            <label class="relative sm:w-60"><span class="sr-only">Search accounts</span><svg class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg><input type="search" placeholder="Search account code or name..." class="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-xs outline-none transition duration-150 placeholder:text-slate-400 focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></label>
            <select aria-label="Account type" class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs text-slate-700 outline-none transition duration-150 focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option>All Types</option></select>
            <select aria-label="Account status" class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs text-slate-700 outline-none transition duration-150 focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option>All Status</option></select>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-250 border-collapse text-left">
                <thead><tr class="border-b border-slate-100 text-[11px] font-semibold tracking-wide text-slate-500 uppercase"><th class="w-22 px-4 py-3">Code</th><th class="px-4 py-3">Account Name</th><th class="w-23 px-4 py-3">Type</th><th class="w-40 px-4 py-3">Sub-Type</th><th class="w-41 px-4 py-3">Balance</th><th class="w-28 px-4 py-3">Status</th><th class="w-47 px-4 py-3">Actions</th></tr></thead>
                <tbody class="text-xs text-slate-700">
                    <tr class="apm-table-row"><td class="apm-code">1000</td><td class="apm-account-name">Cash on Hand</td><td>Asset</td><td>Current Asset</td><td class="apm-money">&#8369;245,830.00</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                    <tr class="apm-table-row"><td class="apm-code">1010</td><td class="apm-account-name">Petty Cash Fund</td><td>Asset</td><td>Current Asset</td><td class="apm-money">&#8369;10,000.00</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                    <tr class="apm-table-row"><td class="apm-code">1020</td><td class="apm-account-name">BPI Checking Account</td><td>Asset</td><td>Current Asset</td><td class="apm-money">&#8369;1,482,350.75</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                    <tr class="apm-table-row"><td class="apm-code">1030</td><td class="apm-account-name">BDO Savings Account</td><td>Asset</td><td>Current Asset</td><td class="apm-money">&#8369;876,420.50</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                    <tr class="apm-table-row"><td class="apm-code">1040</td><td class="apm-account-name">Metrobank Dollar Account</td><td>Asset</td><td>Current Asset</td><td class="apm-money">&#8369;125,000.00</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                    <tr class="apm-table-row"><td class="apm-code">1100</td><td class="apm-account-name">Accounts Receivable</td><td>Asset</td><td>Current Asset</td><td class="apm-money">&#8369;987,650.00</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                    <tr class="apm-table-row"><td class="apm-code">1110</td><td class="apm-account-name">Allowance for Bad Debts</td><td>Asset</td><td>Current Asset</td><td class="apm-money text-red-600">-&#8369;15,000.00</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                    <tr class="apm-table-row"><td class="apm-code">1200</td><td class="apm-account-name">Inventory</td><td>Asset</td><td>Current Asset</td><td class="apm-money">&#8369;234,500.00</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                    <tr class="apm-table-row"><td class="apm-code">1300</td><td class="apm-account-name">Prepaid Expenses</td><td>Asset</td><td>Current Asset</td><td class="apm-money">&#8369;45,000.00</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                    <tr class="apm-table-row"><td class="apm-code">1500</td><td class="apm-account-name">Office Equipment</td><td>Asset</td><td>Fixed Asset</td><td class="apm-money">&#8369;480,000.00</td><td><span class="apm-active-badge">Active</span></td><td class="apm-actions"><button type="button">Edit</button><button type="button">Deactivate</button></td></tr>
                </tbody>
            </table>
        </div>
        <footer class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-500">Showing 1–10 of 38 records</p><div class="flex items-center gap-1"><button type="button" disabled class="apm-page-button text-slate-300">‹ Prev</button><button type="button" class="apm-page-button border-blue-600 bg-blue-600 text-white hover:bg-blue-500">1</button><button type="button" class="apm-page-button">2</button><button type="button" class="apm-page-button">3</button><button type="button" class="apm-page-button">4</button><button type="button" class="apm-page-button">Next ›</button></div></footer>
    </section>
    <section aria-label="Account type summaries" class="mx-auto mt-4 grid max-w-[1088px] grid-cols-1 gap-3 pb-8 sm:grid-cols-2 xl:grid-cols-5">
        <article class="apm-summary-card dashboard-enter [animation-delay:100ms]"><p>Asset</p><strong>&#8369;6,037,751.25</strong><span>13 accounts</span></article><article class="apm-summary-card dashboard-enter [animation-delay:150ms]"><p>Liability</p><strong>&#8369;807,390.00</strong><span>8 accounts</span></article><article class="apm-summary-card dashboard-enter [animation-delay:200ms]"><p>Equity</p><strong>&#8369;4,138,010.00</strong><span>3 accounts</span></article><article class="apm-summary-card dashboard-enter [animation-delay:250ms]"><p>Revenue</p><strong>&#8369;6,982,120.00</strong><span>4 accounts</span></article><article class="apm-summary-card dashboard-enter [animation-delay:300ms] sm:col-span-2 xl:col-span-1"><p>Expense</p><strong>&#8369;4,913,650.00</strong><span>10 accounts</span></article>
    </section>
</main>
@endsection
