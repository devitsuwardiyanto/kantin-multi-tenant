<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Models\Canteen;
use App\Models\Tenant;
use App\Models\UserCanteenRole;
use App\Modules\Admin\Services\CreateTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminTenantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private CreateTenant $createTenant) {}

    private function canteen(Request $request): Canteen
    {
        $id = UserCanteenRole::query()
            ->where('user_id', $request->user()?->id)
            ->whereIn('role', ['owner', 'manager', 'finance'])
            ->value('canteen_id');
        abort_if($id === null, 403, 'Anda tidak mengelola kantin mana pun.');

        return Canteen::query()->findOrFail((int) $id);
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Tenant::class);
        $canteen = $this->canteen($request);
        $tenants = Tenant::query()->where('canteen_id', $canteen->id)->orderBy('display_name')->get();

        return view('admin.tenants.index', compact('canteen', 'tenants'));
    }

    public function create(): View
    {
        $this->authorize('create', Tenant::class);

        return view('admin.tenants.create');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $canteen = $this->canteen($request);
        $data = $request->validated();
        $tenant = $this->createTenant->handle($canteen, [
            'code' => $data['code'],
            'slug' => $data['slug'],
            'display_name' => $data['display_name'],
            'commission_rate' => (float) $data['commission_rate'] / 100, // persen -> fraksi
        ]);

        return redirect()->route('admin.tenants.edit', $tenant)->with('status', 'Tenant dibuat dengan komisi aktif dan balance awal.');
    }

    public function edit(Tenant $tenant): View
    {
        $this->authorize('update', $tenant);
        $tenant->load(['balance']);
        $commissions = $tenant->commissionSchemes()->orderByDesc('valid_from')->get();
        $bankAccounts = $tenant->bankAccounts()->orderByDesc('is_primary')->get();
        $members = $tenant->tenantRoles()->with('user')->get();

        return view('admin.tenants.edit', compact('tenant', 'commissions', 'bankAccounts', 'members'));
    }
}
