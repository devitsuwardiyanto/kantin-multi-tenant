<?php

namespace App\Modules\Ordering\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ordering\Realtime\CustomerChannelUser;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * Endpoint otorisasi channel privat untuk pelanggan anonim (UC-09 langkah 3). Sesi pelanggan
 * dibaca dari cookie tepercaya lalu dipasang sebagai "pengguna" broadcast; callback channel
 * `order.{publicId}` memeriksa kepemilikan pesanan + token pelacakan. Tanpa sesi → 403.
 */
final class CustomerBroadcastAuthController extends Controller
{
    public function __invoke(Request $request, ResolveCustomerSession $sessions): mixed
    {
        $session = $sessions->current($request);
        abort_if($session === null, 403);

        $request->setUserResolver(fn (): CustomerChannelUser => new CustomerChannelUser((string) $session->id));

        return Broadcast::auth($request);
    }
}
