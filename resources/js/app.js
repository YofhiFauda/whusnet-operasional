import NProgress from 'nprogress';

window.NProgress = NProgress;
NProgress.configure({ showSpinner: false, minimum: 0.1 });
NProgress.done();

window.addEventListener('beforeunload', () => {
    NProgress.start();
});

import './bootstrap';
import './image-source-picker';

/* htmx — nav instant lintas semua halaman lewat hx-boost="true" di <body>
   (layouts/app.blade.php). Dibundel lokal lewat Vite sama seperti Alpine,
   bukan CDN — alasan sama: app operasional internal, jaringan luar gak
   boleh jadi titik gagal buat interaksi dasar (lihat catatan Alpine di atas). */
import 'htmx.org';

/* Fetch boost gagal (network/4xx/5xx) → fallback navigasi native, biar
   gak nyangkut blank/stuck di halaman lama. Lihat docs/superpowers/specs/
   2026-11-03-spa-feel-htmx-design.md § Error Handling. */
window.addEventListener('htmx:responseError', (e) => {
    window.location.href = e.detail.requestConfig.path;
});

/* Boost nav lewat fetch htmx sendiri, BUKAN axios — interceptor NProgress di
   bootstrap.js (dipasang buat request axios biasa) gak pernah ketrigger pas
   klik link boosted, jadi user klik lalu diam, nunggu tanpa tanda apa pun
   sampai swap jadi (keluhan UX 2026-11-04). `detail.boosted` dicek supaya
   NProgress gak ikut nyala buat request htmx non-boost lain (mis. hx-get
   dropdown kecil yang sudah punya hx-indicator sendiri).

   Highlight instan di link sidebar yang diklik juga ditambah di sini —
   kelas `-active` yang sama dipakai server (Request::routeIs()) — supaya
   user lihat link-nya "nyala" SAAT diklik, bukan baru pas body selesai
   ditukar. Kelas ini otomatis lenyap begitu swap full-body datang (elemen
   lama ikut diganti), gak perlu dibersihkan manual.

   NProgress.start() DITUNDA 150ms (bukan langsung), bukan malas — nav
   yang memang udah cepat (<150ms, kasus umum di app ini) kalau dikasih
   progress bar JUSTRU kerasa lebih lambat, karena mata user kebiasa liat
   "sesuatu" yang nyicil connect sebelum konten muncul, padahal dulu tanpa
   indicator transisinya kerasa instan (loncat langsung). Pola ini
   standar UX utk async indicator (threshold anti-flicker) — bar cuma
   muncul kalau request BENERAN lambat (>150ms), nav cepat tetap kerasa
   instan tanpa bar sama sekali. Timer dibatalkan di `afterRequest` kalau
   belum keburu nyala. */
let boostProgressTimer = null;
document.body.addEventListener('htmx:beforeRequest', (e) => {
    if (!e.detail.boosted) {
        return;
    }
    const link = e.detail.elt.closest?.('.sidebar-nav-item, .sidebar-subitem');
    if (link) {
        link.classList.add(link.classList.contains('sidebar-subitem') ? 'sidebar-subitem-active' : 'sidebar-nav-item-active');
    }
    boostProgressTimer = setTimeout(() => NProgress.start(), 150);
});
document.body.addEventListener('htmx:afterRequest', (e) => {
    if (e.detail.boosted) {
        clearTimeout(boostProgressTimer);
        NProgress.done();
    }
});

/* ── Alpine.js — dibundel lokal lewat Vite, BUKAN CDN ──
   Dulu dimuat dari cdn.jsdelivr.net di layouts/app.blade.php. Tiga alasan
   pindah: (1) bundle CDN cuma berisi core, jadi `x-collapse` di 6 titik
   (roles/matrix, noc/worksheet, tickets/history, pop-tree-picker) mati dan
   cuma melempar peringatan directive tak dikenal; (2) URL-nya `3.x.x` —
   rilis minor mana pun masuk produksi tanpa lewat repo/CI; (3) aplikasi
   operasional internal ISP: kalau jsdelivr tak terjangkau, seluruh interaksi
   Alpine mati sementara halamannya tetap ter-render — gagal diam-diam.

   `window.Alpine` WAJIB di-set. Bundle CDN memasang global itu otomatis,
   bundle lokal TIDAK. Tiga view realtime (fop/dashboard, tasks/own,
   fop_tasks/index) memanggil `window.Alpine.initTree()` untuk mengikat ulang
   directive di DOM yang baru diganti Echo, dan semuanya dijaga
   `if (window.Alpine)` — tanpa baris ini mereka gagal TANPA error.

   Plugin didaftarkan SEBELUM start(): plugin yang didaftarkan setelahnya
   diabaikan tanpa peringatan. */
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import { infrastructureReport } from './task-infrastructure-report';

Alpine.plugin(collapse);
Alpine.plugin(focus);

window.Alpine = Alpine;
Alpine.data('infrastructureReport', infrastructureReport);
Alpine.start();

/* Safety net: di beberapa browser/kondisi nyata (ketemu di halaman Detail
   Pelanggan — tab lazy-mount lewat `<template x-if>` tetap blank walau HTML
   & store sudah benar), walk otomatis `Alpine.start()` atas `document.body`
   kadang tidak sampai mem-bind semua node sebelum event `load`. Manual
   `Alpine.initTree(document.body)` di console selalu berhasil membereskannya
   — initTree aman dipanggil ulang, directive bawaan Alpine sudah dijaga
   idempoten (node yang sudah di-bind dilewati). Panggil sekali lagi di
   `load` sebagai jaring pengaman, bukan pengganti walk awal. */
window.addEventListener('load', () => {
    Alpine.initTree(document.body);
});

/* `<input type="number">` yang lagi fokus ikut geser nilainya kalau kena
   scroll wheel mouse/trackpad (perilaku bawaan Chrome/Edge) — user gak
   sadar, nilainya diam-diam berubah per `step` (mis. kabel roll "Meter
   Terpakai" step=0.01, scroll kepencet 29x pas isi form = 1000 jadi
   999.71, ketauan 2026-11-01). Blur begitu wheel nyentuh input number
   yang fokus — scroll halaman tetap jalan normal, cuma inputnya lepas
   fokus duluan sebelum browser sempat ubah value. */
document.addEventListener('wheel', () => {
    const active = document.activeElement;
    if (active instanceof HTMLInputElement && active.type === 'number') {
        active.blur();
    }
}, { passive: true });

/* Dipakai material-rows.blade.php (itemOptionsFor) & installation-item-rows.blade.php
   (itemOptionsWithLegacy) — dua komponen beda yang sama-sama butuh "baris lama
   yang id-nya sudah gak ada di opsi sekarang (custody habis/dipindah) tetap
   ditawarkan UNTUK BARIS ITU SAJA". Tanpa ini <select>/combobox jatuh diam-diam
   ke opsi pertama saat render, dan submit ulang menimpa field jadi barang lain.
   Global (bukan import module) karena kedua komponen mendefinisikan fungsi
   Alpine-nya lewat <script> inline yang di-push lewat @push('scripts'), bukan
   modul ES — pola yang sama dengan materialRows()/comboBox() di komponen itu
   sendiri. */
window.withLegacyOption = function (options, matcher, buildFallback) {
    if (options.some(matcher)) {
        return options;
    }

    return [...options, buildFallback()];
};
