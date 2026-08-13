<?php

namespace App\Http\Controllers;

use App\Services\Accounting\FinancialReportService;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\ExpenseDataService;
use App\Services\DemoData\SalesDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function index(Request $request, FinancialReportService $reports, SalesDataService $sales, AccountDataService $accounts, ExpenseDataService $expenses): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $report = array_key_exists((string) $request->query('report'), FinancialReportService::REPORTS) ? (string) $request->query('report') : 'trial-balance';
        $filters = $this->filters($request);
        try {
            $initial = $reports->generate($report, $filters);
            $summaries = $reports->summaries($filters);
        } catch (RuntimeException $exception) {
            $initial = $reports->generate('trial-balance');
            $summaries = $reports->summaries();
        }

        return view('financial-reports', [
            'initialReport' => $initial,
            'summaries' => $summaries,
            'reportNames' => FinancialReportService::REPORTS,
            'customers' => collect($sales->customers())->sortBy('name')->values()->all(),
            'expenseCategories' => collect($accounts->all(['type' => 'Expense', 'status' => 'Active']))->sortBy('name')->values()->all(),
            'expenseStatuses' => collect($expenses->all())->pluck('status')->unique()->sort()->values()->all(),
        ]);
    }

    public function data(Request $request, FinancialReportService $reports): JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }
        $validator = validator($request->query(), $this->rules());
        if ($validator->fails()) {
            return response()->json(['message' => 'Please correct the report filters.', 'errors' => $validator->errors()], 422);
        }
        $validated = $validator->validated();
        try {
            return response()->json(['report' => $reports->generate($validated['report'], $validated)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function csv(Request $request, FinancialReportService $reports): StreamedResponse|RedirectResponse|JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }
        $validator = validator($request->query(), $this->rules());
        if ($validator->fails()) {
            return response()->json(['message' => 'Please correct the report filters.'], 422);
        }
        try {
            $report = $reports->generate($validator->validated()['report'], $validator->validated());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, [$report['title']]);
            fputcsv($output, ['Period', $report['period']]);
            fputcsv($output, ['Generated', now()->toIso8601String()]);
            fputcsv($output, []);
            fputcsv($output, array_column($report['columns'], 'label'));
            foreach ($report['rows'] as $row) {
                if (($row['row_type'] ?? '') === 'group') {
                    fputcsv($output, [$row['account_name'] ?? '']);

                    continue;
                }
                fputcsv($output, array_map(fn (array $column): mixed => $row[$column['key']] ?? '', $report['columns']));
            }
            fputcsv($output, []);
            $totalRow = [];
            foreach ($report['columns'] as $index => $column) {
                $totalRow[] = $index === 0 ? ($report['totals']['label'] ?? 'TOTAL') : ($report['totals'][$column['key']] ?? '');
            }
            fputcsv($output, $totalRow);
            fclose($output);
        }, $report['key'].'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->only(['date_from', 'date_to', 'party', 'status', 'category', 'tax_code']);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'report' => ['required', Rule::in(array_keys(FinancialReportService::REPORTS))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'party' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:30'],
            'tax_code' => ['nullable', Rule::in(['INPUT', 'OUTPUT'])],
        ];
    }
}
