<?php

/**
 * Parameter kredensial portal pelanggan (docs/api/api-portal-pelanggan/,
 * Fase 2) yang tidak eksplisit angkanya di dokumen — dipusatkan di sini
 * (bukan magic number di model) supaya gampang diubah satu tempat.
 * `lockout_minutes` dikonfirmasi pemilik produk 2026-08-24: 15 menit,
 * mengikuti pola lockout PIN §6.5.4 modul QR.
 */
return [

    'lockout_threshold' => 5,

    'lockout_minutes' => 15,

];
