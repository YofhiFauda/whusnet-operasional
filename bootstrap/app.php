<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureNetworkAssignmentWriteToken;
use App\Http\Middleware\EnsurePopDistribusiReadToken;
use App\Http\Middleware\EnsurePortalClientSecret;
use App\Http\Middleware\EnsurePortalCustomerToken;
use App\Http\Middleware\PortalStaffToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
            // API Baru (docs/api/api-pop-distribusi/) — dua token bearer
            // terpisah, jangan digabung jadi satu alias ber-parameter
            // (keputusan.md §5).
            'pop_distribusi.read' => EnsurePopDistribusiReadToken::class,
            'network_assignment.write' => EnsureNetworkAssignmentWriteToken::class,
            // API Portal Pelanggan (docs/api/api-portal-pelanggan/, Fase 2) —
            // dua lapis kredensial TERPISAH sengaja: client secret portal
            // (statis per environment) vs token per-pelanggan (DB lookup).
            // Jangan digabung jadi satu middleware ber-if (keputusan.md §5).
            'portal_client' => EnsurePortalClientSecret::class,
            'portal_token' => EnsurePortalCustomerToken::class,
            // Kredensial STAF/kolektor di jalur Portal (2026-08-29,
            // docs/plan/qr-code/analisa-unifikasi-qr-staff-portal.md §4) —
            // ber-parameter purpose ('tickets'/'kolektor'), lihat docblock
            // PortalStaffToken kenapa token satu purpose tidak lolos di purpose lain.
            'portal_staff_token' => PortalStaffToken::class,
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
        // API Baru (docs/api/api-pop-distribusi/) butuh error /api/* SELALU
        // JSON — Website B tidak selalu kirim header Accept: application/json,
        // dan tanpa ini exception tak tertangani (404 route salah, 500, dst)
        // akan render halaman error Blade ke konsumen mesin.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // QR pelanggan (docs/plan/qr-code/rancangan-qr-pelanggan-final.md
        // §6.5.4, §6.5.5b) — "PIN plaintext tidak pernah masuk session,
        // cache, flash, atau log". Laravel default-nya CUMA mengecualikan
        // password/password_confirmation/current_password dari
        // withInput() saat validasi gagal — kalau field pin/hp_last4/
        // new_pin lolos lewat $request->validate() biasa TANPA baris ini,
        // validasi format yang gagal (mis. kurang dari 6 digit) bakal
        // nge-flash PIN yang diketik ke session `_old_input`, persis yang
        // dilarang dokumen. QrBillingController pakai nama field ini —
        // didaftarkan global di sini (bukan per-controller) supaya
        // pengaman ini tidak bisa lupa kepasang lagi kalau field serupa
        // ditambah di tempat lain nanti.
        $exceptions->dontFlash(['pin', 'hp_last4', 'new_pin', 'new_pin_confirmation', 'new_password']);
    })->create();
