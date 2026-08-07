<?php

namespace App\Http\Controllers;

use App\Services\DemoData\AccountDataService;
use App\Services\Exports\ChartOfAccountsExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChartOfAccountsExportController extends Controller
{
    public function pdf(
        Request $request,
        AccountDataService $accounts,
        ChartOfAccountsExportService $exports,
    ): Response|RedirectResponse {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $content = $exports->pdf($accounts->all($this->filters($request)), now());
        $filename = 'chart-of-accounts-'.now()->format('Y-m-d').'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($content),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function csv(
        Request $request,
        AccountDataService $accounts,
        ChartOfAccountsExportService $exports,
    ): StreamedResponse|RedirectResponse {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $rows = $accounts->all($this->filters($request));
        $filename = 'chart-of-accounts-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            static function () use ($exports, $rows): void {
                $stream = fopen('php://output', 'wb');

                if ($stream === false) {
                    throw new \RuntimeException('Unable to open the CSV output stream.');
                }

                $exports->writeCsv($stream, $rows);
                fclose($stream);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /** @return array{search?: string|null, type?: string|null, status?: string|null} */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'])],
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
        ]);
    }
}
