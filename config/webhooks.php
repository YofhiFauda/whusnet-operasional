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

];
