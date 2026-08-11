<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice['invoice_number'] }} | APM Accounting</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 p-4 text-slate-800 print:bg-white print:p-0">
    <main class="mx-auto max-w-4xl rounded-xl bg-white p-6 shadow-sm print:max-w-none print:rounded-none print:shadow-none sm:p-10">
        <header class="flex flex-col gap-5 border-b-2 border-slate-900 pb-5 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="text-lg font-bold text-slate-900">APM Customs</p><p class="text-xs text-slate-500">Accounting System</p></div>
            <div class="sm:text-right"><h1 class="text-2xl font-bold text-slate-900">SALES INVOICE</h1><p class="mt-1 font-mono text-sm text-blue-600">{{ $invoice['invoice_number'] }}</p></div>
        </header>
        <section class="mt-6 grid gap-5 text-xs sm:grid-cols-2">
            <div><p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Bill To</p><p class="mt-1 text-sm font-semibold">{{ $invoice['customer_name'] }}</p><p class="mt-1 text-slate-500">Customer code: {{ $invoice['customer_code'] }}</p></div>
            <dl class="grid grid-cols-2 gap-2 sm:ml-auto sm:w-64"><dt class="text-slate-500">Invoice date</dt><dd class="text-right font-mono">{{ $invoice['invoice_date'] }}</dd><dt class="text-slate-500">Due date</dt><dd class="text-right font-mono">{{ $invoice['due_date'] }}</dd><dt class="text-slate-500">Status</dt><dd class="text-right font-medium">{{ $invoice['display_status'] }}</dd><dt class="text-slate-500">Reference</dt><dd class="text-right">{{ $invoice['reference'] ?: '—' }}</dd></dl>
        </section>
        <div class="mt-7 overflow-x-auto"><table class="w-full min-w-[600px] text-left text-xs"><thead class="bg-slate-900 text-white"><tr><th class="px-3 py-2.5">Description</th><th class="px-3 py-2.5 text-right">Quantity</th><th class="px-3 py-2.5 text-right">Unit Price</th><th class="px-3 py-2.5 text-right">Tax</th><th class="px-3 py-2.5 text-right">Amount</th></tr></thead><tbody>@foreach($invoice['lines'] as $line)<tr class="border-b border-slate-200"><td class="px-3 py-3">{{ $line['description'] }}</td><td class="px-3 py-3 text-right font-mono">{{ number_format($line['quantity'], 2) }}</td><td class="px-3 py-3 text-right font-mono">₱{{ number_format($line['unit_price'], 2) }}</td><td class="px-3 py-3 text-right font-mono">{{ number_format($line['tax_rate'], 2) }}%</td><td class="px-3 py-3 text-right font-mono">₱{{ number_format($line['total'], 2) }}</td></tr>@endforeach</tbody></table></div>
        <div class="mt-5 ml-auto w-full max-w-xs"><dl class="grid grid-cols-2 gap-2 text-xs"><dt class="text-slate-500">Subtotal</dt><dd class="text-right font-mono">₱{{ number_format($invoice['subtotal'], 2) }}</dd><dt class="text-slate-500">Tax</dt><dd class="text-right font-mono">₱{{ number_format($invoice['tax'], 2) }}</dd><dt class="border-t border-slate-300 pt-3 text-sm font-bold">Total</dt><dd class="border-t border-slate-300 pt-3 text-right font-mono text-sm font-bold">₱{{ number_format($invoice['total'], 2) }}</dd></dl></div>
        @if($invoice['memo'])<p class="mt-8 rounded-lg bg-slate-50 p-4 text-xs text-slate-600"><strong class="text-slate-800">Memo:</strong> {{ $invoice['memo'] }}</p>@endif
        <footer class="mt-10 flex items-center justify-between border-t border-slate-200 pt-4 text-[10px] text-slate-400"><span>Demo data only</span><span>Generated {{ now()->format('Y-m-d H:i') }}</span></footer>
    </main>
    <button type="button" onclick="window.print()" class="fixed right-5 bottom-5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white shadow-lg transition hover:bg-blue-500 print:hidden"><i class="fa-solid fa-print"></i> Print Invoice</button>
</body>
</html>
