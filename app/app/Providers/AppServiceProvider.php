<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureHttps();
        $this->configureRateLimiting();
    }

    /**
     * Force HTTPS di production supaya semua URL dan asset dihasilkan
     * dengan scheme https. Reverse proxy seperti Nginx atau Cloudflare
     * biasanya sudah handle TLS, tapi Laravel harus tahu supaya cookie
     * secure dan link tidak downgrade ke http.
     */
    private function configureHttps(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            request()?->server->set('HTTPS', 'on');
        }
    }

    /**
     * Batasi percobaan login: 10 percobaan per 5 menit per kombinasi
     * IP dan email/login. Cukup longgar untuk kantor bersama tapi
     * tetap mencegah brute force dari 1 IP+kredensial yang sama.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $identifier = Str::lower((string) ($request->input('email') ?? $request->input('login') ?? ''));
            $key = $request->ip().'|'.$identifier;

            return Limit::perMinutes(5, 10)->by($key)->response(function () {
                return back()->withErrors([
                    'login' => 'Terlalu banyak percobaan login. Coba lagi dalam beberapa menit.',
                    'email' => 'Terlalu banyak percobaan login. Coba lagi dalam beberapa menit.',
                ]);
            });
        });

        /*
         * Batasi unggahan berkas: 10 per menit per akun.
         *
         * Setiap unggahan menyisakan objek permanen di object storage, jadi
         * tanpa batas ini satu akun bisa membanjiri bucket. Di-key ke akun
         * yang login bila ada, kalau tidak ke alamat IP.
         */
        RateLimiter::for('uploads', function (Request $request) {
            $actor = Auth::guard('employee')->id()
                ?? Auth::guard('web')->id()
                ?? $request->ip();

            return Limit::perMinute(10)->by('uploads|'.$actor)->response(function () {
                return back()->withErrors([
                    'dokumen' => 'Terlalu banyak unggahan. Coba lagi sebentar lagi.',
                ]);
            });
        });
    }
}
