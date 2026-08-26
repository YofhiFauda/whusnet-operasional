<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

/**
 * Konfigurasi dokumentasi OpenAPI (Scramble) untuk API baru
 * (docs/api/api-pop-distribusi/ dan docs/api/api-portal-pelanggan/). Setiap
 * modul, token/kredensial TERPISAH (keputusan.md §5 untuk pop-distribusi;
 * business-logic.md §Autentikasi untuk portal — dua lapis, client secret +
 * token per-pelanggan) — bukan satu security scheme global, karena tiap
 * pasangan token punya kelas risiko beda. `OpenApi::secure()` bawaan
 * Scramble menerapkan satu scheme ke SEMUA operation lewat
 * `$openApi->security` (dokumen-level), jadi di sini scheme didaftarkan
 * manual ke `components.securitySchemes` lalu di-assign per-operation —
 * bukan lewat `secure()` — supaya tiap endpoint menampilkan kredensial yang
 * benar-benar dia butuhkan di UI dokumentasi (dan tombol "Authorize" di
 * Postman/Scalar/Elements), bukan tercampur atau kosong.
 */
class ScrambleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $readScheme = SecurityScheme::http('bearer')
                ->as('PopDistribusiReadToken')
                ->setDescription('Token akses baca-saja untuk membaca struktur topologi Mini POP dan Distribusi.');

            $writeScheme = SecurityScheme::http('bearer')
                ->as('NetworkAssignmentWriteToken')
                ->setDescription('Token akses tulis untuk mengonfirmasi penugasan jaringan pelanggan.');

            // Dua lapis kredensial portal pelanggan (business-logic.md
            // §Autentikasi) — client secret portal (statis per environment,
            // header X-Portal-Client, dicek EnsurePortalClientSecret) DAN
            // token per-pelanggan (bearer, dicek EnsurePortalCustomerToken).
            // Endpoint yang butuh keduanya mendapat SecurityRequirement
            // dengan DUA key sekaligus — di OpenAPI, satu objek requirement
            // dengan >1 scheme berarti AND (semua wajib), bukan OR.
            $portalClientScheme = SecurityScheme::apiKey('header', 'X-Portal-Client')
                ->as('PortalClientSecret')
                ->setDescription('Client secret portal, statis per environment — membuktikan "ini portal resmi". Dicek di SEMUA endpoint /api/customer-portal/auth/* dan /me/*, TIDAK di /ping.');

            $portalTokenScheme = SecurityScheme::http('bearer')
                ->as('PortalAccessToken')
                ->setDescription('Access token pelanggan (15 menit, dari /auth/login atau /auth/refresh) — membuktikan "ini pelanggan X". TIDAK dipakai di /auth/login, /auth/claim, /auth/refresh (belum ada identitas pelanggan saat endpoint itu dipanggil).');

            $openApi->components->addSecurityScheme($readScheme->schemeName, $readScheme);
            $openApi->components->addSecurityScheme($writeScheme->schemeName, $writeScheme);
            $openApi->components->addSecurityScheme($portalClientScheme->schemeName, $portalClientScheme);
            $openApi->components->addSecurityScheme($portalTokenScheme->schemeName, $portalTokenScheme);

            // Tidak ada security default di level dokumen — tiap operation
            // wajib eksplisit set scheme-nya sendiri di bawah, supaya gak ada
            // endpoint yang diam-diam mewarisi token yang salah.
            $openApi->security = [];

            foreach ($openApi->paths as $path) {
                if (str_contains($path->path, 'pop-distribusi') && isset($path->operations['get'])) {
                    $path->operations['get']->addSecurity(
                        new SecurityRequirement([$readScheme->schemeName => []])
                    );
                }

                // network-assignment DAN network-device berbagi token tulis
                // yang sama (satu kelas risiko: sama-sama menulis data
                // pelanggan) — rate limiter-nya yang terpisah, bukan token
                // (keputusan.md §19).
                if ((str_contains($path->path, 'network-assignment') || str_contains($path->path, 'network-device'))
                    && isset($path->operations['post'])) {
                    $path->operations['post']->addSecurity(
                        new SecurityRequirement([$writeScheme->schemeName => []])
                    );
                }

                if (! str_contains($path->path, '/customer-portal')) {
                    continue;
                }

                // /ping — health-check publik, tanpa kredensial apa pun
                // (Fase 0, risiko disclosure nol — lihat routes/api.php).
                if (str_ends_with($path->path, '/ping')) {
                    continue;
                }

                // Tiga endpoint auth yang belum punya identitas pelanggan
                // saat dipanggil — cuma client secret, TANPA bearer token
                // pelanggan (login: belum login; claim: stub, belum ada
                // sesi; refresh: identitas datang dari refresh_token di
                // body, bukan header Authorization).
                $clientOnly = str_ends_with($path->path, '/auth/login')
                    || str_ends_with($path->path, '/auth/claim')
                    || str_ends_with($path->path, '/auth/refresh');

                $requirement = $clientOnly
                    ? [$portalClientScheme->schemeName => []]
                    : [$portalClientScheme->schemeName => [], $portalTokenScheme->schemeName => []];

                foreach ($path->operations as $operation) {
                    $operation->addSecurity(new SecurityRequirement($requirement));
                }
            }
        });
    }
}
