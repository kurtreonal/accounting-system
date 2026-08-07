@extends('layouts.accounting', ['pageTitle' => 'Dashboard', 'activePage' => 'dashboard'])

@section('content')
<main class="p-4 sm:p-5">
    <div class="dashboard-enter">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500"><span>Overview</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Dashboard</span></nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-xl font-bold text-slate-900">Dashboard</h1><p class="mt-1 text-sm text-slate-500">Overview of your business and financial activity</p></div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="apm-outline-button">New Invoice</button>
                <button type="button" class="apm-primary-button"><svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>New Journal Entry</button>
            </div>
        </div>
    </div>

    <section aria-label="Financial overview" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article class="apm-summary-card dashboard-enter [animation-delay:50ms]"><p>Total Assets</p><strong>&#8369;6,037,751.25</strong><span>13 accounts</span></article>
        <article class="apm-summary-card dashboard-enter [animation-delay:100ms]"><p>Total Liabilities</p><strong>&#8369;807,390.00</strong><span>8 accounts</span></article>
        <article class="apm-summary-card dashboard-enter [animation-delay:150ms]"><p>Cash on Hand</p><strong>&#8369;245,830.00</strong><span>As of today</span></article>
        <article class="apm-summary-card dashboard-enter [animation-delay:200ms]"><p>Accounts Receivable</p><strong>&#8369;987,650.00</strong><span>6 open invoices</span></article>
        <article class="apm-summary-card dashboard-enter [animation-delay:250ms] sm:col-span-2 xl:col-span-1"><p>Net Income</p><strong>&#8369;2,068,470.00</strong><span>Current period</span></article>
    </section>

    <section class="mt-4 grid gap-4 xl:grid-cols-3">
        <article class="dashboard-enter overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2 [animation-delay:300ms]">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">Revenue vs. Expenses</h2><p class="mt-0.5 text-xs text-slate-500">Monthly performance</p></div><button type="button" class="apm-outline-button">View Report</button></header>
            <div class="overflow-x-auto px-4 pt-4 pb-3">
                <svg class="h-62 min-w-150 w-full" viewBox="0 0 720 250" role="img" aria-label="Static revenue and expense trend chart">
                    <defs><linearGradient id="dashboard-blue" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2563eb" stop-opacity=".18"/><stop offset="1" stop-color="#2563eb" stop-opacity=".02"/></linearGradient></defs>
                    <g stroke="#dbe4ef" stroke-dasharray="3 4"><path d="M55 20H700M55 70H700M55 120H700M55 170H700M55 220H700"/></g>
                    <path d="M55 162 C105 126 135 139 177 111 S250 91 291 106 S365 82 408 68 S482 88 524 57 S605 48 700 33 V220H55Z" fill="url(#dashboard-blue)"/>
                    <path d="M55 162 C105 126 135 139 177 111 S250 91 291 106 S365 82 408 68 S482 88 524 57 S605 48 700 33" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M55 184 C110 170 142 173 185 157 S256 151 303 145 S372 154 420 129 S496 141 545 119 S628 125 700 103" fill="none" stroke="#f43f5e" stroke-width="2.5" stroke-linecap="round"/>
                    <g fill="#71819b" font-family="Inter, sans-serif" font-size="10"><text x="13" y="23">&#8369;1.2M</text><text x="19" y="73">&#8369;900K</text><text x="19" y="123">&#8369;600K</text><text x="19" y="173">&#8369;300K</text><text x="34" y="223">&#8369;0</text></g>
                    <g fill="#71819b" font-family="Inter, sans-serif" font-size="10" text-anchor="middle"><text x="55" y="240">Jan</text><text x="114" y="240">Feb</text><text x="173" y="240">Mar</text><text x="232" y="240">Apr</text><text x="291" y="240">May</text><text x="350" y="240">Jun</text><text x="409" y="240">Jul</text><text x="468" y="240">Aug</text><text x="527" y="240">Sep</text><text x="586" y="240">Oct</text><text x="645" y="240">Nov</text><text x="700" y="240">Dec</text></g>
                </svg>
                <div class="mt-2 flex justify-center gap-5 text-[11px]"><span class="flex items-center gap-1.5 text-blue-600"><i class="size-2 rounded-full bg-blue-600"></i>Revenue</span><span class="flex items-center gap-1.5 text-rose-500"><i class="size-2 rounded-full bg-rose-500"></i>Expenses</span></div>
            </div>
        </article>

        <article class="dashboard-enter overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:350ms]">
            <header class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Cash Position</h2><p class="mt-0.5 text-xs text-slate-500">Cash and bank accounts</p></header>
            <div class="space-y-4 p-5">
                <div><div class="mb-1.5 flex justify-between text-xs"><span>Bank Accounts</span><strong class="font-mono">&#8369;2,358,771.25</strong></div><div class="h-2 rounded-full bg-slate-100"><div class="h-2 w-[82%] rounded-full bg-blue-600"></div></div></div>
                <div><div class="mb-1.5 flex justify-between text-xs"><span>Cash on Hand</span><strong class="font-mono">&#8369;245,830.00</strong></div><div class="h-2 rounded-full bg-slate-100"><div class="h-2 w-[34%] rounded-full bg-emerald-500"></div></div></div>
                <div><div class="mb-1.5 flex justify-between text-xs"><span>Petty Cash</span><strong class="font-mono">&#8369;10,000.00</strong></div><div class="h-2 rounded-full bg-slate-100"><div class="h-2 w-[12%] rounded-full bg-amber-500"></div></div></div>
                <div class="rounded-lg bg-slate-50 p-4"><p class="text-xs text-slate-500">Total available cash</p><p class="mt-1 font-mono text-lg font-bold text-slate-900">&#8369;2,614,601.25</p></div>
            </div>
        </article>
    </section>

    <section class="dashboard-enter mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:400ms]">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">Recent Transactions</h2><p class="mt-0.5 text-xs text-slate-500">Latest recorded activity</p></div><button type="button" class="apm-outline-button">View All</button></header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-200 border-collapse text-left">
                <thead><tr class="border-b border-slate-100 text-[11px] font-semibold tracking-wide text-slate-500 uppercase"><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Status</th></tr></thead>
                <tbody class="text-xs text-slate-700">
                    <tr class="apm-table-row"><td class="apm-code text-blue-600">JE-2024-0089</td><td class="apm-account-name">Service revenue recognition</td><td>Dec 30, 2024</td><td>Journal Entry</td><td class="apm-money text-right">&#8369;185,000.00</td><td><span class="apm-active-badge">Posted</span></td></tr>
                    <tr class="apm-table-row"><td class="apm-code text-blue-600">INV-2024-0234</td><td class="apm-account-name">Accenture Philippines, Inc.</td><td>Dec 29, 2024</td><td>Sales Invoice</td><td class="apm-money text-right">&#8369;280,000.00</td><td><span class="rounded-md bg-amber-100 px-2 py-1 text-[10px] font-medium text-amber-700">Pending</span></td></tr>
                    <tr class="apm-table-row"><td class="apm-code text-blue-600">EXP-2024-0076</td><td class="apm-account-name">Office toner cartridges</td><td>Dec 23, 2024</td><td>Expense</td><td class="apm-money text-right text-red-600">-&#8369;18,400.00</td><td><span class="apm-active-badge">Posted</span></td></tr>
                    <tr class="apm-table-row"><td class="apm-code text-blue-600">PAY-2024-0058</td><td class="apm-account-name">Customer payment received</td><td>Dec 22, 2024</td><td>Receipt</td><td class="apm-money text-right">&#8369;125,440.00</td><td><span class="apm-active-badge">Posted</span></td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
