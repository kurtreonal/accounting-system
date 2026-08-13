@extends('layouts.accounting', ['pageTitle' => 'Cash & Bank', 'activePage' => 'cash-bank'])

@section('content')
<main id="cash-bank-page" class="p-4 sm:p-5" data-account-url="{{ route('cash-bank.accounts.store') }}" data-transaction-url="{{ route('cash-bank.transactions.store') }}" data-reconciliation-url="{{ route('cash-bank.reconciliations.store') }}" data-user-role="{{ $user['role'] }}">
    <div class="dashboard-enter">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500"><span>Banking</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Cash &amp; Bank</span></nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div><h1 class="text-xl font-bold text-slate-900">Cash &amp; Bank</h1><p class="mt-1 text-xs text-slate-500">Manage cash accounts, transactions, transfers, and reconciliation.</p></div>
            <div class="flex flex-wrap gap-2 print:hidden">
                <button type="button" class="apm-outline-button" data-open-transaction="deposit"><i class="fa-solid fa-plus mr-1"></i>Deposit</button>
                <button type="button" class="apm-outline-button" data-open-transaction="withdrawal"><i class="fa-solid fa-minus mr-1"></i>Withdrawal</button>
                <button type="button" class="apm-outline-button" data-open-transaction="charge"><i class="fa-solid fa-receipt mr-1"></i>Bank Charge</button>
                <button type="button" class="apm-outline-button" data-open-transaction="interest"><i class="fa-solid fa-percent mr-1"></i>Interest</button>
                <button type="button" class="apm-primary-button" data-open-transaction="transfer"><i class="fa-solid fa-arrow-right-arrow-left"></i>Transfer</button>
            </div>
        </div>

        <section class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Cash summary">
            <article class="apm-summary-card"><p>Total Cash &amp; Bank</p><strong>&#8369;{{ number_format((float) $metrics['total'], 2) }}</strong><span>{{ count($cashAccounts) }} active accounts</span></article>
            <article class="apm-summary-card"><p>Bank Accounts</p><strong>{{ $metrics['bank_count'] }}</strong><span>Active bank accounts</span></article>
            <article class="apm-summary-card"><p>Total Inflows</p><strong class="!text-emerald-600">&#8369;{{ number_format((float) $metrics['inflow'], 2) }}</strong><span>Deposits and interest</span></article>
            <article class="apm-summary-card"><p>Total Outflows</p><strong class="!text-rose-600">&#8369;{{ number_format((float) $metrics['outflow'], 2) }}</strong><span>Withdrawals and charges</span></article>
        </section>

        <div class="mt-5 flex gap-1 overflow-x-auto border-b border-slate-200" role="tablist">
            <button class="apm-tab border-blue-600 text-blue-600" data-cb-tab="accounts" role="tab" aria-selected="true">Accounts</button>
            <button class="apm-tab" data-cb-tab="transactions" role="tab" aria-selected="false">Transactions</button>
            <button class="apm-tab" data-cb-tab="reconciliation" role="tab" aria-selected="false">Reconciliation</button>
        </div>

        <section class="mt-4" data-cb-panel="accounts">
            <div class="mb-3 flex items-center justify-between"><p class="text-xs text-slate-500">Balances update from posted journal entries.</p><button id="cb-add-account" type="button" class="apm-outline-button print:hidden"><i class="fa-solid fa-plus mr-1"></i>Add Account</button></div>
            <div id="cb-account-cards" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"></div>
        </section>

        <section class="mt-4 hidden" data-cb-panel="transactions">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row">
                    <label class="relative flex-1"><span class="sr-only">Search transactions</span><i class="fa-solid fa-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-xs text-slate-400"></i><input id="cb-search" type="search" class="h-9 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-xs" placeholder="Search reference or description"></label>
                    <select id="cb-account-filter" class="h-9 rounded-lg border border-slate-200 px-3 text-xs"><option value="">All accounts</option>@foreach($cashAccounts as $account)<option value="{{ $account['code'] }}">{{ $account['name'] }}</option>@endforeach</select>
                    <select id="cb-type-filter" class="h-9 rounded-lg border border-slate-200 px-3 text-xs"><option value="">All types</option><option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option><option value="transfer">Transfer</option><option value="charge">Bank charge</option><option value="interest">Interest</option></select>
                </div>
                <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Reference</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Account</th><th class="px-4 py-3 text-left">Description</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3 text-center">Status</th></tr></thead><tbody id="cb-transaction-rows"></tbody></table></div>
            </div>
        </section>

        <section class="mt-4 hidden" data-cb-panel="reconciliation">
            <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <form id="cb-reconciliation-form" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" novalidate>
                    <div class="flex items-start justify-between"><div><h2 class="font-semibold text-slate-900">Bank reconciliation</h2><p class="mt-1 text-xs text-slate-500">Match book transactions to a statement balance.</p></div><span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-700">Demo</span></div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3"><label class="text-xs text-slate-600">Account *<select name="account_code" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2" required><option value="">Select account</option>@foreach($cashAccounts as $account)<option value="{{ $account['code'] }}">{{ $account['name'] }}</option>@endforeach</select></label><label class="text-xs text-slate-600">Statement date *<input name="statement_date" type="date" value="{{ now()->toDateString() }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2" required></label><label class="text-xs text-slate-600">Statement balance *<input name="statement_balance" type="number" step="0.01" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2" required></label></div>
                    <div id="cb-reconciliation-summary" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4"></div>
                    <div class="mt-4 max-h-72 overflow-auto rounded-lg border border-slate-200"><table class="w-full min-w-[600px] text-xs"><thead class="sticky top-0 bg-slate-50"><tr><th class="p-2 text-left">Clear</th><th class="p-2 text-left">Date</th><th class="p-2 text-left">Reference</th><th class="p-2 text-left">Description</th><th class="p-2 text-right">Amount</th></tr></thead><tbody id="cb-reconcile-rows"><tr><td colspan="5" class="p-8 text-center text-slate-500">Select an account to begin.</td></tr></tbody></table></div>
                    <label class="mt-3 block text-xs text-slate-600">Notes<textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 p-2"></textarea></label>
                    <p data-reconciliation-message class="mt-3 hidden rounded-lg p-3 text-xs"></p><div class="mt-4 flex justify-end"><button id="cb-reconcile-submit" class="apm-primary-button" type="submit">Complete Reconciliation</button></div>
                </form>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold text-slate-900">Reconciliation history</h2><div id="cb-reconciliation-history" class="mt-4 space-y-3"></div></div>
            </div>
        </section>
    </div>
</main>

<div id="cb-transaction-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-hidden="true"><button class="absolute inset-0 bg-slate-950/55" data-cb-close="transaction" aria-label="Close"></button><form id="cb-transaction-form" class="relative z-10 w-full max-w-lg rounded-xl bg-white p-5 shadow-2xl" novalidate><div class="flex items-start justify-between"><div><h2 id="cb-transaction-title" class="font-semibold text-slate-900">Record transaction</h2><p class="mt-1 text-xs text-slate-500">This will post a balanced journal entry.</p></div><button type="button" class="apm-icon-button" data-cb-close="transaction"><i class="fa-solid fa-xmark"></i></button></div><input name="request_token" type="hidden"><input name="type" type="hidden"><div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="text-xs text-slate-600">Date *<input name="date" type="date" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2" required></label><label class="text-xs text-slate-600">Amount *<input name="amount" type="number" min="0.01" step="0.01" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2" required></label><label data-standard-account class="text-xs text-slate-600">Cash / bank account *<select name="account_code" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2"></select></label><label data-standard-account class="text-xs text-slate-600">Offset account *<select name="offset_account_code" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2"></select></label><label data-transfer-account class="hidden text-xs text-slate-600">From account *<select name="from_account_code" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2"></select></label><label data-transfer-account class="hidden text-xs text-slate-600">To account *<select name="to_account_code" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2"></select></label><label class="text-xs text-slate-600 sm:col-span-2">Reference<input name="reference" maxlength="50" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2"></label><label class="text-xs text-slate-600 sm:col-span-2">Description *<textarea name="description" maxlength="180" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 p-2" required></textarea></label></div><p data-transaction-message class="mt-3 hidden rounded-lg p-3 text-xs"></p><div class="mt-4 flex justify-end gap-2"><button type="button" class="apm-outline-button" data-cb-close="transaction">Cancel</button><button id="cb-transaction-submit" type="submit" class="apm-primary-button">Post Transaction</button></div></form></div>

<div id="cb-account-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-hidden="true"><button class="absolute inset-0 bg-slate-950/55" data-cb-close="account" aria-label="Close"></button><form id="cb-account-form" class="relative z-10 w-full max-w-md rounded-xl bg-white p-5 shadow-2xl" novalidate><div class="flex justify-between"><h2 class="font-semibold text-slate-900">Add cash or bank account</h2><button type="button" class="apm-icon-button" data-cb-close="account"><i class="fa-solid fa-xmark"></i></button></div><label class="mt-4 block text-xs text-slate-600">Account name *<input name="name" maxlength="100" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2" required></label><label class="mt-3 block text-xs text-slate-600">Account type *<select name="kind" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2"><option value="Bank">Bank</option><option value="Cash">Cash</option></select></label><p data-account-message class="mt-3 hidden rounded-lg p-3 text-xs"></p><div class="mt-4 flex justify-end gap-2"><button type="button" class="apm-outline-button" data-cb-close="account">Cancel</button><button type="submit" class="apm-primary-button">Create Account</button></div></form></div>

<script id="cb-data" type="application/json">{!! json_encode(['cashAccounts' => $cashAccounts, 'postingAccounts' => $postingAccounts, 'transactions' => $transactions, 'reconciliations' => $reconciliations], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection
