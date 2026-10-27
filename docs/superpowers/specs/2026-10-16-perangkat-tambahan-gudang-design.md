# Rancangan: Perangkat Tambahan dari Gudang (Issue, Pemasangan, Putus Langganan)

Status: disetujui user, semua pertanyaan terbuka sudah diputuskan (2026-10-17), siap masuk tahap rencana implementasi (`writing-plans`).
Tanggal: 2026-10-16
Sprint: ADHOC-190 (Ad-Hoc Improvement baru — lihat `docs/TASKS.md`)
Lanjutan dari: `docs/superpowers/specs/2026-10-15-perangkat-tambahan-paket-design.md` (v1, free-text, tidak terhubung Gudang) dan analisa `docs/superpowers/specs/2026-10-16-analisa-perangkat-tambahan-gudang-dan-laporan.md`.

## 1. Tujuan

Perangkat tambahan (AP, STB, dan sejenisnya) milik kantor, disewakan ke pelanggan sebagai bagian dari paket. Perangkat itu:
- tercatat di stok Gudang (SN, lokasi, siapa yang pegang),
- dibawa teknisi dari Gudang lewat satu form,
- dipilih dari daftar bawaan saat laporan pemasangan (tanpa ketik SN ulang),
- ditarik kembali ke kantor saat pelanggan putus langganan.

## 2. Keputusan (hasil diskusi 2026-10-16)

| Topik | Keputusan |
|---|---|
| Sumber perangkat tambahan | Milik kantor, terhubung Gudang penuh (opsi A). Free-text tidak dipakai untuk perangkat baru. |
| Wajib/opsional di laporan pemasangan | Wajib kalau paket punya `max_additional_devices > 0` — teknisi wajib melaporkan status (dipasang / kembali Gudang / rusak / hilang) untuk **setiap perangkat yang diatur di paket itu**, bukan cuma "minimal satu". Kalau paket limitnya 0, bagian ini opsional/tidak tampil. |
| Perangkat dibawa tapi tidak terpasang | Teknisi wajib memilih status akhir: kembali ke Gudang, rusak, atau hilang. |
| Permission | Pakai permission yang sudah ada: `customers.detail.devices.create` dan `customers.detail.devices.update`. Tidak ada permission baru. |
| Putus langganan | Perangkat tambahan wajib ditarik. Ikut alur "Ambil Alat" (DEAC), diperluas dari perangkat utama ke semua perangkat tambahan. |
| Kontrol kecukupan barang | **Revisi 2026-10-20:** FOP boleh assign/jadwalkan Task Pemasangan sebelum Gudang issue perangkat tambahan. Issue dilakukan sebelum perangkat dipasang; laporan hanya menerima SN yang benar-benar berstatus ISSUED pada custody tim dan sesuai cabang. Jika jumlah keputusan kurang dari batas paket, teknisi harus memakai jalur lapor kekurangan dengan alasan wajib. |
| Langkah di Laporan Pemasangan | Langkah sendiri, terpisah dari Material Terpakai (ADHOC-165). Alasan: Material Terpakai = barang konsumsi, perangkat tambahan = barang ber-SN yang statusnya dilacak individual — beda logic wajib/opsional, nyampur bikin ambigu. |
| Nama penanda kategori | Tetap `is_customer_extra_device`. Diganti hanya kalau muncul istilah baku kantor yang lebih cocok. |

## 3. Data model

### 3.1 Penanda perangkat tambahan di kategori barang
`item_categories` mendapat kolom `is_customer_extra_device` (boolean, default false). Kategori AP dan STB diset true.

Alasan: `CustomerDeviceProfileService::resolve()` mengambil `InventorySerial` yang terpasang ke pelanggan sebagai perangkat UTAMA. Tanpa penanda ini, AP yang dipasang lewat Gudang akan tampil sebagai perangkat utama di Detail Pelanggan. Penanda dipakai untuk memisahkan keduanya.

### 3.2 Tautan perangkat tambahan ke stok
`customer_additional_devices` mendapat kolom:
- `inventory_serial_id` (FK nullable ke `inventory_serials`, nullOnDelete). Perangkat baru wajib punya ini.
- `status` (string): `terpasang`, `ditarik`. Dipakai untuk melacak penarikan per perangkat.
- `retrieved_at` (timestamp nullable).

Baris lama dari v1 (free-text, `inventory_serial_id` null) dibiarkan. Tidak dimigrasi otomatis. Tetap tampil di Detail dengan label "tanpa stok" supaya datanya tidak hilang.

## 4. Alur

### 4.1 Assign FOP lebih dulu, lalu issue dari Gudang
- Gudang membuat issue ke teknisi seperti biasa, lewat `InventoryIssueService`.
- Barang perangkat tambahan (kategori `is_customer_extra_device = true`) ikut dipilih di form yang sama dengan modem, kabel, dan pasif.
- Tidak ada form baru. Hanya kategori baru yang ikut daftar.
- FOP dapat assign/jadwalkan atau mengganti tim Task Pemasangan meskipun custody perangkat tambahan tim masih kosong atau kurang. Ini memungkinkan penugasan teknisi dilakukan sebelum Gudang menyerahkan barang.
- Gudang tetap meng-issue perangkat tambahan ke teknisi melalui form issue yang ada sebelum pemasangan fisik. Stok kosong tidak membatalkan penugasan; kekurangan harus ditindaklanjuti dan tercatat bila pekerjaan dilaporkan belum lengkap.

### 4.2 Laporan Pemasangan
- Di langkah laporan yang sesuai (lihat catatan §8), sistem menampilkan daftar perangkat tambahan yang sedang dibawa teknisi (custody).
- Teknisi memilih setiap perangkat yang dipasang. Untuk setiap perangkat yang dipilih, dibuat baris `customer_additional_devices` dengan `inventory_serial_id` dan status `terpasang`. `InventorySerial` ditandai `INSTALLED` ke pelanggan itu lewat `InventoryService::installSerial()`.
- Perangkat yang dibawa tapi tidak dipasang: teknisi memilih status akhir per barang (kembali ke Gudang, rusak, atau hilang). Keputusan ini dicatat, tidak dibiarkan menggantung.
- Validasi wajib: kalau `max_additional_devices` paket > 0, laporan **tidak bisa disubmit** sampai teknisi memberi keputusan (dipasang / kembali Gudang / rusak / hilang) untuk **setiap perangkat tambahan yang diatur di paket itu** — jumlahnya mengikuti `max_additional_devices`, bukan cuma "minimal satu dari yang dibawa". Kalau paket limitnya 0, bagian ini tidak tampil sama sekali (opsional).
- **Jalur kekurangan:** bila perangkat belum cukup saat laporan pemasangan, submit tanpa alasan tetap ditolak. Teknisi dapat mencatat kekurangan dengan alasan wajib; laporan ditandai `kekurangan_perangkat = true` untuk tindak lanjut FOP/Gudang. SN yang tidak di-issue ke tim tidak pernah boleh dicatat terpasang.

### 4.3 Putus langganan (DEAC)
- Alur "Ambil Alat" yang sudah ada tetap dipakai.
- Daftar yang ditampilkan ke teknisi bukan hanya perangkat utama, tetapi semua `customer_additional_devices` dengan status `terpasang` milik pelanggan itu.
- Per perangkat, teknisi mencatat hasil penarikan. Saat dikembalikan, `InventorySerial` dipindah dari pelanggan kembali ke Gudang lewat service yang sudah ada, lalu baris `customer_additional_devices` diubah ke `ditarik` dengan `retrieved_at`.
- Perangkat yang tidak bisa ditarik (rusak atau hilang) juga dicatat, dengan alasan.

### 4.4 Tampilan
- Detail Pelanggan, tab Perangkat: bagian "Perangkat Tambahan" menampilkan SN dan status per perangkat. Perangkat dari Gudang diberi label sumber (Gudang), perangkat lama diberi label "tanpa stok".
- `CustomerDeviceProfileService::resolve()`: perangkat utama hanya mengambil `InventorySerial` yang kategorinya bukan `is_customer_extra_device`.

## 5. Tidak termasuk (scope luar)
- Perubahan form laporan selain langkah perangkat tambahan.
- Migrasi otomatis data free-text lama ke stok.
- Pelaporan Task Teknisi selain Laporan Pemasangan dan DEAC.
- Notifikasi ke Gudang saat teknisi melapor.

## 6. Dampak ke kode yang ada
- `ItemCategory` (kolom baru) + seeder kategori AP/STB.
- `InventoryIssueService` dan form issue Gudang: hanya menampilkan kategori baru, tanpa logika baru.
- `InventoryService::installSerial()`: dipakai ulang. Guard cabang (ADHOC-159) tetap berlaku.
- `CustomerInstallationController` (laporan pemasangan): langkah baru untuk perangkat tambahan, termasuk field `kekurangan_perangkat` + `kekurangan_perangkat_alasan`.
- Titik assign/jadwal Task Pemasangan (FOP): guard kecukupan custody lama dihapus dari create, edit, reassign, switch team, dan bulk assign. Aturan pemilihan SN di laporan tetap berlaku.
- Alur DEAC / `TaskDeviceRetrievalController` dan `DeviceRetrievalLog`: diperluas ke banyak perangkat.
- `CustomerDeviceProfileService::resolve()`: filter perangkat utama.
- `CustomerAdditionalDeviceController` (v1): store tetap ada untuk jalur lama, tapi perangkat baru wajib lewat stok.

## 7. Testing
- Issue: barang kategori tambahan muncul di form Gudang bersama kategori lain.
- Assign/jadwal Task Pemasangan: berhasil sebelum issue Gudang; issue setelah assign membuat SN muncul di laporan; reassign juga berhasil tanpa custody.
- Laporan pemasangan: paket limit > 0 memaksa keputusan untuk setiap perangkat yang dibawa. Paket limit 0 tidak memaksa.
- Laporan pemasangan — jalur kekurangan: submit ditolak tanpa alasan kalau jumlah kurang dari limit; submit lolos dengan alasan terisi dan `kekurangan_perangkat = true` tercatat.
- Perangkat yang dipasang: `InventorySerial` berstatus `INSTALLED` ke pelanggan, baris `customer_additional_devices` terbuat dengan `inventory_serial_id`.
- Perangkat yang tidak dipasang: status akhir tercatat, `InventorySerial` tidak ikut terpasang.
- Profile service: AP yang dipasang lewat Gudang tidak tampil sebagai perangkat utama.
- DEAC: semua perangkat tambahan `terpasang` muncul di daftar penarikan. Setelah ditarik, status `ditarik` dan `InventorySerial` kembali ke Gudang.
- Guard cabang: teknisi cabang lain tidak bisa memasang perangkat cabang asal lain.
- Regresi: `CustomerAdditionalDeviceTest`, `GantiPaketBulanAktivasiTidakMenghapusBarisJualModemTest`, `CustomerEditTest`, `VerificationInlineEditTest`, dan test alur DEAC yang ada.

## 8. Keputusan user — semua pertanyaan terbuka sudah dijawab (2026-10-16 s/d 2026-10-17)

**Wajib/opsional** — kalau paket punya perangkat tambahan yang diatur (`max_additional_devices > 0`), teknisi **wajib** melaporkan status untuk setiap perangkat yang diatur di paket itu (bukan cuma minimal satu dari yang dibawa). Kalau paket tidak punya perangkat tambahan, bagian ini opsional/tidak tampil. Lihat §4.2.

**Sprint** — masuk sebagai Ad-Hoc Improvement baru, **ADHOC-190**, dicatat di `docs/TASKS.md`. Tidak menunggu sprint S8.10-T003 selesai.

**Kekurangan jumlah — revisi 2026-10-20:** FOP boleh assign/jadwalkan Task Pemasangan sebelum Gudang issue. Kontrol SN berlaku saat laporan pemasangan: hanya custody tim yang dapat dipilih dan dipasang. Jumlah kurang dari batas paket memerlukan alasan pada jalur lapor kekurangan.

**Langkah laporan pemasangan** — langkah sendiri, terpisah dari Material Terpakai (ADHOC-165), karena beda logic wajib/opsional dan beda jenis barang (konsumsi vs ber-SN individual).

**Nama penanda** `is_customer_extra_device` — tetap dipakai. Tidak ada istilah baku kantor yang lebih cocok saat ini.

Tidak ada pertanyaan terbuka yang tersisa. Siap lanjut ke `superpowers:writing-plans`.
