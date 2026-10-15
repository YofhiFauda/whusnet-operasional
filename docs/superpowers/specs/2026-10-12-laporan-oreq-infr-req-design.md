# Rancangan Laporan O-REQ dan INFR-REQ: FOP, Gudang, Infrastruktur

Status: **Fondasi dan seksi laporan dasar sudah diimplementasikan pada kode lokal; alur laporan lengkap belum. Audit 2026-10-15 di §0 adalah acuan status terbaru.**

## 0. Audit relevansi 2026-10-15 — acuan status terbaru

**Kesimpulan:** alur FOP → Gudang/custody → laporan teknisi → aset pada node → review FOP masih berlaku. Dokumen ini sekarang merupakan rancangan penyelesaian laporan, bukan rancangan untuk memulai fondasi Infrastruktur dari nol. Bagian historis di bawah dibaca bersama audit ini jika menyebut kondisi 2026-10-12.

| Area | Keadaan terbaru | Dampak pada rancangan laporan |
|---|---|---|
| Lokasi dan Gudang | Pohon node, port, aset DEPLOYED, saldo qty/roll, ledger DEPLOY/RETRIEVE_INFRA, serta peta baca Topologi/Inventaris sudah ada di kode lokal. | Alur utama tetap; laporan harus memakai sumber ini, bukan membuat model lokasi atau inventaris baru. |
| Chassis OLT | Fase 0 ADHOC-173: Mini POP adalah lokasi fisik yang dapat menaungi banyak chassis. Chassis boleh ber-anchor ke Mini POP **atau Cabang langsung**; kartu menjadi anak chassis, slot dimiliki chassis. | Ketentuan lama di §8 yang hanya mengizinkan anchor Mini POP diganti dengan aturan ini. |
| Aset lama | Jalur registrasi aset lama REGISTER_INFRA dan halaman per node sudah ada. | Jangan membuat task, ISSUE, atau DEPLOY fiktif untuk aset yang sudah lama terpasang. |
| Form O-REQ/INFR-REQ | Seksi infra dasar sudah ada: satu node tujuan, SN baru, SN lama, satu item qty, dan satu roll; transaksi di dalam submit laporan. | Task 12 plan v3 telah dijalankan sebagian. §10 di bawah adalah daftar gap lanjutan, bukan langkah sebelum form pertama dibuat. |
| Port dan slot | Port assignment serta slot chassis dikelola tersendiri; peta membacanya. | DEPLOY barang pada node tidak otomatis membuktikan port tersambung, slot terisi, atau pelanggan menempati port. Perubahan port/slot butuh aksi dan verifikasi terpisah. |

**Yang sudah ditangani sebagian:** pilihan SN lama sekarang berasal dari serial DEPLOYED dalam cabang task, bukan custody; mutasi Ganti berlangsung dalam satu transaksi. Task tanpa pelanggan yang mempunyai anchor FopTask dapat deploy; bila anchor tidak ada, submit dengan isian infra gagal keras. Barang infrastruktur ROLL dipisah dari picker roll pelanggan.

**Yang masih harus diselesaikan sebelum rancangan laporan dianggap terpenuhi:**

1. SN lama harus dibatasi ke **node terpilih**, bukan hanya salah satu node di cabang task. Periksa lagi setelah lock saat transaksi cabut; saat ini retrieveSerialFromLocation menerima SN tanpa parameter node yang diharapkan.
2. Satu kunjungan dapat memiliki beberapa aksi dan lokasi. Form sekarang hanya memiliki satu paket field infra dan satu node. Diperlukan identitas aksi persisten agar kirim ulang sesudah FOP reject tidak mengulang DEPLOY.
3. Tentukan alur koreksi/pembalik yang menjaga ledger append-only, termasuk cabut atau koreksi qty/roll dan feeder. Belum ada jalur retrieve qty/roll dari saldo node.
4. Pastikan task non-pelanggan tanpa anchor bisa memperoleh anchor valid sebelum laporan atau gagal jelas sebelum pekerjaan dicatat; fallback membuat FopTask saat ini masih bergantung pada pelanggan.
5. Tegakkan satu jalur pemakaian per item/SN/roll antara seksi generik dan infrastruktur, dan tampilkan aksi serta transaksi pada review FOP.
6. Tentukan apakah pemeriksaan INFR tanpa mutasi wajib memilih node, dan cara mencatat port/slot yang benar-benar berubah. Jangan menyimpulkan perubahan port/slot dari keberadaan SN atau teks laporan.
7. Migrasi dan seeder Infrastruktur pada database target perlu diverifikasi tersendiri. Status kode lokal dalam docs/TASKS.md tidak membuktikan database target sudah siap.

Rujukan status terbaru: docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md §10–11, docs/plan/infrastruktur/rancangan-operasional-infrastruktur-dinamis.md, docs/plan/infrastruktur/analisa-gap-peta-infrastruktur.md §6, dan docs/TASKS.md ADHOC-168/170/173/178. Fitur API konfigurasi otomatis tetap di luar rancangan ini.

## 1. Tujuan dan batas

Laporan O-REQ dan INFR-REQ masih berbagi form dasar Maintenance, tetapi sekarang sudah memiliki seksi Infrastruktur untuk mutasi barang ke node. Gap 3–4 pada analisis task teknisi adalah alasan historis rancangan ini. Dokumen melengkapi desain pohon infrastruktur yang telah berkembang sampai Fase 0; penyelesaian laporan tetap di luar task sprint aktif S8.10-T003.

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

Setiap **baris aksi fisik** perlu identitas stabil tersimpan di server sebagai kunci idempotensi. Seksi awal dari Task 12 sudah ada, tetapi identitas aksi ini belum tersedia. Rencanakan penyimpanannya pada tahap lanjutan, misalnya record aksi infrastruktur per task yang mereferensikan transaksi ledger. Kirim ulang aksi yang sama hanya mengubah bagian laporan yang boleh diedit. Jika barang/lokasi semula salah, buat transaksi pembalik/perpindahan dan aksi baru dengan alasan koreksi. Ledger tetap append-only. Perubahan fisik baru memakai identitas aksi baru.

Mutasi dan laporan harus atomik. Kegagalan validasi, saldo, atau salah satu aksi membatalkan seluruh submit; task tidak menjadi selesai.

## 6. Aturan aksi

| Aksi | Pilihan | Hasil |
|---|---|---|
| Periksa | Node, tanpa barang bila tidak ada mutasi | Laporan tersimpan, stok tidak berubah. |
| Pasang | SN ISSUED atau qty/roll dari custody tim; node tujuan | DEPLOY ke node, custody berkurang, SN/saldo node bertambah. |
| Cabut | SN DEPLOYED pada node terpilih | RETRIEVE_INFRA ke custody teknisi pelaksana, lokasi SN kosong. |
| Ganti | SN lama DEPLOYED di node dan SN baru ISSUED dari custody tim | Cabut lama dan pasang baru dalam satu transaksi. Gagal satu langkah membatalkan semuanya. |

Implementasi awal sudah mengambil kandidat infra_retrieve_old_serial_id dari SN DEPLOYED dalam cabang task, dan SN baru dari custody. Namun kandidat SN lama belum dibatasi ke node yang dipilih; retrieveSerialFromLocation() juga belum menerima node yang diharapkan. Validasi node SN lama harus ditegakkan ulang dengan lock saat transaksi. Qty/meter harus positif dan tidak melebihi custody. Cabut balik saldo qty/roll dari node belum didukung; rancang transaksi tersendiri sebelum menampilkan aksi tersebut.

## 7. Task tanpa pelanggan

O-REQ/INFR-REQ dapat mempunyai customer_id kosong. Semua mutasi aset tetap wajib tertaut ke nomor task, aktor, tim, waktu, dan lokasi. TaskWorkToolService::resolveTaskFor() bisa mengembalikan null, sedangkan fallback TaskMaintenanceController sekarang hanya membuat FopTask untuk task yang punya pelanggan. Implementasi harus menjamin anchor FopTask bagi task non-pelanggan atau memilih relasi task langsung yang konsisten di ledger. Jika anchor tidak tersedia, submit gagal jelas dan atomik; task tidak boleh selesai sementara aset yang dilaporkan terlewat.

## 8. Chassis OLT

Keputusan Fase 0 tanggal 2026-10-14: Mini POP adalah **lokasi fisik**, bukan identitas satu OLT. Satu Mini POP dapat memiliki beberapa node olt_chassis. Jika chassis berada di gedung Cabang, node boleh ber-anchor langsung ke POP Cabang tanpa membuat Mini POP baru. Kartu olt_card menjadi anak chassis dan nomor slot dikelola per chassis.

Node chassis adalah posisi fisik yang tetap ketika SN unit diganti. Barang baru dari Gudang masuk melalui DEPLOY; barang lama yang sudah tercatat ditarik melalui RETRIEVE_INFRA. Chassis atau kartu yang sudah terpasang sebelum sistem memiliki jalur registrasi REGISTER_INFRA per node; jangan membuat task/DEPLOY fiktif. Satu node komponen hanya boleh mempunyai SN aktif yang kompatibel menurut tipe barang dan tipe node.

Perubahan SN chassis tidak otomatis memindahkan anak topologi atau mengisi/melepas slot dan port. Pekerjaan topologi memakai NetworkNodeService::move() dan pengelolaan slot/port yang berizin, dengan pemeriksaan scope serta hubungan fisik.

## 9. Scope, audit, dan billing

- Lokasi harus berada pada POP task **dan** scope aktor. Untuk task di Mini POP, turunkan cabang melalui hierarki; jangan anggap task.pop_id selalu ID Cabang.
- Teknisi hanya memakai custody anggota tim. SN lama harus benar-benar DEPLOYED pada node yang dilaporkan. Validasi server berlaku meskipun request tidak berasal dari form.
- Pindah node lintas cabang mengikuti scope asal dan tujuan desain v3. Nasib task terbuka ketika node pindah tetap open item; jangan diam-diam mengubah POP task.
- Ledger DEPLOY/RETRIEVE_INFRA mengisi to_pop_id/from_pop_id sebagai snapshot cabang. Pindah node di kemudian hari tidak mengubah riwayat.
- Seksi ini tidak memberi izin pembayaran atau membuat invoice. O-REQ/INFR-REQ belum masuk verifikasi tagihan pelanggan C-REQ/MTN.

## 10. Prioritas penyelesaian laporan setelah implementasi awal

Seksi dasar laporan O-REQ/INFR-REQ dan plan v3 Task 12 sudah ada pada kode lokal. Pekerjaan berikutnya adalah menutup gap perilaku berikut, bukan membuat ulang form dari awal:

1. Validasi SN yang dicabut berada pada node yang dipilih, termasuk pemeriksaan ulang di dalam transaksi.
2. Baris aksi persisten dengan lokasi per aksi, dukungan beberapa aksi satu kunjungan, dan idempotensi kirim ulang setelah FOP reject.
3. Koreksi/pembalik yang menjaga ledger, serta cabut atau koreksi saldo qty/roll dan feeder.
4. Anchor transaksi yang pasti untuk task tanpa pelanggan; gagal jelas jika tidak tersedia.
5. Satu jalur pemakaian tiap item/SN/roll antara seksi generik dan Infrastruktur.
6. Hasil aksi dan transaksi terlihat di detail task saat FOP meninjau laporan.
7. Penentuan hubungan laporan dengan perubahan port/slot yang diverifikasi, tanpa menganggap DEPLOY otomatis membuat assignment.

Rancangan operasional lebih baru berada di docs/plan/infrastruktur/rancangan-operasional-infrastruktur-dinamis.md. Migrasi/seeder harus diverifikasi pada database target sebelum alur ini digunakan di sana.

## 11. Skenario penerimaan

- INFR-REQ tanpa pelanggan memasang SN custody ke node dalam scope; task, ledger, status SN, dan daftar aset node menunjuk kejadian yang sama.
- O-REQ kantor tanpa kerja jaringan selesai tanpa node dan tanpa DEPLOY palsu. O-REQ yang memasang aset jaringan memakai guard yang sama dengan INFR-REQ.
- Ganti mengambil SN lama dari node dan SN baru dari custody. SN lama di node lain atau SN baru di luar custody ditolak.
- FOP menolak karena bukti kurang jelas; submit ulang tidak menambah DEPLOY atau mengurangi stok lagi.
- Koreksi barang/lokasi membuat transaksi pembalik dan aksi baru tanpa menghapus riwayat.
- Beberapa chassis OLT dapat dipasang pada node fisik yang ber-anchor ke satu Mini POP, atau langsung ke Cabang bila berada di gedung Cabang, tanpa membuat POP duplikat atau mengubah CID/RBAC pelanggan.
- Duplikasi item/roll di dua seksi ditolak sebelum stok berubah; node di luar POP/scope juga ditolak.
- Snapshot cabang ledger lama tetap setelah node dipindah.

## 12. Referensi

- docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md
- docs/superpowers/plans/2026-10-09-aset-infrastruktur-lokasi.md
- docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md
- docs/task-teknisi/business-logic.md
- docs/fop-task/rancangan-request-pemasangan-dan-material-task.md
