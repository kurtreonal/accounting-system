@extends('layouts.accounting', ['activePage' => 'audit-trail', 'pageTitle' => 'Audit Trail'])

@section('content')
<main id="audit-trail-page" class="p-4 sm:p-5" data-page-size="8">
    <div class="dashboard-enter print:hidden">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500"><span>Administration</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700 dark:text-slate-300">Audit Trail</span></nav>
        <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div><h1 class="text-xl font-bold text-slate-900 dark:text-white">Audit Trail</h1><p class="mt-1 text-sm text-slate-500">Complete read-only log of user actions and system events</p></div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-900" role="tablist" aria-label="Audit display">
                    <button type="button" class="rounded-md bg-blue-600 px-3 py-2 text-xs font-semibold text-white" data-audit-view="table" role="tab" aria-selected="true">Table</button>
                    <button type="button" class="rounded-md px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300" data-audit-view="timeline" role="tab" aria-selected="false">Timeline</button>
                </div>
                <a href="{{ route('audit-trail.export.csv', request()->only(['search', 'action', 'role', 'resource'])) }}" class="apm-outline-button inline-flex h-9 items-center gap-1.5"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV</a>
                <button type="button" class="apm-outline-button inline-flex h-9 items-center gap-1.5" data-print-page><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
            </div>
        </div>
    </div>

    <section aria-label="Audit summary" class="mx-auto mt-5 grid max-w-3xl grid-cols-1 gap-3 sm:grid-cols-2 xl:max-w-none xl:grid-cols-4 print:hidden">
        @foreach ([
            ['Total Events', $metrics['total']],
            ["Today's Activity", $metrics['today']],
            ['Active Users', $metrics['users']],
            ['Modules Accessed', $metrics['modules']],
        ] as $index => [$label, $value])
            <article class="apm-summary-card dashboard-enter [animation-delay:{{ ($index + 1) * 50 }}ms]"><p>{{ $label }}</p><strong class="font-mono text-slate-900 dark:text-white">{{ $value }}</strong><span>{{ $index === 0 ? 'Recorded audit events' : 'From current audit history' }}</span></article>
        @endforeach
    </section>

    <section data-audit-panel="table" class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm [animation-delay:250ms] dark:border-slate-700 dark:bg-slate-900">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div><h2 class="text-sm font-semibold text-slate-900 dark:text-white">Event Log</h2><p class="mt-0.5 text-xs text-slate-500"><span data-audit-count>{{ count($logs) }}</span> events recorded</p></div>
            @if (request()->hasAny(['search', 'action', 'role', 'resource']))<a href="{{ route('audit-trail') }}" class="text-xs font-medium text-blue-600 hover:underline print:hidden">Clear filters</a>@endif
        </header>
        <form method="get" data-audit-filters class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(230px,1fr)_170px_180px_190px] print:hidden dark:border-slate-800">
            <label class="relative"><span class="sr-only">Search audit events</span><i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-3 left-3 text-xs text-slate-400" aria-hidden="true"></i><input name="search" value="{{ request('search') }}" class="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-8 text-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-950" placeholder="Search user, module, or record…"></label>
            <select name="action" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs dark:border-slate-700 dark:bg-slate-950"><option value="">All Actions</option>@foreach ($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ Str::headline($action) }}</option>@endforeach</select>
            <select name="role" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs dark:border-slate-700 dark:bg-slate-950"><option value="">All Roles</option>@foreach ($roles as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>@endforeach</select>
            <select name="resource" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs dark:border-slate-700 dark:bg-slate-950"><option value="">All Modules</option>@foreach ($resources as $resource)<option value="{{ $resource }}" @selected(request('resource') === $resource)>{{ Str::headline($resource) }}</option>@endforeach</select>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1180px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase dark:bg-slate-800"><tr><th class="px-4 py-3">Timestamp</th><th class="px-4 py-3">User</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Module</th><th class="px-4 py-3">Record</th><th class="px-4 py-3" title="Record value or status before this action">Before</th><th class="px-4 py-3" title="Record value or status after this action">After</th></tr></thead>
                <tbody data-audit-rows class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($logs as $log)
                        @php
                            $badge = match (true) {
                                str_contains($log['action'], 'post'), str_contains($log['action'], 'approve') => 'bg-emerald-100 text-emerald-700',
                                str_contains($log['action'], 'create') => 'bg-blue-100 text-blue-700',
                                str_contains($log['action'], 'review'), str_contains($log['action'], 'submit') => 'bg-indigo-100 text-indigo-700',
                                str_contains($log['action'], 'delete'), str_contains($log['action'], 'reverse') => 'bg-red-100 text-red-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                            $before = is_array($log['before']) ? json_encode($log['before'], JSON_UNESCAPED_SLASHES) : ($log['before'] ?? '—');
                            $after = is_array($log['after']) ? json_encode($log['after'], JSON_UNESCAPED_SLASHES) : ($log['after'] ?? '—');
                        @endphp
                        <tr data-audit-row class="apm-table-row"><td class="apm-code whitespace-nowrap">{{ $log['created_at_display'] }}</td><td class="font-medium text-slate-800 dark:text-slate-100">{{ $log['actor_name'] }}</td><td>{{ $log['actor_role'] }}</td><td><span class="inline-flex rounded-md px-2 py-1 text-[10px] font-medium {{ $badge }}">{{ $log['action_label'] }}</span></td><td>{{ $log['resource_label'] }}</td><td><button type="button" data-record-detail data-record-resource="{{ $log['resource'] }}" data-record-id="{{ $log['resource_id'] }}" class="apm-code text-blue-600 hover:underline">{{ $log['resource_id'] }}</button></td><td class="max-w-48 truncate" title="{{ $before }}">{{ $before }}</td><td class="max-w-48 truncate text-emerald-600" title="{{ $after }}">{{ $after }}</td></tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-14 text-center"><i class="fa-solid fa-clock-rotate-left text-2xl text-slate-300" aria-hidden="true"></i><p class="mt-2 text-sm font-medium text-slate-600">No audit events match filters</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between print:hidden dark:border-slate-800"><span data-audit-summary>Showing 0 records</span><div class="flex gap-1"><button type="button" class="apm-page-button" data-audit-prev>‹ Prev</button><span data-audit-page class="grid min-w-8 place-items-center rounded bg-blue-600 px-2 text-[10px] text-white">1</span><button type="button" class="apm-page-button" data-audit-next>Next ›</button></div></footer>
    </section>

    <section data-audit-panel="timeline" hidden class="dashboard-enter mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:hidden dark:border-slate-700 dark:bg-slate-900">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Activity Timeline</h2><p class="mt-1 text-xs text-slate-500">Same filtered events in chronological sequence</p>
        <ol class="relative mt-5 ml-2 border-l border-slate-200 dark:border-slate-700">
            @forelse ($logs as $log)<li class="relative mb-6 ml-6"><span class="absolute top-1 -left-[29px] size-3 rounded-full border-2 border-white bg-blue-600 dark:border-slate-900"></span><time class="apm-code text-[10px] text-slate-500">{{ $log['created_at_display'] }}</time><p class="mt-1 text-sm text-slate-700 dark:text-slate-200"><strong>{{ $log['actor_name'] }}</strong> · {{ $log['action_label'] }} · <span class="text-blue-600">{{ $log['resource_label'] }} {{ $log['resource_id'] }}</span></p><p class="mt-0.5 text-xs text-slate-500">{{ $log['actor_role'] }}</p></li>@empty<li class="ml-6 text-sm text-slate-500">No events.</li>@endforelse
        </ol>
    </section>
</main>
@endsection
