# Rancangan Laporan O-REQ dan INFR-REQ: FOP, Gudang, Infrastruktur

Status: **Konsep disetujui user pada 2026-10-12; belum diimplementasikan.**

## 1. Tujuan dan batas

Laporan O-REQ dan INFR-REQ sekarang memakai form MTN generik. Gap 3–4 pada analisis task teknisi mencatat bahwa pekerjaan kantor dan jaringan belum dibedakan serta perangkat jaringan dari Gudang belum dapat dicatat sebagai terpasang. Rancangan ini melengkapi desain pohon infrastruktur v3. Implementasi tidak termasuk task sprint aktif S8.10-T003.

Di luar rancangan: invoice pelanggan O-REQ/INFR-REQ, pengadaan barang, penjualan antar cabang, lokasi permanen aset kantor non-jaringan, dan kategori rinci semua variasi O-REQ. Harga barang Gudang tidak otomatis menjadi tagihan pelanggan.

## 2. Tanggung jawab

| Pihak | Tanggung jawab |
|---|---|
| FOP | Membuat/menjadwalkan task, memilih POP, menugaskan tim, dan meninjau kualitas laporan. |
| Gudang | Issue barang ke custody tim, mencatat mutasi/saldo, menerima aset yang dicabut. |
| Teknisi | Melaporkan pekerjaan fisik, lokasi sebenarnya, barang dipakai/dicabut, dan bukti. |
| Infrastruktur | Menyediakan pohon lokasi, kapasitas port, dan daftar aset terpasang di tiap node. |

Persetujuan FOP adalah quality gate laporan, bukan perintah kedua untuk mengurangi stok. Pemasangan fisik dicatat saat teknisi pertama kali melaporkannya.

## 3. Dua jenis laporan

**O-REQ — pekerjaan internal kantor.** Catat uraian, tindakan, hasil, material/barang yang dipakai atau dikembalikan, dan bukti relevan. network_node opsional. Seksi Pasang/Ganti Infrastruktur hanya digunakan bila pekerjaan benar-benar menyentuh jaringan. Pekerjaan kantor tidak boleh dipaksa memilih ODP/ODC sebagai lokasi palsu. Alat kerja pinjaman mengikuti custody dan retur yang sudah ada. Pemasangan permanen aset kantor non-jaringan belum punya lokasi pada desain v3; jangan mencatatnya sebagai DEPLOYED di network_nodes.

**INFR-REQ — pekerjaan jaringan.** Catat aksi periksa, pasang, ganti, atau cabut, lokasi jaringan, SN/qty/meter terkait, hasil, dan bukti. Node wajib jika ada perubahan aset atau pemakaian material yang menjadi saldo node. Pemeriksaan tanpa mutasi tidak mewajibkan baris barang. Satu laporan boleh memiliki beberapa aksi fisik.

**Satu sumber pemakaian.** Form lama memiliki materials[] dan roll generik; plan v3 menambah infra_*. Satu item/SN/roll tidak boleh dikirim lewat dua seksi dan mengurangi stok dua kali. Barang yang menjadi aset/saldo jaringan dicatat melalui seksi Infrastruktur beserta node. Material kerja generik tetap melalui Material Terpakai. UI dan validasi server menolak duplikasi.

## 4. Alur FOP, Gudang, dan Infrastruktur

1. FOP membuat task pada POP yang benar dan menugaskan tim.
2. Gudang issue barang ke custody. Issue belum berarti terpasang.
3. Teknisi bekerja dan submit realisasi beserta lokasi serta bukti.
4. Dalam satu transaksi database, laporan dan mutasi custody ke node disimpan. Ledger DEPLOY menyimpan snapshot cabang pada saat kejadian.
5. FOP setuju/tolak/pending laporan tanpa membuat mutasi Gudang tambahan.
6. Halaman node menunjukkan aset/saldo terpasang; traceability menunjukkan riwayat dari Gudang sampai node.

## 5. FOP reject dan kirim ulang

Laporan maintenance dapat dikirim ulang setelah FOP reject dan task kembali in_progress. Jika submit pertama sudah mencatat DEPLOY, kirim ulang foto/uraian tidak boleh membuat DEPLOY kedua, memotong custody kedua kali, atau menggandakan saldo node.

Setiap **baris aksi fisik** perlu identitas stabil tersimpan di server sebagai kunci idempotensi. Sebelum Task 12 plan dikerjakan, tentukan penyimpanannya, misalnya record aksi infrastruktur per task yang mereferensikan transaksi ledger. Kirim ulang aksi yang sama hanya mengubah bagian laporan yang boleh diedit. Jika barang/lokasi semula salah, buat transaksi pembalik/perpindahan dan aksi baru dengan alasan koreksi. Ledger tetap append-only. Perubahan fisik baru memakai identitas aksi baru.

Mutasi dan laporan harus atomik. Kegagalan validasi, saldo, atau salah satu aksi membatalkan seluruh submit; task tidak menjadi selesai.

## 6. Aturan aksi

| Aksi | Pilihan | Hasil |
|---|---|---|
| Periksa | Node, tanpa barang bila tidak ada mutasi | Laporan tersimpan, stok tidak berubah. |
| Pasang | SN ISSUED atau qty/roll dari custody tim; node tujuan | DEPLOY ke node, custody berkurang, SN/saldo node bertambah. |
| Cabut | SN DEPLOYED pada node terpilih | RETRIEVE_INFRA ke custody teknisi pelaksana, lokasi SN kosong. |
| Ganti | SN lama DEPLOYED di node dan SN baru ISSUED dari custody tim | Cabut lama dan pasang baru dalam satu transaksi. Gagal satu langkah membatalkan semuanya. |

Task 12 plan v3 saat ini memvalidasi infra_retrieve_old_serial_id dari daftar SN custody. Itu harus dikoreksi: SN lama berasal dari aset DEPLOYED pada node, SN baru dari custody. Server memeriksa ulang status, lokasi, dan custody dengan lock dalam transaksi. Qty/meter harus positif dan tidak melebihi custody. Cabut balik saldo qty/roll dari node belum didukung retrieveSerialFromLocation(); jika diperlukan, rancang transaksi tersendiri sebelum menampilkan aksi tersebut.

## 7. Task tanpa pelanggan

O-REQ/INFR-REQ dapat mempunyai customer_id kosong. Semua mutasi aset tetap wajib tertaut ke nomor task, aktor, tim, waktu, dan lokasi. TaskWorkToolService::resolveTaskFor() bisa mengembalikan null, sedangkan fallback TaskMaintenanceController sekarang hanya membuat FopTask untuk task yang punya pelanggan. Implementasi harus menjamin anchor FopTask bagi task non-pelanggan atau memilih relasi task langsung yang konsisten di ledger. Jika anchor tidak tersedia, submit gagal jelas dan atomik; task tidak boleh selesai sementara aset yang dilaporkan terlewat.

## 8. Chassis OLT

Dalam desain v3, Mini POP adalah identitas lokasi, scope, CID, dan billing; ia bukan network_node. Chassis OLT dari Gudang belum punya tujuan DEPLOY. Tambahkan tipe node fisik olt_chassis dengan anchor_pop_id ke Mini POP. Node ini merepresentasikan posisi/perangkat fisik, bukan POP kedua. Saat chassis diganti, node tetap; SN lama dicabut dan SN baru dipasang menurut aksi Ganti.

Node lain yang sudah langsung ber-anchor ke Mini POP tetap valid. Anak topologi tidak otomatis dipindah ke bawah olt_chassis saat SN chassis diganti. Perubahan parent memakai NetworkNodeService::move() dan aturan scope yang ada. Perluasan open item chassis desain v3 ini masuk plan sebelum dukungan pemasangan/penggantian OLT dinyatakan siap.

## 9. Scope, audit, dan billing

- Lokasi harus berada pada POP task **dan** scope aktor. Untuk task di Mini POP, turunkan cabang melalui hierarki; jangan anggap task.pop_id selalu ID Cabang.
- Teknisi hanya memakai custody anggota tim. SN lama harus benar-benar DEPLOYED pada node yang dilaporkan. Validasi server berlaku meskipun request tidak berasal dari form.
- Pindah node lintas cabang mengikuti scope asal dan tujuan desain v3. Nasib task terbuka ketika node pindah tetap open item; jangan diam-diam mengubah POP task.
- Ledger DEPLOY/RETRIEVE_INFRA mengisi to_pop_id/from_pop_id sebagai snapshot cabang. Pindah node di kemudian hari tidak mengubah riwayat.
- Seksi ini tidak memberi izin pembayaran atau membuat invoice. O-REQ/INFR-REQ belum masuk verifikasi tagihan pelanggan C-REQ/MTN.

## 10. Penyesuaian plan sebelum Task 12

Plan pohon infrastruktur v3 tetap menjadi dasar. Sebelum membuat seksi laporan, perbarui plan untuk:

1. Memisahkan perilaku O-REQ dan INFR-REQ, termasuk Periksa tanpa mutasi.
2. Menetapkan identitas aksi persisten dan idempotensi kirim ulang setelah FOP reject.
3. Memisahkan query SN lama DEPLOYED pada node dan SN baru ISSUED di custody; Ganti harus atomik.
4. Menjamin anchor transaksi bagi task tanpa pelanggan; gagal submit bila tidak tersedia.
5. Menambah olt_chassis untuk pemasangan/penggantian chassis.
6. Memastikan satu jalur pemakaian tiap item/SN/roll pada form lama dan seksi Infrastruktur.
7. Menampilkan hasil pekerjaan serta mutasi aset di detail task yang dipakai FOP saat review.

## 11. Skenario penerimaan

- INFR-REQ tanpa pelanggan memasang SN custody ke node dalam scope; task, ledger, status SN, dan daftar aset node menunjuk kejadian yang sama.
- O-REQ kantor tanpa kerja jaringan selesai tanpa node dan tanpa DEPLOY palsu. O-REQ yang memasang aset jaringan memakai guard yang sama dengan INFR-REQ.
- Ganti mengambil SN lama dari node dan SN baru dari custody. SN lama di node lain atau SN baru di luar custody ditolak.
- FOP menolak karena bukti kurang jelas; submit ulang tidak menambah DEPLOY atau mengurangi stok lagi.
- Koreksi barang/lokasi membuat transaksi pembalik dan aksi baru tanpa menghapus riwayat.
- Chassis OLT dapat dipasang pada node fisik ber-anchor Mini POP tanpa membuat POP duplikat atau mengubah CID/RBAC pelanggan.
- Duplikasi item/roll di dua seksi ditolak sebelum stok berubah; node di luar POP/scope juga ditolak.
- Snapshot cabang ledger lama tetap setelah node dipindah.

## 12. Referensi

- docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md
- docs/superpowers/plans/2026-10-09-aset-infrastruktur-lokasi.md
- docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md
- docs/task-teknisi/business-logic.md
- docs/fop-task/rancangan-request-pemasangan-dan-material-task.md
