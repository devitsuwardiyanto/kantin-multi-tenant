<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserCanteenRole;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UC-23: menampilkan bukti transfer (disk privat) hanya kepada pengelola kantin pemilik tenant.
 */
final class WithdrawalProofController extends Controller
{
    public function __invoke(Request $request, int $withdrawal): StreamedResponse
    {
        $canteenIds = UserCanteenRole::query()->where('user_id', $request->user()?->id)->whereIn('role', ['owner', 'manager', 'finance'])->pluck('canteen_id');

        $record = Withdrawal::query()->withoutGlobalScope('tenant')
            ->whereKey($withdrawal)
            ->whereHas('tenant', fn ($q) => $q->whereIn('canteen_id', $canteenIds))
            ->whereNotNull('transfer_proof_path')
            ->firstOrFail();

        return Storage::disk('local')->response((string) $record->transfer_proof_path);
    }
}
