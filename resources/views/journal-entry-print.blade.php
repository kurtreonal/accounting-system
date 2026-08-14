<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Entry {{ $entry['journal_number'] }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 portrait; margin: 10mm; }
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
        <h1 class="mb-6 text-left text-xl font-bold">Journal Entry {{ $entry['journal_number'] }}</h1>
        <table class="w-full text-left text-xs">
            <thead>
                <tr>
                    <th class="px-3 py-2.5">Account</th>
                    <th class="px-3 py-2.5">Description</th>
                    <th class="px-3 py-2.5">Reference</th>
                    <th class="px-3 py-2.5 text-right">Debit</th>
                    <th class="px-3 py-2.5 text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entry['lines'] as $line)
                    <tr>
                        <td class="px-3 py-3"><span class="font-mono">{{ $line['account_code'] }}</span> - {{ $line['account_name'] }}</td>
                        <td class="px-3 py-3">{{ $line['description'] ?: '-' }}</td>
                        <td class="px-3 py-3">{{ $line['party_reference'] ?: '-' }}</td>
                        <td class="px-3 py-3 text-right font-mono">{{ $line['debit'] > 0 ? 'PHP '.number_format($line['debit'], 2) : '-' }}</td>
                        <td class="px-3 py-3 text-right font-mono">{{ $line['credit'] > 0 ? 'PHP '.number_format($line['credit'], 2) : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-bold">
                    <td colspan="3" class="px-3 py-3 text-right">Totals</td>
                    <td class="px-3 py-3 text-right font-mono">PHP {{ number_format($entry['total_debit'], 2) }}</td>
                    <td class="px-3 py-3 text-right font-mono">PHP {{ number_format($entry['total_credit'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </main>
    <button type="button" onclick="window.print()" class="fixed right-5 bottom-5 rounded-md bg-slate-900 px-4 py-2 text-xs font-medium text-white print:hidden">Print</button>
</body>
</html>
