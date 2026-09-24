<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\Tenant;
use App\Models\UserCanteenRole;
use App\Modules\Admin\Http\Requests\StoreTenantRequest;
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
        // UC-21 langkah 2: daftar tenant + penanggung jawab, rekening utama, dan komisi yang AKTIF sekarang.
        $tenants = Tenant::query()
            ->where('canteen_id', $canteen->id)
            ->with([
                'tenantRoles' => fn ($query) => $query->where('role', 'owner')->with('user'),
                'bankAccounts' => fn ($query) => $query->where('is_primary', true),
                'commissionSchemes' => fn ($query) => $query->withoutGlobalScope('tenant')->effectiveAt(now()),
            ])
            ->orderBy('display_name')
            ->get();

        return view('admin::tenants.index', compact('canteen', 'tenants'));
    }

    public function create(): View
    {
        $this->authorize('create', Tenant::class);

        return view('admin::tenants.create');
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
            'pic_name' => $data['pic_name'],
            'pic_email' => $data['pic_email'],
            'bank_code' => $data['bank_code'],
            'account_holder' => $data['account_holder'],
            'account_number' => $data['account_number'],
        ]);

        return redirect()->route('admin.tenants.edit', $tenant)->with('status', "Tenant dibuat. Tautan atur kata sandi dikirim ke {$data['pic_email']}.");
    }

    public function edit(Tenant $tenant): View
    {
        $this->authorize('update', $tenant);
        $tenant->load(['balance']);
        $commissions = $tenant->commissionSchemes()->orderByDesc('valid_from')->get();
        $activeCommission = CommissionScheme::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)->effectiveAt(now())->first();
        // UC-21 langkah 6: riwayat versi komisi beserta identitas pengelola (dari audit append-only).
        $commissionActors = AuditLog::query()
            ->where('entity', 'commission_scheme')
            ->whereIn('entity_id', $commissions->pluck('id')->map(fn (int $id): string => (string) $id))
            ->with('actor')
            ->get()
            ->mapWithKeys(fn (AuditLog $log): array => [(int) $log->entity_id => $log->actor?->name]);
        $bankAccounts = $tenant->bankAccounts()->orderByDesc('is_primary')->get();
        $members = $tenant->tenantRoles()->with('user')->get();

        return view('admin::tenants.edit', compact('tenant', 'commissions', 'activeCommission', 'commissionActors', 'bankAccounts', 'members'));
    }
}
