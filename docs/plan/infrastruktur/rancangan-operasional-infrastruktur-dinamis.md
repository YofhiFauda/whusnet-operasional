# Rancangan Operasional Infrastruktur Dinamis

Tanggal: 2026-10-14
Status: Rancangan; belum menjadi task implementasi sprint aktif.
Acuan visual: peta-infrastruktur-isp.html di folder ini.

## Tujuan dan prinsip

Satu halaman peta memperlihatkan susunan jaringan, kapasitas dan slot kosong, barang Gudang yang terpasang, pelanggan pengguna port, serta riwayat pekerjaan. Susunan tiap cabang boleh berbeda kedalaman tanpa skema khusus per cabang.

POP menentukan wilayah akses, CID, dan billing. Network node menentukan posisi/fungsi fisik. Port dan slot menentukan kapasitas. Item, serial, saldo infrastruktur, dan ledger Gudang menentukan identitas serta riwayat barang. Pelanggan menempati port ODP melalui assignment. ONT pelanggan dibaca dari pelanggan dan serial INSTALLED, bukan DEPLOY pada node. SN dan model barang tidak disalin menjadi identitas permanen node: ganti unit mempertahankan posisi dan mengganti SN melalui cabut/pasang.

HTML adalah acuan tampilan, bukan skema atau sumber produksi. localStorage, hapus node beserta turunan, dan serial langsung di node tidak diadopsi.

## Status implementasi 2026-10-14

| Area | Sudah ada | Belum tuntas |
|---|---|---|
| Pohon | network_nodes dengan parent_id dan anchor_pop_id; tipe dikelola admin; POP scope dari anchor. | Feeder, rack, lapisan pusat. |
| Port/slot | network_node_ports, network_port_assignments, olt_slots pada kerja lokal ADHOC-168. | Migrasi database target, hubungan chassis-kartu, pengisian dari pemasangan. |
| Gudang | SN DEPLOYED pada node, infrastructure_balances qty/roll, ledger DEPLOY/RETRIEVE_INFRA. | Registrasi aset lama dan cabut/koreksi qty/roll. |
| Laporan | Seksi infra pada form maintenance bersama O-REQ/INFR-REQ. | Aksi per baris, kirim ulang idempoten, validasi semua kasus. |
| API | Mini POP/Distribusi dan teks olt_number, olt_slot, olt_port pelanggan. | Field API belum menempati port pohon. |
| Tampilan | Prototipe HTML memiliki tab Topologi dan Inventaris. | ADHOC-167 /network/inventaris dibatalkan dan dihapus dari dev pada 2026-10-14 karena tidak sesuai prototipe. |

Status kerja lokal tidak berarti migrasi berjalan di setiap database target. Sprint tercatat aktif tetap S8.10-T003 FOP Notification Dashboard.

## Tampilan tujuan

Satu halaman mengikuti tab Topologi dan Inventaris prototipe. Topologi berupa pohon/kanvas dengan perluas, pencarian, pilihan kedalaman, dan panel detail. Inventaris berupa tabel titik/perangkat menurut lokasi, tipe, kapasitas, sisa, utilisasi, dan status. Filter cabang dibatasi POP scope. Detail memuat port/slot bernomor, target pelanggan/node, dan barang Gudang: nama, SN/lot, qty/meter, tanggal, teknisi, task, serta tautan riwayat.

Inventaris titik/kapasitas dan barang fisik tampil bersama, tetapi sumber datanya berbeda. Tanggal, teknisi, dan task berasal dari ledger; posisi sekarang dari status serial/saldo node. Riwayat cabang memakai snapshot ledger dan tidak berubah saat node pindah.

Bedakan port kosong terverifikasi, terpakai terverifikasi, terpakai belum terverifikasi, dan belum didata. Ini kebutuhan produk; bentuk skema belum diputuskan. Jangan menyamakan belum didata dengan kosong. Bongkar berarti cabut aset, lepas assignment dengan alasan/konfirmasi fisik, lalu nonaktifkan posisi. Riwayat tidak dihapus.

## Keterlacakan Gudang

Alur baru: Gudang Cabang -> issue ke custody teknisi -> task O-REQ/INFR-REQ -> laporan realisasi pada node -> DEPLOY -> SN DEPLOYED atau saldo qty/roll node. Issue belum berarti terpasang. Persetujuan FOP memeriksa mutu laporan dan tidak memotong stok kedua kali.

Jejak harus terbaca dua arah: barang -> ledger -> task/teknisi -> node, dan node -> aset aktif -> transaksi asal. Cabut SN memakai RETRIEVE_INFRA. Laporan saldo fisik qty/roll baru lengkap setelah jalur cabut/koreksi ada.

Aset lama memerlukan registrasi awal/opname khusus dengan node, identitas, kondisi, tanggal observasi, aktor, dan alasan. Transaksi dibedakan dari pemasangan baru; jangan membuat task atau DEPLOY fiktif. Input UI versus import dan siapa pengesahnya masih keputusan produk.

## Kasus laporan O-REQ dan INFR-REQ

| Kasus | Node | Perubahan |
|---|---|---|
| O-REQ kantor tanpa jaringan | Opsional | Tindakan, hasil, material, bukti; tanpa DEPLOY palsu. |
| O-REQ menyentuh jaringan | Wajib bila mutasi | Aturan sama dengan INFR-REQ. |
| INFR-REQ periksa | Lokasi relevan | Hasil pemeriksaan; stok tetap jika tanpa barang. |
| Pasang | Node tujuan wajib | SN custody atau qty/meter tersedia; DEPLOY satu kali. |
| Ganti | Node SN lama | Cabut SN DEPLOYED pada node itu dan pasang baru atomik. |
| Cabut | Node sumber wajib | RETRIEVE_INFRA ke custody dengan alasan. |
| Beberapa aksi satu kunjungan | Lokasi per aksi | Identitas aksi tetap agar kirim ulang tidak menggandakan ledger. |

Server memeriksa cabang task dan POP scope aktor. Barang sama tidak masuk seksi material generik dan infrastruktur pada submit yang sama. Laporan serta semua mutasi atomik. Task tanpa pelanggan tetap membutuhkan anchor transaksi valid; bila tidak ada, submit gagal jelas. FOP melihat aksi, barang, node, dan transaksi sebelum menilai. Koreksi membuat pembalik serta aksi baru, bukan menghapus ledger.

Form kini belum memenuhi semua aturan. Ganti perlu memvalidasi SN lama berada pada node terpilih, bukan hanya cabang sama. Kirim ulang setelah FOP reject memerlukan identitas aksi persisten.

## Kapasitas OLT, PON, dan ODP

| Tingkat | Arti kapasitas | Terpakai ketika |
|---|---|---|
| Chassis OLT | Slot kartu fisik | Kartu dipasang pada slot. |
| Kartu PON | Jumlah port PON fisik menurut model | Port dikonfirmasi dipakai. |
| Port PON | Batas ONT/layanan hilir sesuai desain | Pelanggan hilir terpetakan. |
| ODP | Port splitter/drop fisik | Assignment aktif atau pemakaian sementara berlabel jelas. |

Angka 128 sebagai max_ports kartu berpotensi merupakan batas ONT per PON, bukan port fisik kartu. Jumlah port kartu dan batas ONT per PON dipisah menurut perangkat nyata. Perlu keputusan apakah OLT pertama selalu Mini POP atau dapat langsung di Cabang. Chassis fisik perlu posisi aset sendiri agar SN OLT terlacak tanpa menduplikasi Mini POP.

## Manual dahulu, API untuk rekonsiliasi

1. Admin/FOP mencatat Mini POP, node, slot/kartu, dan port fisik tersedia.
2. Saat pemasangan teknisi memilih ODP dan port nyata. Server menolak port terisi, pelanggan dengan port aktif lain, atau pilihan luar cabang/scope. Pemasangan gagal tidak meninggalkan port terisi semu.
3. NOC/teknisi mengisi OLT, slot, port PON, dan VLAN sesuai konfigurasi nyata. Setting OLT tetap manual.
4. Pindah/putus layanan melepas assignment dengan catatan fisik agar kapasitas benar.

Sesudah alur manual stabil, API sistem lain boleh mengirim konfirmasi dengan pelanggan, kode titik/port yang dipetakan ke ID internal, waktu, sumber, dan kunci idempotensi. Field olt_number/olt_slot/olt_port sekarang hanya detail perangkat pelanggan; belum boleh otomatis menandai port pohon. Perbedaan API dan laporan lapangan masuk antrean verifikasi NOC/FOP, tanpa menimpa atau menghitung dua kali. Otomatisasi setting OLT di luar rancangan ini.

## Dinamis antar lokasi

Pohon tidak mewajibkan semua tingkat di tiap cabang. Contoh: Cabang A -> Mini POP/OLT -> ODP; Cabang B -> Mini POP/OLT -> ODC -> ODP; Cabang C -> agregasi -> beberapa Mini POP -> feeder -> ODC -> ODP. Tipe dan aturan penempatan dikelola admin. Parent node menyatakan hirarki posisi; assignment port menyatakan koneksi nyata. Pindah subtree memeriksa scope asal/tujuan, sambungan, dan aset aktif. Uplink lintas cabang perlu aturan eksplisit, bukan menghapus semua guard cabang.

## Urutan usulan task

1. Putuskan chassis OLT, posisi kartu, arti port kartu, dan batas ONT per PON.
2. Kunci status node, kompatibilitas item/tipe, konsistensi parent-port, pelepasan port/kartu.
3. Lengkapi laporan semua kasus, idempotensi, cabut qty/roll, dan registrasi aset lama.
4. Hubungkan assignment ODP/OLT ke pemasangan manual, perubahan, putus layanan, serta rekonsiliasi legacy ambigu.
5. Bangun tab Topologi/Inventaris sesuai HTML dari data aplikasi dan POP scope; ukur performa pohon besar.
6. Setelah manual terbukti, buat API konfirmasi/rekonsiliasi sistem lain.

Ini urutan rancangan, bukan izin melompati S8.10-T003 atau menerapkan migrasi sekarang.

## Kriteria penerimaan kelak

- Topologi/Inventaris mengikuti prototipe, memakai data aplikasi, membatasi POP scope.
- Barang terlacak dua arah antara Gudang, task, teknisi, dan node; riwayat cabang tetap saat node pindah.
- Satu port ODP tidak ditempati dua pelanggan aktif; pindah/putus membebaskan port dengan jejak.
- Slot, port PON, batas ONT, dan ODP menampilkan satuan benar dan membedakan kosong dari belum didata.
- Semua kasus laporan diuji berhasil, gagal, kirim ulang tanpa mutasi ganda atau task selesai sebagian.
- Perbedaan API dan laporan lapangan masuk antrean verifikasi dengan sumber/waktu jelas.

## Rujukan

- docs/plan/infrastruktur/peta-infrastruktur-isp.md dan peta-infrastruktur-isp.html
- docs/plan/infrastruktur/analisa-gap-peta-infrastruktur.md
- docs/plan/infrastruktur/analisa-inventaris-infrastruktur.md (riwayat ADHOC-167)
- docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md
- docs/superpowers/specs/2026-10-12-laporan-oreq-infr-req-design.md
- docs/api/api-pop-distribusi/business-logic.md
- docs/TASKS.md (ADHOC-167, ADHOC-168, S8.10-T003)
