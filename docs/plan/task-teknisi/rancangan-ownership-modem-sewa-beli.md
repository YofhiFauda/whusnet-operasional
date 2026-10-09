# Rancangan: Ownership Modem (Sewa / Beli Sendiri / Beli dari Gudang)

Tanggal: 2026-10-09
Status: **spec disetujui user, belum dikerjakan — lanjut ke implementation plan.**
Terkait: `docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md` (Gap 1 — dasar analisa & Q&A bisnis awal). Dokumen ini adalah desain teknis lengkap hasil brainstorming lanjutan, menggantikan rencana solving sementara di Gap 1 §35-40.

Scope dokumen ini: **Gap 1 utuh** + sebagian **Gap 2** (breakdown nominal jual modem di Verifikasi C-REQ, tanpa mengubah skema Invoice/`ManualInvoiceCategory`) + sebagian **Gap 5** (laporan barang terjual — khusus jual ke pelanggan, BUKAN jual antar cabang). Gap 2 sisanya (breakdown C-REQ non-modem), Gap 3, Gap 4, dan Gap 5 sisanya (jual antar cabang) **di luar scope** — tetap gap terbuka, dikerjakan terpisah nanti.

---

## 1. Tujuan

Sistem sekarang gak bisa bedain modem Sewa vs Beli di level behavior — cuma ada `customer_services.contract_type` (sewa/beli) yang sekadar label manual, gak nyambung ke `inventory_serials`, `installSerial()`, atau DEAC. Akibatnya DEAC selalu mencoba menarik modem pas pelanggan putus, walau modem itu udah jadi milik pelanggan.

Tujuan fitur ini:
1. Modem yang **dibeli** (baik beli sendiri dari luar, maupun beli dari gudang kita) ditandai di level sistem sehingga **tidak wajib ditarik** saat DEAC/ganti modem/pindah lokasi.
2. Modem **beli dari gudang** tetap lewat custody/stok kita seperti Sewa (Issue → Install, SN tercatat), tapi dikenakan biaya jual dan dicatat di laporan penjualan — beda dari Sewa yang cuma "dipinjamkan".
3. Modem **beli sendiri** (dari luar) sama sekali di luar sistem stok kita — cuma metadata di `customer_devices`, tidak ada biaya dari kita.
4. Status kepemilikan ini terlihat sebagai label di Detail Pelanggan, List Pelanggan, dan Lacak Barang.

## 2. Data Model Baru

### 2.1 `inventory_serials.ownership` (enum, baru)

Migration tambah kolom `ownership` string(20) default `'company'`, nullable `false`.

Enum baru `app/Enums/DeviceOwnership.php`:
```php
enum DeviceOwnership: string
{
    case COMPANY = 'company';   // wajib ditarik pas DEAC/ganti modem/pindah lokasi — default
    case CUSTOMER = 'customer'; // sudah terjual, TIDAK wajib ditarik
}
```

Ini **axis berbeda** dari `OwnershipMode` (installable/company_asset — soal boleh dipasang ke pelanggan atau cuma alat kerja). Jangan digabung, jangan ditumpangi di enum yang sama — sesuai penegasan di 3 tempat dokumen existing.

Ditulis di `InventoryService::installSerial()` — parameter baru `DeviceOwnership $ownership = DeviceOwnership::COMPANY`, disimpan ke kolom `ownership` SN yang baru diinstall. Satu-satunya titik penulisan (konsisten dengan prinsip "satu pintu transisi ke INSTALLED").

### 2.2 `customer_devices.acquisition_source` (enum, baru)

Migration tambah kolom `acquisition_source` string(20) default `'sewa'`, nullable `false`.

Enum baru `app/Enums/DeviceAcquisitionSource.php`:
```php
enum DeviceAcquisitionSource: string
{
    case SEWA = 'sewa';
    case BELI_SENDIRI = 'beli_sendiri';
    case BELI_GUDANG = 'beli_gudang';

    public function label(): string
    {
        return match ($this) {
            self::SEWA => 'Sewa',
            self::BELI_SENDIRI => 'Beli Sendiri',
            self::BELI_GUDANG => 'Beli dari Gudang',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::SEWA => 'info',
            self::BELI_SENDIRI => 'warning',
            self::BELI_GUDANG => 'success',
        };
    }
}
```

Ini **authoritative buat label display** — field per-device (bukan per-pelanggan seperti `contract_type`), supaya akurat kalau pelanggan ganti modem dari Sewa ke Beli di tengah jalan. Diisi di titik yang sama dengan `installSerial()` (untuk Sewa/Beli dari Gudang) atau langsung saat input manual (untuk Beli Sendiri — tidak lewat `installSerial()` sama sekali).

Hubungan dengan `ownership` di `inventory_serials`:
| `acquisition_source` | Ada baris `inventory_serials`? | `inventory_serials.ownership` |
|---|---|---|
| `sewa` | Ya | `company` |
| `beli_gudang` | Ya | `customer` |
| `beli_sendiri` | Tidak | — |

### 2.3 Tabel baru `inventory_serial_sales`

```php
Schema::create('inventory_serial_sales', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inventory_serial_id')->constrained('inventory_serials')->restrictOnDelete();
    $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
    $table->foreignId('fop_task_id')->nullable()->constrained('fop_tasks')->restrictOnDelete(); // null = dari PSB
    $table->decimal('price', 12, 2);
    $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
    $table->timestamps();
});
```

Cuma dibuat untuk `acquisition_source=beli_gudang`. Jadi sumber tunggal buat breakdown billing (§5) dan Laporan Barang Terjual (§8). FK `restrictOnDelete` konsisten dengan aturan proyek soal data keuangan.

## 3. Flow PSB — Laporan Pemasangan (`CustomerInstallationController::storeSpeedtest()`)

Gate pakai `customer_services.contract_type` yang sudah ada (tidak diubah skemanya):

- **`contract_type=sewa`** — flow existing tidak berubah. `installSerial()` dipanggil tanpa parameter ownership khusus → default `DeviceOwnership::COMPANY`, `acquisition_source=sewa` otomatis ditulis bersamaan.
- **`contract_type=beli`** — form wajib pilih radio (2 opsi, validasi required):
  - **Beli Sendiri**: SN picker (`selected_inventory_serial_id`) disembunyikan & tidak required. Input baru: `device_brand`, `device_model`, `device_serial_number` (teks bebas, bukan FK) — langsung create/update `customer_devices` dengan `acquisition_source=beli_sendiri`. Tidak memanggil `installSerial()`, tidak menyentuh `inventory_serials` sama sekali, tidak ada tagihan modem dari kita.
  - **Beli dari Gudang**: SN picker tetap wajib seperti sekarang. `installSerial($serial, $customer, $fopTask, $technicians, $actor, ownership: DeviceOwnership::CUSTOMER)`. Field baru **Harga Jual Modem** (numeric, required, min 0) → setelah install sukses, create `inventory_serial_sales` (`fop_task_id=null`). Harga ini diteruskan ke `InitialInvoiceService` sebagai baris tambahan di `InvoiceItemBuilder::rebuildFor()` dengan `RevenueSubcategory` baru **"Jual Modem"** (di bawah `RevenueCategory` yang sudah relevan — perlu dicek master data existing, kemungkinan di bawah kategori "Perangkat"/sejenis saat implementasi). Tagihan AWAL tetap satu invoice, cuma nambah baris.

## 4. Flow Ganti/Tambah Modem — MTN & C-REQ (`TaskMaintenanceController`)

Section "Modem/Perangkat Aktif" existing di-gate radio **4 opsi**, wajib dipilih begitu teknisi mau pasang/ganti SN apa pun (berlaku untuk MTN biasa maupun semua kategori C-REQ — bukan cuma yang `requiresModem()`):

- **Tidak Ganti Modem** (default) — section SN picker disembunyikan, tidak ada perubahan ownership apa pun.
- **Sewa Gudang** — flow seperti sekarang. `installSerial(..., ownership: DeviceOwnership::COMPANY)`, `acquisition_source=sewa`. `$returnExistingSerial` tetap ikut logic existing `creqCategory->addsModemWithoutReturningExisting()`.
- **Beli Sendiri** — SN picker disembunyikan, input manual brand/model/serial ke `customer_devices`, `acquisition_source=beli_sendiri`. Tidak ada charge, tidak menyentuh `inventory_serials`.
- **Beli dari Gudang** — SN picker wajib, `installSerial(..., ownership: DeviceOwnership::CUSTOMER)`, `acquisition_source=beli_gudang`. Field **Harga Jual Modem** wajib → create `inventory_serial_sales` dengan `fop_task_id` terisi (task saat ini).

Validasi: radio ini independen dari `TaskType`/`CReqCategory` — gate murni di level "pilih SN atau tidak", jadi satu implementasi berlaku ke MTN dan C-REQ sekaligus.

## 5. Billing — Breakdown Jual Modem di Verifikasi C-REQ

`TaskCreqBillingController::approve()` — kalau task punya `inventory_serial_sales` row terkait (hasil pilihan Beli dari Gudang di laporan teknisi), form tambah 2 input:
- **Harga Modem** — prefilled dari `inventory_serial_sales.price`, CS boleh koreksi.
- **Biaya Jasa** — nominal jasa di luar harga modem.

Server men-sum dua nominal → tetap satu nilai yang dikirim ke `ManualCategoryInvoiceService::issue()` sebagai `$amount` (skema `Invoice`/`ManualInvoiceCategory` **tidak diubah** — tetap flat subtotal, tidak pakai `invoice_items`). `description` invoice disusun otomatis: `"Jasa: Rp{jasa} + Jual Modem: Rp{harga}"`. `inventory_serial_sales.price` di-update dengan nilai final (hasil koreksi CS) supaya Laporan Barang Terjual (§8) konsisten dengan nominal yang benar-benar ditagih.

Kalau task tidak punya `inventory_serial_sales` row (Sewa/Tidak Ganti Modem/Beli Sendiri) — form & flow Verifikasi C-REQ tidak berubah sama sekali dari sekarang.

## 6. DEAC — Skip Modem Milik Pelanggan (`TaskDeviceRetrievalController`)

`report()` — query `installedSerials` ditambah filter `->where('ownership', DeviceOwnership::COMPANY->value)`. SN `ownership=customer` tidak pernah muncul di daftar wajib ditarik.

Kasus SEMUA SN pelanggan itu `ownership=customer` (list jadi kosong) atau pelanggan cuma punya `acquisition_source=beli_sendiri` (gak pernah ada SN sama sekali): tampilkan banner info "Modem pelanggan ini milik sendiri/sudah dibeli — tidak perlu ditarik" dan longgarkan guard `serials[]` `required_if:outcome,diambil` khusus kondisi ini — teknisi submit konfirmasi, `TaskDeviceRetrieval` tercatat outcome `DIAMBIL`, `serials=[]`, `notes` auto-terisi "Modem milik pelanggan, tidak perlu ditarik". **Tidak menambah case baru di `DeviceRetrievalOutcome`** (YAGNI — keputusan user).

## 7. Label — Detail Pelanggan, List Pelanggan, Lacak Barang

Satu sumber: `customer_devices.acquisition_source`. Badge baru mengikuti pola `<x-warehouse.origin-badge>`:
- `sewa` → "Sewa" (info)
- `beli_sendiri` → "Beli Sendiri" (warning)
- `beli_gudang` → "Beli dari Gudang" (success)

Dipasang di:
- Detail Pelanggan (`customers.show`) — section device/teknis, dekat info SN/modem existing.
- List Pelanggan (`resources/views/customers/partials/_list_table.blade.php:183,320`) — kolom `contract_type` existing di halaman ini disandingkan/diselaraskan dengan badge ini (hindari dua label berbeda yang bisa gak sinkron — detail implementasi diputuskan saat coding, prioritaskan satu sumber kebenaran).
- Lacak Barang (warehouse) — cuma relevan untuk `sewa`/`beli_gudang` (yang punya baris `inventory_serials`); `beli_sendiri` otomatis tidak muncul di sini karena memang tidak tercatat gudang.

## 8. Laporan Barang Terjual ke Pelanggan

Halaman/tab baru di Laporan Gudang. Query dari `inventory_serial_sales` join `inventory_serials` → `item`, `customer`, `fop_task` (nullable).

Kolom: tanggal jual, SN, nama barang, pelanggan, cabang (POP pelanggan), harga jual, sumber (PSB jika `fop_task_id` null / C-REQ-MTN jika terisi), diinput oleh (`created_by`).

- **POP scope wajib** — filter pakai `EffectiveAccessService::getAllowedPopIds()`, pola sama laporan gudang lain. Tidak boleh bocor lintas cabang.
- **Permission baru**: `warehouse_reports.sold_to_customer.view` — granular per halaman, bukan numpang permission generik.

## 9. Testing

Feature test baru (nama sesuai gejala, bukan kelas):

1. `CustomerInstallationOwnershipBeliSendiriTest` — PSB `contract_type=beli` → Beli Sendiri: `acquisition_source=beli_sendiri`, tidak ada baris `inventory_serials`/`inventory_serial_sales` baru.
2. `CustomerInstallationOwnershipBeliGudangTest` — PSB `contract_type=beli` → Beli dari Gudang: `installSerial` set `ownership=customer`, `inventory_serial_sales` tercatat, baris InvoiceItem "Jual Modem" muncul di tagihan AWAL, invariant subtotal `InvoiceItemBuilder` tetap lolos.
3. `TaskMaintenanceOwnershipRadioRequiredTest` — submit laporan MTN/C-REQ pilih SN tanpa pilih radio ownership → validasi gagal.
4. `TaskCreqBillingModemSaleBreakdownTest` — Verifikasi C-REQ dengan sale row: breakdown Harga Modem + Jasa ter-sum jadi 1 invoice amount, description auto-compose, `inventory_serial_sales.price` sinkron hasil koreksi CS.
5. `DeviceRetrievalSkipsCustomerOwnedSerialTest` — DEAC: SN `ownership=customer` tidak muncul di `installedSerials`; kasus semua SN customer-owned → banner otomatis, `TaskDeviceRetrieval` outcome `DIAMBIL` `serials=[]` tanpa guard gagal.
6. `CustomerDetailShowsAcquisitionSourceBadgeTest` — badge Sewa/Beli Sendiri/Beli dari Gudang tampil benar di Detail Pelanggan & List Pelanggan.
7. `WarehouseSoldToCustomerReportScopedByPopTest` — laporan baru terpotong POP scope, permission `warehouse_reports.sold_to_customer.view` ditegakkan.
8. Regresi: `InstallationSerialDropdownAuthorityTest` dan test existing `TaskCreqBillingController::approve()` tetap lolos tanpa sale row.

## 10. Keputusan Terbuka yang Sudah Diselesaikan (ringkasan Q&A brainstorming)

- Siapa set flag ownership: **teknisi** pas submit laporan (checkbox/radio), bukan CS.
- Breakdown billing: tabel baru `inventory_serial_sales`, **tidak** mengubah skema `Invoice`/`ManualInvoiceCategory` (FINAL).
- DEAC skip: **dikeluarkan dari daftar**, tidak menambah case `DeviceRetrievalOutcome` baru.
- Data lama: **dibiarkan** default `ownership=company`/`acquisition_source=sewa` (dianggap Sewa), tidak ada backfill manual/tool koreksi massal.
- Radio 4 opsi: berlaku **di semua task MTN & C-REQ** begitu teknisi pilih SN apa pun, bukan cuma kategori C-REQ yang `requiresModem()`.
- Laporan Barang Terjual ke Pelanggan: **masuk scope** dokumen ini (bukan ditunda ke Gap 5).

## 11. Di Luar Scope (tetap gap terbuka)

- Gap 2 sisanya: breakdown nominal C-REQ untuk pekerjaan non-modem (tetap 1 field `amount` manual).
- Gap 3: form O-REQ/INFR REQ generik.
- Gap 4: perangkat infrastruktur non-pelanggan (OLT/Switch/SFP/AP).
- Gap 5 sisanya: jual barang bekas retur **antar cabang** (Pusat → Cabang B) — beda dari laporan di §8 yang khusus jual ke pelanggan.
