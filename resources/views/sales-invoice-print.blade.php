<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Invoice {{ $invoice['invoice_number'] }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        .simple-document table { border-collapse: collapse; }
        .simple-document th,
        .simple-document td { border: 0; border-bottom: 1px solid #d1d5db; }
        .simple-document thead th { border-top: 1px solid #111827; border-bottom-color: #111827; }
        .simple-document tfoot td { border-top: 1px solid #111827; border-bottom-color: #111827; }
        @media print {
            html, body { background: #fff !important; color: #111827 !important; }
            .simple-document { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .simple-document th, .simple-document td { background: #fff !important; color: #111827 !important; }
        }
    </style>
</head>
<body class="bg-white p-6 font-sans text-slate-900 print:p-0">
    <main class="simple-document mx-auto max-w-6xl">
        <h1 class="mb-6 text-left text-xl font-bold">Sales Invoice {{ $invoice['invoice_number'] }}</h1>
        <table class="w-full text-left text-xs">
            <thead>
                <tr>
                    <th class="px-3 py-2.5">Customer</th>
                    <th class="px-3 py-2.5">Description</th>
                    <th class="px-3 py-2.5 text-right">Quantity</th>
                    <th class="px-3 py-2.5 text-right">Unit Price</th>
                    <th class="px-3 py-2.5 text-right">Tax</th>
                    <th class="px-3 py-2.5 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice['lines'] as $line)
                    <tr>
                        <td class="px-3 py-3">{{ $invoice['customer_name'] }}</td>
                        <td class="px-3 py-3">{{ $line['description'] }}</td>
                        <td class="px-3 py-3 text-right font-mono">{{ number_format($line['quantity'], 2) }}</td>
                        <td class="px-3 py-3 text-right font-mono">PHP {{ number_format($line['unit_price'], 2) }}</td>
                        <td class="px-3 py-3 text-right font-mono">{{ number_format($line['tax_rate'], 2) }}%</td>
                        <td class="px-3 py-3 text-right font-mono">PHP {{ number_format($line['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="px-3 py-2 text-right">Subtotal</td>
                    <td class="px-3 py-2 text-right font-mono">PHP {{ number_format($invoice['subtotal'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5" class="px-3 py-2 text-right">Tax</td>
                    <td class="px-3 py-2 text-right font-mono">PHP {{ number_format($invoice['tax'], 2) }}</td>
                </tr>
                <tr class="font-bold">
                    <td colspan="5" class="px-3 py-2 text-right">Total</td>
                    <td class="px-3 py-2 text-right font-mono">PHP {{ number_format($invoice['total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </main>
    <button type="button" onclick="window.print()" class="fixed right-5 bottom-5 rounded-md bg-slate-900 px-4 py-2 text-xs font-medium text-white print:hidden">Print</button>
</body>
</html>
