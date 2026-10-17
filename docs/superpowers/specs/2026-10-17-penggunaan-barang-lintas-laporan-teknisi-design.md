# Rancangan Penggunaan Barang Lintas Laporan Teknisi

Tanggal: 2026-10-17
Status: Rancangan untuk review user; belum menjadi task implementasi.
Sprint aktif: S8.10-T003 (FOP Notification Dashboard). Status task tersebut tidak berubah.

## 1. Tujuan dan keputusan yang sudah ada

Survey, Pemasangan, C-REQ, Maintenance, O-REQ, INFR-REQ, dan DEAC perlu mencatat barang menurut kejadian lapangan tanpa menambah field khusus setiap muncul barang atau kasus baru. Satu pekerjaan dapat memuat beberapa barang, beberapa tindakan, dan hasil berbeda per barang. Catatan harus dapat ditelusuri ke stok, lokasi, pemilik, pelanggan atau node, serta biaya bila relevan.

Keputusan user 2026-10-17: **pekerjaan fisik, termasuk pemasangan atau penyerahan barang, boleh mendahului persetujuan harga**. Harga akhir dan invoice mengikuti verifikasi biaya. Penolakan biaya tidak menghapus kejadian fisik.

**Rekomendasi yang belum diputuskan user:** pekerjaan fisik boleh ditandai selesai saat barang yang dilaporkan belum cocok dengan custody, dengan kasus rekonsiliasi terbuka dan tanpa mutasi stok resmi untuk baris yang belum terbukti. Rinciannya ada di §8.

Rancangan ini hanya dokumentasi lintas modul. Implementasi membutuhkan task tersendiri; tidak mengubah kode, skema, permission, atau urutan sprint sekarang.

## 2. Kondisi sistem yang harus dipertahankan

- Survey sudah menyimpan estimasi material terstruktur pada task_materials. Estimasi tidak mengurangi stok.
- Pemasangan dan Maintenance memakai custody tim, InventoryService untuk material/SN/roll, dan guard asal Cabang.
- C-REQ dan Maintenance berbayar memakai verifikasi biaya, task_billing_items, serta invoice manual. Hubungan penjualan SN ke verifikasi biaya masih mengambil satu InventorySerialSale per task; form kepemilikan barang masih berpusat pada modem.
- O-REQ/INFR-REQ sudah memiliki aksi infrastruktur jamak, idempotensi, ledger, dan validasi node, port, slot, SN, jumlah, serta roll. Rancangan ini mempertahankannya. Menurut TASKS.md ADHOC-193, migrasi database target masih perlu diterapkan dan diverifikasi.
- DEAC memakai laporan khusus, dapat melaporkan beberapa SN, dan mempunyai retur fisik bertahap. Review FOP yang menolak laporan tidak membalik perpindahan fisik yang sudah terjadi.
- ADHOC-190 telah memutuskan AP/STB tambahan milik kantor, disewakan bersama paket, diambil dari Gudang, dan wajib ditarik saat DEAC. Jumlah dicek saat assign/jadwal; kekurangan setelah issue dapat menjadi pengecualian beralasan. Rancangan ini tidak mengubah keputusan itu.

Rujukan: docs/task-teknisi/business-logic.md, docs/task-teknisi/database-schema.md, docs/warehouse/business-logic.md, docs/superpowers/specs/2026-10-12-laporan-oreq-infr-req-design.md, dan docs/superpowers/specs/2026-10-16-perangkat-tambahan-gudang-design.md.

## 3. Analisis tiap laporan

| Laporan | Keadaan yang harus bisa dicatat | Risiko jika form tetap kaku |
| --- | --- | --- |
| Survey | Beberapa estimasi, alternatif barang, jumlah perkiraan, kebutuhan belum ada di master, dan perubahan rencana | Estimasi disalahartikan sebagai pemakaian; rencana tidak dapat dibandingkan dengan hasil. |
| Pemasangan | Sebagian barang dipasang, sebagian tidak; SN berbeda dari rencana; barang sewa, jual, atau milik pelanggan | Barang menggantung di custody, status pemilik keliru, atau satu kekurangan mengunci seluruh laporan. |
| C-REQ | Jual CCTV/barang lain, sewa perangkat, ganti modem, pakai kabel, serta jasa dalam satu kunjungan | Penjualan tidak tertaut ke SN/invoice atau hanya penjualan pertama yang terbaca. |
| Maintenance | Pasang pengganti, cabut unit lama, pakai material, catat nasib unit lama, gratis atau berbayar | Stok baru berkurang tetapi unit lama dan kewajiban retur hilang dari jejak. |
| O-REQ | Inspeksi tanpa barang, aksi jamak pada node/port, penggantian, koreksi versus cabut fisik | Barang tercatat pada lokasi salah atau dihitung ganda lewat material umum dan infrastruktur. |
| INFR-REQ | SN, qty, roll, port, slot pada beberapa lokasi dan cabutan yang menunggu pemeriksaan | Saldo node menyimpang; cabutan dianggap siap pakai terlalu cepat. |
| DEAC | Hasil per perangkat kantor: diambil, tidak ditemukan, ditolak, atau perlu tindak lanjut | Satu status pelanggan menutupi unit yang belum kembali; barang pelanggan ikut ditarik. |

Kolom risiko adalah analisis skenario, bukan klaim bahwa semua skenario sudah terjadi sebagai bug.

## 4. Pendekatan

Pilihan A, menambah field khusus per barang/laporan, cepat untuk satu kasus tetapi terus mengulang perubahan. Pilihan B, form builder sepenuhnya bebas, fleksibel tampilannya namun tidak menjamin stok, pemilik, node, atau tagihan benar. **Pilihan C direkomendasikan: kontrak baris aktivitas barang bersama, dengan tindakan yang dibatasi per jenis laporan.** UI dapat memakai pola baris yang konsisten, sementara service domain tetap menegakkan aturan spesifik.

Setiap baris aktivitas memuat identitas persisten, task/laporan asal, item master atau kebutuhan belum dimaster, tindakan, jumlah dan satuan atau SN/roll, sumber fisik, tujuan pelanggan/node, hasil, waktu kejadian, aktor, bukti/catatan, dan referensi transaksi yang dihasilkan. Persyaratan field mengikuti tindakan serta jenis pelacakan item: SERIALIZED satu SN per unit, QUANTITY sejumlah unit, ROLL identitas roll dan panjang. Nama/satuan penting disimpan sebagai snapshot agar histori tidak berubah saat master diedit.

Baris laporan **bukan sumber saldo kedua**. Ledger inventory_transactions, posisi InventorySerial, custody/roll, dan saldo infrastruktur tetap dimutasi melalui service yang berlaku. Baris laporan merujuk transaksi fisik tersebut. Rincian biaya tetap milik alur billing. Skema tabel konkret diputuskan setelah audit relasi yang ada agar task_materials dan TaskInfrastructureAction tidak disalin tanpa alasan.

## 5. Empat makna yang harus dipisah

1. **Rencana:** estimasi Survey atau kebutuhan yang disiapkan FOP; tidak memindahkan stok.
2. **Kejadian lapangan:** dilihat, dipasang, dipakai, dicabut, diserahkan, tidak dipasang, atau tidak ditemukan.
3. **Posisi dan kepemilikan fisik:** Gudang, custody, pelanggan, node, transit; milik perusahaan atau pelanggan.
4. **Keuangan:** calon biaya, harga disetujui, invoice, pembayaran.

Status task/pekerjaan fisik, review FOP, rekonsiliasi Gudang, verifikasi harga, invoice, dan pembayaran tidak boleh saling menimpa. Satu task dapat selesai secara fisik ketika pekerjaan administrasi lain masih terbuka.

## 6. Tindakan per laporan

- **Survey:** catat estimasi dan perubahan beralasan. Barang “lainnya” menjadi kandidat master, tanpa mutasi stok. Data survey lama tetap terbaca.
- **Pemasangan:** pilih dari custody; catat hasil tiap unit: dipasang, tetap dibawa, dikembalikan, rusak/hilang, atau kekurangan beralasan sesuai keputusan paket. Bedakan perangkat utama dari perangkat tambahan serta kantor/sewa dari pelanggan/beli.
- **C-REQ dan Maintenance:** izinkan beberapa baris pasang, jual/serah, pakai, cabut, kembali, dan jasa pada satu task. Tindakan berbayar membuat kandidat verifikasi, bukan langsung invoice.
- **O-REQ/INFR-REQ:** gunakan aksi/service infrastruktur yang sudah ada. Inspeksi tidak mengubah stok; koreksi administratif berbeda dari cabut; barang cabutan menunggu inspeksi sebelum menjadi stok tersedia.
- **DEAC:** tampilkan kewajiban tarik per unit milik perusahaan; catat hasil masing-masing. Unit milik pelanggan tidak menjadi kewajiban tarik. Unit terambil mengikuti transit teknisi → Cabang → Pusat sesuai aturan retur sekarang.

## 7. Contoh gabungan C-REQ

Pelanggan meminta CCTV dari perusahaan, AP sewa, dan kabel. Gudang meng-issue ketiganya. Teknisi memasang CCTV dan AP, memakai kabel, serta mencatat hasil aktual pada baris berbeda. CCTV menjadi milik pelanggan; AP tetap milik kantor; kabel menjadi material terpakai. Bila modem lama juga dicabut, hasilnya menjadi baris lain dan masuk transit retur.

Pekerjaan fisik selesai tanpa menunggu harga. Verifikator biaya kemudian menetapkan harga CCTV dan jasa yang relevan serta menerbitkan invoice dengan rincian yang merujuk tepat sekali ke tiap penjualan. AP sewa yang sudah termasuk paket tidak otomatis dijual. DEAC kelak menarik AP, bukan CCTV. Penolakan atau koreksi biaya tidak menghapus pemasangan.

Hubungan InventorySerialSale ke task/verifikasi harus mendukung banyak penjualan, bukan first() satu sale. Setiap sale yang ditagih muncul tepat satu kali pada rincian invoice. Barang tambahan tidak boleh menimpa profil perangkat utama pelanggan.

## 8. Selisih lapangan dan kelanjutan operasional — rekomendasi menunggu keputusan

Rekomendasi: **pisahkan “pekerjaan fisik selesai” dari “barang terekonsiliasi”.** Bila teknisi benar-benar memasang/memakai barang tetapi sistem tidak menemukan custody yang cocok, teknisi memilih alasan selisih, mencatat item/SN/qty fisik, asal yang diketahui, waktu, dan bukti. Task dapat selesai secara fisik agar teknisi lanjut bekerja. Sistem membuka antrean rekonsiliasi untuk FOP/Gudang. Klaim tersebut belum menjadi mutasi stok, status SN terpasang resmi, atau aset node/pelanggan yang terverifikasi.

Penyelesaian antrean harus memilih hasil yang terlacak: cocokkan ke issue/custody/SN sah lalu mutasikan stok; koreksi identitas atau jumlah dengan audit; pastikan barang milik pelanggan dan tidak menggunakan stok kantor; atau nyatakan konflik untuk investigasi. Klaim awal tidak dihapus. Penjualan barang yang asalnya belum terbukti belum menjadi invoice final; pekerjaan fisiknya tetap tercatat. Status antrean, penanggung jawab, dan umur kasus harus tampak, agar jalur pengecualian tidak menjadi tempat selisih terlupakan.

Kontrol hulu tetap berlaku. Untuk AP/STB paket menurut ADHOC-190, FOP memeriksa jumlah custody saat assign/jadwal; selisih lapangan adalah pengecualian setelah issue, bukan jalur normal pengganti Gudang.

Jika user memutuskan task harus tetap tertahan sampai stok cocok, bagian ini harus direvisi sebelum implementasi. Rekomendasi tidak dianggap persetujuan otomatis.

## 9. Kasus lain yang wajib diselesaikan

| Kasus | Aturan rancangan |
| --- | --- |
| Pekerjaan selesai sebagian atau perlu kunjungan ulang | Simpan hasil per baris dan buat tindak lanjut pekerjaan sisa; jangan samakan hasil semua barang. |
| Barang dibawa tetapi tidak dipasang | Tetap terlihat dalam custody atau ikuti alur kembali/rusak/hilang; jangan tandai terpasang. |
| Barang lama dicabut saat penggantian | Catat pemasangan baru dan transit unit lama terpisah dalam task yang sama. |
| SN tidak cocok, item belum ada di master, atau stok kurang | Jangan buat stok/SN fiktif; simpan klaim fisik untuk rekonsiliasi sesuai §8 jika kejadian sudah berlangsung. |
| Pengiriman ulang karena jaringan atau review gagal | Kunci idempoten per aksi mencegah ledger, sale, dan invoice ganda. Draf serta unggahan yang berhasil tidak hilang. Offline penuh perlu rancangan tersendiri. |
| Review FOP menolak laporan setelah barang berpindah | Perbaiki laporan tanpa menghapus perpindahan fisik. Kesalahan mutasi dibetulkan dengan transaksi pembalik berreferensi. |
| Harga berubah/ditolak sesudah serah terima | Kasus komersial tetap terbuka; catatan serah terima tidak dihapus. Harga final hanya ditetapkan petugas berwenang. |
| POP, tim, atau lokasi berubah | Scope dan asal issue dicek pada aksi fisik. Perubahan task tidak otomatis memindahkan stok; gunakan transfer sah. |
| Barang kantor, terjual, atau milik pelanggan sendiri | Kewajiban DEAC dan invoice mengikuti kepemilikan per unit. Barang pelanggan sendiri tidak menjadi stok Gudang. |
| Material pelanggan dan infrastruktur dipilih bersamaan | Item/SN/roll sama tidak boleh dikonsumsi dua jalur. Validasi node/port/slot tetap berlaku. |

## 10. Akses, audit, dan tampilan

Teknisi mencatat fakta pekerjaan; FOP meninjau hasil dan selisih; Gudang memastikan asal, lokasi, serta kondisi; petugas verifikasi biaya menetapkan nominal dan menerbitkan invoice. Izin mengikuti matrix role dan scope POP, tanpa permission langsung ke user atau role per Cabang. List, detail, export, dan aksi harus membatasi pelanggan, task, barang, dan node yang terlibat.

Form menampilkan tindakan yang relevan saja. Sebelum submit, teknisi melihat ringkasan terpasang, tidak terpasang, kembali, dan perlu tindak lanjut. Sesudah submit, Detail Task, riwayat FOP, Detail Pelanggan atau Peta Infrastruktur, Gudang, dan verifikasi biaya menunjukkan hasil yang konsisten sesuai hak masing-masing. Audit mencatat aktor, waktu kejadian dan pencatatan, nilai awal/baru, serta referensi koreksi; histori lama tidak dihapus.

## 11. Urutan implementasi bila rancangan disetujui

1. Audit kontrak data: petakan laporan, ledger, device, sale, invoice, dan status yang sudah ada. Tentukan hubungan tunggal tanpa saldo ganda.
2. Putuskan status pengecualian §8, penanggung jawab antrean, batas waktu, dan eskalasi.
3. Bangun lintasan sempit C-REQ/MTN untuk beberapa barang jual/sewa/ganti dalam satu task, termasuk harga sesudah pekerjaan dan hubungan invoice per sale.
4. Selaraskan Pemasangan dan DEAC per perangkat sesuai ADHOC-190 serta retur yang berjalan.
5. Selaraskan Survey dan tampilan O-REQ/INFR-REQ dengan pola bersama tanpa melemahkan validasi infrastrukturnya.
6. Migrasi data lama secara aman, tes regresi tiap laporan, lalu terapkan bertahap. Jangan backfill kepemilikan atau stok dari tebakan.

Setiap langkah memerlukan task implementasi tersendiri. Tidak ada perubahan kode atau perpindahan sprint dari dokumen ini.

## 12. Kriteria penerimaan

- Satu task dapat memuat beberapa item, tindakan, dan hasil tanpa kehilangan SN/roll/qty masing-masing.
- Estimasi Survey tidak mengubah stok; aksi fisik memakai service/ledger yang sesuai.
- Barang kantor sewa, barang terjual, dan barang milik pelanggan menghasilkan kewajiban DEAC yang benar.
- Pekerjaan fisik, review FOP, rekonsiliasi Gudang, harga, invoice, dan pembayaran dapat memiliki status berbeda tanpa saling menimpa.
- Kirim ulang, review ulang, dan koreksi tidak membuat transaksi/tagihan ganda; histori asli tetap dapat dilihat.
- Selisih tidak membuat stok atau tagihan fiktif dan memiliki penanggung jawab yang terlihat.
- POP scope, matrix role, guard lintas Cabang, serta validasi node/port/slot tetap berlaku.
- Data dan laporan lama tetap dapat dibaca selama transisi.

## 13. Keputusan yang masih diperlukan

1. Apakah pekerjaan fisik boleh selesai dengan rekonsiliasi barang terbuka sebagaimana rekomendasi §8? User belum memutuskan.
2. Siapa pemilik antrean untuk tiap jenis selisih, beserta tenggat dan eskalasinya?
3. Apakah klaim sementara atas barang yang belum terdaftar berlaku untuk semua laporan fisik atau hanya kasus tertentu?
4. Bila satu task berisi barang jual yang belum terekonsiliasi dan jasa yang sudah pasti, apakah seluruh invoice menunggu atau jasa boleh ditagih terpisah? Harus mencegah tagihan ganda saat barang kemudian disahkan.

Pertanyaan tersebut tidak mengubah keputusan yang telah dibuat pada ADHOC-190 dan alur O-REQ/INFR-REQ.
