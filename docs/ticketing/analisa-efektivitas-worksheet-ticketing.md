# Analisa Efektivitas Worksheet Ticketing (Helpdesk & NOC)

Dokumen turunan dari `docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD` — evaluasi apakah
implementasi Worksheet Helpdesk + mekanisme Close/Escalate beneran lebih cepat &
efisien dibanding pencatatan manual Excel, plus daftar celah yang ditemukan.

**Status**: Analisa per 2026-07-27. SEMUA gap (#1-#8) sudah diperbaiki per
2026-07-27 (lihat bagian D).

> ⚠️ **SEBAGIAN SUDAH TERSALIP (2026-07-28).** Dokumen ini menganalisa Worksheet
> Ticketing versi lama — waktu itu Helpdesk **langsung kehilangan akses** begitu
> tiket dikirim ke NOC, semua daftar tiket numpang route bucket generik
> `/tickets/{bucket}`, dan modul Ticketing sama sekali gak punya endpoint cancel.
> Ketiganya sudah berubah:
>
> | Yang ditulis di sini | Kondisi sekarang |
> |---|---|
> | Serah-terima ke NOC langsung final | Ada window **"Pending NOC"** — Helpdesk masih pegang kendali sampai NOC klik **Oncheck NOC** |
> | NOC kerja dari `/tickets/diproses` (Gap #1 & bagian D-13) | NOC punya halaman sendiri: **Worksheet NOC** (`/noc/worksheet`, 2 tab) |
> | Pembatalan cuma lewat `/fop-tasks` (bagian A) | Tiket **pra-FOP** bisa dibatalkan dari Ticketing (`tickets.cancel`); pasca-FOP tetap lewat `/fop-tasks` |
> | `ticketRowAction()` + `confirm()` native (bagian D-1) | Semua aksi lewat `window.confirmTicketAction()` (dialog global + input alasan) |
> | Baseline 283 test | Bertambah: `TicketOnCheckNocTest`, `TicketPreFopCancelTest`, `TicketingRbacTest`, `NocWorksheetTest`, `NocDashboardTest` |
>
> Nilai dokumen ini sekarang **historis** — perbandingan vs Excel (bagian B) dan
> rasional tiap gap masih relevan sebagai catatan keputusan. Untuk perilaku yang
> berlaku sekarang, rujuk `docs/ticketing/business-logic.md`.

---

## A. Verdict Ringkas

- **Bikin tiket (Worksheet Helpdesk)**: lebih cepat dari Excel — search-autofill,
  dupe-check, dropdown Master Issue, AJAX stay-on-page.
- **Klaim "NOC kerja 1 halaman"**: sebelumnya SALAH di implementasi (Gap #1 — tombol
  aksi di `/tickets/{bucket}` nge-bounce NOC ke halaman detail, kebalikan dari
  tujuan). **Sudah diperbaiki**, lihat bagian D.
- **Di bawah kendala** (banyak tiket bareng, 2 orang kerja bareng, koneksi lambat):
  race condition penulisan (Gap #2) **sudah diperbaiki**, begitu juga blind-spot
  visibility (Gap #3 auto-refresh, Gap #4 indikator cap, Gap #5 dupe-check
  server-side) — kelas masalah yang Excel justru gak punya (satu file, semua orang
  lihat sheet sama) sekarang ketutup.
- **Recovery salah kirim**: NOC sekarang bisa "Kembalikan" tiket ke Helpdesk kalau
  salah terima (Gap #7). FOP tetap terminal buat sisi Ticketing (by design, lihat
  CLAUDE.md § Sinkronisasi Ticket ↔ FopTask) — pembatalan dari sana tetap lewat
  `/fop-tasks`, bukan lewat modul ini.

---

## B. Perbandingan vs Excel (poin yang beneran lebih cepat)

| Pain point Excel | Sistem sekarang | Speed win |
|---|---|---|
| Ketik ulang data pelanggan manual | Search CID/nama → autofill 9 field | Hilangkan typo + re-entry |
| Gak ada deteksi tiket dobel | Warning kuning otomatis kalau customer punya tiket open | Cegah kerja dobel |
| Kategori & prioritas ditebak manual | Dropdown Master Issue → auto-fill prioritas | 1 klik vs mikir+ketik |
| Ganti sheet/kolom status manual, gak ada notifikasi ke pihak lain | Tombol Selesai/Ke NOC/Ke FOP — pindah tangan + history otomatis, actor tercatat | Zero manual bookkeeping |
| Reload/re-navigate abis submit | AJAX stay-on-page, form auto-reset, fokus balik ke search | Entry beruntun tanpa jeda |
| "Siapa yang pegang ini terakhir" gak jelas | Atribusi (dibuat/dieskalasi/diselesaikan siapa) otomatis dari `ticket_histories` | Audit gratis, gak perlu tanya-tanya |

---

## C. Gap yang Ditemukan

Urut prioritas (Kritis → Rendah). Tiap gap dikasih bukti kode (file:line) — bukan
spekulasi.

### 1. ~~KRITIS — Tombol aksi index BUKAN 1-halaman, malah nge-bounce NOC~~ (DIPERBAIKI)

Lihat bagian D.

### 2. ~~TINGGI — Race condition / TOCTOU di close/escalate~~ (DIPERBAIKI)

Lihat bagian D.

### 3. ~~TINGGI — Worksheet panel gak ada auto-refresh sama sekali~~ (DIPERBAIKI)

Lihat bagian D.

### 4. ~~MENENGAH — Cap 30 tiket di worksheet, gak ada indikator "masih ada lagi"~~ (DIPERBAIKI)

Lihat bagian D.

### 5. ~~MENENGAH — Deteksi duplikat ikut kena blind spot Gap #4~~ (DIPERBAIKI)

Lihat bagian D.

### 6. ~~MENENGAH — Double-submit gak dijaga di index~~ (DIPERBAIKI)

Nempel gratis pas benerin Gap #1 (lihat bagian D di dokumen versi sebelumnya —
`ticketRowAction()` udah disable-all-buttons dari awal).

### 7. ~~RENDAH-MENENGAH — Gak ada jalur "batal kirim" / kembalikan~~ (DIPERBAIKI)

Lihat bagian D.

### 8. ~~RENDAH — Attachment gagal kehilangan pilihan file~~ (DIPERBAIKI)

Lihat bagian D.

---

## D. Selesai Diperbaiki

### Gap #1 — Tombol aksi index sekarang AJAX, gak nge-bounce (2026-07-27)

`resources/views/tickets/index.blade.php` — form `method="POST"` native diganti
`<button type="button" onclick="ticketRowAction(this, url, payload, confirmMsg)">`.
Fungsi global `ticketRowAction()` (di `@push('scripts')` bawah file yang sama):
`fetch()` JSON ke `tickets.close`/`tickets.escalate` → server balikin JSON (bukan
redirect, karena `$request->wantsJson()` true) → baris tiket dihapus in-place
(fade-out) + toast, badge count di header didekremen. **Zero navigasi**, NOC
beneran tetep di `/tickets/diproses`.

Efek samping: native `<button type="button">` sengaja dipakai (bukan `<form>`) biar
gak ketiban global `document.addEventListener('submit', ...)` di
`layouts/app.blade.php` yang nambahin dialog konfirmasi KEDUA — sebelumnya form
native + `onsubmit="return confirm(...)"` bakal kena double-confirm (native
`confirm()` browser, lalu `window.Dialog` custom) karena string `onsubmit` gak match
whitelist skip-nya (`confirmAction`/`confirmDelete`/case-sensitive `Confirm`). Bug
laten ini otomatis ke-skip sekalian.

Sekalian nutup **Gap #6** (double-submit) — semua tombol di baris ke-disable pas
request jalan (`buttons.forEach(b => b.disabled = true)`).

Test regresi: `TicketCloseEscalateTest::test_index_bucket_action_buttons_are_not_native_forms`,
`test_ajax_action_from_index_context_does_not_redirect`.

### Gap #2 — `lockForUpdate()` di close/escalate (2026-07-27)

`app/Services/TicketService.php` — `close()`, `escalateToNoc()`, `escalateToFop()`
sekarang buka dengan `$ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();`
di dalam transaksi, SEBELUM guard `assertActorOwnsTicket()`/`assertTicketStillOpen()`
dicek. Request kedua yang nyaris bareng WAJIB antre nunggu lock lepas, baca state
TERBARU (bukan state basi dari sebelum commit pertama), jadi ketahan guard dengan
benar alih-alih dua-duanya lolos.

Catatan: SQLite in-memory (dipakai test suite) gak punya row-level locking
sungguhan kayak MySQL/Postgres, jadi race condition asli gak bisa disimulasikan di
PHPUnit single-process. Proteksi ini efektif di production (MySQL, sesuai
`config/database.php` non-test env) — testable secara sekuensial lewat guard yang
udah ada (`test_cannot_act_on_ticket_already_closed`, dst).

### Gap #3 — Auto-refresh broadcast Reverb + tombol Refresh manual (2026-07-27)

Infra Reverb/Echo udah dipakai di tempat lain (`fop.{pop_id}` channel di
`resources/views/fop/dashboard.blade.php`), jadi ikutin pola yang sama, BUKAN
bangun dari nol:

- `App\Events\TicketQueueUpdated implements ShouldBroadcast` — sinyal "sesuatu di
  antrean POP ini berubah" doang, gak bawa payload tiket lengkap. Listener refetch
  sendiri lewat endpoint yang udah lolos scope POP & permission user, bukan percaya
  payload broadcast mentah.
- `routes/channels.php` — channel `tickets.{popId}` baru, otorisasi pakai
  `EffectiveAccessService::getAllowedPopIds()`/`hasAllPopAccess()` (jalur BENAR per
  CLAUDE.md), **bukan** `$user->pops()` legacy yang dipakai channel `fop.{pop_id}`
  lama.
- `TicketService::create()/close()/escalateToNoc()/escalateToFop()` — dispatch
  `broadcast(new TicketQueueUpdated($popId))->toOthers()` SETELAH `DB::transaction()`
  return (bukan di dalam closure — gak boleh nembak kalau rollback). `toOthers()`
  biar tab aktor sendiri gak refetch dobel (state udah di-patch lokal).
- **Worksheet panel** (`tickets/create.blade.php`): endpoint baru
  `GET /api/tickets/worksheet-tasks` (`TicketController::worksheetJson()`) balikin
  JSON bentuk sama persis kayak initial load. Alpine `initEchoListeners()` subscribe
  `tickets.{popId}` per POP yang kelihatan user (`Pop::forUser()`), `.listen('.TicketQueueUpdated', ...)`
  → `refreshWorksheet()` replace `this.tasks`. Tombol "Refresh" manual jadi fallback
  kalau Reverb gak jalan di browser user (default `.env.example` `BROADCAST_CONNECTION=log`,
  bukan `reverb` — perlu dikonfigurasi eksplisit biar broadcast beneran nyampe).
- **Index bucket** (`tickets/index.blade.php`, dipakai NOC): pola beda —
  self-refetch `window.location.href` (pertahanin bucket + filter query string)
  lalu innerHTML-swap container `bucket-tabs-container`/`tickets-list-container`/
  `tickets-pagination-container`, PLUS `outerHTML`-swap khusus `bucket-count-badge`
  (innerHTML doang bakal ninggalin atribut `data-count` basi, bikin drift sama
  `decrementBucketCountBadge()` punya `ticketRowAction()`). Sama persis pola
  `refreshDashboardContainers()` di `fop/dashboard.blade.php` — BUKAN rebuild HTML
  manual di JS (mismatch risk sama Blade rendering).

Test regresi: `test_creating_ticket_dispatches_queue_updated_event`,
`test_close_dispatches_queue_updated_event`, `test_escalate_dispatches_queue_updated_event`.

### Gap #4 — Indikator "+N lainnya" saat lebih dari cap panel (2026-07-27)

`TicketController::worksheetTotalActiveCount()` — query `activeForWorksheet()->count()`
TANPA `limit()`, dibandingin sama `worksheetTasks()` yang tetep kena cap
(`WORKSHEET_DISPLAY_LIMIT = 30`, gak dihapus — cap tetep berguna buat batasin
payload). `worksheetJson()` balikin keduanya (`tasks` + `total`).

Blade: `worksheetTotalCount` dikirim ke Alpine dari server-side (initial load) dan
di-refresh bareng `tasks` tiap `refreshWorksheet()`/aksi lokal. Kalau
`worksheetTotalCount > tasks.length`, muncul baris "+N tiket aktif lainnya — Lihat
Semua →" di bawah list, link ke `/tickets/masuk`. `performTicketAction()` &
`submitForm()` juga ngupdate `worksheetTotalCount` lokal (increment pas submit
sukses, decrement pas ticket keluar dari `activeForWorksheet()` scope — mis. abis
di-close).

Test regresi: `test_worksheet_shows_more_indicator_when_over_display_cap`,
`test_worksheet_json_total_not_capped`.

### Gap #5 — Dupe-check server-side, gak kena cap panel (2026-07-27)

`TicketController::duplicates()` — endpoint baru `GET /api/tickets/duplicates?customer_id=X`,
query `Ticket::query()->applyUserScope()->where('customer_id', $id)->activeForWorksheet()`
(scope yang SAMA persis dipakai worksheet panel — lihat Gap #4 — jadi hasilnya
konsisten). TANPA `limit()`, jadi tiket lama customer yang udah kegeser dari cap 30
panel tetap kedeteksi.

Alpine `create.blade.php`: `duplicateTickets` diubah dari getter (filter array
`tasks` lokal yang kena cap) jadi property reactive + `checkDuplicates(customerId)`
yang di-panggil dari `pick()` (dan dikosongin lagi di `clearSelection()`). Template
pemakaiannya (`duplicateTickets.length`, loop `d in duplicateTickets`) gak berubah —
field `id`/`code`/`bucket` dari respons server sama persis kayak sebelumnya.

Test regresi: `test_duplicates_endpoint_finds_open_ticket_regardless_of_worksheet_cap`
(secara eksplisit dorong tiket target keluar dari cap 30 pakai 30 tiket lain, buktiin
tetep kedeteksi), `test_duplicates_endpoint_excludes_closed_tickets`,
`test_duplicates_endpoint_requires_permission`.

### Gap #6 — (nempel gratis pas Gap #1, lihat atas)

### Gap #7 — Aksi "Kembalikan ke Helpdesk" buat NOC (2026-07-27)

- `TicketHistoryAction::DIKEMBALIKAN` — case baru di enum, dipakai NAMA aksi di
  histori (badge slate, netral — beda dari amber DIESKALASI biar kebaca ini
  gerakan "turun" bukan "naik").
- `Ticket::actionFlagsFor()` — tambah key `can_return_to_helpdesk`, true CUMA
  buat NOC (`handler === TicketHandler::NOC`). Helpdesk gak dapet flag ini sama
  sekali — dia asal-usul tiket, gak ada "turun" ke mana pun. FOP juga gak dapet
  — `assertTicketStillOpen()` udah nolak semua aksi Ticketing begitu
  `handler=FOP` (by design, CLAUDE.md § Sinkronisasi Ticket ↔ FopTask — pembatalan
  dari FOP tetap lewat `/fop-tasks`, BUKAN lewat modul ini).
- `TicketService::returnToHelpdesk()` — pola sama persis close()/escalateToNoc()
  (lockForUpdate, assertActorOwnsTicket, assertTicketStillOpen, guard tambahan
  `$ticket->handler !== TicketHandler::NOC`), broadcast `TicketQueueUpdated` abis
  commit.
- Route `POST /tickets/{ticket}/return-to-helpdesk`, tombol "Kembalikan" nempel di
  ketiga UI (worksheet panel, index bucket, halaman detail) — pola sama persis
  Selesai/Ke NOC/Ke FOP, gate dari `actionFlagsFor()` yang sama.
- Atribusi `returnedToHelpdeskBy()` ditambahin ke `worksheetCardPayload()` dan
  ditampilin ("↩ Kembali ke Helpdesk oleh X") di worksheet card + index row.

Test regresi: `test_noc_can_return_ticket_to_helpdesk`,
`test_helpdesk_cannot_return_ticket_to_helpdesk`,
`test_cannot_return_ticket_still_at_helpdesk`,
`test_cannot_return_ticket_already_at_fop`,
`test_return_to_helpdesk_dispatches_queue_updated_event`,
`test_action_flags_expose_return_to_helpdesk_only_for_noc`.

### Gap #8 — Pesan error submit lebih spesifik (2026-07-27)

`submitForm()` di `create.blade.php` — dua jalur error dipisah & diperjelas:
- **Server balikin respons tapi gagal** (`!res.ok`, mis. 403/419/500): coba parse
  `body.message` dari respons kalau ada, fallback ke pesan generik kalau bukan
  JSON valid.
- **`fetch()` sendiri gagal** (network putus/timeout, gak ada respons server sama
  sekali): pesan beda ("periksa koneksi internet").
- Kedua jalur nambahin baris "Lampiran yang sudah dipilih masih tersimpan" kalau
  `attachments.length > 0` — negasin asumsi lama user (padahal `resetForm()`
  emang cuma dipanggil pas sukses, filenya gak pernah ke-clear di jalur gagal,
  tinggal dikomunikasikan).

Gak ada perubahan backend — `store()` yang 422 udah balikin field errors lengkap
lewat body `errors` (kepake di error summary panel yang udah ada duluan).

Test regresi: `test_store_validation_failure_returns_specific_field_errors`,
`test_close_action_error_returns_message_field_for_frontend_display`.

---

## E. Step Pengujian (UAT)

**A — Kecepatan entry (bandingin ke Excel actual)**
1. Siapkan 5 kasus (CID beda-beda), catat waktu isi form Excel manual (baseline).
2. Ulangi 5 kasus sama di Worksheet Helpdesk (`/tickets/new`): search CID → pilih
   kategori → isi keluhan → Ctrl+Enter submit.
3. Bandingkan waktu rata-rata per entry.

**B — 5 skenario alur (dari rancangan doc)**
4. Helpdesk submit tiket → cek masuk bucket Masuk, handler=Helpdesk.
5. Klik "Selesai" di worksheet card → cek pindah ke `/tickets/selesai`, hilang dari
   panel.
6. Submit tiket baru → klik "Ke NOC" → login sbg NOC → cek muncul di
   `/tickets/diproses` dengan atribusi "→ NOC oleh [nama Helpdesk]".
7. Dari situ NOC klik "Selesai" → cek pindah Selesai, atribusi "✓ Selesai oleh
   [NOC]".
8. Tiket baru lain, NOC klik "Ke FOP" → cek FopTask kebentuk (`TFOP-...`), tiket
   ilang dari worksheet (udah `handler=FOP`).
9. Helpdesk submit → langsung klik "Ke FOP" (skip NOC) → cek FopTask kebentuk,
   atribusi eskalasi FOP tercatat.

**C — Guard / gak boleh salah pencet**
10. Login role lain (mis. Teknisi) → cek tombol aksi gak muncul sama sekali.
11. NOC coba akses tiket yang masih di Helpdesk (belum dilempar) → harus ditolak
    (403/error), bukan cuma disembunyikan di UI.
12. Coba close tiket yang udah closed / udah di FOP → harus ditolak, pesan jelas.

**D — NOC 1-halaman**
13. Login NOC, buka `/tickets/diproses` sekali — cek bisa liat semua tiket assigned
    + eksekusi Selesai/Ke FOP tanpa pindah halaman sama sekali. **Sudah lulus**
    sejak Gap #1 diperbaiki (2026-07-27) — klik tombol aksi tetap di halaman list,
    baris hilang in-place, gak ada redirect ke detail.

**E — Duplicate check (server-side, sejak Gap #5 diperbaiki)**
14. Bikin 2 tiket buat CID yang sama, tiket ke-2 harus muncul warning kuning pas
    customer dipilih. Ulangi tapi dorong tiket pertama keluar dari cap 30 panel
    (bikin >30 tiket buat customer lain) — warning HARUS tetap muncul (query
    server-side, gak lagi kena cap array lokal).

**F — Race condition (butuh 2 sesi/tab bersamaan)**
15. Buka tiket yang sama di 2 tab (login Helpdesk & NOC beda sesi setelah tiket
    dieskalasi ke NOC — atau simulasikan pakai 2 request cURL nyaris bersamaan).
    Klik Close di satu tab dan Ke FOP di tab lain dalam window waktu <100ms — cek
    apakah dua-duanya sukses (BUG, harusnya cuma satu) atau salah satu ditolak
    (BENAR — sejak Gap #2 diperbaiki).

**G — Auto-refresh & indikator cap (Gap #3 & #4)**
16. Buka worksheet (`/tickets/new`) di 2 tab beda user (Helpdesk A & B, POP sama).
    Dari tab A, submit tiket baru → tab B (kalau Reverb kekoneksi) list panelnya
    ke-update sendiri dalam beberapa detik tanpa refresh manual. Kalau Reverb gak
    jalan di environment (`BROADCAST_CONNECTION` bukan `reverb`), klik tombol
    "Refresh" manual di tab B → harus keupdate.
17. Buka `/tickets/diproses` (index bucket, dipakai NOC) di 2 tab — ulangi test 16
    di sana; cek `bucket-count-badge`, tab counts, dan baris tiket semua keupdate
    konsisten (bukan cuma sebagian).
18. Bikin >30 tiket aktif (submit berturut-turut) → worksheet panel harus nampilin
    "+N tiket aktif lainnya — Lihat Semua →" di bawah list, N = total - 30.

**I — Kembalikan ke Helpdesk (Gap #7)**
19. Helpdesk kirim tiket ke NOC. Login NOC, klik "Kembalikan" (dari worksheet
    panel, index bucket, atau halaman detail — pilih salah satu) → tiket balik ke
    Helpdesk, bucket jadi Masuk lagi, atribusi "↩ Kembali ke Helpdesk oleh [NOC]"
    muncul.
20. Login Helpdesk, buka tiket yang masih di tangannya sendiri (belum pernah ke
    NOC) → tombol "Kembalikan" TIDAK muncul (Helpdesk gak punya ke mana pun buat
    kembali).
21. Eskalasi tiket sampe ke FOP → cek gak ada tombol "Kembalikan" atau aksi
    Ticketing apa pun lagi di halaman manapun (FOP terminal by design).

**J — Pesan error submit (Gap #8)**
22. Pilih 1-2 file lampiran di worksheet, matikan koneksi internet (DevTools →
    Network → Offline), klik Create Ticket → toast harus bilang "periksa koneksi
    internet" + "lampiran masih tersimpan", form gak clear, file masih kepilih.
    Nyalain lagi koneksi, submit ulang → harus sukses tanpa pilih ulang file.

**K — Regresi otomatis**
23. `php artisan test --filter=Ticket` — pastikan hijau. Baseline naik jadi 283
    test setelah fix Gap #1-#8 (2026-07-27), dari 262 sebelum perbaikan apa pun.

---

## F. Rekomendasi Urutan Perbaikan

**Semua gap (#1-#8) selesai per 2026-07-27.** Urutan pengerjaan aktual:

1. ~~Gap #1~~ — **selesai**, tombol index jadi AJAX.
2. ~~Gap #2~~ — **selesai**, `lockForUpdate()`.
3. ~~Gap #6~~ — **selesai**, nempel gratis pas benerin #1 (disable-on-submit).
4. ~~Gap #3 / #4~~ — **selesai**, broadcast Reverb + indikator cap.
5. ~~Gap #5~~ — **selesai**, dupe-check server-side.
6. ~~Gap #7~~ — **selesai**, aksi "Kembalikan ke Helpdesk".
7. ~~Gap #8~~ — **selesai**, pesan error lebih spesifik.

**Belum ada di roadmap ini** (bukan gap, tapi potensi lanjutan kalau volume tiket
tumbuh): dashboard SLA countdown per tiket, notifikasi push (browser/Telegram) saat
tiket baru masuk, bulk actions (multi-select close/escalate).

**Catatan operasional**: Gap #3 baru KERASA di production kalau `BROADCAST_CONNECTION`
di-set ke `reverb` (bukan default `.env.example` yang `log`) DAN `php artisan reverb:start`
jalan. Tanpa itu, sistem tetep jalan normal — auto-refresh diam-diam gak aktif,
fallback ke tombol Refresh manual (gak ada error, cuma gak "auto").
