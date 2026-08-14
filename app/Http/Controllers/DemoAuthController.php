<?php

namespace App\Http\Controllers;

use App\Services\Accounting\DashboardDataService;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\UserDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;
use RuntimeException;

class DemoAuthController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('demo_user')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login', [
            'roles' => $this->roles(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'role' => ['required', 'string'],
        ]);

        $user = collect($this->users())->first(
            fn (array $user): bool => strtolower($user['email']) === strtolower($credentials['email'])
                && $user['role'] === $credentials['role']
                && $user['active'] === true
                && password_verify($credentials['password'], $user['password_hash'])
        );

        if (! $user) {
            return back()
                ->withErrors(['email' => 'The email, password, or selected role is incorrect.'])
                ->onlyInput('email', 'role');
        }

        $request->session()->regenerate();
        $request->session()->put('demo_user', [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'avatar_data_url' => $user['avatar_data_url'] ?? null,
        ]);

        return redirect()->route('dashboard');
    }

    public function switchUser(Request $request, UserDataService $users): RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $user = collect($users->all())->first(
            fn (array $candidate): bool => (int) ($candidate['id'] ?? 0) === (int) $validated['user_id']
                && ($candidate['active'] ?? false) === true
                && in_array($candidate['role'] ?? '', array_keys((array) config('demo_permissions.roles')), true)
        );

        if (! $user) {
            return back()->withErrors(['user_id' => 'That demo account is unavailable.']);
        }

        $request->session()->put('demo_user', [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'avatar_data_url' => $user['avatar_data_url'] ?? null,
        ]);

        return redirect()->route('dashboard')->with('demo_user_switched', "Now demonstrating {$user['role']} access as {$user['name']}.");
    }

    public function updateAvatar(Request $request, UserDataService $users): JsonResponse
    {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }

        $validated = $request->validate([
            'avatar_data_url' => ['nullable', 'string', 'max:200000'],
        ]);
        $avatar = $validated['avatar_data_url'] ?? null;

        if ($avatar !== null) {
            if (! preg_match('/^data:image\/(jpeg|png|webp);base64,([A-Za-z0-9+\/=]+)$/', $avatar, $matches)) {
                return response()->json(['message' => 'The profile picture format is invalid.'], 422);
            }

            $binary = base64_decode($matches[2], true);
            $dimensions = $binary === false ? false : @getimagesizefromstring($binary);
            $expectedMime = 'image/'.$matches[1];
            if ($binary === false || strlen($binary) > 150000 || $dimensions === false || ($dimensions['mime'] ?? '') !== $expectedMime || $dimensions[0] !== 256 || $dimensions[1] !== 256) {
                return response()->json(['message' => 'The profile picture must be a valid 256 × 256 image under 150 KB.'], 422);
            }
        }

        $user = $users->updateAvatar((int) $request->session()->get('demo_user.id'), $avatar);
        $sessionUser = (array) $request->session()->get('demo_user');
        $request->session()->put('demo_user', [...$sessionUser, 'avatar_data_url' => $user['avatar_data_url'] ?? null]);

        return response()->json([
            'message' => $avatar === null ? 'Profile picture removed.' : 'Profile picture updated.',
            'avatar_data_url' => $user['avatar_data_url'] ?? null,
        ]);
    }

    public function dashboard(Request $request, DashboardDataService $dashboard): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        return view('dashboard', [
            'user' => $request->session()->get('demo_user'),
            'dashboard' => $dashboard->summary(),
        ]);
    }

    public function chartOfAccounts(Request $request, AccountDataService $accounts): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        $allAccounts = $accounts->all();

        return view('chart-of-accounts', [
            'accounts' => array_slice($allAccounts, 0, 10),
            'accountDataset' => $allAccounts,
            'accountSummaries' => collect(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'])->mapWithKeys(
                fn (string $type): array => [$type => [
                    'balance' => collect($allAccounts)->where('type', $type)->sum('balance'),
                    'count' => collect($allAccounts)->where('type', $type)->count(),
                ]]
            ),
            'totalAccounts' => count($allAccounts),
        ]);
    }

    public function journalEntries(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        return view('journal-entries');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('demo_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** @return array<int, array<string, mixed>> */
    private function users(): array
    {
        $path = (string) config('accounting.users_path', storage_path('demo-data/users.json'));

        if (! is_file($path)) {
            throw new RuntimeException('The demo users JSON file is missing.');
        }

        try {
            $users = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The demo users JSON file is invalid.', previous: $exception);
        }

        if (! is_array($users)) {
            throw new RuntimeException('The demo users JSON file must contain an array.');
        }

        return $users;
    }

    /** @return array<int, string> */
    private function roles(): array
    {
        return collect($this->users())
            ->where('active', true)
            ->pluck('role')
            ->unique()
            ->values()
            ->all();
    }
}
