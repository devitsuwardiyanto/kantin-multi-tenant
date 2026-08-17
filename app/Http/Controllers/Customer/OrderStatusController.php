<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\Ordering\Services\ResolveTrackedOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Halaman status pesanan untuk pelanggan anonim. Order dikenali lewat cookie pelacakan
 * (opaque, HttpOnly). Tanpa/ salah token → 404 generik (anti-enumeration).
 */
final class OrderStatusController extends Controller
{
    public function __invoke(Request $request, string $canteen, ResolveTrackedOrder $resolver): View
    {
        $order = $resolver->current($request);
        abort_if($order === null, 404);

        $order->load([
            'tenantOrders.tenant:id,display_name',
            'tenantOrders.items',
            'tenantOrders.items.modifiers',
        ]);

        return view('customer.order', ['order' => $order]);
    }
}
