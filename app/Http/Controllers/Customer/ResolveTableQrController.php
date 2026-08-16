<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\Ordering\Services\ResolveTableScan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Endpoint publik pemindaian QR: /q/{token} (rate limited). Membuat sesi anonim,
 * memasang cookie aman, lalu redirect ke katalog canteen yang ditemukan (bukan input klien).
 * Token invalid/expired/revoked → 404 generik.
 */
final class ResolveTableQrController extends Controller
{
    public function __construct(private ResolveTableScan $resolver) {}

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $result = $this->resolver->resolve($token);
        abort_if($result === null, 404);

        $cookie = cookie(
            name: 'customer_session',
            value: $result['plain'],
            minutes: 240,
            path: '/',
            domain: null,
            secure: $request->isSecure() || app()->isProduction(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );

        return redirect()
            ->route('customer.home', ['canteen' => $result['canteen']->slug])
            ->withCookie($cookie);
    }
}
