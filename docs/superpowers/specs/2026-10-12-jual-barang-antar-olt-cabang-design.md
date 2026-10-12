# Spec: Jual Perangkat Retur Ber-SN Antar OLT/Cabang melalui Gudang Pusat

Status: **Spec untuk review user; belum diimplementasikan.** Tanggal: 2026-10-12. Cakupan: Gap 5 pada analisa ownership modem dan rincian billing C-REQ. Task aktif S8.10-T003 tidak berubah.

## 1. Tujuan dan keputusan

Gudang Pusat dapat menjual perangkat retur ber-SN yang layak pakai, berasal dari OLT/cabang A, untuk digunakan OLT/cabang B. Aplikasi mencatat asal operasional, harga, perpindahan fisik, penerimaan, status pembayaran, dan riwayat SN. Investor terkait OLT tetap di luar aplikasi; aplikasi ini khusus operasional dan tidak menentukan penjual formal, pajak, atau jurnal keuangan.

Keputusan user:

- Harga jual ditentukan dan diinput **hanya Gudang Pusat**.
- Barang boleh dikirim dan diterima sebelum pembayaran dilakukan.
- Setelah SN yang benar diterima secara fisik, barang boleh di-issue/dipasang untuk OLT tujuan walaupun pembayaran belum lunas.
- Barang rusak tidak boleh dijual; Gudang/PIC Cabang tidak dapat memulai jual-beli atau transfer antar cabang.

Status fisik dan status pembayaran adalah dua alur independen. Pembayaran tertunda ditindaklanjuti Pusat, bukan pengunci stok.

## 2. Cakupan

Masuk spec: barang retur dengan Item.tracking_type=serialized dan satu InventorySerial per unit, baik serial_number dari vendor (Item.auto_generate_serial=false) maupun kode serial yang dibuat sistem (true). Keduanya diperlakukan sama setelah masuk inventory_serials. Kandidat jual harus AVAILABLE di Gudang Pusat, kondisi final used_good, dan condition_checked_at terisi. Asal retur dapat berupa DEAC, Ganti Modem/Migrasi, atau walk-in setelah konfirmasi final Pusat.
Perangkat ber-ID buatan sistem tidak mendapat pengecualian: ia harus mempunyai jejak retur fisik dan inspeksi final Pusat yang sama. Bila kategori perangkat tersebut belum memiliki jalur retur dari lokasi pemasangannya, dukungan jalur retur itu menjadi prasyarat sebelum SN-nya ditawarkan untuk sale; sistem tidak boleh membuat stok AVAILABLE fiktif di Pusat.

Di luar spec: tracking_type=quantity, kabel tracking_type=roll, barang kondisi new, barang rusak/scrap, jual langsung ke pelanggan, master investor, penagihan/pembayaran pelanggan, kas/bank, general ledger, dokumen pajak, dan otomatisasi penyelesaian uang kepada investor. Laporan operasional boleh memperlihatkan nilai jual dan status pembayaran, tetapi tidak mengklaim angka itu sebagai pendapatan perusahaan.

## 3. Pilihan arsitektur

**Dipilih: perluas InventoryTransfer dua fase dengan mode sale.** Dispatch Pusat→Gudang Cabang dan konfirmasi fisik Cabang tetap menjadi satu-satunya mutasi stok. Metadata jual melekat pada transfer; harga jual per SN dibekukan pada baris dispatch. Ledger inventory_transactions tetap sumber riwayat fisik, tanpa sumber stok kedua.

Transaksi jual terpisah sepenuhnya akan memerlukan sinkronisasi jual dan transfer fisik. Transfer biasa tanpa mode jual tidak bisa memisahkan laporan, harga, dan akses. Satu transfer sale mempunyai **satu OLT/cabang asal dan satu OLT/cabang tujuan**, tetapi boleh memuat beberapa SN dari asal yang sama. Barang dari asal berbeda harus memakai transaksi lain.

## 4. Asal operasional dan lokasi fisik

| Identitas | Makna |
|---|---|
| source_operational_pop_id | OLT/cabang asal barang sebelum jual; retur ke Pusat tidak mengubahnya. |
| from_pop_id | Gudang Pusat yang secara fisik mengirim. |
| to_pop_id | Gudang Cabang yang secara fisik menerima. |
| target_operational_pop_id | OLT/cabang tujuan penggunaan setelah penerimaan. |

POP operasional asal dan tujuan boleh Cabang atau Mini POP. Gudang penerima harus Cabang. Jika tujuan Mini POP, cabang induknya harus sama dengan to_pop_id menurut Pop::resolveCabangId(). issued_from_pop_id tidak boleh menjadi asal jual karena field itu berarti gudang ISSUE terakhir, bukan unit yang membeli barang.

Untuk tiap InventorySerial, accountable_pop_id mencatat unit operasional yang bertanggung jawab. Pada penerimaan/registrasi awal untuk unit tertentu, Pusat mengisinya berdasarkan asal pembelian atau penugasan operasional. Retur ke Pusat tidak mengubahnya. Bila serial lama belum mempunyai asal, Pusat harus melakukan atribusi asal eksplisit dengan alasan dan audit sebelum boleh dijual; tidak ada backfill otomatis dari lokasi, pelanggan terakhir, atau issued_from_pop_id. Setelah SN diterima fisik di tujuan sale, accountable_pop_id berubah ke target_operational_pop_id secara atomik dengan konfirmasi penerimaan. SN yang belum diterima tetap teratribusi ke asal.

InventorySerial.ownership (company/customer) tetap hanya untuk kewajiban retur pelanggan. Serial milik customer tidak memenuhi syarat jual antar OLT.

## 5. Kontrak data

- inventory_serials: accountable_pop_id nullable FK pops, terpisah dari current_pop_id dan ownership. Perubahan hanya lewat service yang diaudit.
- inventory_transfers: purpose restock/return_to_pusat/sale. Migrasi mengisi data lama menurut arah transfer: Pusat→Cabang menjadi restock, Cabang→Pusat menjadi return_to_pusat; arah lain harus diaudit, bukan ditebak. Transfer baru wajib menetapkan purpose sesuai service-nya. source_operational_pop_id dan target_operational_pop_id wajib hanya untuk sale; payment_status unpaid/paid, payment_reference, payment_confirmed_at, dan payment_confirmed_by hanya berlaku untuk sale. TransferStatus tetap menyatakan status fisik.
- inventory_transactions pada baris dispatch sale: sale_unit_price_snapshot decimal positif per SN, terpisah dari unit_price_snapshot biaya/nilai stok. Total jual diturunkan dari snapshot dispatch, bukan harga terkini.
- audit_logs: atribusi asal, dispatch sale, penerimaan, konfirmasi/koreksi pembayaran, dan pembalikan memuat aktor, waktu, transaksi/SN, serta perubahan relevan.

Satu SN hanya boleh masuk sekali dalam transfer dan tidak boleh berada pada dua transfer aktif. Validasi, lock serial/transfer, dan penulisan header, ledger, serta status harus atomik. Referential integrity mencegah penghapusan POP atau serial yang masih direferensikan transaksi.

Status pembayaran versi spec ini hanya unpaid/paid. Pusat menandai paid setelah memverifikasi **pembayaran penuh** dan menyimpan referensi bukti operasional. Pembayaran sebagian belum dicatat sebagai paid dan tidak mengubah hak pakai barang. Koreksi konfirmasi paid wajib beralasan dan tercatat audit, bukan menghapus histori.

## 6. Alur dan aturan server

1. Pusat memilih source_operational_pop_id, target_operational_pop_id, Gudang Cabang penerima, dan SN eligible yang accountable_pop_id-nya sama dengan asal. Form hanya menampilkan kandidat sah; service mengulang seluruh validasi saat submit.
2. Pusat mengisi harga jual >0 **per SN**. Nilai taksiran retur atau biaya inventory tidak otomatis menjadi harga jual. Dispatch sekali membuat transfer purpose=sale dan ledger keluar; SN menjadi TRANSFERRED dan current_pop_id kosong. Harga dan asal/tujuan adalah snapshot yang tidak bisa diubah setelah dispatch.
3. Gudang Cabang hanya mengonfirmasi SN fisik yang cocok pada kiriman menuju gudangnya. Konfirmasi parsial mengikuti pola transfer saat ini: hanya SN diterima menjadi AVAILABLE, current_pop_id menunjuk Cabang, dan accountable_pop_id berubah ke OLT tujuan. SN tidak cocok tetap TRANSFERRED untuk investigasi Pusat.
4. Sesudah penerimaan fisik, Issue dan titik pakai harus memeriksa SN dialokasikan ke OLT/cabang tujuan yang benar. Guard Cabang yang sudah ada tetap berlaku; untuk OLT berbeda di bawah satu Cabang, pemeriksaan accountable_pop_id mencegah pemakaian silang. Payment unpaid tidak menjadi syarat Issue/pakai.
5. Pusat dapat mengonfirmasi pembayaran penuh setelah dispatch, baik sebelum maupun sesudah penerimaan fisik. Transaksi unpaid masuk daftar tindak lanjut Pusat; tidak ada tanggal jatuh tempo atau label overdue tanpa aturan tersendiri.

Server wajib menolak SN bukan di Pusat, bukan AVAILABLE, belum diperiksa, kondisi selain used_good, status DAMAGED/QUARANTINE/SCRAPPED, ownership=customer, asal kosong/tidak cocok, target di luar cabang penerima, harga kosong/tidak positif, transfer sudah dikonfirmasi, dan request jual oleh Cabang. UI menyembunyikan kandidat terlarang tetapi bukan pengaman tunggal. Alur restock Pusat→Cabang dan retur Cabang→Pusat yang lama tetap berjalan; keduanya tidak mewajibkan asal/tujuan OLT atau harga jual.

## 7. Selisih dan pembalikan

SN yang tidak diterima tidak boleh diissue. Pusat menyelidiki selisih; SN tidak boleh ditandai diterima hanya agar stok terlihat seimbang. Setelah dispatch, sale tidak bisa diedit atau dihapus. Jika sale keliru setelah barang diterima, barang harus dikirim balik secara fisik lewat transaksi pembalik yang merujuk sale asal; accountable_pop_id kembali ke asal hanya setelah penerimaan balik dikonfirmasi. Histori sale dan pembayaran tetap ada. Pengembalian uang diselesaikan di luar aplikasi dan dicatat sebagai referensi koreksi operasional jika diperlukan.

## 8. Permission, scope, dan tampilan

Permission baru mengikuti format feature.action, misalnya warehouse_transfer.sale.create, warehouse_transfer.sale.view_price, dan warehouse_transfer.sale.confirm_payment. Grant diberikan hanya kepada operator Pusat yang berwenang; tidak membuat role per cabang atau memberi permission langsung ke user. Create/dispatch/payment memeriksa permission **dan** scope Gudang Pusat di route serta service. Scope all_pop saja tidak cukup. Cabang dapat mempunyai izin sempit melihat surat jalan dan mengonfirmasi kiriman ke gudangnya sendiri, tanpa hak jual, harga, atau data cabang lain.

Pusat mendapat daftar Sale Antar OLT/Cabang dengan filter periode, asal, tujuan, SN, status fisik, dan status pembayaran; total nilai jual dan daftar unpaid. Cabang hanya mendapat kiriman masuk serta stok tersedia dalam scope-nya. Lacak Barang menampilkan kronologi SN, asal operasional, retur ke Pusat, sale, penerimaan, dan pemakaian berikutnya. Laporan ini tidak digabung dengan Barang Terjual ke Pelanggan atau pembayaran pelanggan. Export mengikuti pembatasan permission dan scope yang sama dengan halaman.

Surat Jalan lama tetap tanpa harga. PDF Invoice transfer lama mengambil unit_price_snapshot sebagai nilai inventory sehingga **tidak boleh dipakai sebagai nota harga jual**. Mode sale memerlukan dokumen bernilai operasional tersendiri yang membaca sale_unit_price_snapshot, hanya untuk Pusat, berlabel bukan invoice pelanggan atau faktur pajak. Nomor transfer mengikat dokumen dan ledger.

## 9. Kriteria penerimaan

- SN vendor dan SN buatan sistem, keduanya tracking_type=serialized, mengikuti alur sale yang sama. Item quantity/roll dan kondisi new tidak tersedia.
- SN used_good yang sudah diperiksa di Pusat dengan accountable_pop_id valid dapat dijual; semua kondisi/status terlarang ditolak pada request langsung tanpa mutasi parsial.
- SN dari dua asal berbeda tidak dapat digabung dalam satu sale. Asal kosong memerlukan atribusi Pusat dengan alasan dan audit lebih dulu.
- Harga per SN diisi Pusat, >0, tersimpan sebagai snapshot terpisah dari biaya/nilai stok; Cabang tidak dapat membaca atau mengubahnya.
- Dispatch mengurangi stok Pusat sekali. Receive ulang/bersamaan tidak menggandakan stok Cabang. Receive parsial hanya mengubah SN yang cocok.
- SN diterima boleh diissue/dipakai untuk OLT tujuan walau payment_status=unpaid. SN transit/selisih atau dialokasikan ke OLT lain ditolak.
- Pusat dapat mencatat paid sesudah verifikasi penuh; unpaid tidak memblokir alur fisik. Tidak dibuat invoice/payment pelanggan, jurnal, atau investor master.
- User tanpa permission, PIC Gudang Cabang, dan user all_pop tanpa kewenangan Pusat mendapat 403 pada create/dispatch/payment; penerima Cabang hanya dapat mengonfirmasi kiriman ke gudangnya.
- Riwayat dan laporan Pusat menampilkan asal, tujuan, harga, status, aktor, waktu yang benar; Cabang tidak mendapat harga melalui halaman, export, PDF, atau respons API.
- Pembalikan menjaga ledger dan audit lama tanpa menghapus histori.

## 10. Referensi

- docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md — Gap 5 dan keputusan user.
- docs/plan/warehouse/rancangan-invoice-surat-jalan-transfer.md — transfer fisik dan PDF existing.
- docs/plan/warehouse/rancangan-retur-ke-pusat-dan-modem-rusak.md — retur tiga tahap dan inspeksi final Pusat.
- docs/plan/warehouse/analisa-generate-id-barang-non-serial.md — serial vendor dan kode buatan sistem.
- docs/warehouse/business-logic.md dan docs/warehouse/database-schema.md — perilaku serta skema berjalan.
