# Modul Ticketing

Ticketing = tiket internal **PERUSAHAAN** (helpdesk/NOC/sales/admin/POP admin/FOP) — beda dari `FopTask` yang internal **FOP**. Tiket adalah *permintaan* (keluhan pelanggan MTN atau permintaan C-REQ); `FopTask` adalah *penugasan* yang **cuma kebentuk kalau tiketnya dieskalasi ke FOP**.

Berlaku **cuma untuk 2 tipe**: `MTN` (Maintenance) dan `C-REQ` (Customer Request) — lihat `TaskType::ticketValues()`. Tipe lain (SURVEY, PSB, O-REQ, DEAC, INFR REQ) gak pernah masuk lewat modul ini.

## Konsep Inti

Tiket punya **dua rezim status** yang berurutan. Selama masih ditangani internal (Helpdesk/NOC), statusnya kolom sendiri di tabel `tickets`. Begitu dieskalasi ke FOP, kendali pindah dan statusnya diturunkan dari `FopTask`:

```
                    ┌──────────── REZIM INTERNAL (kolom `tickets`) ─────────────┐
Ticket dibuat  →    handler=HELPDESK              handler=NOC                    │
(TKT-YYYY-NNNN)     status=OPEN        ──"Ke NOC"→ status=OPEN                    │
                    │                             noc_checked_at=NULL            │
                    │                             (PENDING NOC)                  │
                    │                                   │                        │
                    │                            "Oncheck NOC"                   │
                    │                                   ▼                        │
                    │                             noc_checked_at=<ts>            │
                    │                             (ONCHECK NOC)                  │
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

- **Tiket PUNYA kolom status sendiri** (`handler` + `status` + `noc_checked_at`) — ini berubah sejak migrasi `2026_07_25_000003`. Sebelumnya tiket sama sekali gak punya status dan selalu numpang `FopTask`; itu gak cukup begitu Helpdesk & NOC perlu menyelesaikan tiket **tanpa** melibatkan lapangan sama sekali. Detail: [business-logic.md § Dua Rezim Status](business-logic.md#2-dua-rezim-status).
- **FopTask TIDAK auto-dibuat saat submit.** Tiket lahir di tangan Helpdesk sebagai antrean mentah. `FopTask` baru kebentuk kalau eksplisit dieskalasi (`escalateToFop()`), atau kalau FOP sendiri yang submit dari halaman Task FOP. Ini kebalikan dari perilaku lama (auto-sync) yang bikin setiap keluhan — termasuk yang cukup dibenerin dari meja — langsung nyampah jadi tugas lapangan.
- **Window "Pending NOC".** Antara Helpdesk klik "Ke NOC" sampai NOC klik "Oncheck NOC", **dua-duanya** masih boleh bertindak. Begitu NOC Oncheck, Helpdesk kehilangan akses. Tujuannya: tiket gak menggantung tanpa pemilik kalau NOC belum sempat lihat, tapi begitu NOC mulai kerja gak ada dua tangan di objek yang sama.
- **`handler=FOP` itu terminal buat sisi Ticketing.** Semua aksi Ticketing (Selesai/Assign/Kembalikan/Batalkan) ditolak `assertTicketStillOpen()`. Pembatalan setelah titik ini wajib lewat `/fop-tasks`.
- **Data pelanggan di-snapshot (dibekukan), bukan dibaca live** — lihat [business-logic.md § Snapshot Data Pelanggan](business-logic.md#5-snapshot-data-pelanggan).
- **Dua riwayat per pembatalan pasca-FOP** — `fop_task_status_history` (sisi FOP) dan `ticket_histories` (sisi pengirim) ditulis bareng oleh `FopTaskObserver`. Pembatalan **pra-FOP** cuma nulis `ticket_histories` (belum ada FopTask buat dicatat).

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | Dua rezim status, window Pending NOC, aturan aksi & guard, snapshot pelanggan, bucket, dual-history, RBAC per-halaman |
| [user-flow.md](user-flow.md) | Langkah Helpdesk & NOC per halaman, skenario A/B/C, pembatalan pra & pasca FOP |
| [flowchart.md](flowchart.md) | State machine tiket, alur tiap aksi, resolusi bucket, RBAC decision tree |
| [database-schema.md](database-schema.md) | Tabel `tickets`, `ticket_attachments`, `ticket_histories`, `ticket_issue_categories` — kolom, relasi, migrasi |

## Halaman & RBAC

Lima halaman, **permission masing-masing** — dulu semuanya numpang `tickets.view` lewat route bucket generik `/tickets/{bucket}` sehingga gak bisa di-toggle per-halaman di Role Matrix.

| Halaman | Route | Permission | Buat siapa |
|---|---|---|---|
| **New Ticket** (Worksheet Helpdesk & NOC) | `/tickets/new` | `tickets.create` | Helpdesk, NOC, sales, admin, pop_admin, FOP |
| **Worksheet NOC** — tab Ticket Masuk | `/noc/worksheet/masuk` | `noc_worksheet.masuk.view` | NOC, admin |
| **Worksheet NOC** — tab Ticket Diproses | `/noc/worksheet/diproses` | `noc_worksheet.diproses.view` | NOC, admin |
| **Dashboard NOC** | `/noc/dashboard` | `noc_dashboard.view` | NOC, admin, atasan (monitoring) |
| **Ticket Selesai** | `/tickets/selesai` | `tickets.selesai.view` | semua pemegang `tickets.*` + atasan |
| **Ticket Dibatalkan** | `/tickets/dibatalkan` | `tickets.dibatalkan.view` | semua pemegang `tickets.*` + atasan |

`/noc/worksheet` (tanpa tab) adalah entry point yang dipakai sidebar — dia redirect ke tab pertama yang user boleh buka, jadi user yang cuma punya akses satu tab gak kena 403.

### Permission aksi

| Permission | Role default | Dipakai untuk |
|---|---|---|
| `tickets.view` | owner, atasan, admin, noc, helpdesk, fop, sales, pop_admin | Lihat detail tiket + download lampiran (di-scope POP lewat `HasPopScope`) |
| `tickets.create` | owner, admin, noc, helpdesk, fop, sales, pop_admin | Halaman New Ticket + submit tiket — **atasan sengaja gak dapet** (cuma monitoring) |
| `tickets.update` | idem `tickets.create` minus atasan | Selesai / Assign NOC / Assign FOP / Oncheck NOC / Kembalikan. Otorisasi per-handler (siapa yang lagi pegang) dicek di `TicketService`, bukan di permission ini |
| `tickets.cancel` | idem `tickets.update` | Batalkan tiket **pra-FOP**. Terpisah dari `tickets.update` biar bisa dicabut sendiri (mis. NOC boleh selesaikan tapi gak boleh membatalkan) |
| `fop_tasks.cancel` | owner, admin, fop | Batalkan `FopTask` — satu-satunya jalur pembatalan tiket yang **sudah** di FOP |
| `fop_tasks.create` | owner, admin, fop | Gerbang tambahan: field `technicians[]` saat submit cuma dihonor kalau aktor punya ini — mencegah helpdesk self-assign lewat request yang di-craft manual |

## Views

- `resources/views/tickets/create.blade.php` — **New Ticket**: form submit (CID lookup + auto-fill + Detail Keluhan + Catatan Teknis + Lampiran) di kiri, panel **List Task Ticketing** di kanan dengan 3 tab: **Ticket** / **Assign NOC** / **Assign FOP** (filter per `handler`, bukan per bucket)
- `resources/views/noc/worksheet.blade.php` — **Worksheet NOC**, satu halaman dua tab (Ticket Masuk / Ticket Diproses)
- `resources/views/noc/dashboard.blade.php` — **Dashboard NOC**: stat counter, list tiket aktif + aging, feed aktivitas, statistik per Issue, statistik per Daerah
- `resources/views/tickets/selesai.blade.php`, `dibatalkan.blade.php` — halaman arsip, masing-masing file sendiri; isi list-nya share `tickets/partials/archive.blade.php`
- `resources/views/tickets/show.blade.php` — detail tiket: snapshot pelanggan, keluhan, panel Aksi Tiket, dua kolom riwayat, lampiran
- `resources/views/tickets/partials/action-dialog.blade.php` — **satu-satunya** dialog konfirmasi + input alasan buat semua aksi tiket di semua halaman (numpang `window.Dialog` global). Lihat [business-logic.md § Dialog Konfirmasi](business-logic.md#12-dialog-konfirmasi--input-alasan)

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
| `/tickets/{ticket}` | GET | `tickets.view` | `TicketController@show` — `whereNumber`, didaftarkan SETELAH route statis di atas |
| `/ticket-attachments/{attachment}` | GET | `tickets.view` | `TicketController@download` — disk privat |
| `/tickets/{ticket}/close` | POST | `tickets.update` | `TicketController@close` |
| `/tickets/{ticket}/escalate` | POST | `tickets.update` | `TicketController@escalate` — `target=noc\|fop` |
| `/tickets/{ticket}/oncheck-noc` | POST | `tickets.update` | `TicketController@onCheckNoc` |
| `/tickets/{ticket}/return-to-helpdesk` | POST | `tickets.update` | `TicketController@returnToHelpdesk` |
| `/tickets/{ticket}/cancel` | POST | `tickets.cancel` | `TicketController@cancel` — **cuma pra-FOP** |
| `/noc/worksheet` | GET | — (controller yang cek) | `NocWorksheetController@index` — redirect ke tab pertama yang boleh |
| `/noc/worksheet/masuk` | GET | `noc_worksheet.masuk.view` | `NocWorksheetController@masuk` |
| `/noc/worksheet/diproses` | GET | `noc_worksheet.diproses.view` | `NocWorksheetController@diproses` |
| `/noc/dashboard` | GET | `noc_dashboard.view` | `NocDashboardController@index` |

> **Sudah dihapus:** `/tickets` (`tickets.index`) dan `/tickets/{bucket}` (`tickets.bucket`). Bucket Masuk & Diproses pindah jadi tab Worksheet NOC; Selesai & Dibatalkan jadi halaman sendiri.

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
| Migration | `2026_07_23_000001..000003`, `2026_07_24_000001`, `2026_07_25_000001..000003`, `2026_07_28_000001` (lihat [database-schema.md](database-schema.md)) |

## Pola Redirect (PRG)

Submit dari worksheet pakai `fetch()` JSON (stay-on-page, form auto-reset). Aksi dari halaman **detail** pakai POST native → redirect balik ke `tickets.show`. Aksi dari **list/worksheet** pakai `fetch()` JSON → baris dihapus in-place, tanpa navigasi. Aturan lengkap: **[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.

---

**Last updated:** 2026-07-28 (restrukturisasi: window Pending NOC + Oncheck NOC, halaman Worksheet/Dashboard NOC, arsip jadi halaman sendiri, RBAC per-halaman, dialog konfirmasi terpusat)
