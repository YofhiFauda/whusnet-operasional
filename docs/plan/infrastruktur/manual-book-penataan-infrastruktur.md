# Manual Book Penataan Infrastruktur ISP: dari Awal sampai Selesai

**Versi:** 2026-10-24 (diperbarui untuk Slot/PON sebagai node, ADHOC-213)  
**Sasaran:** FOP, NOC, Admin/Operator Infrastruktur, Gudang, dan Teknisi.  
**Status:** Panduan operasional untuk fitur yang tersedia di kode saat ini. Jalur yang belum lengkap diberi tanda **BELUM DIDUKUNG PENUH**; jangan dianggap sudah otomatis atau siap dipakai di setiap database target.

## 1. Prinsip kerja

Peta Infrastruktur adalah **layar baca dan verifikasi**. Titik dan port disiapkan di **Master Node Jaringan & Port**; barang berasal dari **Gudang**; pekerjaan fisik dicatat melalui **Task INFR REQ** atau **O-REQ** bila menyentuh jaringan. Peta kemudian membaca posisi, kapasitas, port, slot, dan aset terpasang dari data tersebut. File `peta-infrastruktur-isp.html` adalah prototipe visual dengan data browser, bukan tempat mengubah jaringan produksi.

| Istilah | Arti operasional |
|---|---|
| POP/Cabang atau Mini POP | Wilayah dan scope akses. Mini POP berada di bawah Cabang. |
| Tipe node | Fungsi posisi: Server, OLT, Switch Mini POP, Slot, PON, Port Switch, ODP. |
| Node | Posisi/fungsi fisik yang tetap ada saat unit perangkat ber-SN diganti. |
| Port node | Titik sambung bernomor. Port ODP untuk satu pelanggan; port hulu dapat diarahkan ke anak node. |
| Slot / PON / Port Switch | Node di bawah OLT atau Switch (OLT -> Slot -> PON; Switch -> Slot -> Port). Dibuat massal lewat Generate Slot & PON, bukan lewat Tambah Node. Berbeda dari port ODP. |
| SKU/SN/roll/qty Gudang | Identitas serta jumlah barang fisik, bukan identitas permanen node. |
| Issue → custody → DEPLOY | Barang diserahkan ke teknisi, kemudian baru dinyatakan terpasang saat laporan fisik mencatat DEPLOY. |
| Assignment | Hubungan port ke pelanggan atau anak node. Membuat anak node saja belum membuktikan kabel hulunya terhubung. |

**Aturan utama:** catat apa yang benar-benar terpasang di lapangan. Jangan menandai port terpakai, membuat Task palsu, atau mengisi slot hanya dari teks API. Hak menu bergantung pada permission dan scope POP user; jika menu tidak terlihat, minta pengelola RBAC memeriksa hak akses, bukan membuat role khusus per cabang.

## 2. Peta layar dan penanggung jawab

| Tahap | Penanggung jawab | Layar aplikasi | Hasil yang diperiksa |
|---|---|---|---|
| Wilayah | Admin berizin | Master POP/Cabang | Cabang aktif; Mini POP terkait cabang yang benar. |
| Jenis posisi | Admin berizin | Master Tipe Node Jaringan | Tipe aktif, aturan induk/anchor, dan arti kapasitas benar. |
| Posisi & port | Operator berizin `network_nodes.create/update` | Master Node Jaringan & Port | Node, induk, lokasi, port 1..N, sambungan hulu. |
| Barang | Gudang berizin | Master Barang, stok Gudang, Issue | SKU sesuai, SN/roll tersedia, bukti Issue ke custody tim. |
| Pekerjaan | FOP, teknisi | Task, Task Saya, Laporan Maintenance bagian Infrastruktur | Task cabang benar; laporan fisik, node, SN/qty/roll tercatat. |
| Pelanggan | Teknisi pemasangan | Laporan Pemasangan pelanggan, bagian Distribusi Jaringan | Satu pelanggan memakai satu port ODP kosong yang dipilih. |
| Pemeriksaan akhir | FOP/NOC/operator | Peta Infrastruktur ISP, detail node, Riwayat Gudang | Node, port, aset, teknisi, task, dan status fisik cocok. |

**Sebelum mulai:** pastikan migrasi dan seeder modul infrastruktur sudah diterapkan pada lingkungan yang digunakan. Dokumen kerja lokal tidak menjamin tabel/fitur yang sama telah tersedia di database target. Petugas juga perlu memiliki permission yang sesuai dan scope untuk Cabang/Mini POP tersebut.

## 3. Alur standar A–Z untuk titik jaringan baru

### Langkah 1 — Tentukan pekerjaan dan lokasi nyata

1. FOP/NOC mencatat kebutuhan: jenis perangkat, kode lokasi, Cabang, Mini POP bila ada, induk fisik, port hulu, kapasitas nyata, barang yang akan dipasang, dan pelaksana.
2. Pilih Task **INFR REQ** untuk pekerjaan jaringan. **O-REQ** dipakai untuk pekerjaan kantor; bagian Infrastruktur hanya dipakai jika O-REQ benar-benar mengubah jaringan. Pemeriksaan tanpa pemasangan barang tidak perlu membuat DEPLOY.
3. Pastikan task menunjuk cabang yang sama dengan lokasi node. Untuk task tanpa pelanggan, periksa adanya **Task FOP** yang menjadi anchor transaksi Gudang. Jika tidak ada, form pemasangan aset akan gagal; hentikan submit dan eskalasi ke FOP/pengelola aplikasi karena alur pembuatan anchor untuk task tanpa pelanggan belum tuntas. Jangan membuat pelanggan atau task fiktif.

### Langkah 2 — Siapkan POP dan tipe node

1. Di **Master POP/Cabang**, pastikan Cabang sudah ada dan aktif. Jika lokasi merupakan Mini POP, buat/cek Mini POP di bawah Cabang yang benar.
2. Di **Master Tipe Node Jaringan**, gunakan tipe yang sesuai. Tipe yang berlaku: `server`, `olt_chassis` (artinya OLT), `switch_mini_pop`, `olt_slot`, `olt_pon`, `switch_slot`, `switch_port`, dan `odp`. Tipe lama (`core_router`, `aggregation_switch`, `olt_card`, `pon_port`, `odc`) sudah dihapus. Tipe Slot/PON/Port Switch dibuat otomatis oleh Generate massal (langkah 3A), jangan ditambah manual lewat Tambah Node.
3. Periksa aturan penempatan tipe: apakah boleh langsung pada POP tertentu atau harus menjadi anak tipe node tertentu. Jika susunan lapangan berbeda, admin dapat mengelola aturan pada master tipe; jangan mengubah jenis fisik hanya supaya form lolos.
4. Periksa `has_ports` dan `max_ports`. Port individual Switch kini dipetakan sebagai node `switch_port` (di bawah `switch_slot`) lewat Generate massal, bukan lewat kapasitas ringkasan pada tipe. Port fisik ODP tetap tabel port ODP yang dibuat saat node ODP disimpan.

### Langkah 3 — Buat posisi node dan port

1. Buka **Master Node Jaringan & Port** → **Tambah Node Baru**.
2. Isi tipe, kode unik, nama, serta penempatan: **langsung di POP** atau **anak node induk**. Untuk anak node, POP diwarisi dari induk; pilih induk yang memang berada pada cabang yang sama.
3. Isi alamat/koordinat bila berbeda dari induk. Bila kosong, tampilan memakai lokasi efektif warisan.
4. Untuk tipe berport, isi **jumlah port fisik yang benar**, misalnya ODP 8 port. Saat disimpan, sistem membuat port bernomor 1–8 dalam keadaan kosong. Penambahan kapasitas berikutnya memakai **Tambah Port**; port baru melanjutkan nomor terakhir dan tunduk pada batas tipe.
5. Jika node anak mendapat sambungan dari port induk, buka detail **node induk** → pilih port kosong → **Hubungkan** ke anak langsung. Node anak dan assignment port hulu adalah dua catatan berbeda; keduanya perlu dicek.
6. **Penting untuk ODP:** ODP **wajib** berinduk satu **PON**. Cara termudah: buka halaman detail **PON** lalu isi form **Tambah ODP** (PON itu otomatis jadi induk; setelah simpan Anda diarahkan ke detail ODP baru). Alternatif: di modal Tambah Titik pilih tipe `ODP`, lalu di field **PON induk** ketik kode/nama OLT atau kode PON (mis. `OLT-SIMAN-S1-P2`) dan pilih satu hasil berlabel `OLT / Slot n / PON m` (mouse atau panah ↑/↓ + Enter). Pencarian di server, hanya PON dalam scope POP Anda, maksimal 20 hasil — persempit dengan mengetik lebih spesifik. Mengetik ulang membatalkan pilihan; pastikan muncul tulisan "PON terpilih" sebelum menyimpan. Bila PON belum ada, buat dulu lewat langkah 3A. ODP tanpa PON ditolak. Tidak ada lagi langkah "hubungkan uplink ODP ke PON" atau status "uplink terverifikasi": induk PON itulah uplink-nya. ODP lama yang masih menempel langsung di POP tetap tampil dan masih bisa dipasang ke PON lewat aksi Pindah.
7. Periksa node dan port pada Peta. **Perhatian:** pembuatan node saat ini langsung memberi status `active`, meski pemasangan fisik belum selesai. Sampai tersedia status rencana/commissioning, operator harus mengoordinasikan waktu pembuatan node dengan pekerjaan lapangan dan tidak menafsirkan status aktif sebagai bukti perangkat sudah terpasang.

### Langkah 3A — Generate massal Slot dan PON (OLT) atau Port (Switch)

1. Buka detail node **OLT** (atau **Switch Mini POP**) di Master Node, lalu pilih aksi **Generate Slot & PON** (Switch: **Generate Slot & Port**). Aksi ini membuka halaman tersendiri (bukan modal).
2. Isi **jumlah slot** (1-32; khusus OLT wajib), **PON per slot** (Switch: port, 1-64), dan **kapasitas pelanggan aktif per PON baru** (opsional, khusus OLT). Switch memakai satu Slot 1 logis otomatis, jadi jumlah slot tidak perlu diisi.
3. Simpan. Sistem membuat Slot, PON, atau Port sebagai node dengan kode turunan otomatis (`{kode OLT}-S3-P5`), dalam satu transaksi dan dengan satu entri audit. Aksi **aman diulang**: hanya nomor yang belum ada yang dibuat, nilai yang sudah diisi tidak ditimpa. Kapasitas OLT dinaikkan otomatis bila jumlah slot melebihi kapasitas terdata.
4. Kode Slot/PON/Port **tidak bisa diketik** dan node ini **tidak bisa dipindah** antar-induk; yang dipindah adalah OLT/Switch (seluruh isinya ikut) atau ODP.
5. Kapasitas dan ambang Red Zone PON diubah dari halaman detail PON/Slot; kapasitas tidak boleh di bawah pemakaian (pelanggan aktif pada ODP anaknya).

### Langkah 4 — Siapkan barang dan tugas lapangan

1. Di **Master Barang**, pastikan item fisik sudah terdaftar. Barang yang menetap di infrastruktur harus bertipe kepemilikan `infrastructure_asset`; SN switch/OLT/ODP yang ingin terlacak perlu identitas serial bila barangnya memang serial.
2. Untuk item yang dipetakan ke tipe node, pastikan tipenya sesuai. Chassis/kartu OLT memiliki validasi SKU-ke-tipe khusus. Jangan mengubah SKU/tipe untuk mengakali penolakan.
3. FOP memastikan Task pada Cabang tersebut terjadwal dan tim sudah ditugaskan.
4. Gudang memastikan stok tersedia di **Cabang asal**, lalu memakai **Issue Gudang** untuk menyerahkan SN, qty, atau roll kepada teknisi/tim yang ditugaskan. Catat nomor Issue. **Issue hanya berarti berada di custody**, belum terpasang di jaringan. Teknisi mencocokkan barang yang diterima dengan daftar custody sebelum berangkat.

### Langkah 5 — Pasang fisik dan laporkan realisasi

1. Teknisi memasang perangkat/kabel sesuai rencana, lalu mencatat identitas nyata: kode node, port hulu/hilir, SN, roll dan meter/qty yang dipakai, serta hasil kerja.
2. Buka **Task Saya** → task yang dikerjakan → **Laporan Maintenance** → bagian **Pasang/Ganti Infrastruktur**. Pilih **titik jaringan tujuan yang benar**, SN baru dari custody, atau qty/roll beserta jumlahnya. Barang yang menjadi aset/saldo node jangan diulang pada bagian Material Terpakai generik.
3. Simpan laporan. Sistem mencatat DEPLOY dan memindahkan SN menjadi terpasang atau menambah saldo qty/roll node dalam transaksi laporan. Jika validasi/custody gagal, laporan tidak boleh dianggap selesai.
4. FOP memeriksa isi laporan. Persetujuan kualitas laporan **bukan** tindakan stok kedua kali. Jangan mengirim ulang pemasangan yang sama secara coba-coba jika laporan ditolak; idempotensi aksi per baris belum tuntas.
5. Buka **Peta Infrastruktur ISP** → cari node → cocokkan aset, SN/lot, jumlah, waktu, teknisi, task, dan port. Bila punya izin, buka **Riwayat Gudang** dari aset untuk menelusuri Issue dan DEPLOY.

### Langkah 6 — Serahkan dan tutup pekerjaan

FOP/NOC menyatakan pekerjaan selesai secara operasional hanya setelah checklist §8 terpenuhi. Simpan nomor Task, kode node, SN, nomor Issue, port yang dipakai, dan catatan ketidaksesuaian. Peta dan CSV adalah tampilan data tercatat; pemeriksaan fisik tetap menjadi dasar kebenaran sambungan.

## 4. Contoh kerja: memasang switch baru

**Contoh:** switch agregasi `SW-SMN-01` di Cabang Siman, SN `SW-ABC-001`.

1. Tentukan apakah ini posisi agregasi atau core. Gunakan tipe yang sesuai aturan penempatan. Jika perlu mencatat port 1–24, siapkan tipe berport yang valid lebih dulu; tipe switch bawaan hanya punya kapasitas ringkasan.
2. Siapkan Cabang/node induk dan buat node `SW-SMN-01` pada Master Node. Bila switch terhubung dari port node hulu yang dikelola, buat assignment hulu sesuai keadaan fisik.
3. FOP menyiapkan INFR REQ. Gudang Issue SN `SW-ABC-001` ke teknisi tim itu dari Cabang Siman.
4. Setelah switch terpasang, teknisi memilih node `SW-SMN-01` dan SN tersebut pada laporan Infrastruktur. Periksa hasil DEPLOY di Peta dan Riwayat Gudang.
5. Catat konfigurasi perangkat jaringan di prosedur NOC terpisah. **Peta tidak melakukan konfigurasi switch atau OLT melalui API.**

**Jika hanya mengganti unit switch pada posisi yang sama:** pertahankan node `SW-SMN-01`; laporkan SN lama sebagai cabut dan SN baru sebagai pasang. Periksa SN lama memang berada di node itu sebelum submit. Jangan membuat node kedua hanya karena perangkat fisiknya berganti.

## 5. Contoh kerja: menambah ODP baru

**Contoh:** `ODP-SMN-04`, splitter 1:8, di bawah PON 3 pada Slot 2 OLT `OLT-SMN-01`.

1. Cek Cabang/Mini POP dan OLT `OLT-SMN-01`; pastikan Slot 2 dan PON 3 sudah ada (bila belum, Generate massal di langkah 3A) dan jalur fisik tersedia.
2. Buat node bertipe `odp`, **induk = PON 3 (`OLT-SMN-01-S2-P3`)**, kode `ODP-SMN-04`, nama dan lokasi lapangan, jumlah port **8**. Sistem menghasilkan port 1–8 kosong. ODP tanpa PON induk ditolak.
3. Uplink ODP ke PON adalah induknya; tidak ada langkah "hubungkan" terpisah dan tidak ada status "terverifikasi" untuk uplink. Pemakaian PON (pelanggan aktif pada semua ODP anaknya) tampil otomatis di detail PON.
4. Gudang Issue ODP/splitter ber-SN dan material pendukung yang relevan ke tim. Teknisi memasang fisik, kemudian mencatat DEPLOY ke node ODP pada INFR REQ.
5. Periksa di Peta: jalur OLT → Slot → PON → ODP, port 1–8 kosong, aset/SN terpasang, dan riwayat Task. Setelah pelanggan benar-benar disambungkan, port ODP terkait baru ditandai terpakai melalui laporan pemasangan pelanggan.

## 6. Pelanggan, port ODP, slot OLT, dan perubahan sambungan

### Pemasangan pelanggan baru

Pada **Laporan Pemasangan pelanggan** bagian **Distribusi Jaringan**, jika ODP terkelola tersedia, teknisi wajib memilih **ODP + port kosong**. Sistem membuat assignment port ke pelanggan dan menolak pemakaian ganda serta pelanggan yang sudah memakai port aktif lain. Nomor OLT, slot, port OLT, dan VLAN masih dicatat berdasarkan hasil konfigurasi NOC/lapangan sebagai **data teknis pelanggan**; teks itu belum otomatis mengisi pohon port OLT. Jika belum ada ODP terkelola, form masih menerima nomor/port ODP sebagai teks legacy; itu **belum** menjadi assignment port yang terverifikasi di Peta.

### Slot, PON, dan Port Switch (tidak ada lagi kartu OLT)

Slot, PON, dan Port Switch adalah **node** di pohon Master Node, bukan tabel terpisah. **Tidak ada lagi alur kartu OLT**: form "jumlah slot chassis" dan aksi "tempatkan node `olt_card` ke slot" di detail Mini POP sudah dihapus. Chassis, rack, dan kartu OLT adalah **aset Gudang** (SN yang dipasang ke Slot lewat laporan DEPLOY), bukan node; mengganti kartu berarti mengganti SN pada Slot yang sama, sedangkan posisi Slot/PON/ODP di bawahnya tidak berubah.

- Membuat struktur: pakai **Generate Slot & PON** (langkah 3A). Menambah satu slot/PON/port tambahan juga dari halaman detail Slot/PON yang bersangkutan (aksi tambah mengikuti konteks: perangkat -> tambah Slot, Slot -> tambah PON/Port).
- **Pohon dimuat bertahap (lazy):** di Master Node, Mini POP dan OLT/Switch tampil langsung (cabangnya tertutup), sedangkan Slot, PON, dan ODP di bawahnya dimuat saat cabang dibuka — bertingkat: OLT → Slot → PON → ODP. Klik nama Slot/PON/ODP di pohon untuk membuka halaman detailnya. Daftar inspector di kanan hanya memuat perangkat dan node non-posisi. Saat OLT/Switch diklik, panel **Manajemen Node — OLT** (atau **— Switch**) dimuat di inspector: kartu tiap Slot berisi PON/Port beserta kapasitasnya, form kapasitas perangkat/Slot, **Tambah PON/Port** per Slot, **Tambah Slot**, tautan Detail, serta tombol **Generate struktur**. Panel dimuat saat perangkat dipilih (sesaat tampil "Memuat…"; bila gagal ada tombol **Coba lagi**). Simpan dari panel mengarah ke halaman Detail perangkat/Slot; bila validasi gagal Anda kembali ke Master Node dengan perangkat yang sama tetap terpilih dan pesan error tampil sebagai notifikasi. Panel yang sama juga ada di halaman Detail perangkat; rincian satu Slot/PON ada di halaman detail Slot/PON.
- Mengganti **kode OLT/Switch** ikut mengganti kode turunan seluruh Slot/PON/Port di bawahnya; bila salah satu kode baru bentrok, penggantian dibatalkan utuh.
- Slot, PON, dan Port Switch tidak bisa dipindah atau diberi kode manual. Memindahkan OLT/Switch antar Mini POP dalam satu Cabang ikut membawa seluruh isinya; pindah lintas Cabang ditolak bila masih ada aset/saldo di dalam subtree.
- Batas ONT per PON adalah kapasitas pelanggan aktif PON (kolom kapasitas), dihitung dari pelanggan aktif pada ODP anaknya. Slot, PON, dan port ODP berbeda satuan; jangan menyamakannya.
- "Slot OLT kosong" di Peta kini berarti Slot yang belum punya PON.

### Melepas atau mengganti port

1. Pastikan perpindahan kabel/penurunan layanan telah benar-benar dilakukan di lapangan.
2. Di **Detail Node**, untuk port lama pilih **Kosongkan port**, centang konfirmasi pelepasan fisik, dan isi catatan alasan. Sistem menutup assignment aktif dan mengosongkan port.
3. Port hulu ke **anak node** dapat dihubungkan ulang dari port kosong ke anak langsung yang sama setelah pelepasan, dengan pemeriksaan cabang dan larangan dua assignment aktif.
4. **Perpindahan port pelanggan aktif belum punya satu alur atomik khusus.** Jangan menyelesaikannya hanya dengan mengetik nomor port baru pada data teknis pelanggan. FOP/NOC harus merekonsiliasi pelepasan assignment lama, sambungan fisik, serta pencatatan assignment baru yang sah; bila form yang tersedia tidak mendukung kasusnya, catat sebagai pekerjaan tertunda untuk pengembangan alur pindah port.
5. Menandai port “terpakai” secara manual hanya membuat status **belum terverifikasi**. Gunakan untuk temuan lapangan yang targetnya belum diketahui, lalu lakukan verifikasi; jangan perlakukan sebagai assignment pelanggan yang valid.

## 7. Ganti, cabut, pindah, dan pemeriksaan

| Kasus | Prosedur saat ini | Batas yang harus diperhatikan |
|---|---|---|
| Periksa tanpa barang | Laporan INFR REQ berisi hasil/bukti; jangan pilih barang pada seksi Infrastruktur. | Tidak membuat mutasi Gudang. |
| Ganti satu SN pada node | Gudang Issue SN baru; teknisi memilih node, SN lama untuk cabut dan SN baru untuk pasang pada laporan. | Pastikan SN lama benar di node itu; kandidat form saat ini belum menjamin kecocokan node secara ketat. |
| Cabut SN | Laporkan SN lama untuk RETRIEVE_INFRA, lalu atur pemulangan custody ke Gudang sesuai prosedur Gudang. | Jangan langsung menghapus node atau riwayat ledger. |
| Cabut qty/roll dari node | **BELUM DIDUKUNG PENUH.** | Jangan mengurangi saldo node lewat catatan bebas atau DEPLOY negatif. Perlu alur koreksi/retur khusus. |
| Pindah node/subtree | Gunakan aksi pindah di Master Node dan pemeriksaan scope asal/tujuan. | Periksa sambungan, aset, serta task terbuka; jangan pindah lintas cabang sambil menganggap histori ledger berubah. |
| Nonaktifkan titik | Selesaikan cabut aset dan pelepasan port fisik, kemudian ubah status node menjadi nonaktif. | UI saat ini belum memaksa urutan tersebut; gunakan checklist dan pemeriksaan FOP/NOC. |
| Aset lama sudah terpasang sebelum sistem | **BELUM ADA registrasi awal/opname khusus.** | Jangan membuat Issue, Task, atau DEPLOY palsu untuk menghasilkan sejarah semu. |

**Khusus laporan yang ditolak FOP:** periksa ledger dan status barang sebelum kirim ulang. Identitas aksi persisten dan pencegahan mutasi ganda untuk semua kasus belum lengkap; eskalasi bila barang sudah DEPLOYED tetapi laporan perlu diperbaiki.

## 8. Checklist penerimaan pekerjaan

- [ ] Task, tim, Cabang, dan lokasi fisik cocok; pekerjaan tanpa pelanggan memiliki anchor transaksi yang valid bila memakai barang.
- [ ] Jenis node, induk, kode, lokasi, kapasitas, dan nomor port mencerminkan kondisi nyata.
- [ ] Port hulu diarahkan ke anak yang benar; port ODP pelanggan dipilih dari port kosong, bukan hanya ditulis sebagai teks.
- [ ] SKU/SN/roll/qty dan Issue Gudang cocok dengan custody tim; tidak dicatat dua kali sebagai Material Terpakai.
- [ ] Laporan mencatat node tujuan, hasil, barang, dan bukti yang relevan; DEPLOY/RETRIEVE_INFRA terlihat di Riwayat Gudang.
- [ ] Peta menampilkan node dan aset pada Cabang yang benar; nomor port dan jumlah terpakai cocok dengan pemeriksaan lapangan.
- [ ] FOP/NOC meninjau selisih antara data lapangan dan teks OLT/API; tidak menimpa data fisik secara otomatis.
- [ ] Pekerjaan yang belum didukung penuh dicatat sebagai tindak lanjut, bukan dinyatakan selesai semu.

## 9. Bila terjadi masalah

| Gejala | Periksa |
|---|---|
| Node/ODP tidak muncul di Peta | Scope POP user, Cabang/Mini POP induk, aturan penempatan, serta apakah data sudah disimpan. Anak node tanpa anchor seharusnya mewarisi POP induk pada Peta. |
| Tidak dapat membuat tipe/node | Permission master, tipe aktif, aturan parent/anchor, kode unik, dan batas `max_ports`. |
| SN tidak tersedia di laporan | Status barang harus ISSUED, item `infrastructure_asset`, berasal dari Cabang task, dan berada pada custody tim yang benar. |
| Laporan gagal karena Task FOP | Task tanpa pelanggan belum mempunyai anchor transaksi yang diperlukan; hentikan submit dan eskalasi ke FOP/pengelola aplikasi, jangan buat data palsu. |
| Port ODP tidak ada di pilihan pelanggan | Cek status ODP, port kosong, Cabang pelanggan, serta apakah node/port sudah menjadi data terkelola. |
| Port tidak bisa dipakai | Cek assignment aktif pada port itu atau assignment aktif pelanggan pada port ODP lain. |
| Slot/PON tidak muncul di Master Node | Pohon dimuat bertahap: buka cabang OLT/Switch di pohon agar Slot dan PON dimuat. Bila memang belum ada, jalankan Generate Slot & PON (langkah 3A). |
| ODP ditolak saat disimpan | ODP wajib berinduk PON; pilih PON yang berada di Cabang yang sama. Slot/PON/Port tidak bisa dipindah atau diberi kode manual. |
| Peta dan fisik berbeda | Hentikan klaim selesai; cocokkan kode node, serial, Task, transaksi Gudang, port dan kondisi lapangan. Koreksi melalui transaksi/alur yang tepat, bukan menghapus histori. |

## 10. Batas implementasi dan rujukan

Panduan ini menggambarkan fitur aplikasi pada 2026-10-24. **BELUM DIDUKUNG PENUH:** commissioning/status rencana, perpindahan port pelanggan secara atomik, daftar Slot/PON di dalam inspector Master Node (sengaja tidak dimuat; gunakan pohon lazy-load dan halaman detail perangkat/Slot/PON), kartu OLT (konsepnya sudah dihapus; kartu adalah aset Gudang), retur qty/roll dari node, registrasi aset lapangan lama, aksi multi-baris yang idempoten, dan rekonsiliasi API OLT. Jangan menyamakan prototipe HTML dengan fitur aplikasi yang sudah tersedia.

Rujukan: `docs/plan/infrastruktur/rancangan-operasional-infrastruktur-dinamis.md`, `analisa-inventaris-infrastruktur.md`, `analisa-gap-peta-infrastruktur.md`, dan `docs/superpowers/specs/2026-10-12-laporan-oreq-infr-req-design.md`.
