<?php

use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);

        // Aplikasi SELALU berada di belakang proxy: nginx di depan PHP-FPM, dan
        // di produksi masih ditambah Cloudflare Tunnel (cloudflared) yang
        // memutus TLS di sisi Cloudflare lalu meneruskan HTTP polos ke nginx.
        //
        // Tanpa baris ini Laravel melihat request sebagai http:// — akibatnya
        // route()/url()/asset() menghasilkan tautan http di halaman yang
        // dibuka lewat https: browser memblokir sebagai mixed content, aset
        // Vite & broadcasting/auth gagal diam-diam, dan redirect setelah
        // simpan (PRG) melempar pengguna keluar dari HTTPS.
        //
        // `at: '*'` disengaja: alamat IP cloudflared/nginx dinamis (jaringan
        // Docker, atau connector Cloudflare yang bisa berpindah), jadi tidak
        // ada daftar IP yang stabil untuk dipercaya. Ini aman SELAMA PHP-FPM
        // tidak pernah bisa dihubungi langsung dari internet — port 9000 tidak
        // dipublikasikan di docker-compose.yml, satu-satunya pintu masuk
        // adalah nginx. Kalau suatu saat PHP-FPM/8000 diekspos publik, isi
        // TRUSTED_PROXIES dengan daftar IP nyata, karena header
        // X-Forwarded-* bisa dipalsukan klien.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
