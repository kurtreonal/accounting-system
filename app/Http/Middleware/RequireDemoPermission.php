<?php

namespace App\Http\Middleware;

use App\Services\DemoAccessService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireDemoPermission
{
    public function __construct(private readonly DemoAccessService $access) {}

    public function handle(Request $request, Closure $next, ?string $permission = null): Response|JsonResponse|RedirectResponse
    {
        if (! $request->session()->has('demo_user')) {
            if ($request->expectsJson() || ! $request->isMethod('GET')) {
                return response()->json(['message' => 'Authentication is required.'], 401);
            }

            return redirect()->route('login');
        }

        if ($permission !== null && ! $this->access->allows($request, $permission)) {
            if ($request->expectsJson() || ! $request->isMethod('GET')) {
                return response()->json(['message' => 'This demo role is not allowed to perform this action.'], 403);
            }

            abort(403, 'This demo role is not allowed to view this page.');
        }

        return $next($request);
    }
}
