<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <title>{{ $pageTitle }} | APM Accounting</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f2f6fa] font-sans text-slate-800 antialiased">
    <div class="min-h-screen lg:pl-60">
        <aside class="fixed inset-y-0 left-0 z-30 hidden h-dvh w-60 flex-col overflow-hidden bg-[#0f172a] text-slate-300 lg:flex">
            <div class="flex h-14 shrink-0 items-center gap-2.5 border-b border-white/10 px-3.5">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-blue-600 text-[11px] font-bold text-white shadow-lg shadow-blue-950/30">APM</span>
                <div class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-semibold text-white">APM Customs</p>
                    <p class="text-xs text-slate-500">Accounting System</p>
                </div>
            </div>

            <nav class="dashboard-scrollbar-hidden min-h-0 flex-1 overflow-y-auto px-2 py-3 text-[12.5px]">
                <p class="apm-nav-heading">Overview</p>
                <a href="{{ route('dashboard') }}" @if ($activePage === 'dashboard') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'dashboard' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span>Dashboard</span><span class="{{ $activePage === 'dashboard' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span>
                </a>

                <p class="apm-nav-heading mt-4">Accounting</p>
                <a href="{{ route('chart-of-accounts') }}" @if ($activePage === 'chart-of-accounts') aria-current="page" @endif class="apm-nav-item {{ $activePage === 'chart-of-accounts' ? 'bg-blue-600 text-white shadow-md shadow-blue-950/20 hover:bg-blue-500' : '' }}">
                    <span>Chart of Accounts</span><span class="{{ $activePage === 'chart-of-accounts' ? 'rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-semibold text-white' : 'apm-full-badge' }}">FULL</span>
                </a>
                <button type="button" class="apm-nav-item"><span>Journal Entries</span><span class="apm-full-badge">FULL</span></button>
                <button type="button" class="apm-nav-item"><span>General Ledger</span><span class="apm-full-badge">FULL</span></button>

                <p class="apm-nav-heading mt-4">Sales &amp; Receivables</p>
                <button type="button" class="apm-nav-item"><span>Sales and Revenue</span><span class="apm-full-badge">FULL</span></button>
                <button type="button" class="apm-nav-item"><span>Accounts Receivable</span><span class="apm-full-badge">FULL</span></button>

                <p class="apm-nav-heading mt-4">Purchases &amp; Payables</p>
                <button type="button" class="apm-nav-item"><span>Accounts Payable</span><span class="apm-full-badge">FULL</span></button>

                <p class="apm-nav-heading mt-4">Banking</p>
                <button type="button" class="apm-nav-item"><span>Cash &amp; Bank</span><span class="apm-full-badge">FULL</span></button>
                <button type="button" class="apm-nav-item"><span>Expenses</span><span class="apm-full-badge">FULL</span></button>

                <p class="apm-nav-heading mt-4">Reporting</p>
                <button type="button" class="apm-nav-item"><span>Financial Reports</span><span class="apm-full-badge">FULL</span></button>

                <p class="apm-nav-heading mt-4">Administration</p>
                <button type="button" class="apm-nav-item"><span>Tax Settings</span><span class="apm-full-badge">FULL</span></button>
                <button type="button" class="apm-nav-item"><span>Audit Trail</span><span class="apm-full-badge">FULL</span></button>
                <button type="button" class="apm-nav-item"><span>Users &amp; Settings</span><span class="apm-full-badge">FULL</span></button>
            </nav>

            <div class="flex h-16 shrink-0 items-center border-t border-white/10 px-3">
                <div class="flex w-full items-center gap-2.5 rounded-lg p-1 transition-colors duration-150 hover:bg-white/5">
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-red-600 text-[10px] font-bold text-white">MS</span>
                    <div class="min-w-0"><p class="truncate text-[11px] font-medium text-white">Maria Santos</p><span class="inline-block rounded-full bg-red-100 px-1.5 py-0.5 text-[8px] font-bold text-red-600">Administrator</span></div>
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-20 flex h-14 items-center border-b border-slate-200 bg-white/95 px-3 backdrop-blur sm:px-5">
            <button type="button" aria-label="Open menu" class="apm-icon-button mr-2">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <p class="shrink-0 text-sm font-semibold text-slate-700">{{ $pageTitle }}</p>
            <label class="relative ml-3 hidden w-80 md:block">
                <span class="sr-only">Search anything</span>
                <svg class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                <input type="search" placeholder="Search anything..." class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pr-3 pl-9 text-xs outline-none transition duration-150 placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:ring-3 focus:ring-blue-100">
            </label>
            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <span class="hidden rounded-md border border-amber-300 bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-700 sm:inline">Demo</span>
                <span class="hidden rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-[10px] font-semibold text-red-600 sm:inline">Administrator</span>
                <button type="button" aria-label="Account menu" class="apm-icon-button bg-slate-100"><svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg></button>
                <button type="button" aria-label="Notifications" class="apm-icon-button hidden sm:grid"><svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.85 23.85 0 0 0 5.454-1.31A8.97 8.97 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.312 6.022 23.85 23.85 0 0 0 5.455 1.31m5.714 0a24.26 24.26 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg></button>
                <button type="button" aria-label="Toggle theme" class="apm-icon-button hidden sm:grid"><svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9.75 9.75 0 0 1 8.25 2.25a9.75 9.75 0 1 0 13.5 13.5Z"/></svg></button>
                <span class="grid size-8 place-items-center rounded-full bg-red-600 text-[10px] font-bold text-white">MS</span>
            </div>
        </header>

        @yield('content')
    </div>
</body>
</html>
