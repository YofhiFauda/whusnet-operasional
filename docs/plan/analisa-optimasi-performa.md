# Analisa Optimasi Performa — Whusnet Operasional

Tanggal analisa: 2026-08-04
Metode: audit kode (grep + baca controller/migration/blade) atas 6 aspek performa: N+1 query, indexing, caching, pagination, asset frontend, JS main thread.

## Ringkasan Status

| Aspek | Status |
|---|---|
| N+1 Query | Baik |
| DB Indexing | Baik, ada gap di tabel `tickets` |
| Caching | Lemah — gap terbesar |
| Pagination | Baik |
| Asset Frontend | Ada gap (CDN blocking, tanpa code-splitting) |
| JS Main Thread / Event Listener | Sedang — pola polling, belum manfaatkan Reverb/Echo yang sudah ada |

## 1. N+1 Query — Baik

Controller besar sudah eager-load dengan benar:
- `InvoiceController::index` (`app/Http/Controllers/InvoiceController.php:27-35`) — `with(['customer.collector', 'pop', 'customerService', 'internetPackage', 'payments.collector'])`
- `PaymentController::index` (`app/Http/Controllers/PaymentController.php:39`)
- `CustomerController::renderCustomerList` (`app/Http/Controllers/CustomerController.php:125-137`) — `select()` kolom terbatas + `with([...])`, sudah ada komentar alasan tuning
- `TaskController::index` (`app/Http/Controllers/TaskController.php:48`)
- `FopTaskController::index` (`app/Http/Controllers/FopTaskController.php:43`, history line 1015)
- `TicketController::worksheetTasks()` (`app/Http/Controllers/TicketController.php:103-119`) — kolom sempit + `limit(WORKSHEET_DISPLAY_LIMIT)`

Tidak ditemukan lazy-load dalam loop di controller manapun. Tidak ada gap dikonfirmasi.

## 2. DB Indexing — Baik, ada gap

Sudah ada migration index dedicated:
- `database/migrations/2026_07_22_164035_add_performance_indexes_phase3.php` — composite index: `customers_pop_status_idx`, `customers_status_created_idx`, `invoices_status_due_idx`, `invoices_pop_period_idx`, `payments_status_date_idx`, `tasks_type_status_completed_idx`, `fop_tasks_pop_date_status_idx`
- `database/migrations/2026_07_24_145737_add_customer_search_prefix_indexes.php` — prefix index customer_code/cid/primary_phone/identity_number
- `database/migrations/2026_07_01_160949_add_composite_indexes_to_tasks_tables.php`

**Gap:**
- `tickets.handler` + `tickets.status` **tanpa index** (`database/migrations/2026_07_25_000003_add_handler_and_status_to_tickets_table.php:21-24`), padahal `handler` adalah filter utama routing Helpdesk/NOC/FOP — tiap query worksheet/dashboard full scan tabel `tickets`. Index yang ada di tabel ini cuma `pop_id+created_at`, `created_by`, `type` (`2026_07_23_000001_create_tickets_table.php:38-40`).
- Tidak ada composite index `payments(invoice_id, payment_status)` untuk query "pembayaran valid per invoice" (`InvoiceController.php:31`) — saat ini cuma mengandalkan index FK implisit `invoice_id`.

## 3. Caching — Lemah, gap terbesar

Caching yang sudah ada cuma: `EffectiveAccessService.php:27,110,123` (permission/scope/pop-id), `AuditLogController.php:49,54` (dropdown modules/actions), `CheckCountdownStatus.php:52`.

**Gap — dashboard agregasi berat, tanpa cache, dihitung ulang tiap request:**
- `DashboardController::index` (`app/Http/Controllers/DashboardController.php:65-110`) — ~7 query `count()` + 3 `get()`
- `NocDashboardController::index` (`app/Http/Controllers/NocDashboardController.php:89-234`) — ~9 `count()` berurutan + beberapa `get()->map()->count()`
- `FopDashboardController::index` (`app/Http/Controllers/FopDashboardController.php:47-172`) — beberapa `with()->get()` tim/task

Kandidat kuat `Cache::remember()` TTL pendek (30-60 detik), karena halaman ini kemungkinan sering di-refresh/poll.

## 4. Pagination — Baik

- `InvoiceController.php:74` `paginate(10)`
- `PaymentController.php:92` `paginate(10)`
- `CustomerController.php:246-247` `per_page` whitelist `[10,25,50,100]`
- `TicketHistoryController.php:66` `paginate(50)`
- Query `get()` di dashboard controller sudah dibatasi scope hari-ini/aktif — bukan risiko tabel besar.

## 5. Asset Frontend — Ada gap

- `resources/views/layouts/app.blade.php` (1194 baris). Alpine.js via CDN sudah `defer` (line 22) — baik.
- NProgress CSS+JS dimuat dari CDN terpisah **tanpa defer/async** (line 25-26) — render-blocking, tidak dibundel lewat Vite.
- Dua inline `<script>` tambahan di layout (line 726, 934) + satu `setTimeout` (line 839).
- `vite.config.js` — tidak ada `build.rollupOptions.output.manualChunks`, tanpa code-splitting.
- 58 file Blade punya `<script>` block sendiri — JS per-halaman kebanyakan inline, bukan dibundel lewat Vite, menambah cost parse per halaman & mencegah caching/reuse antar navigasi.

## 6. JS Main Thread / Event Listener — Sedang

- `resources/views/fop/dashboard.blade.php:666` — full page `window.location.reload()` via `setTimeout` sebagai mekanisme "selesai", bukan update state ringan.
- `fop/dashboard.blade.php:698` — busy-poll retry loop (`attempts < 20`, `setTimeout` 100ms) alih-alih listener once yang proper.
- 21 file pakai `setInterval`/`setTimeout` untuk polling status live — padahal Reverb + laravel-echo + pusher-js sudah terpasang di stack, tapi masih under-used untuk kasus ini.
- `customers/index.blade.php` — 0 `x-data` top-level, server-rendered, ringan.

## Urutan Pengerjaan (Prioritas)

1. **Index `tickets.handler` + `tickets.status`** — migration baru, low-risk, dampak langsung ke query worksheet NOC/Helpdesk/FOP yang sering diakses.
2. **Cache 3 dashboard controller berat** (`DashboardController`, `NocDashboardController`, `FopDashboardController`) — `Cache::remember()` TTL 30-60 detik, invalidasi via event/observer relevan atau expired TTL. Dampak besar, effort sedang.
3. **Composite index `payments(invoice_id, payment_status)`** — migration kecil, tuntaskan sekalian dengan langkah 1.
4. **Ganti loading NProgress dari CDN sinkron** ke asset dibundel Vite / tambah `defer` — quick win, kurangi render-blocking.
5. **Ganti pola poll+reload di `fop/dashboard.blade.php`** (line 666, 698) dengan event broadcast Reverb/Echo yang sudah tersedia di stack — effort lebih besar (perlu event + listener Echo), tapi hilangkan busy-poll & full reload.
6. **Code-splitting Vite (`manualChunks`)** + audit 58 file blade dengan inline `<script>`, pindahkan JS reusable ke `resources/js/` — cleanup jangka panjang, non-urgent, kerjakan bertahap per modul saat modul itu disentuh task lain (hindari refactor besar-besaran sekaligus di luar sprint aktif).

Catatan: langkah 1-4 aman dikerjakan independen tanpa nyentuh sprint aktif lain. Langkah 5-6 sebaiknya jadi task sprint tersendiri (scope check dulu sesuai `docs/TASKS.md`) karena nyentuh banyak file & butuh test broadcast/frontend.

## Progress

### Fase 1 — Selesai (2026-08-04)

Migration `2026_08_04_135419_add_index_tickets_handler_status_and_payments_invoice_status.php`:
- `tickets_handler_status_idx` (`handler`, `status`) — dipakai bareng di `NocDashboardController` & `NocWorksheetController`.
- `payments_invoice_status_idx` (`invoice_id`, `payment_status`) — dipakai `InvoiceController::index()`.

Migrasi jalan bersih, 362 test ticket/payment/invoice lolos.

### Fase 2 — Selesai (2026-08-04), dengan penyesuaian scope

`DashboardController`, `NocDashboardController`, `FopDashboardController` sekarang meng-cache blok **stats/angka agregasi** (`Cache::remember`, TTL 30-60 detik, key per user+filter/tanggal — scope POP efektif beda per user).

**Perubahan dari rencana awal**: rencana semula cache SELURUH data halaman (termasuk listing Eloquent Collection: `activeTickets`, `activeFopTeams`, `dueInvoices`, dst). Dibatalkan setelah ditemukan bug environment: **round-trip Eloquent Collection (atau bahkan `Illuminate\Support\Collection` polos) lewat cache store apa pun di sini (`file` MAUPUN `redis`) korup jadi `__PHP_Incomplete_Class`** — reproduksi independen di luar dashboard/HTTP sama sekali:

```php
Cache::put('zz', collect([1,2,3]), 30);
Cache::get('zz'); // __PHP_Incomplete_Class, bukan Collection
```

Array/skalar polos round-trip normal (dicek juga). Root cause belum ditelusuri (bukan config `serialize` di `config/cache.php`, direproduksi di kedua store) — kemungkinan ada di setup PHP/extension container ini. **Perlu task investigasi terpisah sebelum ada kode lain yang cache Eloquent Collection/Model mentah** — kalau ini kejadian di produksi (cache store produksi = `redis` per `.env`), endpoint apa pun yang cache object akan 500 secara intermiten begitu TTL cache-nya "hit".

Karena itu, scope Fase 2 dipersempit ke stats (semua nilai `int`/`float`/`string`/array-scalar) — aman di store manapun, dan itu memang bagian terberat yang diaudit (7-9 query `count()` berurutan per dashboard). Listing/koleksi (activeTickets, activeFopTeams, dueInvoices, incompleteCustomers, customersByPop, issueStats/regionStats dikonversi ke array biasa sebelum di-cache) tetap dihitung fresh tiap request seperti sebelumnya.

32 test dashboard (Dashboard/NocDashboard/FopDashboard) + 65 test Noc lolos. Full suite: 948 passed, 10 gagal (pre-existing, tidak terkait — `EffectiveAccessServiceTest`/`SubscriptionStatusMasterTest`, sudah dikonfirmasi gagal identik di `dev` sebelum perubahan ini).

### Fase 3 — Selesai (2026-08-04)

NProgress dipindah dari CDN sinkron ke dependency npm dibundel Vite:
- `npm install nprogress` (dependency asli, bukan CDN).
- `resources/js/app.js` — import `NProgress`, expose `window.NProgress`, `configure()` + `done()` + listener `beforeunload` (pindah dari inline script `layouts/app.blade.php`).
- `resources/css/app.css` — `@import 'nprogress/nprogress.css'`.
- `layouts/app.blade.php` — `<link>`/`<script>` CDN NProgress + inline script dihapus, tinggal `@vite(...)`.

Build (`npm run build`) sempat gagal karena `public/build/assets/*` ke-root-owned dari build container sebelumnya (host user `yopi` gak bisa unlink) — diselesaikan user secara manual di luar sesi ini. Terverifikasi: `nprogress` sudah kebundel di `app-BwXrV9td.js`, tidak ada lagi reference CDN.

### Fase 4 — Selesai (2026-08-04)

Ganti pola poll+reload di `resources/views/fop/dashboard.blade.php` dengan mekanisme berbasis event (Reverb/Echo yang sudah ada):

- **Busy-poll → event**: `initEchoListeners()` sebelumnya retry `setTimeout` sampai 20× nunggu `window.Echo` ada. `resources/js/echo.js` sekarang menembak `CustomEvent('echo:ready')` tepat setelah `window.Echo` di-assign; dashboard dengar event itu (langsung jalan kalau `window.Echo` sudah ada duluan).
- **Full reload → partial refresh**: `submitSwitchTeam()` sebelumnya `setTimeout(() => window.location.reload(), 1000)` sehabis sukses. Sekarang panggil `refreshTeamsBoard()` baru: fetch halaman, swap `#fop-teams-board` via `outerHTML` (bukan `innerHTML` — elemen di dalamnya pakai binding Alpine `@dragover`/`@click` yang perlu di-scan ulang), sinkron `teamsData` dari payload `#fop-teams-json` yang ikut ke-refresh, lalu `Alpine.initTree()` buat re-bind directive di subtree baru. Fallback ke full reload kalau `Alpine`/board elemen gak ketemu (jaring pengaman, bukan best-effort silent fail).

206 test FOP + 13 test FopDashboard/SwitchTeam lolos, blade kompilasi bersih (`view:clear` + test ulang). Build frontend nunggu langkah manual user yang sama kayak Fase 3 (`public/build` root-owned).

### Fase 5 — BELUM DIKERJAKAN (dihentikan user, 2026-08-05)

**Alasan berhenti**: scope refactor-nya kebesaran buat dikerjakan sekarang (bukan quick-win kayak Fase 1-4) — beresiko nyentuh banyak file sekaligus di luar sprint aktif. Diputuskan ditunda sampai mendekati masa staging, dikerjakan sebagai task/sprint tersendiri dengan scope check penuh.

Isi rencana asli (`vite.config.js` code-splitting + audit inline `<script>`):

1. **`vite.config.js`** — tambah `build.rollupOptions.output.manualChunks` untuk code-splitting (saat ini seluruh JS App jadi satu bundle tanpa split per rute/modul).
2. **57 file Blade dengan `<script>` inline** (per pengecekan ulang 2026-08-05 — sebagian sudah bersih lewat Fase 3/4, sebagian lain BELUM disentuh sama sekali) — JS-nya perlu dipindah ke `resources/js/` biar bisa dibundel & di-cache browser lintas halaman, bukan re-parse tiap load:

   - `resources/views/layouts/app.blade.php` (shell utama — masih ada beberapa inline script sisa: theme toggle IIFE, sidebar collapse, dll)
   - `resources/views/dashboard.blade.php`, `noc/dashboard.blade.php`, `noc/worksheet.blade.php`, `fop/dashboard.blade.php` (sisa non-Echo), `fop_tasks/index.blade.php`
   - `resources/views/customers/create.blade.php`, `edit.blade.php`, `fieldwork.blade.php`, `index.blade.php`, `show.blade.php`, `tabs/_riwayat_ticketing.blade.php`
   - `resources/views/tickets/create.blade.php`, `show.blade.php`, `partials/action-dialog.blade.php`, `partials/archive.blade.php`, `partials/detail-drawer.blade.php`
   - `resources/views/tasks/edit.blade.php`, `own.blade.php`, `show.blade.php`, `maintenance-report.blade.php`
   - `resources/views/invoices/index.blade.php`, `show.blade.php`; `payments/create.blade.php`, `show.blade.php`, `partials/quick-payment-modal.blade.php`; `reports/payments/index.blade.php`
   - `resources/views/verifications/admin.blade.php`, `queue.blade.php`; `installations/report.blade.php`; `surveys/queue.blade.php`, `report.blade.php`
   - `resources/views/master/pop/create.blade.php`, `edit.blade.php`, `index.blade.php`; `master/paket/create.blade.php`, `edit.blade.php`, `index.blade.php`; `master/distribusi/create.blade.php`, `edit.blade.php`; `master/sla-timeline/index.blade.php`; `master/wilayah.blade.php`
   - `resources/views/roles/index.blade.php`, `matrix.blade.php`; `users/_form.blade.php`; `notifications/index.blade.php`; `collectors/show.blade.php`; `auth/login.blade.php`
   - Komponen reusable: `components/countdown-timer.blade.php`, `components/dialog.blade.php`, `components/material-rows.blade.php`, `components/notification-dropdown.blade.php`, `components/toast.blade.php`, `components/ui/pop-filter.blade.php`, `components/ui/pop-tree-picker.blade.php`, `components/ui/wilayah-filter.blade.php`, `components/work-tool-checklist.blade.php`

   Rekomendasi urutan kalau nanti dikerjakan: komponen reusable dulu (dipakai berkali-kali, dampak paling besar per effort), baru layout shell, baru modul per-halaman dari yang paling sering diakses (dashboard, customers, tickets) ke yang jarang (master data, auth).

3. **Test & verifikasi ulang** — pint, `php artisan test --compact`, `npm run build`, smoke test manual tiap halaman yang disentuh (skrip pindah lokasi = risiko `ReferenceError`/binding Alpine putus kalau ada dependency urutan eksekusi yang kelewat).

**Catatan buat siapa pun yang lanjutin**: jangan migrasi semua sekaligus dalam satu PR — per audit awal, ini "cleanup jangka panjang, non-urgent, kerjakan bertahap per modul saat modul itu disentuh task lain" (lihat bagian Urutan Pengerjaan di atas). Task tracker sempat dibuat buat memecah ini per-modul (layout → components → customers → tickets → tasks → payments/invoices → verifications/installations/surveys → noc/roles/users/misc → master → auth/reports → code-splitting → verifikasi akhir) tapi dihapus lagi bareng keputusan berhenti ini — pecahannya tetap valid sebagai referensi kalau mau dipakai ulang nanti.
