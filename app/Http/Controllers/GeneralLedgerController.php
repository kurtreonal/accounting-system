<?php

namespace App\Http\Controllers;

use App\Services\Accounting\GeneralLedgerService;
use App\Services\Exports\AccountingPdfExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralLedgerController extends Controller
{
    public function index(Request $request, GeneralLedgerService $ledger): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $accounts = $ledger->accounts();
        $requestedCode = (string) $request->query('account', '');
        $selected = collect($accounts)->first(
            static fn (array $account): bool => (string) $account['code'] === $requestedCode
        ) ?? ($accounts[0] ?? null);
        $report = $selected === null ? null : $ledger->forAccount(
            (string) $selected['code'],
            $request->query('date_from'),
            $request->query('date_to'),
            (string) $request->query('search', ''),
        );

        return view('general-ledger', [
            'accounts' => $accounts,
            'report' => $report,
        ]);
    }

    public function data(Request $request, GeneralLedgerService $ledger): JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }

        try {
            $report = $ledger->forAccount(
                (string) $request->query('account', ''),
                $request->query('date_from'),
                $request->query('date_to'),
                (string) $request->query('search', ''),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $report]);
    }

    public function csv(Request $request, GeneralLedgerService $ledger): StreamedResponse|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        try {
            $report = $ledger->forAccount(
                (string) $request->query('account', ''),
                $request->query('date_from'),
                $request->query('date_to'),
                (string) $request->query('search', ''),
            );
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        $filename = 'general-ledger-'.$report['account']['code'].'.csv';

        return response()->streamDownload(static function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['General Ledger']);
            fputcsv($output, ['Account', $report['account']['code'].' - '.$report['account']['name']]);
            fputcsv($output, ['Period', ($report['filters']['date_from'] ?? 'Beginning').' to '.($report['filters']['date_to'] ?? 'Present')]);
            fputcsv($output, ['Generated', now()->toIso8601String()]);
            fputcsv($output, []);
            fputcsv($output, ['Date', 'Journal Entry', 'Reference', 'Description', 'Debit', 'Credit', 'Running Balance']);
            fputcsv($output, ['', '', '', 'Beginning Balance', '', '', number_format($report['beginning_balance'], 2, '.', '')]);

            foreach ($report['rows'] as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['journal_number'],
                    $row['reference'],
                    $row['line_description'] !== '' ? $row['line_description'] : $row['description'],
                    number_format($row['debit'], 2, '.', ''),
                    number_format($row['credit'], 2, '.', ''),
                    number_format($row['running_balance'], 2, '.', ''),
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(
        Request $request,
        GeneralLedgerService $ledger,
        AccountingPdfExportService $exports,
    ): Response|RedirectResponse {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        try {
            $report = $ledger->forAccount(
                (string) $request->query('account', ''),
                $request->query('date_from'),
                $request->query('date_to'),
                (string) $request->query('search', ''),
            );
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        $content = $exports->generalLedger($report, now());
        $filename = 'general-ledger-'.$report['account']['code'].'-'.now()->format('Y-m-d').'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($content),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
