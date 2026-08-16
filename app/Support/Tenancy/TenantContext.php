<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use RuntimeException;

/**
 * Menyimpan satu Tenant aktif selama SATU request/job. Didaftarkan sebagai scoped()
 * binding (bukan singleton) di AppServiceProvider agar tidak bocor antar request/job
 * pada worker panjang, Octane, atau Reverb. Dibersihkan pada akhir lifecycle.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    /** Gagal cepat bila context belum diisi (mencegah query lintas tenant tak sengaja). */
    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('TenantContext belum diisi.');
    }

    public function id(): int
    {
        return $this->tenant()->id;
    }

    public function idOrNull(): ?int
    {
        return $this->tenant?->id;
    }
}
