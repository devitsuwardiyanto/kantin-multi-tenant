<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Admin\Http\Requests\ScheduleCommissionRequest;
use App\Modules\Admin\Services\ChangeCommissionSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

final class AdminCommissionController extends Controller
{
    public function __construct(private ChangeCommissionSchedule $service) {}

    public function store(ScheduleCommissionRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validated();
        $this->service->handle($tenant, (float) $data['commission_rate'] / 100, Carbon::parse($data['effective_at']));

        return redirect()->route('admin.tenants.edit', $tenant)->with('status', 'Skema komisi dijadwalkan.');
    }
}
