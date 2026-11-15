# Rancangan Perbaikan — Dampak `hx-boost` Global + 3 Bug Laporan User

**Tanggal:** 2026-11-13
**Status:** SEBAGIAN DIKERJAKAN (urutan disetujui user 2026-11-13: P6 → P7 → P1).

| Tahap | Status | Bukti |
|---|---|---|
| P6 (Bug 2) | ✅ selesai, **dikonfirmasi user** | Seeder pakai `Role::TECHNICIAN_CODES`; `TombolLaporanPicGudangMunculTest` 19 lulus (sebelumnya 9 gagal); seeder dijalankan di DB lokal → PSB #6 `statusComplete` 0 → 1 |
| P7 (Bug 3) | ✅ selesai, terverifikasi Chrome headless | **Akar masalah sebenarnya (ditemukan setelah user lapor masih rusak):** double-escape Blade. `installation-item-rows` meng-echo `{{ $modemBlockedExpr }}` di dalam atribut `options-expr`, lalu `<x-combobox>` meng-echo `{{ $optionsExpr }}` lagi, sehingga `'beli_sendiri'` menjadi `&amp;#039;…`, Alpine `SyntaxError: Unexpected token '&'`, `comboBox` tidak terbentuk, dan daftar barang kosong. Ini terjadi di PSB **dan** Maintenance/C-REQ, baik dibuka lewat boost maupun F5. Bukan htmx. Fix: `{!! $modemBlockedExpr !!}` + `BarangDipasangComboboxKosongTest`. Bukti Chrome (user `pic_gudang`, PSB #6): opsi 5 SN modem + 2 roll tampil, memilih SN mengisi `selected_inventory_serial_id=21`, di jalur boost dan langsung. Perbaikan pendukung tetap dipertahankan: 2 `const` → `var`, timer `window.__psbReportTimer`, init via `window.onPageReady`, penjaga redeclare diperketat. |
| P7b (Bug 3, desain ulang) | ✅ selesai, terverifikasi Chrome headless | Permintaan user 2026-11-13: satu combobox gabungan menyulitkan teknisi yang memegang banyak barang. Baris `installation-item-rows` (PSB + Maintenance/C-REQ) menjadi **Kategori → Nama Barang → Jumlah** (+ Catatan khusus pasif). Opsi hanya dari custody tim, Kategori menampilkan "N barang dipegang", Nama Barang terfilter per kategori, memilih barang langsung ikut mengisi kategori. Jumlah: modem 1 unit terkunci, roll = meter, pasif = qty (maks sisa). Nama field ke server tidak berubah. Test `BarangDipasangPilihKategoriNamaJumlahTest`. Chrome (pic_gudang, PSB #6): kategori Modem/ONT (5) & Kabel Dropcore (2), form terkirim `serial=21, roll=48, meter=150`. Catatan: user pic_gudang lokal belum memegang barang pasif (0 custody), jadi kategori pasif tidak muncul untuknya. Itu data, bukan bug. |
| P7c (Perangkat Tambahan) | ✅ selesai (test; belum uji Chrome — data lokal 0 custody perangkat tambahan) | Permintaan user 2026-11-13. Pelanggan bisnis sudah didukung (gerbang = `internet_packages.max_additional_devices` > 0, bukan tipe pelanggan). Picker diseragamkan ke pola **Kategori → Nama Barang (SN)**, tanpa Jumlah (1 SN/baris): `installations/partials/_additional_devices_step.blade.php` (langkah Perangkat Tambahan PSB) dan `tasks/partials/extra-device-replacement.blade.php` (SN pengganti Maintenance/C-REQ; kategori terkunci ke kategori perangkat lama kalau dari Gudang). Combobox tanpa name; nilai lewat hidden `decisions[i][inventory_serial_id]` (ber-`form="form-additional-devices"`) / `extra_replacements[i][new_serial_id]`. Test: `InstallationAdditionalDeviceStepTest::picker_perangkat_tambahan_pakai_kategori_lalu_nama_barang`, `MaintenanceGantiPerangkatTambahanTest::sn_pengganti_dipilih_lewat_kategori_lalu_nama_barang`. |
| P1 + P2 | ✅ selesai | Listener `htmx:beforeProcessNode` di `app.js` + fallback `responseError` hanya GET; `FormTidakDiBoostHtmxTest`; simulasi jsdom: 0 XHR form (sebelumnya 4), dialog konfirmasi tampil, link tetap boost; aset sudah di-build |
| P3 (DOMContentLoaded) | ✅ selesai (test + Chrome headless) | 19 listener `DOMContentLoaded` di 18 view → `window.onPageReady` (verifikasi antrean/admin, invoices/create, roles/index & matrix, users/_form, partial cascade wilayah, customers create/edit + network assignment, master wilayah/POP/Distribusi, payments create/edit/show). Callback yang kini jalan tiap kunjungan diperiksa satu-satu: hanya memasang listener ke elemen halaman (ikut terganti) — kecuali countdown antrean verifikasi (`setInterval` → disimpan di `window.__verificationCountdownTimer` & di-clear dulu) dan masking Rupiah layout (script di `<body>`, dulu listener `input`/`submit` document menumpuk tiap navigasi → guard `window.__rupiahBound`, format awal via onPageReady). Bug lama ikut beres: 4 halaman master POP/Distribusi memakai `document.querySelector('form')` yang mengembalikan `#logout-form` sidebar → spinner submit nempel ke form logout; kini `#main-content form`. Pengecualian beralasan: layouts/app (definisi), toast (sudah cabang readyState), dashboard (chart lewat `chart:ready`). Penjaga `ScriptHalamanJalanSaatNavigasiBoostTest`. Chrome (owner): /customers/create via boost ⇒ 22 kecamatan termuat (dulu kosong), listener `input` document tetap 1 setelah navigasi berulang. |
| P4 (Bug 1, Echo) | ✅ Tasks Saya + lonceng selesai, terverifikasi Chrome headless; ⏳ view realtime lain | Server bersih: satu jadwal FOP = 1 `AppNotification` + 1 `TaskScheduled` per teknisi (test `TaskSayaNotifikasiDanKartuTidakDobelTest`, jalur `store` & `update` draft→terjadwal). Penyebab di klien: (a) `technicianNotifier` (`tasks/own`) & `notificationDropdown` memanggil `Echo.private(...).listen()` tiap init → handler menumpuk per navigasi boost; (b) `injectTaskCard()` cek-lalu-await → handler ganda menyisipkan kartu ganda; (c) toast `technicianNotifier` dobel dengan toast lonceng (tiap `TaskScheduled` selalu berpasangan `AppNotification`). Perbaikan: listener sekali per dokumen (`window.__technicianEchoBound` / `window.__notificationEchoBound`) meneruskan ke komponen yang sedang tampil; guard `pendingCards` + cek ulang pasca-fetch; toast `technicianNotifier` dihapus (cukup toast lonceng); bug `getElementById('today-task-list').parentElement` null diperbaiki. Chrome (u4): 3× navigasi boost → tiap event tetap 1 callback; simulasi 1 event = 1 fetch kartu, badge +1. Catatan: notifikasi OS Web Push (ADHOC-288) tetap muncul walau app terbuka — desain, tidak diubah. **View realtime lain ✅ (2026-11-14):** helper `resources/js/page-echo.js` → `window.pageEcho(channel).listen(...)`: listener ditautkan ke `#main-content` halaman itu dan di-`stopListening()` begitu jangkar lepas dari DOM (sapu saat daftar baru, tiap `htmx:afterSettle`, dan malas saat event datang). Dipakai di 12 view: `verifications/queue`, `tasks/show`, `invoices/index`, `fop/dashboard`, `partials/collector-realtime`, `noc/dashboard`, `tickets/history`, `tickets/partials/archive`, `tickets/show`, `tickets/create`, `fop_tasks/index`, `customers/show`. Ikut diperbaiki: 3 listener yang menunggu `DOMContentLoaded` (verifikasi, tagihan, detail pelanggan) → `window.onPageReady` (dulu tak pernah terpasang kalau halaman dibuka lewat navigasi boost). Penjaga `ListenerRealtimeHalamanTidakMenumpukTest` (view baru yang memanggil `window.Echo.private(` langsung = gagal, kecuali lonceng & Tasks Saya). Chrome (owner): NOC ↔ FOP Task 5× bolak-balik → tiap event tetap 1 callback, listener halaman sebelumnya terlepas; antrean verifikasi lewat boost kini punya listener. `dragover` `fop/dashboard` diselesaikan di P8. |
| P4b (Bug 1, toast + push dobel) | ✅ selesai (test + simulasi SW; perlu cek di device) | Laporan user 2026-11-13/14: setelah P4 masih 2 tanda per jadwal. Diagnosa console browser user: `Toast.show` cuma dipanggil 1× (lonceng), listener 1 — tanda kedua = notifikasi OS Web Push (`AppPushNotification`, ADHOC-288) yang selalu ditampilkan `public/sw.js`. Keputusan user: SW tidak menampilkan notifikasi OS selama ada tab Whusnet ber-`visibilityState === 'visible'`; ack delivery tetap dikirim; app tertutup/di belakang/HP terkunci ⇒ push tampil normal. Ditambah `skipWaiting()` + `clients.claim()` supaya SW baru langsung aktif. Test `PushTidakDobelSaatAplikasiTerbukaTest`; simulasi Node: terlihat ⇒ 0 notif/1 ack, belakang/tertutup ⇒ 1 notif/1 ack. **Opsi 2 ✅ (2026-11-14):** `AppPushNotification` menerima `$url` (path relatif = actionUrl lonceng) → payload `data.url`; diisi di TaskService (task.show), TicketService (actionUrl tiket), CollectorDepositService (worklist kolektor), CustomerController (detail pelanggan). Push ke pelanggan (Invoice/Payment, portal) sengaja tanpa url. `sw.js` `notificationclick`: resolve path relatif ke absolut (dulu `client.url === '/…'` tak pernah cocok ⇒ selalu tab baru), tolak origin lain, fokus tab yang sudah di tujuan → pakai ulang tab Whusnet lain (`navigate`) → baru `openWindow`. Test `KlikPushMembukaHalamanTujuanTest`; simulasi Node 5 skenario sesuai. |
| P5 (scanner) | ✅ selesai (test + Chrome kamera palsu) | `resources/js/barcode-scan.js` & `qr-scan.js`: pemasangan top-level module (cuma jalan sekali per tab) dibungkus `mountBarcodeScanners()` / `mountQrScanner()` — dipanggil saat module dimuat dan tiap `htmx:afterSettle`, elemen ditandai `data-barcode-mounted` / `data-qr-mounted` supaya tak dobel. Kamera dimatikan di `htmx:beforeSwap` (target `document.body`) + `pagehide`; registry scanner dibersihkan dari blok yang lepas DOM. Race ditutup: stream yang baru terbuka setelah halaman ditinggal langsung di-`stop()`. Tanpa perubahan di 6 view. Test `ScannerKameraNavigasiBoostTest`. Chrome (owner, `--use-fake-device-for-media-stream`): Scan Barang & Scan QR kunjungan ke-1 dan ke-2 kamera nyala (1 track), setelah pindah menu 0 track hidup. Catatan: 2 kegagalan `QrInAppScanPageTest` (GET dashboard 302) di luar P5. |
| P8 (minor) | ✅ selesai 2026-11-14 | Helper `window.pageListen(target, type, fn, opts)` di `<head>` layout: listener `document`/`window`/`matchMedia` diikat ke `#main-content` halaman itu, diam begitu jangkar lepas & dicopot di `htmx:afterSettle`. Dipakai 17 listener di 15 view (Escape modal verifikasi/user/alasan putus/pelanggan, klik dropdown cetak tagihan, `payment-recorded` tagihan & detail pelanggan, `task-list-updated`, dragover papan FOP, change/input batch kolektor, `ticket-drawer-action` worksheet NOC, resize riwayat tiket & form tiket, `package-category-created` paket) + shortcut keyboard & konfirmasi submit layout (dulu Ctrl+D men-toggle tema N kali — 2 listener = tema tak berubah). Banner koneksi realtime: `bind('state_change')` sekali per tab, banner baru ikut menyesuaikan status putus. `@stack('styles')`/`@yield('styles')` dirender di awal `<body>` (bukan `<head>` — head tidak ikut ditukar boost) → style `network/nodes` & `roles/matrix` kini aktif. `chart:ready` dashboard `{ once: true }`. Penjaga `ListenerGlobalTidakMenumpukTest`. Chrome (owner): FOP ↔ Master Node berulang → dragover 1 lalu 0 setelah pindah, keydown/submit konstan, Ctrl+D benar, `.tree-line::before` aktif lewat boost. |

Catatan test: 13 kegagalan di `FopTaskCreateFollowsTicketingTest`, `FopTaskCancelCascadeAuthTest`, `TaskReassignTest`,
`DeviceRetrievalHistoryAndWalkInTest` **sudah ada sebelum** perubahan ini (hasil identik dengan seeder versi HEAD). Bukan
regresi ADHOC-297, perlu ditangani terpisah.
**Terkait:** ADHOC-263 (SPA-feel htmx), ADHOC-267 (`hx-history="false"`), ADHOC-291 (form `:action` dinamis 405),
`docs/superpowers/specs/2026-11-03-spa-feel-htmx-design.md`.

---

## 1. Latar Belakang

`layouts/app.blade.php:127` memasang `<body hx-boost="true" hx-history="false">`. Artinya **setiap link dan
setiap `<form>` di seluruh aplikasi** dijalankan lewat htmx (2.0.11), bukan hanya 50 file yang menulis
"htmx" secara eksplisit. Navigasi boost menukar `innerHTML` seluruh `<body>`. Ini **bukan page load**:

- `DOMContentLoaded` / `load` **tidak** menyala lagi.
- `window`, `document`, `window.Echo`, dan module ES **tetap hidup** lintas navigasi. Akibatnya listener yang
  dipasang ke objek-objek itu menumpuk tiap kunjungan.
- `<script>` inline di body dieksekusi ulang tiap navigasi. `<script type="module" src>` (mis. `@vite(...)`)
  **hanya dieksekusi sekali per dokumen**.
- Form yang di-boost ditangani listener `submit` milik htmx di elemen form. Listener itu memanggil
  `preventDefault()` lalu langsung mengirim request, **tanpa peduli** apakah handler lain sudah membatalkan
  event-nya (`defaultPrevented` tidak dicek).
- Target (`action`/`method`) form di-cache saat htmx memproses node. Perubahan `action` lewat JS sesudahnya
  diabaikan.

### Metode verifikasi
- Audit pola lewat grep di `resources/views` dan `resources/js`, ditambah pembacaan source
  `node_modules/htmx.org/dist/htmx.esm.js` (fungsi `boostElement`, `addEventListener`, `duplicateScript`).
- Simulasi jsdom memakai **file htmx asli dari repo**. Hasilnya:
  | Skenario | Hasil |
  |---|---|
  | `<form onsubmit="return confirm(...)">`, user klik **Batal** | POST **tetap terkirim** |
  | Listener konfirmasi global di `document` (meniru `app.blade.php:1486`) | Dialog tampil **0 kali** |
  | `<form method="POST" target="_blank">` | POST ajax di tab yang sama |
  | `form.action` diubah JS setelah halaman dimuat | POST ke URL **lama** |

---

## 2. Temuan Audit htmx

### 2.1 🔴 KRITIS — Form & tombol

**K1. Dialog konfirmasi global tidak pernah muncul.**
`layouts/app.blade.php:1486`: listener `submit` (bubble, di `document`) keluar lebih awal karena
`e.defaultPrevented` sudah `true` (diset htmx). Dampaknya, semua form POST/PUT/DELETE yang di-boost
(±324 dari 336 form; hanya 12 yang `hx-boost="false"`) tersimpan atau terhapus **tanpa** dialog
"Konfirmasi Simpan/Ubah/Hapus".

**K2. `preventDefault()` dari handler lain diabaikan, sehingga form tetap terkirim atau terkirim dobel.**
| Pola | Lokasi | Dampak |
|---|---|---|
| `onsubmit="return confirm(...)"` | ±10 form: hapus node kosong, terbitkan tagihan instalasi, hapus kategori, tutup tiket "Sudah Dipenuhi", periode hapus buku, tarik/pulihkan panduan, hapus perangkat tambahan | Klik **Batal** tetap mengeksekusi |
| `onsubmit="event.preventDefault(); window.confirmAction(...)"` | 8 form (Kembalikan…, Buat Task…, device retrieval…) | Request langsung terkirim, lalu "Lanjutkan" memanggil `form.submit()` sehingga terkirim **2×** |
| `addEventListener('submit')` konfirmasi lebih bayar | `payments/create.blade.php:489` | Lebih bayar tersimpan **tanpa** konfirmasi |
| Bayar Cepat (form tanpa `method`/`action`) | `payments/partials/quick-payment-modal.blade.php:6`, `:733` | Fetch POST jalan, **dan** htmx melakukan GET ke halaman sekarang dengan isi form di query string. Body ditukar, modal hilang, URL kotor |
| `onsubmit="submitTerminationModal(event)"` (`action=""`) | `customers/partials/_termination_modal.blade.php:35` | htmx ikut POST ke URL halaman sekarang (`/customers` = `customers.store`) |
| `onsubmit="submitReasonModal(event)"` | `master/customer-termination-reasons/index.blade.php:207` | Dobel: fetch + POST htmx |
| `onsubmit="return confirmRoleDestroy(...)"`, `handleFormSubmit(this)` | roles, dll. | Sama seperti baris pertama |
| Submit listener lain | `verifications/admin.blade.php:1662`, `tasks/maintenance-report.blade.php:690`, `qr/attendance.blade.php:57`, `master/pop/*`, `master/distribusi/*` | Perlu dicek satu per satu: kalau listener-nya `preventDefault()`, kena pola yang sama |

**K3. Form yang `action`-nya diubah JS belakangan terkirim ke URL lama** (pola ADHOC-291).
| Form | Lokasi | Dampak |
|---|---|---|
| `#rejectForm` | `verifications/queue.blade.php:142` (`action=""`, diset di `:336`) | POST ke halaman antrean |
| `#roleForm` mode Edit | `roles/index.blade.php:364`, `:542` | Edit role terkirim ke `roles.store` |
| `#cancel-survey-modal-form` | `surveys/queue.blade.php:184`, `:198` | POST ke halaman antrean survey |
| `.hub-document-form` | `customers/partials/_quick_hub_modal.blade.php:372` (`_list_scripts.blade.php:435`) | Upload dokumen ke URL salah |
| `#package-change-form` | `customers/partials/_package_change_modal.blade.php:35` (`_list_scripts.blade.php:663`) | Ganti paket ke URL salah |
| `#network-assignment-form` | `customers/partials/_network_assignment_modal.blade.php:35` (`_network_assignment_js.blade.php:34`) | Penempatan jaringan ke URL salah |
| `#reason-modal-form` mode Edit | `master/customer-termination-reasons/index.blade.php:298` | Edit terkirim ke `store` |

Form yang dibuat lewat `document.createElement('form')` lalu `form.submit()` (mis. `tickets/show.blade.php:1146`)
**aman**, karena tidak pernah diproses htmx dan submit-nya native. Form dengan `:action` statis
(warehouse/scan, network/nodes, fop_tasks) juga **aman**: htmx memproses node baru setelah settle (±20 ms),
yaitu sesudah Alpine mengikat atribut.

**K4. Form `target="_blank"` tidak membuka tab baru.**
Cetak Kwitansi di `collector-worksheet/show.blade.php:415` dan `:716` dikirim lewat ajax, lalu kwitansinya
menimpa halaman worksheet di tab yang sama. Fungsi `boostElement` htmx mengecek `target` hanya untuk `<a>`,
tidak untuk `<form>`.

### 2.2 🟠 TINGGI — Script halaman mati setelah navigasi boost

**T1. ±30 handler `DOMContentLoaded` tidak jalan kalau halaman dibuka lewat klik menu** (normal kalau F5).
| Area | Lokasi |
|---|---|
| Format awal kolom rupiah (global) | `layouts/app.blade.php:1817` |
| Cascade wilayah (combobox kecamatan/desa) | `partials/wilayah-cascade-script.blade.php:64` |
| Filter POP → Mini POP → Distribusi | `customers/create.blade.php:704`, `customers/edit.blade.php:1018`, `customers/partials/_network_assignment_js.blade.php:104` |
| Toggle metode bayar / kategori | `payments/create.blade.php:375`, `payments/edit.blade.php:310`, `invoices/create.blade.php:154` |
| Buka ulang modal tolak setelah error validasi | `payments/show.blade.php:666` |
| Wizard laporan | `surveys/report.blade.php:785`, `installations/report.blade.php:1485`, `tasks/maintenance-report.blade.php:732`, `:779` |
| Master | `master/pop/create.blade.php:231`, `master/pop/edit.blade.php:246`, `master/distribusi/create.blade.php:95`, `master/distribusi/edit.blade.php:96`, `master/wilayah.blade.php:94` |
| RBAC & user | `roles/index.blade.php:504`, `roles/matrix.blade.php:952`, `:1166`, `users/_form.blade.php:335` |
| Verifikasi | `verifications/queue.blade.php:376` (countdown macet "Menghitung..."), `:493`, `verifications/admin.blade.php:1656` |
| Realtime Echo | `invoices/index.blade.php:398`, `customers/show.blade.php:1793` |
| Grafik dashboard | `dashboard.blade.php:1188` (tertolong jalur `chart:ready`) |

Satu-satunya yang sudah dijaga adalah `components/toast.blade.php:255` (`if (document.readyState === 'loading')`).

**T2. Scanner barcode/QR mati di kunjungan ke-2, dan kamera tetap menyala.**
`resources/js/barcode-scan.js:686` dan `resources/js/qr-scan.js:153` mengikat DOM di top-level module.
Module hanya dieksekusi sekali per dokumen, jadi kunjungan berikutnya ke `warehouse/scan`, `receive/create`,
`transfers/create|show`, `issues/create`, atau `qr/scan` membuat tombol scanner mati. Kamera hanya
dihentikan di `pagehide` (`barcode-scan.js:759`, `qr-scan.js:176`), padahal event itu tidak menyala saat
navigasi boost.

**T3. Listener Echo menumpuk tiap navigasi, menyebabkan toast dan aksi dobel.**
Komponen `notification-dropdown` di header (`components/notification-dropdown.blade.php:94`) memanggil
`Echo.private(...).notification(...)` setiap `init()` tanpa guard. Setelah N kali pindah halaman,
1 notifikasi menghasilkan N toast. Pola yang sama (listen tanpa guard dan tanpa `stopListening`):
`tasks/own.blade.php:360` (**akar bug user #1**), `noc/dashboard.blade.php:584`, `tickets/history`,
`tickets/partials/archive`, `partials/collector-realtime`, `verifications/queue`, `tasks/show`.
`fop/dashboard`, `fop_tasks/index`, `customers/show`, `tickets/show|create` sudah punya guard parsial;
perlu dicek ulang.

### 2.3 🟡 MINOR
- `@push('styles')` (`network/nodes/index.blade.php:6`) dan `@section('styles')` (`roles/matrix.blade.php:1180`)
  tidak pernah dirender karena layout tidak punya `@stack('styles')`/`@yield('styles')`. Bug ini sudah ada
  sebelum htmx.
- Listener global di layout (`keydown` `:1398`, `submit` `:1486`, `input` `:1804`, `submit` capture `:1825`)
  dan listener `document` di beberapa view (`invoices/show:758`, `collector-pay-script:97,593`, `users/_form:498`,
  dst.) bertambah satu tiap navigasi. Saat ini idempoten, tapi tetap bocor memori.
- NOC dashboard: kalau `charts.js` belum termuat saat datang lewat boost, grafik tab Analitik baru muncul
  setelah tab diklik (tidak ada listener `chart:ready`).

### 2.4 ✅ Sudah aman
- Semua link export/download/print sudah `hx-boost="false"` atau `target="_blank"` (28 titik dicek).
- Registrasi `alpine:init` sudah dijaga (`pop-filter`, `wilayah-filter`, `verifications/queue`,
  `reports/collector-monthly`, `customers/create|edit|show`).
- Tidak ada `<script src>` CDN di body aplikasi.
- Kolom rupiah dengan nilai awal dari server sudah diformat di server (`FormatHelper::rupiahInput`), jadi
  T1 untuk rupiah hanya kosmetik, bukan risiko nominal ×100.

---

## 3. Tiga Bug Laporan User

### Bug 1 — Tasks Saya: 1 task dijadwalkan, muncul 3 notifikasi + 3 kartu

**Gejala:** FOP menjadwalkan 1 task, tapi di halaman Tasks Saya teknisi muncul 3 toast dan 3 kartu task yang sama.

**Akar masalah (klien, pasti):** kombinasi T3 dan race di `injectTaskCard()`.
1. `technicianNotifier().init()` (`tasks/own.blade.php:351-383`) memasang
   `Echo.private('teknisi.{id}').listen('TaskScheduled', ...)` setiap kali halaman Tasks Saya dibuka.
   Channel Echo tetap hidup lintas navigasi boost, dan listener lama tidak pernah dilepas. Teknisi yang
   bolak-balik Tasks Saya → Detail → Tasks Saya sebanyak 3× punya 3 listener aktif.
2. Satu event `TaskScheduled` memicu 3 handler. Masing-masing menampilkan toast, jadi muncul 3 toast.
3. `injectTaskCard()` (`:430`) mengecek `getElementById('task-card-{id}')` **sebelum** `await fetchCard()`.
   Ketiga handler lolos cek bersamaan (kartu belum ada), ketiganya fetch, lalu ketiganya menyisipkan kartu.
   Hasilnya 3 kartu dengan `id` dobel.
4. Ditambah lagi `notification-dropdown` (T3) yang ikut melipatgandakan toast dari notifikasi database.

**Kemungkinan kontribusi server (perlu diverifikasi):** jalur jadwal FOP memanggil
`TaskService::notifyTeam()` (broadcast `TaskScheduled` + `AppNotification`) dan, di beberapa cabang,
`broadcast(new TaskScheduled(...))` langsung (`TaskService.php:772`) atau `FopTaskController::broadcastSwitchedExecutionTask()`
(`:1378`). Di working tree sudah ada pekerjaan belum commit (`FopTaskAssignToTeamNotificationFloodTest`) untuk
flood notifikasi "Task Dilepas". Perlu test yang menghitung **jumlah broadcast `TaskScheduled` dan
`AppNotification` per 1 aksi jadwal FOP**, untuk memastikan server mengirim tepat 1.

**Rencana perbaikan:**
- Guard listener per halaman: simpan handle di `window.__taskOwnEcho`. Sebelum memasang listener baru,
  panggil `stopListening` pada listener lama, atau pasang sekali saja dan arahkan handler ke komponen aktif.
  Lepas listener di `htmx:beforeSwap`.
- `injectTaskCard()`: tandai task yang sedang di-fetch (`Set` in-flight), lalu cek ulang keberadaan kartu
  **setelah** `await` sebelum menyisipkan.
- `notification-dropdown`: listener `.notification()` dipasang **sekali per dokumen** (flag global), dan
  handler menulis ke store Alpine, bukan ke `this` komponen yang sudah dibuang.
- Test server: `TaskScheduledBroadcastSekaliPerJadwalTest` dengan `Event::fake([TaskScheduled::class])` dan
  `Notification::fake()` untuk jalur `store`, `update`, `assignToTeam`, `bulkAssignTeam`.

### Bug 2 — Tombol Laporan hilang untuk Pemasangan, Maintenance, C-REQ, O-REQ, INFR-REQ

**Gejala:** teknisi tidak bisa masuk halaman laporan karena tombol "Isi Laporan" / "Lanjutkan Laporan" tidak ada.

**Cara tombol dirender (server-side, bukan Alpine):** `tasks/partials/own-card.blade.php:244-264` dan
`tasks/show.blade.php:1250-1272`. Tombol hanya ada kalau **dua** syarat terpenuhi:
1. `$task->status->acceptsReport()`, yaitu status `in_progress` atau `lapor_nanti`. Task `terjadwal` memang
   belum punya tombol laporan, hanya tombol **Mulai**.
2. `@can('statusComplete', $task)` → `TaskPolicy::statusComplete()` (`TaskPolicy.php:283`) → `isMember()`
   **dan** `canTransition($user, 'in_progress', 'selesai')`. Pengecekan kedua membaca tabel
   `workflow_transition_permissions` + pivot role, dan harus ada baris `in_progress → selesai` yang terhubung
   ke role `teknisi` dengan permission `task.execute` (`WorkflowTransitionPermissionSeeder.php:55-58`).

**✅ AKAR MASALAH TERKONFIRMASI (cek DB container 2026-11-13, user juga konfirmasi tombol Survey ikut hilang):**
Teknisi yang menguji memegang role **`pic_gudang`** (teknisi merangkap PIC gudang, `Role::TECHNICIAN_CODES =
['teknisi','pic_gudang']`). Tapi `WorkflowTransitionPermissionSeeder` menautkan semua transisi `task.execute`
hanya ke `['owner','admin','teknisi']`. Akibatnya `canTransition()` gagal untuk role `pic_gudang`:

| Task (DB lokal) | Tipe | Status | Anggota | `statusComplete` | `statusStart` |
|---|---|---|---|---|---|
| #2 | SURVEY | in_progress | u3 `teknisi` | **1** | 0 |
| #6 | PSB | in_progress | u4 `pic_gudang` | **0** → tombol laporan hilang | 0 |
| #4 | PSB | pending | u4 `pic_gudang` | 0 | 1 (lewat fallback) |

Jadi **ini bukan bug htmx**, tapi kasus "satu orang dua fungsi" yang belum tertutup di aturan transisi task.
Kondisi ini berlaku untuk **semua** tipe task (Survey ikut hilang), sesuai laporan user. Untuk C-REQ/O-REQ/INFR REQ,
`statusStart` juga gagal (hanya MAINTENANCE yang punya fallback `TaskPolicy.php:273`), jadi task tipe itu bahkan tidak
bisa dimulai oleh PIC gudang.

**Perbaikan:** di `WorkflowTransitionPermissionSeeder`, ganti `'teknisi'` dengan `...Role::TECHNICIAN_CODES` pada
semua transisi `task.execute` (`terjadwal→in_progress`, `in_progress→selesai`, `terjadwal→pending`,
`in_progress→pending`, `pending→pending`), lalu jalankan ulang seeder itu di semua environment (aman: seeder ini memang
men-*truncate* dan mengisi ulang tabelnya sendiri). Policy **tidak** diubah. Test regresi
`TombolLaporanPicGudangMunculTest`: data provider `teknisi`/`pic_gudang` × semua `TaskType` → link laporan tampil di
`tasks.own` & `tasks.show`, dan tombol Mulai tampil untuk status `terjadwal`.

Catatan: perlu dicek apakah ada titik lain yang masih mencari teknisi lewat `where('code','teknisi')` di jalur task
(lihat memori proyek "teknisi merangkap PIC gudang").

**Dugaan awal (disimpan sebagai riwayat analisa):**
- **A. Data RBAC transisi.** Kalau baris `in_progress → selesai` hilang atau tidak terhubung ke role
  `teknisi` (seeder men-*truncate* tabel ini tiap jalan, catatan di `TaskPolicy.php:293-297`), maka
  `statusComplete` = false untuk **semua** tipe. Untuk C-REQ/O-REQ/INFR REQ, `statusStart` juga butuh baris
  `terjadwal → in_progress` (hanya MAINTENANCE yang punya fallback di `TaskPolicy.php:273`), sehingga task
  tidak bisa dimulai, tetap `terjadwal`, dan tombol laporan tidak pernah muncul.
  → **Pertanyaan diagnostik:** apakah tombol laporan **Survey** masih muncul? Kalau Survey juga hilang,
  hampir pasti penyebabnya A.
- **B. Status task tidak pernah sampai `in_progress`.** Tombol **Mulai** (`tasks.start`,
  `customers.installation.start`) adalah form yang di-boost. Kalau responsnya error, `htmx:responseError` di
  `app.js` jatuh ke `window.location.href = path`, yaitu GET ke URL POST, yang menghasilkan 405 dan tampak
  "tidak terjadi apa-apa". Perlu dicek di log nginx/Laravel.
- **C. Kartu dari realtime.** Kartu yang disisipkan ulang lewat `fetchCard()` (`/tasks-saya/partial/{id}`)
  memakai view dan policy yang sama, jadi seharusnya identik. Tapi akibat Bug 1, kartu dobel ber-`id` sama
  bisa membuat `refreshTaskCard()` mengganti kartu yang salah, sehingga kartu lama berstatus `terjadwal`
  (tanpa tombol laporan) tetap tampil.

**Langkah verifikasi:**
```bash
docker compose exec -u www-data app php artisan tinker --execute '
  dump(App\Models\WorkflowTransitionPermission::with("roles:id,code")->get(["id","from_status","to_status","permission_name"])->toArray());
  dump(App\Models\Role::where("code","teknisi")->first()?->permissions()->pluck("name")->filter(fn($n)=>str_starts_with($n,"task."))->values());
'
```
Lalu login sebagai teknisi dan cek satu task `in_progress` di Detail Task: apakah tombolnya muncul?

**Rencana perbaikan:** tergantung hasil verifikasi.
- Kalau A: perbaiki data lewat seeder (jangan ubah policy). Tambah test regresi
  `TombolLaporanTeknisiMunculSemuaTipeTaskTest`, dengan data provider per `TaskType` × status `in_progress`/`lapor_nanti`
  yang meng-assert link laporan ada di `tasks.own` dan `tasks.show`.
- Kalau B: ikut perbaikan K1/K2 (form keluar dari boost), plus `htmx:responseError` jangan me-redirect GET untuk verb non-GET.
- Kalau C: ikut perbaikan Bug 1.

### Bug 3 — Combobox alat/barang yang dipegang teknisi (PSB, Maintenance, dll.)

**Ekspektasi user:** combobox menampilkan **barang yang dibawa teknisi berdasarkan penjadwalan/pengeluaran
Gudang (custody tim)**. Setelah dipilih, baris menampilkan **Nama Kategori, Nama Barang, Jumlah, Catatan**.

**Kondisi kode sekarang:** `components/installation-item-rows.blade.php` dipakai Laporan Pemasangan
(`installations/report.blade.php:791`) dan Laporan Maintenance/C-REQ/O-REQ/INFR REQ
(`tasks/maintenance-report.blade.php:274`).
- Satu combobox "Barang (custody tim)" berisi gabungan SN modem (`eligibleSerials`), roll kabel
  (`eligibleRolls`), dan barang pasif (`eligiblePassiveCustody`), dengan label `"{kode} — {nama} (sisa X unit)"`.
- Setelah dipilih:
  - **pasif**: hanya kolom **Jumlah** + **Catatan**. Kategori dan Nama Barang **tidak** ditampilkan
    sebagai kolom; kategori hanya disimpan di hidden input `item_type`.
  - **roll**: hanya **Meter Terpakai**.
  - **modem**: tidak ada kolom tambahan.
- `tasks/partials/customer-report-extra.blade.php:9` memakai komponen lain (`material-rows` mode custody)
  yang **punya** kolom Kategori, Nama Barang, Jumlah, Satuan, Catatan. Jadi bentuknya tidak seragam antar halaman.

**Kemungkinan sumber "bug":**
- **a. Tampilan tidak sesuai ekspektasi.** Kolom Kategori dan Nama Barang memang tidak ada di komponen gabungan
  (desain `docs/superpowers/specs/2026-11-02-unify-dropdown-item-psb-design.md`).
- **b. Opsi kosong atau salah.** Daftar custody dihitung per **tim task**
  (`eligibleSerialsForTeam`/`eligibleRollsForTeam`/`eligiblePassiveCustodyForTeam`). Kalau barang di-issue Gudang
  ke teknisi yang bukan anggota tim task tersebut, atau tim berubah setelah issue, barangnya tidak muncul.
- **c. Efek htmx (T1).** Halaman laporan dibuka lewat boost, sehingga `DOMContentLoaded` di
  `installations/report.blade.php:1485` dan `tasks/maintenance-report.blade.php:732/779` tidak jalan. Progress
  wizard, toggle billable, dan toggle field C-REQ tidak terinisialisasi. Ini bisa membuat bagian form tampak rusak.
  Combobox sendiri (Alpine, fungsi global via `@push('scripts')`) seharusnya tetap jalan.

**Konfirmasi user (2026-11-13):** "barang sudah dijadwalkan ke teknisi, tapi saat pemasangan PSB barang yang dibawa
tidak bisa di-install."

**Hasil verifikasi server (DB container, task PSB #6, anggota u4):**
- Custody u4: 5 SN modem ZTE F609 (`issued`, kondisi `new` → lolos `isClearedForIssue()`), 2 roll drop core
  (`issued`, sisa 1000 m), 0 barang pasif.
- Halaman `customers.installation.report` dirender sebagai u4 → **HTTP 200, semua SN dan roll ada di data
  `installationItemRows(...)`**. Jadi query custody (`eligibleSerialsForTeam` dkk.) **benar**; dugaan (b) gugur.

**Kesimpulan:** kegagalan ada di **browser**, yaitu di halaman laporan yang dibuka lewat navigasi boost. Bukti dari
working tree: sudah ada perbaikan **parsial yang belum di-commit** (2026-11-11) untuk gejala persis ini:
- `installations/report.blade.php:243`: `overflow-hidden` dicabut karena memotong daftar combobox, sehingga barang tidak
  bisa dipilih.
- `installations/report.blade.php:1324`: `let`/`const` top-level diganti `var`. Kalau survey report dibuka lalu PSB
  report lewat boost, terjadi SyntaxError "already declared" yang memutus script sesudahnya (comboBox dan baris
  barang custody). Penjaganya `TopLevelLetConstBoostRedeclareTest`.

**Yang masih tersisa:**
1. `installations/report.blade.php:1345` dan `:1347` masih `const timerStartedAt` / `const hiddenStartedAtInit` (di
   dalam `@if`, indentasi lebih dalam, jadi lolos dari regex test penjaga). Kunjungan **kedua** ke laporan PSB yang
   pemasangannya sudah dimulai tetap memicu SyntaxError redeclare, dan seluruh blok script wizard mati.
2. `installations/report.blade.php:1485`: inisialisasi wizard di `DOMContentLoaded` (listener progress,
   `enableAktivasiButton`, `updateWizardButtons`, `goToStep` setelah `?activated=1`) **tidak jalan** kalau halaman
   dibuka dari tombol "Lapor Sekarang"/"Lanjutkan Laporan" (link yang di-boost). Pola yang sama ada di
   `tasks/maintenance-report.blade.php:732`, `:779`. Ini T1.
3. Belum dicoba ulang di browser setelah perbaikan 2026-11-11 (status uji manual tidak tercatat).

**Rencana perbaikan:**
- Ganti 2 `const` tersisa menjadi `var` dan perketat regex `TopLevelLetConstBoostRedeclareTest` supaya juga menangkap
  deklarasi di dalam blok `@if`/`@foreach` Blade (yang bukan blok JS).
- Inisialisasi wizard pindah ke `onPageReady()` (P3).
- Uji manual: Tasks Saya → Lapor Sekarang (boost) → pilih SN + roll → Aktivasi → Speedtest, dua kali berturut-turut tanpa F5,
  dengan akun `teknisi` dan `pic_gudang`.
- (Kosmetik, opsional) tampilkan kolom Kategori + Nama Barang setelah barang dipilih (kemungkinan a), kalau user
  masih menginginkannya.

**Rencana perbaikan (draf, setelah konfirmasi):**
- Kalau a: setelah barang dipilih, tampilkan kolom **Kategori** dan **Nama Barang** (read-only, diturunkan dari
  opsi custody) + **Jumlah** + **Catatan** untuk semua mekanisme (modem: Jumlah tetap 1, roll: Jumlah = meter).
  Nama field yang dikirim ke server tidak berubah, jadi backend tetap aman (prinsip komponen ini).
- Kalau b: periksa aturan kelayakan custody per tim vs per teknisi, dengan test berbasis data Gudang.
- Kalau c: ikut perbaikan T1.

---

## 4. Rencana Perbaikan Bertahap

Urutan berdasarkan risiko data → fungsi → kenyamanan. Tiap tahap punya test dan bisa di-commit sendiri.

| Tahap | Isi | File utama | Menutup |
|---|---|---|---|
| **P1** | **Keluarkan semua `<form>` dari boost** di satu tempat: listener `htmx:beforeProcessNode` di `resources/js/app.js` yang memberi `hx-boost="false"` pada setiap `FORM` sebelum htmx memprosesnya. Form kembali submit native seperti sebelum ADHOC-263; navigasi link tetap instan. Alternatif ditolak: menambah `hx-boost="false"` ke ±324 form (gampang terlewat di form baru), atau memindahkan konfirmasi ke `htmx:confirm` (tidak menolong `onsubmit` / fetch handler). | `resources/js/app.js` | K1, K2, K3, K4, Bug 2-B |
| **P2** | `htmx:responseError` hanya fallback `location.href` untuk request GET. | `resources/js/app.js` | Bug 2-B |
| **P3** | Helper `window.onPageReady(fn)`: jalankan langsung kalau `document.readyState !== 'loading'`, kalau tidak tunggu `DOMContentLoaded`. Ganti ±30 pemanggilan `DOMContentLoaded`. | `layouts/app.blade.php` + view di T1 | T1, Bug 3-c |
| **P4** | Siklus hidup realtime: pasang listener Echo sekali (flag global) atau lepas di `htmx:beforeSwap` (`stopListening`); in-flight guard di `injectTaskCard()`; `notification-dropdown` sekali per dokumen. | `notification-dropdown`, `tasks/own`, `noc/dashboard`, `tickets/*`, `collector-realtime`, `verifications/queue`, `tasks/show` | T3, Bug 1 |
| **P5** | Scanner: ekspor `mountBarcodeScanners(root)` / `mountQrScanner()` yang dipanggil dari `onPageReady` di view; hentikan kamera di `htmx:beforeSwap`. | `resources/js/barcode-scan.js`, `resources/js/qr-scan.js`, 6 view scan | T2 |
| **P6** | Bug 2: tautkan transisi `task.execute` ke `Role::TECHNICIAN_CODES` (bukan hanya `teknisi`) + jalankan ulang seeder. **Bisa dikerjakan paling dulu**, karena terpisah dari htmx. | `database/seeders/WorkflowTransitionPermissionSeeder.php` | Bug 2 |
| **P7** | Bug 3: 2 `const` tersisa → `var`, perketat test penjaga, init wizard via `onPageReady`. | `installations/report.blade.php`, `tasks/maintenance-report.blade.php`, `TopLevelLetConstBoostRedeclareTest` | Bug 3 |
| **P8** | Minor: tambah `@stack('styles')` di `<head>` layout; guard listener global layout supaya hanya dipasang sekali. | `layouts/app.blade.php` | 2.3 |

### Risiko
- **P1** mengembalikan perilaku *full page reload* setelah submit form. Ini disengaja: perilaku itu yang dirancang
  untuk dialog konfirmasi, PRG, dan `withErrors()->withInput()`. Komentar `toast.blade.php:201` (flash setelah
  submit boost) perlu dicek ulang karena asumsinya berubah.
- **P1** harus memastikan form yang memang sengaja di-boost (kalau ada) tetap bisa opt-in, mis. dengan
  `data-boost-form`. Saat ini tidak ditemukan form yang membutuhkannya.
- **P4** menyentuh banyak view realtime. Kerjakan per halaman, uji manual dengan 2 akun (FOP + teknisi).

---

## 5. Rencana Test

| Test (nama sesuai gejala) | Jenis | Isi |
|---|---|---|
| `FormTidakDiBoostHtmxTest` | Feature (render) | Layout memuat script opt-out form; `hx-boost="true"` tetap ada di body |
| `TaskScheduledBroadcastSekaliPerJadwalTest` | Feature | 1 aksi jadwal FOP → tepat 1 `TaskScheduled` per teknisi + 1 `AppNotification` |
| `TombolLaporanTeknisiMunculSemuaTipeTaskTest` | Feature, `#[DataProvider]` | Semua `TaskType` (non-auto) × `in_progress`/`lapor_nanti` → link laporan ada di `tasks.own` & `tasks.show` |
| `DomContentLoadedTanpaGuardTest` | Feature (scan view) | Tidak ada `addEventListener('DOMContentLoaded'` mentah di `resources/views` selain helper `onPageReady` (pola `PostTargetRenderedServerSideTest`) |
| `EchoListenerTanpaGuardTest` | Feature (scan view) | Setiap `Echo.private(`/`Echo.channel(` di view memakai helper/guard |
| Uji manual browser | Manual | Hapus dengan Batal, edit role, putus langganan, Bayar Cepat, lebih bayar, cetak kwitansi, wilayah cascade setelah klik menu, scanner 2× kunjungan, jadwal FOP → teknisi 1 toast 1 kartu |

Aturan repo: hanya jalankan file test yang terdampak, bukan full suite.

---

## 6. Pertanyaan Terbuka untuk User

1. ~~Bug 2: Survey juga hilang?~~ **Terjawab: ya.** Akar masalah terkonfirmasi (role `pic_gudang`, §3 Bug 2).
2. ~~Bug 3: gejala persisnya?~~ **Terjawab:** barang custody tidak bisa di-install saat PSB. Server terverifikasi benar,
   sisa masalah ada di sisi browser (§3 Bug 3).
3. **P1:** setuju semua form kembali submit native (reload penuh setelah simpan), dengan navigasi link tetap instan?
   (Dijelaskan ke user 2026-11-13; menunggu keputusan.)
