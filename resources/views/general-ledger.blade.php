@extends('layouts.accounting', ['pageTitle' => 'General Ledger', 'activePage' => 'general-ledger'])

@section('content')
<main id="general-ledger-page" class="p-4 sm:p-5 print:p-0"
    data-report-url="{{ route('general-ledger.data') }}"
    data-export-url="{{ route('general-ledger.export.csv') }}"
    data-pdf-url="{{ route('general-ledger.export.pdf') }}"
    data-journal-url="{{ route('journal-entries') }}">
    <p id="ledger-print-context" class="mb-4 hidden text-xs text-slate-500 print:block">
        Period: {{ request('date_from', 'Beginning') }} to {{ request('date_to', 'Present') }}
    </p>
    <div class="dashboard-enter print:hidden">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500">
            <span>Accounting</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">General Ledger</span>
        </nav>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900">General Ledger</h1>
                <p class="mt-1 text-sm text-slate-500">Account-level transaction history with running balances</p>
            </div>
            <div class="flex gap-2">
                <a id="ledger-export" href="{{ $report ? route('general-ledger.export.csv', ['account' => $report['account']['code']]) : '#' }}" class="apm-outline-button {{ $report ? '' : 'pointer-events-none opacity-50' }}">Export CSV</a>
                <a id="ledger-export-pdf" href="{{ $report ? route('general-ledger.export.pdf', ['account' => $report['account']['code']]) : '#' }}" class="apm-primary-button sm:hidden {{ $report ? '' : 'pointer-events-none opacity-50' }}" download>Export PDF</a>
                <button id="ledger-print" type="button" class="apm-outline-button hidden sm:inline-flex" data-print-page @disabled(! $report)>Print</button>
            </div>
        </div>
    </div>

    <div class="mt-5 grid items-start gap-4 lg:grid-cols-[175px_minmax(0,1fr)] print:block">
        <aside class="dashboard-enter overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:50ms] print:hidden">
            <div class="border-b border-slate-100 px-4 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Accounts</h2>
                <p class="mt-0.5 text-xs text-slate-500">Select to view</p>
                <label class="relative mt-3 block">
                    <span class="sr-only">Search accounts</span>
                    <input id="ledger-account-search" type="search" placeholder="Search accounts..." class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none transition focus:border-blue-400 focus:ring-3 focus:ring-blue-100">
                </label>
            </div>
            <div id="ledger-account-list" class="dashboard-scrollbar-hidden max-h-[31rem] overflow-y-auto p-2">
                @forelse ($accounts as $account)
                    @php($selected = $report && (string) $report['account']['code'] === (string) $account['code'])
                    <button type="button" data-account-code="{{ $account['code'] }}" data-account-search="{{ Str::lower($account['code'].' '.$account['name']) }}"
                        class="ledger-account-button mb-1 w-full rounded-lg px-3 py-2.5 text-left text-xs transition duration-150 hover:bg-blue-50 active:scale-[.98] {{ $selected ? 'bg-blue-600 text-white shadow-sm hover:bg-blue-500' : 'text-slate-700' }}">
                        <span class="flex items-center justify-between gap-2 font-mono"><span>{{ $account['code'] }}</span><span data-account-balance>{{ number_format((float) $account['balance'] / 1000, 0) }}k</span></span>
                        <span class="mt-1 block truncate font-medium">{{ $account['name'] }}</span>
                    </button>
                @empty
                    <p class="px-2 py-8 text-center text-xs text-slate-500">No accounts available.</p>
                @endforelse
                <p id="ledger-account-empty" class="hidden px-2 py-8 text-center text-xs text-slate-500">No accounts match your search.</p>
            </div>
        </aside>

        <section class="min-w-0">
            <div class="grid gap-3 sm:grid-cols-3 print:grid-cols-3">
                <article class="apm-summary-card dashboard-enter [animation-delay:100ms]">
                    <span>Account</span>
                    <strong id="ledger-account-summary" class="truncate">{{ $report ? $report['account']['code'].' - '.$report['account']['name'] : 'No account' }}</strong>
                </article>
                <article class="apm-summary-card dashboard-enter [animation-delay:150ms]">
                    <span>Type</span>
                    <strong id="ledger-type-summary" class="truncate">{{ $report ? $report['account']['type'].' · '.($report['account']['sub_type'] ?: 'Unclassified') : '—' }}</strong>
                </article>
                <article class="apm-summary-card dashboard-enter [animation-delay:200ms]">
                    <span>Balance as of selected end date</span>
                    <strong id="ledger-balance-summary">PHP {{ number_format((float) ($report['ending_balance'] ?? 0), 2) }}</strong>
                </article>
            </div>

            <div class="dashboard-enter mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:250ms] print:border-0 print:shadow-none">
                <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="ledger-table-title" class="text-sm font-semibold text-slate-900">{{ $report ? $report['account']['code'].' — '.$report['account']['name'] : 'General Ledger' }}</h2>
                        <p id="ledger-row-count" class="mt-0.5 text-xs text-slate-500">{{ $report ? count($report['rows']).' '.Str::plural('transaction', count($report['rows'])) : '0 transactions' }}</p>
                    </div>
                    <label class="relative w-full sm:w-60 print:hidden">
                        <span class="sr-only">Search ledger entries</span>
                        <input id="ledger-entry-search" type="search" value="{{ request('search') }}" placeholder="Search entries..." class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none transition focus:border-blue-400 focus:ring-3 focus:ring-blue-100">
                    </label>
                </header>

                <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-end print:hidden">
                    <label class="text-[11px] font-medium text-slate-600">From
                        <input id="ledger-date-from" type="date" value="{{ request('date_from') }}" class="mt-1 block h-9 rounded-lg border border-slate-200 px-3 text-xs outline-none transition focus:border-blue-400 focus:ring-3 focus:ring-blue-100">
                    </label>
                    <label class="text-[11px] font-medium text-slate-600">To
                        <input id="ledger-date-to" type="date" value="{{ request('date_to') }}" class="mt-1 block h-9 rounded-lg border border-slate-200 px-3 text-xs outline-none transition focus:border-blue-400 focus:ring-3 focus:ring-blue-100">
                    </label>
                    <button id="ledger-clear-filters" type="button" class="apm-outline-button h-9">Clear filters</button>
                    <p id="ledger-error" role="alert" class="hidden text-xs font-medium text-red-600 sm:ml-auto"></p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px] text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Date</th>
                                <th class="px-4 py-3 font-semibold">Journal Entry</th>
                                <th class="px-4 py-3 font-semibold">Description</th>
                                <th class="px-4 py-3 text-right font-semibold">Debit</th>
                                <th class="px-4 py-3 text-right font-semibold">Credit</th>
                                <th class="px-4 py-3 text-right font-semibold">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody id="ledger-table-body" class="text-slate-700">
                            @if ($report)
                                <tr class="ledger-opening-balance border-b border-slate-100 bg-slate-50/60">
                                    <td colspan="5" class="px-4 py-3 font-medium text-slate-500">Beginning balance</td>
                                    <td class="apm-money px-4 py-3 text-right">PHP {{ number_format((float) $report['beginning_balance'], 2) }}</td>
                                </tr>
                                @forelse ($report['rows'] as $row)
                                    <tr class="apm-table-row">
                                        <td class="font-mono text-[11px]">{{ $row['date'] }}</td>
                                        <td><a href="{{ route('journal-entries', ['entry' => $row['journal_number']]) }}" class="font-mono text-blue-600 hover:underline">{{ $row['journal_number'] }}</a></td>
                                        <td><span class="font-medium">{{ $row['line_description'] ?: $row['description'] }}</span>@if($row['reference'])<span class="mt-0.5 block text-[10px] text-slate-400">{{ $row['reference'] }}</span>@endif</td>
                                        <td class="apm-money text-right">{{ $row['debit'] > 0 ? 'PHP '.number_format($row['debit'], 2) : '—' }}</td>
                                        <td class="apm-money text-right">{{ $row['credit'] > 0 ? 'PHP '.number_format($row['credit'], 2) : '—' }}</td>
                                        <td class="apm-money text-right {{ $row['running_balance'] < 0 ? 'text-red-600' : '' }}">PHP {{ number_format($row['running_balance'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-10 text-center text-xs text-slate-500">No posted transactions match the selected account and filters.</td></tr>
                                @endforelse
                            @else
                                <tr><td colspan="6" class="px-4 py-10 text-center text-xs text-slate-500">Add an account in Chart of Accounts to view its ledger.</td></tr>
                            @endif
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50 text-xs font-semibold text-slate-800">
                            <tr>
                                <td id="ledger-footer-count" colspan="3" class="px-4 py-3 font-normal text-slate-500">{{ $report ? count($report['rows']).' transactions' : '0 transactions' }}</td>
                                <td id="ledger-total-debit" class="apm-money px-4 py-3 text-right">PHP {{ number_format((float) ($report['total_debit'] ?? 0), 2) }}</td>
                                <td id="ledger-total-credit" class="apm-money px-4 py-3 text-right">PHP {{ number_format((float) ($report['total_credit'] ?? 0), 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <footer class="flex items-center justify-between gap-3 px-4 py-3 print:hidden">
                    <p id="ledger-record-count" class="text-[11px] text-slate-500">Showing {{ $report ? count($report['rows']) : 0 }} records</p>
                    <div id="ledger-pagination" class="flex gap-1"></div>
                </footer>
            </div>
        </section>
    </div>

    <script id="general-ledger-report" type="application/json">@json($report)</script>
</main>
@endsection
