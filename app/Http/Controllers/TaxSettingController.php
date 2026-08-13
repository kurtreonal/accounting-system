<?php

namespace App\Http\Controllers;

use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\JournalEntryDataService;
use App\Services\DemoData\TaxCodeDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxSettingController extends Controller
{
    public function index(Request $request, TaxCodeDataService $taxCodes, JournalEntryDataService $journals): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }
        $filters = $this->summaryFilters($request);

        return view('tax-settings', [
            'user' => $request->session()->get('demo_user'),
            'taxCodes' => $taxCodes->all(),
            'summary' => $this->summaryData($journals, $taxCodes, $filters),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, TaxCodeDataService $taxCodes, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyAdministration($request)) {
            return $response;
        }
        $validator = validator($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $taxCode = $taxCodes->create($this->attributes($validator->validated()));
            $auditLogs->record($this->actor($request), 'created', $taxCode['code'], ['rate' => $taxCode['rate'], 'type' => $taxCode['type']], 'tax_code');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Tax code created.', 'tax_code' => $taxCode], 201);
    }

    public function update(Request $request, int $id, TaxCodeDataService $taxCodes, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyAdministration($request)) {
            return $response;
        }
        $validator = validator($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $taxCode = $taxCodes->update($id, $this->attributes($validator->validated()));
            $auditLogs->record($this->actor($request), 'updated', $taxCode['code'], ['rate' => $taxCode['rate'], 'type' => $taxCode['type']], 'tax_code');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Tax code updated.', 'tax_code' => $taxCode]);
    }

    public function status(Request $request, int $id, TaxCodeDataService $taxCodes, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyAdministration($request)) {
            return $response;
        }
        $validator = validator($request->all(), ['status' => ['required', Rule::in(['Active', 'Inactive'])]]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $taxCode = $taxCodes->updateStatus($id, $validator->validated()['status']);
            $auditLogs->record($this->actor($request), Str::lower($taxCode['status']), $taxCode['code'], [], 'tax_code');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => "Tax code marked {$taxCode['status']}.", 'tax_code' => $taxCode]);
    }

    public function setDefault(Request $request, int $id, TaxCodeDataService $taxCodes, AuditLogDataService $auditLogs): JsonResponse
    {
        if ($response = $this->denyAdministration($request)) {
            return $response;
        }
        try {
            $taxCode = $taxCodes->setDefault($id);
            $auditLogs->record($this->actor($request), 'set_default', $taxCode['code'], ['type' => $taxCode['type']], 'tax_code');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => "{$taxCode['code']} is now the default {$taxCode['type']} code.", 'tax_code' => $taxCode]);
    }

    public function summary(Request $request, TaxCodeDataService $taxCodes, JournalEntryDataService $journals): JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }
        $validator = validator($request->query(), $this->summaryRules());
        if ($validator->fails()) {
            return response()->json(['message' => 'Please correct the VAT summary filters.', 'errors' => $validator->errors()], 422);
        }

        return response()->json(['summary' => $this->summaryData($journals, $taxCodes, $validator->validated())]);
    }

    public function csv(Request $request, TaxCodeDataService $taxCodes, JournalEntryDataService $journals): StreamedResponse|RedirectResponse|JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }
        $view = $request->query('view', 'rates');
        if ($view === 'summary') {
            $validator = validator($request->query(), $this->summaryRules());
            if ($validator->fails()) {
                return response()->json(['message' => 'Please correct the VAT summary filters.'], 422);
            }
            $summary = $this->summaryData($journals, $taxCodes, $validator->validated());

            return response()->streamDownload(function () use ($summary): void {
                $output = fopen('php://output', 'wb');
                fputcsv($output, ['Date', 'Reference', 'Journal', 'Tax Code', 'Direction', 'Configured Rate', 'Taxable Amount', 'Tax Amount']);
                foreach ($summary['rows'] as $row) {
                    fputcsv($output, [$row['date'], $row['reference'], $row['journal_number'], $row['tax_code'], $row['direction'], $row['rate'], number_format($row['taxable_amount'], 2, '.', ''), number_format($row['tax_amount'], 2, '.', '')]);
                }
                fclose($output);
            }, 'vat-summary-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        $rows = $taxCodes->all();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Code', 'Tax Name', 'Rate', 'Type', 'Applies To', 'Default', 'Status']);
            foreach ($rows as $row) {
                fputcsv($output, [$row['code'], $row['name'], $row['rate'], $row['type'], $row['applies_to'], $row['is_default'] ? 'Yes' : 'No', $row['status']]);
            }
            fclose($output);
        }, 'tax-rates-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['required', Rule::in(['VAT', 'EWT', 'DST'])],
            'applies_to' => ['required', 'string', 'max:100'],
            'is_default' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function summaryRules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'tax_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'code' => Str::upper(trim($data['code'])),
            'name' => trim($data['name']),
            'rate' => round((float) $data['rate'], 2),
            'type' => $data['type'],
            'applies_to' => trim($data['applies_to']),
            'is_default' => (bool) $data['is_default'],
        ];
    }

    /** @return array{date_from: string, date_to: string, tax_code: string} */
    private function summaryFilters(Request $request): array
    {
        return [
            'date_from' => (string) ($request->query('date_from') ?: now()->startOfYear()->toDateString()),
            'date_to' => (string) ($request->query('date_to') ?: now()->toDateString()),
            'tax_code' => trim((string) $request->query('tax_code', '')),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function summaryData(JournalEntryDataService $journals, TaxCodeDataService $taxCodes, array $filters): array
    {
        $codes = collect($taxCodes->all());
        $defaults = $codes->where('status', 'Active')->groupBy('type')->map(fn ($group) => $group->firstWhere('is_default', true) ?? $group->first());
        $rows = [];
        foreach ($journals->all() as $entry) {
            if (! in_array($entry['status'], ['Posted', 'Reversed'], true) || $entry['date'] < $filters['date_from'] || $entry['date'] > $filters['date_to']) {
                continue;
            }
            foreach ($entry['lines'] as $line) {
                $name = Str::lower((string) ($line['account_name'] ?? ''));
                $direction = str_contains($name, 'input tax') ? 'Input VAT' : (str_contains($name, 'output tax') ? 'Output VAT' : (str_contains($name, 'withholding') || str_contains($name, 'ewt') ? 'EWT Withheld' : null));
                if ($direction === null) {
                    continue;
                }
                $type = $direction === 'EWT Withheld' ? 'EWT' : 'VAT';
                $code = $defaults->get($type);
                if (! $code || ($filters['tax_code'] ?? '') !== '' && $code['code'] !== $filters['tax_code']) {
                    continue;
                }
                $amount = $direction === 'Input VAT'
                    ? (float) $line['debit'] - (float) $line['credit']
                    : (float) $line['credit'] - (float) $line['debit'];
                $rate = (float) $code['rate'];
                $rows[] = [
                    'date' => $entry['date'], 'reference' => $entry['reference'] ?: $entry['journal_number'], 'journal_number' => $entry['journal_number'],
                    'tax_code' => $code['code'], 'tax_name' => $code['name'], 'direction' => $direction, 'rate' => $rate,
                    'taxable_amount' => $rate > 0 ? round($amount / ($rate / 100), 2) : 0.0, 'tax_amount' => round($amount, 2),
                    'link' => route('journal-entries', ['entry' => $entry['journal_number']]),
                ];
            }
        }
        $output = round((float) collect($rows)->where('direction', 'Output VAT')->sum('tax_amount'), 2);
        $input = round((float) collect($rows)->where('direction', 'Input VAT')->sum('tax_amount'), 2);
        $ewt = round((float) collect($rows)->where('direction', 'EWT Withheld')->sum('tax_amount'), 2);

        return ['rows' => $rows, 'metrics' => ['output' => $output, 'input' => $input, 'net' => round($output - $input, 2), 'ewt' => $ewt], 'record_count' => count($rows), 'generated_at' => now()->toIso8601String(), 'filters' => $filters];
    }

    private function denyAdministration(Request $request): ?JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }
        if ($request->session()->get('demo_user.role') !== 'Administrator') {
            return response()->json(['message' => 'Only Administrators can change demo tax configuration.'], 403);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function actor(Request $request): array
    {
        return (array) $request->session()->get('demo_user', []);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['message' => 'Please correct the tax code fields.', 'errors' => $errors], 422);
    }
}
