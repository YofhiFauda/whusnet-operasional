# Rancangan: Perangkat Tambahan per Paket (Bisnis & lainnya)

Status: draft — menunggu review user.
Tanggal: 2026-10-15

## 1. Latar Belakang & Masalah

Paket tertentu (mis. Bisnis) kasih benefit perangkat lebih dari 1 — ONT/modem
utama + perangkat tambahan (AP, STB, extender, dll). Sistem sekarang cuma
nyatet 1 perangkat per pelanggan:

- `customers.customerDevice()` = `hasOne` ke `CustomerDevice` (tabel
  `customer_devices`, kolom `customer_id` **tanpa** unique constraint di DB —
  limit 1 itu murni di layer relasi Eloquent + UI, bukan skema).
- `CustomerDeviceProfileService::resolve()`/`editPrefill()` = SATU sumber
  kebenaran device utama (PPPOE, ODP, VLAN, WiFi, OLT, redaman, dll),
  dipakai di ~43 titik: tab Perangkat Detail, Edit Pelanggan step 7, FOP,
  Task Teknisi, retrieval alat, webhook instalasi, dst.

## 2. Keputusan Desain Inti

**Jangan ubah `customerDevice` jadi `hasMany`.** Device utama tetap 1 row
seperti sekarang — supaya 43 titik existing yang baca
`CustomerDeviceProfileService` sama sekali tidak kesentuh.

Perangkat tambahan jadi **entity baru yang terpisah**, dengan field jauh
lebih sedikit, karena field jaringan (PPPOE, ODP, VLAN, WiFi) sudah
diwariskan dari device utama — sesuai arahan user: "PPPOE hanya mencatat 1
device utama; tambahan cuma SN, MAC, dll".

## 3. Data Model

### 3.1 `internet_packages` — kolom baru

```
max_additional_devices  unsignedInteger  default 0  nullable: false
```

Default `0` → semua paket existing otomatis "tidak boleh ada perangkat
tambahan", perilaku lama tidak berubah. Admin isi manual di Master Paket
Internet buat paket yang punya benefit ini (mis. paket Bisnis → 2 atau 3).

### 3.2 Tabel baru `customer_additional_devices`

```
id
customer_id       foreignId → customers, cascadeOnDelete
device_type       string            -- AP, STB, Extender, dll (bebas teks,
                                        atau pakai ItemCategory existing?
                                        lihat Open Question #1)
brand             string nullable
model             string nullable
serial_number     string nullable
mac_address       string nullable
technical_note    text nullable
created_at / updated_at
```

**Sengaja TIDAK ada**: `pppoe_username/password`, `wifi_ssid/password`,
`vlan_id`, `odp`/`odp_port`, `signal_rx_power`, `connection_mode` — semua itu
milik device utama (satu jalur PPPOE ke pelanggan), perangkat tambahan cuma
numpang jaringan yang sama.

Model `CustomerAdditionalDevice`, relasi `Customer::additionalDevices():
HasMany`. Ikut `RecordsAuditLogs` (konsisten `CustomerDevice`).

### 3.3 Kenapa tabel baru, bukan nullable-in field di `customer_devices`

Device utama & tambahan punya shape beda (field jaringan vs tidak) dan
cardinality beda (1 vs N-dibatasi-paket). Nyampur di satu tabel bikin
`customer_devices` punya kolom yang cuma valid buat baris "utama" —
ambigu tiap kali ada query/tampilan baru. Tabel pisah = boundary jelas,
sesuai prinsip "unit lebih kecil, satu tanggung jawab".

## 4. Alur & UI

**Lokasi input: Tab Perangkat di Detail Pelanggan** (bukan Edit Pelanggan
step 7). Alasan:
- Device tambahan itu list dengan jumlah variabel, bukan field form tetap
  — gak natural dipaksa ke step wizard Edit yang linear.
- Tab Perangkat sudah punya pola "tombol aksi → modal" buat device utama
  (`openDeviceModal()`). Tambah/edit/hapus device tambahan pakai pola
  serupa: modal kecil per device (form singkat: jenis, merk, model, SN,
  MAC, catatan) — konsisten pola 1 (view-only → modal) digabung mutasi
  ringan tanpa validasi majemuk, selaras cara tab ini sudah bekerja.
- Permission dipakai ulang: `customers.detail.devices.update` (nambah/
  ubah/hapus device tambahan), `customers.detail.devices.view_sensitive`
  tidak relevan (device tambahan tidak punya field sensitif seperti
  PPPOE password).

**Validasi batas jumlah**: saat tambah device baru, cek
`customer->additionalDevices()->count() < customer->activePackage->max_additional_devices`
(ambil paket aktif dari `customer_services`/service aktif pelanggan —
pola ambil paket aktif ikut yang sudah ada di `CustomerService`). Kalau
sudah penuh, tombol "Tambah Perangkat" nonaktif + pesan "Sudah mencapai
batas N perangkat tambahan untuk paket ini".

**Pindah/ganti paket ke paket dengan limit lebih kecil**: kalau pelanggan
punya device tambahan lebih banyak dari limit paket baru, pindah paket
**ditolak** sampai admin hapus device tambahan sampai pas limit — pola
guard ini konsisten dengan "Pindah Cabang wajib lunas piutang dulu"
(`CustomerRelocationService`): satu sumber aturan, ditegakkan di tempat
ganti paket terjadi (observer atau service ganti paket — perlu
diidentifikasi titik tepatnya di tahap plan, lihat Open Question #2).

## 5. Dampak ke Kode Existing

Minimal — ini keuntungan utama desain tabel-pisah:
- **Tidak ada perubahan** ke `CustomerDeviceProfileService`,
  `CustomerDevice`, atau 43 titik yang baca `customerDevice`.
- **Tidak ada perubahan** ke FopTask/Task/retrieval alat — device tambahan
  di luar flow itu untuk v1 (lihat §6 scope).
- Perubahan terbatas ke: migration baru (kolom paket + tabel baru), model
  baru, service baru (atau method baru di service ganti paket utk guard
  limit), view tab Perangkat (tambah section + modal), 1 controller baru
  (CRUD device tambahan, resource routes nested di customer).

## 6. Scope v1 — yang SENGAJA tidak dikerjakan

- Device tambahan **tidak** terhubung Inventori Gudang (tidak decrement
  stok, tidak pakai `InventorySerial`). Pencatatan manual teks saja,
  seperti field SN device utama versi lama sebelum terhubung Gudang.
- Device tambahan **tidak** masuk flow "Ambil Alat"/`DeviceRetrievalLog`
  saat pelanggan putus langganan. Kalau nanti dibutuhkan, itu task
  susulan terpisah — jangan dipaksa masuk v1.
- Device tambahan **tidak** tampil/diisi dari Task Teknisi (laporan
  instalasi/maintenance). Teknisi tetap hanya melapor device utama.

Kalau salah satu di atas ternyata dibutuhkan sekarang, itu tanda scope
harus naik — angkat sebelum masuk plan, jangan ditambah diam-diam saat
implementasi.

## 7. Testing

- Feature test: tambah device tambahan sampai limit paket → OK; nambah
  lagi di atas limit → ditolak dengan pesan jelas.
- Feature test: ganti paket pelanggan ke paket dengan limit lebih kecil
  saat device tambahan masih lebih banyak → ditolak.
- Feature test: hapus device tambahan → `recalculateCompleteness()` tidak
  wajib ikut (device tambahan bukan syarat "Siap Billing" — konfirmasi di
  Open Question #3 kalau ternyata perlu).
- Permission test: user tanpa `customers.detail.devices.update` tidak bisa
  tambah/ubah/hapus.

## 8. Keputusan atas Open Questions (resolved)

1. **`device_type`**: teks bebas (string), sama persis pola
   `customer_devices.device_type` — dropdown pilihan standar (AP, STB,
   Extender, Lainnya) tapi kolom DB tetap string, bukan FK ke
   `ItemCategory`. `ItemCategory` dikhususkan buat material pasif Gudang
   (barang stok bernomor SKU); device tambahan di sini bukan barang
   Gudang, jadi beda konteks — jangan dipaksa satu master.
2. **Titik guard ganti paket**: `CustomerPackageService::change()`
   (`app/Services/CustomerPackageService.php:55`). Tambah assert BARU
   (pola sama seperti `assertNoOutstandingPreviousPeriod()`) sebelum
   `applyPackageToService()` dipanggil — kalau
   `customer->additionalDevices()->count() > $newPackage->max_additional_devices`,
   lempar `RuntimeException` dengan pesan jelas suruh admin hapus device
   tambahan dulu. Penetapan paket PERTAMA KALI (form Edit Pelanggan, belum
   lewat `CustomerPackageService`) tidak butuh guard tambahan — device
   tambahan baru bisa diisi setelah pelanggan ada & punya layanan, jadi
   saat assignment pertama hitungnya selalu 0.
3. **Kelengkapan data**: murni informasional — TIDAK mempengaruhi
   `recalculateCompleteness()` / syarat "Siap Billing". Device tambahan
   itu benefit tambahan, bukan syarat aktivasi.
