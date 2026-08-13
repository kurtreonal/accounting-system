<?php

namespace App\Services;

use Illuminate\Http\Request;

class DemoAccessService
{
    /** @return array<int, string> */
    public function permissionsForRole(?string $role): array
    {
        $permissions = config('demo_permissions.roles.'.($role ?? ''), []);

        return is_array($permissions) ? array_values(array_map('strval', $permissions)) : [];
    }

    public function allowsRole(?string $role, string $permission): bool
    {
        $permissions = $this->permissionsForRole($role);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function allows(Request $request, string $permission): bool
    {
        return $this->allowsRole($request->session()->get('demo_user.role'), $permission);
    }

    public function isViewer(Request $request): bool
    {
        return $request->session()->get('demo_user.role') === 'Viewer / Auditor';
    }
}
