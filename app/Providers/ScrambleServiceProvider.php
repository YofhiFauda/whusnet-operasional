<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

/**
 * Konfigurasi dokumentasi OpenAPI (Scramble) untuk API baru
 * (docs/api/api-pop-distribusi/). Dua endpoint, dua token bearer TERPISAH
 * (keputusan.md §5) — bukan satu security scheme global, karena baca dan
 * tulis punya kelas risiko beda. `OpenApi::secure()` bawaan Scramble
 * menerapkan satu scheme ke SEMUA operation lewat `$openApi->security`
 * (dokumen-level), jadi di sini scheme didaftarkan manual ke
 * `components.securitySchemes` lalu di-assign per-operation — bukan lewat
 * `secure()` — supaya endpoint #1 dan #2 masing-masing menampilkan token
 * bearer-nya sendiri di UI dokumentasi, bukan tercampur.
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

            $openApi->components->addSecurityScheme($readScheme->schemeName, $readScheme);
            $openApi->components->addSecurityScheme($writeScheme->schemeName, $writeScheme);

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
            }
        });
    }
}
