<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint contoh untuk membuktikan isolasi (global scope + scoped binding + policy).
 * UI Livewire penuh menyusul Modul 7; di sini fokus pada pembuktian keamanan.
 */
final class TenantMenuController extends Controller
{
    use AuthorizesRequests;

    public function index(Tenant $tenant): JsonResponse
    {
        // Global scope memfilter ke tenant aktif (TenantContext dari middleware).
        $menus = Menu::query()->orderBy('name')->get(['id', 'name', 'base_price', 'is_available']);

        return response()->json(['tenant' => $tenant->slug, 'menus' => $menus]);
    }

    public function show(Tenant $tenant, Menu $menu): JsonResponse
    {
        $this->authorize('view', $menu);

        return response()->json($menu->only(['id', 'name', 'base_price', 'is_available']));
    }

    public function update(Request $request, Tenant $tenant, Menu $menu): JsonResponse
    {
        $this->authorize('update', $menu);

        $data = $request->validate(['is_available' => ['required', 'boolean']]);
        $menu->update(['is_available' => $data['is_available']]);

        return response()->json(['ok' => true, 'is_available' => $menu->is_available]);
    }
}
