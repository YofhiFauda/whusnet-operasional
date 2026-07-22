# Analisa Index, N+1 & Beban Database

**Tanggal:** 2026-07-22
**Status:** Analisa — belum ada kode/migration yang diubah
**Cakupan:** seluruh skema `whusnet_operasional` (MySQL 8) + pola query di `app/` dan `resources/views/`

---

## 1. Ringkasan Eksekutif

Delapan akar masalah ditemukan. Empat di antaranya bukan sekadar "kurang index" — tapi query yang
secara struktural tidak bisa memakai index apa pun, jadi menambah index di situ hanya menambah
biaya tulis tanpa mempercepat baca.

| # | Akar masalah | Dampak | Kelas perbaikan |
|---|---|---|---|
| A | 11 kolom legacy tanpa index | Impor legacy jadi **O(N²)** | Tambah index |
| B | `audit_logs` cuma punya index `user_id` | Tabel paling cepat tumbuh, dibaca tanpa index | Tambah index |
| C | POP scope selalu `whereIn(pop_id)` tapi composite tak ada | Semua list & laporan full scan | Tambah index |
| D | `InvoiceObserver::creating()` 2× full scan per insert | Generate tagihan bulanan **O(N²)** | Tambah index |
| E | 4 index redundan di `tasks` | Write amplification, buffer pool terbuang | **Hapus** index |
| F | `whereDate()` / `LIKE '%x%'` / JSON path / `orderByRaw` | Index mustahil dipakai | Ubah kode |
| G | Schema drift: kolom zombie, tipe kelewat lebar, `select *` | Row bloat, index mahal | Bersihkan skema |
| **H** | **11 titik N+1 / unbounded fetch** | **Ratusan query per render halaman** | **Ubah kode** |

Urutan dampak dari yang terbesar: **D → A → H → B → C → E → F → G**.
D dan A kuadratik terhadap volume data. H kuadratik terhadap *jumlah baris yang ditampilkan* —
efeknya langsung terasa user bahkan di data kecil, jadi diprioritaskan tinggi meski perbaikannya
tidak menyentuh skema sama sekali.

Poin penting soal H: **index tidak menyembuhkan N+1.** 200 query masing-masing 0,3 ms tetap 60 ms
overhead round-trip, dan biaya itu tidak turun seberapa pun bagusnya index. N+1 dan index adalah
dua sumbu masalah yang terpisah — keduanya harus dikerjakan.

---

## 2. Metodologi & Keterbatasan

Analisa disusun dari dua sumber, bukan tebakan:

1. **Index yang benar-benar ada** — dibaca dari `information_schema.STATISTICS` pada DB
   `whusnet_operasional`, lalu dicocokkan dengan 47 migration yang mendeklarasikan
   `$table->index()` / `$table->unique()`.
2. **Pola query nyata** — hasil telusur `where/orderBy/whereHas/groupBy` di seluruh
   `app/Http/Controllers`, `app/Services`, `app/Observers`, `app/Console/Commands`, `app/Traits`.

**Keterbatasan yang harus disadari:** DB lokal masih 34 tabel / 55 migration terpasang dan
seluruh tabel transaksi kosong (`customers`, `invoices`, `payments` = 0 baris). Jadi **belum ada
`EXPLAIN` di atas data nyata**. Semua estimasi biaya di dokumen ini bersifat struktural
(bentuk query × bentuk index), bukan hasil pengukuran. Verifikasi wajib dilakukan setelah
seeding volume — lihat §15.

---

## 3. Baseline — Inventaris Index Saat Ini

Yang **sudah benar** dan tidak perlu disentuh:

| Tabel | Index | Kenapa sudah cukup |
|---|---|---|
| `customers` | `unique(pop_id, customer_code)` | `pop_id` kecover sebagai leftmost prefix — jangan tambah index `pop_id` terpisah |
| `payments` | `unique(invoice_id, payment_date, amount)` | `invoice_id` kecover leftmost; guard duplikat sekaligus index lookup |
| `tasks` | `(pop_id, status, scheduled_at)`, `(status, scheduled_at)`, `(customer_id, task_type)` | composite sudah tepat bentuknya |
| `tickets` | `(pop_id, created_at)`, `(ticket_id, happened_at)` di histori | sudah sesuai pola query |
| `customer_technical_details` | `old_customer_id`, `old_request_id`, `unique(old_report_id)` | **satu-satunya tabel legacy yang di-index dengan benar** |
| `pops` | `parent_id` | dipakai `resolvePopTree()` |
| `user_role_scopes` / `_targets` | unique composite | resolusi scope murah |

Catatan penting: `cache`, `cache_locks`, `sessions`, `jobs`, `failed_jobs` ada di MySQL tapi
**tidak terpakai** — `.env` memakai Redis untuk `CACHE_STORE`, `SESSION_DRIVER`, dan
`QUEUE_CONNECTION`. Tabel-tabel itu bukan sumber beban. Cache permission/scope di
`EffectiveAccessService` juga sudah di Redis dengan TTL 1 jam, jadi RBAC **bukan** hot path DB.

---

## 4. Akar Masalah A — Kolom Legacy Tanpa Index (O(N²))

Migration `2026_06_15_000002_add_legacy_ids_to_billing_and_packages_tables.php` menambah 11 kolom
legacy ke 5 tabel. **Tidak satu pun diberi index.** Bandingkan dengan
`2026_06_15_000001_create_customer_technical_details_table.php` yang mengindeks kolom legacy-nya
dengan benar — inkonsistensi ini kemungkinan besar kelalaian, bukan keputusan.

Padahal seluruh jalur migrasi legacy melakukan lookup **per baris**:

```
CustomerController.php:1241   Customer::where('old_customer_id', …)->value('id')
CustomerController.php:1413   Customer::where('old_customer_id', …)->exists()
CustomerController.php:1595   CustomerService::where('old_request_id', …)->exists()
CustomerController.php:1699   CustomerTechnicalDetail::where('old_report_id', …)->exists()   ← ter-index, cepat
CustomerController.php:1772   Invoice::where('old_invoice_id',…)->orWhere('old_cost_id',…)
CustomerController.php:1860   Payment::where('old_payment_id', …)->exists()
CustomerController.php:1871   Invoice::where('old_invoice_id', …)->exists()
CustomerController.php:1992   InternetPackage::where('old_package_id', …)
CustomerController.php:2176   CustomerService::where('old_request_id', …)
```

Setiap panggilan = full table scan. Impor N baris ke tabel berisi M baris = **N × M row-read**.
Untuk 20.000 pelanggan artinya ratusan juta pembacaan baris, dan biayanya **naik kuadratik**
seiring impor berjalan — 1.000 baris pertama cepat, 1.000 baris terakhir merangkak.

`Invoice::where('old_invoice_id')->orWhere('old_cost_id')` (baris 1772) lebih parah lagi: `OR`
di dua kolom berbeda memaksa MySQL scan penuh **meski keduanya sudah di-index**, kecuali
optimizer memilih index merge. Perlu diverifikasi dengan `EXPLAIN` setelah index dipasang;
kalau optimizer tidak merge, pecah jadi dua query `exists()` terpisah.

**Index yang dibutuhkan** (semua non-unique — kolom nullable dan data legacy punya duplikat historis):

| Tabel | Kolom |
|---|---|
| `customers` | `old_customer_id`, `old_request_id` |
| `customer_services` | `old_request_id`, `old_cost_id` |
| `invoices` | `old_invoice_id`, `old_cost_id`, `old_request_id` |
| `payments` | `old_payment_id`, `old_transaction_id`, `old_request_id` |
| `internet_packages` | `old_package_id` |

Semua bertipe `varchar(50)` — murah untuk di-index (≤200 byte utf8mb4).

Terkait dengan `[[project_legacy_migration_id_collision]]`: karena ID legacy sudah di-scope
per-POP, index tunggal di kolom legacy tetap benar; scoping cabang dilakukan lewat join ke
`pop_id`, dan index kolom legacy inilah yang memfilter duluan.

---

## 5. Akar Masalah B — `audit_logs` Praktis Tanpa Index

Skema `audit_logs` hanya punya `PRIMARY(id)` + FK `user_id`. Tidak ada index untuk
`auditable_type`, `auditable_id`, `module`, `action`, maupun `created_at`.

Ini tabel yang **paling cepat tumbuh** di sistem — ditulis oleh trait `RecordsAuditLogs` dan
belasan controller (`CustomerController`, `CustomerVerificationController`,
`CustomerTerminationController`, `CustomerNetworkAssignmentController`,
`CustomerDocumentController`, plus 4 console command). Setiap transisi status pelanggan,
setiap verifikasi, setiap terminasi menambah satu baris.

Query pembacanya:

```
CustomerController.php:169   where(auditable_type) + whereIn(auditable_id) + where(module)
                             + where(action) + orderByDesc(created_at)
CustomerController.php:257   idem, per-customer, dieksekusi di setiap halaman detail pelanggan
AuditLogController.php:23    where(module) / where(action) / orderBy(created_at) + paginate
AuditLogController.php:42-43 SELECT DISTINCT module; SELECT DISTINCT action
```

Dua baris terakhir yang paling boros: **dua full table scan hanya untuk mengisi dropdown filter**,
dijalankan setiap kali halaman audit dibuka. Bahkan dengan index pun `DISTINCT` di tabel besar
mahal — solusi benar adalah cache Redis (TTL menit) atau tabel lookup master modul/aksi.

**Index yang dibutuhkan:**

- `(auditable_type, auditable_id, created_at)` — riwayat per entitas, sekaligus melayani `ORDER BY`
- `(module, action, created_at)` — filter halaman audit
- `(created_at)` — `orderBy` + `paginate` tanpa filter

Perhatikan `auditable_type` bertipe `varchar(255)`. Di utf8mb4 itu 1020 byte per entri index —
mahal. Isinya cuma FQCN model (`App\Models\Customer`, dst.), jadi `varchar(100)` lebih dari cukup.
Persempit kolomnya **sebelum** memasang index; lihat §10 dan Fase 4 di §13.

---

## 6. Akar Masalah C — POP Scope Tanpa Composite Index

`HasPopScope::scopeApplyUserScope` (`app/Traits/HasPopScope.php:24`) diterapkan di hampir seluruh
list: `CustomerController`, `InvoiceController`, `PaymentController`, `TicketController`,
`DashboardController`, dan ketiga controller laporan. Untuk user non-owner selalu menghasilkan
`WHERE pop_id IN (…)`.

Bentuk query nyata di semua tempat itu identik:

```sql
WHERE pop_id IN (…) AND <status/periode/tanggal> = ? ORDER BY <kolom> DESC LIMIT 15
```

Tapi `customers` hanya punya index FK single-column. `status`, `data_completeness_status`,
`registration_date` **tidak ter-index sama sekali** — padahal `status` adalah kolom filter paling
sering dipakai di seluruh aplikasi (`CustomerController:90/108/112`, `DashboardController:57-64`,
`FopDashboardController:46/75/82/91`).

Efek sampingnya ganda: bukan cuma `SELECT` yang scan, tapi juga `COUNT(*)` yang dijalankan
Laravel untuk paginator — jadi **dua** full scan per halaman.

**Index yang dibutuhkan:**

| Tabel | Index | Pemanggil |
|---|---|---|
| `customers` | `(pop_id, status)` | CustomerController:90/112/127, Dashboard:57-64 |
| `customers` | `(pop_id, data_completeness_status)` | CustomerReport:50-54, Dashboard:60-64 |
| `customers` | `(status, created_at)` | FopDashboard:45-47 & 74-77 (antrian survey, urut terlama) |
| `customers` | `(pop_id, registration_date)` | CustomerReport:62-71 |

Urutan kolom penting: `pop_id` di depan karena selalu ada (scope wajib), `status` menyusul karena
opsional. Index `(status, pop_id)` **tidak** setara dan tidak akan terpakai saat filter status kosong.

Terkait `[[project_customer_list_status_group_visibility]]`: filter `status_group` menghasilkan
`whereIn('status', [4-5 nilai])`. Index `(pop_id, status)` tetap efektif untuk `IN` — MySQL
melakukan range scan per nilai.

---

## 7. Akar Masalah D — Observer Full-Scan Per Insert (paling parah)

`InvoiceObserver::creating()` menjalankan **dua** query sebelum setiap baris invoice masuk:

```php
// app/Observers/InvoiceObserver.php:48 — guard burst duplicate
Invoice::where('customer_id', …)->where('invoice_type', …)
    ->where('billing_period', …)->where('total_amount', …)
    ->where('created_at', '>=', now()->subSeconds(300))->exists();

// app/Observers/InvoiceObserver.php:96 — guard satu langganan per periode
Invoice::where('customer_id', …)->where('billing_period', …)
    ->whereIn('invoice_type', [AWAL, BULANAN])
    ->where('invoice_status', '!=', BATAL)->exists();
```

Tidak satu pun kolom itu ter-index. Tabel `invoices` hanya punya FK + `unique(invoice_number)`.

Ini **titik terburuk di seluruh sistem**, alasannya:

1. Guard ini sengaja ditaruh di observer supaya berlaku di semua jalur masuk — jadi biayanya kena
   di manual input, impor, tinker, *dan* generate bulanan.
2. `GenerateMonthlyInvoicesCommand` menerbitkan invoice untuk **semua** pelanggan aktif dalam satu
   run. Invoice ke-N melakukan scan atas tabel yang sudah berisi N-1 baris baru → **O(N²)** dalam
   satu perintah.
3. `GenerateMonthlyInvoicesCommand:75-78` menjalankan cek serupa untuk **ketiga** kalinya sebelum
   memanggil create.
4. Dan `GenerateMonthlyInvoicesCommand:104` menambah lagi
   `where('invoice_number', 'like', "INV-{$periodCode}-%")->orderBy('invoice_number','desc')` —
   ini `LIKE` dengan wildcard di *akhir*, jadi index `unique(invoice_number)` **bisa** dipakai.
   Sudah aman, biarkan.

Migration `2026_07_21_164556` sudah mendokumentasikan kenapa unique index
`(customer_id, invoice_type, billing_period)` **tidak boleh** dipasang: invoice berstatus `batal`
akan terus menempati slot periode dan memblokir penggantinya, dan MySQL tidak punya partial index.
Keputusan itu benar dan tetap dipertahankan.

**Tapi index NON-UNIQUE dengan kolom yang sama tidak punya masalah itu sama sekali.** Ia hanya
mempercepat lookup, tidak menolak apa pun. Ini perbaikan dengan rasio manfaat/risiko tertinggi
di seluruh dokumen:

```php
$table->index(['customer_id', 'billing_period', 'invoice_type'], 'invoices_customer_period_type_idx');
```

Satu index ini melayani **ketiga** query guard sekaligus (guard kedua pakai prefix
`customer_id, billing_period`; guard pertama pakai ketiganya).

`PaymentObserver::creating()` sebaliknya tidak melakukan query sama sekali — hanya validasi
nominal in-memory. Tidak perlu diapa-apakan.

---

## 8. Akar Masalah E — Index Redundan (biaya tulis sia-sia)

Tabel `tasks` punya **12 index**. Empat di antaranya adalah prefix persis dari composite yang
ditambahkan `2026_07_01_160949_add_composite_indexes_to_tasks_tables.php`. Optimizer MySQL tidak
akan pernah memilih index yang lebih sempit ketika ada superset-nya, tapi setiap `INSERT`/`UPDATE`
tetap membayar pemeliharaan B-tree dan memakan halaman buffer pool.

| Hapus | Sudah tercakup oleh |
|---|---|
| `tasks_status_index` (`status`) | `tasks_status_scheduled_idx` (`status`, `scheduled_at`) |
| `tasks_pop_id_index` (`pop_id`) | `tasks_pop_status_idx` (`pop_id`, `status`) |
| `tasks_pop_status_idx` (`pop_id`, `status`) | `tasks_pop_status_scheduled_idx` (`pop_id`, `status`, `scheduled_at`) |
| `tasks_customer_id_index` (`customer_id`) | `tasks_customer_type_idx` (`customer_id`, `task_type`) |

**Pertahankan** `tasks_scheduled_at_index` dan `tasks_task_type_index` — keduanya bukan prefix
index mana pun, dan `task_type` dipakai sendirian di `FopDashboardController:84`.

Karena `tasks` ditulis sangat sering (setiap start/complete/pending/cancel/reassign lewat
`TaskService`, plus sinkronisasi balik dari `FopTask`), menghapus 4 B-tree ini menurunkan biaya
tulis sekitar 30% tanpa efek apa pun ke baca.

Catatan: `tasks_pop_id_index` mungkin dibuat MySQL otomatis untuk menopang foreign key. Kalau
`DROP INDEX` ditolak dengan errno 150, drop FK constraint dulu atau pastikan composite
`tasks_pop_status_idx` sudah ada sebagai penopang (leftmost `pop_id` memenuhi syarat FK).

---

## 9. Akar Masalah F — Query yang Tidak Bisa Pakai Index

Bagian ini yang **tidak bisa** diselesaikan dengan migration. Menambah index di sini justru
memperburuk: bayar biaya tulis, tidak dapat percepatan baca.

### F.1 `whereDate()` mematikan index

MySQL membungkus kolom jadi `DATE(kolom)`. Fungsi di sisi kiri = index tidak sargable.
Yang paling ironis: `issue_date`, `due_date`, `payment_date`, `registration_date` **sudah bertipe
`date`** — pembungkusnya benar-benar tanpa guna.

```
InvoiceReportController.php:62,66,149,153    whereDate('issue_date', …)
PaymentReportController.php:67,71,153,157    whereDate('payment_date', …)
CustomerReportController.php:62,66,131,135   whereDate('registration_date', …)
DashboardController.php:74,88                whereDate('due_date', '<=', …)
NotificationController.php:49                whereDate('created_at', …)
FopDashboardController.php:96,116,357,361    whereDate('scheduled_at', …)
FopTaskController.php:694                    whereDate('task_date', …)
```

Perbaikan:
- Kolom bertipe `date` → ganti langsung jadi `where('issue_date', '>=', $x)`.
- Kolom bertipe `datetime`/`timestamp` (`scheduled_at`, `created_at`) → `whereBetween` dengan
  batas awal & akhir hari: `whereBetween('scheduled_at', [$d->startOfDay(), $d->endOfDay()])`.

**Index di §12 untuk kolom-kolom ini tidak akan memberi manfaat apa pun sampai perbaikan
ini dikerjakan.** Kerjakan F.1 dulu, baru pasang indexnya.

### F.2 `LIKE '%x%'` di 9 kolom sekaligus

`CustomerController.php:75-85` mencari di `full_name`, `customer_code`, `old_customer_id`,
`old_request_id`, `cid`, `email`, `phone`, `primary_phone`, `identity_number` — semuanya dengan
wildcard di depan. Tidak ada index B-tree yang bisa dipakai. Selalu full scan, dan karena
di-`OR` semuanya, MySQL mengevaluasi 9 perbandingan string per baris.

Pilihan perbaikan, berurutan dari yang paling ringan:
1. **Pisahkan berdasarkan bentuk input.** Kalau input numerik/beralfanumerik pola kode
   (`C1X4ARQ…`, nomor HP), pakai prefix `LIKE 'x%'` — index B-tree langsung terpakai.
   Hanya `full_name` yang benar-benar butuh substring.
2. **FULLTEXT index** di `full_name` + `MATCH … AGAINST` untuk pencarian nama.
3. Kurangi jumlah kolom yang di-scan: `old_request_id` dan `identity_number` jarang dicari manual.

`TicketController.php:62-65` punya masalah sama tapi lebih ringan, dan `:65` menambahkan
`whereHas('customer', … LIKE …)` — subquery dependen di atas kolom tanpa index.

### F.3 JSON path di atas kolom TEXT

Dua tempat, dua tingkat keparahan:

**`NotificationController.php:53` — `where('data->type', $request->type)`**
Kolom `notifications.data` dideklarasikan `$table->text('data')` (bukan `json`). Operator `->`
memaksa MySQL meng-CAST tiap baris ke JSON lalu mengekstrak path — full scan + parsing per baris.
Ini bukan sekadar lambat, tapi rapuh: baris dengan JSON tidak valid bisa melempar error.
Perbaikan: kolom generated + index.

```sql
ALTER TABLE notifications
  ADD COLUMN notification_type VARCHAR(100)
    GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, '$.type'))) STORED,
  ADD INDEX notifications_type_idx (notification_type);
```

**`CustomerController.php:145 & 173` — `whereJsonContains('new_values->status', 'rejected')`
di dalam subquery `ORDER BY`**
Ini yang terburuk. Bentuknya subquery **berkorelasi** (`whereColumn('auditable_id', 'customers.id')`)
di klausa `ORDER BY`, jadi dieksekusi **sekali per baris pelanggan**, dan tiap eksekusi men-scan
`audit_logs` sambil mem-parse JSON. Biaya = `jumlah_pelanggan × jumlah_audit_logs`.

Filter `status_group=failed` akan berhenti bisa dipakai begitu `audit_logs` mencapai ratusan ribu
baris. Tidak ada index yang menyelamatkan ini.

Perbaikan yang benar: simpan `rejected_at` dan `terminated_at` sebagai **kolom nyata** di
`customers`, diisi oleh observer/`CustomerWorkflowService` saat transisi terjadi. `ORDER BY` jadi
kolom biasa yang bisa di-index, dan `audit_logs` kembali ke fungsi aslinya (jejak audit, bukan
sumber data operasional). Baris 169-207 (`$rejectLogs`/`$terminateLogs` untuk ditampilkan) boleh
tetap membaca `audit_logs` — itu sudah dibatasi `whereIn(auditable_id, $customerIds)` untuk 15
baris satu halaman, dan akan cepat setelah index bagian B terpasang.

### F.4 `orderByRaw` bertingkat = filesort wajib

`FopTaskController.php:62-72` mengurutkan papan FOP dengan empat ekspresi `orderByRaw`
berturut-turut (`CASE WHEN client_request_date …`, dst.). Ekspresi tidak bisa dilayani index —
MySQL **selalu** filesort seluruh hasil.

Index tetap berguna untuk mempersempit `WHERE`-nya, tapi perhatikan `whereNotIn('status', [selesai, dibatalkan])`
adalah negasi dengan selektivitas rendah — di papan yang sehat sebagian besar baris lolos filter.
Perbaikan struktural: batasi rentang `task_date` (papan FOP realistis hanya butuh beberapa hari
ke depan/belakang), sehingga filesort bekerja atas puluhan baris, bukan puluhan ribu.

### F.5 `whereHas` = subquery dependen

```
FopDashboardController.php:83-88   whereHas('tasks', … task_type + status + completed_at < …)
FopDashboardController.php:275     whereHas('teamMembers', …)
FopDashboardController.php:347,353 whereHas('teamMembers', …) di dalam LOOP per teknisi
FopTaskController.php:695          whereHas('technicians', …)
```

`FopDashboardController:337-364` adalah N+1 kelas berat: loop atas semua teknisi, dan di dalamnya
**dua** query `whereHas` per teknisi. 20 teknisi = 40 subquery per render dashboard — dan dashboard
ini auto-refresh (lihat `FopDashboardAutoRefreshTest`). Perbaikan: satu query agregat
`GROUP BY user_id` di luar loop.

`tasks.completed_at` tidak ter-index sama sekali padahal dipakai di `FopDashboardController:86`.

---

## 10. Akar Masalah G — Schema Drift & Row Bloat

`customers` punya **48 kolom**, termasuk satu `TEXT` (`address`). Setiap list memuat seluruh baris
via `select *` (tidak ada `->select()` di `CustomerController::index`), dengan 10 relasi
di-eager-load. Halaman 15 baris menarik jauh lebih banyak data dari yang dirender.

Masalah struktural yang terlihat dari inventaris kolom:

**Kolom status ganda.** Ada `status` **dan** `customer_status` **dan** `old_account_status`.
`customer_status` hanya dipakai sebagai variabel antara untuk mengisi
`customer_services.service_status` (`CustomerController:414, 511, 663, 800`) — nilainya duplikat
dan bisa menyimpang dari `status`. Dua sumber kebenaran untuk satu konsep.

**Kolom telepon ganda tiga.** `phone`, `primary_phone`, `alternative_phone`. Pencarian
(`CustomerController:82-83`) dan cek duplikat (`:1436`) harus menyapu dua kolom dengan `OR` —
menggandakan biaya dan membuat index tidak efektif.

**Kolom teknis duplikat.** `ont_sn`, `olt_code`, `odp_code`, `vlan_id`, `ip_address` ada di
`customers` **dan** di `customer_technical_details` / `customer_services`. Melebarkan baris yang
paling sering dibaca demi data yang jarang dipakai.

**Tipe kolom kelewat lebar untuk kandidat index:**

| Kolom | Sekarang | Cukup | Alasan |
|---|---|---|---|
| `customers.cid` | `varchar(150)` | `varchar(50)` | pernah di-`enlarge` (migration 2026_06_19) untuk data legacy; format CID nyata jauh lebih pendek |
| `audit_logs.auditable_type` | `varchar(255)` | `varchar(100)` | isinya FQCN model |
| `customers.status` dkk. | `varchar(50)` | `varchar(30)` | nilai enum terpanjang `installation_in_progress` = 24 char |

Di utf8mb4 setiap karakter = 4 byte, jadi `varchar(255)` = 1020 byte per entri index. Mempersempit
kolom sebelum mengindeks menurunkan ukuran index 2-5×, yang langsung berarti lebih banyak muat di
buffer pool.

**Master data dimuat penuh tanpa limit.** Ada 8 pemanggilan `Village::/District::/City::…->get()`
tanpa batas, termasuk `CustomerController:1118` (`Village::with('district')->orderBy('name')->get()`)
dan `FopTaskController:126`. Untuk cakupan Madiun+Ponorogo saat ini masih ratusan baris — belum
kritis, tapi akan meledak begitu wilayah bertambah. Ganti dengan endpoint pencarian
(`?q=` + limit) atau cache Redis.

**`FopTaskController:129` — `Pop::orderBy('name')->get()` tanpa `forUser()`.** Bukan isu performa
tapi isu scope: dropdown menampilkan seluruh POP lintas cabang. Bandingkan dengan controller lain
yang konsisten memakai `Pop::forUser()`. Perlu dikonfirmasi apakah disengaja.

---

## 11. Akar Masalah H — N+1 Query & Unbounded Fetch

Bagian ini murni soal kode. **Tidak ada satu pun yang bisa diperbaiki dengan menambah index.**

Konteks kenapa masalah ini bisa menumpuk tanpa ketahuan: `AppServiceProvider` tidak memanggil
`Model::preventLazyLoading()` sama sekali, jadi setiap lazy load diam-diam berhasil dan tidak
pernah melempar error di development maupun di test.

Sebelum masuk daftar temuan, perlu dicatat bahwa **sebagian besar list di repo ini sudah benar**.
`InvoiceController::index`, `PaymentController::index`, `CustomerController::show`,
`TicketController::index`, dan `FopTaskController::index` semuanya sudah memakai `with()` yang
lengkap, termasuk relasi bertingkat seperti `auditLogs.user.role`. Masalahnya terkonsentrasi di
tiga pola: **accessor yang menyentuh relasi**, **agregasi di dalam loop**, dan **`->get()` tanpa
batas**.

### H.1 Accessor `clean_address` — 3 lazy load per pelanggan

`Customer::getCleanAddressAttribute()` (`app/Models/Customer.php:373`) membaca `$this->village`,
`$this->district`, dan `$this->city`. Ketiganya relasi. Kalau tidak di-eager-load, setiap
pemanggilan accessor = **3 query**.

Ini masalah paling menyebar di repo, karena accessor terlihat seperti properti biasa di Blade —
tidak ada petunjuk visual bahwa `{{ $task->customer->clean_address }}` memicu query.

| Lokasi | Eager load yang ada | Biaya |
|---|---|---|
| `FopDashboardController.php:132` | `['customer', 'pop', 'teamMembers.user']` | 3 × jumlah task hari ini |
| `FopDashboardController.php:188` | `['fopTasks.customer', …]` | 3 × **seluruh** task di **seluruh** team |
| `FopDashboardController.php:375` | `Task::with('customer')` di dalam loop teknisi | 3 × jumlah teknisi |
| `resources/views/tasks/own.blade.php:133` | `TaskController:44` — `['customer', …]` | 3 × jumlah task teknisi |
| `resources/views/tasks/partials/own-card.blade.php:61` | idem | 3 × jumlah kartu |
| `resources/views/tasks/show.blade.php:186` | halaman detail | 3 (dapat diterima) |

Dashboard FOP memanggilnya di tiga tempat berbeda dalam satu render. Dengan 30 task aktif dan
15 teknisi, hanya dari accessor ini saja sudah ±135 query — dan dashboard FOP **auto-refresh**
(lihat `FopDashboardAutoRefreshTest`), jadi biaya itu berulang terus tanpa interaksi user.

`InvoiceController.php:137` (`$invoice->customer->append('clean_address')`) adalah satu-satunya
pemakaian yang sudah aman — `load()` di baris 120-127 sudah menyertakan `customer.village`,
`customer.district`, `customer.city`. **Jadikan itu acuan.**

### H.2 `getTeknisiList()` — 2 query per teknisi, dan ada DUA salinannya

```php
// FopDashboardController.php:337-364  DAN  CustomerVerificationController.php:444-489
return $query->get()->map(function (User $teknisi) use ($today) {
    $activeTask = Task::with('customer')
        ->whereHas('teamMembers', fn ($q) => $q->where('user_id', $teknisi->id))
        ->where('status', IN_PROGRESS)->latest('started_at')->first();     // query #1

    $taskCount = Task::whereHas('teamMembers', fn ($q) => $q->where('user_id', $teknisi->id))
        ->where(…)->count();                                               // query #2
    …
});
```

Dua query per teknisi, masing-masing memakai `whereHas` (subquery dependen), dan `$activeTask`
lalu memicu `clean_address` (H.1) = 3 query lagi. **5 query per teknisi.** 20 teknisi = 100 query,
hanya untuk daftar status teknisi di sidebar.

Yang memperburuk: logikanya **diduplikasi utuh** di dua controller. Satu perbaikan harus
diterapkan dua kali, atau — lebih baik — diekstrak ke satu service.

### H.3 `FopTaskTeam::isActive()` — query per team, padahal datanya sudah ada

```php
// app/Models/FopTaskTeam.php:45
public function isActive(): bool
{
    return $this->fopTasks()->whereNotIn('status', [SELESAI, DIBATALKAN])->exists();
}

// app/Http/Controllers/FopDashboardController.php:148
FopTaskTeam::with(['members', 'fopTasks.technicians', 'fopTasks.customer', 'fopTasks.task'])
    ->get()
    ->filter->isActive()      // ← query baru per team, mengabaikan fopTasks yang sudah dimuat
```

`fopTasks()` dengan tanda kurung memanggil **relasi**, bukan koleksi yang sudah dimuat — jadi
`with(['fopTasks.…'])` di baris atasnya terbuang sia-sia untuk keperluan filter ini.

Yang menarik: `FopTaskController.php:153-156` menyelesaikan kebutuhan yang **persis sama** dengan
benar — memfilter koleksi yang sudah dimuat di memori, tanpa query tambahan. Perbaikan H.3 cukup
menyalin pola yang sudah ada di repo sendiri.

### H.4 `FopTaskTeam` dimuat seluruhnya tanpa batas tanggal

`FopDashboardController.php:142-148` memuat **semua** team yang pernah ada, lengkap dengan
members, fopTasks, technicians, customers, dan tasks — baru kemudian difilter di PHP.

`FopTaskTeamService::rebuildTeamsForDate()` membuat team baru setiap tanggal kerja. Setelah satu
tahun operasi, ini berarti 300+ team beserta seluruh anak-anaknya dimuat ke memori setiap kali
dashboard di-refresh, untuk menampilkan segelintir team hari ini.

Sekali lagi `FopTaskController.php:151` sudah benar dengan `->limit(50)`. Dashboard tidak.

### H.5 `CustomerValidationService` — `load()` tanpa syarat

```php
// app/Services/CustomerValidationService.php:87   ← BENAR
if (! $customer->relationLoaded('customerService')) {
    $customer->load('customerService');
}

// app/Services/CustomerValidationService.php:169  ← SALAH, di method yang sama alur
$customer->load('customerService');
```

`load()` selalu menembak DB, bahkan ketika relasi sudah dimuat. Karena
`resources/views/customers/index.blade.php:332` memanggil `$customer->dataCompleteness()`
**per baris**, ini menjadi 1 query ekstra per pelanggan di daftar — meski
`CustomerController:72` sudah rajin meng-eager-load `customerService`.

Baris 87 di file yang sama membuktikan penulisnya tahu caranya. Baris 169 kelewat.

### H.6 `CustomerController::show` — `load()` dobel

`CustomerController.php:855-876` memuat 17 relasi termasuk `pop`. Lalu baris 889 memuat ulang:

```php
$customer->load(['pop', 'distribution', 'miniPop']);   // 'pop' ditembak dua kali
```

Satu query terbuang per kunjungan halaman detail. Kecil, tapi gejala dari pola yang sama dengan
H.5 — `load()` dipakai di tempat yang seharusnya `loadMissing()`.

### H.7 Export laporan memuat seluruh tabel ke memori

Ketiga controller laporan punya pola identik:

```
InvoiceReportController.php:161    $query->orderByDesc('issue_date')->orderByDesc('id')->get();
PaymentReportController.php:162    idem
CustomerReportController.php:140   idem
```

Lalu koleksi itu dioper ke closure `response()->stream(...)`.

Ini **bukan** N+1 — relasinya sudah di-eager-load dengan benar. Tapi masalahnya justru lebih
serius: `->get()` **tanpa `LIMIT`** memuat seluruh hasil ke memori PHP *sebelum* streaming dimulai,
sehingga seluruh manfaat `StreamedResponse` hilang. Ekspor 240.000 invoice = 240.000 model Invoice
+ 240.000 model Customer + relasi lain, sekaligus di RAM. Kandidat kuat OOM di produksi.

Perbaikan: `->lazy()` atau `->cursor()` di dalam closure, sehingga baris ditarik dan ditulis
sambil jalan.

### H.8 `->get()` tanpa batas di halaman detail & dropdown

| Lokasi | Yang dimuat | Risiko |
|---|---|---|
| `CustomerController.php:978-988` | **seluruh** `audit_logs` pelanggan | Tumbuh selamanya, tak pernah dipangkas |
| `CustomerController.php:998-1007` | seluruh `tasks` + 5 relasi bertingkat | Sedang |
| `CustomerController.php:1008-1011` | seluruh `fopTasks` + 3 relasi | Sedang |
| `FopTaskController.php:183-192` | **seluruh** FopTask aktif lintas team & halaman | Di-render jadi JSON dropdown |
| `TicketController.php:245-252` | hasil pencarian pelanggan | Perlu `->limit()` |

`audit_logs` yang paling mengkhawatirkan: satu pelanggan berumur 3 tahun dengan puluhan transisi
status, verifikasi, dan perubahan data akan menumpuk ratusan baris yang **semuanya** dirender ke
satu halaman. Butuh paginasi atau `->limit()` + tombol "lihat semua".

### H.9 Master wilayah dimuat penuh (lihat juga §10)

8 pemanggilan `Village::/District::/City::…->get()` tanpa batas. Yang paling berat
`CustomerController.php:1118` — `Village::with('district')->orderBy('name')->get()` — memuat
seluruh desa **beserta** kecamatannya untuk mengisi satu `<select>`.

### H.10 `Pop::forUser()` memakai jalur scope yang salah

```php
// app/Models/Pop.php:132
return $query->whereHas('users', fn ($q) => $q->where('user_id', $user->id));
```

Ini pivot `user_pops` — jalur lama yang `CLAUDE.md` peringatkan ("Tidak paham `pop_tree`").
Jalur yang benar adalah `EffectiveAccessService::getAllowedPopIds()`, yang **sudah di-cache Redis**.

Dampaknya ganda:
1. **Performa** — `whereHas` menembak DB setiap pemanggilan, padahal versi ter-cache tersedia.
   `Pop::forUser()` dipanggil di hampir semua halaman index.
2. **Kebenaran** — pengecekan rolenya `in_array($user->role?->name, ['Owner','Admin','Admin Pusat'])`
   memakai `name`, sementara seluruh repo memakai `code`. `'Admin Pusat'` bahkan tidak ada di
   `RoleSeeder`. Perlu dikonfirmasi apakah ini bug tersendiri.

### H.11 `FopTaskTeam::workloadSummary()` — bom waktu

```php
// app/Models/FopTaskTeam.php:53
public function workloadSummary(): array
{
    return $this->fopTasks()->with('technicians:id')->get()…
}
```

Saat ini belum dipanggil di dalam loop, jadi belum berbahaya. Tapi bentuknya (method model yang
menembak DB) membuatnya jadi N+1 begitu ada yang memanggilnya per team. Tandai supaya tidak
kejadian.

---

## 12. Rencana Index — Daftar Lengkap

### P0 — Kuadratik, kerjakan duluan

```php
// invoices — melayani KETIGA query guard duplikat (Observer:48, Observer:96, Command:75)
$table->index(['customer_id', 'billing_period', 'invoice_type'], 'invoices_customer_period_type_idx');

// audit_logs
$table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_logs_auditable_idx');
$table->index(['module', 'action', 'created_at'], 'audit_logs_module_action_idx');
$table->index('created_at', 'audit_logs_created_at_idx');

// kolom legacy — 11 index single-column
customers            : old_customer_id, old_request_id
customer_services    : old_request_id, old_cost_id
invoices             : old_invoice_id, old_cost_id, old_request_id
payments             : old_payment_id, old_transaction_id, old_request_id
internet_packages    : old_package_id
```

### P1 — List & laporan (kerjakan SETELAH perbaikan F.1)

```php
// customers
$table->index(['pop_id', 'status'], 'customers_pop_status_idx');
$table->index(['pop_id', 'data_completeness_status'], 'customers_pop_completeness_idx');
$table->index(['status', 'created_at'], 'customers_status_created_idx');
$table->index(['pop_id', 'registration_date'], 'customers_pop_registration_idx');

// invoices
$table->index(['pop_id', 'billing_period'], 'invoices_pop_period_idx');
$table->index(['pop_id', 'issue_date'], 'invoices_pop_issue_idx');
$table->index(['invoice_status', 'due_date'], 'invoices_status_due_idx');

// payments
$table->index(['pop_id', 'payment_date'], 'payments_pop_date_idx');
$table->index(['payment_status', 'payment_date'], 'payments_status_date_idx');
$table->index(['customer_id', 'payment_date'], 'payments_customer_date_idx');
```

### P2 — Operasional lapangan

```php
$table->index(['task_type', 'status', 'completed_at'], 'tasks_type_status_completed_idx');  // tasks
$table->index(['pop_id', 'task_date', 'status'], 'fop_tasks_pop_date_status_idx');          // fop_tasks
$table->index(['customer_id', 'category', 'status'], 'fop_tasks_customer_cat_status_idx');  // fop_tasks
$table->index(['notifiable_id', 'notifiable_type', 'read_at'], 'notifications_unread_idx'); // notifications
$table->index(['customer_id', 'created_at'], 'customer_status_logs_customer_created_idx');
$table->index(['customer_id', 'service_status'], 'customer_services_customer_status_idx');
```

Setelah `fop_tasks_pop_date_status_idx` terpasang, index single `fop_tasks_status_index`,
`fop_tasks_task_date_index`, dan `fop_tasks_priority_index` perlu ditinjau ulang — kemungkinan
besar `fop_tasks_task_date_index` masih dibutuhkan (dipakai sendirian), tapi `status` bisa jadi
redundan tergantung pola query final.

### Hapus

```php
$table->dropIndex('tasks_status_index');
$table->dropIndex('tasks_pop_id_index');
$table->dropIndex('tasks_pop_status_idx');
$table->dropIndex('tasks_customer_id_index');
```

---

## 13. Rancangan Perbaikan

Enam fase. Urutannya bukan selera — tiap fase adalah prasyarat teknis fase berikutnya.

**Aturan yang berlaku di semua fase:**
- Satu fase = satu commit = satu PR. Jangan gabung fase.
- Setiap fase punya test sendiri (`CLAUDE.md`: "Fitur/perbaikan baru wajib ada test").
- `vendor/bin/pint` sebelum commit.
- Update `docs/TASKS.md` setiap fase selesai.
- Jangan sentuh modul di luar fase yang sedang dikerjakan.

---

### Fase 0 — Pasang Detektor (½ hari)

**Kenapa duluan:** tanpa alat ukur, fase-fase berikutnya tidak bisa dibuktikan berhasil. Dan
`preventLazyLoading` akan **menemukan N+1 yang belum tercantum di §11** — daftar itu hasil telaah
manual, bukan hasil instrumentasi, jadi kemungkinan besar belum lengkap.

```php
// app/Providers/AppServiceProvider.php — boot()
use Illuminate\Database\Eloquent\Model;

Model::preventLazyLoading(! app()->isProduction());
```

Sengaja tidak diaktifkan di produksi: lazy load yang lolos ke produksi harus jadi query lambat,
bukan halaman 500.

Ekspektasi: **sebagian test langsung merah.** Itu hasil yang diinginkan — tiap kegagalan menunjuk
satu N+1 nyata. Catat daftarnya, itu jadi input tambahan untuk Fase 1.

Untuk mengukur, tambahkan penghitung query sementara (hapus sebelum merge):

```php
if (! app()->isProduction()) {
    DB::listen(fn ($q) => logger()->debug('SQL', ['sql' => $q->sql, 'ms' => $q->time]));
}
```

**Baseline yang wajib dicatat sebelum lanjut** — jumlah query & waktu render untuk: daftar
pelanggan, detail pelanggan, dashboard, dashboard FOP, papan FOP, daftar tagihan, daftar
pembayaran, task teknisi. Angka ini yang dipakai membuktikan Fase 1-5 berhasil.

---

### Fase 1 — Bereskan N+1 (1-2 hari)

Tidak menyentuh skema sama sekali, jadi risikonya paling rendah dan hasilnya paling cepat terlihat
user. Kerjakan sebelum index: N+1 yang tersisa akan mengaburkan pengukuran manfaat index nanti.

**1.1 — `clean_address` (H.1).** Dua pilihan, pilih salah satu dan konsisten:

*Pilihan A — eager load di semua pemanggil.* Paling cepat, tapi jebakannya tetap ada untuk
pemanggil berikutnya.

```php
// FopDashboardController:112
Task::with(['customer.village', 'customer.district', 'customer.city', 'pop', 'teamMembers.user'])

// FopDashboardController:142
FopTaskTeam::with([
    'members',
    'fopTasks.technicians',
    'fopTasks.customer.village',
    'fopTasks.customer.district',
    'fopTasks.customer.city',
    'fopTasks.task',
])

// TaskController:44
Task::with(['customer.village', 'customer.district', 'customer.city', 'pop', 'evidences', 'fop', 'teamMembers'])
```

*Pilihan B — buat accessor tidak butuh relasi.* Lebih baik jangka panjang: `clean_address` hanya
memerlukan **nama** desa/kecamatan/kota. `customer_addresses` sudah menyimpan `village`,
`district`, `city` sebagai string. Ubah accessor supaya memakai kolom string itu, dan relasi
hanya jadi cadangan bila string kosong. Jebakannya hilang permanen.

**Rekomendasi: A sekarang, B saat Fase 4** (Fase 4 sudah membongkar skema pelanggan, jadi B
menyatu wajar di situ).

**1.2 — `getTeknisiList()` (H.2).** Ekstrak ke satu service, ganti loop dengan dua query agregat:

```php
// app/Services/TeknisiWorkloadService.php (baru)
// Panggil SEKALI, bukan per teknisi.
$activeByUser = Task::query()
    ->join('task_teams', 'task_teams.task_id', '=', 'tasks.id')
    ->where('tasks.status', TaskStatus::IN_PROGRESS->value)
    ->whereIn('task_teams.user_id', $teknisiIds)
    ->select('task_teams.user_id', 'tasks.id', 'tasks.customer_id')
    ->get()
    ->keyBy('user_id');

$countByUser = Task::query()
    ->join('task_teams', 'task_teams.task_id', '=', 'tasks.id')
    ->whereIn('task_teams.user_id', $teknisiIds)
    ->where(fn ($q) => …)                       // logika tanggal yang sama, tanpa whereDate (lihat 2.1)
    ->groupBy('task_teams.user_id')
    ->selectRaw('task_teams.user_id, COUNT(*) as total')
    ->pluck('total', 'task_teams.user_id');
```

Dari 5 × N query jadi 3 query tetap. Lalu **hapus duplikatnya** —
`FopDashboardController:337-364` dan `CustomerVerificationController:444-489` sama-sama memanggil
service ini. Jangan tinggalkan dua salinan.

**1.3 — `isActive()` (H.3).** Salin pola yang sudah benar di `FopTaskController:153-156`:

```php
->filter(fn (FopTaskTeam $team) => $team->fopTasks->contains(
    fn (FopTask $t) => ! in_array($t->status->value, [SELESAI, DIBATALKAN], true)
))
```

Pertimbangkan menandai `FopTaskTeam::isActive()` `@deprecated` supaya tidak dipakai lagi di
konteks koleksi.

**1.4 — Batasi rentang team (H.4).** `FopDashboardController:142` tambahkan
`->whereBetween('work_date', [$today->copy()->subDays(7), $today->copy()->addDays(7)])`.
Rentangnya perlu dikonfirmasi ke pemilik produk — dashboard FOP kemungkinan hanya butuh hari ini.

**1.5 — `load()` → `loadMissing()` (H.5, H.6).** `CustomerValidationService:169` dan
`CustomerController:889`. Perubahan satu kata, tapi H.5 menghilangkan satu query per baris di
daftar pelanggan.

**1.6 — Streaming ekspor (H.7).** Pindahkan eksekusi query **ke dalam** closure:

```php
$callback = function () use ($query) {
    $file = fopen('php://output', 'w');
    fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($file, [...]);                                  // header

    foreach ($query->orderByDesc('issue_date')->orderByDesc('id')->lazy(500) as $invoice) {
        fputcsv($file, [...]);
        // flush berkala supaya buffer tidak menumpuk
    }

    fclose($file);
};
```

Berlaku sama untuk `PaymentReportController:162` dan `CustomerReportController:140`.

**1.7 — Batasi `->get()` (H.8).** `audit_logs` di halaman detail pelanggan → paginasi atau
`->limit(50)` + tombol "lihat semua". `FopTaskController:185` (`$switchTargetTasks`) → batasi ke
rentang tanggal papan. `TicketController:252` → `->limit(20)`.

**Test Fase 1:** buat `tests/Feature/DashboardFopQueryCountTest.php` yang menegaskan jumlah query
tidak melampaui ambang, memakai `DB::listen()` sebagai penghitung. Ini yang menjaga N+1 tidak
kembali diam-diam nanti — dan menamainya sesuai gejala mengikuti konvensi repo.

---

### Fase 2 — Sargability (½ hari)

**Blocker untuk Fase 3.** Selama `whereDate()` masih ada, index tanggal yang dipasang Fase 3
tidak akan pernah terpakai dan pengukurannya akan menyesatkan.

**2.1 — Ganti 15 pemanggilan `whereDate()`:**

```php
// Kolom bertipe `date` (issue_date, payment_date, registration_date, due_date, task_date)
- $query->whereDate('issue_date', '>=', $startDate);
+ $query->where('issue_date', '>=', $startDate);

// Kolom bertipe datetime/timestamp (scheduled_at, created_at) — harus rentang, bukan sama-dengan
- $query->whereDate('scheduled_at', $today);
+ $query->whereBetween('scheduled_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()]);
```

**Hati-hati pada yang kedua.** `whereDate('scheduled_at', $today)` dan
`whereBetween(startOfDay, endOfDay)` tidak selalu setara persis di batas detik/mikrodetik. Semua
test yang menyentuh penjadwalan task harus dijalankan ulang, dan kasus batas (task tepat pukul
00:00:00 dan 23:59:59) sebaiknya ditambahkan ke test.

**2.2 — `DISTINCT module`/`action` (AuditLogController:42-43)** → `Cache::remember(…, 300, …)`.

**2.3 — Rentang tanggal papan FOP (F.4)** sebelum `orderByRaw`, supaya filesort bekerja atas
puluhan baris.

---

### Fase 3 — Index (1 hari)

Baru sekarang, setelah Fase 1-2, index bisa diukur manfaatnya secara jujur.

Urutan dalam fase: **3.1** index P0 (§12) → ukur → **3.2** index P1 → ukur → **3.3** hapus 4 index
redundan `tasks` → ukur biaya tulis → **3.4** index P2.

Ukur di antara sub-fase, jangan sekaligus di akhir — kalau semuanya dipasang bersamaan, tidak akan
ketahuan index mana yang sebenarnya tidak berguna.

Karena masih development (§14), tulis index P0 langsung ke migration `create_*` asalnya, bukan
menumpuk file migration baru.

---

### Fase 4 — Bersihkan Skema (1-2 hari, destruktif)

**Wajib `migrate:fresh --seed`.** Hanya mungkin sekarang; setelah go-live pintu ini tertutup.

Urutan: **4.1** persempit tipe kolom (`customers.cid`, `audit_logs.auditable_type`, kolom status)
→ **4.2** drop kolom zombie (`customer_status`, `old_account_status`, kolom teknis duplikat) →
**4.3** satukan `phone`/`primary_phone` → **4.4** `clean_address` pilihan B (lihat 1.1).

4.1 harus mendahului 4.2 supaya index yang sudah dipasang Fase 3 langsung lahir dengan ukuran
benar tanpa perlu dibangun ulang.

Setiap drop kolom perlu grep menyeluruh dulu — `customer_status` muncul di 10 lokasi
(`CustomerController:414, 511, 512, 663, 800, 801, 2107, 2267, 3065, 3074`), semuanya harus
diarahkan ke `customer_services.service_status`.

---

### Fase 5 — Perbaikan Struktural (2-3 hari)

Yang tersisa, semuanya butuh keputusan desain — bukan perubahan mekanis:

**5.1 — Kolom `rejected_at` + `terminated_at` di `customers`** (F.3). Menghapus subquery JSON
berkorelasi di `ORDER BY`. Butuh: migration + pengisian di `CustomerWorkflowService` + backfill
dari `audit_logs` yang ada + ubah `CustomerController:139-158`.

**5.2 — Generated column `notification_type`** (F.3). Sekalian perbaiki tipe kolom
`notifications.data` dari `text` ke `json`.

**5.3 — Pencarian pelanggan** (F.2). Pisah berdasarkan bentuk input (prefix-`LIKE` untuk kode/HP,
FULLTEXT untuk nama). Perlu keputusan produk: apakah pencarian substring di tengah nomor HP
memang dibutuhkan?

**5.4 — Endpoint pencarian wilayah** (H.9) `?q=` + limit, ganti 8 pemanggilan `->get()`.

**5.5 — `Pop::forUser()` pindah ke `EffectiveAccessService`** (H.10). **Ini bukan sekadar
performa — ini berpotensi bug scope.** `CLAUDE.md` menyatakan `user_pops` tidak paham `pop_tree`,
artinya user ber-scope `pop_tree` bisa jadi melihat daftar POP yang salah di dropdown. Perlu
dikonfirmasi ke pemilik produk sebelum diubah, dan butuh test tersendiri.

**5.6 — `->select()` di daftar pelanggan** (G). Kerjakan paling akhir: sebelum Fase 4 selesai,
belum jelas kolom mana yang akan tersisa.

---

### Ringkasan Beban

| Fase | Isi | Perkiraan | Risiko | Butuh `migrate:fresh` |
|---|---|---|---|---|
| 0 | Detektor + baseline | ½ hari | Nihil | Tidak |
| 1 | N+1 | 1-2 hari | Rendah | Tidak |
| 2 | Sargability | ½ hari | **Sedang** (batas tanggal) | Tidak |
| 3 | Index | 1 hari | Rendah | Ya (kalau tulis ke migration asal) |
| 4 | Bersihkan skema | 1-2 hari | **Tinggi** (destruktif) | Ya |
| 5 | Struktural | 2-3 hari | Sedang | Ya (5.1, 5.2) |

Total ±7-9 hari kerja. Fase 0-2 saja (2-3 hari) sudah memberi perbaikan yang langsung terasa user
tanpa menyentuh skema sama sekali — kalau waktu terbatas, kerjakan itu dulu dan hentikan di situ.

**Fase yang butuh konfirmasi sebelum dikerjakan:** 1.4 (rentang tanggal dashboard FOP), 4.2
(kolom mana yang benar-benar boleh dibuang), 5.3 (perilaku pencarian), 5.5 (perubahan scope POP).

---

## 14. Karena Masih Tahap Development

Status development berarti kehilangan data dapat diterima — ini membuka opsi yang tertutup di
produksi. Manfaatkan sekarang, karena setelah go-live tidak akan bisa lagi.

Ini juga bukan jalur baru: isi DB saat ini 100% hasil migrasi legacy, dan alur perbaikan yang
sudah berlaku memang "perbaiki migration → import ulang" (lihat `docs/RUNBOOK_COMMANDS.md`).
Jadi Fase 4 tidak menambah beban operasional apa pun di luar yang sudah rutin dikerjakan.

**Yang jadi mungkin sekarang:**

1. **Bersihkan kolom zombie lewat migration destruktif.** Drop `customers.customer_status`,
   `customers.old_account_status`, dan kolom teknis yang duplikat dengan
   `customer_technical_details`. Satukan `phone`/`primary_phone` jadi satu kolom. Di produksi ini
   butuh backfill berhari-hari; sekarang cukup `migrate:fresh --seed`.

2. **Persempit tipe kolom sebelum mengindeks.** `ALTER TABLE … MODIFY` di tabel berisi data adalah
   operasi mahal dan berisiko truncation. Sekarang tabel kosong — gratis.
   Kerjakan **sebelum** memasang index P0/P1 supaya index langsung lahir dengan ukuran benar.

3. **Perbaiki migration di tempat asalnya, bukan menumpuk migration tambal.** Repo sudah punya
   112 migration dan sebagian saling menambal (`enlarge_cid`, `alter_cid`,
   `update_distributions_unique_index`, `scope_customer_code_unique_to_pop`). Karena data boleh
   hilang, index P0 lebih baik **dituliskan langsung ke migration `create_*` yang bersangkutan**
   daripada jadi file baru. Hasilnya skema yang bisa dibaca satu kali jalan.
   *Catatan:* ini mengubah file yang sudah tercatat di tabel `migrations`, jadi wajib
   `migrate:fresh` — jangan setengah-setengah.

4. **Tinjau ulang keputusan unique index invoice.** Migration `2026_07_21_164556` memutuskan tidak
   memasang unique `(customer_id, invoice_type, billing_period)` karena invoice `batal` memblokir
   penggantinya. Alternatif yang layak dicoba sekarang mumpung data boleh dibuang: kolom generated
   `period_slot` yang bernilai `NULL` saat status `batal` (MySQL mengabaikan `NULL` di unique index
   — ini cara meniru partial index):

   ```sql
   ALTER TABLE invoices
     ADD COLUMN period_slot VARCHAR(120)
       GENERATED ALWAYS AS (
         CASE WHEN invoice_status = 'batal' THEN NULL
              ELSE CONCAT(customer_id,'|',invoice_type,'|',billing_period) END
       ) STORED,
     ADD UNIQUE INDEX invoices_period_slot_unique (period_slot);
   ```

   Ini akan menegakkan aturan "satu tagihan langganan per periode" **di level database**, sekaligus
   melayani lookup guard, sekaligus tetap membolehkan invoice pengganti setelah pembatalan.
   **Prasyarat:** `SatuTagihanLanggananPerPeriodeTest` dan `AuditTagihanDobelTest` harus tetap
   hijau — dua test itulah yang menggagalkan percobaan sebelumnya. Kalau gagal lagi, batalkan dan
   pertahankan index non-unique dari §12.
   Kaitannya: `[[project_aturan_tagihan_awal_bulanan]]`, `[[project_legacy_billing_migration_defects]]`.

5. **Aktifkan `Model::preventLazyLoading()` di non-produksi.** Saat ini tidak ada di
   `AppServiceProvider` sama sekali, artinya N+1 lolos tanpa terdeteksi — persis kondisi yang
   melahirkan `FopDashboardController:337-364`. Tambahkan di `boot()`:

   ```php
   Model::preventLazyLoading(! app()->isProduction());
   ```

   Sebagian test kemungkinan langsung gagal. Itu **fitur, bukan bug** — tiap kegagalan menandai
   satu N+1 nyata.

---

## 15. Cara Verifikasi

Analisa ini struktural, belum terukur. Sebelum menyatakan selesai:

1. **Siapkan volume realistis.** `CustomerSeeder` yang ada terlalu kecil. Butuh factory yang
   menghasilkan ±20.000 pelanggan, ±240.000 invoice (12 periode), ±200.000 pembayaran,
   ±100.000 baris `audit_logs`. Tanpa volume, `EXPLAIN` akan bilang "full scan" untuk semuanya
   karena optimizer memang memilih full scan di tabel kecil.

2. **Ukur sebelum & sesudah.** Untuk tiap query di bagian 4-7:
   ```sql
   EXPLAIN ANALYZE <query>;
   ```
   Yang dicari: `type=ref/range` (bukan `ALL`), `rows` turun drastis, dan **hilangnya**
   `Using filesort` / `Using temporary` di query yang seharusnya terlayani index.

3. **Hitung query per halaman.** Pasang `DB::listen()` sementara di `AppServiceProvider` atau pakai
   Pail, lalu buka: daftar pelanggan, detail pelanggan, dashboard, dashboard FOP, papan FOP,
   laporan tagihan. Catat jumlah query + total waktu, sebelum dan sesudah.

4. **Ukur sisi tulis juga.** Jalankan `billing:generate-monthly-invoices` di atas 20.000 pelanggan
   dan catat durasinya sebelum & sesudah `invoices_customer_period_type_idx`. Ini metrik tunggal
   yang paling menunjukkan nilai keseluruhan pekerjaan ini.

5. **Verifikasi index benar-benar terpakai, bukan sekadar ada.**
   ```sql
   SELECT * FROM sys.schema_unused_indexes WHERE object_schema = 'whusnet_operasional';
   ```
   Index yang muncul di sini setelah pengujian menyeluruh = kandidat hapus. Ini juga cara
   memastikan asumsi "4 index `tasks` redundan" benar.

6. **Regression test wajib hijau**, khususnya yang menyentuh guard tagihan dobel:
   `SatuTagihanLanggananPerPeriodeTest`, `AuditTagihanDobelTest`,
   `AktivasiTertagihDobelKarenaActivationDateStaleTest`,
   `InitialInvoiceProrateIgnoresClientAmountTest`.

---

## 16. Checklist Eksekusi

### Fase 0 — Detektor
- [ ] `Model::preventLazyLoading(! app()->isProduction())` di `AppServiceProvider`
- [ ] Catat daftar test yang jadi merah — itu N+1 tambahan di luar §11
- [ ] Catat baseline: jumlah query + waktu render 8 halaman kunci

### Fase 1 — N+1
- [ ] **1.1** `clean_address` — eager load di `FopDashboardController` (3 lokasi) + `TaskController:44`
- [ ] **1.2** `getTeknisiList()` → `TeknisiWorkloadService`, hapus duplikat di 2 controller
- [ ] **1.3** `isActive()` → filter koleksi in-memory (`FopDashboardController:148`)
- [ ] **1.4** Batasi rentang `work_date` team di dashboard FOP ⚠️ *butuh konfirmasi*
- [ ] **1.5** `load()` → `loadMissing()` di `CustomerValidationService:169` + `CustomerController:889`
- [ ] **1.6** Ekspor 3 laporan pakai `->lazy()` di dalam closure stream
- [ ] **1.7** Batasi `->get()` tanpa batas (5 lokasi, §H.8)
- [ ] **Test** `DashboardFopQueryCountTest` — ambang jumlah query

### Fase 2 — Sargability
- [ ] **2.1** Ganti 15 `whereDate()` ⚠️ *cek kasus batas 00:00:00 / 23:59:59*
- [ ] **2.2** Cache `DISTINCT module` / `DISTINCT action`
- [ ] **2.3** Batasi rentang tanggal papan FOP sebelum `orderByRaw`

### Fase 3 — Index
- [ ] **3.1** P0: `invoices_customer_period_type_idx` + 3 index `audit_logs` + 11 index legacy → ukur
- [ ] **3.2** P1: composite `customers` / `invoices` / `payments` → ukur
- [ ] **3.3** Drop 4 index redundan `tasks` → ukur biaya tulis
- [ ] **3.4** P2: index operasional → ukur

### Fase 4 — Skema (destruktif, `migrate:fresh`)
- [ ] **4.1** Persempit `customers.cid`, `audit_logs.auditable_type`, kolom status
- [ ] **4.2** Drop kolom zombie ⚠️ *butuh konfirmasi kolom mana*
- [ ] **4.3** Satukan `phone` / `primary_phone`
- [ ] **4.4** `clean_address` pilihan B — accessor lepas dari relasi

### Fase 5 — Struktural
- [ ] **5.1** Kolom `rejected_at` / `terminated_at` + backfill
- [ ] **5.2** Generated column `notification_type`, `data` → tipe `json`
- [ ] **5.3** Pencarian pelanggan: prefix-`LIKE` + FULLTEXT ⚠️ *butuh konfirmasi*
- [ ] **5.4** Endpoint pencarian wilayah `?q=` + limit
- [ ] **5.5** `Pop::forUser()` → `EffectiveAccessService` ⚠️ *potensi bug scope, butuh konfirmasi*
- [ ] **5.6** `->select()` di daftar pelanggan

### Penutup
- [ ] Seeder volume + `EXPLAIN ANALYZE` sebelum/sesudah
- [ ] `sys.schema_unused_indexes` bersih dari index yang baru dipasang
- [ ] `php artisan test` full suite hijau
- [ ] `vendor/bin/pint`
- [ ] Update `docs/TASKS.md` + `docs/DATABASE_RULES.md`

---

## Rujukan

**Sumber beban — index:**
- `app/Traits/HasPopScope.php:24` — sumber `whereIn(pop_id)` di seluruh sistem
- `app/Observers/InvoiceObserver.php:48,96` — dua query guard per insert
- `app/Services/EffectiveAccessService.php` — cache Redis TTL 1 jam, bukan hot path DB

**Sumber beban — N+1:**
- `app/Models/Customer.php:373` — accessor `clean_address`, 3 lazy load per pemanggilan
- `app/Models/FopTaskTeam.php:45,53` — `isActive()` & `workloadSummary()`, query per team
- `app/Http/Controllers/FopDashboardController.php:132,188,337,375` — titik terpadat
- `app/Http/Controllers/CustomerVerificationController.php:444-489` — duplikat `getTeknisiList()`
- `app/Services/CustomerValidationService.php:87` vs `:169` — pola benar & pola salah di satu file

**Contoh yang sudah benar (jadikan acuan):**
- `app/Http/Controllers/InvoiceController.php:120-137` — eager load lengkap sebelum `append()`
- `app/Http/Controllers/FopTaskController.php:150-156` — filter koleksi in-memory + `limit(50)`
- `app/Http/Controllers/CustomerController.php:855-876` — eager load bertingkat 17 relasi

**Migration yang menjelaskan keputusan sebelumnya:**
- `2026_06_15_000002_add_legacy_ids_to_billing_and_packages_tables.php` — 11 kolom tanpa index
- `2026_07_04_091731_add_duplicate_guard_indexes_to_invoices_and_payments.php` — kenapa guard invoice di app layer
- `2026_07_21_164556_add_invoice_period_unique_index_to_invoices.php` — kenapa unique polos ditolak
- `2026_07_01_160949_add_composite_indexes_to_tasks_tables.php` — asal 4 index redundan

**Dokumen terkait:** `docs/DATABASE_RULES.md`, `docs/database-schema.md`, `docs/RUNBOOK_COMMANDS.md`
