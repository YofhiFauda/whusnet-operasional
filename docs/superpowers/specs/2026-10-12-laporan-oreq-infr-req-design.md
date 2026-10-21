# Rancangan Laporan O-REQ dan INFR-REQ: FOP, Gudang, Infrastruktur

Status: **Fondasi laporan, anchor FopTask, koreksi/cabut material, inspeksi Gudang, dan nomor Task pada Peta sudah ada. Lanjutan kode lokal 2026-10-17: baris aksi jamak, validasi SN pada node, slot kartu OLT, port asal/tujuan, serta review FOP dan pencegahan stok ganda sudah diimplementasikan. Rekonsiliasi selisih fisik Gudang dan penerapan migrasi pada database target masih perlu tindak lanjut. Audit di §0 tetap acuan batas desain.**

## 0. Audit relevansi 2026-10-15 — acuan status terbaru

**Kesimpulan:** alur FOP → Gudang/custody → laporan teknisi → aset pada node → review FOP masih berlaku. Dokumen ini sekarang merupakan rancangan penyelesaian laporan, bukan rancangan untuk memulai fondasi Infrastruktur dari nol. Bagian historis di bawah dibaca bersama audit ini jika menyebut kondisi 2026-10-12.

| Area | Keadaan terbaru | Dampak pada rancangan laporan |
|---|---|---|
| Lokasi dan Gudang | Pohon node, port, aset DEPLOYED, saldo qty/roll, ledger DEPLOY/RETRIEVE_INFRA sudah ada di kode lokal. **Halaman Inventaris berdiri sendiri sudah dihapus (2026-10-15)** — jejak aset/saldo sekarang disajikan dari Peta Infrastruktur (`InfrastructureMapController`/`InfrastructureMapService`, route `/network/peta`), bukan halaman terpisah. | Alur utama tetap; laporan harus memakai sumber ini. Setiap acuan "halaman Inventaris" di dokumen ini (§6, §10 poin 8) dibaca sebagai **Peta Infrastruktur** — jangan dibuat halaman Inventaris baru. |
| Chassis OLT | Fase 0 ADHOC-173: Mini POP adalah lokasi fisik yang dapat menaungi banyak chassis. Chassis boleh ber-anchor ke Mini POP **atau Cabang langsung**; kartu menjadi anak chassis, slot dimiliki chassis (`OltSlot`, kolom `chassis_node_id`/`card_node_id`). | Ketentuan lama di §8 yang hanya mengizinkan anchor Mini POP diganti dengan aturan ini. |
| Aset lama | Jalur registrasi aset lama REGISTER_INFRA dan halaman per node sudah ada. | Jangan membuat task, ISSUE, atau DEPLOY fiktif untuk aset yang sudah lama terpasang. |
| Form O-REQ/INFR-REQ | Seksi infra dasar sudah ada: satu node tujuan, SN baru, SN lama, satu item qty, dan satu roll; transaksi di dalam submit laporan. | Task 12 plan v3 telah dijalankan sebagian. §10 di bawah adalah daftar gap lanjutan, bukan langkah sebelum form pertama dibuat. |
| Port dan slot | Port assignment (`NetworkPortAssignmentService`) serta slot chassis (`OltSlot`) dikelola tersendiri; Peta Infrastruktur membacanya. | DEPLOY barang pada node tidak otomatis membuktikan port tersambung, slot terisi, atau pelanggan menempati port. Perubahan port/slot butuh aksi dan verifikasi terpisah. |
| Penempatan tipe node vs kompatibilitas SKU — **dua guard terpisah, jangan dicampur** | `network_node_type_rules` (dibaca `NetworkNodeService::assertTypePlacement()`) mengatur topologi: anak boleh ke parent-type/anchor-pop-type mana. **Bukan** aturan SKU. Kompatibilitas SKU↔node barang/aset ditegakkan terpisah di `InventoryService::assertNodeAcceptsSerialItem()` — cek `item.infrastructure_node_type_id` vs `node.node_type_id`, dan untuk `olt_chassis`/`olt_card` wajib kategori `olt_module` + slot/komponen belum terisi. Dipanggil dari `deploySerial()` dan `registerExistingInfraSerial()`. | Audit 2026-10-12 sebelumnya menyebut keduanya seolah satu aturan "kompatibilitas SKU OLT di registrasi aset lama" — itu salah sumber. Baris aksi Pasang/Ganti kartu OLT yang dibangun di rancangan laporan ini (§6, §10 poin 7) **memanggil `InventoryService::deploySerial()`**, yang menjalankan `assertNodeAcceptsSerialItem()` secara internal (`private`); jangan memanggil guard itu langsung atau menulis ulang cek SKU di controller laporan — satu sumber kebenaran untuk kompatibilitas barang, terpisah dari aturan penempatan node. |

**Yang sudah ditangani sebagian:** pilihan SN lama berasal dari serial DEPLOYED dalam cabang task, bukan custody; mutasi Ganti berlangsung dalam satu transaksi. Task O-REQ/INFR-REQ tanpa pelanggan dibuatkan tepat satu anchor FopTask, termasuk fallback task lama. Koreksi qty/node/identitas roll dan cabut qty/roll memakai transaksi terpisah; hasil cabut menunggu inspeksi Gudang dan potongan roll layak memakai identitas baru. Barang infrastruktur ROLL dipisah dari picker roll pelanggan.

**Update implementasi kode lokal 2026-10-17:**

1. SN lama kini wajib berada pada node aksi yang dipilih; pemeriksaan diulang setelah lock saat cabut. Node Mini POP dibandingkan melalui cabang induknya.
2. Laporan dapat memiliki beberapa baris aksi dengan kunci persisten. Pengiriman ulang dengan kunci dan mutasi sama tidak membuat ledger baru; kunci sama dengan mutasi berbeda ditolak. Seluruh baris satu laporan dibatalkan bila satu gagal.
3. Jalur material umum dan Infrastruktur menolak item/SN/roll yang sama. Detail Task untuk review FOP menampilkan tindakan, lokasi, pelaksana, waktu, dan nomor transaksi.
4. Pemeriksaan node/port tidak mengubah stok. Kartu OLT mensyaratkan SN dan slot pada chassis induk. Sambung kabel mensyaratkan port asal serta port tujuan yang valid; lepas membebaskan keduanya. Ganti SN tanpa tindakan port tidak mengubah assignment.
5. Koreksi administratif dan cabut fisik qty/roll, inspeksi Gudang, serta satu anchor FopTask untuk Task tanpa pelanggan sudah tersedia. Nomor Task tampil pada Peta; port tujuan tampil pada Peta dan detail node.

**Masih terbuka:** selisih jumlah fisik saat inspeksi Gudang ditahan untuk keputusan rekonsiliasi tersendiri; audit data lama sebelum unique migration dan penerapan/verifikasi migrasi serta seeder pada database target perlu dilakukan. Status kode lokal tidak menyatakan database target sudah siap.

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

**INFR-REQ — pekerjaan jaringan.** Catat aksi periksa, pasang, ganti, atau cabut, lokasi jaringan, SN/qty/meter terkait, hasil, dan bukti. Node wajib jika ada perubahan aset atau pemakaian material yang menjadi saldo node. Pemeriksaan tanpa mutasi tidak mewajibkan baris barang. Satu laporan boleh memiliki beberapa baris aksi fisik, termasuk aksi pada node berbeda yang masih berada dalam POP task dan scope aktor.

**Satu sumber pemakaian.** Form lama memiliki materials[] dan roll generik; plan v3 menambah infra_*. Satu item/SN/roll tidak boleh dikirim lewat dua seksi dan mengurangi stok dua kali. Barang yang menjadi aset/saldo jaringan dicatat melalui seksi Infrastruktur beserta node. Material kerja generik tetap melalui Material Terpakai. UI dan validasi server menolak duplikasi.

## 4. Alur FOP, Gudang, dan Infrastruktur

1. FOP membuat task pada POP yang benar dan menugaskan tim.
2. Gudang issue barang ke custody. Issue belum berarti terpasang.
3. Teknisi bekerja dan submit realisasi beserta lokasi serta bukti.
4. Dalam satu transaksi database, laporan dan mutasi custody ke node disimpan. Ledger DEPLOY menyimpan snapshot cabang pada saat kejadian.
5. FOP setuju/tolak/pending laporan tanpa membuat mutasi Gudang tambahan.
6. Halaman node dan Peta Infrastruktur menunjukkan aset/saldo terpasang serta jejak pemasangan; Gudang dapat menelusuri riwayat custody sampai node. Data jejak berasal dari ledger dan relasi task/aksi yang sama, bukan salinan manual yang dapat berbeda.

## 5. FOP reject dan kirim ulang

Laporan maintenance dapat dikirim ulang setelah FOP reject dan task kembali in_progress. Jika submit pertama sudah mencatat DEPLOY, kirim ulang foto/uraian tidak boleh membuat DEPLOY kedua, memotong custody kedua kali, atau menggandakan saldo node.

Setiap **baris aksi fisik** memiliki identitas stabil yang disimpan di server dan unik dalam task, misalnya record aksi infrastruktur per task yang mereferensikan satu atau beberapa transaksi ledger. Baris memuat jenis tindakan, node/lokasi masing-masing, barang atau SN/roll, jumlah/meter, pelaksana, waktu realisasi, serta perubahan slot/port bila relevan. Form awal Task 12 belum memiliki identitas aksi ini. Kirim ulang kunci aksi dengan isi mutasi yang sama mengembalikan hasil lama tanpa ledger baru atau pemotongan custody kedua. Kunci sama dengan isi mutasi berbeda ditolak sebagai konflik; edit bukti/uraian yang diizinkan tidak mengubah mutasi. Jika barang/lokasi salah, buat aksi koreksi dengan identitas baru dan transaksi pembalik/perpindahan beralasan. Ledger tetap append-only. Aksi fisik baru memakai identitas baru.

Validasi idempotensi harus terjadi di server dalam transaksi database, dengan penguncian saldo/serial dan batas unik identitas aksi per task agar dua request bersamaan tidak sama-sama memotong stok. Mutasi dan laporan harus atomik. Kegagalan validasi, saldo, atau salah satu aksi membatalkan seluruh submit; task tidak menjadi selesai.

## 6. Aturan aksi

| Aksi | Pilihan | Hasil |
|---|---|---|
| Periksa | Node, tanpa barang bila tidak ada mutasi | Laporan tersimpan, stok tidak berubah. |
| Pasang | SN ISSUED atau qty/roll dari custody tim; node tujuan | DEPLOY ke node, custody berkurang, SN/saldo node bertambah. |
| Cabut | SN DEPLOYED pada node terpilih | RETRIEVE_INFRA ke custody teknisi pelaksana, lokasi SN kosong. |
| Ganti | SN lama DEPLOYED di node dan SN baru ISSUED dari custody tim | Cabut lama dan pasang baru dalam satu transaksi. Gagal satu langkah membatalkan semuanya. |

### Bentuk baris aksi dan hubungan fisik

Satu laporan dapat berisi beberapa baris aksi. Setiap baris berdiri sendiri untuk lokasi, barang, kuantitas, dan kunci idempotensi; satu kunjungan dapat, misalnya, mengganti SN di satu chassis lalu memakai kabel dari roll di node lain. Setiap baris tervalidasi terhadap POP task dan scope aktor. Tindakan yang hanya memeriksa node tidak membuat transaksi stok.

Slot dan port dicatat **sesuai perubahan fisik yang benar-benar terjadi**:

| Pekerjaan | Data fisik yang diperlukan | Dampak |
|---|---|---|
| Periksa node/port tanpa perubahan | Node/port yang diperiksa, hasil dan bukti | Tidak mengubah stok, slot, atau port assignment. |
| Pasang/ganti kartu OLT | Chassis, nomor slot, **node kartu** anak chassis, SN baru; SN lama bila ganti | SN dipasang pada node kartu; slot menyimpan posisi node kartu dalam chassis. Mutasi aset dan perubahan slot diperiksa sebagai satu aksi. |
| Sambung/lepas/pindah kabel | Port asal/tujuan yang terlibat dan tindakan pada masing-masing ujung | Assignment port berubah hanya setelah validasi hubungan fisik dan kapasitas. |
| Ganti perangkat tanpa mengubah kabel | Node, SN lama dan baru, konfirmasi koneksi tetap | Mutasi SN terjadi; assignment port tetap. |
| O-REQ kantor tanpa pekerjaan jaringan | Uraian, material kantor jika ada | Tidak memaksa node, slot, atau port jaringan. |

DEPLOY ke node tidak otomatis mengisi slot atau menghubungkan port. Khusus kartu OLT, verifikasi bahwa slot milik chassis dan node kartu adalah anak chassis tersebut; tujuan DEPLOY SN adalah node kartu, bukan node chassis atau baris slot. Jika baris aksi memang mengubah aset **dan** slot/port, perubahan tersebut harus berhasil bersama dalam satu transaksi; kegagalan salah satunya membatalkan aksi. Jika tidak ada perubahan koneksi, jangan buat assignment baru dari keberadaan SN. Detail implementasi mengikuti model slot chassis dan network_port_assignments yang sudah ada.

Implementasi awal sudah mengambil kandidat infra_retrieve_old_serial_id dari SN DEPLOYED dalam cabang task, dan SN baru dari custody. Namun kandidat SN lama belum dibatasi ke node yang dipilih; retrieveSerialFromLocation() juga belum menerima node yang diharapkan. Validasi node SN lama harus ditegakkan ulang dengan lock saat transaksi. Qty/meter harus positif dan tidak melebihi custody. Cabut balik saldo qty/roll dari node belum didukung; rancang transaksi tersendiri sebelum menampilkan aksi tersebut.

### Koreksi pencatatan dan pencabutan fisik qty/roll — keputusan 2026-10-16

Keduanya adalah kejadian berbeda. Form wajib memilih alasan dan jenis kejadian; satu baris aksi tidak boleh mengubahnya diam-diam saat kirim ulang.

| Kejadian | Catatan dan efek saldo |
|---|---|
| Salah catat qty, roll, atau node; barang tidak berpindah secara fisik | Aksi koreksi administratif merujuk transaksi DEPLOY asal dan membuat catatan pembalik serta catatan yang benar. Kurangi proyeksi node yang salah; pulihkan custody/length yang terpotong hanya jika barang memang belum dipakai, atau tempatkan efeknya pada node yang benar tanpa mengarang pencabutan fisik. Jangan memakai RETRIEVE_INFRA untuk kejadian yang tidak terjadi. |
| Barang benar-benar dicabut | Aksi cabut merujuk node, item, jumlah, dan transaksi asal; tulis RETRIEVE_INFRA dan kurangi saldo node. RETRIEVE_INFRA mengembalikan hasil cabut ke custody pelaksana untuk pemeriksaan; tandai sebagai menunggu pemeriksaan dan cegah issue/deploy ulang sampai kondisi diputuskan. Setelah kondisi dan jumlah fisik diverifikasi, barang layak pakai mengikuti alur retur/pemakaian yang berlaku; barang rusak/sisa tak layak mengikuti alur penyesuaian atau afkir beralasan. |

Untuk QUANTITY, saldo node saat ini digabung per item (bukan per lot custody), sementara DEPLOY mencatat lot sumber per transaksi. Karena itu koreksi/cabut harus merujuk transaksi asal dan membatasi jumlah kumulatif per transaksi serta per saldo node; jangan menebak lot dari saldo agregat. Untuk ROLL, saldo node dibedakan menurut kode roll. Kabel yang telah dipotong tidak otomatis menambah panjang roll asal; bila potongan layak pakai, catat sebagai potongan/identitas fisik tersendiri sebelum menjadi stok tersedia.

Ledger tetap append-only. Simpan alasan, aktor, waktu, aksi asal, transaksi asal, dan aksi pengganti bila ada. Jalankan validasi saldo, penguncian, pembalik, dan mutasi pengganti dalam satu transaksi; tolak koreksi/cabut yang melebihi jumlah bersih yang masih terpasang. Setiap aksi koreksi/cabut punya identitas idempoten sendiri. Jalur layanan dan bentuk ledger koreksi administratif perlu dibangun; ini keputusan perilaku, bukan klaim bahwa kode saat ini sudah mendukungnya.

### Jejak pemasangan di Peta Infrastruktur dan Gudang

**Koreksi 2026-10-15: tidak ada halaman "Inventaris" berdiri sendiri.** Halaman itu sudah dihapus dari kode (`NetworkInventoryController`, `NetworkInventoryService`, view `network/inventaris/index.blade.php`); fungsinya digabung ke **Peta Infrastruktur** (`InfrastructureMapController`/`InfrastructureMapService`, route `/network/peta`). Setiap sebutan "Inventaris" di bawah ini dan di §10 poin 8 dibaca sebagai Peta Infrastruktur.

Setiap aset serial yang **dipasang melalui laporan O-REQ/INFR-REQ** harus dapat ditelusuri dari Peta Infrastruktur: **SN, barang, node pemasangan saat ini, teknisi pelaksana, waktu realisasi/mutasi, nomor task, baris aksi, dan transaksi ledger terkait**. Detail task dan review FOP menampilkan baris aksi beserta hasil mutasinya. Gudang melihat hubungan yang sama untuk menelusuri issue, custody, deploy, dan retrieve. Nomor task tetap wajib untuk O-REQ/INFR-REQ tanpa pelanggan.

**Aset lama adalah jalur berbeda.** Aset yang sudah terpasang sebelum pencatatan sistem masuk melalui `REGISTER_INFRA`, dengan petugas pendata, waktu observasi/registrasi, node, dan alasan. Nomor task pemasangan serta teknisi pemasang boleh kosong jika memang tidak diketahui. Peta Infrastruktur menampilkan asal data sebagai registrasi aset lama dan tidak membuat ISSUE, DEPLOY, atau task fiktif.

Peta Infrastruktur menampilkan status dan node aktif dari data aset, lalu mengambil riwayat pelaksana, waktu, nomor task, dan perpindahan dari ledger serta relasi baris aksi. Riwayat tidak ditimpa saat aset dicabut atau dipindah. Untuk barang qty/roll, tampilkan saldo node saat ini bersama riwayat transaksi (`InfrastructureBalance` hanya cache saldo, bukan sumber kebenaran — sumbernya tetap ledger); jangan menganggap jumlah DEPLOY historis sebagai saldo terkini. Jangan buat sumber riwayat kedua yang harus diselaraskan manual dengan Gudang.

## 7. Task tanpa pelanggan

**Keputusan 2026-10-16:** setiap Task O-REQ/INFR-REQ mempunyai tepat satu FopTask sebagai anchor ledger, walaupun customer_id kosong. FopTask memakai task_id, kategori, POP, jadwal, dan tim dari Task yang sebenarnya; tidak membuat pelanggan palsu. InventoryTransaction tetap menyimpan fop_task_id, dan Peta Infrastruktur menampilkan nomor Task pekerjaan melalui relasi FopTask -> Task (nomor FopTask boleh tetap ada sebagai referensi internal). Semua mutasi tetap menautkan aktor, tim, waktu, dan node.

Untuk task baru, buat/pastikan anchor pada alur pembuatan Task. Untuk task lama tanpa anchor, buat sebelum laporan pertama dalam transaksi yang mengunci Task. Cari FopTask yang sudah tertaut berdasarkan task_id; jangan membuat yang kedua. Audit data duplikat sebelum menegakkan keunikan task_id pada FopTask; keunikan database serta penguncian mencegah dua submit bersamaan membuat anchor ganda. Jika anchor tidak bisa dibuat/ditemukan, tolak seluruh submit sebelum laporan atau aset berubah. Fallback TaskMaintenanceController saat ini hanya membuat FopTask ketika ada pelanggan, sehingga belum memenuhi keputusan ini.

Aset lama yang diregistrasi melalui REGISTER_INFRA tetap pengecualian: tidak mempunyai Task/FopTask pemasangan fiktif (§6).

## 8. Chassis OLT

Keputusan Fase 0 tanggal 2026-10-14: Mini POP adalah **lokasi fisik**, bukan identitas satu OLT. Satu Mini POP dapat memiliki beberapa node olt_chassis. Jika chassis berada di gedung Cabang, node boleh ber-anchor langsung ke POP Cabang tanpa membuat Mini POP baru. Kartu olt_card menjadi anak chassis dan nomor slot dikelola per chassis.

Node chassis adalah posisi fisik yang tetap ketika SN unit diganti. Barang baru dari Gudang masuk melalui DEPLOY; barang lama yang sudah tercatat ditarik melalui RETRIEVE_INFRA. Chassis atau kartu yang sudah terpasang sebelum sistem memiliki jalur registrasi REGISTER_INFRA per node; jangan membuat task/DEPLOY fiktif. Satu node komponen hanya boleh mempunyai SN aktif yang kompatibel menurut tipe barang dan tipe node — ditegakkan oleh `InventoryService::assertNodeAcceptsSerialItem()` (bukan `network_node_type_rules`, yang mengatur penempatan topologi, lihat §0). Method itu `private` dan dipanggil di dalam `deploySerial()` serta `registerExistingInfraSerial()`; alur laporan memakai `deploySerial()`.

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
3. **Sebagian selesai (2026-10-16):** koreksi qty/node/identitas roll dan cabut fisik qty/roll terpisah, ledger append-only, referensi transaksi asal, batas saldo, serta disposisi hasil cabut tersedia. Lanjutkan rekonsiliasi selisih fisik.
4. **Selesai (2026-10-16):** satu FopTask unik per Task O-REQ/INFR-REQ, termasuk tanpa pelanggan; task lama dipastikan memiliki anchor sebelum laporan dalam transaksi.
5. Satu jalur pemakaian tiap item/SN/roll antara seksi generik dan Infrastruktur.
6. Hasil aksi dan transaksi terlihat di detail task saat FOP meninjau laporan.
7. Field dan validasi slot/port kondisional per baris aksi; perubahan assignment hanya untuk tindakan fisik yang dilaporkan dan diverifikasi.
8. Tampilan Peta Infrastruktur, Gudang, detail task, dan review FOP memakai jejak ledger/aksi yang sama untuk SN, pelaksana, waktu, task, dan node.

Rancangan operasional lebih baru berada di docs/plan/infrastruktur/rancangan-operasional-infrastruktur-dinamis.md. Migrasi/seeder harus diverifikasi pada database target sebelum alur ini digunakan di sana.

## 11. Skenario penerimaan

- INFR-REQ tanpa pelanggan memasang SN custody ke node dalam scope; task, ledger, status SN, dan daftar aset node menunjuk kejadian yang sama.
- O-REQ kantor tanpa kerja jaringan selesai tanpa node dan tanpa DEPLOY palsu. O-REQ yang memasang aset jaringan memakai guard yang sama dengan INFR-REQ.
- Ganti mengambil SN lama dari node dan SN baru dari custody. SN lama di node lain atau SN baru di luar custody ditolak.
- Satu laporan memuat beberapa aksi dengan barang, jumlah, dan node berbeda; masing-masing divalidasi dan terlacak sendiri.
- FOP menolak karena bukti kurang jelas; submit ulang baris aksi yang sama tidak menambah DEPLOY atau mengurangi stok lagi, termasuk bila dua request identik datang bersamaan.
- Kunci aksi lama dengan mutasi berbeda ditolak; koreksi menggunakan aksi dan ledger baru beralasan.
- Periksa port tanpa mutasi tidak mengurangi stok; pasang kartu memakai slot yang valid; sambung kabel memakai port yang valid; ganti SN tanpa mengubah koneksi tidak mengubah assignment.
- Peta Infrastruktur dan Gudang menampilkan SN hasil DEPLOY, teknisi, waktu, nomor task, node, serta jejak ledger yang sama; histori tetap ada setelah retrieve. Aset lama REGISTER_INFRA menampilkan petugas pendata, waktu observasi, node, dan alasan tanpa task pemasangan fiktif.
- Pasang/ganti kartu OLT menghubungkan chassis, nomor slot, node kartu anak chassis, dan SN pada node kartu; alur laporan memakai `InventoryService::deploySerial()` agar guard SKU yang sama berlaku.
- Koreksi administratif qty/roll pada node atau jumlah yang salah merujuk DEPLOY asal, membalik efek catatan, dan mencatat hasil benar tanpa RETRIEVE_INFRA fisik fiktif; ledger lama tidak dihapus.
- Cabut fisik qty/roll mengurangi saldo node dan mencatat RETRIEVE_INFRA serta disposisi hasil cabut; jumlah di atas saldo atau sisa transaksi asal ditolak, dan panjang roll asal tidak otomatis bertambah.
- Task O-REQ/INFR-REQ tanpa pelanggan memperoleh tepat satu FopTask melalui task_id; dua submit bersamaan tidak menduplikasi anchor, nomor Task tampil di Peta Infrastruktur, dan kegagalan anchor membatalkan seluruh submit.
- Beberapa chassis OLT dapat dipasang pada node fisik yang ber-anchor ke satu Mini POP, atau langsung ke Cabang bila berada di gedung Cabang, tanpa membuat POP duplikat atau mengubah CID/RBAC pelanggan.
- Duplikasi item/roll di dua seksi ditolak sebelum stok berubah; node di luar POP/scope juga ditolak.
- Snapshot cabang ledger lama tetap setelah node dipindah.

## 12. Referensi

- docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md
- docs/superpowers/plans/2026-10-09-aset-infrastruktur-lokasi.md
- docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md
- docs/task-teknisi/business-logic.md
- docs/fop-task/rancangan-request-pemasangan-dan-material-task.md
