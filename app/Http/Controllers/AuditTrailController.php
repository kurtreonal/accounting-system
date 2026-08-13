<?php

namespace App\Http\Controllers;

use App\Services\DemoData\AuditLogDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditTrailController extends Controller
{
    public function index(Request $request, AuditLogDataService $auditLogs): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }
        $search = Str::lower(trim((string) $request->query('search', '')));
        $resource = trim((string) $request->query('resource', ''));
        $logs = array_values(array_filter($auditLogs->all(), static function (array $log) use ($search, $resource): bool {
            $haystack = Str::lower(implode(' ', [$log['actor_name'] ?? '', $log['actor_role'] ?? '', $log['action'] ?? '', $log['resource'] ?? '', $log['resource_id'] ?? '']));

            return ($search === '' || str_contains($haystack, $search)) && ($resource === '' || ($log['resource'] ?? '') === $resource);
        }));

        return view('audit-trail', [
            'user' => $request->session()->get('demo_user'),
            'logs' => $logs,
            'resources' => collect($auditLogs->all())->pluck('resource')->filter()->unique()->sort()->values()->all(),
        ]);
    }
}
