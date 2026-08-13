import NProgress from 'nprogress';

window.NProgress = NProgress;
NProgress.configure({ showSpinner: false, minimum: 0.1 });
NProgress.done();

window.addEventListener('beforeunload', () => {
    NProgress.start();
});

import './bootstrap';

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

Alpine.plugin(collapse);
Alpine.plugin(focus);

window.Alpine = Alpine;
Alpine.start();
