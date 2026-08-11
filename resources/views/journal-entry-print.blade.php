<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $entry['journal_number'] }} | Journal Entry</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-100 p-5 font-sans text-slate-900 print:bg-white print:p-0">
    <main class="mx-auto max-w-4xl rounded-xl bg-white p-8 shadow-sm print:max-w-none print:rounded-none print:shadow-none">
        <header class="flex items-start justify-between border-b border-slate-300 pb-5">
            <div><p class="text-sm font-semibold text-blue-600">APM Customs</p><h1 class="mt-1 text-2xl font-bold">Journal Entry</h1></div>
            <button type="button" onclick="window.print()" class="apm-primary-button print:hidden">Print</button>
        </header>
        <section class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
            <div><p class="text-xs text-slate-500">Journal Number</p><p class="mt-1 font-mono font-semibold">{{ $entry['journal_number'] }}</p></div>
            <div><p class="text-xs text-slate-500">Date</p><p class="mt-1">{{ $entry['date'] }}</p></div>
            <div><p class="text-xs text-slate-500">Reference</p><p class="mt-1">{{ $entry['reference'] ?: '-' }}</p></div>
            <div><p class="text-xs text-slate-500">Status</p><p class="mt-1 font-semibold">{{ $entry['status'] }}</p></div>
            <div class="col-span-2 sm:col-span-4"><p class="text-xs text-slate-500">Description</p><p class="mt-1">{{ $entry['description'] }}</p></div>
        </section>
        <table class="mt-7 w-full border-collapse text-left text-xs">
            <thead><tr class="border-y border-slate-300 bg-slate-50"><th class="px-3 py-2">Account</th><th class="px-3 py-2">Description</th><th class="px-3 py-2">Reference</th><th class="px-3 py-2 text-right">Debit</th><th class="px-3 py-2 text-right">Credit</th></tr></thead>
            <tbody>@foreach ($entry['lines'] as $line)<tr class="border-b border-slate-200"><td class="px-3 py-3"><span class="font-mono">{{ $line['account_code'] }}</span> - {{ $line['account_name'] }}</td><td class="px-3 py-3">{{ $line['description'] }}</td><td class="px-3 py-3">{{ $line['party_reference'] }}</td><td class="px-3 py-3 text-right font-mono">{{ $line['debit'] > 0 ? 'PHP '.number_format($line['debit'], 2) : '-' }}</td><td class="px-3 py-3 text-right font-mono">{{ $line['credit'] > 0 ? 'PHP '.number_format($line['credit'], 2) : '-' }}</td></tr>@endforeach</tbody>
            <tfoot><tr class="border-b-2 border-slate-900 font-semibold"><td colspan="3" class="px-3 py-3 text-right">Totals</td><td class="px-3 py-3 text-right font-mono">PHP {{ number_format($entry['total_debit'], 2) }}</td><td class="px-3 py-3 text-right font-mono">PHP {{ number_format($entry['total_credit'], 2) }}</td></tr></tfoot>
        </table>
        <footer class="mt-8 grid grid-cols-2 gap-8 text-xs text-slate-600 sm:grid-cols-3">
            <div><p>Prepared by</p><p class="mt-8 border-t border-slate-400 pt-2 font-medium text-slate-900">{{ data_get($entry, 'created_by.name', '-') }}</p></div>
            <div><p>Reviewed by</p><p class="mt-8 border-t border-slate-400 pt-2 font-medium text-slate-900">{{ data_get($entry, 'reviewed_by.name', '-') }}</p></div>
            <div><p>Posted by</p><p class="mt-8 border-t border-slate-400 pt-2 font-medium text-slate-900">{{ data_get($entry, 'posted_by.name', '-') }}</p></div>
        </footer>
    </main>
</body>
</html>
