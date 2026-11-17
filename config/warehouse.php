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

    /*
    |--------------------------------------------------------------------------
    | Ukuran Logo Kop Dokumen Gudang (PDF)
    |--------------------------------------------------------------------------
    |
    | Tinggi logo (px) di kop Surat Jalan, Invoice Transfer, dan Surat
    | Pengajuan Barang — SATU angka untuk ketiganya supaya kop seragam.
    | Lebar menyesuaikan rasio gambar `public/images/kop-surat-logo.png`
    | otomatis; kolom teks kop di sebelahnya ikut menyempit.
    |
    | Ubah lewat .env: WAREHOUSE_KOP_LOGO_HEIGHT=100, lalu
    | `php artisan config:clear` (atau `config:cache` di produksi).
    | Batas wajar: 52–120. Di atas ~120 teks kop jadi terlalu sempit.
    */
    'kop_logo' => [
        'height' => (int) env('WAREHOUSE_KOP_LOGO_HEIGHT', 84),
    ],
];
