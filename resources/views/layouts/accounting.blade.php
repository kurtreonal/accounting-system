<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} | APM Accounting</title>
    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('apm-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = savedTheme ?? (prefersDark ? 'dark' : 'light');

                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;

                if (window.matchMedia('(min-width: 64rem)').matches && localStorage.getItem('apm-sidebar-collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (_) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $currentUser = session('demo_user', ['name' => 'Demo User', 'role' => 'Viewer / Auditor']);
    $demoPermissions = app(\App\Services\DemoAccessService::class)->permissionsForRole($currentUser['role'] ?? null);
    $demoCan = fn (string $permission): bool => in_array('*', $demoPermissions, true) || in_array($permission, $demoPermissions, true);
    $userInitials = collect(preg_split('/\s+/', trim($currentUser['name'] ?? 'Demo User')))
        ->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->join('');
    $demoRoleLabels = [
        'Administrator' => 'Administrator',
        'Accountant' => 'Accountant',
        'Encoder / Staff' => 'Encoder',
        'Viewer / Auditor' => 'Auditor',
    ];
    $demoUsers = collect(app(\App\Services\DemoData\UserDataService::class)->all())
        ->filter(fn (array $user): bool => ($user['active'] ?? false) === true && isset($demoRoleLabels[$user['role'] ?? '']))
        ->sortBy(fn (array $user): int => array_search($user['role'], array_keys($demoRoleLabels), true))
        ->values();
@endphp
<body class="min-h-screen bg-[#f2f6fa] font-sans text-slate-800 antialiased">
    <script id="demo-access" type="application/json">{!! Illuminate\Support\Js::encode([
        'role' => $currentUser['role'] ?? 'Viewer / Auditor',
        'permissions' => $demoPermissions,
    ]) !!}</script>
    <div id="app-shell" class="min-h-screen lg:pl-60 print:pl-0">
        <button id="sidebar-overlay" type="button" class="fixed inset-0 z-25 hidden cursor-default bg-slate-950/55 lg:hidden print:hidden" aria-label="Close navigation"></button>
        <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-30 hidden h-dvh w-60 flex-col overflow-hidden bg-[#0f172a] text-slate-300 lg:flex print:hidden" aria-label="Primary navigation">
            <div class="flex h-14 shrink-0 items-center border-b border-white/10 px-3.5">
                <a href="{{ route('dashboard') }}" class="apm-sidebar-label min-w-0 text-sm font-semibold leading-tight text-white transition hover:text-blue-300" aria-label="Nexii-Tech Solutions Inc. dashboard" title="Nexii-Tech Solutions Inc.">
                    Nexii-Tech Solutions Inc.
                </a>
            </div>

            <nav class="dashboard-scrollbar-hidden min-h-0 flex-1 overflow-y-auto px-2 py-3 text-[12.5px]">
                <p class="apm-nav-heading">Overview</p>
                <a href="{{ route('dashboard') }}" title="Dashboard" @if ($activePage === 'dashboard') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'dashboard' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-gauge-high" aria-hidden="true"></i><span class="apm-nav-label">Dashboard</span></span>
                </a>

                <p class="apm-nav-heading mt-4">Accounting</p>
                <a href="{{ route('chart-of-accounts') }}" title="Chart of Accounts" @if ($activePage === 'chart-of-accounts') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'chart-of-accounts' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-list-check" aria-hidden="true"></i><span class="apm-nav-label">Chart of Accounts</span></span>
                </a>
                <a href="{{ route('journal-entries') }}" title="Journal Entries" @if ($activePage === 'journal-entries') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'journal-entries' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-book" aria-hidden="true"></i><span class="apm-nav-label">Journal Entries</span></span>
                </a>
                <a href="{{ route('general-ledger') }}" title="General Ledger" @if ($activePage === 'general-ledger') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'general-ledger' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-book-open" aria-hidden="true"></i><span class="apm-nav-label">General Ledger</span></span>
                </a>

                <p class="apm-nav-heading mt-4">Sales &amp; Receivables</p>
                <a href="{{ route('sales-revenue') }}" title="Sales and Revenue" @if ($activePage === 'sales-revenue') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'sales-revenue' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-chart-line" aria-hidden="true"></i><span class="apm-nav-label">Sales and Revenue</span></span></a>
                <a href="{{ route('accounts-receivable') }}" title="Accounts Receivable" @if ($activePage === 'accounts-receivable') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'accounts-receivable' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-file-invoice-dollar" aria-hidden="true"></i><span class="apm-nav-label">Accounts Receivable</span></span></a>

                <p class="apm-nav-heading mt-4">Purchases &amp; Payables</p>
                <a href="{{ route('accounts-payable') }}" title="Accounts Payable" @if ($activePage === 'accounts-payable') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'accounts-payable' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-money-check-dollar" aria-hidden="true"></i><span class="apm-nav-label">Accounts Payable</span></span></a>

                <p class="apm-nav-heading mt-4">Banking</p>
                <a href="{{ route('cash-bank') }}" title="Cash &amp; Bank" @if ($activePage === 'cash-bank') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'cash-bank' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-building-columns" aria-hidden="true"></i><span class="apm-nav-label">Cash &amp; Bank</span></span></a>
                <a href="{{ route('expenses') }}" title="Expenses" @if ($activePage === 'expenses') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'expenses' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-wallet" aria-hidden="true"></i><span class="apm-nav-label">Expenses</span></span></a>

                <p class="apm-nav-heading mt-4">Reporting</p>
                <a href="{{ route('financial-reports') }}" title="Financial Reports" @if ($activePage === 'financial-reports') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'financial-reports' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-chart-pie" aria-hidden="true"></i><span class="apm-nav-label">Financial Reports</span></span></a>

                <p class="apm-nav-heading mt-4">Administration</p>
                @if ($demoCan('tax.manage'))
                <a href="{{ route('tax-settings') }}" title="Tax Settings" @if ($activePage === 'tax-settings') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'tax-settings' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-percent" aria-hidden="true"></i><span class="apm-nav-label">Tax Settings</span></span></a>
                @endif
                @if ($demoCan('audit.view'))
                <a href="{{ route('audit-trail') }}" title="Audit Trail" @if ($activePage === 'audit-trail') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'audit-trail' ? 'bg-blue-600 text-white' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span class="apm-nav-label">Audit Trail</span></span></a>
                @endif
                @if ($demoCan('users.manage'))
                <a href="{{ route('users-settings') }}" title="Users &amp; Settings" @if ($activePage === 'users-settings') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'users-settings' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-users-gear" aria-hidden="true"></i><span class="apm-nav-label">Users &amp; Settings</span></span></a>
                @endif
            </nav>

            <div class="flex h-16 shrink-0 items-center border-t border-white/10 px-3">
                <button type="button" class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg p-1 text-left transition-colors duration-150 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-blue-400/70" data-profile-toggle aria-haspopup="menu" aria-expanded="false" title="Open profile menu">
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-red-600 text-[10px] font-bold text-white">{{ $userInitials }}</span>
                    <div class="apm-sidebar-label min-w-0"><p class="truncate text-[11px] font-medium text-white">{{ $currentUser['name'] }}</p><span class="inline-block rounded-full bg-red-100 px-1.5 py-0.5 text-[8px] font-bold text-red-600">{{ $currentUser['role'] }}</span></div>
                    <i class="apm-sidebar-label fa-solid fa-chevron-up ml-auto text-[9px] text-slate-500" aria-hidden="true"></i>
                </button>
            </div>
        </aside>

        <header id="app-header" class="sticky top-0 z-20 flex h-14 items-center border-b border-slate-200 bg-white/95 px-3 backdrop-blur sm:px-5 print:hidden">
            <button id="sidebar-toggle" type="button" aria-label="Collapse navigation" aria-controls="app-sidebar" aria-expanded="true" class="apm-icon-button mr-2">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <p class="shrink-0 text-sm font-semibold text-slate-700">{{ $pageTitle }}</p>
            <label class="relative ml-3 hidden w-80 md:block">
                <span class="sr-only">Search anything</span>
                <svg class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                <input type="search" placeholder="Search anything..." class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pr-3 pl-9 text-xs outline-none transition duration-150 placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:ring-3 focus:ring-blue-100">
            </label>
            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <button id="demo-user-toggle" type="button" aria-label="Switch demo user" aria-haspopup="menu" aria-expanded="false" aria-controls="demo-user-menu" title="Switch demo user" class="apm-icon-button">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                </button>
                <button id="theme-toggle" type="button" aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode" class="apm-icon-button">
                    <svg class="theme-icon-moon size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9.75 9.75 0 0 1 8.25 2.25a9.75 9.75 0 1 0 13.5 13.5Z"/></svg>
                    <svg class="theme-icon-sun size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="3.75"/><path stroke-linecap="round" d="M12 2.25v2.1M12 19.65v2.1M21.75 12h-2.1M4.35 12h-2.1M18.9 5.1l-1.48 1.48M6.58 17.42 5.1 18.9M18.9 18.9l-1.48-1.48M6.58 6.58 5.1 5.1"/></svg>
                </button>
                <button type="button" class="grid size-8 cursor-pointer place-items-center rounded-full bg-red-600 text-[10px] font-bold text-white shadow-sm transition hover:bg-red-500 focus:outline-none focus:ring-3 focus:ring-red-200" data-profile-toggle aria-label="Open profile menu for {{ $currentUser['name'] }}" aria-haspopup="menu" aria-expanded="false" title="Open profile menu">{{ $userInitials }}</button>
            </div>
        </header>

        <div id="demo-user-menu" class="fixed z-50 hidden w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl print:hidden dark:border-slate-700 dark:bg-slate-900" role="menu" aria-hidden="true" aria-labelledby="demo-user-toggle">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Demo user access</p>
                <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400">Switch roles to preview each account's navigation and authority.</p>
            </div>
            <div class="p-2">
                @foreach ($demoUsers as $demoUser)
                    @php
                        $isCurrentDemoUser = (int) ($currentUser['id'] ?? 0) === (int) $demoUser['id'];
                        $demoInitials = collect(preg_split('/\s+/', trim($demoUser['name'] ?? 'Demo User')))
                            ->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->join('');
                    @endphp
                    <form method="POST" action="{{ route('demo-user.switch') }}" data-demo-user-form>
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $demoUser['id'] }}">
                        <button type="submit" role="menuitemradio" aria-checked="{{ $isCurrentDemoUser ? 'true' : 'false' }}" @disabled($isCurrentDemoUser) class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition hover:bg-blue-50 focus:bg-blue-50 focus:outline-none disabled:cursor-default disabled:bg-slate-100 dark:hover:bg-slate-800 dark:focus:bg-slate-800 dark:disabled:bg-slate-800/70">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full {{ $isCurrentDemoUser ? 'bg-blue-600' : 'bg-slate-600' }} text-[10px] font-bold text-white">{{ $demoInitials }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $demoRoleLabels[$demoUser['role']] }}</span>
                                <span class="block truncate text-[10px] text-slate-500 dark:text-slate-400">{{ $demoUser['name'] }}</span>
                            </span>
                            @if ($isCurrentDemoUser)
                                <i class="fa-solid fa-check text-xs text-blue-600" aria-label="Current user"></i>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
            <p class="border-t border-amber-100 bg-amber-50 px-4 py-2.5 text-[10px] leading-4 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">Demo access control only — not production authentication.</p>
        </div>

        <div id="profile-menu" class="fixed z-50 hidden w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl print:hidden" role="menu" aria-hidden="true">
            <div class="border-b border-slate-100 px-4 py-3">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-red-600 text-[11px] font-bold text-white">{{ $userInitials }}</span>
                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $currentUser['name'] }}</p><p class="truncate text-[11px] text-slate-500">{{ $currentUser['email'] ?? 'Demo account' }}</p></div>
                </div>
                <span class="mt-2 inline-flex rounded-full bg-red-50 px-2 py-1 text-[9px] font-semibold text-red-600">{{ $currentUser['role'] }}</span>
            </div>
            <div class="p-2">
                <button type="button" class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-left text-xs font-medium text-red-600 transition hover:bg-red-50 focus:bg-red-50 focus:outline-none" data-logout-open role="menuitem">
                    <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center" aria-hidden="true"></i>
                    <span>Log out</span>
                </button>
            </div>
        </div>

        <div id="logout-confirmation-modal" class="fixed inset-0 z-[60] hidden items-center justify-center p-4 print:hidden" role="dialog" aria-modal="true" aria-labelledby="logout-confirmation-title" aria-describedby="logout-confirmation-description" aria-hidden="true">
            <button type="button" class="absolute inset-0 cursor-default bg-slate-950/60 backdrop-blur-[1px]" data-logout-cancel aria-label="Cancel logout"></button>
            <section class="relative z-10 w-full max-w-sm rounded-xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-red-50 text-red-600"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></span>
                    <div><h2 id="logout-confirmation-title" class="text-sm font-semibold text-slate-900">Log out of your account?</h2><p id="logout-confirmation-description" class="mt-1 text-xs leading-5 text-slate-500">You will return to the login page. Any unsaved form changes will be lost.</p></div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="apm-outline-button" data-logout-cancel>Cancel</button>
                    <form action="{{ route('logout') }}" method="POST" data-logout-form>
                        @csrf
                        <button type="submit" class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-red-600 px-3.5 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-red-500 focus:outline-none focus:ring-3 focus:ring-red-200 disabled:cursor-not-allowed disabled:opacity-60">
                            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Yes, log out</span>
                        </button>
                    </form>
                </div>
            </section>
        </div>

        <header class="apm-print-header" aria-hidden="true">
            <div>
                <p class="apm-print-brand">APM Customs</p>
                <p class="apm-print-system">Accounting System</p>
            </div>
            <div class="apm-print-title-block">
                <h1>{{ $pageTitle }}</h1>
                <p>Generated <span data-print-generated>{{ now()->format('Y-m-d H:i') }}</span></p>
            </div>
        </header>

        @yield('content')

        <div id="record-detail-modal" data-endpoint="{{ url('/record-details') }}" class="fixed inset-0 z-[55] hidden items-center justify-center p-4 print:hidden" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="record-detail-title">
            <button type="button" class="absolute inset-0 cursor-default bg-slate-950/60 backdrop-blur-[1px]" data-record-detail-close aria-label="Close record details"></button>
            <section class="relative z-10 flex max-h-[calc(100dvh-2rem)] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div><div class="flex flex-wrap items-center gap-2"><h2 id="record-detail-title" data-record-detail-title class="text-sm font-semibold text-slate-900 dark:text-white">Record Details</h2><span data-record-detail-status></span></div><p data-record-detail-subtitle class="mt-1 font-mono text-[11px] text-blue-600"></p></div>
                    <button type="button" class="apm-icon-button shrink-0" data-record-detail-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </header>
                <div data-record-detail-content class="dashboard-scrollbar-hidden min-h-0 overflow-y-auto p-5"></div>
                <footer class="flex shrink-0 justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-slate-800 dark:bg-slate-950/40"><button type="button" class="apm-outline-button" data-record-detail-close>Close</button></footer>
            </section>
        </div>
    </div>
</body>
</html>
