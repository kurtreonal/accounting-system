@extends('layouts.accounting', ['activePage' => 'users-settings', 'pageTitle' => 'Users & Settings'])

@section('content')
<main id="users-settings-page" class="p-4 sm:p-5" data-user-url="{{ route('users-settings.users.store') }}" data-company-url="{{ route('users-settings.company') }}" data-system-url="{{ route('users-settings.system') }}" data-reset-prepare-url="{{ route('users-settings.reset.prepare') }}" data-reset-url="{{ route('users-settings.reset') }}">
    <div class="dashboard-enter">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500"><span>Administration</span><span class="text-slate-300">/</span><span class="font-medium text-slate-700 dark:text-slate-300">Users &amp; Roles</span></nav>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div><h1 class="text-xl font-bold text-slate-900 dark:text-white">Users &amp; Roles</h1><p class="mt-1 text-sm text-slate-500">Manage static demo accounts, roles, permissions, and accounting settings</p></div>
            <a href="{{ route('users-settings.export') }}" class="apm-outline-button inline-flex h-9 items-center gap-1.5"><i class="fa-solid fa-download" aria-hidden="true"></i> Export Settings</a>
        </div>

        <div class="mt-5 flex overflow-x-auto border-b border-slate-200 dark:border-slate-700" role="tablist" aria-label="Users and settings sections">
            @foreach (['users' => 'Users', 'roles' => 'Roles', 'permissions' => 'Permissions', 'activity' => 'Activity', 'company' => 'Company', 'system' => 'System'] as $key => $label)
                <button type="button" class="apm-tab {{ $key === 'users' ? 'border-blue-600 text-blue-600' : '' }}" data-settings-tab="{{ $key }}" role="tab" aria-selected="{{ $key === 'users' ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>

        <section aria-label="User summary" class="mx-auto mt-5 grid max-w-3xl grid-cols-2 gap-3 xl:max-w-none xl:grid-cols-4">
            @foreach ([['Total Users', $metrics['total'], 'fa-users'], ['Active', $metrics['active'], 'fa-user-check'], ['Assigned Roles', $metrics['roles'], 'fa-user-shield'], ['Inactive', $metrics['inactive'], 'fa-user-lock']] as [$label, $value, $icon])
                <article class="apm-summary-card flex items-center gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span><div><strong class="mt-0">{{ $value }}</strong><p class="mt-1">{{ $label }}</p></div></article>
            @endforeach
        </section>
    </div>

    <section data-settings-panel="users" class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div><h2 class="text-sm font-semibold text-slate-900 dark:text-white">All Users</h2><p class="mt-0.5 text-xs text-slate-500"><span data-user-count>{{ count($users) }}</span> demo accounts</p></div>
            <button id="new-user" type="button" class="apm-primary-button"><i class="fa-solid fa-plus" aria-hidden="true"></i> New User</button>
        </header>
        <div class="grid gap-2 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-800">
            <label class="relative lg:col-span-1"><span class="sr-only">Search users</span><i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-3 left-3 text-xs text-slate-400"></i><input id="user-search" type="search" class="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-8 text-xs dark:border-slate-700 dark:bg-slate-950" placeholder="Search name, email, ID…"></label>
            <select id="user-role-filter" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs dark:border-slate-700 dark:bg-slate-950"><option value="">All Roles</option>@foreach (array_keys($roles) as $role)<option>{{ $role }}</option>@endforeach</select>
            <select id="user-department-filter" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs dark:border-slate-700 dark:bg-slate-950"><option value="">All Departments</option>@foreach (collect($users)->pluck('department')->unique()->sort() as $department)<option>{{ $department }}</option>@endforeach</select>
            <select id="user-status-filter" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs dark:border-slate-700 dark:bg-slate-950"><option value="">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-xs">
            <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500 dark:bg-slate-800"><tr><th class="px-4 py-3"><button data-user-sort="name">Employee <i class="fa-solid fa-sort"></i></button></th><th class="px-4 py-3"><button data-user-sort="department">Department / Position <i class="fa-solid fa-sort"></i></button></th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Employment</th><th class="px-4 py-3">Account Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
            <tbody id="user-rows" class="text-slate-700 dark:text-slate-300"></tbody>
        </table></div>
        <footer class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800"><span id="user-page-summary"></span><div class="flex gap-1"><button id="user-prev" type="button" class="apm-page-button">‹ Prev</button><span id="user-page-number" class="grid min-w-8 place-items-center rounded bg-blue-600 px-2 text-[10px] text-white">1</span><button id="user-next" type="button" class="apm-page-button">Next ›</button></div></footer>
    </section>

    <section data-settings-panel="roles" hidden class="dashboard-enter mt-5 grid gap-4 lg:grid-cols-2">
        @foreach ($roles as $role => $permissions)
            @php($assigned = collect($users)->where('role', $role)->count())
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between"><div><h2 class="font-semibold text-slate-900 dark:text-white">{{ $role }}</h2><p class="mt-1 text-xs text-slate-500">{{ $assigned }} assigned {{ Str::plural('user', $assigned) }}</p></div><span class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-semibold text-blue-600 dark:bg-blue-950/40">{{ in_array('*', $permissions, true) ? 'Full access' : count($permissions).' permissions' }}</span></div>
                <p class="mt-4 text-xs leading-5 text-slate-600 dark:text-slate-300">@switch($role)@case('Administrator') Full static-demo administration, accounting configuration, approvals, reports, users, and settings. @break @case('Accountant') Accounting masters, transactions, posting, reversals, bank, reports, and audit. No user or tax administration. @break @case('Encoder / Staff') Creates and edits transaction drafts, then submits for review. No protected posting or configuration. @break @default Read-only dashboards, posted records, reports, exports, and audit trail. @endswitch</p>
            </article>
        @endforeach
    </section>

    <section data-settings-panel="permissions" hidden class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <header class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold text-slate-900 dark:text-white">Static Permission Matrix</h2><p class="mt-1 text-xs text-slate-500">Actual permissions used by Laravel middleware and shared JavaScript role checks</p></header>
        @php($permissionKeys = collect($roles)->flatten()->reject(fn ($permission) => $permission === '*')->unique()->sort()->values())
        <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500 dark:bg-slate-800"><tr><th class="px-4 py-3 text-left">Permission</th>@foreach (array_keys($roles) as $role)<th class="px-4 py-3 text-center">{{ $role }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($permissionKeys as $permission)<tr><td class="px-4 py-3"><p class="font-medium text-slate-800 dark:text-slate-200">{{ Str::headline($permission) }}</p><code class="text-[10px] text-slate-400">{{ $permission }}</code></td>@foreach ($roles as $permissions)<td class="px-4 py-3 text-center">@if (in_array('*', $permissions, true) || in_array($permission, $permissions, true))<i class="fa-solid fa-circle-check text-emerald-600" aria-label="Allowed"></i>@else<span class="text-slate-300">—</span>@endif</td>@endforeach</tr>@endforeach
        </tbody></table></div>
    </section>

    <section data-settings-panel="activity" hidden class="dashboard-enter mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <header class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold text-slate-900 dark:text-white">User &amp; Settings Activity</h2><p class="mt-1 text-xs text-slate-500">Recent administration actions; full history remains in Audit Trail</p></header>
        <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500 dark:bg-slate-800"><tr><th class="px-4 py-3">Timestamp</th><th class="px-4 py-3">Administrator</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Record</th></tr></thead><tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-300">@forelse ($activity as $event)<tr><td class="px-4 py-3 font-mono text-[11px]">{{ $event['created_at'] }}</td><td class="px-4 py-3">{{ $event['actor_name'] }}</td><td class="px-4 py-3">{{ Str::headline($event['action']) }}</td><td class="px-4 py-3 font-mono text-blue-600">{{ $event['resource_id'] }}</td></tr>@empty<tr><td colspan="4" class="px-4 py-12 text-center text-slate-500">No user or settings activity yet.</td></tr>@endforelse</tbody></table></div>
    </section>

    <section data-settings-panel="company" hidden class="dashboard-enter mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div><h2 class="text-sm font-semibold text-slate-900 dark:text-white">Company Information</h2><p class="mt-1 text-xs text-slate-500">Fictional demo identity used in exports and headers</p></div>
        <form id="company-settings-form" class="mt-5 grid gap-4 sm:grid-cols-2" novalidate>@csrf
            <label class="text-xs text-slate-600 dark:text-slate-300">Display name *<input name="name" value="{{ $settings['company']['name'] }}" required maxlength="100" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="text-xs text-slate-600 dark:text-slate-300">Legal name *<input name="legal_name" value="{{ $settings['company']['legal_name'] }}" required maxlength="150" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="text-xs text-slate-600 dark:text-slate-300">Demo Tax ID<input name="tax_id" value="{{ $settings['company']['tax_id'] }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="text-xs text-slate-600 dark:text-slate-300">Email<input name="email" type="email" value="{{ $settings['company']['email'] }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="text-xs text-slate-600 dark:text-slate-300">Phone<input name="phone" value="{{ $settings['company']['phone'] }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="text-xs text-slate-600 dark:text-slate-300">Address<input name="address" value="{{ $settings['company']['address'] }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"></label>
            <p data-company-message class="hidden rounded-lg p-3 text-xs sm:col-span-2"></p><div class="sm:col-span-2 flex justify-end"><button type="submit" class="apm-primary-button">Save Company</button></div>
        </form>
    </section>

    <section data-settings-panel="system" hidden class="dashboard-enter mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div><h2 class="text-sm font-semibold text-slate-900 dark:text-white">System Preferences</h2><p class="mt-1 text-xs text-slate-500">Static fiscal, numbering, currency, and date configuration</p></div>
        <form id="system-settings-form" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" novalidate>@csrf
            <label class="text-xs text-slate-600 dark:text-slate-300">Fiscal year starts<select name="fiscal_year_start" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950">@foreach (range(1, 12) as $month)<option value="{{ $month }}" @selected((int) $settings['system']['fiscal_year_start'] === $month)>{{ DateTime::createFromFormat('!m', $month)->format('F') }}</option>@endforeach</select></label>
            <label class="text-xs text-slate-600 dark:text-slate-300">Currency<select name="currency" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"><option @selected($settings['system']['currency'] === 'PHP')>PHP</option><option @selected($settings['system']['currency'] === 'USD')>USD</option></select></label>
            <label class="text-xs text-slate-600 dark:text-slate-300">Date format<select name="date_format" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950">@foreach (['Y-m-d', 'm/d/Y', 'd/m/Y'] as $format)<option @selected($settings['system']['date_format'] === $format)>{{ $format }}</option>@endforeach</select></label>
            @foreach (['journal_prefix' => 'Journal prefix', 'invoice_prefix' => 'Invoice prefix', 'bill_prefix' => 'Bill prefix'] as $name => $label)<label class="text-xs text-slate-600 dark:text-slate-300">{{ $label }} *<input name="{{ $name }}" value="{{ $settings['system'][$name] }}" required maxlength="8" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 uppercase dark:border-slate-700 dark:bg-slate-950"></label>@endforeach
            <label class="text-xs text-slate-600 dark:text-slate-300">Timezone<select name="timezone" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"><option @selected($settings['system']['timezone'] === 'Asia/Manila')>Asia/Manila</option><option @selected($settings['system']['timezone'] === 'UTC')>UTC</option></select></label>
            <p data-system-message class="hidden rounded-lg p-3 text-xs lg:col-span-3"></p><div class="lg:col-span-3 flex justify-end"><button type="submit" class="apm-primary-button">Save System Settings</button></div>
        </form>

        <div class="mt-7 border-t border-slate-200 pt-6 dark:border-slate-700">
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/70 dark:bg-red-950/25">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="max-w-2xl"><h3 class="text-sm font-semibold text-red-800 dark:text-red-300">Reset Demo Data</h3><p class="mt-1 text-xs leading-5 text-red-700 dark:text-red-300/80">Permanently delete customers, vendors, transactions, journals, payments, expenses, bank activity, reconciliations, and audit history. Chart of Accounts and required configuration remain; every account balance becomes zero.</p></div>
                    <button id="open-demo-reset" type="button" class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 text-xs font-medium text-red-700 transition hover:bg-red-100 active:scale-95 dark:border-red-800 dark:bg-slate-900 dark:text-red-300 dark:hover:bg-red-950/50"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset Demo Data</button>
                </div>
                <form id="demo-reset-form" hidden class="mt-4 border-t border-red-200 pt-4 dark:border-red-900/60" novalidate>
                    <label class="block max-w-md text-xs font-medium text-red-800 dark:text-red-300">Type <strong class="font-mono">RESET DEMO DATA</strong> to confirm
                        <input name="confirmation" autocomplete="off" spellcheck="false" class="mt-2 h-9 w-full rounded-lg border border-red-300 bg-white px-3 font-mono text-xs text-slate-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 dark:border-red-800 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-red-950" placeholder="RESET DEMO DATA">
                    </label>
                    <p data-reset-error class="mt-2 hidden text-xs text-red-700 dark:text-red-300"></p>
                    <p data-reset-status class="mt-3 hidden rounded-lg bg-white/70 p-3 text-xs text-slate-700 dark:bg-slate-900/70 dark:text-slate-200" role="status" aria-live="polite"></p>
                    <div class="mt-4 flex flex-wrap gap-2"><button type="submit" class="inline-flex h-9 items-center rounded-lg bg-red-600 px-3 text-xs font-medium text-white transition hover:bg-red-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">Start 5-second reset</button><button id="cancel-demo-reset" type="button" class="apm-outline-button">Cancel</button></div>
                </form>
            </div>
        </div>
    </section>

    <script id="users-settings-data" type="application/json">{!! Illuminate\Support\Js::encode(['users' => $users, 'roles' => array_keys($roles), 'currentUserId' => session('demo_user.id')]) !!}</script>
</main>

<div id="user-form-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-hidden="true"><button type="button" class="absolute inset-0 bg-slate-950/55" data-user-close="form" aria-label="Close user form"></button><form id="user-form" class="relative z-10 max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-5 shadow-2xl dark:bg-slate-900" novalidate>@csrf<input name="user_id" type="hidden"><div class="flex items-start justify-between"><div><h2 id="user-form-title" class="font-semibold text-slate-900 dark:text-white">New User</h2><p class="mt-1 text-xs text-slate-500">Static demonstration account. Not production identity management.</p></div><button type="button" class="apm-icon-button" data-user-close="form"><i class="fa-solid fa-xmark"></i></button></div><div class="mt-5 grid gap-3 sm:grid-cols-2">
    <label class="text-xs text-slate-600 dark:text-slate-300">Employee code *<input name="employee_code" required maxlength="20" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"><span data-user-error="employee_code" class="text-[10px] text-red-600"></span></label>
    <label class="text-xs text-slate-600 dark:text-slate-300">Full name *<input name="name" required maxlength="100" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"><span data-user-error="name" class="text-[10px] text-red-600"></span></label>
    <label class="text-xs text-slate-600 dark:text-slate-300">Email *<input name="email" type="email" required maxlength="120" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"><span data-user-error="email" class="text-[10px] text-red-600"></span></label>
    <label class="text-xs text-slate-600 dark:text-slate-300">Role *<select name="role" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950">@foreach (array_keys($roles) as $role)<option>{{ $role }}</option>@endforeach</select><span data-user-error="role" class="text-[10px] text-red-600"></span></label>
    <label class="text-xs text-slate-600 dark:text-slate-300">Department *<input name="department" required maxlength="80" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"><span data-user-error="department" class="text-[10px] text-red-600"></span></label>
    <label class="text-xs text-slate-600 dark:text-slate-300">Position *<input name="position" required maxlength="100" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"><span data-user-error="position" class="text-[10px] text-red-600"></span></label>
    <label class="text-xs text-slate-600 dark:text-slate-300">Employment type *<select name="employment_type" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"><option>Regular</option><option>Probationary</option><option>Contract</option></select></label>
    <div data-new-password-fields class="contents"><label class="text-xs text-slate-600 dark:text-slate-300">Password *<input name="password" type="password" minlength="8" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"><span data-user-error="password" class="text-[10px] text-red-600"></span></label><label class="text-xs text-slate-600 dark:text-slate-300 sm:col-start-2">Confirm password *<input name="password_confirmation" type="password" minlength="8" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"></label></div>
</div><p data-user-message class="mt-3 hidden rounded-lg p-3 text-xs"></p><div class="mt-5 flex justify-end gap-2"><button type="button" class="apm-outline-button" data-user-close="form">Cancel</button><button type="submit" class="apm-primary-button">Save User</button></div></form></div>

<div id="user-password-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-hidden="true"><button type="button" class="absolute inset-0 bg-slate-950/55" data-user-close="password" aria-label="Close password form"></button><form id="user-password-form" class="relative z-10 w-full max-w-md rounded-xl bg-white p-5 shadow-2xl dark:bg-slate-900" novalidate>@csrf<input name="user_id" type="hidden"><div class="flex justify-between"><div><h2 class="font-semibold text-slate-900 dark:text-white">Reset Demo Password</h2><p data-password-user class="mt-1 text-xs text-slate-500"></p></div><button type="button" class="apm-icon-button" data-user-close="password"><i class="fa-solid fa-xmark"></i></button></div><div class="mt-4 space-y-3"><label class="text-xs text-slate-600 dark:text-slate-300">New password *<input name="password" type="password" minlength="8" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"></label><label class="text-xs text-slate-600 dark:text-slate-300">Confirm password *<input name="password_confirmation" type="password" minlength="8" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 dark:border-slate-700 dark:bg-slate-950"></label></div><p data-password-message class="mt-3 hidden rounded-lg p-3 text-xs"></p><div class="mt-5 flex justify-end gap-2"><button type="button" class="apm-outline-button" data-user-close="password">Cancel</button><button type="submit" class="apm-primary-button">Reset Password</button></div></form></div>
@endsection
