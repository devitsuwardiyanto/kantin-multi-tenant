<x-layouts::auth title="Masuk">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Masuk" description="Gunakan akun tenant atau pengelola Anda." />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                label="Surel"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="nama@kantin.test"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    label="Kata sandi"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Kata sandi"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        Lupa kata sandi?
                    </flux:link>
                @endif
            </div>

            <!-- Kebijakan UC-19 ditampilkan agar pengguna tahu konsekuensi percobaan gagal. -->
            <p class="text-xs text-zinc-600 dark:text-zinc-400" data-test="lockout-policy">
                5× gagal dalam 10 menit → akun terkunci 15 menit. Sesi berakhir setelah 8 jam tidak aktif.
            </p>

            <!-- Remember Me -->
            <flux:checkbox name="remember" label="Ingat saya" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    Masuk →
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
