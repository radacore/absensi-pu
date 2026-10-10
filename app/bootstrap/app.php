<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Header keamanan untuk semua respons (nosniff, anti-clickjacking, dll).
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureAdminRole::class,
            'employee' => \App\Http\Middleware\EnsureEmployeeAuth::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Error dirender lewat Inertia supaya kunjungan SPA tidak berakhir
        // dengan modal "invalid Inertia response" dari klien. Status tetap
        // dipertahankan (403 tetap 403, dst) — hanya tampilannya yang berubah.
        $exceptions->respond(function (Response $response, \Throwable $e): Response {
            $status = $response->getStatusCode();
            $request = request();

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            // Jangan tutupi halaman debug Laravel saat pengembangan.
            if ($status === 500 && config('app.debug')) {
                return $response;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return $response;
            }

            return Inertia::render('Errors/Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
