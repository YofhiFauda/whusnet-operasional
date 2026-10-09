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

/* ── Chart.js — dibundel lokal lewat Vite, sama alasan Alpine di bawah:
   aplikasi operasional internal, jangan tergantung CDN pihak ketiga buat
   render chart di Dashboard NOC/FOP Analitik. `Chart.register(...registerables)`
   daftarkan semua controller/element/scale bawaan (line/bar/doughnut, dst) —
   tanpa ini cuma core Chart yang ke-load dan tiap jenis chart perlu didaftar
   manual satu-satu. `window.Chart` WAJIB di-set (sama pola `window.Alpine`)
   biar view Blade bisa langsung pakai `new Chart(...)` dari inline script.

   WAJIB SEBELUM `Alpine.start()` di bawah — `import` di-hoist, tapi baris
   assignment (`window.Chart = Chart`) TIDAK, jalan sesuai urutan tertulis.
   `Alpine.start()` scan DOM SINKRON dan langsung panggil `init()` tiap
   komponen `x-data` (termasuk `nocDashboardHandler()` di noc/dashboard.blade.php
   yang manggil `window.nocDashboardRenderCharts()`) — kalau baris ini taruh
   SESUDAH `Alpine.start()`, `window.Chart` masih `undefined` pas `init()`
   jalan, dan render chart di-skip diam-diam (guard `typeof window.Chart ===
   'undefined'`). Pernah kejadian persis ini — semua canvas chart Dashboard
   NOC blank walau datanya ada. */
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;

Alpine.plugin(collapse);
Alpine.plugin(focus);

window.Alpine = Alpine;
Alpine.start();
