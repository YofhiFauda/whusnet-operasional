# Unify Dropdown Item Dipasang di Laporan PSB — Form 1 (Pemasangan/Aktivasi)

Tanggal: 2026-11-02 · Status: **menunggu review user** · Modul: Task Teknisi / Instalasi (PSB)

## 1. Masalah

Langkah 5 ("Alat") wizard laporan instalasi PSB (`CustomerInstallationController::report()`) sebenarnya berisi **2 form HTML berbeda**:

- **Form 1 — Pemasangan/Aktivasi** (`storePemasangan()` → tombol "Aktivasi"): berisi data device + **3 picker barang terpisah** — combobox SN "Perangkat Aktif" (modem), combobox "Roll Kabel", dan `<x-material-rows>` khusus Perangkat Pasif.
- **Form 2 — Perangkat Tambahan** (`CustomerInstallationAdditionalDeviceController::store()`, form HTML sendiri di bagian bawah Langkah 5 yang sama): sudah punya dropdown growable sendiri (tombol "+ Tambah Perangkat").

Ketiga picker di Form 1 sumbernya custody tim yang sama (sudah benar, sudah di-scope), tapi dipisah jadi 3 kontrol UI beda karena mekanismenya beda:

| Picker (Form 1) | Sumber data | Mekanisme | Kolom tujuan di `CustomerInstallation` |
|---|---|---|---|
| SN Perangkat Aktif (modem) | `eligibleSerialsForTeam()` → `InventorySerial` | per-serial, **singular** (1 modem/instalasi) | `selected_inventory_serial_id` |
| Roll Kabel | `eligibleRollsForTeam()` → `InventoryRoll` | per-roll, **singular** (1 roll/instalasi) | `selected_inventory_roll_id` + `roll_meters_used` |
| Material Terpakai (Pasif) | `eligiblePassiveCustodyForTeam()` → `TechnicianCustody` | per-qty/batch, **plural** (`<x-material-rows>` sudah growable) | `materials[]` (tabel `TaskMaterial`, lewat `TaskMaterialService`) |

**Form 2 sudah sesuai pola target** (dropdown + tombol tambah baris) — tidak perlu diubah.

## 2. Tujuan & keberhasilan

- Di Form 1, teknisi pilih barang lewat **satu dropdown growable** (perluasan `<x-material-rows>` / komponen baru serupa) yang menawarkan Modem + Roll Kabel + Pasif sekaligus — bukan 3 kontrol terpisah.
- Baris **Modem dan baris Roll dibatasi maksimal 1 masing-masing** (ditegakkan di UI, karena kolom tujuannya singular di DB — lihat tabel §1); baris Pasif tetap unlimited seperti sekarang.
- Submit Form 1 **tetap menulis ke kolom/field yang sama persis** (`selected_inventory_serial_id`, `selected_inventory_roll_id`, `roll_meters_used`, `materials[]`) — komponen baru cuma mengganti cara teknisi *memilih*, bukan apa yang *disimpan/divalidasi/dieksekusi*.
- **Tidak ada perubahan di `storePemasangan()`, `storeSpeedtest()`, skema tabel, atau logic consumption** (`installSerial()`, `consumeFromRoll()`, `InventoryService::reconcileMaterialsAgainstCustody()`). Risiko minim karena backend tidak disentuh — perubahan murni Blade + Alpine.
- Keberhasilan: teknisi isi Form 1 lewat 1 dropdown gabungan tanpa perlu tahu istilah "SN/roll/custody"; test existing (`CustomerInstallationTest`, `InstallationRollConsumptionTest`, dll) tetap lulus tanpa diubah karena nama field POST tidak berubah.

## 3. Di luar cakupan

- **Form 2 (Perangkat Tambahan)** — sudah punya pola growable sendiri, tidak diubah.
- **Radio ownership modem** (`modem_acquisition_choice`: beli_sendiri/beli_gudang) — ditentukan dari `contract_type` pelanggan (keputusan dibuat saat registrasi, bukan di Langkah 5), tetap apa adanya. Saat `beli_sendiri` dipilih, baris "Modem" di dropdown baru otomatis tidak relevan (SN prohibited) — perilaku ini **tidak berubah**, cuma mengikuti validasi existing.
- **Checkbox cable crossing** — tidak digabung ke dropdown ini, tetap kontrol terpisah seperti sekarang.
- Form lain yang masih pakai pola lama (pendaftaran, verifikasi admin, maintenance non-PSB, edit pelanggan) — tidak disentuh.
- Migrasi data / skema tabel — tidak perlu, tidak ada perubahan struktur data.

## 4. Desain

### 4.1 Komponen UI baru: `installation-item-rows`

Komponen Blade baru `resources/views/components/installation-item-rows.blade.php` (bukan memodifikasi `material-rows.blade.php` langsung — komponen itu dipakai juga di Laporan Survey yang tidak butuh mekanisme serial/roll; file baru menghindari risiko regresi ke halaman lain, sesuai pola "file yang berubah bersama hidup bersama").

- Dibangun dari pola `material-rows.blade.php` (Alpine `x-data`, `addRow()`/`removeRow()`), tapi tiap opsi dropdown "Barang" membawa `mechanism` (`modem|roll|pasif`) yang diturunkan dari sumber datanya (bukan field baru di `ItemCategory`):
  - opsi `mechanism=modem`: satu opsi per `InventorySerial` eligible (dari `eligibleSerialsForTeam()`) — pilih opsi ini langsung menentukan SN-nya, tidak ada input qty.
  - opsi `mechanism=roll`: satu opsi per `InventoryRoll` eligible (dari `eligibleRollsForTeam()`) — pilih opsi ini menampilkan input "Meter Terpakai" dengan hint `length_remaining` roll itu.
  - opsi `mechanism=pasif`: satu opsi per item dari `eligiblePassiveCustodyForTeam()` (perilaku identik `<x-material-rows restrict-to-custody>` sekarang) — menampilkan input qty dengan hint `available`.
- Alpine (`installationItemRows()`, fungsi baru, bukan reuse `materialRows()`) menegakkan: hanya **1 baris aktif** boleh ber-`mechanism=modem`, hanya **1 baris aktif** boleh ber-`mechanism=roll` (opsi modem/roll yang sudah dipakai baris lain disembunyikan dari dropdown baris berikutnya, sama pola `optionsFor()` yang sudah ada di `_additional_devices_step.blade.php` buat exclude SN terpakai).
- **Nama `name` attribute per baris mengikuti `mechanism`-nya**, bukan satu pola array seragam:
  - `mechanism=modem` → `<input type="hidden" name="selected_inventory_serial_id" :value="row.serial_id">` (field singular, tanpa index array).
  - `mechanism=roll` → `name="selected_inventory_roll_id"` + `name="roll_meters_used"` (singular).
  - `mechanism=pasif` → `name="materials[{{ index }}][item_id|qty|unit|item_type|note]"` (array, persis format `<x-material-rows>` sekarang — index baris boleh ada gap, `materials.*` wildcard validation tidak masalah).
- Baris kosong (belum pilih barang) tidak merender `name` apa pun — aman diskip oleh validasi existing (sama seperti baris kosong `<x-material-rows>` sekarang, yang sudah dibuang `TaskMaterialService::normalizeRow()`).

### 4.2 Query gabungan (server, sisi render form)

Di `CustomerInstallationController::report()`, 3 metode private yang sudah ada (`eligibleSerialsForTeam()`, `eligibleRollsForTeam()`, `eligiblePassiveCustodyForTeam()`) **tidak diubah isinya** — hanya dipanggil seperti sekarang, lalu hasilnya dikirim ke `<x-installation-item-rows>` sebagai 3 prop terpisah (`:serial-options`, `:roll-options`, `:custody-options`). Komponen sendiri yang menggabungkan jadi satu daftar dropdown di sisi Alpine (`itemOptions` gabungan dengan field `mechanism` penanda asal). Tidak ada service baru, tidak ada query baru — murni pemetaan data yang sudah dikirim ke view, sama seperti sebelumnya.

### 4.3 Perubahan di `report.blade.php`

Blok render Step 5 yang sekarang memanggil combobox SN modem + combobox roll + `<x-material-rows :custody-options="$eligiblePassiveCustody" restrict-to-custody />` (sekitar baris 1050-1072, perlu dikonfirmasi ulang persis saat implementasi) diganti satu panggilan:

```blade
<x-installation-item-rows
    :serial-options="$eligibleSerials"
    :roll-options="$eligibleRolls"
    :custody-options="$eligiblePassiveCustody"
    :initial-rows="$existingUsage"
/>
```

Prefill (`$existingUsage`, baris material tersimpan) dan prefill SN/roll yang sudah tersimpan di `$installation->selected_inventory_serial_id`/`selected_inventory_roll_id` dipetakan jadi baris awal komponen (supaya buka ulang laporan tetap menunjukkan pilihan sebelumnya) — detail pemetaan ini bagian dari task implementasi, bukan keputusan baru.

### 4.4 Testing

**Tidak perlu test backend baru** — `storePemasangan()`/`storeSpeedtest()` tidak berubah, field POST tetap sama, jadi test existing (`CustomerInstallationTest`, `InstallationRollConsumptionTest`, `MaterialReportFlowTest`, dll) harus tetap lulus **tanpa modifikasi** sebagai bukti bahwa perubahan ini murni presentasi. Ini justru acceptance criteria utama task: kalau test lama sampai perlu diubah, berarti scope sudah melebar ke backend dan harus dihentikan untuk dicek ulang.

Test baru yang relevan (kalau ditambahkan) sebatas sanity di level Blade/Alpine rendering (opsional, repo ini tidak punya kebiasaan browser test) — fokus verifikasi via test existing yang hit endpoint `storePemasangan` dengan payload yang sekarang dihasilkan komponen baru (field names identik).

## 5. Risiko & catatan implementasi

- Komponen baru (bukan modifikasi `material-rows.blade.php`) supaya Laporan Survey (pemakai lain komponen itu) tidak kena resiko apa pun dari perubahan ini.
- Pastikan `npm run build` dijalankan setelah ubah Blade — tanpa itu Alpine baru tidak ikut bundle, dropdown jadi mati tanpa error jelas.
- Baris Modem/Roll yang "sudah dipakai di baris lain disembunyikan dari dropdown" meniru pola `optionsFor()` di `_additional_devices_step.blade.php` — baca file itu dulu sebagai referensi saat implementasi, jangan desain ulang dari nol.
- Prefill (buka ulang laporan yang sudah pernah disubmit) harus dites manual (run app, isi laporan, reload halaman) karena ini area yang mudah salah tanpa browser test otomatis.
