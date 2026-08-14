<?php

namespace Tests\Feature;

use App\Services\DemoAccessService;
use App\Services\DemoData\DemoDataResetService;
use Illuminate\Routing\Route;
use Tests\TestCase;

class DemoRoleEnforcementTest extends TestCase
{
    public function test_every_accounting_mutation_route_has_central_permission_middleware(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(static function (Route $route): bool {
            $action = (string) $route->getActionName();
            $methods = $route->methods();

            return str_starts_with($action, 'App\\Http\\Controllers\\')
                && ! str_contains($action, 'DemoAuthController')
                && array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE']) !== [];
        });

        $this->assertNotEmpty($routes);
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertTrue(
                collect($middleware)->contains(static fn (string $item): bool => str_starts_with($item, 'demo.permission:')),
                $route->uri().' is missing centralized permission middleware.',
            );
        }
    }

    public function test_static_permission_matrix_matches_section_two_roles(): void
    {
        $access = app(DemoAccessService::class);
        $matrix = [
            'Administrator' => ['drafts.manage' => true, 'master_data.manage' => true, 'configuration.manage' => true, 'transactions.approve' => true, 'cash_bank.manage' => true, 'tax.manage' => true, 'users.manage' => true, 'audit.view' => true],
            'Accountant' => ['drafts.manage' => true, 'master_data.manage' => true, 'configuration.manage' => true, 'transactions.approve' => true, 'cash_bank.manage' => true, 'tax.manage' => false, 'users.manage' => false, 'audit.view' => true],
            'Encoder / Staff' => ['drafts.manage' => true, 'drafts.submit' => true, 'master_data.manage' => false, 'configuration.manage' => false, 'transactions.approve' => false, 'cash_bank.manage' => false, 'audit.view' => false],
            'Viewer / Auditor' => ['drafts.manage' => false, 'master_data.manage' => false, 'configuration.manage' => false, 'transactions.approve' => false, 'cash_bank.manage' => false, 'reports.view' => true, 'audit.view' => true],
        ];

        foreach ($matrix as $role => $permissions) {
            foreach ($permissions as $permission => $expected) {
                $this->assertSame($expected, $access->allowsRole($role, $permission), "{$role}: {$permission}");
            }
        }
    }

    public function test_role_specific_navigation_and_controls_are_hidden_or_disabled(): void
    {
        $viewer = $this->withSession($this->demoSession('Viewer / Auditor'))->get('/dashboard')->assertOk();
        $viewer->assertDontSee('New Journal Entry')->assertDontSee('Users &amp; Settings', false)->assertSee('Audit Trail');

        $encoder = $this->withSession($this->demoSession('Encoder / Staff'))->get('/accounts-payable')->assertOk();
        $encoder->assertSee('New Bill')->assertDontSee('id="ap-new-vendor"', false)->assertDontSee('Audit Trail');

        $administrator = $this->withSession($this->demoSession('Administrator'))->get('/dashboard')->assertOk();
        $administrator->assertSee('Users &amp; Settings', false)->assertDontSee('Phase 2—not implemented');
        $this->withSession($this->demoSession('Administrator'))->get('/users-settings')->assertOk()->assertSee('Users &amp; Roles', false);
        $this->withSession($this->demoSession('Administrator'))->get('/record-details/settings/all-demo-data')
            ->assertOk()->assertJsonPath('record.title', 'Demo Data Reset');
        $this->withSession($this->demoSession('Accountant'))->get('/users-settings')->assertForbidden();
    }

    public function test_navbar_demo_user_switch_updates_the_session_and_permissions(): void
    {
        $response = $this->withSession($this->demoSession('Administrator'))
            ->post('/demo-user/switch', ['user_id' => 4]);

        $response->assertRedirect('/dashboard')
            ->assertSessionHas('demo_user.id', 4)
            ->assertSessionHas('demo_user.role', 'Viewer / Auditor');

        $dashboard = $this->get('/dashboard')->assertOk();
        $dashboard->assertSee('Demo user access')
            ->assertSee('Auditor')
            ->assertDontSee('New Journal Entry')
            ->assertDontSee('Users &amp; Settings', false);
    }

    public function test_demo_reset_clears_records_but_preserves_zeroed_accounts_and_configuration(): void
    {
        $directory = storage_path('framework/testing/demo-reset-'.uniqid());
        mkdir($directory, 0777, true);
        $paths = [];

        try {
            foreach ((array) config('accounting') as $key => $originalPath) {
                if (! str_ends_with((string) $key, '_path')) continue;
                $paths[$key] = $directory.'/'.$key.'.json';
                $value = match ($key) {
                    'accounts_path' => [['code' => '1', 'name' => 'Cash', 'type' => 'Asset', 'sub_type' => 'Cash', 'balance' => 1250, 'status' => 'Active']],
                    'users_path' => [['id' => 1, 'name' => 'Administrator']],
                    'settings_path' => ['company' => ['name' => 'Demo']],
                    'tax_codes_path' => [['id' => 1, 'code' => 'VAT']],
                    default => [['id' => 1]],
                };
                file_put_contents($paths[$key], json_encode($value, JSON_THROW_ON_ERROR));
                config(["accounting.{$key}" => $paths[$key]]);
            }

            $result = app(DemoDataResetService::class)->reset();

            $this->assertSame(0, json_decode(file_get_contents($paths['accounts_path']), true, flags: JSON_THROW_ON_ERROR)[0]['balance']);
            foreach (['users_path', 'settings_path', 'tax_codes_path'] as $preserved) {
                $this->assertNotEmpty(json_decode(file_get_contents($paths[$preserved]), true, flags: JSON_THROW_ON_ERROR));
            }
            foreach (array_diff(array_keys($paths), ['accounts_path', 'users_path', 'settings_path', 'tax_codes_path']) as $cleared) {
                $this->assertSame([], json_decode(file_get_contents($paths[$cleared]), true, flags: JSON_THROW_ON_ERROR), $cleared);
            }
            $this->assertSame(1, $result['accounts_zeroed']);
        } finally {
            foreach ($paths as $path) if (is_file($path)) unlink($path);
            if (is_dir($directory)) rmdir($directory);
        }
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(string $role): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Role Tester', 'email' => 'role@example.test', 'role' => $role]];
    }
}
