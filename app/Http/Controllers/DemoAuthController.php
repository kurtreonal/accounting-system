<?php

namespace App\Http\Controllers;

use App\Services\DemoData\AccountDataService;
use Illuminate\Http\RedirectResponse;
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
        ]);

        return redirect()->route('dashboard');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('login');
        }

        return view('dashboard', [
            'user' => $request->session()->get('demo_user'),
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
        $path = storage_path('demo-data/users.json');

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
