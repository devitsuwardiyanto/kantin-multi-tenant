<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\Auth\LoginLockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * UC-19: verifikasi kredensial + status akun, pesan kesalahan generik, dan penguncian akun
     * (5x gagal/10 menit -> terkunci 15 menit). Akun nonaktif ditolak dengan pesan yang sama
     * agar keberadaan akun tidak terbocorkan.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): User {
            $lockout = app(LoginLockout::class);
            $email = (string) $request->input(Fortify::username());

            if ($lockout->isLocked($email)) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'Akun terkunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam '.$lockout->minutesRemaining($email).' menit.',
                ]);
            }

            $user = User::query()->where('email', $email)->first();

            if ($user !== null && $user->isActive() && Hash::check((string) $request->input('password'), $user->password)) {
                $lockout->clear($email);

                return $user;
            }

            $lockout->recordFailure($email);

            throw ValidationException::withMessages([
                Fortify::username() => 'Surel atau kata sandi tidak sesuai.',
            ]);
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            // Lapis per-IP terhadap brute force massal; penguncian per akun ditangani LoginLockout.
            return Limit::perMinute(20)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
