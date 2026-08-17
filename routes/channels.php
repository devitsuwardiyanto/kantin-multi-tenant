<?php

use App\Models\User;
use App\Support\Realtime\TenantChannels;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Antrean dapur per tenant (privat): hanya anggota tenant yang boleh mendengarkan.
Broadcast::channel('tenant.{tenantId}.orders', function (User $user, int $tenantId): bool {
    return TenantChannels::canAccessOrders($user, $tenantId);
});
