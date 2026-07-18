# Database Schema — Modul Ticketing

## Migrasi

| Migrasi | Isi |
|---|---|
| `2026_07_23_000001_create_tickets_table.php` | Tabel `tickets` — kolom inti |
| `2026_07_23_000002_create_ticket_attachments_table.php` | Tabel `ticket_attachments` |
| `2026_07_23_000003_create_ticket_histories_table.php` | Tabel `ticket_histories` |
| `2026_07_24_000001_add_customer_snapshot_to_tickets_table.php` | Tambah 8 kolom snapshot pelanggan ke `tickets` |

## Tabel `tickets`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `ticket_number` | string, unique | Format `TKT-{tahun}-{urut4digit}`, mis. `TKT-2026-0001` |
| `type` | string(20) | `MTN` atau `C-REQ` — `Rule::in(TaskType::ticketValues())` |
| `customer_id` | FK → `customers` | `restrictOnDelete` |
| `pop_id` | FK → `pops` | `restrictOnDelete` — snapshot POP pelanggan saat tiket dibuat, dipakai `HasPopScope` |
| `customer_name` | string, nullable | Snapshot — nama pelanggan saat tiket dibuat |
| `customer_address` | text, nullable | Snapshot — alamat |
| `customer_phone` | string, nullable | Snapshot — `primary_phone` fallback `phone` |
| `customer_odp` | string, nullable | Snapshot — `odp_code` fallback `customer_devices.odp` |
| `customer_package` | string, nullable | Snapshot — nama paket internet |
| `customer_device` | string, nullable | Snapshot — ringkasan brand+model+device_type (non-sensitif, SN/MAC/PPPoE sengaja gak ikut) |
| `customer_latitude` | decimal(10,7), nullable | Snapshot koordinat |
| `customer_longitude` | decimal(10,7), nullable | Snapshot koordinat |
| `detail_keluhan` | text | Wajib diisi |
| `catatan_teknis` | text, nullable | Opsional |
| `priority` | string(20) | `FopTaskPriority` (`low`/`Medium`/`High`/`Urgent`) |
| `created_by` | FK → `users` | `restrictOnDelete` — "Assigned by" |
| `fop_task_id` | FK → `fop_tasks`, nullable | `nullOnDelete` — kalau FOP hapus `FopTask`, tiket TETAP ada (jadi "Terputus") |
| `created_at`, `updated_at` | timestamp | |

**Index:** `[pop_id, created_at]`, `created_by`, `type`.

**Kolom yang SENGAJA gak ada:** `status` (selalu derived dari `fopTask.status`, lihat business-logic.md §5) dan `cid` (selalu dibaca live dari `customer.display_id`, bukan snapshot — lihat business-logic.md §4).

## Tabel `ticket_attachments`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `ticket_id` | FK → `tickets` | `cascadeOnDelete` |
| `file_path` | string | Path di disk `local` (privat, bukan `public`) |
| `original_name` | string | Nama file asli buat ditampilkan/download |
| `mime_type` | string(100), nullable | |
| `size` | unsignedBigInteger, nullable | Bytes |
| `uploaded_by` | FK → `users` | `restrictOnDelete` |
| `created_at`, `updated_at` | timestamp | |

## Tabel `ticket_histories`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `ticket_id` | FK → `tickets` | `cascadeOnDelete` |
| `action` | string(30) | `TicketHistoryAction`: `dibuat` atau `dibatalkan` |
| `from_status` | string(30), nullable | Status `FopTask` sebelum aksi |
| `to_status` | string(30), nullable | Status `FopTask` sesudah aksi |
| `reason` | text, nullable | Alasan (diisi kalau action=dibatalkan) |
| `actor_id` | FK → `users`, nullable | `nullOnDelete` — null kalau aksi dipicu proses sistem |
| `happened_at` | timestamp | |
| `created_at`, `updated_at` | timestamp | |

**Index:** `[ticket_id, happened_at]`.

Kembaran `fop_task_status_history` — satu pembatalan nulis ke dua tabel ini sekaligus (lihat business-logic.md §9).

## Relasi Model

```
Ticket
├─ belongsTo Customer         (customer_id)
├─ belongsTo Pop               (pop_id)
├─ belongsTo User as creator   (created_by)
├─ belongsTo FopTask           (fop_task_id)
├─ hasMany TicketAttachment
└─ hasMany TicketHistory

FopTask
└─ hasOne Ticket               (fop_task_id) — kembaran Ticket::fopTask()

TicketAttachment
├─ belongsTo Ticket
└─ belongsTo User as uploader  (uploaded_by)

TicketHistory
├─ belongsTo Ticket
└─ belongsTo User as actor     (actor_id)
```

## Kolom yang Butuh Diperhatikan Saat Eager-Load

`Customer::getDisplayIdAttribute()` (dipakai buat tampilkan CID di mana pun ticket/customer ditampilkan) butuh 3 kolom Customer yang **gampang kelewat kalau eager-load dibatasi**:

- `pop_id` — buat resolve relasi `pop()`
- `status` — buat cek `bareStatuses` (terminated/failed/dll)
- `distribution_id` — buat cek udah masuk distribusi atau belum

Kalau salah satu gak ke-select saat eager-load dengan kolom dibatasi (`'customer:id,...'`), `display_id` diam-diam jatuh ke fallback yang salah (lihat business-logic.md §7 & flowchart.md §6). Tempat yang WAJIB include 3 kolom ini: `TicketController::index()`, `FopTaskController::index()` (buat modal Edit).
