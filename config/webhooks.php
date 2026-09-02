<?php

/*
|--------------------------------------------------------------------------
| Tujuan Webhook Keluar (API 1)
|--------------------------------------------------------------------------
|
| Tujuan tetap, hardcode — BUKAN tabel database. Keputusan rev. 8
| (docs/api/keputusan.md): cuma ada 1 konsumen tiap transport sekarang
| (Website B untuk http_json, Telegram Eksternal untuk telegram), jadi
| tabel + form admin dinamis adalah abstraksi sebelum dibutuhkan. Nambah
| konsumen kedua nanti = satu entri baru di sini + satu pemanggilan
| eksplisit di listener, bukan baris data.
|
| 'telegram_external' SENGAJA punya kredensial sendiri, terpisah dari
| config('services.telegram.*') yang dipakai 6 pemanggilan TelegramBotService
| internal. Kalau tercampur, pesan untuk pihak luar mendarat di grup
| internal — pemisahan yang jadi alasan seluruh desain ini batal seketika.
|
*/

return [

    'website_b' => [
        'url' => env('WEBHOOK_WEBSITE_B_URL'),
        'secret' => env('WEBHOOK_WEBSITE_B_SECRET'),
    ],

    'telegram_external' => [
        'bot_token' => env('TELEGRAM_EXTERNAL_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_EXTERNAL_CHAT_ID'),
    ],

    /*
    |----------------------------------------------------------------------
    | Webhook Keluar — Portal Pelanggan (docs/api/api-portal-pelanggan/, Fase 3)
    |----------------------------------------------------------------------
    |
    | Arah SAMA seperti website_b/telegram_external di atas (App ini →
    | luar), BEDA dari portal_client_secret di bawah (itu arah MASUK,
    | portal yang connect ke sini). Event 'invoice.updated', dikirim
    | SendInvoiceUpdatedWebhook lewat webhook_outbox destination
    | 'customer_portal' — literal ini sudah tertulis di komentar migrasi
    | webhook_outbox (2026_08_20_100000_...), jangan diubah namanya.
    |
    */

    'customer_portal' => [
        'url' => env('PORTAL_WEBHOOK_URL'),
        'secret' => env('PORTAL_WEBHOOK_SECRET'),
    ],

    /*
    |----------------------------------------------------------------------
    | Token API Baru — Topologi Jaringan & Konfirmasi Assignment (API masuk)
    |----------------------------------------------------------------------
    |
    | Arah KEBALIKAN dari dua entri di atas — Website B yang connect ke sini,
    | bukan Whusnet yang connect keluar (docs/api/api-pop-distribusi/README.md).
    | Dua token TERPISAH sengaja: baca topologi (risiko rendah, cuma expose
    | struktur internal) vs tulis assignment (mengubah CID pelanggan). Kalau
    | token baca bocor, token tulis TETAP aman — lihat keputusan.md §5.
    |
    */

    'pop_distribusi_read_token' => env('POP_DISTRIBUSI_READ_TOKEN'),
    'network_assignment_write_token' => env('NETWORK_ASSIGNMENT_WRITE_TOKEN'),

    /*
    |----------------------------------------------------------------------
    | Client Secret Portal Pelanggan (docs/api/api-portal-pelanggan/, Fase 2)
    |----------------------------------------------------------------------
    |
    | Statis per environment — membuktikan "ini portal resmi", BUKAN
    | kredensial per pelanggan (itu tabel customer_portal_tokens, beda
    | lapis). Dicek EnsurePortalClientSecret lewat header X-Portal-Client,
    | pola sama EnsurePopDistribusiReadToken (hash_equals, tanpa DB lookup).
    | Tuas darurat: cabut nilai ini, seluruh portal mati seketika tanpa
    | menyentuh token pelanggan (business-logic.md §Autentikasi).
    |
    */

    'portal_client_secret' => env('PORTAL_CLIENT_SECRET'),

];
