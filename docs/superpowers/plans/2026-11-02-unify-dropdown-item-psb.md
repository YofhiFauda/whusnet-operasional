# Unify Dropdown Item PSB (Form 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti 3 picker barang terpisah (SN Modem, Roll Kabel, Material Pasif) di Form 1 (Pemasangan/Aktivasi) Langkah 5 Laporan PSB jadi satu komponen dropdown growable, tanpa mengubah backend/validasi/consumption apa pun.

**Architecture:** Komponen Blade+Alpine baru (`installation-item-rows`) menggabungkan 3 collection data yang sudah dikirim `report()` (`$eligibleSerials`, `$eligibleRolls`, `$eligiblePassiveCustody`) jadi satu dropdown; tiap baris merender `name` attribute sesuai mekanisme barangnya (singular untuk modem/roll, array `materials[]` untuk pasif) — sehingga POST yang dikirim ke `storePemasangan()` identik dengan sebelumnya. Backend (controller, FormRequest/validate, service consumption) tidak disentuh sama sekali.

**Tech Stack:** Blade component, Alpine.js 3 (lewat Vite), PHPUnit 12 (test existing, tidak ada test backend baru).

**Spec:** `docs/superpowers/specs/2026-11-02-unify-dropdown-item-psb-design.md`

## Global Constraints

- Backend (`CustomerInstallationController::storePemasangan()`, `storeSpeedtest()`, validasi, `TaskMaterialService`, `InventoryService`) **tidak boleh diubah** — field POST harus identik nama & bentuknya dengan sebelum task ini.
- Baris bermekanisme `modem` maksimal 1 aktif; baris bermekanisme `roll` maksimal 1 aktif (ditegakkan di Alpine, bukan server — server tetap menolak lewat `Rule::in()` yang sudah ada kalau dilanggar).
- Baris bermekanisme `pasif` tetap unlimited, format `materials[index][item_id|item_name|item_type|qty|unit|note]` identik `<x-material-rows restrict-to-custody>` sekarang.
- Jangan modifikasi `resources/views/components/material-rows.blade.php` — dipakai juga Laporan Survey, buat komponen baru terpisah.
- `vendor/bin/pint --dirty --format agent` sebelum commit tiap task yang menyentuh PHP.
- `npm run build` wajib dijalankan sebelum klaim selesai (CLAUDE.md: Alpine ikut bundle Vite).
- Jangan jalankan full test suite (`php artisan test` tanpa filter) — hanya file/filter yang terdampak.

## Review Focus

- **Baris Modem/Roll dipilih dobel di 2 baris berbeda** — Alpine harus exclude opsi yang sudah dipakai baris lain (pola `optionsFor()` di `_additional_devices_step.blade.php`), supaya tidak submit 2 `selected_inventory_serial_id` (browser cuma kirim value field terakhir dengan nama sama → silent data loss kalau tidak dicegah di UI).
- **Baris pasif dengan `item_id` sama di 2 baris** — behaviour existing (`<x-material-rows>`) sudah membiarkan ini (server yang menjumlah via `requestedQtyByItem` di `storePemasangan()`); komponen baru tidak boleh menyimpangkan ini jadi tiba-tiba terlarang di UI.
- **Buka ulang laporan yang sudah pernah disubmit (`$installation->selected_inventory_serial_id` sudah terisi)** — baris Modem/Roll harus prefill terisi, bukan kosong seolah belum pernah diisi.
- **SN/roll yang dulu tersimpan tapi sekarang sudah tidak ada di custody tim** (dicabut gudang, dipindah) — harus tetap tampil sebagai opsi "tersimpan" (pola `itemOptionsFor()` legacy-row di `material-rows.blade.php`), bukan hilang diam-diam dan bikin submit ulang menimpa field itu jadi kosong.
- **Teknisi kontrak `beli_sendiri`** — baris Modem di dropdown tetap bisa dipilih oleh UI baru (komponen tidak tahu soal `modem_acquisition_choice`), tapi server tetap menolak lewat `Rule::prohibitedIf()` existing kalau SN terisi — pastikan test existing yang menutup kasus ini (`GantiPaketBulanAktivasiTidakMenghapusBarisJualModemTest` atau sejenis) tetap lulus tanpa modifikasi, bukti bahwa UI baru tidak mengubah perilaku ini.

---

### Task 1: Komponen `installation-item-rows` (Blade + Alpine, murni presentasi)

**Files:**
- Create: `resources/views/components/installation-item-rows.blade.php`
- Reference (read-only, pola yang ditiru): `resources/views/components/material-rows.blade.php`, `resources/views/installations/partials/_additional_devices_step.blade.php`
- Test: tidak ada test PHPUnit baru untuk task ini (murni Blade/Alpine) — diverifikasi lewat Task 2 (integrasi) via test existing.

**Interfaces:**
- Consumes: props `serialOptions` (Collection<InventorySerial> dari `eligibleSerialsForTeam()`, tiap item punya `id`, `serial_number`, `item.name`, `item.brand`/`model` kalau ada), `rollOptions` (Collection<InventoryRoll> dari `eligibleRollsForTeam()`, tiap item punya `id`, `code`/`roll_code`, `length_remaining`, `item.name`), `custodyOptions` (array shape sama `eligiblePassiveCustodyForTeam()`: `{item_id, code, name, unit, type, available}`), `initialSerialId` (?int, prefill), `initialRollId` (?int, prefill), `initialRollMeters` (?float, prefill), `initialMaterialRows` (array, format sama `$rows` di `material-rows.blade.php`).
- Produces: markup yang merender input dengan `name` persis: `selected_inventory_serial_id`, `selected_inventory_roll_id`, `roll_meters_used`, `materials[{index}][item_id|item_name|item_type|qty|unit|note]` — dikonsumsi Task 2 saat dipasang ke `report.blade.php` menggantikan 3 picker lama. Tidak ada interface PHP/service baru.

- [ ] **Step 1: Baca referensi pola existing**

Baca `resources/views/components/material-rows.blade.php` (field Alpine `materialRows()`: `itemOptions`, `rows`, `addRow()`, `removeRow()`, `itemOptionsFor()` buat legacy-row fallback) dan `resources/views/installations/partials/_additional_devices_step.blade.php` (pola `optionsFor(index)` buat exclude opsi yang sudah dipakai baris lain). Tidak ada output — langkah pemahaman sebelum menulis komponen baru.

- [ ] **Step 2: Tulis struktur `@props` dan normalisasi 3 sumber data jadi satu `itemOptions` array di komponen `installation-item-rows.blade.php`**

Tiap opsi di `itemOptions` punya bentuk `{option_id, mechanism, label, unit, available}` di mana:
- `mechanism='modem'`: `option_id` = `InventorySerial->id`, `label` = `"{$serial->item->name} — SN {$serial->serial_number}"`, `available` = null (tidak ada qty).
- `mechanism='roll'`: `option_id` = `InventoryRoll->id`, `label` = `"{$roll->item->name} — Roll {$roll->code} (sisa {$roll->length_remaining}m)"`, `available` = `length_remaining`.
- `mechanism='pasif'`: `option_id` = `item_id` dari `$custodyOptions` row, `label` = `"{$row['code']} — {$row['name']} (sisa {$row['available']} {$row['unit']})"`, `available` = `row['available']`.

Field ini dikirim ke Alpine lewat `@js($itemOptions)`, sama pola `material-rows.blade.php` baris 97-106.

- [ ] **Step 3: Implementasi Alpine `installationItemRows(itemOptions, initialRows)` — fungsi baru di `<script>` komponen ini**

`initialRows` dibangun dari `initialSerialId`/`initialRollId`+`initialRollMeters`/`initialMaterialRows` (kalau ada nilainya, jadi baris awal; kalau semua kosong, `rows = []` sama seperti `<x-material-rows>` saat belum ada data). Method:
- `addRow()` — push baris kosong `{mechanism: '', option_id: '', qty: ''}`.
- `removeRow(index)` — `rows.splice(index, 1)`.
- `optionsFor(index)` — kembalikan `itemOptions` dikurangi opsi `mechanism IN ('modem','roll')` yang `option_id`-nya sudah dipakai baris lain (bukan baris `index` ini sendiri) — cegah 2 baris modem/roll aktif bersamaan. Opsi `mechanism='pasif'` TIDAK difilter dobel (boleh dipilih di >1 baris, sama perilaku `<x-material-rows>` sekarang).
- `onChoose(index, optionId)` — set `rows[index].option_id` dan `rows[index].mechanism` dari opsi yang dipilih (lookup di `itemOptions`), reset `qty`.

- [ ] **Step 4: Render `<template x-for>` per baris dengan `name` attribute kondisional sesuai `row.mechanism`**

```blade
<template x-if="row.mechanism === 'modem'">
    <input type="hidden" name="selected_inventory_serial_id" :value="row.option_id">
</template>
<template x-if="row.mechanism === 'roll'">
    <input type="hidden" name="selected_inventory_roll_id" :value="row.option_id">
    <input type="number" step="0.01" min="0.01" name="roll_meters_used" x-model="row.qty" :max="availableFor(row)">
</template>
<template x-if="row.mechanism === 'pasif'">
    <input type="hidden" :name="`materials[${index}][item_id]`" :value="row.option_id">
    <input type="number" step="0.01" min="0" :name="`materials[${index}][qty]`" x-model="row.qty" :max="availableFor(row)">
    {{-- item_name/item_type/unit/note: isi dari opsi terpilih, sama field tersembunyi seperti material-rows --}}
</template>
```

Dropdown pemilihan barang pakai `<x-combobox>` (sama komponen yang dipakai `material-rows.blade.php`) dengan `options-expr="() => optionsFor(index)"`, `on-select-expr="onChoose(index, value)"`.

- [ ] **Step 5: Tombol "+ Tambah Barang" dan render manual di browser**

Jalankan `npm run build` (atau `npm run dev` kalau sedang iterasi), buka halaman Laporan Pemasangan PSB (butuh task aktif dengan custody terisi — pakai data dev/seed yang sudah ada atau tinker buat set up custody kalau perlu), pastikan dropdown gabungan muncul, baris Modem/Roll hilang dari opsi baris lain setelah dipakai, baris Pasif tetap bisa dobel. Tidak ada assertion otomatis — catat hasil manual di laporan task (lihat §Testing di spec).

- [ ] **Step 6: `vendor/bin/pint --dirty --format agent`**

Jalankan kalau ada file PHP tersentuh (komponen Blade biasanya tidak perlu, tapi jalankan untuk jaga-jaga kalau ada file PHP pendukung).

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/installation-item-rows.blade.php
git commit -m "feat(installation): tambah komponen dropdown gabungan modem/roll/pasif"
```

---

### Task 2: Integrasi ke `report.blade.php` — ganti 3 picker lama

**Files:**
- Modify: `resources/views/installations/report.blade.php` (blok Step 5 "Alat" yang merender combobox SN modem, combobox roll, dan `<x-material-rows :custody-options="$eligiblePassiveCustody" restrict-to-custody />` — cari lewat grep `eligibleSerials|eligibleRolls|x-material-rows` di file ini sebelum mulai, baris pasti bisa berbeda dari perkiraan karena file ini besar dan sering berubah)
- Modify (kalau prefill butuh data tambahan yang belum dikirim view): `app/Http/Controllers/CustomerInstallationController.php` method `report()` — **hanya menambah variable yang dikirim ke view** (misal `$installation->selected_inventory_serial_id` kalau belum ada di scope), **tidak mengubah 3 method eligible*ForTeam() atau logic lain**.
- Test: tidak ada test baru — jalankan test existing yang submit ke `storePemasangan()` untuk pastikan field POST masih diterima identik.

**Interfaces:**
- Consumes: komponen `<x-installation-item-rows>` dari Task 1 (props: `serial-options`, `roll-options`, `custody-options`, `initial-serial-id`, `initial-roll-id`, `initial-roll-meters`, `initial-material-rows`).
- Produces: tidak ada interface baru untuk task lain — ini task integrasi akhir.

- [ ] **Step 1: Grep lokasi persis 3 picker lama di `report.blade.php`**

```bash
grep -n "eligibleSerials\|eligibleRolls\|x-material-rows" resources/views/installations/report.blade.php
```

Catat baris combobox SN modem, combobox roll, dan panggilan `<x-material-rows>` yang pakai `$eligiblePassiveCustody` (bukan yang dipakai Survey/estimasi — pastikan konteksnya Step 5 Pemasangan).

- [ ] **Step 2: Ganti blok yang ditemukan Step 1 dengan satu panggilan komponen baru**

```blade
<x-installation-item-rows
    :serial-options="$eligibleSerials"
    :roll-options="$eligibleRolls"
    :custody-options="$eligiblePassiveCustody"
    :initial-serial-id="$installation->selected_inventory_serial_id"
    :initial-roll-id="$installation->selected_inventory_roll_id"
    :initial-roll-meters="$installation->roll_meters_used"
    :initial-material-rows="$existingUsage"
/>
```

`$existingUsage` sudah ada di `report()` (lihat `CustomerInstallationController.php:443-445`) — pastikan bentuknya cocok dengan format `$rows` yang diharapkan Task 1 Step 2 (`item_id`, `item_name`, `item_type`, `qty`, `unit`, `note`); kalau beda bentuk (misal `TaskMaterial` model vs array), tambahkan `->map()` tipis di `report()` sebelum dikirim ke view, jangan ubah `$existingUsage` punya pemakai lain.

- [ ] **Step 3: Hapus markup lama yang sudah diganti**

Pastikan tidak ada sisa `<select name="selected_inventory_serial_id">`/combobox lama yang nempel ganda (dua field dengan `name` sama akan konflik submit).

- [ ] **Step 4: Jalankan test existing yang menyentuh Form 1 submit**

```bash
docker compose exec -u www-data app php artisan test --compact tests/Feature/CustomerInstallationTest.php tests/Feature/InstallationRollConsumptionTest.php tests/Feature/MaterialReportFlowTest.php
```

(sesuaikan path test sesuai nama file sebenarnya di repo — grep `storePemasangan` di `tests/Feature/*.php` untuk daftar lengkap kalau 3 nama di atas tidak persis). Expected: SEMUA PASS tanpa modifikasi apa pun di file test — ini bukti backend tidak berubah.

- [ ] **Step 5: `npm run build`**

Wajib — Alpine ikut bundle Vite, tanpa ini interaksi dropdown baru mati di browser.

- [ ] **Step 6: Verifikasi manual di browser**

Buka Laporan Pemasangan untuk task PSB yang punya custody modem+roll+pasif (pakai data dev existing atau siapkan lewat seeder/tinker read-only query, jangan ubah data produksi), isi dropdown gabungan, submit, konfirmasi `storePemasangan()` menyimpan seperti biasa (cek redirect sukses + data tersimpan tampil saat reload halaman).

- [ ] **Step 7: `vendor/bin/pint --dirty --format agent`**

- [ ] **Step 8: Commit**

```bash
git add resources/views/installations/report.blade.php app/Http/Controllers/CustomerInstallationController.php
git commit -m "feat(installation): pasang dropdown gabungan modem/roll/pasif di Form Pemasangan"
```

---

### Task 3: Regresi penuh area terdampak + bersihkan markup mati

**Files:**
- Modify: `resources/views/installations/report.blade.php` (hapus CSS/Alpine helper lama yang cuma dipakai 3 picker lama, kalau ada dan tidak dipakai bagian lain halaman)
- Test: jalankan suite test instalasi lebih luas (bukan full suite global).

**Interfaces:**
- Consumes: hasil Task 1 & 2 sudah terpasang.
- Produces: tidak ada — task penutup.

- [ ] **Step 1: Jalankan seluruh test file terkait instalasi/material yang disentuh scope ini**

```bash
docker compose exec -u www-data app php artisan test --compact --filter=Installation
docker compose exec -u www-data app php artisan test --compact --filter=Material
```

Expected: semua PASS. Kalau ada test gagal, itu sinyal scope sudah melebar ke backend (lihat Global Constraints) — STOP, jangan ubah test untuk "menyesuaikan", laporkan ke user dulu.

- [ ] **Step 2: Grep sisa referensi ke markup lama yang sudah tidak terpakai**

```bash
grep -n "eligibleExtraDeviceSerials\|selected_inventory_serial_id\|selected_inventory_roll_id" resources/views/installations/report.blade.php
```

Pastikan tidak ada `<select>`/`<input>` lama dengan `name` yang sama masih nempel di luar komponen baru (duplikat nama field bikin browser cuma kirim satu nilai, silent bug).

- [ ] **Step 3: `vendor/bin/pint --dirty --format agent`, commit kalau ada perubahan**

```bash
git add resources/views/installations/report.blade.php
git commit -m "chore(installation): bersihkan markup picker lama yang sudah diganti"
```

(skip commit ini kalau Step 2 tidak menemukan apa-apa untuk dibersihkan).
