<?php

namespace App\Modules\Ordering\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\TenantOpeningHours;
use App\Modules\Ordering\Services\ResolveTableScan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint publik pemindaian QR: /q/{token} (rate limited). Membuat sesi anonim,
 * memasang cookie aman, lalu redirect ke katalog canteen yang ditemukan (bukan input klien).
 * Token invalid/expired/revoked → 404 generik dengan saran menghubungi petugas (UC-02 alur 3a);
 * kantin di luar jam operasional → halaman jam buka tanpa membuat sesi (alur 3b).
 */
final class ResolveTableQrController extends Controller
{
    public function __construct(private ResolveTableScan $resolver, private TenantOpeningHours $hours) {}

    public function __invoke(Request $request, string $token): RedirectResponse|Response
    {
        $result = $this->resolver->resolve($token);

        if ($result === null) {
            return response()->view('ordering::scan-invalid', [], 404);
        }

        if ($result['status'] === 'closed') {
            $tenants = $this->hours->activeTenants($result['canteen']);

            return response()->view('ordering::canteen-closed', [
                'canteen' => $result['canteen'],
                'table' => $result['table'],
                'tenants' => $tenants,
                'hours' => $this->hours,
            ]);
        }

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
            ->route('customer.welcome', ['canteen' => $result['canteen']->slug])
            ->withCookie($cookie);
    }
}
