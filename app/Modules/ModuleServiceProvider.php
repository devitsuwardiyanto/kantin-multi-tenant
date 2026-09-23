<?php

namespace App\Modules;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use ReflectionClass;

/**
 * Basis provider modul (modular monolith, vertical slice). Setiap modul MEMILIKI file fiturnya
 * sendiri di dalam folder modul, dan provider inilah yang merakitnya:
 *
 *  - routes/{portal}.php       → dimuat otomatis; isinya wajib dibungkus PortalRoutes
 *                                 (middleware portal tetap terpusat, tak bisa terlupa)
 *  - resources/views           → view('{alias}::...')
 *  - resources/views/livewire  → <livewire:{alias}::nama-komponen />
 *  - Http/Controllers, Services, Data, Actions → kelas PHP biasa (PSR-4 App\Modules\...)
 *
 * Yang TIDAK dipindah ke modul (shared kernel): model Eloquent (satu database dipakai lintas
 * modul), layout/komponen UI, middleware, dan dashboard portal.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Alias modul untuk namespace view & Livewire, mis. 'catalog'.
     */
    abstract protected function moduleAlias(): string;

    public function boot(): void
    {
        $path = $this->modulePath();

        $this->loadViewsFrom($path.'/resources/views', $this->moduleAlias());

        Livewire::addNamespace(
            namespace: $this->moduleAlias(),
            viewPath: $path.'/resources/views/livewire',
        );

        foreach (glob($path.'/routes/*.php') ?: [] as $routeFile) {
            $this->loadRoutesFrom($routeFile);
        }
    }

    /**
     * Folder modul = folder tempat kelas provider turunan berada.
     */
    protected function modulePath(): string
    {
        return dirname((string) (new ReflectionClass($this))->getFileName());
    }
}
