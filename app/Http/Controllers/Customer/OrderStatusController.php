<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Halaman status pesanan untuk pelanggan anonim. Order dikenali lewat cookie pelacakan
 * (opaque, HttpOnly) — di-hash lalu dicari. Tanpa/ salah token → 404 generik (anti-enumeration).
 */
final class OrderStatusController extends Controller
{
    public function __invoke(Request $request, string $canteen): View
    {
        $token = $request->cookie('order_tracking');
        abort_if(! is_string($token) || $token === '', 404);

        $order = Order::query()
            ->where('tracking_token_hash', OpaqueToken::hash($token))
            ->with([
                'tenantOrders.tenant:id,display_name',
                'tenantOrders.items',
                'tenantOrders.items.modifiers',
            ])
            ->first();

        abort_if($order === null, 404);

        return view('customer.order', ['order' => $order]);
    }
}
