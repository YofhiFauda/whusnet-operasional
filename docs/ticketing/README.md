# Modul Ticketing

Ticketing = tiket internal **PERUSAHAAN** (helpdesk/NOC/sales/admin/POP admin/FOP) — beda dari `FopTask` yang internal **FOP**. Tiket adalah *permintaan* (keluhan pelanggan MTN atau permintaan C-REQ); `FopTask` adalah *penugasan* hasil auto-sync dari permintaan itu.

Berlaku **cuma untuk 2 tipe**: `MTN` (Maintenance) dan `C-REQ` (Customer Request) — lihat `TaskType::ticketValues()`. Tipe lain (SURVEY, PSB, O-REQ, RELOKASI, DEAC, INFR) gak pernah masuk lewat modul ini.

## Konsep Inti

```
Ticket (created_by = pengirim, snapshot data pelanggan)
   │
   │ 1:1, auto-sync saat submit (TicketService::create())
   ▼
FopTask (status Draft — atau Terjadwal kalau FOP submit sambil assign teknisi)
   │
   │ FOP assign teknisi (kapan saja, lewat /fop-tasks)
   ▼
Task (eksekusi teknisi lapangan)
```

- **Ticket TIDAK punya kolom status sendiri** — status selalu diturunkan dari `FopTask.status` hasil sync (`Ticket::resolveStatus()`). Prinsip ini disengaja: dua sumber kebenaran yang bisa menyimpang adalah kelas bug yang sama yang pernah terjadi di unifikasi `FopTaskStatus` → `TaskStatus` (2026-07-20).
- **Data pelanggan di-snapshot (dibekukan), bukan dibaca live** — nama/alamat/HP/ODP/paket/perangkat/koordinat pelanggan disalin ke kolom `tickets.customer_*` saat tiket dibuat. Kalau data pelanggan berubah belakangan, riwayat tiket lama TETAP menampilkan kondisi saat keluhan dilaporkan. Detail rasional: [business-logic.md § Snapshot Data Pelanggan](business-logic.md#snapshot-data-pelanggan).
- **Dua jalur masuk, satu logic** (`TicketService::create()`): (1) helpdesk/NOC/sales submit dari `/tickets/new` → selalu Draft, teknisi di-assign FOP belakangan; (2) FOP submit langsung dari `/fop-tasks` → boleh assign teknisi & jadwal di form yang sama, skip Draft langsung ke Terjadwal.
- **Dua riwayat per pembatalan** — `fop_task_status_history` (sisi FOP) dan `ticket_histories` (sisi pengirim) ditulis bareng oleh `FopTaskObserver`, gak peduli tiket dibatalkan dari `/fop-tasks` atau lewat cascade dari `/tasks`.

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | Aturan tipe tiket, snapshot data pelanggan, bucket status, dual-history pembatalan, RBAC 3-lapis (`tickets.*`/`fop_tasks.*`/`task.cancel`), bug CID & fix-nya |
| [user-flow.md](user-flow.md) | Langkah helpdesk submit tiket, FOP assign/create langsung, cancel, lihat detail |
| [flowchart.md](flowchart.md) | Alur auto-sync ke FopTask, resolusi bucket, RBAC decision tree pembatalan, transisi Draft→Terjadwal |
| [database-schema.md](database-schema.md) | Tabel `tickets`, `ticket_attachments`, `ticket_histories` — kolom, relasi, migrasi |

## Aktor & Permission

| Permission | Role default | Dipakai untuk |
|---|---|---|
| `tickets.view` | owner, atasan, admin, noc, helpdesk, fop, sales, pop_admin | Lihat daftar/detail tiket (di-scope POP lewat `HasPopScope`) |
| `tickets.create` | owner, admin, helpdesk, fop, sales, pop_admin | Submit tiket baru — **atasan sengaja gak dapet** (cuma monitoring) |
| `fop_tasks.cancel` | owner, admin, fop | Batalkan `FopTask` (termasuk yang asalnya dari tiket) — **satu-satunya jalur pembatalan**, modul Ticketing sendiri sengaja gak punya endpoint cancel |
| `fop_tasks.create` | owner, admin, fop | Gerbang tambahan: kalau FOP submit tiket dari `/fop-tasks` sambil isi `technicians[]`, field itu cuma dihonor kalau aktor punya permission ini — mencegah helpdesk self-assign lewat request yang di-craft manual |

## Views

- `resources/views/tickets/index.blade.php` — daftar tiket ala Gmail, 4 bucket submenu (Masuk/Diproses/Selesai/Dibatalkan), search, filter tipe, toggle "Ticket Saya"
- `resources/views/tickets/create.blade.php` — form submit tiket baru (CID lookup + panel auto-fill 8 field + Detail Keluhan + Catatan Teknis + Lampiran)
- `resources/views/tickets/show.blade.php` — detail tiket: snapshot pelanggan, keluhan, dua kolom riwayat (Ticketing + Task FOP), lampiran
- `resources/views/fop_tasks/index.blade.php` — modal "Tambah Task FOP" ikut mode Ticketing kalau kategori MTN/C-REQ dipilih (create maupun edit tiket yang udah nyambung)
- `resources/views/fop_tasks/history_detail.blade.php` — Detail Task FOP nampilin section "Detail Ticket" buat MTN/C-REQ yang asalnya dari Ticketing

## Routes

| Route | Method | Permission | Controller |
|---|---|---|---|
| `/tickets/new` | GET | `tickets.create` | `TicketController@create` |
| `/tickets` | POST | `tickets.create` | `TicketController@store` — dipakai dua jalur (helpdesk & FOP), lihat [business-logic.md § Dua Jalur Masuk](business-logic.md#dua-jalur-masuk-satu-logic) |
| `/api/tickets/lookup-customer` | GET | `tickets.create` | `TicketController@lookupCustomer` — CID lookup, dipakai form create di `/tickets/new` dan modal `/fop-tasks` |
| `/tickets/{bucket}` | GET | `tickets.view` | `TicketController@index` — `bucket` dibatasi `whereIn` ke 4 nilai `TicketBucket` |
| `/tickets/{ticket}` | GET | `tickets.view` | `TicketController@show` — dibatasi `whereNumber`, gak bentrok sama route bucket |
| `/ticket-attachments/{attachment}` | GET | `tickets.view` | `TicketController@download` — disk privat, gak ada URL publik langsung |

## Teknologi

| Komponen | Stack |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Frontend | Blade, Alpine.js, Tailwind |
| Database | MySQL — `tickets`, `ticket_attachments`, `ticket_histories` |
| Storage | Disk `local` (privat) buat lampiran — bukan `public`, karena bisa memuat data pelanggan |

## File Kode Terkait

| Area | File |
|---|---|
| Model | `app/Models/Ticket.php`, `TicketAttachment.php`, `TicketHistory.php` |
| Enum | `app/Enums/TicketBucket.php`, `TicketHistoryAction.php`, `TaskType::ticketValues()` |
| Controller | `app/Http/Controllers/TicketController.php` |
| Service | `app/Services/TicketService.php` — satu-satunya tempat `Ticket` + `FopTask` kembar dibuat |
| Observer | `app/Observers/FopTaskObserver.php` — penulis tunggal `ticket_histories` saat `FopTask` dibatalkan |
| Policy | `app/Policies/TaskPolicy.php::cancelViaFopTask()` — otorisasi cascade cancel dari `/fop-tasks` ke `Task` eksekusi |
| Migration | `2026_07_23_000001..000003`, `2026_07_24_000001` (lihat [database-schema.md](database-schema.md)) |

---

**Last updated:** 2026-07-24 (sinkronisasi penuh Ticketing ↔ Task FOP: create/edit modal ikut Ticketing, Detail Task lengkap, fix CID & Draft-macet)
