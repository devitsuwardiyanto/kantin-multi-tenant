<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Admin\Services\ChangeTenantStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * UC-21 alur 3a: aktif/nonaktifkan tenant (policy `update` = pengelola kantin pemilik tenant).
 */
class AdminTenantStatusController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ChangeTenantStatus $service) {}

    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);
        $data = $request->validate(['status' => ['required', Rule::in(ChangeTenantStatus::ALLOWED)]]);

        $this->service->handle($tenant, $data['status']);

        return redirect()->route('admin.tenants.index')->with('status', $data['status'] === 'active'
            ? "Tenant {$tenant->display_name} diaktifkan."
            : "Tenant {$tenant->display_name} dinonaktifkan dan disembunyikan dari katalog.");
    }
}
