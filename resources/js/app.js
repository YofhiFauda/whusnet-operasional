import NProgress from 'nprogress';

window.NProgress = NProgress;
NProgress.configure({ showSpinner: false, minimum: 0.1 });
NProgress.done();

window.addEventListener('beforeunload', () => {
    NProgress.start();
});

import './bootstrap';
import './image-source-picker';

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
