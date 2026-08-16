<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBankAccountRequest;
use App\Models\Tenant;
use App\Models\TenantBankAccount;
use App\Modules\Admin\Services\ManageBankAccount;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminBankAccountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ManageBankAccount $service) {}

    public function store(StoreBankAccountRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->store($tenant, $request->validated());

        return redirect()->route('admin.tenants.edit', $tenant)->with('status', 'Rekening ditambahkan (menunggu verifikasi).');
    }

    public function verify(Request $request, Tenant $tenant, TenantBankAccount $account): RedirectResponse
    {
        $this->authorize('manageBank', $tenant);
        abort_unless($account->tenant_id === $tenant->id, 404);
        $this->service->verify($account, $request->boolean('approve'));

        return redirect()->route('admin.tenants.edit', $tenant)->with('status', 'Status rekening diperbarui.');
    }

    public function makePrimary(Tenant $tenant, TenantBankAccount $account): RedirectResponse
    {
        $this->authorize('manageBank', $tenant);
        abort_unless($account->tenant_id === $tenant->id, 404);
        $this->service->makePrimary($account);

        return redirect()->route('admin.tenants.edit', $tenant)->with('status', 'Rekening primary diperbarui.');
    }
}
