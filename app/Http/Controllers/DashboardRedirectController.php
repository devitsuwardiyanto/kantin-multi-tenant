<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\UserTenantRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * UC-19 langkah 5: setelah masuk, arahkan pengguna ke dasbor sesuai perannya. Pengelola ke
 * portal admin; operator ke dasbor tenant aktif pertama miliknya (TenantContext dibentuk oleh
 * middleware `tenant` di sana). Pengguna tanpa peran internal melihat dasbor umum.
 */
class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user?->isTenantOperator()) {
            $tenantSlug = Tenant::query()
                ->whereIn('id', UserTenantRole::query()->where('user_id', $user->id)->select('tenant_id'))
                ->where('status', 'active')
                ->orderBy('id')
                ->value('slug');

            if ($tenantSlug !== null) {
                return redirect()->route('tenant.dashboard', ['tenant' => $tenantSlug]);
            }
        }

        return view('dashboard');
    }
}
