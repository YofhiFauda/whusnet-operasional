<?php

/**
 * Scope SEMPIT — hanya grup Portal Pelanggan (docs/api/api-portal-pelanggan/, Fase 0).
 *
 * Sebelum file ini ada, seluruh /api/* (termasuk pop-distribusi &
 * network-assignment) diam-diam kena default vendor Laravel: allowed_origins
 * '*', wide open. Endpoint itu server-to-server (dipanggil sistem lain lewat
 * bearer token, bukan browser) — mereka tidak butuh CORS sama sekali dan
 * SENGAJA tidak dimasukkan ke `paths` di bawah, supaya kebijakan CORS baru
 * ini tidak diam-diam ikut menutupi/mengubah perilaku mereka.
 */
return [

    'paths' => ['api/customer-portal/*'],

    'allowed_methods' => ['*'],

    // Whitelist origin SPESIFIK — bukan wildcard (keputusan.md §1: "Prefix
    // /api/v1/portal" dan pola wildcard serupa ditolak demi kontrak portal
    // yang eksplisit). Satu origin per environment; ganti nilai .env saat
    // deploy, jangan tambah origin kedua di sini.
    'allowed_origins' => array_values(array_filter([env('PORTAL_ALLOWED_ORIGIN')])),

    'allowed_origins_patterns' => [],

    // X-Portal-Client disiapkan buat Fase 2 (client secret portal, dua
    // lapis kredensial di samping bearer token pelanggan) — dimasukkan
    // sekarang supaya file ini tidak perlu disentuh lagi nanti.
    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Portal-Client'],

    'exposed_headers' => [],

    'max_age' => 0,

    // FALSE: portal menyimpan token API kita di sesi server-side-nya sendiri
    // (HttpOnly, bukan localStorage — business-logic.md §"Token") dan
    // memanggil kita lewat header Authorization: Bearer, bukan cookie lintas
    // domain. Tidak ada sesi/cookie API kita yang pernah dibagi ke domain
    // portal, jadi credentials cross-origin tidak relevan di sini.
    'supports_credentials' => false,

];
