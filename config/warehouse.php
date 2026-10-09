<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Penandatangan Statis Dokumen Gudang
    |--------------------------------------------------------------------------
    |
    | Nama+jabatan Kepala Gudang yang muncul di Invoice Transfer Pusat→Cabang.
    | TTD-nya, sama seperti TTD Admin Gudang Pusat & PJ Cabang di Surat Jalan,
    | MURNI nama + garis kosong — diisi tangan di kertas, TIDAK PERNAH masuk
    | sistem (koreksi user 2026-09-17: jangan ada mekanisme upload/gambar TTD
    | sama sekali, di dokumen manapun).
    |
    | Nama di-hardcode di sini, BUKAN ditarik dari `users.name` — keputusan
    | eksplisit user (docs/plan/warehouse/rancangan-invoice-surat-jalan-transfer.md
    | §7 keputusan #3): nama resmi di dokumen kemungkinan tidak sama persis
    | dengan nama akun sistemnya.
    */
    'kepala_gudang' => [
        'name' => 'Nadya Naralita Setiadi',
        'title' => 'Kepala Gudang',
    ],
];
