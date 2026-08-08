# Modul Ticketing

Ticketing = tiket internal **PERUSAHAAN** (helpdesk/NOC/sales/admin/POP admin/FOP) — beda dari `FopTask` yang internal **FOP**. Tiket adalah *permintaan* (keluhan pelanggan MTN atau permintaan C-REQ); `FopTask` adalah *penugasan* yang **cuma kebentuk kalau tiketnya dieskalasi ke FOP**.

Berlaku **cuma untuk 2 tipe**: `MTN` (Maintenance) dan `C-REQ` (Customer Request) — lihat `TaskType::ticketValues()`. Tipe lain (SURVEY, PSB, O-REQ, DEAC, INFR REQ) gak pernah masuk lewat modul ini.

## Konsep Inti

Tiket punya **dua rezim status** yang berurutan. Selama masih ditangani internal (Helpdesk/NOC), statusnya kolom sendiri di tabel `tickets`. Begitu dieskalasi ke FOP, kendali pindah dan statusnya diturunkan dari `FopTask`:

```
                    ┌──────────── REZIM INTERNAL (kolom `tickets`) ─────────────┐
Ticket dibuat  →    handler=HELPDESK              handler=NOC                    │
(TKT-YYYY-NNNN)     status=OPEN        ──"Ke NOC"→ status=OPEN                    │
                    │                             (DIPROSES NOC — seketika)      │
                    │                             dipegang helpdesk + noc        │
                    │                                   │                        │
                    └───────── Selesai / Batalkan ──────┘                        │
                                       │                                          │
                                  status=CLOSED / CANCELLED  ────────────────────┘
                                       │
                    ─────────── "Assign FOP" (escalateToFop) ───────────
                                       ▼
                    ┌──────────── REZIM FOP (turunan FopTask) ─────────────┐
                    handler=FOP  →  FopTask (TFOP-YYYY-NNNN, status DRAFT) │
                    (TERMINAL buat        │                                 │
                     sisi Ticketing)      │ FOP assign teknisi (/fop-tasks) │
                                          ▼                                 │
                                     Task (TASK-YYYY-NNNN, eksekusi)        │
                    └──────────────────────────────────────────────────────┘
```

Prinsip yang dijaga:

- **Tiket PUNYA kolom status sendiri** (`handler` + `status`) — ini berubah sejak migrasi `2026_07_25_000003`. Sebelumnya tiket sama sekali gak punya status dan selalu numpang `FopTask`; itu gak cukup begitu Helpdesk & NOC perlu menyelesaikan tiket **tanpa** melibatkan lapangan sama sekali. Detail: [business-logic.md § Dua Rezim Status](business-logic.md#2-dua-rezim-status).
- **FopTask TIDAK auto-dibuat saat submit.** Tiket lahir di tangan Helpdesk sebagai antrean mentah. `FopTask` baru kebentuk kalau eksplisit dieskalasi (`escalateToFop()`), atau kalau FOP sendiri yang submit dari halaman Task FOP. Ini kebalikan dari perilaku lama (auto-sync) yang bikin setiap keluhan — termasuk yang cukup dibenerin dari meja — langsung nyampah jadi tugas lapangan.
- **Assign ke NOC = langsung diproses.** Begitu Helpdesk klik "Ke NOC", tiket seketika berstatus **Diproses NOC** dan dipegang **berdua** (`helpdesk` + `noc`) sampai selesai/dibatalkan/dikirim ke FOP.
  → Window **"Pending NOC"** + aksi **"Oncheck NOC"** DIHAPUS (ADHOC-06, 2026-07-29) beserta kolom `noc_checked_at`, endpoint `tickets.oncheck-noc`, dan flag `can_oncheck_noc`. Langkah "terima dulu" itu tidak mencerminkan cara kerja sebenarnya: assign = mulai kerja. Riwayat lama beraksi `dicek_noc` tetap terbaca (enum case-nya dipertahankan).
- **`handler=FOP` itu terminal buat sisi Ticketing.** Semua aksi Ticketing (Selesai/Assign/Kembalikan/Batalkan) ditolak `assertTicketStillOpen()`. Pembatalan setelah titik ini wajib lewat `/fop-tasks`.
- **Data pelanggan di-snapshot (dibekukan), bukan dibaca live** — lihat [business-logic.md § Snapshot Data Pelanggan](business-logic.md#5-snapshot-data-pelanggan).
- **Dua riwayat per pembatalan pasca-FOP** — `fop_task_status_history` (sisi FOP) dan `ticket_histories` (sisi pengirim) ditulis bareng oleh `FopTaskObserver`. Pembatalan **pra-FOP** cuma nulis `ticket_histories` (belum ada FopTask buat dicatat).
- **Target SLA (sejak `2026_08_05`)** — `tickets.sla_hours`/`sla_deadline_at` di-snapshot saat tiket lahir, diwarisi `fop_tasks.handling_sla_hours` saat eskalasi (satu clock, gak reset di handoff). Detail: [business-logic.md § 16](business-logic.md#16-target-sla-ticketing).

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | Dua rezim status, kepemilikan tiket, aturan aksi & guard, snapshot pelanggan, bucket, dual-history, RBAC per-halaman |
| [user-flow.md](user-flow.md) | Langkah Helpdesk & NOC per halaman, skenario A/B/C, pembatalan pra & pasca FOP |
| [flowchart.md](flowchart.md) | State machine tiket, alur tiap aksi, resolusi bucket, RBAC decision tree |
| [database-schema.md](database-schema.md) | Tabel `tickets`, `ticket_attachments`, `ticket_histories`, `ticket_issue_categories` — kolom, relasi, migrasi |

## Halaman & RBAC

Lima halaman, **permission masing-masing** — dulu semuanya numpang `tickets.view` lewat route bucket generik `/tickets/{bucket}` sehingga gak bisa di-toggle per-halaman di Role Matrix.

| Halaman | Route | Permission | Buat siapa |
|---|---|---|---|
| **New Ticket** (Worksheet Helpdesk & NOC) | `/tickets/new` | `tickets.create` | Helpdesk, NOC, sales, admin, pop_admin, FOP |
| **Worksheet NOC** | `/noc/worksheet` | `noc_worksheet.view` | NOC, admin |
| **Dashboard NOC** | `/noc/dashboard` | `noc_dashboard.view` | NOC, admin, atasan (monitoring) |
| **Ticket Selesai** | `/tickets/selesai` | `tickets.selesai.view` | semua pemegang `tickets.*` + atasan |
| **Ticket Dibatalkan** | `/tickets/dibatalkan` | `tickets.dibatalkan.view` | semua pemegang `tickets.*` + atasan |

`/noc/worksheet` digerbangi **satu** permission (`noc_worksheet.view`) untuk seluruh halaman, termasuk kedua tabnya (**Tiket Masuk** / **Assign FOP**, ADHOC-09) — tab dipilih lewat query `?tab=`, bukan route sendiri. Dua permission tab lama (`noc_worksheet.masuk.view`, `noc_worksheet.diproses.view`) sudah pensiun (feature dinonaktifkan di `TicketFeatureSeeder`, barisnya sengaja gak dihapus dari DB); tab baru **tidak** menghidupkannya lagi. URL lama `/noc/worksheet/masuk` & `/noc/worksheet/diproses` tetap di-redirect ke `/noc/worksheet`.

### Permission aksi

| Permission | Role default | Dipakai untuk |
|---|---|---|
| `tickets.view` | owner, atasan, admin, noc, helpdesk, fop, sales, pop_admin | Lihat detail tiket + download lampiran (di-scope POP lewat `HasPopScope`) |
| `tickets.create` | owner, admin, noc, helpdesk, fop, sales, pop_admin | Halaman New Ticket + submit tiket — **atasan sengaja gak dapet** (cuma monitoring) |
| `tickets.update` | idem `tickets.create` minus atasan | Selesai / Assign NOC / Assign FOP / Kembalikan. Otorisasi per-handler (siapa yang lagi pegang) dicek di `TicketService`, bukan di permission ini |
| `tickets.cancel` | idem `tickets.update` | Batalkan tiket **pra-FOP**. Terpisah dari `tickets.update` biar bisa dicabut sendiri (mis. NOC boleh selesaikan tapi gak boleh membatalkan) |
| `fop_tasks.cancel` | owner, admin, fop | Batalkan `FopTask` — satu-satunya jalur pembatalan tiket yang **sudah** di FOP |
| `fop_tasks.create` | owner, admin, fop | Gerbang tambahan: field `technicians[]` saat submit cuma dihonor kalau aktor punya ini — mencegah helpdesk self-assign lewat request yang di-craft manual |

## Views

- `resources/views/tickets/create.blade.php` — **New Ticket**: form submit (CID lookup + auto-fill + Detail Keluhan + Catatan Teknis + Lampiran) di kiri, panel **List Task Ticketing** di kanan dengan 3 tab: **Ticket** / **Assign NOC** / **Assign FOP** (filter per `handler`, bukan per bucket). Panel kanan sortable per kolom (Ticket ID & Time / Status-Issue / Lokasi-POP-ODP) + navigasi keyboard penuh (Arrow/Enter/C/V/B/N) — lihat [business-logic.md § 17](business-logic.md#17-worksheet-helpdesk--sort-kolom--keyboard-shortcut).
- `resources/views/noc/worksheet.blade.php` — **Worksheet NOC**: tabel padat (satu baris = satu tiket) + pencarian + filter (POP, kategori, prioritas, tipe, pengirim, rentang tanggal) + dua tab bercounter **Tiket Masuk** / **Assign FOP**. Aksi diambil dari baris terpilih lewat drawer (`components/ui/drawer.blade.php`), bukan kolom tombol
- `resources/views/noc/dashboard.blade.php` — **Dashboard NOC**: stat counter, list tiket aktif + aging, feed aktivitas, statistik per Issue, statistik per Daerah
- `resources/views/tickets/selesai.blade.php`, `dibatalkan.blade.php` — halaman arsip, masing-masing file sendiri; isi list-nya share `tickets/partials/archive.blade.php`
- `resources/views/tickets/show.blade.php` — detail tiket: badge Kategori Issue + Target SLA (countdown live), snapshot pelanggan, keluhan, panel Aksi Tiket, dua kolom riwayat, lampiran
- `resources/views/tickets/partials/action-dialog.blade.php` — **satu-satunya** dialog konfirmasi + input alasan buat semua aksi tiket di semua halaman (numpang `window.Dialog` global). Lihat [business-logic.md § Dialog Konfirmasi](business-logic.md#12-dialog-konfirmasi--input-alasan)
- `resources/views/tickets/partials/detail-drawer.blade.php` — **drawer detail kanan**, dipakai BERSAMA oleh Worksheet Helpdesk & Worksheet NOC (ADHOC-10). Isi di-fetch dari `tickets.detail-json` dan setara halaman detail penuh: Status & atribusi, Task FOP terkait (teknisi + tombol Buka Task FOP), Aksi Ticket, Snapshot Pelanggan, Keluhan & Catatan, Lampiran (ukuran + pengunggah), Riwayat Ticketing, Riwayat Task FOP. Tombol aksinya cuma men-dispatch event `ticket-drawer-action`, konfirmasi + POST tetap di halaman pemanggil (satu sumber per halaman). Kontrak event: `open-ticket-drawer` / `close-ticket-drawer` (permintaan buka/tutup, dari pemanggil) dan `ticket-drawer-action` (aksi dari dalam drawer) — plus **`ticket-drawer-shown`/`ticket-drawer-hidden`** (sejak `2026_08_05`, notifikasi state SEBENARNYA, dispatch tiap `shown` berubah lewat cara APAPUN termasuk tombol X/backdrop/Escape — dipakai Worksheet Helpdesk buat nonaktifin navigasi keyboard selagi drawer kebuka, lihat business-logic.md § 17).
  → Panel dimulai di bawah navbar (`top-16`) dan pakai **z-index literal `z-[60]`**. Jangan diganti balik ke `z-drawer`/`z-dropdown`/`z-modal`/`z-sticky`: token `--z-*` cuma ada di `:root` (di luar `@theme`) dan z-index bukan namespace yang di-generate Tailwind v4, jadi class itu tidak pernah muncul di CSS hasil build dan elemennya jatuh ke `z-index:auto`.

## Routes

| Route | Method | Permission | Controller |
|---|---|---|---|
| `/tickets/new` | GET | `tickets.create` | `TicketController@create` |
| `/tickets` | POST | `tickets.create` | `TicketController@store` — dua jalur (worksheet & FOP), lihat [business-logic.md § Dua Jalur Masuk](business-logic.md#4-dua-jalur-masuk-satu-logic) |
| `/api/tickets/lookup-customer` | GET | `tickets.create` | `TicketController@lookupCustomer` |
| `/api/tickets/worksheet-tasks` | GET | `tickets.create` | `TicketController@worksheetJson` — refresh panel List Task Ticketing |
| `/api/tickets/duplicates` | GET | `tickets.create` | `TicketController@duplicates` — dupe-check server-side per `customer_id` |
| `/tickets/selesai` | GET | `tickets.selesai.view` | `TicketSelesaiController@index` |
| `/tickets/dibatalkan` | GET | `tickets.dibatalkan.view` | `TicketDibatalkanController@index` |
| `/api/tickets/{ticket}/detail` | GET | `tickets.view` | `TicketController@detailJson` — isi drawer detail di dua worksheet (snapshot + lampiran + riwayat + flag aksi). Gerbang sama dengan `show()`: permission + POP scope |
| `/tickets/{ticket}` | GET | `tickets.view` | `TicketController@show` — halaman detail penuh, dipakai dari halaman ARSIP (Selesai/Dibatalkan/History) & notifikasi; `whereNumber`, didaftarkan SETELAH route statis di atas |
| `/ticket-attachments/{attachment}` | GET | `tickets.view` | `TicketController@download` — disk privat |
| `/tickets/{ticket}/close` | POST | `tickets.update` | `TicketController@close` |
| `/tickets/{ticket}/escalate` | POST | `tickets.update` | `TicketController@escalate` — `target=noc\|fop` |
| `/tickets/{ticket}/return-to-helpdesk` | POST | `tickets.update` | `TicketController@returnToHelpdesk` |
| `/tickets/{ticket}/cancel` | POST | `tickets.cancel` | `TicketController@cancel` — **cuma pra-FOP** |
| `/noc/worksheet` | GET | `noc_worksheet.view` | `NocWorksheetController@index` — `?tab=masuk\|assign_fop` (default `masuk`, nilai asing jatuh ke `masuk`) + query filter (`q`, `pop_id`, `issue_category_id`, `type`, `priority`, `created_by`, `date_from`, `date_to`) |
| `/noc/worksheet/masuk`, `/noc/worksheet/diproses` | GET | — | `Route::redirect` ke `/noc/worksheet` (bookmark lama) |
| `/noc/dashboard` | GET | `noc_dashboard.view` | `NocDashboardController@index` |

> **Sudah dihapus:** `/tickets` (`tickets.index`), `/tickets/{bucket}` (`tickets.bucket`), dan `/tickets/{ticket}/oncheck-noc` (ADHOC-06). Bucket Masuk & Diproses pindah ke Worksheet NOC; Selesai & Dibatalkan jadi halaman sendiri.

## Teknologi

| Komponen | Stack |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Frontend | Blade, Alpine.js, Tailwind |
| Realtime | Laravel Reverb — channel `tickets.{popId}`, event `TicketQueueUpdated` |
| Database | MySQL — `tickets`, `ticket_attachments`, `ticket_histories`, `ticket_issue_categories` |
| Storage | Disk `local` (privat) buat lampiran — bukan `public`, karena bisa memuat data pelanggan |

## File Kode Terkait

| Area | File |
|---|---|
| Model | `app/Models/Ticket.php`, `TicketAttachment.php`, `TicketHistory.php`, `TicketIssueCategory.php` |
| Enum | `app/Enums/TicketHandler.php`, `TicketHandlingStatus.php`, `TicketBucket.php`, `TicketHistoryAction.php`, `TaskType::ticketValues()` |
| Controller | `TicketController.php`, `TicketArchiveController.php` (abstract) + `TicketSelesaiController.php` / `TicketDibatalkanController.php`, `NocWorksheetController.php`, `NocDashboardController.php` |
| Service | `app/Services/TicketService.php` — satu-satunya tempat transisi status tiket |
| Observer | `app/Observers/FopTaskObserver.php` — penulis tunggal `ticket_histories` saat `FopTask` dibatalkan |
| Policy | `app/Policies/TaskPolicy.php::cancelViaFopTask()` |
| Event | `app/Events/TicketQueueUpdated.php` |
| Seeder | `database/seeders/TicketFeatureSeeder.php` — semua Feature/permission modul ini |
| Migration | `2026_07_23_000001..000003`, `2026_07_24_000001`, `2026_07_25_000001..000003`, `2026_07_28_000001`, `2026_07_29_000001..000003`, `2026_08_05_091143` (lihat [database-schema.md](database-schema.md)) |

## Pola Redirect (PRG)

Submit dari worksheet pakai `fetch()` JSON (stay-on-page, form auto-reset). Aksi dari halaman **detail** pakai POST native → redirect balik ke `tickets.show`. Aksi dari **list/worksheet** pakai `fetch()` JSON → baris dihapus in-place, tanpa navigasi. Aturan lengkap: **[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.

---

**Last updated:** 2026-08-05 (Target SLA Ticketing: `tickets.sla_hours`/`sla_deadline_at`, warisan ke `fop_tasks.handling_sla_hours` saat eskalasi — [analisa-target-sla-ticketing.md](../plan/analisa-target-sla-ticketing.md); Worksheet Helpdesk dapet sort kolom + navigasi keyboard penuh (Arrow/Enter/C/V/B/N) — [analisa-percepatan-alur-helpdesk-noc.md](../plan/analisa-percepatan-alur-helpdesk-noc.md); bugfix drawer detail (`ticket-drawer-shown`/`hidden`); Kategori Issue sekarang tampil di Detail Ticket. Sebelumnya ADHOC-10: detail tiket di Worksheet Helpdesk & Worksheet NOC pindah ke **drawer kanan** bersama (`tickets/partials/detail-drawer.blade.php` + endpoint `tickets.detail-json`), navigasi `/tickets/{id}` disisakan buat halaman arsip; sebelumnya ADHOC-09: Worksheet NOC jadi tabel padat + pencarian + filter + dua tab **Tiket Masuk**/**Assign FOP**, aksi lewat drawer baris terpilih — tab kedua turunan data, BUKAN pengembalian Pending NOC; sebelumnya 2026-07-29 ADHOC-06: window Pending NOC + aksi Oncheck NOC dihapus, Worksheet NOC jadi satu halaman tanpa tab, kolom `noc_checked_at` di-drop; sebelumnya 2026-07-28 restrukturisasi Worksheet/Dashboard NOC, arsip jadi halaman sendiri, RBAC per-halaman)
