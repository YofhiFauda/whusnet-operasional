# Analisa: Manajemen POP, Mini-POP, Distribusi & Sistem ID Pelanggan

> Dokumen ini menganalisa kondisi saat ini vs kebutuhan bisnis nyata untuk sistem manajemen jaringan hierarki dan pembentukan ID pelanggan (Request ID → CID → Terminated).

---

## 1. Pemahaman Kebutuhan Bisnis

### Hierarki Jaringan ISP Anda

Berdasarkan penjelasan Anda, sistem mengikuti struktur jaringan fisik berikut:

```
Perusahaan
└── POP / Cabang          ← contoh: "D" (kode prefix CID)
    └── OLT / Mini-POP    ← contoh: "2" (nomor OLT ke-2 di cabang tersebut)
        └── Distribusi    ← contoh: "X6C" (kode distribusi/area)
            └── Pelanggan
```

### Decoding Kode `D2X6C`

| Bagian | Nilai | Makna |
|--------|-------|-------|
| `D`    | Huruf | Kode prefix Cabang POP (`cid_prefix` di tabel `pops`) |
| `2`    | Angka | Nomor OLT ke-2 di cabang tersebut (`olt_number` di `customer_technical_details`) |
| `X6C`  | Kode  | Kode distribusi (`code` di tabel `distributions`) |

### Siklus Hidup ID Pelanggan

```
[REGISTRASI] → Request ID: RQ000474
                    ↓ (setelah aktivasi & teknis lengkap)
[AKTIF]      → CID: D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH
                    ↓ (jika terminate/putus)
[BERHENTI]   → Kembali ke Request ID: RQ000474
```

**Format CID Lengkap:**
```
{cid_prefix}{olt_number}{dist_code}{customer_code}_{DESA}_{NAMAPELANGGAN}
     D            2         X6C      RQ001296    MANGKUJAYAN  DYAHGALUH
```

---

## 2. Kondisi Implementasi Saat Ini

### Yang Sudah Ada ✅

#### A. Tabel `pops`
- Ada field `cid_prefix` (kode huruf seperti "D") — sudah sesuai
- Ada field `registration_prefix` (prefix untuk RQ number)
- Ada `type`: `pusat`, `cabang`, `mini_pop` (tapi mini_pop tidak dipakai sebagai OLT)
- Ada parent-child relationship (`parent_id`)

#### B. Tabel `distributions`
- Ada `pop_id` (relasi ke POP/Cabang)
- Ada `code` (kode distribusi, misal "X6C")
- Ada `name`, `description`
- **Composite unique index**: `(pop_id, code)` ← sudah difix di migration terbaru

#### C. Tabel `customer_technical_details`
- Ada `olt_number` — sudah ditambahkan di Sprint 9-T001
- Ada `olt_slot`, `vlan`
- Ada `odp_number`, `odp_port`, `olt_port`

#### D. Model `Pop::generateComplexCid()`
- Sudah ada implementasi awal di `app/Models/Pop.php`
- Format: `{prefix}{olt_number}{dist_code}{customer_code}_{village}_{name}`

---

## 3. Gap dan Kebingungan yang Ditemukan

### Gap 1: OLT / Mini-POP tidak punya entitas sendiri ⚠️

**Masalah:**  
Saat ini struktur POP menggunakan `type = mini_pop` di tabel `pops`, tapi **mini-POP tidak merepresentasikan OLT secara teknis**. OLT bisa punya banyak port, slot, dan nomor — ini bukan entitas admin/cabang biasa.

Yang terjadi saat ini:
- `olt_number` disimpan di `customer_technical_details` — artinya ini adalah **atribut pelanggan**, bukan master data OLT
- Tidak ada tabel master OLT/Mini-POP yang independent
- Jika dua pelanggan berbeda sama-sama di OLT nomor 2 cabang D, mereka hanya tau itu dari field teknis masing-masing — **tidak ada relasi ke entitas OLT bersama**

**Implikasi untuk CID:**  
`olt_number` dalam CID (`D**2**X6C...`) diambil dari `customer_technical_details.olt_number`. Ini valid, tapi tidak terhubung ke master OLT yang terdaftar. Jika OLT diganti atau dipindah, tidak ada cara trace pelanggan mana yang terdampak.

---

### Gap 2: Relasi Distribusi ke Pelanggan belum ada ⚠️

**Masalah:**  
Tabel `distributions` hanya berelasi ke `pops`. Pelanggan (`customers`) **tidak punya `distribution_id`**.

Saat ini di `generateComplexCid()`, distribusi dioper sebagai parameter fungsi:
```php
$cid = $pop->generateComplexCid($customer, $dist);
```

Artinya: siapa yang memilih `$dist` untuk pelanggan ini? **Tidak ada tempat penyimpanannya di database.** CID bisa digenerate, tapi distribusi mana yang digunakan tidak tersimpan di record pelanggan.

---

### Gap 3: CID belum tersimpan konsisten di database ⚠️

**Masalah:**  
Model `Customer` punya field `cid`, tapi:
- Proses kapan `cid` di-set belum jelas (saat aktivasi? saat teknis diisi?)
- Jika pelanggan terminate, field `cid` tetap di database — yang berubah hanya status
- Logika display "tampilkan `customer_code` jika terminated" ada di view/controller, bukan konsisten di model

---

### Gap 4: Request ID Format Tidak Konsisten ⚠️

**Masalah:**  
Dari contoh Anda, Request ID: `RQ000474` dan `RQ001296` — format ini adalah **angka sequential** per POP.

Implementasi saat ini di `Pop::generateIdentifier()`:
```php
return sprintf('%s-%s-%06d', $prefix, $this->pop_code, $nextNumber);
// Hasil: RQ-SMN-000001 (ada dash dan pop_code)
```

Tapi format yang Anda inginkan adalah: `RQ000474` (**tanpa dash, tanpa pop_code**).

> **Ini adalah inkonsistensi format yang bisa menyebabkan CID tidak sesuai ekspektasi.**

---

### Gap 5: `generateComplexCid()` tidak dipanggil di flow aktivasi ⚠️

**Masalah:**  
Method `generateComplexCid()` sudah ada di model `Pop`, tapi **belum diintegrasikan ke flow aktivasi pelanggan**. CID belum otomatis digenerate saat pelanggan diaktifkan.

---

## 4. Arsitektur yang Direkomendasikan

### Opsi A: Pendekatan Minimal (Cukup untuk MVP) ✅ Direkomendasikan

Tidak membuat tabel OLT terpisah. Cukup:

1. **Tambah `distribution_id` ke tabel `customers`** — agar relasi distribusi pelanggan tersimpan
2. **Perbaiki format Request ID** — hilangkan dash dan pop_code, jadikan `RQ######`
3. **Integrasikan `generateComplexCid()` ke flow aktivasi** — panggil saat status berubah ke `active`
4. **OLT tetap disimpan sebagai angka di `customer_technical_details.olt_number`** — cukup untuk generate CID

**Skema data minimal:**
```
customers
├── customer_code        = "RQ001296"  (Request ID, generated saat registrasi)
├── cid                  = "D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH"  (generated saat aktif)
├── distribution_id      = FK ke distributions.id  ← PERLU DITAMBAHKAN
├── status               = registered | active | terminated | ...
└── ...

customer_technical_details
└── olt_number           = "2"  ← digunakan saat generate CID

distributions
├── id
├── pop_id               = FK ke pops (cabang)
├── code                 = "X6C"
└── name                 = "Distribusi Area X6"

pops
├── cid_prefix           = "D"
├── registration_prefix  = "RQ"
└── pop_code             = "SMN"
```

### Opsi B: Pendekatan Lengkap (Post-MVP) 🔜

Tambahkan tabel `olts` sebagai master OLT:
```
pops → olts → distributions → customers
```

Dengan tabel `olts`:
```sql
id, pop_id, olt_number, name, brand, model, ip_address, status
```

Ini memungkinkan:
- Manajemen OLT/mini-POP sebagai entitas terpisah
- Filter pelanggan per OLT
- Kapasitas pelanggan per OLT
- Monitoring per OLT (jika integrasi SNMP dilakukan di fase berikutnya)

> **Opsi B adalah post-MVP sesuai aturan AGENTS.md — jangan dikerjakan sekarang.**

---

## 5. Alur Sistem yang Benar (Setelah Fix)

```
REGISTRASI PELANGGAN
Admin pilih: POP/Cabang (D) → Distribusi (X6C)
Sistem generate: customer_code = RQ000474
Simpan: customers.distribution_id = ID distribusi yang dipilih
Status: registered

↓

PENGISIAN TEKNIS (Teknisi)
customer_technical_details.olt_number = "2"
customer_technical_details.odp_number = "ODP-D2-01"
dll.

↓

AKTIVASI PELANGGAN
Sistem load: pop.cid_prefix = "D"
Sistem load: tech.olt_number = "2"
Sistem load: distribution.code = "X6C" (dari customer.distribution_id)
Sistem load: customer.customer_code = "RQ001296"
Sistem load: village.name = "MANGKUJAYAN"
Sistem load: customer.full_name = "DYAH GALUH"
CID = "D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH"
Simpan: customers.cid = CID tersebut
Status: active

↓

TERMINATE / PUTUS
Status: terminated
UI menampilkan: customer_code (RQ001296), bukan CID
customers.cid tetap tersimpan di DB (sebagai histori)
```

---

## 6. Task Konkret yang Perlu Dikerjakan

### Task 1: Perbaiki Format Request ID
**File:** `app/Models/Pop.php` → method `generateIdentifier()`

Ubah format dari:
```php
sprintf('%s-%s-%06d', $prefix, $this->pop_code, $nextNumber)
// → RQ-SMN-000001
```
Menjadi:
```php
sprintf('%s%06d', $prefix, $nextNumber)
// → RQ000001
```

> ⚠️ Perlu migrasi data existing jika sudah ada pelanggan dengan format lama.

---

### Task 2: Tambah `distribution_id` ke `customers`
**File baru:** migration `add_distribution_id_to_customers_table.php`

```php
$table->foreignId('distribution_id')
    ->nullable()
    ->constrained('distributions')
    ->nullOnDelete();
```

**File:** `app/Models/Customer.php` — tambah fillable `distribution_id` dan relasi:
```php
public function distribution(): BelongsTo {
    return $this->belongsTo(Distribution::class);
}
```

---

### Task 3: Integrasikan CID Generation ke Flow Aktivasi
**File:** `app/Http/Controllers/CustomerController.php` → method `activate()`

Saat status diubah ke `active`:
```php
$distribution = $customer->distribution;
$pop = $customer->pop;
if ($pop && $distribution) {
    $cid = $pop->generateComplexCid($customer, $distribution);
    $customer->update(['cid' => $cid]);
}
```

---

### Task 4: Tambah Dropdown Distribusi di Form Registrasi
**File:** `resources/views/customers/create.blade.php`

Tambah field: **Pilih Distribusi** (dropdown yang di-filter berdasarkan POP yang dipilih).

---

### Task 5: Logika Tampilan ID (UI)
**File:** `resources/views/customers/show.blade.php` dan `index.blade.php`

```php
$displayId = in_array($customer->status, ['active', 'suspended'])
    ? $customer->cid
    : $customer->customer_code;
```

---

## 7. Rangkuman Kebingungan & Solusinya

| Kebingungan | Penyebab | Solusi |
|-------------|----------|--------|
| Format CID `D2X6C` dari mana? | "D" dari `pop.cid_prefix`, "2" dari `tech.olt_number`, "X6C" dari `distribution.code` | Dokumentasikan & pastikan relasi distribusi tersimpan di pelanggan |
| Request ID format `RQ000474` | Generator saat ini menghasilkan `RQ-SMN-000001` (ada dash) | Fix `generateIdentifier()` agar hanya `{prefix}{######}` |
| Distribusi mana yang dipakai pelanggan ini? | Tidak ada `distribution_id` di tabel `customers` | Tambah FK `distribution_id` ke `customers` |
| Kapan CID digenerate? | Belum ada trigger — tidak otomatis | Integrasikan ke flow aktivasi di `CustomerController` |
| Setelah terminate tampil apa? | UI display belum konsisten | Aturan: jika `terminated`, tampilkan `customer_code`; jika `active`/`suspended`, tampilkan `cid` |
| OLT sebagai mini-POP atau entitas teknis? | Tabel `pops` punya type `mini_pop` tapi tidak merepresentasikan OLT fisik | Untuk MVP: OLT tetap sebagai angka di `customer_technical_details.olt_number`. Entitas OLT terpisah adalah post-MVP |

---

## 8. Pertanyaan untuk Konfirmasi

Sebelum memulai implementasi, perlu konfirmasi:

1. **Format Request ID**: Apakah `RQ000474` itu sequential per POP atau global? (Dari kode saat ini sepertinya per POP, tapi `000474` vs `001296` menunjukkan rentang berbeda)

2. **Kapan distribusi dipilih?**: Saat registrasi (oleh admin/CS), atau saat survei (oleh teknisi)?

3. **CID digenerate kapan?**: Saat pelanggan `active` (sudah terpasang dan diaktifkan), atau setelah data teknis terisi?

4. **Apakah pelanggan lama (hasil migrasi) perlu di-generate CID-nya?**: Data legacy dari `sand_db_sandya.sql` yang statusnya `active` — apakah perlu CID dibuatkan retroaktif?

5. **Format nama di CID**: `DYAHGALUH` — ini nama lengkap tanpa spasi. Bagaimana jika nama mengandung karakter khusus (titik, apostrof)?

---

## 9. Gap Tambahan — Ditemukan 18 Juni 2026

Gap berikut ditemukan saat review implementasi setelah dokumen analisa awal ditulis. Tidak tercakup di Bagian 3 karena baru teridentifikasi dari review kode aktual.

---

### Gap 6: Inkonsistensi `olt_code` vs `olt_number` — OLT Ambigu di UI ⚠️ → **SUDAH DIFIX**

**Masalah yang ditemukan:**

Terdapat dua field OLT dengan tujuan berbeda yang tidak dikomunikasikan dengan jelas di UI:

| Field | Tabel | Diisi oleh | Digunakan untuk CID? |
|-------|-------|------------|----------------------|
| `olt_code` | `customers` | Admin/CS saat registrasi | ❌ Tidak |
| `olt_number` | `customer_technical_details` | Teknisi saat survei/instalasi | ✅ Ya |

**Dampak:** Admin bisa mengisi "Kode OLT" di form registrasi, tapi CID tetap default ke angka `1` sampai teknisi mengisi `olt_number` di tab detail teknis. Tidak ada petunjuk di UI bahwa kedua field ini berbeda fungsinya.

**Fix yang diterapkan (18 Juni 2026):**

1. **`create.blade.php` & `edit.blade.php`** — Label field `olt_code` diubah dari "KODE OLT" menjadi "NAMA / KODE PERANGKAT OLT" dengan keterangan eksplisit:
   > *"label perangkat, bukan untuk CID"* dan *"Nomor OLT untuk CID diisi teknisi saat survei/pemasangan."*

2. **`show.blade.php`** — Section "INTEGRASI TEKNIS" ditambahkan baris "Nomor OLT" yang membaca dari `customer_technical_details.olt_number` dengan badge `[CID]` untuk membedakannya dari `olt_code`. Jika belum diisi, tampil warning *"Belum diisi teknisi"* berwarna amber.

**Status:** ✅ Fix UI selesai. Tidak ada perubahan database — `olt_code` tetap ada sebagai label perangkat, `olt_number` tetap sebagai sumber CID.

---

### Gap 7: Inkonsistensi `displayId` di Halaman Index ⚠️ → **SUDAH DIFIX**

**Masalah yang ditemukan:**

Logika tampilan ID antara `index.blade.php` dan `show.blade.php` tidak konsisten:

```php
// show.blade.php (BENAR)
$displayId = in_array($status, ['active', 'suspended']) && $customer->cid
    ? $customer->cid
    : $customer->customer_code;

// index.blade.php (SALAH — sebelum fix)
$displayId = $customer->cid ?? $customer->customer_code;
```

**Dampak:** Pelanggan `terminated` yang masih memiliki `cid` tersimpan di DB akan menampilkan CID di halaman daftar, padahal seharusnya kembali ke `customer_code` (Request ID).

**Fix yang diterapkan (18 Juni 2026):**

`index.blade.php` diubah menjadi:
```php
$displayId = in_array($customer->status, ['active', 'suspended']) && $customer->cid
    ? $customer->cid
    : $customer->customer_code;
```

**Status:** ✅ Selesai. Logika sekarang konsisten di seluruh halaman.

---

## 10. Status Akhir Semua Gap (Update 18 Juni 2026)

| Gap | Deskripsi | Status |
|-----|-----------|--------|
| Gap 1 | OLT tidak punya entitas master terpisah | 🔜 Post-MVP — ditunda by design |
| Gap 2 | `distribution_id` tidak ada di `customers` | ✅ Selesai (migration + model + form) |
| Gap 3 | CID belum tersimpan konsisten saat aktivasi | ✅ Selesai (flow aktivasi di controller) |
| Gap 4 | Format Request ID `RQ-SMN-000001` vs `RQ000001` | ✅ Selesai (fix `generateIdentifier()`) |
| Gap 5 | `generateComplexCid()` tidak dipanggil saat aktivasi | ✅ Selesai (integrasi di `CustomerController::activate()`) |
| Gap 6 | `olt_code` vs `olt_number` ambigu di UI | ✅ Selesai (label & hint diperjelas, show ditambah baris `olt_number`) |
| Gap 7 | `displayId` tidak konsisten antara index dan show | ✅ Selesai (index diseragamkan dengan logika show) |

**Semua gap MVP sudah ditangani.** Gap 1 (tabel OLT terpisah) tetap ditunda sebagai post-MVP sesuai keputusan di AGENTS.md.
