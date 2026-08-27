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
    | Rekening Tujuan Transfer (Fase 2 — tanpa gateway)
    |--------------------------------------------------------------------------
    |
    | Halaman tagihan publik (§6.1) menampilkan rekening ini + tombol Salin
    | + tombol WhatsApp admin POP, TANPA integrasi payment gateway/QRIS —
    | itu Fase 4, DITAHAN sampai ada perintah resmi (§0). Pencatatan
    | pembayaran tetap manual lewat /payments.
    */
    'bank_account' => [
        'bank_name' => env('QR_BANK_NAME'),
        'account_number' => env('QR_BANK_ACCOUNT_NUMBER'),
        'account_holder' => env('QR_BANK_ACCOUNT_HOLDER'),
    ],
];
