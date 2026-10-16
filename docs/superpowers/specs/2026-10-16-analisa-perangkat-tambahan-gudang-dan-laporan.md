# Analisa: Perangkat Tambahan — Input Gudang & Laporan Pemasangan

Status: usulan, belum dirancang detail.
Tanggal: 2026-10-16
Terkait: `docs/superpowers/specs/2026-10-15-perangkat-tambahan-paket-design.md` (v1 sengaja tidak terhubung Gudang dan laporan teknisi, lihat §6).

## 1. Masalah

- Teknisi harus mengisi perangkat dua kali: saat mengambil barang di Gudang (issue), lalu lagi saat Laporan Pemasangan (SN/MAC diketik ulang).
- Laporan sering terlewat karena kondisi lapangan: panas, bolak-balik, lupa. Ini alasan operasional, bukan sekadar preferensi sistem.
- Akibatnya data perangkat tambahan kosong atau tidak akurat.

## 2. Kondisi sekarang

- Modem/ONT, kabel, dan perangkat pasif keluar dari Gudang sebagai stok (`InventorySerial` untuk barang ber-SN, roll/qty untuk material). Pemasangan lewat `InventoryService::installSerial()`, `consumeFromCustody()`, dan `consumeFromRoll()`. Guard lintas cabang berlaku di titik pemakaian (ADHOC-159).
- Perangkat tambahan (AP/STB) di v1 berupa teks bebas di `customer_additional_devices`, tidak terhubung ke Gudang.

## 3. Usulan: satu form input dari Gudang

Gudang memberi barang ke teknisi lewat satu form. Di form itu gudang memilih semua barang yang dibawa teknisi, dari kategori apa pun (modem, kabel, pasif, AP, STB, dan lainnya).

Saat Laporan Pemasangan, teknisi memilih perangkat dari daftar barang yang dibawanya (custody), tidak mengetik SN ulang. Yang tidak terpasang dikembalikan ke custody.

Kelebihan:
- SN cukup diinput sekali, di Gudang.
- Kalau teknisi lupa melapor, data barang sudah ada dari saat issue. Laporan tinggal konfirmasi pemasangan ke pelanggan mana.
- Konsisten dengan guard cabang dan jejak stok yang sudah ada.

Risiko dan catatan:
- Barang yang dibawa belum tentu terpasang. Laporan harus bisa mencatat barang yang kembali ke custody. Polanya sudah ada untuk material (`consumeFromRoll`), perlu dicek apakah bisa dipakai juga untuk barang ber-SN.
- Perangkat tambahan harus menjadi item stok ber-SN agar bisa dipilih. Itu butuh `ItemCategory` dan SKU. Untuk barang milik pelanggan (bawa sendiri), jalur teks bebas tetap diperlukan.
- Risiko konflik data (perlu dicek sebelum build): `installSerial()` menandai `InventorySerial` sebagai `INSTALLED` dengan `customer_id`. `CustomerDeviceProfileService::resolve()` mengambil `installedInventorySerial` terbaru sebagai perangkat UTAMA. Akibatnya AP yang dipasang lewat Gudang bisa tampil sebagai perangkat utama di Detail Pelanggan. Ini harus dibedakan (misalnya lewat kategori item atau flag peran) sebelum fitur ini jalan.

## 4. Opsi

A. Terhubung Gudang penuh. Perangkat tambahan adalah `InventorySerial` yang terpasang ke pelanggan dan dipilih dari custody. Paling konsisten, tetapi cakupannya besar: `InventoryService`, `CustomerDeviceProfileService`, form Gudang, dan Laporan Pemasangan.

B. Teks bebas dengan field di Laporan Pemasangan (opsional). Cepat dibuat, tetapi SN tetap diketik dua kali jika barangnya dari Gudang, dan stok tidak tercatat.

C. Hybrid. Barang milik kantor lewat form Gudang (opsi A). Barang milik pelanggan lewat teks bebas (opsi B).

Rekomendasi: C untuk sprint berikutnya. Kalau perangkat tambahan selalu milik kantor, pilih A.

## 5. Keputusan & pertanyaan terbuka

Keputusan (2026-10-16): perangkat tambahan adalah milik kantor, disewakan ke pelanggan, termasuk dalam paket. Maka pakai **opsi A** (terhubung Gudang penuh). Opsi B dan C tidak dipakai untuk perangkat tambahan.

Contoh alur (opsi A):
1. Gudang memberi AP ke teknisi lewat satu form, SN di-scan sekali.
2. Teknisi memasang AP di rumah pelanggan.
3. Di Laporan Pemasangan, teknisi pilih "AP ini terpasang" dari daftar barang yang dibawanya. Tidak ketik SN.
4. Yang tidak terpasang kembali ke teknisi/Gudang.
5. Detail Pelanggan menampilkan AP sebagai perangkat tambahan, bukan perangkat utama.

Putus langganan (2026-10-16): perangkat tambahan **wajib ditarik kembali** ke kantor. Ikut alur "Ambil Alat" (DEAC) yang sudah ada, tapi cakupannya harus diperluas dari perangkat utama ke semua perangkat tambahan. Status tarik per perangkat perlu dicatat, tidak cukup satu flag per pelanggan.

Pertanyaan terbuka:

1. Alur "Ambil Alat" yang ada saat ini (flag `device_retrieved_at` di `customer_devices`) sepertinya hanya mengurus perangkat utama. Perlu dicek sebelum perluasan.
2. Di Laporan Pemasangan, field ini wajib atau opsional? Usulan: opsional, sama seperti Material Terpakai (ADHOC-165).
3. Perangkat yang dibawa tapi tidak terpasang: kembali ke custody, atau dicatat rusak/hilang?
4. Role teknisi perlu permission `customers.detail.devices.create` di Role Matrix?
