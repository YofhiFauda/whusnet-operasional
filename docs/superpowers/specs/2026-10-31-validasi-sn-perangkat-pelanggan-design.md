# Rancangan: Validasi SN Perangkat & Sinkron `ont_sn` di Edit Pelanggan

Tanggal: 2026-10-31 · Status: **diimplementasikan (2026-10-31)** · Path: bounded-plus (tulis spec atas permintaan user)

> Catatan implementasi — penyimpangan dari rancangan awal:
> - Aturan A/B diperluas: SN yang sama dengan **perangkat tambahan** (`customer_additional_devices`, milik siapa pun) dan AP/STB Gudang terpasang juga **ditolak** sebagai modem utama.
> - Ditambah `checkAdditional()` untuk arah sebaliknya (modal Tambah Perangkat Tambahan).
> - Keputusan §6 (pesan menyebut ID + nama lintas cabang) dan aturan C (menolak) diterapkan sesuai rekomendasi; belum dijawab eksplisit oleh user.
> - Dokumentasi hasil: `docs/data-pelanggan/README.md` (Edit Pelanggan, butir 8) dan `docs/task-teknisi/business-logic.md` §7c.

## 1. Latar belakang & tujuan

Pelanggan lama sering punya modem yang tidak sesuai dengan pembukuan. Admin mengoreksinya lewat
Edit Pelanggan → step 7 "Parameter Teknis" → "Informasi Perangkat Aktif"
(`CustomerController::update()`, validasi `:1007-1011`, tulis `:1115-1145`).

Dua masalah di jalur itu sekarang:

1. **SN bisa ganda.** `customer_devices.serial_number` hanya `nullable string`, tanpa unique index
   dan tanpa rule unik di validasi. Dua pelanggan bisa memegang SN yang sama; SN yang sedang
   `installed` di pelanggan lain atau tercatat di gudang juga lolos tanpa tanda apa pun.
2. **SN tersimpan di tiga kolom, Edit hanya menulis satu.**

   | Kolom | Penulis saat ini |
   |---|---|
   | `customer_devices.serial_number` | Edit, Laporan Pemasangan |
   | `customer_technical_details.router_or_ont_serial` | Pemasangan teknisi, `InventoryService` |
   | `customers.ont_sn` | `InventoryService`, import/migrasi legacy |

   Setelah Edit, dua kolom lain tetap berisi nilai lama. Pembaca yang masih memakai kolom itu
   (mis. `CustomerController.php:1538` "SN ONT", `InfrastructureMapService.php:682-684`,
   `Customer.php:496-516`) menampilkan SN lama.

**Sukses =** (a) SN yang sudah dipakai pelanggan lain atau sedang terpasang di pelanggan lain
ditolak dengan pesan jelas; (b) SN yang tercatat di gudang memunculkan peringatan, tidak
memblokir; (c) setelah Edit, ketiga kolom SN berisi nilai yang sama.

**Bukan tujuan:** mengubah status/stok `inventory_serials` dari Edit; unique index di DB
(data legacy hampir pasti sudah punya duplikat); membersihkan duplikat lama.

## 2. Temuan yang membentuk rancangan

- Status serial gudang adalah enum `SerialStatus` (`received, available, reserved, issued,
  in_use, installed, transferred, returned, damaged, lost, scrapped, quarantine, deployed`).
  Kolom `inventory_serials.customer_id` menunjuk pelanggan pemegangnya.
- `CustomerDeviceProfileService::resolve()` (`:129`) memilih SN tampilan dengan urutan:
  **serial gudang `installed` → `customer_devices` → `router_or_ont_serial` → `ont_sn`**.
  Artinya: jika pelanggan punya serial gudang `installed` (perangkat utama), mengubah SN lewat
  Edit **tidak akan terlihat** di Detail/prefill Edit. Perubahan diam-diam yang tak berefek
  itu lebih buruk daripada ditolak, jadi dicegah (lihat aturan C).
- Prefill Edit memakai `resolve()` yang sama, jadi form Edit memuat SN efektif. Menyimpan form
  tanpa mengubah SN tidak boleh terkena validasi baru (aturan D).
- Data legacy kemungkinan sudah mengandung duplikat. Validasi hanya boleh bereaksi pada
  **perubahan** SN, kalau tidak, Edit pelanggan-pelanggan itu terblokir untuk alasan yang
  tidak mereka sentuh.

## 3. Aturan

SN dinormalisasi sebelum dibandingkan: `trim` + huruf besar (perbandingan di DB pakai `UPPER()`).

| # | Kondisi (hanya jika SN **berubah** dari nilai tersimpan, dan tidak kosong) | Hasil |
|---|---|---|
| A | SN sama ada di `customer_devices` milik **pelanggan lain** | **Tolak** — "SN sudah terdaftar di pelanggan {display_id} – {nama}" |
| B | SN ada di `inventory_serials` berstatus `installed`/`deployed` dengan `customer_id` pelanggan **lain** | **Tolak** — pesan sama, sebut pelanggan pemegang |
| C | Pelanggan ini punya serial gudang `installed` (perangkat utama, bukan perangkat tambahan) dan SN input ≠ SN serial itu | **Tolak** — "Perangkat utama dikelola Gudang; ganti lewat Penggantian Modem/retur" |
| W | SN ada di `inventory_serials` dengan status lain (mis. `available`, `issued`, `scrapped`) dan bukan milik pelanggan ini | **Lolos + peringatan** (flash) — "SN tercatat di Gudang berstatus {status}; stok Gudang tidak berubah" |
| D | SN tidak berubah, atau input kosong | Tidak divalidasi |

Perangkat tambahan (`is_customer_extra_device`) dikecualikan dari aturan C, selaras dengan
`resolve()` `:102`.

## 4. Sinkron kolom

Di dalam `DB::transaction` yang sama dengan penulisan `customer_devices` (`:1095`), hanya bila
key `serial_number` ada di `$validated` (field tidak terkirim = tidak disentuh, sama dengan
aturan `$deviceFields` sekarang):

- `customers.ont_sn` ← SN (atau `null` bila dikosongkan)
- `customer_technical_details.router_or_ont_serial` ← SN (atau `null`) — **hanya bila baris
  detail teknis sudah ada**; tidak membuat baris baru (kolom wajib lain bisa gagal)

Keputusan: dikosongkan di Edit → ketiga kolom ikut dikosongkan (disetujui user).

Catatan: `ont_sn` sengaja dicabut dari *validasi* Edit (`:987-998`) supaya tidak ter-null
diam-diam. Sinkron ini menulis lewat `$customer->update([...])` terpisah di dalam transaksi,
bukan lewat `$validated`, jadi guard itu tidak terlanggar.

## 5. Komponen

- **`app/Services/CustomerDeviceSerialGuard.php`** (baru) — satu tanggung jawab: diberi
  `Customer` + SN input, kembalikan `{blocking: ?string, warning: ?string}`. Tidak menulis
  apa pun. Mengikuti aturan repo "logic di Service".
  - `check(Customer $customer, ?string $serial): array`
  - `normalize(?string $serial): ?string`
- **`CustomerController::update()`** — (1) panggil guard setelah `validate()`; jika `blocking`
  → `back()->withErrors(['serial_number' => …])->withInput()` (pola yang sudah dipakai untuk
  error validasi); (2) tambahkan sinkron §4 di transaksi; (3) `warning` → `with('warning', …)`
  pada redirect PRG ke `customers.show`.
- **View** — tampilkan flash `warning` di halaman Detail bila belum ada komponen flash
  serupa (cek `resources/views/customers/show` dulu; pakai komponen yang ada).
- **Tanpa migrasi, tanpa route baru, tanpa permission baru** (tetap `customers.update`).

## 6. Scope & POP

Pencarian SN pelanggan lain **lintas cabang sengaja tanpa filter POP scope**: tujuannya
mendeteksi tabrakan global, dan hanya `display_id` + nama yang dibocorkan lewat pesan error.
Ini pengecualian sadar dari aturan "query pelanggan wajib lewat POP scope" (CLAUDE.md,
larangan #3). **Perlu persetujuan user** — alternatifnya pesan generik "SN sudah dipakai
pelanggan lain" tanpa nama/ID bila pemegangnya di luar scope user.

## 7. Pengujian (PHPUnit, `tests/Feature/CustomerEditSerialNumberTest.php`)

Pola mengikuti `CustomerEditTest::test_customer_edit_saves_structured_device_and_technical_detail`.

1. SN baru unik → tersimpan; `ont_sn` dan `router_or_ont_serial` ikut sama.
2. SN dipakai `customer_devices` pelanggan lain → 302 back + error `serial_number`; DB tak berubah.
3. SN berbeda huruf besar/kecil + spasi dari milik pelanggan lain → tetap ditolak.
4. SN `installed` di pelanggan lain (gudang) → ditolak.
5. SN `available` di gudang → lolos, flash `warning`, status serial gudang tidak berubah.
6. Pelanggan punya serial gudang `installed`, input SN lain → ditolak (aturan C).
7. Pelanggan punya serial gudang `installed`, input SN sama (resubmit form) → lolos.
8. Legacy: dua pelanggan sudah berSN sama; edit nama salah satu tanpa ubah SN → lolos (aturan D).
9. SN dikosongkan → ketiga kolom `null`.
10. Field `serial_number` tidak dikirim → tiga kolom tidak berubah.
11. Baris `customer_technical_details` belum ada → tidak dibuat; `ont_sn` tetap tersinkron.

Jalankan hanya: `php artisan test --compact tests/Feature/CustomerEditSerialNumberTest.php`
dan `tests/Feature/CustomerEditTest.php` (regresi). Bukan full suite.

## 8. Risiko

- **Aturan C bisa menghalangi koreksi sah** pada pelanggan yang perangkat gudangnya salah
  dicatat. Jalan keluarnya lewat alur gudang (retur/ganti), bukan Edit — sengaja, agar stok
  dan data pelanggan tidak menyimpang.
- **Duplikat legacy tetap ada** sampai ada pembersihan terpisah; di luar scope.
- Dua penulis ke tabel yang sama (Edit dan Laporan Pemasangan) tetap ada (sudah dicatat di
  komentar `:1105-1108`); tidak diubah di sini.

## 9. Di luar scope

Unique index DB; laporan/command pembersih duplikat SN; menautkan SN Edit ke `inventory_serials`
(mengubah status jadi `installed`); perubahan alur Laporan Pemasangan.

## 10. Langkah berikutnya

Setelah spec disetujui → `superpowers:writing-plans`, lalu implementasi TDD, `vendor/bin/pint`,
update `docs/TASKS.md`.
