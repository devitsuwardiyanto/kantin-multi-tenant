<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Admin\Services\AssignTenantRole;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminTenantRoleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private AssignTenantRole $service) {}

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('assignRole', $tenant);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:owner,operator,cashier'],
        ]);
        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $this->service->assign($tenant, $user, $data['role']);

        return redirect()->route('admin.tenants.edit', $tenant)->with('status', 'Role ditugaskan.');
    }

    public function destroy(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        $this->authorize('assignRole', $tenant);
        $data = $request->validate(['role' => ['required', 'in:owner,operator,cashier']]);
        try {
            $this->service->remove($tenant, $user, $data['role']);
        } catch (DomainException $e) {
            return redirect()->route('admin.tenants.edit', $tenant)->withErrors(['role' => $e->getMessage()]);
        }

        return redirect()->route('admin.tenants.edit', $tenant)->with('status', 'Role dicabut.');
    }
}
