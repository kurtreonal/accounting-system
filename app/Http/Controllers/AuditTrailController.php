<?php

namespace App\Http\Controllers;

use App\Services\DemoData\AuditLogDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditTrailController extends Controller
{
    public function index(Request $request, AuditLogDataService $auditLogs): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }
        $allLogs = $this->presentLogs($auditLogs->all());
        $logs = $this->filteredLogs($request, $allLogs);
        $today = now()->toDateString();

        return view('audit-trail', [
            'user' => $request->session()->get('demo_user'),
            'logs' => $logs,
            'resources' => collect($allLogs)->pluck('resource')->filter()->unique()->sort()->values()->all(),
            'actions' => collect($allLogs)->pluck('action')->filter()->unique()->sort()->values()->all(),
            'roles' => collect($allLogs)->pluck('actor_role')->filter()->unique()->sort()->values()->all(),
            'metrics' => [
                'total' => count($allLogs),
                'today' => collect($allLogs)->where('created_date', $today)->count(),
                'users' => collect($allLogs)->pluck('actor_user_id')->filter(fn ($id): bool => $id !== null)->unique()->count(),
                'modules' => collect($allLogs)->pluck('resource')->filter()->unique()->count(),
            ],
        ]);
    }

    public function csv(Request $request, AuditLogDataService $auditLogs): StreamedResponse|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $logs = $this->filteredLogs($request, $this->presentLogs($auditLogs->all()));

        return response()->streamDownload(static function () use ($logs): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Timestamp', 'User', 'Role', 'Action', 'Module', 'Record', 'Before', 'After']);
            foreach ($logs as $log) {
                $before = is_array($log['before']) ? json_encode($log['before'], JSON_UNESCAPED_SLASHES) : ($log['before'] ?? '');
                $after = is_array($log['after']) ? json_encode($log['after'], JSON_UNESCAPED_SLASHES) : ($log['after'] ?? '');
                fputcsv($output, [$log['created_at_display'], $log['actor_name'], $log['actor_role'], $log['action_label'], $log['resource_label'], $log['resource_id'], $before, $after]);
            }
            fclose($output);
        }, 'audit-trail-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param array<int, array<string, mixed>> $logs
     * @return array<int, array<string, mixed>>
     */
    private function presentLogs(array $logs): array
    {
        return array_map(static function (array $log): array {
            $created = Carbon::parse($log['created_at'])->timezone(config('app.timezone'));
            $details = is_array($log['details'] ?? null) ? $log['details'] : [];

            return [
                ...$log,
                'action_label' => Str::headline((string) ($log['action'] ?? 'Event')),
                'resource_label' => Str::headline((string) ($log['resource'] ?? 'System')),
                'created_date' => $created->toDateString(),
                'created_at_display' => $created->format('Y-m-d H:i:s'),
                'before' => $details['before'] ?? null,
                'after' => $details['after'] ?? null,
            ];
        }, $logs);
    }

    /** @param array<int, array<string, mixed>> $logs
     * @return array<int, array<string, mixed>>
     */
    private function filteredLogs(Request $request, array $logs): array
    {
        $filters = $this->filters($request);
        $search = Str::lower($filters['search']);

        return array_values(array_filter($logs, static function (array $log) use ($filters, $search): bool {
            $haystack = Str::lower(implode(' ', [$log['actor_name'] ?? '', $log['actor_role'] ?? '', $log['action_label'] ?? '', $log['resource_label'] ?? '', $log['resource_id'] ?? '']));

            return ($search === '' || str_contains($haystack, $search))
                && ($filters['action'] === '' || ($log['action'] ?? '') === $filters['action'])
                && ($filters['role'] === '' || ($log['actor_role'] ?? '') === $filters['role'])
                && ($filters['resource'] === '' || ($log['resource'] ?? '') === $filters['resource']);
        }));
    }

    /** @return array{search: string, action: string, role: string, resource: string} */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'action' => trim((string) $request->query('action', '')),
            'role' => trim((string) $request->query('role', '')),
            'resource' => trim((string) $request->query('resource', '')),
        ];
    }
}
