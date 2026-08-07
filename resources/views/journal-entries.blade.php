@extends('layouts.accounting', ['pageTitle' => 'Journal Entries', 'activePage' => 'journal-entries'])

@section('content')
<main class="p-4 sm:p-5">
    <div class="dashboard-enter">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500">
            <span>Accounting</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Journal Entries</span>
        </nav>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Journal Entries</h1>
                <p class="mt-1 text-sm text-slate-500">Record and manage all accounting transactions</p>
            </div>
            <button type="button" class="apm-primary-button self-start">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                New Journal Entry
            </button>
        </div>

        <div class="mt-5 flex gap-1 overflow-x-auto border-b border-slate-200 sm:gap-3">
            <button type="button" class="apm-tab border-blue-600 text-blue-600">All Entries</button>
            <button type="button" class="apm-tab">Draft</button>
            <button type="button" class="apm-tab">For Review</button>
            <button type="button" class="apm-tab">Posted</button>
            <button type="button" class="apm-tab">Reversed</button>
        </div>
    </div>

    <section class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:50ms]">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Journal Entries</h2>
                <p class="mt-0.5 text-xs text-slate-500">6 entries</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="apm-outline-button">Export</button>
                <button type="button" class="apm-outline-button">Print</button>
            </div>
        </header>

        <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row">
            <label class="relative sm:w-60">
                <span class="sr-only">Search journal entries</span>
                <svg class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                <input type="search" placeholder="Search journal ID or description..." class="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-xs outline-none transition duration-150 placeholder:text-slate-400 focus:border-blue-400 focus:ring-3 focus:ring-blue-100">
            </label>
            <select aria-label="Journal entry status" class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs text-slate-700 outline-none transition duration-150 focus:border-blue-400 focus:ring-3 focus:ring-blue-100 sm:w-30">
                <option>All</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-250 border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                        <th class="w-27 px-4 py-3">JE No.</th>
                        <th class="w-26 px-4 py-3">Date</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="w-34 px-4 py-3">Reference</th>
                        <th class="w-33 px-4 py-3">Total Debit</th>
                        <th class="w-30 px-4 py-3">Prepared By</th>
                        <th class="w-27 px-4 py-3">Status</th>
                        <th class="w-23 px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-xs text-slate-700">
                    <tr class="apm-table-row">
                        <td><button type="button" class="font-mono text-[11px] font-medium text-blue-600 transition duration-150 hover:text-blue-800 hover:underline">JE-2024-0089</button></td>
                        <td class="font-mono text-[11px]">2024-12-30</td><td class="font-medium">Service revenue recognition</td><td class="font-mono text-[11px]">INV-2024-0234</td><td class="apm-money">&#8369;280,000.00</td><td>Ana Cruz</td><td><span class="apm-active-badge">Posted</span></td><td><button type="button" class="apm-row-action">Reverse</button></td>
                    </tr>
                    <tr class="apm-table-row">
                        <td><button type="button" class="font-mono text-[11px] font-medium text-blue-600 transition duration-150 hover:text-blue-800 hover:underline">JE-2024-0088</button></td>
                        <td class="font-mono text-[11px]">2024-12-29</td><td class="font-medium">Payment of monthly office rent</td><td class="font-mono text-[11px]">BILL-2024-0112</td><td class="apm-money">&#8369;40,000.00</td><td>Ana Cruz</td><td><span class="apm-active-badge">Posted</span></td><td><button type="button" class="apm-row-action">Reverse</button></td>
                    </tr>
                    <tr class="apm-table-row">
                        <td><button type="button" class="font-mono text-[11px] font-medium text-blue-600 transition duration-150 hover:text-blue-800 hover:underline">JE-2024-0087</button></td>
                        <td class="font-mono text-[11px]">2024-12-28</td><td class="font-medium">Semi-monthly payroll</td><td class="font-mono text-[11px]">PAY-2024-0345</td><td class="apm-money">&#8369;345,000.00</td><td>Ana Cruz</td><td><span class="apm-active-badge">Posted</span></td><td><button type="button" class="apm-row-action">Reverse</button></td>
                    </tr>
                    <tr class="apm-table-row">
                        <td><button type="button" class="font-mono text-[11px] font-medium text-blue-600 transition duration-150 hover:text-blue-800 hover:underline">JE-2024-0086</button></td>
                        <td class="font-mono text-[11px]">2024-12-27</td><td class="font-medium">Product sales</td><td class="font-mono text-[11px]">INV-2024-0233</td><td class="apm-money">&#8369;125,440.00</td><td>Ana Cruz</td><td><span class="inline-flex rounded-md bg-amber-100 px-2 py-1 text-[10px] font-medium text-amber-700">For Review</span></td><td><button type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-[11px] font-medium text-white transition duration-150 hover:-translate-y-0.5 hover:bg-blue-500 active:translate-y-0 active:scale-95">Post</button></td>
                    </tr>
                    <tr class="apm-table-row">
                        <td><button type="button" class="font-mono text-[11px] font-medium text-blue-600 transition duration-150 hover:text-blue-800 hover:underline">JE-2024-0085</button></td>
                        <td class="font-mono text-[11px]">2024-12-26</td><td class="font-medium">Office supplies purchase</td><td class="font-mono text-[11px]">EXP-2024-0078</td><td class="apm-money">&#8369;8,500.00</td><td>Ana Cruz</td><td><span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-600">Draft</span></td><td><button type="button" class="apm-row-action">Review</button></td>
                    </tr>
                    <tr class="apm-table-row">
                        <td><button type="button" class="font-mono text-[11px] font-medium text-blue-600 transition duration-150 hover:text-blue-800 hover:underline">JE-2024-0084</button></td>
                        <td class="font-mono text-[11px]">2024-12-24</td><td class="font-medium">Service revenue recognition</td><td class="font-mono text-[11px]">INV-2024-0232</td><td class="apm-money">&#8369;560,000.00</td><td>Ana Cruz</td><td><span class="apm-active-badge">Posted</span></td><td><button type="button" class="apm-row-action">Reverse</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">Showing 1–6 of 6 records</p>
            <div class="flex items-center gap-1">
                <button type="button" disabled class="apm-page-button text-slate-300">‹ Prev</button>
                <button type="button" class="apm-page-button border-blue-600 bg-blue-600 text-white hover:bg-blue-500">1</button>
                <button type="button" disabled class="apm-page-button text-slate-300">Next ›</button>
            </div>
        </footer>
    </section>
</main>
@endsection
