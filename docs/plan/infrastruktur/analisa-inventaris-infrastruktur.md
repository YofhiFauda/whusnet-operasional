# Analisa: Inventaris Aset Gudang Terpasang sebagai Infrastruktur

Status: **analisis historis ADHOC-167; implementasi `/network/inventaris` dibatalkan dan dihapus dari branch dev pada 2026-10-14 karena tidak sesuai prototipe peta-infrastruktur-isp.html**. Dibuat 2026-10-13.
Dasar: `docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md` (v3) dan
prototipe `docs/plan/infrastruktur/peta-infrastruktur-isp.html` (mock, belum terhubung DB).

## 1. Kebutuhan

Bukti bahwa barang gudang sudah terpasang di infrastruktur, sama seperti bukti modem
terpasang di pelanggan. Tidak perlu menggambar topologi. Cukup daftar yang bisa dicari,
difilter, dan diekspor.


## HIPOTESIS AWAL
• ## Review infrastruktur

  Fondasinya sudah ada, tetapi peta HTML belum menjadi tampilan aplikasi. Peta Infrastruktur ISP (/home/yopi/whusnet/whusnet-operasional/docs/plan/infrastruktur/peta-infrastruktur-isp.html) adalah
  prototipe yang menyimpan data di browser. Aplikasi memakai pohon network_nodes yang terhubung ke POP, port, dan ledger Gudang. Karena itu, tampilan Topologi dan Inventaris dari HTML dapat
  dijadikan acuan visual, sementara datanya harus dibaca dari sistem yang sudah ada. Analisis perbedaan desain dan kode (/home/yopi/whusnet/whusnet-operasional/docs/plan/infrastruktur/analisa-gap-
  peta-infrastruktur.md:9).

  | Kebutuhan | Kondisi saat ini |
  | :--- | :--- |
  | Pohon lokasi yang dapat berbeda antar cabang | Sudah ada: tipe node dikelola sebagai master data, kedalaman pohon bebas, dan node dapat dipindah. |
  | Barang Gudang yang terpasang | Sudah ada daftar `/network/inventaris`: SN, qty/roll, lokasi node, tanggal, teknisi, dan task dari ledger DEPLOY. Layanan inventaris (`/home/yopi/whusnet/whusnet-operasional/app/Services/NetworkInventoryService.php:13`). |
  | Tampilan seperti tab Inventaris pada HTML | Daftar aset sudah ada, tetapi belum menjadi seluruh pengalaman peta HTML, termasuk kanvas topologi dan ringkasan utilisasi berantai. |
  | Slot ODP dan kartu OLT | Ada pencatatan port ODP serta slot/kartu OLT secara manual. Pengisian port belum menyatu dengan laporan pemasangan pelanggan. Layanan port (`/home/yopi/whusnet/whusnet-operasional/app/Services/NetworkPortAssignmentService.php:15`), slot OLT (`/home/yopi/whusnet/whusnet-operasional/app/Http/Controllers/Network/OltSlotController.php:16`). |

  ### Apakah barang dari Gudang bisa terlacak?

  Bisa untuk barang yang dicatat melalui alur Gudang → custody teknisi → DEPLOY ke node. Dari barang dapat dicari lokasi terpasangnya; dari node dapat dilihat barang yang menempel. Saat SN dicabut,
  status dan lokasi berubah melalui transaksi RETRIEVE_INFRA. Riwayat transaksi menyimpan cabang pada waktu kejadian, sehingga perpindahan node kemudian tidak mengubah catatan lama. Rancangan aset
  infrastruktur (/home/yopi/whusnet/whusnet-operasional/docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md:185).

  Ada dua batas penting:

  • Aset lama yang sudah berada di lapangan belum punya alur registrasi awal yang layak; alur DEPLOY saat ini mengharapkan task dan custody. Membuat task palsu akan mengaburkan riwayat. Analisis gap
    aset lama (/home/yopi/whusnet/whusnet-operasional/docs/plan/infrastruktur/analisa-gap-peta-infrastruktur.md:227).

  • Daftar inventaris memakai setiap kejadian DEPLOY untuk qty/roll karena jalur cabut qty/roll dari node belum lengkap. Jadi daftar itu adalah bukti pemasangan, tetapi laporan saldo fisik terkini
    perlu dicocokkan dengan infrastructure_balances. Aturan daftar inventaris (/home/yopi/whusnet/whusnet-operasional/app/Services/NetworkInventoryService.php:13).

  ### O-REQ dan INFR-REQ untuk berbagai kasus

  Saat ini keduanya masih berbagi laporan maintenance. Form sudah dapat mencatat pemasangan SN, qty, roll, atau penggantian satu SN pada satu node; proses mutasinya berada dalam transaksi database.
  Namun rancangan laporan khusus (/home/yopi/whusnet/whusnet-operasional/docs/superpowers/specs/2026-10-12-laporan-oreq-infr-req-design.md:1) masih berstatus belum diimplementasikan. Batas form saat
  ini terlihat pada validasi laporan (/home/yopi/whusnet/whusnet-operasional/app/Http/Controllers/TaskMaintenanceController.php:571) dan eksekusi mutasi (/home/yopi/whusnet/whusnet-operasional/app/
  Http/Controllers/TaskMaintenanceController.php:928).

  | Kasus | Aturan yang saya sarankan |
  | :--- | :--- |
  | O-REQ pekerjaan kantor tanpa perubahan jaringan | Simpan tindakan, hasil, material, dan bukti; node jaringan tidak wajib. |
  | INFR-REQ pemeriksaan saja | Simpan hasil pemeriksaan tanpa mutasi stok. |
  | Pasang | Pilih node nyata dan barang dari custody; catat DEPLOY. |
  | Ganti | Pastikan SN lama berada pada node yang dipilih, cabut lalu pasang SN baru dalam satu transaksi. |
  | Cabut | Kembalikan aset ke custody dengan jejak lokasi asal. |
  | Beberapa tindakan dalam satu kunjungan | Perlu baris aksi terpisah dengan identitas tetap, agar laporan yang ditolak FOP lalu dikirim ulang tidak menggandakan mutasi. |

  Prioritas koreksi laporan adalah validasi SN lama terhadap node tujuan, penanganan task tanpa pelanggan yang belum memiliki FopTask, dan pencegahan barang yang sama tercatat pada dua seksi
  material. Ketiganya sudah diidentifikasi dalam rancangan; saya tidak akan menganggapnya selesai hanya karena form infra_* telah tersedia. Rancangan kasus dan penerimaan (/home/yopi/whusnet/
  whusnet-operasional/docs/superpowers/specs/2026-10-12-laporan-oreq-infr-req-design.md:30).

  ### Bagaimana slot ODP dan OLT sebaiknya terisi?

  Rekomendasi saya: catat dan verifikasi manual lebih dahulu, lalu integrasikan API sebagai jalur konfirmasi dan rekonsiliasi. Pengaturan OLT di lapangan masih manual. Menandai slot otomatis hanya
  dari teks nomor port yang dikirim sistem lain berisiko membuat kapasitas tampak terpakai padahal sambungan fisiknya berbeda.

  Alur yang dapat dipakai:

  1. Admin/FOP membuat Mini POP, node OLT/kartu, ODC/ODP, dan nomor port fisiknya.
  2. Saat pemasangan, teknisi memilih ODP dan port kosong yang benar-benar dipakai; sistem mengaitkan port itu dengan pelanggan dan menolak pemakaian ganda.
  3. Nomor OLT, slot, dan PON port dicatat berdasarkan hasil pemasangan atau konfirmasi NOC. Sistem menampilkan kapasitas kartu/port dari master infrastruktur.
  4. API sistem lain mengirim hasil konfigurasi dengan identitas pelanggan dan titik sambung yang dapat dicocokkan. Jika berbeda dari laporan lapangan, tampilkan selisih untuk verifikasi, bukan
     langsung menimpa catatan fisik.

  Pemisahan kapasitasnya perlu tegas: slot chassis menampung kartu; kartu memiliki port PON; satu port PON melayani banyak ONT sesuai batas desain; satu port ODP ditempati satu sambungan pelanggan.
  Analisis saat ini menemukan angka 128 pada tipe kartu berpotensi tertukar dengan batas ONT per PON. Analisis semantik kartu dan port (/home/yopi/whusnet/whusnet-operasional/docs/plan/
  infrastruktur/analisa-gap-peta-infrastruktur.md:120).

  API yang sudah ada menerima olt_number, olt_slot, dan olt_port sebagai data perangkat pelanggan, tetapi field itu belum otomatis menjadi assignment pada pohon port infrastruktur. Endpoint network-
  device (/home/yopi/whusnet/whusnet-operasional/app/Http/Controllers/Api/NetworkDeviceController.php:16). Pemisahan inventaris sumber daya dari data layanan pelanggan ini juga sejalan dengan tujuan
  TM Forum Resource Inventory API; keselarasan tersebut adalah arahan desain, bukan klaim bahwa API proyek sudah mengikuti standar itu.

  ### Agar dinamis di berbagai tempat

  Gunakan POP sebagai wilayah akses, network_nodes sebagai posisi/fungsi fisik, dan ledger Gudang sebagai identitas serta riwayat barang. Cabang boleh mempunyai susunan berbeda: ada yang langsung
  OLT → ODP, ada yang melalui ODC dan feeder. Tipe node dan aturan induk–anak dapat dikelola dari master data; jangan memaksa setiap cabang mengikuti seluruh tingkat pada contoh HTML. Ketika OLT
  diganti, posisi node tetap dan SN barang yang berubah. Keputusan model dinamis (/home/yopi/whusnet/whusnet-operasional/docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md:28).

  Urutan kerja berikutnya yang paling masuk akal ialah menetapkan model chassis OLT versus Mini POP, membenahi aturan kapasitas port, menuntaskan laporan aksi fisik dan registrasi aset lama, lalu
  menghubungkan pilihan port ke pemasangan. Setelah data dan alurnya konsisten, tab Topologi/Inventaris dapat mengikuti HTML dengan angka yang dapat dipercaya.

## 2. Yang sudah ada di sistem

| Kebutuhan | Sumber data |
|---|---|
| SN terpasang | `inventory_serials`: `status = DEPLOYED`, `current_network_node_id` |
| Qty kabel/aksesoris | `infrastructure_balances` (`item_id`, `lot_no`, `network_node_id`, `qty`) |
| Lokasi (breadcrumb) | `NetworkNode::breadcrumbLabel()` |
| Cabang untuk scope | `NetworkNode::resolvePop()`, `NetworkNode::scopeInPops()` |
| Kapasitas & port | `network_node_ports`, `capacity` |
| Bukti modem pelanggan (analog) | `inventory_serials.customer_id`, `installed_at` |
| Halaman node + aset | `/network/nodes` (blok "Aset Terpasang" per node) |

Belum ada: daftar datar lintas node, kolom tanggal pasang dan sumber task di listing,
ekspor, dan bukti foto/BAP saat deploy (belum diverifikasi di `InventoryService`).

## 3. Sumber tanggal pasang dan task

Tanggal pasang, teknisi, dan task asal tidak ada di `inventory_serials`. Ambil dari
ledger `inventory_transactions` tipe `DEPLOY` (`to_network_node_id`, `to_pop_id`,
`created_at`, referensi task). Listing perlu join ke ledger, dengan entri `DEPLOY`
terakhir per SN/lot yang masih `DEPLOYED` atau qty > 0.

## 4. Prototipe `peta-infrastruktur-isp.html`: temuan

Catatan: file 448 KB, sebagian besar bundle JS minified. Yang dibaca: token warna
(CSS variable), struktur tab, dan komponen tabel. Belum dilihat sebagai screenshot.

### 4.1 Struktur halaman
- Header: judul "Peta Infrastruktur ISP", subjudul "Kapasitas, slot kosong, dan topologi
  dari pusat sampai ONT pelanggan", kolom pencarian (lebar 240 px), tombol Tampilan.
- Tab `role="tablist"` berlabel "Tampilan": **Topologi** dan **Inventaris**. Untuk
  fitur ini cukup tab Inventaris.
- Strip ringkasan di atas konten: "Pelanggan (ONT)", "Port PON terpakai",
  "Slot OLT kosong" (angka mono).
- Sidebar kiri: chip filter tipe perangkat (`role="group"`, `aria-label="Filter tipe perangkat"`,
  `aria-pressed` per chip).
- Area kanan: panel detail per perangkat (kartu `iv` berjudul "Kapasitas", "Peta port",
  "Pelanggan", "Slot dan port PON"). Dipakai juga untuk detail aset.
- Legenda warna: `aria-label="Legenda warna"`, bulat kecil per tipe.

### 4.2 Tabel Inventaris
- Kolom: **Perangkat, Lokasi, Terpakai / kapasitas, Kosong, Utilisasi, Status**.
- Lebar minimum tabel 760 px, di dalam wrapper `overflow-x-auto`, kartu `bg-card`
  dengan border.
- Header: `bg-muted/50`, teks `text-muted-foreground`, label `tag` (uppercase kecil).
- Sort (`inv-sort`, lebar 190 px): **Urutan topologi** (default), **Utilisasi tertinggi**,
  **Kosong terbanyak**. Untuk kita: ganti "Urutan topologi" dengan "Lokasi (A-Z)" atau
  "Tanggal pasang terbaru".
- Pencarian: nama, model, serial, paket, alamat, catatan, tipe.
- Empty state: kalimat ringkas di kartu `max-w-sm`, `text-muted-foreground`.

### 4.3 Status dan warna
- Status: `aktif` Aktif, `rencana` Rencana, `gangguan` Gangguan, `nonaktif` Nonaktif.
  Untuk aset gudang: `DEPLOYED` (sukses) dan qty 0 atau dicabut (nonaktif).
- Token warna (light/dark, `:root` dan `[data-theme=dark]`):
  - Umum: `--background` 210 22% 95% (dark 214 30% 8%), `--primary` 214 55% 22%
    (dark 197 80% 56%), `--border` 212 18% 82% (dark 214 20% 21%), `--signal` 197 90% 36%.
  - Status: `--ok` 152 55% 30%, `--warn` 34 92% 38%, `--crit`/`--destructive` 2 72% 46%.
  - Tipe perangkat (`--c-*`): dc, core, service, transport, pop, agg, olt, pon, feeder,
    odp, drop, ont (`--c-ont` hanya di dark, 332 70% 70%).
- Font: IBM Plex Sans (teks), IBM Plex Sans Condensed (judul), IBM Plex Mono (angka,
  serial). Dimuat dari Google Fonts.
- Radius `--radius: .375rem`.

### 4.4 Yang tidak dipakai dari prototipe
- Tab Topologi dan SVG diagram.
- Editor perangkat (form tambah ONT, pasang pelanggan, edit serial di panel). Di sistem
  kita, mutasi lewat halaman create tersendiri sesuai `CLAUDE.md`.
- Data mock (`Math.random`, generator ONT).

## 5. Pemetaan ke implementasi Laravel

- Blade + Alpine (bukan React). Ambil layout dan interaksi, bukan kodenya.
- Token warna jadi CSS variable di `resources/css/app.css` (Tailwind 4), sesuai pola yang
  sudah ada.
- Route baru: `/network/inventaris` (atau tab di `/network/nodes`), permission
  `network_nodes.view`, query lewat `scopeInPops()` dengan `EffectiveAccessService`.
- Filter tipe dan sort: query string, bukan state klien saja, supaya bisa dibagikan.
- Ekspor CSV/PDF untuk bukti: sesuai pola ekspor yang sudah ada di repo.
- Test wajib: scope POP, filter tipe, pencarian serial, qty > 0 untuk ROLL/QUANTITY, dan
  urutan sort.

## 6. Keputusan user (2026-10-13)

1. **Bukti = barang yang dipakai + tanggal pasang + teknisi + task.** Tidak wajib foto/BAP.
   Semua data ini ada di ledger `DEPLOY`, jadi alur O-REQ/INFR REQ tidak berubah.
2. **Prototipe adalah desain resmi.** Ikuti layout tab Inventaris.
3. **Cakupan:** lihat §7.

## 7. Cakupan: per cabang vs lintas cabang

- **Per cabang:** daftar hanya berisi node yang anchor-nya berada di satu cabang
  (atau Mini POP di bawah cabang itu).
- **Lintas cabang:** daftar berisi semua node dalam scope user. Owner dan atasan
  (`all_pop`) melihat semua cabang. Admin cabang melihat cabangnya saja.

Keduanya bukan dua halaman. Satu halaman dengan filter cabang. Default-nya cakupan
scope user, dan user dengan scope lebih dari satu cabang dapat dropdown cabang.

Aturan pembeda:
- **Posisi sekarang** memakai cabang saat ini (`resolvePop()` lewat anchor).
- **Riwayat** memakai snapshot `to_pop_id` di ledger. Barang yang dipasang di cabang X
  bulan lalu tetap tercatat di X walau node sudah dipindah ke Y.

**Keputusan user (2026-10-13): lintas cabang.** Satu halaman, cakupan = semua node dalam
scope user, dengan filter cabang sebagai opsi. Ekspor per cabang dilakukan lewat filter
cabang yang sama (bukan fitur terpisah).
