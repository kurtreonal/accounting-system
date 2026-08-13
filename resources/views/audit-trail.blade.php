@extends('layouts.accounting', ['activePage' => 'audit-trail', 'pageTitle' => 'Audit Trail'])

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div>
        <p class="text-xs text-slate-500">Administration / Audit Trail</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Audit Trail</h1>
        <p class="text-sm text-slate-500">Read-only history of accounting and configuration activity.</p>
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="get" class="flex flex-wrap gap-3 border-b border-slate-200 p-4 dark:border-slate-700">
            <input name="search" value="{{ request('search') }}" class="min-w-64 flex-1 rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-sm" placeholder="Search actor, action, or record…">
            <select name="resource" class="rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-sm">
                <option value="">All resources</option>
                @foreach ($resources as $resource)<option value="{{ $resource }}" @selected(request('resource') === $resource)>{{ str_replace('_', ' ', Str::headline($resource)) }}</option>@endforeach
            </select>
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800"><tr><th class="px-4 py-3">Time</th><th class="px-4 py-3">Actor</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Resource</th><th class="px-4 py-3">Record</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($logs as $log)
                        <tr><td class="whitespace-nowrap px-4 py-3">{{ \Illuminate\Support\Carbon::parse($log['created_at'])->format('Y-m-d H:i:s') }}</td><td class="px-4 py-3"><p class="font-medium">{{ $log['actor_name'] }}</p><p class="text-xs text-slate-500">{{ $log['actor_role'] }}</p></td><td class="px-4 py-3">{{ Str::headline($log['action']) }}</td><td class="px-4 py-3">{{ Str::headline($log['resource']) }}</td><td class="px-4 py-3 font-mono text-xs">{{ $log['resource_id'] }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No audit events match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
