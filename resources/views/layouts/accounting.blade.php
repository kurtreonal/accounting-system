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
    $userInitials = collect(preg_split('/\s+/', trim($currentUser['name'] ?? 'Demo User')))
        ->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->join('');
@endphp
<body class="min-h-screen bg-[#f2f6fa] font-sans text-slate-800 antialiased">
    <div id="app-shell" class="min-h-screen lg:pl-60 print:pl-0">
        <button id="sidebar-overlay" type="button" class="fixed inset-0 z-25 hidden cursor-default bg-slate-950/55 lg:hidden print:hidden" aria-label="Close navigation"></button>
        <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-30 hidden h-dvh w-60 flex-col overflow-hidden bg-[#0f172a] text-slate-300 lg:flex print:hidden" aria-label="Primary navigation">
            <div class="flex h-14 shrink-0 items-center gap-2.5 border-b border-white/10 px-3.5">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-blue-600 text-[11px] font-bold text-white shadow-lg shadow-blue-950/30">APM</span>
                <div class="apm-sidebar-label min-w-0 leading-tight">
                    <p class="truncate text-sm font-semibold text-white">APM Customs</p>
                    <p class="text-xs text-slate-500">Accounting System</p>
                </div>
            </div>

            <nav class="dashboard-scrollbar-hidden min-h-0 flex-1 overflow-y-auto px-2 py-3 text-[12.5px]">
                <p class="apm-nav-heading">Overview</p>
                <a href="{{ route('dashboard') }}" title="Dashboard" @if ($activePage === 'dashboard') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'dashboard' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-gauge-high" aria-hidden="true"></i><span class="apm-nav-label">Dashboard</span></span><span class="apm-nav-badge {{ $activePage === 'dashboard' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span>
                </a>

                <p class="apm-nav-heading mt-4">Accounting</p>
                <a href="{{ route('chart-of-accounts') }}" title="Chart of Accounts" @if ($activePage === 'chart-of-accounts') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'chart-of-accounts' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-list-check" aria-hidden="true"></i><span class="apm-nav-label">Chart of Accounts</span></span><span class="apm-nav-badge {{ $activePage === 'chart-of-accounts' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span>
                </a>
                <a href="{{ route('journal-entries') }}" title="Journal Entries" @if ($activePage === 'journal-entries') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'journal-entries' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-book" aria-hidden="true"></i><span class="apm-nav-label">Journal Entries</span></span><span class="apm-nav-badge {{ $activePage === 'journal-entries' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span>
                </a>
                <a href="{{ route('general-ledger') }}" title="General Ledger" @if ($activePage === 'general-ledger') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'general-ledger' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-book-open" aria-hidden="true"></i><span class="apm-nav-label">General Ledger</span></span><span class="apm-nav-badge {{ $activePage === 'general-ledger' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span>
                </a>

                <p class="apm-nav-heading mt-4">Sales &amp; Receivables</p>
                <a href="{{ route('sales-revenue') }}" title="Sales and Revenue" @if ($activePage === 'sales-revenue') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'sales-revenue' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-chart-line" aria-hidden="true"></i><span class="apm-nav-label">Sales and Revenue</span></span><span class="apm-nav-badge {{ $activePage === 'sales-revenue' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span></a>
                <a href="{{ route('accounts-receivable') }}" title="Accounts Receivable" @if ($activePage === 'accounts-receivable') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'accounts-receivable' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-file-invoice-dollar" aria-hidden="true"></i><span class="apm-nav-label">Accounts Receivable</span></span><span class="apm-nav-badge {{ $activePage === 'accounts-receivable' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span></a>

                <p class="apm-nav-heading mt-4">Purchases &amp; Payables</p>
                <a href="{{ route('accounts-payable') }}" title="Accounts Payable" @if ($activePage === 'accounts-payable') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'accounts-payable' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-money-check-dollar" aria-hidden="true"></i><span class="apm-nav-label">Accounts Payable</span></span><span class="apm-nav-badge {{ $activePage === 'accounts-payable' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span></a>

                <p class="apm-nav-heading mt-4">Banking</p>
                <a href="{{ route('cash-bank') }}" title="Cash &amp; Bank" @if ($activePage === 'cash-bank') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'cash-bank' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-building-columns" aria-hidden="true"></i><span class="apm-nav-label">Cash &amp; Bank</span></span><span class="apm-nav-badge {{ $activePage === 'cash-bank' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span></a>
                <a href="{{ route('expenses') }}" title="Expenses" @if ($activePage === 'expenses') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'expenses' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-wallet" aria-hidden="true"></i><span class="apm-nav-label">Expenses</span></span><span class="apm-nav-badge {{ $activePage === 'expenses' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span></a>

                <p class="apm-nav-heading mt-4">Reporting</p>
                <button type="button" title="Financial Reports" class="apm-nav-item"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-chart-pie" aria-hidden="true"></i><span class="apm-nav-label">Financial Reports</span></span><span class="apm-nav-badge apm-full-badge">FULL</span></button>

                <p class="apm-nav-heading mt-4">Administration</p>
                <button type="button" title="Tax Settings" class="apm-nav-item"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-percent" aria-hidden="true"></i><span class="apm-nav-label">Tax Settings</span></span><span class="apm-nav-badge apm-full-badge">FULL</span></button>
                <button type="button" title="Audit Trail" class="apm-nav-item"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span class="apm-nav-label">Audit Trail</span></span><span class="apm-nav-badge apm-full-badge">FULL</span></button>
                <button type="button" title="Users &amp; Settings" class="apm-nav-item"><span class="apm-nav-content"><i class="apm-nav-icon fa-solid fa-users-gear" aria-hidden="true"></i><span class="apm-nav-label">Users &amp; Settings</span></span><span class="apm-nav-badge apm-full-badge">FULL</span></button>
            </nav>

            <div class="flex h-16 shrink-0 items-center border-t border-white/10 px-3">
                <div class="flex w-full items-center gap-2.5 rounded-lg p-1 transition-colors duration-150 hover:bg-white/5">
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-red-600 text-[10px] font-bold text-white">{{ $userInitials }}</span>
                    <div class="apm-sidebar-label min-w-0"><p class="truncate text-[11px] font-medium text-white">{{ $currentUser['name'] }}</p><span class="inline-block rounded-full bg-red-100 px-1.5 py-0.5 text-[8px] font-bold text-red-600">{{ $currentUser['role'] }}</span></div>
                </div>
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
                <button id="theme-toggle" type="button" aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode" class="apm-icon-button">
                    <svg class="theme-icon-moon size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9.75 9.75 0 0 1 8.25 2.25a9.75 9.75 0 1 0 13.5 13.5Z"/></svg>
                    <svg class="theme-icon-sun size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="3.75"/><path stroke-linecap="round" d="M12 2.25v2.1M12 19.65v2.1M21.75 12h-2.1M4.35 12h-2.1M18.9 5.1l-1.48 1.48M6.58 17.42 5.1 18.9M18.9 18.9l-1.48-1.48M6.58 6.58 5.1 5.1"/></svg>
                </button>
                <span class="grid size-8 place-items-center rounded-full bg-red-600 text-[10px] font-bold text-white">{{ $userInitials }}</span>
            </div>
        </header>

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
    </div>
</body>
</html>
