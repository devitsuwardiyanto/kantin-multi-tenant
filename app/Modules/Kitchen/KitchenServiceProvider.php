<?php

namespace App\Modules\Kitchen;

use App\Models\User;
use App\Modules\Kitchen\Realtime\TenantChannels;
use App\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Broadcast;

/**
 * Modul: Kitchen (alias `kitchen`).
 * Tanggung jawab: Kitchen Display System realtime, state machine order, notifikasi (Modul 12).
 *
 * Titik perakitan modul: binding container di register(); route (routes/*.php), view
 * (`kitchen::`) dan komponen Livewire (`<livewire:kitchen::...>`) dimuat oleh
 * ModuleServiceProvider::boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class KitchenServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Binding kontrak -> implementasi ditambahkan saat modul diimplementasikan.
    }

    public function boot(): void
    {
        parent::boot();

        // Antrean dapur per tenant (privat): hanya anggota tenant yang boleh mendengarkan.
        // Didaftarkan di provider (bukan routes/*.php modul) agar tetap aktif saat route:cache.
        Broadcast::channel(TenantChannels::ORDERS_PATTERN, fn (User $user, int $tenantId): bool => TenantChannels::canAccessOrders($user, $tenantId));
    }

    protected function moduleAlias(): string
    {
        return 'kitchen';
    }
}
