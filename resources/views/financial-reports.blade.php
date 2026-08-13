@extends('layouts.accounting', ['pageTitle' => 'Financial Reports', 'activePage' => 'financial-reports'])

@section('content')
<main id="financial-reports-page" class="p-4 sm:p-5" data-report-url="{{ route('financial-reports.data') }}" data-export-url="{{ route('financial-reports.export.csv') }}">
    <div class="dashboard-enter">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500 print:hidden"><span>Reporting</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700">Financial Reports</span></nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between print:hidden">
            <div><h1 class="text-xl font-bold text-slate-900">Financial Reports</h1><p class="mt-1 text-xs text-slate-500">Generate reports from posted journals and current demo source records.</p></div>
            <div class="flex gap-2"><a id="report-export" href="#" class="apm-outline-button"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV</a><button type="button" data-print-page class="apm-outline-button"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button></div>
        </div>

        <section class="mt-5 grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
            <aside class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm print:hidden" aria-label="Available reports">
                <div class="px-2 pb-2"><h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reports</h2><p class="mt-1 text-[10px] text-slate-400">Select a report to recalculate.</p></div>
                <div id="report-navigation" class="space-y-1">
                    @php $icons = ['trial-balance' => 'fa-scale-balanced', 'income-statement' => 'fa-chart-line', 'balance-sheet' => 'fa-table-columns', 'cash-flow' => 'fa-money-bill-transfer', 'sales-report' => 'fa-chart-column', 'expense-report' => 'fa-receipt', 'tax-summary' => 'fa-percent']; @endphp
                    @foreach($summaries as $summary)
                        <button type="button" data-report-key="{{ $summary['key'] }}" aria-pressed="{{ $summary['key'] === $initialReport['key'] ? 'true' : 'false' }}" class="financial-report-option flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left transition">
                            <span class="financial-report-option-icon mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg"><i class="fa-solid {{ $icons[$summary['key']] }}" aria-hidden="true"></i></span>
                            <span class="min-w-0"><strong class="financial-report-option-title block text-xs">{{ $summary['title'] }}</strong><small data-report-summary="{{ $summary['key'] }}" class="financial-report-option-summary mt-0.5 block truncate text-[10px]">{{ $summary['summary'] }}</small></span>
                        </button>
                    @endforeach
                </div>
            </aside>

            <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-200 p-4 print:border-b-2 print:border-slate-900">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div><h2 id="report-title" class="text-base font-bold text-slate-900">{{ $initialReport['title'] }}</h2><p id="report-period" class="mt-1 text-xs text-slate-500">{{ $initialReport['period'] }}</p></div>
                        <div id="report-health" class="hidden rounded-full px-2.5 py-1 text-[10px] font-semibold"></div>
                    </div>

                    <form id="report-filters" class="mt-4 print:hidden" novalidate>
                        <div class="flex flex-wrap items-end gap-2">
                            <label class="text-[10px] font-medium text-slate-500">From<input name="date_from" type="date" value="{{ $initialReport['filters']['date_from'] }}" class="mt-1 block h-9 rounded-lg border border-slate-200 px-2 text-xs"></label>
                            <label class="text-[10px] font-medium text-slate-500">To / As of<input name="date_to" type="date" value="{{ $initialReport['filters']['date_to'] }}" class="mt-1 block h-9 rounded-lg border border-slate-200 px-2 text-xs"></label>
                            <label data-report-filter="party" class="hidden text-[10px] font-medium text-slate-500">Customer<select name="party" class="mt-1 block h-9 max-w-48 rounded-lg border border-slate-200 px-2 text-xs"><option value="">All customers</option>@foreach($customers as $customer)<option value="{{ $customer['code'] }}">{{ $customer['name'] }}</option>@endforeach</select></label>
                            <label data-report-filter="category" class="hidden text-[10px] font-medium text-slate-500">Category<select name="category" class="mt-1 block h-9 max-w-48 rounded-lg border border-slate-200 px-2 text-xs"><option value="">All categories</option>@foreach($expenseCategories as $category)<option value="{{ $category['code'] }}">{{ $category['name'] }}</option>@endforeach</select></label>
                            <label data-report-filter="status" class="hidden text-[10px] font-medium text-slate-500">Status<select name="status" class="mt-1 block h-9 rounded-lg border border-slate-200 px-2 text-xs"><option value="">All statuses</option><option>Draft</option><option>For Review</option><option>Approved</option><option>Posted</option><option>Paid</option><option>Partially Paid</option><option>Overdue</option></select></label>
                            <label data-report-filter="tax_code" class="hidden text-[10px] font-medium text-slate-500">Tax code<select name="tax_code" class="mt-1 block h-9 rounded-lg border border-slate-200 px-2 text-xs"><option value="">Input &amp; output</option><option value="INPUT">Input VAT</option><option value="OUTPUT">Output VAT</option></select></label>
                            <button type="submit" class="apm-primary-button"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Generate</button>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5"><span class="mr-1 text-[10px] text-slate-400">Quick period:</span><button type="button" data-report-period="month" class="rounded-md border border-slate-200 px-2 py-1 text-[10px] text-slate-600 hover:bg-slate-50">This month</button><button type="button" data-report-period="quarter" class="rounded-md border border-slate-200 px-2 py-1 text-[10px] text-slate-600 hover:bg-slate-50">This quarter</button><button type="button" data-report-period="year" class="rounded-md border border-slate-200 px-2 py-1 text-[10px] text-slate-600 hover:bg-slate-50">This year</button></div>
                        <p id="report-filter-error" class="mt-2 hidden rounded-lg bg-rose-50 p-2 text-xs text-rose-700"></p>
                    </form>
                </header>

                <div id="report-loading" class="hidden p-12 text-center"><i class="fa-solid fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-3 text-xs text-slate-500">Calculating report from posted transactions…</p></div>
                <div id="report-content">
                    <div id="report-kpis" class="hidden grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 xl:grid-cols-4"></div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-xs"><thead id="report-head" class="bg-slate-50 text-[10px] uppercase text-slate-500"></thead><tbody id="report-body"></tbody><tfoot id="report-foot" class="border-t-2 border-slate-700 bg-slate-50 font-bold text-slate-900"></tfoot></table></div>
                    <div id="report-empty" class="hidden p-14 text-center"><i class="fa-solid fa-chart-pie text-4xl text-slate-300"></i><p class="mt-3 text-sm font-semibold text-slate-600">No report data</p><p class="mt-1 text-xs text-slate-400">There are no matching records for the selected period and filters.</p></div>
                </div>
                <footer class="flex flex-col gap-1 border-t border-slate-100 px-4 py-3 text-[10px] text-slate-400 sm:flex-row sm:justify-between"><span id="report-record-count"></span><span id="report-generated">Generated {{ now()->format('Y-m-d H:i') }}</span></footer>
            </section>
        </section>
    </div>
</main>

<script id="financial-report-data" type="application/json">{!! json_encode(['report' => $initialReport, 'reportNames' => $reportNames], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection
