@extends('layouts.accounting', ['pageTitle' => 'Dashboard', 'activePage' => 'dashboard'])

@section('content')
<main id="dashboard-page" class="p-4 sm:p-5">
    <div class="dashboard-enter print:hidden">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500"><span>Overview</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Dashboard</span></nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-xl font-bold text-slate-900">Dashboard</h1><p class="mt-1 text-sm text-slate-500">Live summary from current accounting records</p></div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="apm-outline-button" data-print-page><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
                @if ($demoCan('drafts.manage'))
                    <a href="{{ route('sales-revenue', ['new' => 'invoice']) }}" class="apm-outline-button"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> New Invoice</a>
                    <a href="{{ route('journal-entries', ['new' => 1]) }}" class="apm-primary-button"><i class="fa-solid fa-plus" aria-hidden="true"></i> New Journal Entry</a>
                @endif
            </div>
        </div>
    </div>

    @php
        $kpiLabels = [
            'cash' => 'Cash / Bank Balance',
            'receivables' => 'Accounts Receivable',
            'payables' => 'Accounts Payable',
            'revenue' => 'Revenue',
            'expenses' => 'Expenses',
            'net_income' => 'Net Income',
            'overdue_receivables' => 'Overdue Receivables',
            'overdue_payables' => 'Overdue Payables',
        ];
    @endphp
    <section aria-label="Financial overview" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpiLabels as $key => $label)
            @php($metric = $dashboard['kpis'][$key])
            <article class="apm-summary-card dashboard-enter" style="animation-delay: {{ 50 + ($loop->index * 40) }}ms">
                <p>{{ $label }}</p>
                <strong class="{{ $metric['available'] && $metric['value'] < 0 ? 'text-red-600!' : '' }}">{{ $metric['available'] ? '₱'.number_format($metric['value'], 2) : '—' }}</strong>
                <span>{{ $metric['note'] }}</span>
            </article>
        @endforeach
    </section>

    <section class="mt-4 grid gap-4 xl:grid-cols-[2fr_1fr]">
        <article class="dashboard-enter overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:390ms]">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">Revenue vs. Expenses</h2><p class="mt-0.5 text-xs text-slate-500">Posted journal activity · {{ now()->format('Y') }}</p></div></header>
            @if ($dashboard['chart_max'] > 0)
                <div class="overflow-x-auto p-5">
                    <div class="flex h-56 min-w-155 items-end gap-3 border-b border-slate-200 px-2">
                        @foreach ($dashboard['monthly'] as $monthNumber => $values)
                            <div class="relative flex h-full flex-1 items-end justify-center gap-1 pb-6" title="{{ now()->month($monthNumber)->format('F') }}: Revenue ₱{{ number_format($values['revenue'], 2) }}, Expenses ₱{{ number_format($values['expenses'], 2) }}">
                                <div class="w-2.5 rounded-t bg-blue-600 transition hover:bg-blue-500" style="height: {{ max(1, ($values['revenue'] / $dashboard['chart_max']) * 88) }}%"></div>
                                <div class="w-2.5 rounded-t bg-rose-500 transition hover:bg-rose-400" style="height: {{ max(1, ($values['expenses'] / $dashboard['chart_max']) * 88) }}%"></div>
                                <span class="absolute mt-5 translate-y-full text-[9px] text-slate-500">{{ now()->month($monthNumber)->format('M') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 flex justify-center gap-5 text-[10px]"><span class="flex items-center gap-1.5"><i class="size-2 rounded-full bg-blue-600"></i>Revenue</span><span class="flex items-center gap-1.5"><i class="size-2 rounded-full bg-rose-500"></i>Expenses</span></div>
                </div>
            @else
                <div class="grid h-64 place-items-center p-6 text-center"><div><i class="fa-solid fa-chart-line text-3xl text-slate-300" aria-hidden="true"></i><p class="mt-3 text-sm font-medium text-slate-600">No posted revenue or expense data</p><p class="mt-1 text-xs text-slate-400">Posted journals using Revenue or Expense accounts appear here.</p></div></div>
            @endif
        </article>

        <article class="dashboard-enter overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:430ms]">
            <header class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Cash Position</h2><p class="mt-0.5 text-xs text-slate-500">Accounts identified as cash or bank</p></header>
            @forelse ($dashboard['cash_accounts'] as $account)
                @if($loop->first)<div class="space-y-3 p-5">@endif
                    <a href="{{ route('general-ledger', ['account' => $account['code']]) }}" class="block rounded-lg border border-slate-100 p-3 transition hover:border-blue-200 hover:bg-blue-50"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-medium text-slate-800">{{ $account['name'] }}</p><span class="mt-1 block font-mono text-[10px] text-slate-400">{{ $account['code'] }}</span></div><strong class="font-mono text-xs {{ $account['balance'] < 0 ? 'text-red-600' : 'text-slate-800' }}">₱{{ number_format($account['balance'], 2) }}</strong></div></a>
                @if($loop->last)</div>@endif
            @empty
                <div class="grid h-64 place-items-center p-6 text-center"><div><i class="fa-solid fa-building-columns text-3xl text-slate-300" aria-hidden="true"></i><p class="mt-3 text-sm font-medium text-slate-600">No cash or bank accounts</p><p class="mt-1 text-xs text-slate-400">Add matching accounts in Chart of Accounts.</p></div></div>
            @endforelse
        </article>
    </section>

    <section class="mt-4 grid gap-4 xl:grid-cols-2">
        <article class="dashboard-enter overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">Recent Customer Payments</h2><p class="mt-0.5 text-xs text-slate-500">Current shared receipt data</p></div><a href="{{ route('accounts-receivable') }}" class="apm-outline-button">View AR</a></header>
            <div class="divide-y divide-slate-100">
                @forelse ($dashboard['recent_customer_payments'] as $payment)
                    <div class="flex items-center justify-between gap-4 px-5 py-3 text-xs"><div><p class="font-medium text-slate-800">{{ $payment['customer_name'] }}</p><p class="mt-1 text-[10px]"><button type="button" data-record-detail data-record-resource="customer_payment" data-record-id="{{ $payment['receipt_number'] }}" class="font-mono text-blue-600 hover:underline">{{ $payment['receipt_number'] }}</button><span class="text-slate-400"> · {{ $payment['payment_date'] }}</span></p></div><strong class="font-mono text-slate-800">&#8369;{{ number_format($payment['amount'], 2) }}</strong></div>
                @empty
                    <p class="px-5 py-10 text-center text-xs text-slate-500">No customer payments recorded.</p>
                @endforelse
            </div>
        </article>
        <article class="dashboard-enter overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">Recent Vendor Payments</h2><p class="mt-0.5 text-xs text-slate-500">Current shared disbursement data</p></div><a href="{{ route('accounts-payable') }}" class="apm-outline-button">View AP</a></header>
            <div class="divide-y divide-slate-100">
                @forelse ($dashboard['recent_vendor_payments'] as $payment)
                    <div class="flex items-center justify-between gap-4 px-5 py-3 text-xs"><div><p class="font-medium text-slate-800">{{ $payment['vendor_name'] }}</p><p class="mt-1 text-[10px]"><button type="button" data-record-detail data-record-resource="vendor_payment" data-record-id="{{ $payment['payment_number'] }}" class="font-mono text-blue-600 hover:underline">{{ $payment['payment_number'] }}</button><span class="text-slate-400"> · {{ $payment['payment_date'] }}</span></p></div><strong class="font-mono text-slate-800">&#8369;{{ number_format($payment['amount'], 2) }}</strong></div>
                @empty
                    <p class="px-5 py-10 text-center text-xs text-slate-500">No vendor payments recorded.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="dashboard-enter mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:470ms]">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">Recent Journal Entries</h2><p class="mt-0.5 text-xs text-slate-500">Current journal data</p></div><a href="{{ route('journal-entries') }}" class="apm-outline-button">View All</a></header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-190 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase"><tr><th class="px-4 py-3 font-semibold">Reference</th><th class="px-4 py-3 font-semibold">Description</th><th class="px-4 py-3 font-semibold">Date</th><th class="px-4 py-3 font-semibold">Source</th><th class="px-4 py-3 text-right font-semibold">Amount</th><th class="px-4 py-3 font-semibold">Status</th></tr></thead>
                <tbody class="text-slate-700">
                    @forelse ($dashboard['recent_journals'] as $entry)
                        @php($statusClass = match($entry['status']) {'Posted' => 'bg-emerald-100 text-emerald-700', 'For Review' => 'bg-amber-100 text-amber-700', 'Reversed' => 'bg-red-100 text-red-700', default => 'bg-slate-100 text-slate-600'})
                        <tr class="apm-table-row"><td><button type="button" data-record-detail data-record-resource="journal_entry" data-record-id="{{ $entry['journal_number'] }}" class="font-mono text-[11px] font-medium text-blue-600 hover:underline">{{ $entry['journal_number'] }}</button></td><td class="font-medium text-slate-800">{{ $entry['description'] }}</td><td class="font-mono text-[11px]">{{ $entry['date'] }}</td><td>{{ $entry['source_type'] }}</td><td class="apm-money text-right">₱{{ number_format($entry['total_debit'], 2) }}</td><td><span class="inline-flex rounded-md px-2 py-1 text-[10px] font-medium {{ $statusClass }}">{{ $entry['status'] }}</span></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center"><i class="fa-solid fa-book text-2xl text-slate-300" aria-hidden="true"></i><p class="mt-2 text-sm font-medium text-slate-600">No journal entries yet</p><p class="mt-1 text-xs text-slate-400">Create first journal entry to populate dashboard activity.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
