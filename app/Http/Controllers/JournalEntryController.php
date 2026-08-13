<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingPostingService;
use App\Services\DemoAccessService;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\JournalEntryDataService;
use App\Services\Exports\AccountingPdfExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalEntryController extends Controller
{
    public function index(
        Request $request,
        JournalEntryDataService $journals,
        AccountDataService $accounts,
        DemoAccessService $access,
    ): View|RedirectResponse {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        return view('journal-entries', [
            'journalEntries' => $this->visibleEntries($journals->all(), $request, $access),
            'activeAccounts' => $accounts->all(['status' => 'Active']),
            'user' => $request->session()->get('demo_user'),
        ]);
    }

    public function store(
        Request $request,
        JournalEntryDataService $journals,
        AccountDataService $accounts,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $attributes = $this->validateEntry($request, $accounts);
        if ($attributes instanceof JsonResponse) {
            return $attributes;
        }

        try {
            $entry = $journals->create([
                ...$attributes,
                'created_by' => $this->actorSnapshot($request),
            ]);
            $auditLogs->record($this->actor($request), 'created', $entry['journal_number']);
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Journal entry saved as draft.', 'entry' => $entry], 201);
    }

    public function update(
        Request $request,
        string $journalNumber,
        JournalEntryDataService $journals,
        AccountDataService $accounts,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        $attributes = $this->validateEntry($request, $accounts);
        if ($attributes instanceof JsonResponse) {
            return $attributes;
        }

        try {
            $entry = $journals->update($journalNumber, $attributes);
            $auditLogs->record($this->actor($request), 'updated', $journalNumber);
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Draft journal entry updated.', 'entry' => $entry]);
    }

    public function destroy(
        Request $request,
        string $journalNumber,
        JournalEntryDataService $journals,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        try {
            $journals->delete($journalNumber);
            $auditLogs->record($this->actor($request), 'deleted_draft', $journalNumber);
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Draft journal entry deleted.']);
    }

    public function submitForReview(
        Request $request,
        string $journalNumber,
        JournalEntryDataService $journals,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        try {
            $entry = $journals->find($journalNumber);
            if (! $this->isBalanced($entry)) {
                return response()->json(['message' => 'Balance the journal entry before submitting it for review.'], 422);
            }

            $entry = $journals->submitForReview($journalNumber);
            $auditLogs->record($this->actor($request), 'submitted_for_review', $journalNumber);
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Journal entry submitted for review.', 'entry' => $entry]);
    }

    public function returnToDraft(
        Request $request,
        string $journalNumber,
        JournalEntryDataService $journals,
        AuditLogDataService $auditLogs,
    ): JsonResponse {
        if ($response = $this->denyApproval($request)) {
            return $response;
        }

        try {
            $entry = $journals->returnToDraft($journalNumber);
            $auditLogs->record($this->actor($request), 'returned_to_draft', $journalNumber);
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Journal entry returned to draft.', 'entry' => $entry]);
    }

    public function post(
        Request $request,
        string $journalNumber,
        AccountingPostingService $posting,
    ): JsonResponse {
        if ($response = $this->denyApproval($request)) {
            return $response;
        }

        try {
            $entry = $posting->postManual($journalNumber, $this->actor($request));
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json(['message' => 'Journal entry posted successfully.', 'entry' => $entry]);
    }

    public function reverse(
        Request $request,
        string $journalNumber,
        AccountingPostingService $posting,
    ): JsonResponse {
        if ($response = $this->denyApproval($request)) {
            return $response;
        }

        try {
            $result = $posting->reverse($journalNumber, $this->actor($request));
        } catch (RuntimeException $exception) {
            return $this->persistenceError($exception);
        }

        return response()->json([
            'message' => 'Journal entry reversed. Offsetting entry '.$result['reversal']['journal_number'].' created.',
            ...$result,
        ]);
    }

    public function csv(Request $request, JournalEntryDataService $journals, DemoAccessService $access): StreamedResponse|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $entries = $this->filtered($this->visibleEntries($journals->all(), $request, $access), $request);

        return response()->streamDownload(static function () use ($entries): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Journal Number', 'Date', 'Description', 'Reference', 'Source', 'Total Debit', 'Total Credit', 'Prepared By', 'Status']);
            foreach ($entries as $entry) {
                fputcsv($output, [
                    $entry['journal_number'],
                    $entry['date'],
                    $entry['description'],
                    $entry['reference'],
                    $entry['source_type'],
                    number_format((float) $entry['total_debit'], 2, '.', ''),
                    number_format((float) $entry['total_credit'], 2, '.', ''),
                    Arr::get($entry, 'created_by.name', ''),
                    $entry['status'],
                ]);
            }
            fclose($output);
        }, 'journal-entries.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Request $request, string $journalNumber, JournalEntryDataService $journals, DemoAccessService $access): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        try {
            $entry = $journals->find($journalNumber);
            if ($access->isViewer($request) && ! in_array($entry['status'], ['Posted', 'Reversed'], true)) {
                throw new RuntimeException('The journal entry could not be found.');
            }
        } catch (RuntimeException) {
            abort(404, 'The journal entry could not be found.');
        }

        return view('journal-entry-print', ['entry' => $entry]);
    }

    public function pdf(
        Request $request,
        JournalEntryDataService $journals,
        AccountingPdfExportService $exports,
        DemoAccessService $access,
    ): Response|RedirectResponse {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $entries = $this->filtered($this->visibleEntries($journals->all(), $request, $access), $request);
        $content = $exports->journalEntries($entries, [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
        ], now());

        return $this->pdfDownload($content, 'journal-entries-'.now()->format('Y-m-d').'.pdf');
    }

    public function entryPdf(
        Request $request,
        string $journalNumber,
        JournalEntryDataService $journals,
        AccountingPdfExportService $exports,
        DemoAccessService $access,
    ): Response|RedirectResponse {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        try {
            $entry = $journals->find($journalNumber);
            if ($access->isViewer($request) && ! in_array($entry['status'], ['Posted', 'Reversed'], true)) {
                throw new RuntimeException('The journal entry could not be found.');
            }
        } catch (RuntimeException) {
            abort(404, 'The journal entry could not be found.');
        }

        return $this->pdfDownload(
            $exports->journalEntry($entry, now()),
            $entry['journal_number'].'.pdf',
        );
    }

    /** @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function visibleEntries(array $entries, Request $request, DemoAccessService $access): array
    {
        if (! $access->isViewer($request)) {
            return $entries;
        }

        return array_values(array_filter($entries, static fn (array $entry): bool => in_array($entry['status'], ['Posted', 'Reversed'], true)));
    }

    /** @return array<string, mixed>|JsonResponse */
    private function validateEntry(Request $request, AccountDataService $accounts): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::in(['Manual', 'Invoice', 'Payment', 'Bill', 'Expense'])],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string'],
            'lines.*.description' => ['nullable', 'string', 'max:150'],
            'lines.*.party_reference' => ['nullable', 'string', 'max:100'],
            'lines.*.cost_center' => ['nullable', 'string', 'max:100'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validator->after(function ($validator) use ($request, $accounts): void {
            $activeAccounts = collect($accounts->all(['status' => 'Active']))->keyBy('code');
            $debit = 0.0;
            $credit = 0.0;

            foreach ($request->input('lines', []) as $index => $line) {
                $lineDebit = round((float) ($line['debit'] ?? 0), 2);
                $lineCredit = round((float) ($line['credit'] ?? 0), 2);

                if (($lineDebit > 0) === ($lineCredit > 0)) {
                    $validator->errors()->add("lines.{$index}.amount", 'Enter either a debit or credit amount greater than zero.');
                }

                if (! $activeAccounts->has((string) ($line['account_code'] ?? ''))) {
                    $validator->errors()->add("lines.{$index}.account_code", 'Select an active account.');
                }

                $debit += $lineDebit;
                $credit += $lineCredit;
            }

            if ($debit <= 0 || $credit <= 0) {
                $validator->errors()->add('lines', 'Total debit and total credit must both be greater than zero.');
            }
        });

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $accountMap = collect($accounts->all(['status' => 'Active']))->keyBy('code');
        $lines = collect($validated['lines'])->values()->map(function (array $line, int $index) use ($accountMap): array {
            $account = $accountMap->get((string) $line['account_code']);

            return [
                'id' => $index + 1,
                'account_code' => (string) $line['account_code'],
                'account_name' => $account['name'],
                'description' => trim((string) ($line['description'] ?? '')),
                'party_reference' => trim((string) ($line['party_reference'] ?? '')),
                'cost_center' => trim((string) ($line['cost_center'] ?? '')),
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
            ];
        })->all();

        return [
            'date' => $validated['date'],
            'reference' => trim((string) ($validated['reference'] ?? '')),
            'description' => trim($validated['description']),
            'source_type' => $validated['source_type'],
            'lines' => $lines,
            'total_debit' => round((float) collect($lines)->sum('debit'), 2),
            'total_credit' => round((float) collect($lines)->sum('credit'), 2),
        ];
    }

    private function denyMutation(Request $request): ?JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }

        if ($request->session()->get('demo_user.role') === 'Viewer / Auditor') {
            return response()->json(['message' => 'This demo role has read-only access.'], 403);
        }

        return null;
    }

    private function denyApproval(Request $request): ?JsonResponse
    {
        if ($response = $this->denyMutation($request)) {
            return $response;
        }

        if (! in_array($request->session()->get('demo_user.role'), ['Administrator', 'Accountant'], true)) {
            return response()->json(['message' => 'Only Administrators and Accountants can approve, post, or reverse entries.'], 403);
        }

        return null;
    }

    /** @param array<string, mixed> $entry */
    private function isBalanced(array $entry): bool
    {
        return count($entry['lines']) >= 2
            && (float) $entry['total_debit'] > 0
            && abs((float) $entry['total_debit'] - (float) $entry['total_credit']) < 0.005;
    }

    /** @return array<string, mixed> */
    private function actor(Request $request): array
    {
        return (array) $request->session()->get('demo_user', []);
    }

    /** @return array{id: int|string|null, name: string} */
    private function actorSnapshot(Request $request): array
    {
        $actor = $this->actor($request);

        return ['id' => $actor['id'] ?? null, 'name' => (string) ($actor['name'] ?? 'Demo User')];
    }

    /** @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function filtered(array $entries, Request $request): array
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $status = trim((string) $request->query('status', ''));

        return array_values(array_filter($entries, static function (array $entry) use ($search, $status): bool {
            $matchesSearch = $search === '' || str_contains(mb_strtolower(
                $entry['journal_number'].' '.$entry['description'].' '.$entry['reference']
            ), $search);

            return $matchesSearch && ($status === '' || $entry['status'] === $status);
        }));
    }

    /** @param array<string, array<int, string>> $errors */
    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'message' => 'Please correct the journal entry fields.',
            'errors' => $errors,
        ], 422);
    }

    private function persistenceError(RuntimeException $exception): JsonResponse
    {
        $status = $exception->getMessage() === 'The journal entry could not be found.' ? 404 : 409;

        return response()->json(['message' => $exception->getMessage()], $status);
    }

    private function pdfDownload(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($content),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
