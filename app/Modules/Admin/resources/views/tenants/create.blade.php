<x-layouts.admin title="Buat Tenant">
    <h1 class="text-xl font-semibold">Buat Tenant</h1>
    @if ($errors->any())
        <div class="mt-3 rounded-lg bg-red-100 px-4 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-300">
            <ul class="list-disc ps-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ route('admin.tenants.store') }}" class="mt-6 max-w-md space-y-4">
        @csrf
        <x-input name="display_name" label="Nama tampilan" value="{{ old('display_name') }}" required />
        <x-input name="code" label="Kode (unik per kantin)" value="{{ old('code') }}" required />
        <x-input name="slug" label="Slug" value="{{ old('slug') }}" required />
        <x-input name="commission_rate" label="Komisi (%)" type="number" step="0.01" value="{{ old('commission_rate', '15') }}" required />
        <x-button type="submit">Simpan</x-button>
    </form>
</x-layouts.admin>
