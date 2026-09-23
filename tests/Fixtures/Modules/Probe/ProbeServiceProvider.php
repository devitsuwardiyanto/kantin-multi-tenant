<?php

namespace Tests\Fixtures\Modules\Probe;

use App\Modules\ModuleServiceProvider;

/**
 * Modul tiruan untuk menguji konvensi vertical slice (route/view/Livewire milik modul)
 * sebelum modul nyata memiliki fitur.
 */
final class ProbeServiceProvider extends ModuleServiceProvider
{
    protected function moduleAlias(): string
    {
        return 'probe';
    }
}
