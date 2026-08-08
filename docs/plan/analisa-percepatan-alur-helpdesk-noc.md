# DONE
# Analisa — Percepatan Alur Kerja Helpdesk & NOC

Status: **Analisa/Rencana, belum dieksekusi.** Ditulis 2026-08-05, fokus utama Helpdesk (paling sering pakai worksheet, volume tiket tertinggi), NOC ikut kebagian karena numpang komponen yang sama.

## 1. Konteks

Worksheet Helpdesk (`tickets/create.blade.php`) udah lumayan dioptimasi di iterasi-iterasi sebelumnya:
- Submit ticket stay-on-page via `fetch()` JSON (bukan PRG full-reload).
- Detail buka drawer kanan (bukan pindah halaman) — gak kehilangan filter/scroll/form.
- Quick-dispatch button (Selesai/Ke NOC/Ke FOP/Kembalikan) langsung di baris tabel, gak perlu buka detail dulu.
- Duplicate-check tiket (`tickets.duplicates`) & lookup customer async, server-side, gak kena cap tampilan 30.
- Priority auto-terisi dari `default_priority` kategori (JS, `create.blade.php:1367`).
- Target SLA (lihat `docs/plan/analisa-target-sla-ticketing.md`, diimplementasi sesi sebelumnya).

Analisa ini nyari titik gesekan yang TERSISA — bukan mengulang yang udah ada.

## 2. Temuan

### 2.1 Modal konfirmasi wajib di SEMUA aksi, walau alasan opsional (titik terbesar)

`window.confirmTicketAction()` (`resources/views/tickets/partials/action-dialog.blade.php`) dipanggil dari SEMUA tombol aksi tiket — Selesai, Ke NOC, Ke FOP, Kembalikan, Batalkan — di tiga tempat: worksheet Helpdesk, halaman arsip, Worksheet NOC.

Tapi validasi server (`TicketController`) cuma mewajibkan `reason` buat **Batalkan** (`'reason' => ['required', ...]` di `cancel()`); Close/Escalate/Return semua `nullable`. Praktiknya, buat 4 dari 5 aksi, Helpdesk:
1. Klik tombol (mis. "Ke FOP" — aksi paling sering, tiap tiket MTN/C-REQ yang butuh lapangan).
2. Modal muncul, textarea kosong dibiarin.
3. Klik "Ya, Lanjutkan" lagi.

Dua klik + render/animasi modal buat aksi yang gak butuh input apa pun. Dikali puluhan tiket/hari per Helpdesk, ini ongkos waktu terbesar yang kelihatan dari kode.

### 2.2 Urutan default worksheet = `latest('created_at')`, bukan urgensi

`TicketController::worksheetTasks()` → `->latest('created_at')`. Tiket Urgent yang masuk lebih awal bisa kegeser ke bawah kalau tiket baru terus masuk — Helpdesk harus scan manual nyari yang paling mendesak, padahal `FopTaskController` udah punya pola `priorityOrderCaseSql()`/`priorityOrderBindings()` buat sorting priority yang bisa dipakai lagi di sini.

### 2.3 Gak ada keyboard shortcut

Semua aksi (buka detail, Selesai, Ke NOC, Ke FOP, cari) mouse-only. Ini kerjaan repetitif volume tinggi — kandidat kuat buat hotkey, tapi belum ada sama sekali.

### 2.4 Gak ada canned response/template keluhan per kategori

`detail_keluhan` diketik manual tiap tiket. Kategori yang sama (mis. "Internet Mati Total") sering berulang keluhannya mirip — belum ada prefill/template yang bisa diedit, cuma `default_priority` yang keisi otomatis.

### 2.5 Gak ada alert aktif buat tiket baru masuk

Broadcast `TicketQueueUpdated` (Reverb) diterima dan array `tasks` di-refresh diam-diam — gak ada toast/badge/sound yang narik perhatian Helpdesk kalau lagi fokus ngerjain tiket lain waktu tiket baru (apalagi Urgent) masuk.

## 3. Solusi Diusulkan (urut dampak/effort)

| # | Solusi | Dampak | Effort | Catatan |
|---|---|---|---|---|
| 1 | **Skip modal buat Close/Ke NOC/Ke FOP/Kembalikan** — 1 klik langsung fire aksi (`reason: null`). Tombol kecil opsional "+ catatan" buat yang MEMANG mau isi alasan (textarea inline, non-blocking). Batalkan TETAP pakai modal (reason wajib, aksi riskan/gak bisa dibatalkan). | Tinggi — motong 2 klik+modal di aksi paling sering | Kecil | Ganti pemanggilan `confirmTicketAction()` di 4 dari 5 tombol; `performTicketAction()` yang sudah ada tinggal dipanggil langsung dengan `reason: null`. |
| 2 | **Default sort worksheet: priority/SLA dulu, baru created_at** — reuse `FopTaskController::priorityOrderCaseSql()`/`priorityOrderBindings()` (extract ke helper bersama biar gak diduplikasi, sesuai prinsip "satu sumber kebenaran" yang udah dipegang di file itu). | Tinggi — Helpdesk kerja dari daftar udah tersortir | Kecil | `worksheetTasks()` tambah `orderByRaw(priority CASE)` sebelum `latest('created_at')`. |
| 3 | **Toast "N tiket baru" (+ opsional sound)** pas `TicketQueueUpdated` diterima, bukan silent-refresh. | Sedang | Kecil | Perlu keputusan desain: sound on/off toggle, atau selalu senyap. |
| 4 | **Keyboard shortcut** (row focus + `C`=Close, `F`=Ke FOP, `N`=Ke NOC, `/`=search) | Sedang, tinggi buat power user | Sedang | Perlu mapping key yang gak bentrok sama browser/OS shortcut, dan indikator visual row-focus. |
| 5 | **Template keluhan per kategori** (prefill `detail_keluhan`, tetap editable) | Sedang | Sedang | Nambah kolom baru di `TicketIssueCategory` (mis. `complaint_template`), form CRUD Master Kategori Tiket ikut berubah. |

## 4. Kenapa #1 & #2 Direkomendasikan Duluan

- **Risiko rendah** — gak sentuh data, gak sentuh otorisasi/scope, gak ubah struktur tabel. Murni UX/query-ordering.
- **Dampak langsung kerasa** — kedua titik ini kepakai di JALUR TERSERING (tiap tiket yang di-dispatch, tiap kali buka worksheet).
- #3-5 butuh keputusan desain tambahan (sound on/off, key mapping, isi default template) sebelum bisa dieksekusi — lebih pas didiskusikan/diputuskan dulu daripada langsung dikerjain.

## 5. Belum Dieksekusi

Semua 5 poin di atas masih rencana. User pilih "catat dulu ke docs/plan" — belum ada kode yang diubah dari analisa ini. Kalau mau lanjut, mulai dari #1+#2 (lihat §4).

## 6. Spec Final — Shortcut Keyboard Worksheet Helpdesk (poin #4, dirancang user)

Hasil diskusi lanjutan (2026-08-05), menajamkan poin #4 di §3 jadi spec konkret. Semua ini masih **rencana, belum dieksekusi**.

### 6.1 Sort kolom tabel (klik header = toggle ASC/DESC)

| Kolom | Sortable? | Field yang dipakai |
|---|---|---|
| Ticket ID & Time | Ya | `ticket_number` (kode tiket, TKT-2026-0001 dst — alfanumerik, BUKAN `created_at`) |
| Status / Issue | Ya | `issue_category` (nama kategori issue, BUKAN `status_label`) |
| Pelanggan (CID & Contact) | **Tidak** — sengaja gak sortable | — |
| Lokasi / POP / ODP | Ya | `odp` (kode ODP, BUKAN nama POP) |

- Clientside, atas array `filteredTasks` (cap 30 baris, ringan).
- Klik header pertama kali → ASC; klik lagi di header yang sama → DESC; klik header lain → pindah kolom sort, mulai dari ASC lagi.
- Manual sort OVERRIDE default sort priority/SLA (§3 poin #2) — berlaku sampai user ganti tab (lihat §6.3) atau reload halaman.
- Kolom Lokasi/POP/ODP cuma keliatan ≥2xl (disembunyiin di layar sempit, lihat komentar existing di `create.blade.php`) — sort-nya tetep jalan di belakang layar walau kolomnya lagi disembunyiin, cuma indikator visual arah sort gak keliatan di layar sempit.

### 6.2 Shortcut buka panel + fokus search (poin #2)

**Keputusan: pakai yang udah ada, gak ada tombol baru.** `N` (sudah terimplementasi, `handleShortcut()` di `create.blade.php`) tetap toggle buka/tutup panel Create New Ticket persis kayak sekarang — gak diubah jadi non-toggle, gak nambah shortcut `/` terpisah. Kalau panel kebuka lewat N, fokus ke search customer ngikut behavior existing (`resetForm()` udah manggil `$refs.searchInput?.focus()`).

### 6.3 Arrow Up/Down (navigasi row) + Arrow Left/Right (ganti tab) + Enter (buka drawer)

- Perlu state baru: `focusedIndex` (atau lebih robust `focusedTicketId`, di-resolve balik ke index tiap render — biar gak "loncat" kalau ada tiket baru masuk lewat broadcast di tengah user navigasi).
- Arrow Up/Down: gerak fokus antar baris `filteredTasks`, clamp di batas atas/bawah, `scrollIntoView({block:'nearest'})`. Guard WAJIB skip kalau `e.target` adalah input/textarea/select/contentEditable (pola sama kayak guard `N` yang udah ada) — biar gak nge-hijack cursor waktu user lagi ngetik di search box atau textarea keluhan.
- Arrow Left/Right: pindah `taskFilter` antar tab (`helpdesk` → `noc` → `fop`, clamp di ujung, gak wrap-around kecuali diputuskan lain). **Ganti tab reset fokus ke baris pertama** (dikonfirmasi user) — bukan coba pertahanin posisi.
- Enter: buka drawer detail (`openTicketDetail(focusedTicket.id)`) buat baris yang lagi fokus.
- Highlight visual (ring/border) buat baris fokus — perlu diterapkan di DUA blok render (`activeViewMode === 'table'` dan `'cards'`), sama-sama iterasi `filteredTasks` jadi index-nya konsisten.
- Arrow-navigasi berhenti/gak aktif selagi drawer detail lagi kebuka (biar gak dobel handle sama scroll/fokus di dalam drawer).

### 6.4 Row Focus + huruf (C / V / B) — dispatch aksi

- `C` = Close/Selesai, `V` = Ke NOC, `B` = Ke FOP. Cuma aktif kalau ada row yang lagi fokus (§6.3) dan fokus BUKAN di input/textarea/select/contentEditable (guard sama kayak N).
- **Modal konfirmasi (`window.confirmTicketAction`) TETAP MUNCUL** (dikonfirmasi user — proposal "skip modal" di §3 poin #1 DITOLAK buat pairing ini). Jadi hotkey ini motong LANGKAH PERTAMA doang (gak perlu cari-klik tombol yang tepat di baris yang tepat pakai mouse), tapi konfirmasi tetap harus dilewatin (baik via klik mouse di modal, atau — kalau `window.Dialog` support — Enter buat confirm/Escape buat batal; perlu dicek kompatibilitasnya pas eksekusi, bukan diasumsikan sekarang).
- **Batalkan & Kembalikan ke Helpdesk sengaja TIDAK dikasih hotkey** (dikonfirmasi user) — tetap lewat klik tombol biasa. Batalkan butuh alasan wajib (gak cocok jadi aksi 1-tombol-langsung), Kembalikan cuma relevan buat NOC.

### 6.5 Ringkasan tombol

| Tombol | Aksi | Guard |
|---|---|---|
| `N` | Toggle panel Create New Ticket (sudah ada) | Skip kalau fokus di input/textarea/select |
| `↑` / `↓` | Pindah fokus antar row list tiket | Skip kalau fokus di input/textarea/select; nonaktif selagi drawer kebuka |
| `←` / `→` | Pindah tab Ticket/Assign NOC/Assign FOP, reset fokus ke row pertama | sama |
| `Enter` | Buka drawer detail row yang lagi fokus | sama |
| `C` | Close/Selesai (row fokus) — modal konfirmasi tetap muncul | sama |
| `V` | Ke NOC (row fokus) — modal konfirmasi tetap muncul | sama |
| `B` | Ke FOP (row fokus) — modal konfirmasi tetap muncul | sama |
| klik header kolom | Sort ASC/DESC (Ticket ID & Time / Status-Issue / Lokasi-POP-ODP) | — |

Belum dieksekusi — nunggu keputusan lanjut kapan mulai coding.

## 7. Eksekusi §6 (2026-08-05)

Semua spec §6 diimplementasi di `resources/views/tickets/create.blade.php` (Worksheet Helpdesk). **Tombol/aksi existing (Quick Dispatch di baris tabel/kartu, tombol aksi di drawer detail) TIDAK dihapus/diganti** — shortcut ini jalur TAMBAHAN yang manggil fungsi JS yang SAMA (`closeTicket()`, `escalateTicket()`, `openTicketDetail()`), bukan implementasi paralel. Modal konfirmasi (`window.confirmTicketAction`) tetap muncul buat C/V/B, sesuai keputusan §6.4 — TIDAK di-skip.

| Bagian | Implementasi |
|---|---|
| §6.1 Sort kolom | State `sortField`/`sortDir` + getter `sortedTasks` (wrap `filteredTasks`, sort clientside by `code`/`issue_category`/`odp`). Header 3 kolom jadi `<button @click="sortBy(...)">` dengan ikon panah ASC/DESC. Kolom Pelanggan tetap statis (gak ada tombol sort). |
| §6.2 N (search-focus) | Gak ada perubahan — dikonfirmasi user pakai yang udah ada. |
| §6.3 Arrow nav | State `focusedTicketId` + `drawerOpen` (di-toggle lewat listener `open-ticket-drawer`/`close-ticket-drawer` di root elemen). `moveRowFocus()` (Up/Down), `switchTabByDelta()`+`setTab()` (Left/Right, reset fokus ke row pertama), Enter → `openTicketDetail()`. Highlight ring ditambah di `<tr>` (mode tabel) & `<div>` kartu (mode kartu), keduanya baca `focusedTicketId` yang sama. `data-ticket-row` attribute ditambah buat `scrollIntoView()`. |
| §6.4 C/V/B | Ditambah ke `handleShortcut()`, guard `isTypingTarget()` (helper baru, dipakai ulang juga oleh guard `N` yang lama) + `drawerOpen` + gerbang `task.actions?.can_close`/`can_escalate_noc`/`can_escalate_fop` (SUMBER YANG SAMA dipakai gerbang tombol Quick Dispatch existing, `Ticket::actionFlagsFor()`) — hotkey gak pernah lolosin aksi yang mestinya ke-disable di tombol. Manggil `closeTicket()`/`escalateTicket()` yang SUDAH ADA, modal tetap muncul. |
| Batalkan & Kembalikan | Sengaja gak dikasih hotkey (dikonfirmasi user) — tetap cuma lewat tombol. |

**Test:** Semua test existing yang nge-GET `route('tickets.create')` (83 di `TicketingTest`, plus `TicketingRbacTest`/`TicketCidDisplayTest`/`TicketCloseEscalateTest`/`TicketDetailDrawerTest`) tetap pass — 139 test, 724 assertion, gak ada regresi. Gak ada test baru ditulis khusus buat interaksi keyboard (Alpine/JS clientside, di luar cakupan PHPUnit — perlu manual verify di browser atau test Dusk/Pest-browser kalau mau ke-otomasi, di luar scope sesi ini).

**Belum dikerjakan dari §3 (percepatan Helpdesk yang lebih luas):** #1 (skip modal — DITOLAK buat pairing C/V/B, sesuai §6.4), #2 (default sort priority/SLA dari server), #3 (toast tiket baru), #5 (template keluhan per kategori).

## 8. Bugfix — Row-Navigasi Kececer Abis Escape/Tutup Drawer Manual (2026-08-05)

**Gejala:** Row Focus → Enter (buka drawer) → Escape (tutup drawer) → Arrow gak ngefek lagi, fokus keyboard "kececer" ke field Search Customer Data.

**Akar masalah, dua lapis:**
1. `handleShortcut()` (worksheet) punya cabang Escape sendiri yang SELALU manggil `resetForm()` (efek sampingnya fokus ke `$refs.searchInput`) — TANPA cek drawer lagi kebuka atau enggak. Drawer detail JUGA punya listener Escape sendiri (`x-on:keydown.escape.window="close()"`, di `detail-drawer.blade.php`). Satu keypress Escape → DUA listener nembak: drawer ketutup, TAPI form ikut kereset dan fokus dipaksa pindah ke search box.
2. Lebih dalam: state `drawerOpen` yang gue tambahin di sesi sebelumnya cuma dengerin event `open-ticket-drawer`/`close-ticket-drawer` — itu event **PERMINTAAN** ("tolong buka/tutup"), bukan **notifikasi state**. `close()` internal drawer (dipicu tombol X, klik backdrop, ATAU Escape) cuma nyetel `shown = false` langsung, gak pernah dispatch `close-ticket-drawer`. Efeknya: `drawerOpen` di worksheet nyangkut `true` SELAMANYA abis drawer ditutup manual lewat cara apa pun selain aksi (Close/Escalate) — bukan cuma soal Escape doang.

**Perbaikan:**
- `detail-drawer.blade.php` — `x-effect` di root drawer sekarang JUGA `window.dispatchEvent(new CustomEvent(shown ? 'ticket-drawer-shown' : 'ticket-drawer-hidden'))` tiap `shown` beneran berubah — nangkep SEMUA jalur tutup (X, backdrop, Escape, atau `close-ticket-drawer` dari luar) secara seragam, karena semuanya ujungnya nyetel `shown` yang sama.
- `create.blade.php` (worksheet) — listener `drawerOpen` pindah dari `@open-ticket-drawer.window`/`@close-ticket-drawer.window` (event permintaan, gak reliable) ke `@ticket-drawer-shown.window`/`@ticket-drawer-hidden.window` (event state, akurat).
- `handleShortcut()` — cabang Escape ditambah guard `if (this.drawerOpen) return;` sebelum `resetForm()`, buat nutup celah SATU tick keypress yang sama (event Alpine `x-effect` jalan via microtask, jadi `drawerOpen` belum ke-update pas dua listener Escape nembak bareng di tick yang sama — guard ini nutup celah itu, listener event yang baru nutup celah buat keypress-keypress SETELAHNYA).

**Test:** 94 test relevan (`TicketingTest`, `TicketDetailDrawerTest`, `TicketCloseEscalateTest`) — semua pass, gak ada regresi.

## 9. Dokumentasi Formal — Sudah Diupdate (2026-08-05)

- `docs/ticketing/business-logic.md` § 17 (baru) — sort kolom, tabel navigasi keyboard, kenapa modal tetap muncul.
- `docs/ticketing/user-flow.md` § 2 — badge SLA + subsection "Sort Kolom & Navigasi Keyboard".
- `docs/ticketing/README.md` — bullet Konsep Inti, deskripsi view `create.blade.php`, kontrak event drawer (`ticket-drawer-shown`/`hidden`), footer changelog.
