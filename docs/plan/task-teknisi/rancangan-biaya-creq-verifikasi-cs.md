# Rancangan: Biaya C-REQ + Verifikasi CS + Tagihan Manual

Status: **Rancangan disetujui, belum dikerjakan.**

## 1. Latar Belakang

Task/Ticket bertipe `C-REQ` (`TaskType::CREQ`) kadang punya biaya tersendiri
(mis. pindah lokasi, pindah kabel, tambah modem) yang harus ditagih ke
pelanggan. Saat ini Laporan C-REQ memakai form yang sama persis dengan
Laporan MTN (`TaskMaintenanceController::report()/store()` → tabel
`task_maintenances`), tidak ada tempat mencatat kategori pekerjaan, titik
koordinat (tikor) lama/baru, atau tanda "task ini berbayar".

Kebutuhan:
- Laporan C-REQ perlu dropdown kategori dengan field kondisional.
- Task C-REQ berbayar wajib diverifikasi oleh **CS** sebelum jadi tagihan.
- Setelah diverifikasi, CS yang input tagihannya lewat **Tagihan Manual**
  (fitur existing, `ManualInvoiceService`) — bukan otomatis jadi invoice.

Catatan penting: di codebase ini **tidak ada role "CS"** — role terdekat
adalah `helpdesk` (dikonfirmasi komentar `RolePermissionSeeder.php:348`,
"helpdesk = role terdekat 'CS'"). Semua "verifikasi CS" di rancangan ini
berarti **role `helpdesk`**. Jangan bikin role baru (larangan RBAC #1 di
`CLAUDE.md`).

## 2. Dropdown Kategori C-REQ & Field Kondisional

| Kategori | Tikor (lat/lng lama + baru) | Field tambahan |
|---|---|---|
| Pindah Lokasi | **wajib** | - |
| Pindah Kabel | **wajib** | - |
| Tambah Modem | opsional | `selected_inventory_serial_id` **wajib** (field ini sudah ada di form, dari custody tim — lihat `TaskMaintenanceController::eligibleSerialsForTeam()`) |
| Lainnya | opsional | `category_custom_name` **wajib** (nama kategori bebas) |

Field lain di form Laporan MTN (kendala_teknis, foto OPM/speedtest, material,
work tools) **tidak berubah** — tetap dipakai apa adanya untuk C-REQ.

Tambahan khusus C-REQ:
- Checkbox **"Task ini berbayar"** + textarea catatan biaya. Muncul hanya
  saat `task_type = CREQ`. Dicentang → task masuk antrean verifikasi CS.

## 3. Skema Data

Tabel baru **`task_creq_details`** (1:1 ke `tasks`/`task_maintenances`,
cuma keisi kalau `task_type = CREQ`):

| Kolom | Tipe | Keterangan |
|---|---|---|
| `task_id` | FK → `tasks` | |
| `category` | string (enum `CReqCategory`) | `pindah_lokasi` \| `pindah_kabel` \| `tambah_modem` \| `lainnya` |
| `category_custom_name` | string, nullable | wajib kalau `category = lainnya` |
| `tikor_lama_lat` / `tikor_lama_lng` | decimal, nullable | |
| `tikor_baru_lat` / `tikor_baru_lng` | decimal, nullable | |
| `is_billable` | boolean, default false | dari checkbox "Task ini berbayar" |
| `billing_note` | text, nullable | catatan biaya dari teknisi |
| `verification_status` | string (enum) | `pending` \| `verified` \| `rejected`, default `pending`; hanya bermakna kalau `is_billable = true` |
| `verified_by` | FK → `users`, nullable | |
| `verified_at` | timestamp, nullable | |
| `rejection_reason` | text, nullable | |
| timestamps | | |

Kenapa tabel baru (bukan tambah kolom ke `task_maintenances`): field-field
ini murni khusus C-REQ, tidak relevan untuk MTN/O-REQ/INFR REQ yang berbagi
controller/form sama. Memisahkan tabel menjaga `task_maintenances` tetap
bersih dan skema jelas terikat ke satu tipe task.

Enum baru: `app/Enums/CReqCategory.php` (4 nilai di atas). Status verifikasi
bisa jadi enum sendiri (`CReqVerificationStatus`) atau string constant kecil
di model — diputuskan saat implementasi, dampaknya minor.

## 4. Alur Verifikasi → Tagihan Manual

```
Teknisi submit Laporan C-REQ, is_billable = true
  → task_creq_details.verification_status = pending
  → task tetap selesai seperti biasa (TaskService::complete() tidak berubah)

CS (role helpdesk, permission baru) buka halaman "Verifikasi Biaya C-REQ"
  → list task CREQ dengan is_billable=true & verification_status=pending
  → buka detail: kendala_teknis, kategori, tikor, catatan biaya,
    foto OPM/speedtest
  → Approve:
      verification_status = verified, verified_by/verified_at diisi
      → redirect ke /invoices/create, prefill:
          - customer_id
          - kategori pendapatan (lihat mapping §5)
          - description = billing_note
  → Reject:
      verification_status = rejected + rejection_reason
      (pola sama `ReasonValidationRule` yang sudah dipakai di modul lain)

CS lanjut isi Tagihan Manual seperti biasa lewat /invoices/create
  (ManualInvoiceService — TIDAK diubah)
  → invoice muncul di Halaman Tagihan → diproses admin (alur existing)
```

Penting: verifikasi **tidak** otomatis membuat invoice. CS tetap mengisi
form Tagihan Manual secara sadar (sesuai requirement awal) — approve cuma
membuka form itu dengan data pelanggan & kategori sudah terisi.

## 5. Mapping Kategori C-REQ → Tagihan Manual

`ManualInvoiceCategory` (`app/Enums/ManualInvoiceCategory.php`) sudah
**final** sejak ADHOC-70 (3 nilai: `perbaikan`, `lainnya`, `pindah_lokasi`
— dokumen studi kasus §3.2 menyebutnya final). Rancangan ini **tidak
mengubah enum tersebut**. Mapping dipakai hanya untuk prefill form (CS
tetap bebas mengubahnya sebelum submit):

| Kategori C-REQ | Prefill `ManualInvoiceCategory` |
|---|---|
| `pindah_lokasi` | `PINDAH_LOKASI` |
| `pindah_kabel` | `PERBAIKAN` |
| `tambah_modem` | `PERBAIKAN` |
| `lainnya` | `LAINNYA`, `custom_name` diisi dari `category_custom_name` |

## 6. RBAC

- Permission baru: `tasks.creq_billing.view`, `tasks.creq_billing.verify`.
- Di-assign ke role `helpdesk` lewat `RolePermissionSeeder` (matrix role),
  **bukan** permission langsung ke user (larangan RBAC #2).
- Halaman `/tasks/creq-billing` punya permission sendiri, mengikuti pola
  "tiap halaman Ticketing/Task punya permission sendiri" (`CLAUDE.md`
  §Sinkronisasi, aturan 8).

## 7. Keputusan Desain (hasil klarifikasi)

1. **Skema data**: tabel baru `task_creq_details` (bukan kolom tambahan di
   `task_maintenances`).
2. **Mapping kategori**: Pindah Kabel & Tambah Modem → `perbaikan` (bukan
   nilai enum baru — `ManualInvoiceCategory` tetap 3 nilai final).
3. **Halaman verifikasi**: halaman baru khusus (`/tasks/creq-billing`),
   bukan tab/filter di halaman Task existing.
4. **Input tikor**: input manual lat/lng saja (konsisten pola
   `customers.latitude/longitude`), tanpa link Google Maps otomatis
   (beda dari `Ticket::googleMapsUrl()` — tidak diikutkan di rancangan ini).

## 8. File yang Akan Disentuh (scope implementasi)

- Migration baru: `create_task_creq_details_table`
- `app/Enums/CReqCategory.php` (+ enum status verifikasi bila dipisah)
- `app/Models/TaskCreqDetail.php` + relasi di `Task`/`TaskMaintenance`
- `TaskMaintenanceController::report()/store()` — tambah field dropdown +
  tikor + checkbox khusus CREQ, validasi kondisional per kategori
- `resources/views/tasks/maintenance-report.blade.php` — toggle Alpine
  kondisional per kategori
- Controller + view baru: `TaskCreqBillingController`,
  `resources/views/tasks/creq-billing/{index,show}.blade.php`
- Route baru `/tasks/creq-billing` (index, approve, reject)
- `RolePermissionSeeder.php` — 2 permission baru untuk role `helpdesk`
- Prefill query param ke `/invoices/create` (cek dukungan prefill di
  `InvoiceController@create` sebelum implementasi)
- Test baru:
  - validasi form per kategori (tikor wajib/opsional, field tambahan)
  - alur verifikasi approve/reject
  - permission gate halaman `/tasks/creq-billing`
  - regresi: task MTN/O-REQ/INFR REQ tidak terpengaruh (field CREQ nullable
    dan tidak tampil di form mereka)

## 9. Di Luar Scope

- Tidak mengubah `ManualInvoiceCategory` enum.
- Tidak bikin role baru "CS" — pakai `helpdesk`.
- Tidak ada auto-generate invoice dari hasil verifikasi.
- Tidak ada integrasi link Google Maps untuk tikor.
