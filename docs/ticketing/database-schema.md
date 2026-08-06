# Database Schema — Modul Ticketing

## Migrasi

| Migrasi | Isi |
|---|---|
| `2026_07_23_000001_create_tickets_table.php` | Tabel `tickets` — kolom inti |
| `2026_07_23_000002_create_ticket_attachments_table.php` | Tabel `ticket_attachments` |
| `2026_07_23_000003_create_ticket_histories_table.php` | Tabel `ticket_histories` |
| `2026_07_24_000001_add_customer_snapshot_to_tickets_table.php` | 8 kolom snapshot pelanggan |
| `2026_07_25_000001_create_ticket_issue_categories_table.php` | Master kategori keluhan |
| `2026_07_25_000002_add_issue_category_id_to_tickets_table.php` | FK ke master kategori |
| `2026_07_25_000003_add_handler_and_status_to_tickets_table.php` | **`handler` + `status`** — tiket mulai punya status sendiri |
| `2026_07_28_000001_add_noc_checked_at_to_tickets_table.php` | **`noc_checked_at`** — penanda NOC sudah Oncheck (**di-drop lagi** oleh `2026_07_29_000003`) |
| `2026_07_29_000001_add_resolved_at_to_tickets_table.php` | **`resolved_at`** — waktu keluhan benar-benar beres (History Ticketing) |
| `2026_07_29_000002_add_customer_village_to_tickets_table.php` | **`customer_village`** — snapshot desa saat tiket dibuat |
| `2026_07_29_000003_drop_noc_checked_at_from_tickets_table.php` | **DROP `noc_checked_at`** — window Pending NOC dihapus (ADHOC-06) |
| `2026_08_05_091143_add_sla_fields_to_tickets_table.php` | **`sla_hours` + `sla_deadline_at`** — snapshot Handling SLA di titik tiket lahir (lihat `docs/plan/analisa-target-sla-ticketing.md`) |

### Catatan migrasi `2026_07_25_000003`

Kolom ditambahkan sebagai `string` biasa dengan default dari PHP enum (`->default(TicketHandler::HELPDESK->value)`), **bukan** native DB enum — biar penambahan nilai baru gak butuh `ALTER TABLE`.

Migrasi ini melakukan backfill `DB::table('tickets')->update(['handler' => FOP])`: semua tiket yang sudah ada dibuat sebelum perubahan ini **pasti** punya `FopTask` (dulu auto-sync), jadi menandainya sebagai `FOP` menjaga tampilan riwayat lama tetap benar.

### Catatan migrasi `2026_07_29_000003` (drop `noc_checked_at`)

Destruktif dan disetujui eksplisit: jam Oncheck yang tersimpan hilang permanen. Jejak **siapa** yang dulu meng-Oncheck tetap ada di `ticket_histories` (action `dicek_noc`), jadi audit lama tidak hilang total — itu yang membuat penghapusan kolom ini bisa diterima. `down()` hanya mengembalikan kolom kosong.

### Catatan migrasi `2026_07_29_000001` & `000002`

`resolved_at` dan `customer_village` sama-sama **di-backfill**: `resolved_at` dari `ticket_histories` (jalur internal) dan `tasks.completed_at` (jalur FOP); `customer_village` dari `customers.village_id` saat migrasi jalan — nilai perkiraan terbaik, bukan desa pelanggan pada saat tiket dulu dibuat.

### Catatan migrasi `2026_08_05_091143` (`sla_hours` + `sla_deadline_at`)

Nullable, tanpa backfill — tiket lama (dibuat sebelum kolom ini ada) tetap `NULL` selamanya, bukan error. `Ticket::slaBadgeLabel()`/`slaDeadline()` menangani `NULL` dengan mengembalikan tampilan kosong, bukan exception.

Snapshot dihitung SEKALI di `TicketService::create()` (bukan live-recalculate) — prinsip sama seperti `fop_tasks.handling_sla_hours`. `TicketService::syncToFopTask()` mewariskan `sla_hours` yang sama ke `fop_tasks.handling_sla_hours` saat eskalasi — satu clock SLA dipakai lintas Ticketing → FOP, gak reset di titik handoff. Detail lengkap: `docs/plan/analisa-target-sla-ticketing.md`.

## Tabel `tickets`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `ticket_number` | string, unique | Format `TKT-{tahun}-{urut4digit}`, mis. `TKT-2026-0001` |
| `type` | string(20) | `MTN` atau `C-REQ` — `Rule::in(TaskType::ticketValues())` |
| `issue_category_id` | FK → `ticket_issue_categories`, nullable | Master kategori; nullable karena boleh "Lainnya (isi manual)" |
| `customer_id` | FK → `customers` | `restrictOnDelete` |
| `pop_id` | FK → `pops` | `restrictOnDelete` — dipakai `HasPopScope` |
| `customer_name` | string, nullable | Snapshot |
| `customer_address` | text, nullable | Snapshot |
| `customer_phone` | string, nullable | Snapshot |
| `customer_odp` | string, nullable | Snapshot — `odp_code` fallback `customer_devices.odp` |
| `customer_package` | string, nullable | Snapshot |
| `customer_device` | string, nullable | Snapshot brand+model+device_type (non-sensitif; SN/MAC/PPPoE sengaja gak ikut) |
| `customer_latitude` | decimal(10,7), nullable | Snapshot koordinat |
| `customer_longitude` | decimal(10,7), nullable | Snapshot koordinat |
| `detail_keluhan` | text | Wajib |
| `catatan_teknis` | text, nullable | Opsional |
| `priority` | string(20) | `FopTaskPriority` (`low`/`Medium`/`High`/`Urgent`) |
| **`handler`** | string(20), default `helpdesk` | `TicketHandler` — siapa yang lagi pegang. Beku permanen begitu jadi `fop` |
| **`status`** | string(20), default `open` | `TicketHandlingStatus` — `open`/`closed`/`cancelled`. Cuma bermakna selama `handler` ≠ `fop` |
| **`resolved_at`** | timestamp, nullable | Waktu keluhan beres. Jalur internal diisi `TicketService::close()`; jalur FOP diisi `FopTaskObserver` dari `tasks.completed_at` |
| **`customer_village`** | string(150), nullable | Snapshot nama desa saat tiket dibuat (bukan join relasi — pelanggan bisa pindah desa) |
| **`sla_hours`** | unsignedSmallInteger, nullable | Snapshot Handling SLA (jam) — dihitung sekali saat tiket dibuat, lihat `TicketService::resolveSlaHours()` |
| **`sla_deadline_at`** | timestamp, nullable | `created_at` + `sla_hours` — deadline SLA tunggal, diwariskan ke `fop_tasks.handling_sla_hours` saat eskalasi, gak dihitung ulang |
| `created_by` | FK → `users` | `restrictOnDelete` — "Assigned by" |
| `fop_task_id` | FK → `fop_tasks`, nullable | `nullOnDelete` — kalau FOP hapus `FopTask`, tiket TETAP ada (jadi "Terputus") |
| `created_at`, `updated_at` | timestamp | |

**Index:** `[pop_id, created_at]`, `created_by`, `type`.

**Kolom yang SENGAJA gak ada:** `cid` — selalu dibaca live dari `customer.display_id`, bukan snapshot (lihat business-logic.md § 5).

> **Perubahan penting vs versi dokumen sebelumnya:** dokumen lama menyatakan kolom `status` "SENGAJA gak ada" karena status selalu diturunkan dari `fopTask.status`. Itu **sudah tidak berlaku** sejak `2026_07_25_000003`. Alasannya di business-logic.md § 2.

### Kombinasi kolom status yang valid

| `handler` | `status` | Arti |
|---|---|---|---|
| `helpdesk` | `open` | NULL | Ditangani Helpdesk |
| `noc` | `open` | Diproses NOC (Helpdesk & NOC dua-duanya boleh act) |
| `helpdesk`/`noc` | `closed` | apa saja | Selesai internal |
| `helpdesk`/`noc` | `cancelled` | apa saja | Dibatalkan pra-FOP |
| `fop` | diabaikan | diabaikan | Status ikut `fopTask.status` |

`handler=fop` + `fop_task_id` NULL = orphan ("Terputus") — FopTask-nya dihapus FOP. Dibedakan dari tiket yang belum pernah dieskalasi lewat kolom `handler`, **bukan** cuma cek NULL di `fop_task_id`.

## Tabel `ticket_issue_categories`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Nama kategori (mis. "LOS", "Lemot", "Backbone CUT") |
| `default_priority` | string(20) | `FopTaskPriority` — auto-isi prioritas saat kategori dipilih |
| `sla_source` | string, nullable | Sumber SLA — `'paket'` (default) pakai `InternetPackage::getHandlingSla()`, `'prioritas'` pakai `FopTaskPriority::slaHours()`. Sebelum `2026_08_05_091143` field ini dead config (cuma teks info di form, gak dibaca backend) — sekarang eksplisit dicabangkan di `TicketService::resolveSlaHours()` |
| `is_active` | boolean | Soft-toggle; kategori lama gak dihapus keras biar tiket lama gak kehilangan jejak |
| `created_at`, `updated_at` | timestamp | |

## Tabel `ticket_attachments`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `ticket_id` | FK → `tickets` | `cascadeOnDelete` |
| `file_path` | string | Path di disk `local` (privat, bukan `public`) |
| `original_name` | string | Nama file asli |
| `mime_type` | string(100), nullable | |
| `size` | unsignedBigInteger, nullable | Bytes |
| `uploaded_by` | FK → `users` | `restrictOnDelete` |
| `created_at`, `updated_at` | timestamp | |

## Tabel `ticket_histories`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `ticket_id` | FK → `tickets` | `cascadeOnDelete` |
| `action` | string(30) | `TicketHistoryAction` — lihat tabel di bawah |
| `from_status` | string(30), nullable | Nilai `handler` sebelum aksi (pra-FOP) / status FopTask (pasca-FOP) |
| `to_status` | string(30), nullable | Nilai `handler` sesudah aksi |
| `reason` | text, nullable | Alasan/catatan. **Wajib** untuk `dibatalkan` pra-FOP, opsional untuk aksi lain |
| `actor_id` | FK → `users`, nullable | `nullOnDelete` — null kalau dipicu proses sistem |
| `happened_at` | timestamp | |
| `created_at`, `updated_at` | timestamp | |

**Index:** `[ticket_id, happened_at]`.

### Nilai `action` (`TicketHistoryAction`)

| Nilai | Ditulis oleh | Kapan |
|---|---|---|
| `dibuat` | `TicketService::create()` | Tiket disubmit |
| `dieskalasi` | `escalateToNoc()` / `escalateToFop()` | Kirim ke NOC atau FOP (`to_status` membedakan) |
| `dicek_noc` | — (**usang**, ADHOC-06) | Dulu: NOC klik "Oncheck NOC". Case enum dipertahankan supaya riwayat lama tetap bisa dibaca |
| `diselesaikan` | `close()` | Selesai internal |
| `dikembalikan` | `returnToHelpdesk()` | NOC balikin ke Helpdesk |
| `dibatalkan` | `cancel()` (pra-FOP) **atau** `FopTaskObserver` (pasca-FOP) | Pembatalan |

Kembaran `fop_task_status_history` — satu pembatalan **pasca-FOP** menulis ke dua tabel sekaligus (business-logic.md § 11).

## Relasi Model

```
Ticket
├─ belongsTo Customer              (customer_id)
├─ belongsTo Pop                   (pop_id)
├─ belongsTo TicketIssueCategory   (issue_category_id)
├─ belongsTo User as creator       (created_by)
├─ belongsTo FopTask               (fop_task_id)
├─ hasMany TicketAttachment
└─ hasMany TicketHistory

FopTask
└─ hasOne Ticket                   (fop_task_id) — kembaran Ticket::fopTask()

TicketAttachment
├─ belongsTo Ticket
└─ belongsTo User as uploader      (uploaded_by)

TicketHistory
├─ belongsTo Ticket
└─ belongsTo User as actor         (actor_id)
```

## Cast & Helper di `Ticket`

```php
'handler'         => TicketHandler::class,
'status'          => TicketHandlingStatus::class,
'resolved_at'     => 'datetime',
'sla_deadline_at' => 'datetime',
```

| Helper | Kembalian |
|---|---|
| `isOrphan()` | `handler=FOP` & `fop_task_id` NULL (FopTask dihapus — label "Terputus") |
| `resolutionMinutes()` | `resolved_at` − `created_at`, null kalau belum selesai |
| `holderRoles()` | Role yang sah bertindak sekarang — dipakai Service (otorisasi) & `actionFlagsFor()` (UI) |
| `bucket()` / `scopeInBucket()` | Klasifikasi bucket, dua rezim (internal vs FopTask) |
| `checkedBy()` | **Usang** — user yang dulu Oncheck NOC; cuma relevan buat tiket lama (butuh `histories.actor` eager-loaded) |
| `slaDeadline()` / `slaTotalSeconds()` | `sla_deadline_at` / `sla_hours * 3600` — null kalau tiket gak punya snapshot SLA (data lama) |
| `isSlaBreached()` | `(resolved_at ?? now()) > sla_deadline_at` |
| `slaBadgeLabel()` / `slaBadgeClasses()` | Label & warna badge SLA statis, dipakai worksheet/arsip/history (bukan countdown live) |

## Kolom yang Butuh Diperhatikan Saat Eager-Load

`Customer::getDisplayIdAttribute()` butuh 3 kolom Customer yang **gampang kelewat kalau eager-load dibatasi**:

- `pop_id` — buat resolve relasi `pop()`
- `status` — buat cek `bareStatuses`
- `distribution_id` — buat cek sudah masuk distribusi atau belum

Tanpa salah satunya, `display_id` diam-diam jatuh ke fallback yang salah (business-logic.md § 10). Tempat yang WAJIB menyertakan ketiganya: `TicketArchiveController`, `NocWorksheetController`, `TicketController::worksheetTasks()`, `FopTaskController::index()`.

---

**Last updated:** 2026-08-05
