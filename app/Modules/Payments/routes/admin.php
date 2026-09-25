<?php

use App\Modules\Payments\Http\Controllers\WithdrawalProofController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal admin milik modul Payments: verifikasi pencairan dana tenant (UC-23,
 * <livewire:payments::withdrawal-review>) dan bukti transfer (disk privat).
 */
PortalRoutes::admin(function (): void {
    Route::get('/withdrawals', fn () => view('payments::admin.withdrawals'))->name('withdrawals.index');
    Route::get('/withdrawals/{withdrawal}/proof', WithdrawalProofController::class)->whereNumber('withdrawal')->name('withdrawals.proof');
});
