# Rancangan: Tanggal Request Pemasangan & Pencatatan Material Task

Status: **SUDAH DIIMPLEMENTASI — 2026-07-31 (ADHOC-11).** Keputusan Bagian 4 dijawab user 2026-07-30.
Dibuat: 2026-07-30

Dokumen ini dipertahankan sebagai catatan keputusan & alasannya. Untuk keadaan sistem
saat ini, baca [docs/customer-lifecycle/business-logic.md](../customer-lifecycle/business-logic.md)
(§4 Survey, §6 Pemasangan), [docs/customer-lifecycle/database-schema.md](../customer-lifecycle/database-schema.md),
[docs/fop-task/flowchart.md](../fop-task/flowchart.md) §2–3 & §8, dan
[docs/master/item/README.md](../master/item/README.md).

**Yang berbeda dari rancangan saat dikerjakan:**

1. Enum `MaterialType`/`MaterialKind` + tabel `items` dibangun sekaligus (keputusan #4), jadi `item_id` langsung FK ke master — bukan nullable-menunggu-Inventory seperti draf awal. Tetap nullable **hanya** untuk barang "lainnya".
2. Guard "wajib ≥1 baris material saat `completed`" ditaruh di blok validasi kondisional `store()` (bukan di rules), supaya baris qty 0 / nama kosong ikut terhitung tidak valid — aturan yang sama dipakai `TaskMaterialService` saat menyimpan.
3. Halaman baca-saja yang menampilkan data survey/pemasangan ikut diperbarui (tab Survey & Pemasangan, `tasks/show`, `fop_tasks/history_detail`) — tidak masuk daftar file di Bagian 3 rancangan, ketahuan saat audit pasca-implementasi.

Dua kebutuhan yang dianalisa:

1. **Laporan Survey** — pelanggan bisa minta tanggal pemasangan tertentu. Task PSB
   yang tanggalnya masih jauh harus tenggelam ke bawah papan FOP dan **tidak** kena
   SLA/prioritas, karena "telat" belum punya arti sampai tanggal yang diminta tiba.
2. **Laporan Pemasangan** — teknisi mencatat **perangkat pasif yang benar-benar
   dipakai**, terpisah dari **estimasi alat** di survey. Struktur datanya harus sudah
   final sekarang supaya modul Inventory nanti tidak membongkar ulang.

---

## Bagian 0 — Kondisi Saat Ini (hasil audit)

### 0.1 Yang sudah ada dan bisa dipakai

`fop_tasks.client_request_date` **sudah ada** (`2026_06_30_000001_create_fop_tasks_table.php`),
sudah di-cast `date` di `FopTask.php:42`, dan **sorting yang dibutuhkan sudah jalan**:

```php
// FopTaskController.php:63 — orderBy pertama, sebelum priority
->orderByRaw('CASE WHEN client_request_date IS NOT NULL AND client_request_date >= ? THEN 1 ELSE 0 END',
    [now()->addDay()->toDateString()])
```

Request ≥ besok masuk grup 1 (bawah), hari ini/lewat masuk grup 0 (atas). Persis
perilaku yang diminta: request 20 Agustus 2026 sementara hari ini 30 Juli 2026 →
paling bawah, karena papan memprioritaskan pekerjaan hari ini.

Ada test yang menjaga perilaku ini: `tests/Feature/FopTaskSortingTest.php`.

### 0.2 Yang menghalangi

| # | Masalah | Lokasi |
|---|---|---|
| 1 | `client_request_date` hanya bisa diisi lewat status **Pending** manual di `/fop-tasks` | `FopTaskController.php:222, 248, 325, 426` |
| 2 | `client_request_date` **di-null-kan** begitu status pindah ke draft/terjadwal/in_progress/selesai | `FopTaskController.php:438-441` |
| 3 | Tidak ada jalur dari Laporan Survey ke field itu | — |
| 4 | Prioritas PSB dihitung dari `survey.completed_at + SLA`, jadi task request jauh akan naik URGENT dalam beberapa hari | `FopTaskController.php:1219-1245` |
| 5 | `customer_surveys` tidak punya kolom tanggal request | `2026_06_13_104704_create_customer_surveys_table.php` |

### 0.3 Kondisi pencatatan alat

| Konsep | Tempat sekarang | Bentuk | Cukup? |
|---|---|---|---|
| Estimasi alat (survey) | `customer_surveys.required_tools` | textarea bebas | tidak — tak bisa diagregasi |
| Estimasi kabel | `customer_surveys.cable_estimation_meter` | integer tunggal | sebagian |
| **Alat terpakai saat pemasangan** | **tidak ada** | — | **tidak ada sama sekali** |
| Perangkat pasif terpasang | `customer_technical_details.passive_device{,_type,_qty,_note}` | 4 kolom flat, 1 baris | tidak — konsep beda (aset, bukan konsumsi) |

`installations/report.blade.php` Step 5 (baris 344-491) hanya mencatat perangkat
**aktif**: `device_type`, brand/model, `serial_number`, MAC, PPPoE, ODP/OLT/VLAN,
`initial_attenuation`, foto. Tidak ada satupun field material pasif.

`docs/post-mvp/inventory-fop.md` merencanakan tabel `fop_task_materials`, tapi statusnya
post-MVP dan belum ada tabel/model inventory apapun di `app/Models`.

### 0.4 Temuan sampingan (di luar scope, dicatat saja)

`difficulty_level` di form survey **tidak punya kolom database**. Nilainya dilebur
jadi prefix string ke `survey_note` di `CustomerSurveyController.php:265-271`
(`"Tingkat Kesulitan: SULIT\nCatatan: ..."`). Akibatnya tingkat kesulitan tidak bisa
difilter/diagregasi. Bukan bagian rancangan ini — dicatat sebagai kandidat perbaikan
terpisah.

---

## Bagian 1 — Tanggal Request Pemasangan

### 1.1 Prinsip

**Survey adalah satu-satunya sumber kebenaran.** `fop_tasks.client_request_date` untuk
kategori PEMASANGAN diperlakukan sebagai **nilai turunan** yang di-refresh tiap
auto-sync — bukan disalin sekali lalu hidup sendiri. Ini menghindari dua nilai yang
bisa menyimpang, pola yang sudah bikin masalah di sinkronisasi Ticket↔FopTask.

### 1.2 Perubahan database

Migration baru: `add_requested_installation_date_to_customer_surveys_table`

```php
$table->date('requested_installation_date')->nullable()->after('nearest_odp');
```

Nullable, karena mayoritas pelanggan tidak minta tanggal spesifik. Kosong = "secepatnya",
dan task jatuh ke perilaku SLA normal seperti sekarang.

**Tidak ada perubahan skema di `fop_tasks`** — kolomnya sudah ada.

### 1.3 Perubahan form survey

`resources/views/surveys/report.blade.php` Step 4, field baru setelah ODP Terdekat:

- Label: **TANGGAL REQUEST PEMASANGAN** (opsional)
- `<input type="date" name="requested_installation_date" min="{{ today }}">`
- Teks bantu: *"Kosongkan jika pelanggan tidak meminta tanggal tertentu. Diisi hanya
  jika pelanggan minta dipasang di tanggal tertentu."*
- **Tidak** ditambahkan ke `formFields.laporan.required` di JS — opsional, tak boleh
  menahan progress bar.

`CustomerSurveyController::store()` (baris 222) tambah rule:

```php
'requested_installation_date' => 'nullable|date|after_or_equal:today',
```

### 1.4 Turunkan ke FopTask PSB

`FopTaskController::autoSyncAndCalculatePriority()`, blok Auto-Sync Installation
(baris 1163-1185):

- Eager-load `latestSurvey` pada `$installCustomers`.
- Saat `FopTask::create(...)` untuk kategori PEMASANGAN, isi
  `'client_request_date' => $c->latestSurvey?->requested_installation_date`.

Lalu tambah langkah refresh (task PSB yang sudah terlanjur dibuat / yang
`client_request_date`-nya di-null-kan oleh transisi status):

```php
// client_request_date pada task PEMASANGAN adalah nilai TURUNAN dari
// customer_surveys.requested_installation_date — bukan data milik FopTask.
// Di-refresh tiap sync karena FopTaskController::update() sengaja me-null-kan
// field ini saat status keluar dari Pending (baris 438-441). Kalau tidak
// di-refresh, tanggal request pelanggan hilang begitu FOP menjadwalkan task,
// dan urutan papan langsung salah.
```

**Baris 438-441 tidak diubah.** Field itu tetap milik alur Pending untuk kategori
non-PSB; untuk PSB nilainya dipulihkan dari survey.

### 1.5 Bebaskan dari SLA & prioritas

`FopTaskController.php` blok Dynamic Priority Update (baris 1201-1246), sisipkan
sebelum perhitungan `$deadline`:

```php
// Task yang tanggal request client-nya masih di masa depan TIDAK dihitung SLA-nya.
// Klien sendiri yang meminta tanggal itu, jadi "telat" belum punya arti sampai
// harinya tiba. Tanpa guard ini, PSB request 3 minggu ke depan naik jadi URGENT
// dalam beberapa hari dan menutupi task yang benar-benar harus dikerjakan hari ini.
if ($task->client_request_date?->isAfter($now->copy()->startOfDay())) {
    if ($task->priority !== FopTaskPriority::LOW) {
        $task->update(['priority' => FopTaskPriority::LOW]);
    }
    continue;
}
```

Dan ubah titik referensi SLA PSB (baris 1224):

```php
// Urutan referensi: tanggal request client → survey selesai → updated_at.
// client_request_date didahulukan karena kalau SLA tetap dihitung dari
// survey.completed_at, task request jauh akan lahir dalam kondisi sudah
// overdue tepat di hari-H — teknisi dihukum untuk penundaan yang diminta pelanggan.
$refDate = $task->client_request_date
    ? Carbon::parse($task->client_request_date)
    : ($surveyTask?->completed_at
        ? Carbon::parse($surveyTask->completed_at)
        : Carbon::parse($customer->updated_at));
```

### 1.6 Timer lewat tanggal (jawaban keputusan #3)

Begitu tanggal request terlewat, task menampilkan timer berjalan negatif seperti SLA:
`−18:25:02`.

**Komponen sudah ada, tidak perlu bikin baru.** `resources/views/components/countdown-timer.blade.php`
sudah menangani state TERLAMBAT: countdown terus berjalan ke negatif, format `−HH:MM:SS`,
badge merah berkedip (`countdown-red` + `animate-pulse`). Papan FOP sudah memakainya di
`fop_tasks/index.blade.php:290-293`. Yang berubah cuma **deadline apa yang disuapkan** ke
komponen itu.

**Deadline untuk PSB ber-`client_request_date`:**

```php
// Deadline = AKHIR HARI tanggal request, bukan tanggal request + handlingSlaHours().
// Alasannya: yang dijanjikan ke pelanggan adalah "dipasang tanggal 20", jadi lewat
// tengah malam tanggal 20 = sudah telat, titik. Kalau dipakai +SLA jam, task baru
// merah 2-3 hari setelah tanggal janji — timer-nya bohong terhadap janji ke pelanggan.
Carbon::parse($this->client_request_date)->endOfDay()
```

`slaTotalSeconds()` untuk kasus ini = `86400` (satu hari kerja penuh), supaya ambang
warna hijau/kuning/merah komponen tetap masuk akal di hari-H.

**WAJIB diubah di dua tempat sekaligus:**

| Tempat | Dipakai untuk | Baris |
|---|---|---|
| `FopTask::slaDeadline()` | angka yang tampil di timer | `app/Models/FopTask.php:189-205` |
| `FopTaskController::autoSyncAndCalculatePriority()` | warna badge prioritas | `FopTaskController.php:1219-1227` |

Kalau hanya salah satu diubah, timer dan prioritas menunjukkan dua kebenaran berbeda
untuk task yang sama — bug yang persis sama pernah terjadi di blok ini (lihat komentar
panjang soal enum-vs-string di `FopTask.php:190-195`). Idealnya dua-duanya memanggil
satu helper `FopTask::slaReferenceDate()` agar tidak ada kesempatan menyimpang.

**Sebelum hari-H**: jangan tampilkan countdown hijau berdurasi 3 minggu — itu mengesankan
"masih santai banget" padahal artinya "belum waktunya". Ganti dengan badge netral
*"Dijadwalkan 20 Agu 2026"*. Countdown baru muncul saat `client_request_date` = hari ini,
lalu otomatis jadi `−HH:MM:SS` merah setelah lewat tengah malam.

### 1.7 UI papan FOP

`resources/views/fop_tasks/index.blade.php`:

| Kondisi | Tampilan kolom SLA |
|---|---|
| `client_request_date` di masa depan | badge netral *"Dijadwalkan 20 Agu 2026"* (slate/biru), prioritas LOW |
| `client_request_date` = hari ini | `<x-countdown-timer>` normal menuju `endOfDay` |
| `client_request_date` sudah lewat | `<x-countdown-timer>` state TERLAMBAT — `−18:25:02` merah berkedip |
| `client_request_date` kosong | perilaku sekarang, tidak berubah |

### 1.8 Test

| Test | Isi |
|---|---|
| `SurveyRequestedInstallationDateTest` | field tersimpan; validasi tolak tanggal lampau; kosong tetap lolos |
| `FopTaskClientRequestDatePropagationTest` | PSB auto-sync mengambil tanggal dari survey; tanggal dipulihkan setelah status keluar dari Pending |
| `FopTaskFutureRequestNoSlaTest` | request masa depan → priority tetap LOW meski SLA survey sudah lewat; di hari-H prioritas mulai naik |
| `FopTaskRequestDateDeadlineTest` | `slaDeadline()` = `endOfDay(client_request_date)`; H+1 menghasilkan sisa detik negatif; `slaDeadline()` dan referensi prioritas di controller memberi tanggal yang sama |
| `FopTaskSortingTest` (sudah ada) | pastikan tidak regresi |

---

## Bagian 2 — Material Task (Estimasi vs Terpakai)

### 2.1 Definisi yang disepakati

| Istilah | Arti | Diisi oleh | Kapan |
|---|---|---|---|
| **Estimasi Kebutuhan Alat** | perkiraan alat yang **akan** dipakai | teknisi survey | Laporan Survey |
| **Perangkat Pasif** | alat yang **benar-benar dipakai** saat pemasangan | teknisi pemasangan | Laporan Pemasangan |

Dua-duanya adalah **daftar item**, bukan teks bebas. Selisih keduanya adalah nilai
bisnis utamanya, dan nanti jadi input langsung modul Inventory.

### 2.2 Kenapa tidak pakai struktur yang ada

- `customer_technical_details.passive_device*` — 4 kolom flat, hanya menampung **satu**
  item. Tidak bisa "3 splitter + 120 m dropcore + 2 patch cord". Selain itu konsepnya
  beda: itu **aset yang terpasang permanen di sisi pelanggan**, bukan **konsumsi
  material saat pekerjaan**. Tetap dibiarkan, tidak digabung.
- `customer_surveys.required_tools` — textarea. Tidak bisa dijumlah, tidak bisa
  dibandingkan dengan realisasi, tidak bisa disambung ke stok.

Kolom tidak cukup, jadi tabel baru **memang dibutuhkan** (bukan pelanggaran aturan
"tabel baru kalau kolom cukup" di CLAUDE.md).

### 2.3 Skema

Dua migration. Master item dibangun sekarang (keputusan #4) — bukan nama bebas.

**Alasan master dibangun duluan:** dengan `item_name` teks bebas, data enam bulan ke
depan akan berisi `"Dropcore 1 core"`, `"dropcore 1core"`, `"DC 1C"`, `"kabel dropcore"`
untuk barang yang sama. Modul Inventory kemudian harus melakukan pekerjaan pembersihan
manual yang tak bisa diotomasi — persis kerja ekstra yang ingin dihindari. Master
minimum sekarang membuat penamaan seragam sejak baris pertama.

**Migration A — `create_items_table`**

```php
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->string('code', 30)->unique();   // mis. DC-1C, SPL-1x8
    $table->string('name', 150);
    $table->string('type', 50);             // MaterialType
    $table->string('unit', 20);             // meter | pcs | roll | set
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    // SENGAJA TIDAK ADA di sini: stok, harga, lokasi gudang, minimum stock.
    // Itu wilayah modul Inventory. Tabel ini cuma menjawab "barang apa saja yang
    // boleh dicatat" supaya penamaan seragam sejak sekarang. Inventory nanti
    // menambah kolom/tabel di atasnya, bukan mengganti yang ini.
    $table->index(['type', 'is_active']);
});
```

Master data ini dikelola di **Master Data** (`/master/...`) mengikuti pola master lain
di repo (POP, Paket Internet, Kategori Masalah Tiket): CRUD + seeder isi awal +
permission `items.view|create|update|delete` hasil generate `features` × `actions`,
**bukan** hardcode.

**Migration B — `create_task_materials_table`**

```php
Schema::create('task_materials', function (Blueprint $table) {
    $table->id();

    // Anchor ke FopTask, bukan ke customer_installations: FopTask satu-satunya
    // entitas yang dimiliki SEMUA jenis pekerjaan (SRV, PSB, MTN, C-REQ, O-REQ).
    // Inventory nanti butuh "pemakaian material per task", bukan per instalasi saja.
    $table->foreignId('fop_task_id')->constrained()->cascadeOnDelete();
    $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

    // estimasi = dari Laporan Survey; terpakai = dari Laporan Pemasangan.
    // Satu tabel untuk keduanya, bukan dua tabel kembar: perbandingan
    // estimasi-vs-realisasi jadi self-join sederhana, dan tidak ada risiko
    // dua skema mirip yang menyimpang seiring waktu.
    $table->string('kind', 20); // estimasi | terpakai

    // Nullable HANYA untuk kasus "lainnya" — barang yang belum terdaftar di master
    // dan tak boleh menghalangi teknisi menyelesaikan laporan di lapangan. Baris
    // seperti ini muncul di daftar "perlu dirapikan" untuk admin master data.
    // Kalau di-required-kan sekarang, teknisi akan memaksakan item yang salah demi
    // bisa submit — datanya jadi lebih kotor daripada dibiarkan null.
    $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();

    // Snapshot tipe & nama. Redundan terhadap items — dan memang disengaja:
    // laporan yang sudah disimpan tidak boleh berubah isinya kalau master
    // di-rename atau di-nonaktifkan belakangan.
    $table->string('item_type', 50);   // splitter_odp, kabel_dropcore, patch_cord, ...
    $table->string('item_name', 150);  // snapshot nama/spesifikasi
    $table->decimal('qty', 10, 2);     // decimal, bukan integer: kabel dihitung meter
    $table->string('unit', 20);        // meter | pcs | roll | set

    // Snapshot harga saat dipakai. Kosong sampai Inventory ada. Snapshot, bukan
    // join ke master, supaya histori biaya tidak berubah waktu harga item naik.
    $table->decimal('unit_price_snapshot', 12, 2)->nullable();

    $table->string('note', 255)->nullable();
    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['fop_task_id', 'kind']);
});
```

**Kenapa bentuk ini tahan terhadap kedatangan Inventory:**

| Kebutuhan Inventory nanti | Sudah disediakan |
|---|---|
| Master item + penamaan seragam | tabel `items` |
| Relasi ke master item | `item_id` FK |
| Histori biaya per task | `unit_price_snapshot` |
| Satuan campur (meter/pcs) | `unit` + `qty` decimal |
| Agregasi biaya per kategori task | `fop_task_id` → `fop_tasks.category` |
| Audit pemakaian per teknisi | `recorded_by` |
| Tabel `fop_task_materials` di `docs/post-mvp/inventory-fop.md` | tabel ini **adalah** tabel itu, dibangun lebih awal |

Yang tersisa untuk modul Inventory: kolom stok/harga/minimum di `items`, tabel
pergerakan stok, dan dashboard biaya. **Tidak ada perubahan bentuk pada
`task_materials` maupun UI-nya** — cuma penambahan di atasnya.

### 2.4 Enum tipe item

`App\Enums\MaterialType` — nilai awal mengikuti enum yang sudah dipakai
`CustomerDeviceController.php:43` supaya tidak ada dua daftar tipe yang berbeda:

`splitter_odp`, `kabel_dropcore`, `patch_cord`, `media_converter`, `antena_radio`,
`aksesoris_pasang`, `lainnya`

Tiap case punya `defaultUnit()` (mis. `kabel_dropcore` → `meter`, sisanya → `pcs`)
supaya form bisa auto-isi satuan.

`App\Enums\MaterialKind`: `estimasi`, `terpakai`.

### 2.5 Perubahan UI

**Laporan Survey** (`surveys/report.blade.php` Step 4) — seksi baru
**ESTIMASI KEBUTUHAN ALAT**: baris repeatable (Alpine `x-data`), tiap baris =
tipe (select) + nama/spesifikasi + qty + satuan (auto dari tipe) + catatan.

- `cable_estimation_meter` yang sudah ada tetap dipertahankan, dan otomatis
  menghasilkan satu baris `kabel_dropcore` (qty = nilai itu, unit = meter) —
  jangan minta teknisi mengisi angka yang sama dua kali.
- `required_tools` **tidak di-drop** (ada data survey lama). Turun perannya jadi
  catatan alat khusus non-material: tangga panjang, bor beton, dsb. Label diubah
  jadi "ALAT KHUSUS / KENDALA PERALATAN" agar tidak rancu dengan material.

**Laporan Pemasangan** (`installations/report.blade.php` Step 5) — seksi baru
**PERANGKAT PASIF TERPAKAI**, di bawah "Informasi Distribusi Jaringan":

- Baris repeatable dengan komponen yang sama persis (Blade component dipakai ulang
  di dua halaman — jangan duplikasi markup).
- **Prefill dari baris `estimasi`** milik task tersebut. Teknisi mengubah qty ke
  realita, boleh tambah/hapus baris. Prefill penting: tanpa itu teknisi cenderung
  mengosongkan seksi ini.
- Wajib minimal 1 baris jika `installation_status = completed`. Kalau `failed/revisi`,
  tidak wajib (mengikuti pola `updateFieldRequirements()` yang sudah ada di
  `installations/report.blade.php:654-697`).

**Verifikasi admin** (`verifications/admin.blade.php`) — tabel perbandingan
estimasi vs terpakai per item + selisih. Di sinilah nilai bisnisnya terlihat sebelum
Inventory ada.

### 2.6 Backend

- Model `TaskMaterial` + relasi `FopTask::materials()`, scope `estimasi()` / `terpakai()`.
- Penulisan lewat service (bukan controller): `TaskMaterialService::sync(FopTask, kind, array $rows)`
  — hapus-dan-tulis-ulang dalam satu transaksi, konsisten dengan pola service repo ini.
- `CustomerSurveyController::store()` → `sync(..., MaterialKind::ESTIMASI, ...)`
- `CustomerInstallationController::store()` → `sync(..., MaterialKind::TERPAKAI, ...)`
- Autofill opsional: baris `terpakai` bertipe splitter/dropcore boleh mengisi
  `customer_technical_details.passive_device*` sebagai ringkasan aset. **Opsional,
  bukan penggabungan** — dua tabel tetap punya makna masing-masing.

### 2.7 RBAC & scope

- Data material ikut task → ikut POP scope task. Tidak ada query material tanpa
  join ke `fop_tasks.pop_id`.
- Belum perlu permission halaman baru (belum ada halaman berdiri sendiri). Kalau
  nanti ada laporan material tersendiri, feature code baru: `materials.*` — **bukan**
  menumpang `fop_tasks.view`.

### 2.8 Test

| Test | Isi |
|---|---|
| `SurveyMaterialEstimateTest` | baris estimasi tersimpan; `cable_estimation_meter` menghasilkan baris dropcore; sync mengganti bukan menggandakan |
| `InstallationMaterialUsageTest` | baris terpakai tersimpan; prefill dari estimasi; wajib ≥1 baris saat `completed`; tidak wajib saat `failed` |
| `TaskMaterialScopeTest` | user POP lain tidak bisa membaca material task luar scope |
| `TaskMaterialVarianceTest` | perhitungan selisih estimasi vs terpakai |

---

## Bagian 3 — Urutan Pengerjaan

| Tahap | Isi | Perkiraan sentuhan file | Risiko |
|---|---|---|---|
| **1** | Tanggal request pemasangan (Bagian 1 penuh) | 1 migration, `surveys/report.blade.php`, `CustomerSurveyController`, `FopTaskController`, `fop_tasks/index.blade.php` | rendah — sorting sudah ada, tinggal mengisi datanya |
| **2** | Tabel + enum + service + UI estimasi di survey | 1 migration, 2 enum, 1 model, 1 service, 1 Blade component, `surveys/report.blade.php`, `CustomerSurveyController` | sedang — tabel baru |
| **3** | UI terpakai di pemasangan + prefill | `installations/report.blade.php`, `CustomerInstallationController` | sedang |
| **4** | Tabel perbandingan di verifikasi admin | `verifications/admin.blade.php` | rendah |

Tahap 1 berdiri sendiri dan bisa dirilis lebih dulu.

---

## Bagian 4 — Keputusan yang Perlu Konfirmasi

1. **Tanggal request wajib atau opsional?** Rancangan ini memilih **opsional**.
   Kalau wajib, semua survey harus punya tanggal dan itu mengubah perilaku papan
   FOP untuk semua PSB.
2. **Ambang "masih jauh".** Sekarang logikanya `>= besok` (mengikuti kode yang sudah
   ada). Alternatif: `> H+3` supaya request lusa tetap terlihat di atas.
3. **Kalau tanggal request sudah lewat tapi task belum dikerjakan** — rancangan ini
   memilih SLA mulai berjalan dari `client_request_date`, jadi task langsung naik
   prioritas. Konfirmasi ini yang diinginkan.
4. **Master item minimum sekarang?** Rancangan ini memakai `item_name` bebas +
   `item_type` enum. Alternatif: bikin tabel `items` sederhana sekarang juga
   (nama + satuan, tanpa stok/harga) supaya penamaan seragam sejak awal.
   Trade-off: lebih rapi, tapi menyentuh wilayah modul Inventory lebih awal.
5. **Apakah `required_tools` lama perlu di-migrasi** jadi baris `task_materials`?
   Rancangan ini memilih **tidak** (teks bebas, tak bisa di-parse andal). Data lama
   tetap tampil sebagai catatan.

**JAWAB**

1. Tanggal Requsest Optional
2. bisaaaa saja kalau beritu
3. jika sudah kelewat hari maka kana terdapat Timer yang seperti SLA dengan contoh -18:25:02
4.  nahhh iyaaa iutu yang saya maksut
5. iyaaa memang data lama pada perangkat pasif itu seperti catatan, dan itu susah buat di Tracking di inventory