@extends('layouts.accounting', ['pageTitle' => 'Journal Entries', 'activePage' => 'journal-entries'])

@section('content')
<main id="journal-entries-page" class="p-4 sm:p-5 print:p-0"
    data-store-url="{{ route('journal-entries.store') }}"
    data-update-url-template="{{ route('journal-entries.update', ['journalNumber' => '__NUMBER__']) }}"
    data-delete-url-template="{{ route('journal-entries.destroy', ['journalNumber' => '__NUMBER__']) }}"
    data-submit-url-template="{{ route('journal-entries.submit-review', ['journalNumber' => '__NUMBER__']) }}"
    data-return-url-template="{{ route('journal-entries.return-draft', ['journalNumber' => '__NUMBER__']) }}"
    data-post-url-template="{{ route('journal-entries.post', ['journalNumber' => '__NUMBER__']) }}"
    data-reverse-url-template="{{ route('journal-entries.reverse', ['journalNumber' => '__NUMBER__']) }}"
    data-print-url-template="{{ route('journal-entries.print', ['journalNumber' => '__NUMBER__']) }}"
    data-user-role="{{ $user['role'] }}">
    <div class="dashboard-enter">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500">
            <span>Accounting</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Journal Entries</span>
        </nav>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Journal Entries</h1>
                <p class="mt-1 text-sm text-slate-500">Record and manage all accounting transactions</p>
            </div>
            <button id="new-journal-button" type="button" class="apm-primary-button self-start disabled:cursor-not-allowed disabled:opacity-50 print:hidden"
                @disabled($user['role'] === 'Viewer / Auditor')
                title="{{ $user['role'] === 'Viewer / Auditor' ? 'Viewer / Auditor has read-only access.' : 'Create journal entry' }}">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                New Journal Entry
            </button>
        </div>

        <div id="journal-tabs" class="mt-5 flex gap-1 overflow-x-auto border-b border-slate-200 sm:gap-3 print:hidden" role="tablist" aria-label="Journal entry status filters">
            @foreach (['' => 'All Entries', 'Draft' => 'Draft', 'For Review' => 'For Review', 'Posted' => 'Posted', 'Reversed' => 'Reversed'] as $status => $label)
                <button type="button" role="tab" data-status="{{ $status }}" aria-selected="{{ $status === '' ? 'true' : 'false' }}" class="apm-tab {{ $status === '' ? 'border-blue-600 text-blue-600' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <section class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:50ms] print:border-0 print:shadow-none">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Journal Entries</h2>
                <p id="journal-count" class="mt-0.5 text-xs text-slate-500">{{ count($journalEntries) }} {{ Str::plural('entry', count($journalEntries)) }}</p>
            </div>
            <div class="flex gap-2 print:hidden">
                <a id="journal-export" href="{{ route('journal-entries.export.csv') }}" class="apm-outline-button">Export CSV</a>
                <button id="journal-print-list" type="button" class="apm-outline-button">Print</button>
            </div>
        </header>

        <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row print:hidden">
            <label class="relative sm:w-72">
                <span class="sr-only">Search journal entries</span>
                <svg class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                <input id="journal-search" type="search" placeholder="Search journal number, reference, or description" class="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-xs outline-none transition duration-150 placeholder:text-slate-400 focus:border-blue-400 focus:ring-3 focus:ring-blue-100">
            </label>
            <select id="journal-status-filter" aria-label="Journal entry status" class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs text-slate-700 outline-none transition duration-150 focus:border-blue-400 focus:ring-3 focus:ring-blue-100 sm:w-36">
                <option value="">All Status</option><option>Draft</option><option>For Review</option><option>Posted</option><option>Reversed</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-275 border-collapse text-left">
                <thead><tr class="border-b border-slate-100 text-[11px] font-semibold tracking-wide text-slate-500 uppercase"><th class="w-31 px-4 py-3">JE No.</th><th class="w-27 px-4 py-3">Date</th><th class="px-4 py-3">Description</th><th class="w-35 px-4 py-3">Reference</th><th class="w-33 px-4 py-3">Total Debit</th><th class="w-30 px-4 py-3">Prepared By</th><th class="w-28 px-4 py-3">Status</th><th class="w-62 px-4 py-3">Actions</th></tr></thead>
                <tbody id="journal-table-body" class="text-xs text-slate-700"></tbody>
            </table>
        </div>

        <footer class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <p id="journal-record-count" class="text-xs text-slate-500">Showing 0 records</p>
            <div id="journal-pagination" class="flex items-center gap-1" aria-label="Journal entry pagination"></div>
        </footer>
    </section>
</main>

<div id="journal-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="journal-modal-title" aria-hidden="true">
    <button type="button" tabindex="-1" class="absolute inset-0 cursor-default bg-slate-950/55" data-journal-close aria-label="Close journal entry modal"></button>
    <section id="journal-modal-panel" class="relative z-10 flex max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
        <header class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4">
            <div><h2 id="journal-modal-title" class="text-sm font-semibold text-slate-900">New Journal Entry</h2><p id="journal-modal-status" class="mt-0.5 text-xs text-slate-500">Draft</p></div>
            <button type="button" class="apm-icon-button" data-journal-close aria-label="Close"><svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
        </header>

        <form id="journal-form" class="min-h-0 overflow-y-auto" novalidate>
            @csrf
            <div class="grid gap-4 border-b border-slate-100 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><label for="journal-number" class="mb-1.5 block text-xs font-medium text-slate-700">Journal Number</label><input id="journal-number" type="text" readonly class="h-10 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 font-mono text-xs text-slate-600" value="Generated when saved"></div>
                <div><label for="journal-date" class="mb-1.5 block text-xs font-medium text-slate-700">Transaction Date <span class="text-red-500">*</span></label><input id="journal-date" type="date" required class="h-10 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><p class="mt-1 text-[11px] text-red-600" data-journal-error="date"></p></div>
                <div><label for="journal-reference" class="mb-1.5 block text-xs font-medium text-slate-700">Reference Number</label><input id="journal-reference" type="text" maxlength="50" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><p class="mt-1 text-[11px] text-red-600" data-journal-error="reference"></p></div>
                <div><label for="journal-source" class="mb-1.5 block text-xs font-medium text-slate-700">Source Type <span class="text-red-500">*</span></label><select id="journal-source" required class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"><option value="Manual">Manual</option><option>Invoice</option><option>Payment</option><option>Bill</option><option>Expense</option><option value="Reversal" disabled>Reversal</option></select><p class="mt-1 text-[11px] text-red-600" data-journal-error="source_type"></p></div>
                <div class="sm:col-span-2 lg:col-span-4"><label for="journal-description" class="mb-1.5 block text-xs font-medium text-slate-700">Description / Memo <span class="text-red-500">*</span></label><textarea id="journal-description" required maxlength="255" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100"></textarea><p class="mt-1 text-[11px] text-red-600" data-journal-error="description"></p></div>
            </div>

            <div class="px-5 py-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div><h3 class="text-sm font-semibold text-slate-900">Journal Lines</h3><p class="mt-0.5 text-xs text-slate-500">Minimum two lines. Each line needs either debit or credit.</p></div>
                    <button id="add-journal-line" type="button" class="apm-outline-button self-start">+ Add Line</button>
                </div>
                @if (count($activeAccounts) === 0)
                    <p id="no-active-accounts" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">No active accounts available. Add accounts in Chart of Accounts before creating journal entries.</p>
                @endif
                <p class="mt-2 text-[11px] text-red-600" data-journal-error="lines"></p>
                <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full min-w-250 text-left">
                        <thead><tr class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase"><th class="min-w-52 px-3 py-2">Account *</th><th class="min-w-43 px-3 py-2">Line Description</th><th class="min-w-38 px-3 py-2">Customer / Vendor Ref.</th><th class="min-w-33 px-3 py-2">Cost Center</th><th class="w-31 px-3 py-2 text-right">Debit</th><th class="w-31 px-3 py-2 text-right">Credit</th><th class="w-16 px-3 py-2"></th></tr></thead>
                        <tbody id="journal-lines"></tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50 text-xs font-semibold text-slate-800"><tr><td colspan="4" class="px-3 py-3 text-right">Totals</td><td id="journal-total-debit" class="px-3 py-3 text-right font-mono">&#8369;0.00</td><td id="journal-total-credit" class="px-3 py-3 text-right font-mono">&#8369;0.00</td><td></td></tr></tfoot>
                    </table>
                </div>
                <div id="journal-balance-state" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">Entry not balanced.</div>
            </div>

            <footer class="sticky bottom-0 flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-white px-5 py-4 shadow-[0_-4px_12px_rgba(15,23,42,0.04)]">
                <button type="button" class="apm-outline-button" data-journal-close>Cancel</button>
                <a id="journal-modal-print" href="#" target="_blank" rel="noopener" class="apm-outline-button hidden">Print</a>
                <button id="save-journal-draft" type="submit" class="apm-outline-button">Save Draft</button>
                <button id="save-submit-journal" type="button" class="apm-primary-button">Save &amp; Submit for Review</button>
            </footer>
        </form>
    </section>
</div>

<template id="journal-line-template">
    <tr class="border-t border-slate-100 align-top" data-journal-line>
        <td class="px-2 py-2"><select data-line-field="account_code" aria-label="Account" class="h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-xs outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"><option value="">Select account</option>@foreach ($activeAccounts as $account)<option value="{{ $account['code'] }}">{{ $account['code'] }} - {{ $account['name'] }}</option>@endforeach</select><p class="mt-1 text-[10px] text-red-600" data-line-error="account_code"></p></td>
        <td class="px-2 py-2"><input data-line-field="description" aria-label="Line description" type="text" maxlength="150" class="h-9 w-full rounded-md border border-slate-200 px-2 text-xs outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"></td>
        <td class="px-2 py-2"><input data-line-field="party_reference" aria-label="Customer or vendor reference" type="text" maxlength="100" class="h-9 w-full rounded-md border border-slate-200 px-2 text-xs outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"></td>
        <td class="px-2 py-2"><input data-line-field="cost_center" aria-label="Cost center" type="text" maxlength="100" class="h-9 w-full rounded-md border border-slate-200 px-2 text-xs outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"></td>
        <td class="px-2 py-2"><input data-line-field="debit" aria-label="Debit amount" type="number" min="0" step="0.01" inputmode="decimal" class="h-9 w-full rounded-md border border-slate-200 px-2 text-right font-mono text-xs outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"><p class="mt-1 text-[10px] text-red-600" data-line-error="amount"></p></td>
        <td class="px-2 py-2"><input data-line-field="credit" aria-label="Credit amount" type="number" min="0" step="0.01" inputmode="decimal" class="h-9 w-full rounded-md border border-slate-200 px-2 text-right font-mono text-xs outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"></td>
        <td class="px-2 py-2 text-center"><button type="button" data-remove-line class="rounded-md px-2 py-2 text-xs text-red-600 transition hover:bg-red-50 active:scale-90" aria-label="Remove journal line">Remove</button></td>
    </tr>
</template>

<div id="journal-toast" class="fixed right-4 bottom-4 z-[60] hidden max-w-sm rounded-lg border bg-white px-4 py-3 text-xs font-medium shadow-lg" role="status"></div>
<script id="journal-entry-data" type="application/json">@json($journalEntries)</script>
@endsection
