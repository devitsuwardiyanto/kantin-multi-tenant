<x-layouts.admin title="Dashboard">
    <h1 class="text-xl font-semibold">Dashboard Pengelola</h1>
    <div class="mt-6 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead class="bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                <tr><th class="px-4 py-2">Tenant</th><th class="px-4 py-2">Operator</th><th class="px-4 py-2">Status</th></tr>
            </thead>
            <tbody>
                <tr class="border-t border-zinc-200 dark:border-zinc-800">
                    <td class="px-4 py-3" colspan="3">
                        <x-empty-state title="Belum ada tenant" description="Administrasi tenant diisi Modul 5." />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</x-layouts.admin>
