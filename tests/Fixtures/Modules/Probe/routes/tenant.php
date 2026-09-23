<?php

use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

PortalRoutes::tenant(function (): void {
    Route::view('/probe', 'probe::page')->name('probe');
});
