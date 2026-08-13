<?php

namespace Tests\Feature;

use App\Services\DemoAccessService;
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
        $administrator->assertSee('Users &amp; Settings', false)->assertSee('Phase 2—not implemented');
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(string $role): array
    {
        return ['demo_user' => ['id' => 1, 'name' => 'Role Tester', 'email' => 'role@example.test', 'role' => $role]];
    }
}
