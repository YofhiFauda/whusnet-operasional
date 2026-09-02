<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gemini — OCR CADANGAN untuk kwitansi kolektor
    |--------------------------------------------------------------------------
    |
    | MATI selama `GEMINI_API_KEY` kosong, dan itu keadaan normal: pencocokan
    | kwitansi jalan utamanya lewat QR yang dicetak sistem sendiri (gratis,
    | deterministik). OCR hanya dipakai kalau QR sobek/buram/hasil fotokopi.
    |
    | Selama mati, berkas yang QR-nya gagal langsung jatuh ke pencocokan manual
    | admin — fitur tetap utuh, dan tak ada biaya yang keluar sebelum diputuskan.
    |
    | ⚠️ JANGAN ISI KEY INI SEBELUM ADHOC-25 DIKERJAKAN.
    | Hasil OCR saat ini diperlakukan sama dengan hasil QR: langsung ditempelkan
    | ke pembayaran. OCR bisa salah baca satu digit, dan kalau nomor hasil
    | salah-baca itu kebetulan ada di database, kwitansinya menempel diam-diam
    | ke pembayaran pelanggan LAIN — statusnya hijau "Cocok", tanpa error.
    | Rancangan perbaikannya (OCR jadi usulan, bukan keputusan):
    | docs/plan/kolektor/analisa-risiko-ocr-kwitansi.md
    |
    | docs/kolektor/business-logic.md § Kwitansi.
    |
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => env('GEMINI_TIMEOUT', 30),
    ],

];
