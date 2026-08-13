<?php

namespace App\Providers;

use App\Services\DemoAccessService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', static function ($view): void {
            $access = app(DemoAccessService::class);
            $permissions = $access->permissionsForRole(session('demo_user.role'));
            $view->with('demoPermissions', $permissions);
            $view->with('demoCan', static fn (string $permission): bool => in_array('*', $permissions, true) || in_array($permission, $permissions, true));
        });
    }
}
