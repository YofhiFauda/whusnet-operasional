<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QR HMAC Secret
    |--------------------------------------------------------------------------
    |
    | Menandatangani payload QR pelanggan (pop_id|customer_code|token) supaya
    | QR tidak bisa dihitung ulang oleh siapa pun yang tahu ketiga bahannya
    | (semuanya publik). WAJIB diisi di .env, minimal 32 byte acak:
    | `openssl rand -base64 32`.
    |
    | SENGAJA terpisah dari APP_KEY — rotasi APP_KEY (mis. saat insiden)
    | tidak boleh ikut mematikan seluruh stiker QR yang sudah tercetak dan
    | tertempel di lapangan.
    |
    | docs/plan/qr-code/rancangan-qr-pelanggan-final.md §3.3
    */
    'secret' => env('QR_HMAC_SECRET'),

    'base_url' => env('QR_BASE_URL', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Portal Pelanggan — tujuan redirect pemindai TAMU
    |--------------------------------------------------------------------------
    |
    | Keputusan 2026-08-27: scan QR pelanggan (tamu, belum login) SELALU
    | diarahkan ke Portal (app terpisah), BUKAN lagi gerbang tagihan internal
    | (`QrBillingController`, dicabut). `QrScanController` redirect ke
    | "{portal_base_url}/klaim?code={code}" — Portal yang panggil balik
    | `GET /api/customer-portal/qr/resolve` buat dapetin login_id.
    | WAJIB diisi di production (tanpa ini, pemindai tamu ke-404 diam-diam —
    | lihat guard di QrScanController).
    */
    'portal_base_url' => env('PORTAL_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Radius absen teknisi (Fase 3, rancangan §6.3)
    |--------------------------------------------------------------------------
    | ≤ `attendance_radius_meters` → lolos. Antara itu dan
    | `attendance_hard_limit_meters` → lolos + perlu_review. Di atas hard
    | limit → ditolak. Angka 150 m dipilih karena akurasi GPS HP di area
    | padat rutin meleset 50–100 m; lebih ketat dari itu membuat teknisi
    | selalu jatuh ke tombol manual dan guard-nya mati dalam praktik.
    */
    'attendance_radius_meters' => (int) env('QR_ATTENDANCE_RADIUS_METERS', 150),

    'attendance_hard_limit_meters' => (int) env('QR_ATTENDANCE_HARD_LIMIT_METERS', 500),
];
