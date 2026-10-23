# Spec: Jual Perangkat Retur Ber-SN Antar OLT/Cabang melalui Gudang Pusat

Status: **Rancangan disetujui user; belum diimplementasikan.** Tanggal awal: 2026-10-12; keputusan lanjutan: 2026-10-23. Cakupan: Gap 5 pada analisa ownership modem dan rincian billing C-REQ. Task aktif ADHOC-202 tidak berubah.

## 1. Tujuan dan keputusan

Gudang Pusat dapat menjual perangkat retur ber-SN yang layak pakai, berasal dari OLT/cabang A, untuk digunakan OLT/cabang B. **Dalam konteks Gudang, OLT berarti Cabang (`pops.type=cabang`), bukan perangkat OLT atau Mini POP pada modul Infrastruktur.** Aplikasi mencatat asal Cabang, harga, perpindahan fisik, penerimaan, status pembayaran, dan riwayat SN. Investor terkait OLT tetap di luar aplikasi; aplikasi ini khusus operasional dan tidak menentukan penjual formal, pajak, atau jurnal keuangan.

Keputusan user:

- Harga jual ditentukan dan diinput **hanya Gudang Pusat**.
- Barang boleh dikirim dan diterima sebelum pembayaran dilakukan.
- Setelah SN yang benar diterima secara fisik, barang boleh di-issue/dipasang untuk OLT tujuan walaupun pembayaran belum lunas.
- Barang rusak tidak boleh dijual; Gudang/PIC Cabang tidak dapat memulai jual-beli atau transfer antar cabang.
- Penerimaan parsial dapat dilanjutkan untuk SN yang datang belakangan; pembalikan dicatat per SN tanpa menghapus histori jual atau pembayaran.

Status fisik dan status pembayaran adalah dua alur independen. Pembayaran tertunda ditindaklanjuti Pusat, bukan pengunci stok.

## 2. Cakupan

Masuk spec: barang retur dengan Item.tracking_type=serialized dan satu InventorySerial per unit, baik serial_number dari vendor (Item.auto_generate_serial=false) maupun kode serial yang dibuat sistem (true). Keduanya diperlakukan sama setelah masuk inventory_serials. Kandidat jual harus AVAILABLE di Gudang Pusat, kondisi final used_good, dan condition_checked_at terisi. Asal retur dapat berupa DEAC, Ganti Modem/Migrasi, atau walk-in setelah konfirmasi final Pusat.
Perangkat ber-ID buatan sistem tidak mendapat pengecualian: ia harus mempunyai jejak retur fisik dan inspeksi final Pusat yang sama. Bila kategori perangkat tersebut belum memiliki jalur retur dari lokasi pemasangannya, dukungan jalur retur itu menjadi prasyarat sebelum SN-nya ditawarkan untuk sale; sistem tidak boleh membuat stok AVAILABLE fiktif di Pusat.

Di luar spec: tracking_type=quantity, kabel tracking_type=roll, barang kondisi new, barang rusak/scrap, jual langsung ke pelanggan, master investor, penagihan/pembayaran pelanggan, kas/bank, general ledger, dokumen pajak, dan otomatisasi penyelesaian uang kepada investor. Laporan operasional boleh memperlihatkan nilai jual dan status pembayaran, tetapi tidak mengklaim angka itu sebagai pendapatan perusahaan.

## 3. Pilihan arsitektur

**Dipilih: perluas InventoryTransfer dua fase dengan mode sale.** Dispatch Pusat→Gudang Cabang dan konfirmasi fisik Cabang tetap menjadi satu-satunya mutasi stok. Metadata jual melekat pada transfer; harga jual per SN dibekukan pada baris dispatch. Ledger inventory_transactions tetap sumber riwayat fisik, tanpa sumber stok kedua.

Transaksi jual terpisah sepenuhnya akan memerlukan sinkronisasi jual dan transfer fisik. Transfer biasa tanpa mode jual tidak bisa memisahkan laporan, harga, dan akses. Satu transfer sale mempunyai **satu Cabang asal dan satu Cabang tujuan**, tetapi boleh memuat beberapa SN dari asal yang sama. Barang dari asal berbeda harus memakai transaksi lain.

## 4. Asal operasional dan lokasi fisik

| Identitas | Makna |
|---|---|
| source_operational_pop_id | Cabang asal barang sebelum jual; retur ke Pusat tidak mengubahnya. |
| from_pop_id | Gudang Pusat yang secara fisik mengirim. |
| to_pop_id | Cabang tujuan sekaligus Gudang Cabang yang secara fisik menerima. |

`source_operational_pop_id` dan `to_pop_id` wajib menunjuk dua Cabang yang berbeda; `from_pop_id` wajib Pusat. Tidak ada target Mini POP atau perangkat OLT tersendiri dalam transaksi Gudang. Lokasi infrastruktur yang lebih rinci tetap dicatat saat pemasangan, tetapi tidak menentukan identitas pihak asal/tujuan jual. `issued_from_pop_id` tidak boleh menjadi asal jual karena field itu berarti gudang ISSUE terakhir, bukan Cabang yang membeli barang.

Untuk tiap InventorySerial, `accountable_pop_id` mencatat Cabang operasional yang bertanggung jawab. Pada penerimaan/registrasi awal untuk Cabang tertentu, Pusat mengisinya berdasarkan asal pembelian atau penugasan operasional. Retur ke Pusat tidak mengubahnya. Bila serial lama belum mempunyai asal, Pusat harus melakukan atribusi asal eksplisit dengan alasan dan audit sebelum boleh dijual; tidak ada backfill otomatis dari lokasi, pelanggan terakhir, atau `issued_from_pop_id`. Setelah SN diterima fisik di Cabang tujuan sale, `accountable_pop_id` berubah ke `to_pop_id` secara atomik dengan konfirmasi penerimaan. SN yang belum diterima tetap teratribusi ke Cabang asal.

InventorySerial.ownership (company/customer) tetap hanya untuk kewajiban retur pelanggan. Serial milik customer tidak memenuhi syarat jual antar OLT.

## 5. Kontrak data

- inventory_serials: `accountable_pop_id` nullable FK pops bertipe Cabang bila terisi, terpisah dari `current_pop_id` dan `ownership`. Perubahan hanya lewat service yang diaudit.
- inventory_transfers: `purpose` restock/return_to_pusat/sale. Migrasi mengisi data lama menurut arah transfer: Pusat→Cabang menjadi restock, Cabang→Pusat menjadi return_to_pusat; arah lain harus diaudit, bukan ditebak. Transfer baru wajib menetapkan purpose sesuai service-nya. `source_operational_pop_id` wajib hanya untuk sale; Cabang tujuan sale diambil dari `to_pop_id`, tanpa kolom target OLT terpisah. `payment_status` unpaid/paid, `payment_reference`, `payment_confirmed_at`, dan `payment_confirmed_by` hanya berlaku untuk sale. `receipt_closed_at` dan alasan penutupan mencatat penutupan selisih penerimaan. TransferStatus tetap menyatakan status fisik.
- inventory_transactions pada baris dispatch sale: `sale_unit_price_snapshot` decimal positif per SN, terpisah dari `unit_price_snapshot` biaya/nilai stok. Total jual diturunkan dari snapshot dispatch, bukan harga terkini.
- Pembalikan sale memakai transfer balik Cabang→Pusat yang merujuk sale asal melalui `reverses_sale_transfer_id`; baris SN yang benar-benar diterima di Pusat menentukan bagian sale yang dibalik. Satu SN hanya boleh dibalik sekali. Bukti pengembalian uang, bila diperlukan, dicatat pada transfer pembalik sebagai status/referensi operasional terpisah dari status pembayaran sale asal.
- audit_logs: atribusi asal, dispatch sale, penerimaan, konfirmasi/koreksi pembayaran, dan pembalikan memuat aktor, waktu, transaksi/SN, serta perubahan relevan.

Satu SN hanya boleh masuk sekali dalam transfer dan tidak boleh berada pada dua transfer aktif. Validasi, lock serial/transfer, dan penulisan header, ledger, serta status harus atomik. Referential integrity mencegah penghapusan POP atau serial yang masih direferensikan transaksi.

Status pembayaran versi spec ini hanya unpaid/paid. Pusat menandai paid setelah memverifikasi **pembayaran penuh** dan menyimpan referensi bukti operasional. Pembayaran sebagian belum dicatat sebagai paid dan tidak mengubah hak pakai barang. Koreksi konfirmasi paid wajib beralasan dan tercatat audit, bukan menghapus histori.

## 6. Alur dan aturan server

1. Pusat memilih Cabang asal (`source_operational_pop_id`), Cabang tujuan sekaligus Gudang penerima (`to_pop_id`), dan SN eligible yang `accountable_pop_id`-nya sama dengan asal. Form hanya menampilkan kandidat sah; service mengulang seluruh validasi saat submit.
2. Pusat mengisi harga jual >0 **per SN**. Nilai taksiran retur atau biaya inventory tidak otomatis menjadi harga jual. Dispatch sekali membuat transfer purpose=sale dan ledger keluar; SN menjadi TRANSFERRED dan current_pop_id kosong. Harga dan asal/tujuan adalah snapshot yang tidak bisa diubah setelah dispatch.
3. Gudang Cabang hanya mengonfirmasi SN fisik yang cocok pada kiriman menuju gudangnya. Hanya SN yang diterima menjadi AVAILABLE, `current_pop_id` menunjuk Cabang, dan `accountable_pop_id` berubah ke Cabang tujuan. Jika sebagian belum datang, status `received_partial` tetap dapat menerima **hanya SN yang belum pernah dikonfirmasi**, dengan lock dan pemeriksaan ledger agar penerimaan ulang/bersamaan tidak menggandakan stok. Bila semua SN diterima, status menjadi `received`; Pusat dapat menutup selisih yang tidak terselesaikan dengan alasan dan audit, tanpa membuat stok tersedia fiktif. SN yang terbukti hilang ditandai LOST melalui penyelesaian selisih, bukan dibiarkan sebagai stok transit selamanya.
4. Sesudah penerimaan fisik, `InventoryIssueService::issue(Pop $cabang, ...)` memeriksa `accountable_pop_id` SN hasil sale sama dengan ID Cabang yang mengeluarkan barang; tidak perlu input OLT/Mini POP tambahan. Titik pemasangan/pemakaian juga memeriksa Cabang Task atau lokasi tujuan sama dengan `accountable_pop_id` untuk SN hasil sale. Guard lintas-Cabang yang sudah ada tetap berlaku. Payment unpaid tidak menjadi syarat Issue/pakai.
5. Pusat dapat mengonfirmasi pembayaran penuh setelah dispatch, baik sebelum maupun sesudah penerimaan fisik. Transaksi unpaid masuk daftar tindak lanjut Pusat; tidak ada tanggal jatuh tempo atau label overdue tanpa aturan tersendiri.

Server wajib menolak SN bukan di Pusat, bukan AVAILABLE, belum diperiksa, kondisi selain used_good, status DAMAGED/QUARANTINE/SCRAPPED, ownership=customer, asal kosong/tidak cocok, asal/tujuan bukan Cabang atau sama, harga kosong/tidak positif, transfer sudah ditutup, dan request jual oleh Cabang. UI menyembunyikan kandidat terlarang tetapi bukan pengaman tunggal. Alur restock Pusat→Cabang dan retur Cabang→Pusat yang lama tetap berjalan; keduanya tidak mewajibkan Cabang asal atau harga jual.

## 7. Selisih dan pembalikan

SN yang belum diterima tidak boleh diissue. Pusat menyelidiki selisih; SN tidak boleh ditandai diterima hanya agar stok terlihat seimbang. Penerimaan susulan diperbolehkan selama `receipt_closed_at` kosong. Setelah ditutup, perubahan status SN harus melalui penyelesaian selisih yang diaudit, bukan membuka ulang konfirmasi lama diam-diam.

Setelah dispatch, sale tidak bisa diedit atau dihapus. Pembalikan dilakukan **per SN** lewat transfer balik fisik yang merujuk sale asal. SN yang sudah di-Issue, dipasang, atau dipakai harus lebih dulu kembali melalui alur retur sah; barang rusak tidak boleh otomatis kembali menjadi stok layak jual. `accountable_pop_id` kembali ke Cabang asal hanya setelah penerimaan balik di Pusat dikonfirmasi. Sale asal dan konfirmasi pembayarannya tetap historis; laporan menghitung nilai jual awal, nilai SN yang telah dibalik, dan nilai jual bersih secara terpisah. SN yang pembalikannya baru dikirim tetapi belum diterima tetap dihitung aktif sampai penerimaan balik. Jika sale sudah dibayar, Pusat mencatat apakah pengembalian uang masih tertunda atau sudah selesai beserta referensi bukti eksternal; aplikasi tidak mencatat kas/bank atau jurnal.

## 8. Permission, scope, dan tampilan

Permission baru mengikuti format feature.action, misalnya warehouse_transfer.sale.create, warehouse_transfer.sale.view_price, dan warehouse_transfer.sale.confirm_payment. Grant diberikan hanya kepada operator Pusat yang berwenang; tidak membuat role per cabang atau memberi permission langsung ke user. Create/dispatch/payment memeriksa permission **dan** scope Gudang Pusat di route serta service. Scope all_pop saja tidak cukup. Cabang dapat mempunyai izin sempit melihat surat jalan dan mengonfirmasi kiriman ke gudangnya sendiri, tanpa hak jual, harga, atau data cabang lain.

Pusat mendapat daftar Sale Antar Cabang dengan filter periode, Cabang asal, Cabang tujuan, SN, status fisik, status pembayaran, dan status pembalikan/pengembalian uang; nilai jual awal, nilai dibalik, nilai bersih, daftar unpaid, dan selisih penerimaan. Cabang hanya mendapat kiriman masuk serta stok tersedia dalam scope-nya. Lacak Barang menampilkan kronologi SN, Cabang asal, retur ke Pusat, sale, penerimaan, pembalikan bila ada, dan pemakaian berikutnya. Laporan ini tidak digabung dengan Barang Terjual ke Pelanggan atau pembayaran pelanggan. Export mengikuti pembatasan permission dan scope yang sama dengan halaman.

Surat Jalan lama tetap tanpa harga. Endpoint PDF Invoice transfer lama wajib **menolak** `purpose=sale` pada server karena memakai `unit_price_snapshot` sebagai nilai inventory, bukan harga jual. Mode sale memakai dokumen nilai jual operasional tersendiri yang membaca `sale_unit_price_snapshot`, hanya untuk Pusat, berlabel bukan invoice pelanggan atau faktur pajak. Nomor transfer mengikat dokumen dan ledger.

## 9. Kriteria penerimaan

- SN vendor dan SN buatan sistem, keduanya tracking_type=serialized, mengikuti alur sale yang sama. Item quantity/roll dan kondisi new tidak tersedia.
- SN used_good yang sudah diperiksa di Pusat dengan accountable_pop_id valid dapat dijual; semua kondisi/status terlarang ditolak pada request langsung tanpa mutasi parsial.
- SN dari dua asal berbeda tidak dapat digabung dalam satu sale. Asal kosong memerlukan atribusi Pusat dengan alasan dan audit lebih dulu.
- Harga per SN diisi Pusat, >0, tersimpan sebagai snapshot terpisah dari biaya/nilai stok; Cabang tidak dapat membaca atau mengubahnya.
- Dispatch mengurangi stok Pusat sekali. Receive ulang/bersamaan tidak menggandakan stok Cabang. Receive parsial dapat dilanjutkan hanya untuk SN yang belum diterima; selisih yang ditutup tidak otomatis menjadi stok tersedia.
- Cabang asal dan tujuan harus berbeda serta bertipe Cabang; Mini POP atau perangkat OLT pada modul Infrastruktur tidak dapat dipilih sebagai pihak jual.
- SN diterima boleh diissue/dipakai untuk Cabang tujuan walau `payment_status=unpaid`. SN transit/selisih atau dialokasikan ke Cabang lain ditolak pada Issue dan titik pakai.
- Pusat dapat mencatat paid sesudah verifikasi penuh; unpaid tidak memblokir alur fisik. Tidak dibuat invoice/payment pelanggan, jurnal, atau investor master.
- User tanpa permission, PIC Gudang Cabang, dan user all_pop tanpa kewenangan Pusat mendapat 403 pada create/dispatch/payment; penerima Cabang hanya dapat mengonfirmasi kiriman ke gudangnya.
- Riwayat dan laporan Pusat menampilkan asal, tujuan, harga, status, aktor, waktu yang benar; Cabang tidak mendapat harga melalui halaman, export, PDF, atau respons API.
- Pembalikan per SN baru mengurangi nilai jual bersih setelah penerimaan balik fisik; SN yang sama tidak bisa dibalik dua kali. Status pengembalian uang untuk sale yang sudah dibayar terlihat tanpa mengubah histori pembayaran asal.
- Endpoint Invoice transfer biasa menolak `purpose=sale`; dokumen nilai jual khusus hanya membaca snapshot harga jual dan hanya tersedia bagi Pusat.

## 10. Referensi

- docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md — Gap 5 dan keputusan user.
- docs/plan/warehouse/rancangan-invoice-surat-jalan-transfer.md — transfer fisik dan PDF existing.
- docs/plan/warehouse/rancangan-retur-ke-pusat-dan-modem-rusak.md — retur tiga tahap dan inspeksi final Pusat.
- docs/plan/warehouse/analisa-generate-id-barang-non-serial.md — serial vendor dan kode buatan sistem.
- docs/warehouse/business-logic.md dan docs/warehouse/database-schema.md — perilaku serta skema berjalan.
