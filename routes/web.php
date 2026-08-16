<?php

use App\Http\Controllers\Customer\ResolveTableQrController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Entry point pelanggan via QR meja. Token opaque di URL; rate limited; canteen dari token.
Route::get('/q/{token}', ResolveTableQrController::class)
    ->middleware('throttle:qr-scan')
    ->name('customer.scan');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
