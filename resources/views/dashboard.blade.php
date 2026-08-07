<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111a2f">
    <title>Dashboard | NEXII Accounting</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f3f7fb] font-sans text-[#142038] antialiased">
    <div class="min-h-screen lg:pl-60">
        <aside class="fixed inset-y-0 left-0 z-30 hidden h-dvh w-60 flex-col overflow-hidden bg-[#111a2f] text-slate-300 lg:flex">
            <div class="flex h-14 shrink-0 items-center gap-2.5 border-b border-white/10 px-3.5">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-[#2463eb] text-[11px] font-bold tracking-tight text-white shadow-lg shadow-blue-950/30">NX</span>
                <div class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-semibold text-white">NEXII Tech Solutions</p>
                    <p class="text-xs text-slate-500">Accounting System</p>
                </div>
            </div>

            <nav class="dashboard-scrollbar-hidden min-h-0 flex-1 overflow-y-auto px-2 py-3 text-[12.5px]">
                <p class="px-2 pb-2 text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase">Overview</p>
                <div aria-current="page" class="rounded-lg bg-[#2463eb] px-2.5 py-2 text-white shadow-md shadow-blue-950/20">Dashboard</div>

                <p class="mt-4 px-2 pb-1.5 text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase">Accounting</p>
                <span class="dashboard-nav-item">Chart of Accounts</span>
                <span class="dashboard-nav-item">Journal Entries</span>
                <span class="dashboard-nav-item">General Ledger</span>

                <p class="mt-4 px-2 pb-1.5 text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase">Sales &amp; Receivables</p>
                <span class="dashboard-nav-item">Sales and Revenue</span>
                <span class="dashboard-nav-item">Accounts Receivable</span>

                <p class="mt-4 px-2 pb-1.5 text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase">Purchases &amp; Payables</p>
                <span class="dashboard-nav-item">Accounts Payable</span>

                <p class="mt-4 px-2 pb-1.5 text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase">Banking</p>
                <span class="dashboard-nav-item">Cash &amp; Bank</span>
                <span class="dashboard-nav-item">Expenses</span>

                <p class="mt-4 px-2 pb-1.5 text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase">Reporting</p>
                <span class="dashboard-nav-item">Financial Reports</span>

                <p class="mt-4 px-2 pb-1.5 text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase">Administration</p>
                <span class="dashboard-nav-item">Tax Settings</span>
                <span class="dashboard-nav-item">Audit Trail</span>
                <span class="dashboard-nav-item">Users &amp; Settings</span>
            </nav>

            <div class="flex h-17 shrink-0 items-center border-t border-white/10 p-3">
                <div class="flex w-full items-center gap-2.5 rounded-lg p-1 transition-colors hover:bg-white/5">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-red-600 text-[11px] font-bold text-white">{{ strtoupper(substr($user['name'], 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-medium text-white">{{ $user['name'] }}</p>
                        <span class="inline-block rounded-full bg-red-50 px-1.5 py-0.5 text-[8px] font-bold text-red-600">{{ $user['role'] }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="Sign out" aria-label="Sign out" class="grid size-8 cursor-pointer place-items-center rounded-lg text-slate-500 transition duration-200 hover:bg-white/10 hover:text-white active:scale-90">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3-6 3 3m0 0-3 3m3-3H9"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-20 flex h-14 items-center border-b border-slate-200 bg-white/95 px-4 backdrop-blur lg:px-6">
            <span class="mr-2 grid size-8 place-items-center rounded-lg bg-[#2463eb] text-[11px] font-bold text-white lg:hidden">NX</span>
            <h1 class="text-sm font-semibold text-slate-700">Dashboard</h1>
            <div class="ml-auto flex items-center gap-2">
                <span class="hidden rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-[10px] font-semibold text-red-600 sm:inline">{{ $user['role'] }}</span>
                <button type="button" aria-label="Toggle dark mode" title="Dark mode" class="grid size-8 cursor-pointer place-items-center rounded-full text-slate-500 transition duration-200 hover:bg-blue-50 hover:text-blue-600 active:rotate-12 active:scale-90">
                    <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9.75 9.75 0 0 1 8.25 2.25a9.75 9.75 0 1 0 13.5 13.5Z"/></svg>
                </button>
                <span class="grid size-8 place-items-center rounded-full bg-red-600 text-[10px] font-bold text-white shadow-sm">{{ strtoupper(substr($user['name'], 0, 1)) }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-[1440px] p-4 sm:p-5 lg:p-6">
            <section aria-label="Financial summary" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article class="dashboard-card dashboard-enter [animation-delay:40ms]">
                    <p class="text-xs text-slate-500">Cash on Hand</p>
                    <p class="mt-1.5 font-mono text-xl font-bold tracking-tight text-slate-900">&#8369;0.00</p>
                </article>
                <article class="dashboard-card dashboard-enter [animation-delay:80ms]">
                    <p class="text-xs text-slate-500">Bank Balance</p>
                    <p class="mt-1.5 font-mono text-xl font-bold tracking-tight text-slate-900">&#8369;0.00</p>
                </article>
                <article class="dashboard-card dashboard-enter [animation-delay:120ms]">
                    <p class="text-xs text-slate-500">Accounts Receivable</p>
                    <p class="mt-1.5 font-mono text-xl font-bold tracking-tight text-slate-900">&#8369;0.00</p>
                </article>
                <article class="dashboard-card dashboard-enter [animation-delay:160ms]">
                    <p class="text-xs text-slate-500">Accounts Payable</p>
                    <p class="mt-1.5 font-mono text-xl font-bold tracking-tight text-slate-900">&#8369;0.00</p>
                </article>
                <article class="dashboard-card dashboard-enter [animation-delay:200ms] sm:col-span-2 xl:col-span-1">
                    <p class="text-xs text-slate-500">Net Income</p>
                    <p class="mt-1.5 font-mono text-xl font-bold tracking-tight text-slate-900">&#8369;0.00</p>
                </article>
            </section>

            <section class="mt-5 grid gap-4 xl:grid-cols-3">
                <article class="dashboard-panel dashboard-enter xl:col-span-2 [animation-delay:240ms]">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">Revenue vs. Expenses</h2>
                    </div>
                    <div class="dashboard-empty-state min-h-69">
                        <svg class="size-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                        <p>No revenue or expense data</p>
                    </div>
                </article>

                <article class="dashboard-panel dashboard-enter [animation-delay:280ms]">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">Cash Flow</h2>
                    </div>
                    <div class="dashboard-empty-state min-h-69">
                        <svg class="size-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h14.25M7.5 13.5l3-3 2.25 2.25L16.5 9m0 0h-2.25m2.25 0v2.25"/></svg>
                        <p>No cash-flow data</p>
                    </div>
                </article>
            </section>

            <section class="mt-5 grid gap-4 pb-6 xl:grid-cols-3">
                <article class="dashboard-panel dashboard-enter overflow-hidden [animation-delay:320ms]">
                    <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Recent Journal Entries</h2></div>
                    <div class="dashboard-empty-state min-h-52"><p>No journal entries</p></div>
                </article>

                <article class="dashboard-panel dashboard-enter overflow-hidden [animation-delay:360ms]">
                    <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Outstanding Invoices</h2></div>
                    <div class="dashboard-empty-state min-h-52"><p>No outstanding invoices</p></div>
                </article>

                <article class="dashboard-panel dashboard-enter overflow-hidden [animation-delay:400ms]">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">Pending Approvals</h2>
                        <p class="mt-0.5 text-xs text-slate-500">0 items</p>
                    </div>
                    <div class="dashboard-empty-state min-h-52"><p>No pending approvals</p></div>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
