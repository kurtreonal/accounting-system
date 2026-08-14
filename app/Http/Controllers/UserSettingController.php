<?php

namespace App\Http\Controllers;

use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\DemoDataResetService;
use App\Services\DemoData\SettingDataService;
use App\Services\DemoData\UserDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class UserSettingController extends Controller
{
    public function index(Request $request, UserDataService $users, SettingDataService $settings, AuditLogDataService $audit): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) return redirect()->route('login');
        $rows = $users->all();
        $roles = config('demo_permissions.roles', []);
        $activity = collect($audit->all())->filter(static fn (array $row): bool => in_array($row['resource'] ?? '', ['user', 'settings'], true))->take(30)->values()->all();

        return view('users-settings', [
            'users' => array_map(fn (array $user): array => $this->safe($user), $rows),
            'roles' => $roles,
            'settings' => $settings->all(),
            'activity' => $activity,
            'metrics' => [
                'total' => count($rows),
                'active' => collect($rows)->where('active', true)->count(),
                'roles' => collect($rows)->pluck('role')->unique()->count(),
                'inactive' => collect($rows)->where('active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request, UserDataService $users, AuditLogDataService $audit): JsonResponse
    {
        $validator = validator($request->all(), $this->userRules(true));
        if ($validator->fails()) return $this->invalid($validator->errors()->toArray());
        try {
            $user = $users->create($validator->validated());
            $audit->record($this->actor($request), 'created', (string) $user['id'], ['before' => 'No previous user record', 'after' => 'User account created'], 'user');
            return response()->json(['message' => 'Demo user created.', 'user' => $this->safe($user)], 201);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function update(Request $request, int $id, UserDataService $users, AuditLogDataService $audit): JsonResponse
    {
        $validator = validator($request->all(), $this->userRules(false));
        if ($validator->fails()) return $this->invalid($validator->errors()->toArray());
        try {
            $before = $users->find($id);
            if ((int) $request->session()->get('demo_user.id') === $id && $validator->validated()['role'] !== $before['role']) {
                return response()->json(['message' => 'Current administrator cannot change their own role.'], 409);
            }
            $user = $users->update($id, $validator->validated());
            if ((int) $request->session()->get('demo_user.id') === $id) {
                $sessionUser = (array) $request->session()->get('demo_user');
                $request->session()->put('demo_user', [...$sessionUser, 'name' => $user['name'], 'email' => $user['email']]);
            }
            $audit->record($this->actor($request), 'updated', (string) $id, [
                'before' => 'Previous user profile',
                'after' => 'User profile updated',
            ], 'user');
            return response()->json(['message' => 'Demo user updated.', 'user' => $this->safe($user)]);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function status(Request $request, int $id, UserDataService $users, AuditLogDataService $audit): JsonResponse
    {
        $validator = validator($request->all(), ['active' => ['required', 'boolean']]);
        if ($validator->fails()) return $this->invalid($validator->errors()->toArray());
        if ((int) $request->session()->get('demo_user.id') === $id && ! $validator->validated()['active']) {
            return response()->json(['message' => 'Current administrator cannot deactivate their own account.'], 409);
        }
        try {
            $before = $users->find($id);
            $user = $users->setActive($id, $validator->validated()['active']);
            $audit->record($this->actor($request), $user['active'] ? 'activated' : 'deactivated', (string) $id, ['before' => $before['active'] ? 'Active' : 'Inactive', 'after' => $user['active'] ? 'Active' : 'Inactive'], 'user');
            return response()->json(['message' => $user['active'] ? 'Demo user activated.' : 'Demo user deactivated.', 'user' => $this->safe($user)]);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function password(Request $request, int $id, UserDataService $users, AuditLogDataService $audit): JsonResponse
    {
        $validator = validator($request->all(), ['password' => ['required', 'string', 'min:8', 'confirmed']]);
        if ($validator->fails()) return $this->invalid($validator->errors()->toArray());
        try {
            $user = $users->find($id);
            $users->resetPassword($id, $validator->validated()['password']);
            $audit->record($this->actor($request), 'password_reset', (string) $id, ['before' => 'Protected', 'after' => 'Updated', 'user_name' => $user['name']], 'user');
            return response()->json(['message' => 'Demo password reset.']);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function company(Request $request, SettingDataService $settings, AuditLogDataService $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'legal_name' => ['required', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:240'],
        ]);
        try {
            $saved = $settings->update('company', $data);
            $audit->record($this->actor($request), 'updated_company', 'company', ['before' => 'Previous company settings', 'after' => 'Company settings updated'], 'settings');
            return response()->json(['message' => 'Company settings saved.', 'settings' => $saved['company']]);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function system(Request $request, SettingDataService $settings, AuditLogDataService $audit): JsonResponse
    {
        $data = $request->validate([
            'fiscal_year_start' => ['required', 'integer', 'between:1,12'], 'currency' => ['required', Rule::in(['PHP', 'USD'])],
            'date_format' => ['required', Rule::in(['Y-m-d', 'm/d/Y', 'd/m/Y'])],
            'journal_prefix' => ['required', 'alpha_num', 'max:8'], 'invoice_prefix' => ['required', 'alpha_num', 'max:8'],
            'bill_prefix' => ['required', 'alpha_num', 'max:8'], 'timezone' => ['required', Rule::in(['Asia/Manila', 'UTC'])],
        ]);
        try {
            $saved = $settings->update('system', $data);
            $audit->record($this->actor($request), 'updated_system', 'system', ['before' => 'Previous system preferences', 'after' => 'System preferences updated'], 'settings');
            return response()->json(['message' => 'System settings saved.', 'settings' => $saved['system']]);
        } catch (RuntimeException $e) { return $this->problem($e); }
    }

    public function prepareReset(Request $request): JsonResponse
    {
        $data = $request->validate(['confirmation' => ['required', Rule::in(['RESET DEMO DATA'])]]);
        $token = Str::random(48);
        $request->session()->put('demo_reset', [
            'token_hash' => hash('sha256', $token),
            'not_before' => now()->addSeconds(5)->getTimestamp(),
            'expires_at' => now()->addMinutes(2)->getTimestamp(),
        ]);

        return response()->json([
            'message' => 'Reset validated. Keep this page open during countdown.',
            'token' => $token,
            'wait_seconds' => 5,
        ]);
    }

    public function resetDemoData(Request $request, DemoDataResetService $reset, AuditLogDataService $audit): JsonResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', Rule::in(['RESET DEMO DATA'])],
            'token' => ['required', 'string', 'size:48'],
        ]);
        $challenge = (array) $request->session()->pull('demo_reset', []);
        if ($challenge === [] || ! hash_equals((string) ($challenge['token_hash'] ?? ''), hash('sha256', $data['token']))) {
            return response()->json(['message' => 'Reset validation expired. Start the five-second countdown again.'], 409);
        }
        if (now()->getTimestamp() < (int) ($challenge['not_before'] ?? PHP_INT_MAX)) {
            return response()->json(['message' => 'Five-second safety timer has not finished.'], 425);
        }
        if (now()->getTimestamp() > (int) ($challenge['expires_at'] ?? 0)) {
            return response()->json(['message' => 'Reset validation expired. Start again.'], 409);
        }

        try {
            $result = $reset->reset(function (array $result) use ($audit, $request): void {
                $audit->record($this->actor($request), 'reset_demo_data', 'all-demo-data', [
                    'before' => 'Demo records present',
                    'after' => 'Records cleared; accounts zeroed',
                    'files_reset' => $result['files_reset'],
                    'accounts_zeroed' => $result['accounts_zeroed'],
                ], 'settings');
            });
            return response()->json(['message' => 'Demo records deleted and account balances reset to zero. Reloading application.']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }

    public function export(UserDataService $users, SettingDataService $settings): Response
    {
        $safeUsers = array_map(fn (array $user): array => $this->safe($user), $users->all());
        $json = json_encode(['exported_at' => now()->toIso8601String(), 'users' => $safeUsers, 'roles' => config('demo_permissions.roles'), 'settings' => $settings->all()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return response($json, 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="users-settings.json"', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, array<int, mixed>> */
    private function userRules(bool $create): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:120'],
            'role' => ['required', Rule::in(array_keys(config('demo_permissions.roles', [])))],
            'department' => ['required', 'string', 'max:80'], 'position' => ['required', 'string', 'max:100'],
            'employment_type' => ['required', Rule::in(['Regular', 'Probationary', 'Contract'])],
            ...($create ? ['password' => ['required', 'string', 'min:8', 'confirmed']] : []),
        ];
    }

    /** @param array<string, array<int, string>> $errors */
    private function invalid(array $errors): JsonResponse { return response()->json(['message' => 'Please correct user fields.', 'errors' => $errors], 422); }
    private function problem(RuntimeException $e): JsonResponse { return response()->json(['message' => $e->getMessage()], 409); }
    /** @return array<string, mixed> */
    private function actor(Request $request): array { return (array) $request->session()->get('demo_user', []); }
    /** @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function safe(array $user): array { unset($user['password_hash'], $user['password']); return $user; }
}
