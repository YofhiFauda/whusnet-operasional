# Rencana Jual Beli Barang Ber-SN Antar Cabang — 2026-10-26

> Status: implementasi fase Gudang selesai pada 2026-10-26 (ADHOC-217). Migrasi aditif telah diterapkan ke database aplikasi, dan feature serta izin role telah disinkronkan. Pembayaran internal sengaja tidak terhubung ke aplikasi Finance; nominal dicocokkan melalui Laporan Gudang.

## Scope check

- **Sprint/task:** permintaan baru post-MVP, terpisah dari ADHOC-202 yang sedang aktif. Masukkan sebagai ADHOC baru di `docs/TASKS.md` saat implementasi disetujui; jangan mengubah status ADHOC-202 hanya karena rencana ini dibuat.
- **Modul:** Gudang, transfer stok, identitas SN, laporan nilai, RBAC, dan pencatatan kewajiban pembayaran internal. Billing pelanggan (`invoices`/`payments`) tidak disentuh.
- **Acuan:** keputusan user 2026-10-26 bahwa barang yang diperjualbelikan harus ber-SN pabrikan atau SN yang dibuat sistem; barang habis pakai tidak diperjualbelikan. `docs/MVP_SCOPE.md` menempatkan inventory kompleks dan keuangan kompleks di luar MVP; permintaan ini menjadi pengecualian post-MVP yang eksplisit.
- **Tidak disentuh:** penjualan modem ke pelanggan (`inventory_serial_sales`), invoice pelanggan, pemakaian kabel/roll, alur transfer stok biasa Pusat↔Cabang, serta migrasi/histori Gudang lama.
- **Kriteria utama:** hanya unit `TrackingType::SERIALIZED` yang tersedia, milik perusahaan, lulus pemeriksaan kondisi, dan benar-benar berada di Cabang penjual dapat dijual ke Cabang lain. Barang `QUANTITY` dan `ROLL` ditolak oleh service sekalipun request dimanipulasi. SN pabrikan dan SN buatan sistem diproses identik dari `inventory_serials`.

## Mengapa penjualan saat ini hanya modem

`InventorySerialSale` menyimpan `customer_id`, `fop_task_id`, dan harga. Pemanggilnya berada pada laporan pemasangan/maintenance modem; `TaskBillingItem` memakai baris tersebut sebagai "Jual Modem". Ia adalah **penjualan ke pelanggan**, bukan antar Cabang. `invoices` juga mensyaratkan pelanggan, layanan, dan paket internet; tabel itu tidak boleh dipakai sebagai tagihan Cabang. Transfer Gudang saat ini memindahkan stok dua fase, belum memiliki Cabang↔Cabang langsung, harga jual, pembeli internal, atau penyelesaian tagihan.

`receiveSerialized()` dan `receiveSerializedAuto()` sama-sama membuat `InventorySerial`. Flag `items.auto_generate_serial` hanya menentukan asal identitas saat barang diterima; alur jual beli tidak perlu bercabang menurut asal SN. Kode roll hasil sistem tetap masuk `TrackingType::ROLL` dan **bukan** unit yang dapat dijual melalui alur ini.

## Keputusan bisnis yang sudah dikonfirmasi

1. Cabang A menjual SN ke Cabang B. Setelah B mengonfirmasi unit fisik, dokumen pembelian B berstatus **Dibeli**; stok dan laporan B mencatat asal pembelian serta harga jual antar Cabang. A mencatat penjualan dan nilai yang harus dibayar B. Ini bukan tagihan pelanggan.
2. Nilai stok B memakai **harga jual antar Cabang**. HPP unit sebelum penjualan tetap tersimpan sebagai histori A. Contoh: HPP A Rp200.000, harga jual A→B Rp230.000; nilai unit di stok B Rp230.000 setelah diterima.
3. Pembayaran dilakukan secara internal. Nominal transaksi dicocokkan melalui laporan Gudang dengan bukti pembayaran internal di luar aplikasi. Gudang tidak mencatat transaksi maupun status lunas/belum dibayar, dan tidak diintegrasikan ke Finance.
4. "Dibeli" adalah status **dokumen pembelian dan label asal stok**, bukan nilai baru `SerialStatus`. Status fisik SN tetap TRANSFERRED selama perjalanan dan AVAILABLE setelah B menerima. Sisa yang hilang tetap LOST setelah penutupan dengan bukti.

Harga jual wajib disimpan per SN sebagai snapshot; jangan membaca harga item terkini ketika laporan periode lama dibuka. Aturan pajak/PPN internal, persetujuan diskon, dan jurnal akuntansi tidak diasumsikan dalam rencana ini.

## Arsitektur dan file

| File | Tanggung jawab |
|---|---|
| `database/migrations/*_create_branch_inventory_sales_tables.php` (baru) | Header jual beli ber-FK unik ke `inventory_transfers`, nomor dokumen, nilai final dan status kewajiban internal; baris per `inventory_serial_id` dengan harga jual snapshot dan indeks unik (sale, serial). Tidak ada unique global pada serial, agar unit bisa berpindah lagi kelak. FK `restrictOnDelete` untuk histori; dokumen terbit dikoreksi dengan pembalik, tidak dihapus. |
| `app/Models/BranchInventorySale.php`, `BranchInventorySaleLine.php` (baru) | Relasi Cabang penjual/pembeli, transfer, SN, harga. Harga memakai decimal, bukan float untuk operasi uang. |
| `app/Services/BranchInventorySaleService.php` (baru) | Validasi Cabang berbeda, hak kepemilikan/kelayakan SN, harga, snapshot, pembuatan dokumen dan dispatch dalam satu transaksi. Kunci SN dengan `lockForUpdate()`. |
| `app/Services/InventoryTransferService.php` | Tambah jalur Cabang→Cabang khusus dokumen jual beli SN; konfirmasi hanya SN yang diterima fisik. Transfer biasa dan retur RTR tetap pada jalur masing-masing. |
| `app/Http/Controllers/Warehouse/BranchInventorySaleController.php` (baru), `routes/web.php`, Blade Gudang (baru) | Form pilih Cabang asal/tujuan, daftar SN tersedia dan harga per unit; detail, konfirmasi SN yang benar-benar datang, dokumen cetak. |
| `config/rbac.php`, seeder permission/role terkait | Permission `warehouse_branch_sale.view/create/receive` dan izin terpisah untuk melihat harga. Aksi tulis wajib memeriksa POP scope sisi penjual/penerima. |
| `app/Http/Controllers/Warehouse/WarehouseReportController.php` dan tampilan laporan | Pisahkan laporan jual beli antar Cabang dari "Barang Terjual ke Pelanggan"; tampilkan SN, kedua Cabang, harga beli historis, harga jual snapshot, status fisik, dan nilai yang sudah diterima. |

Nominal acuan cross-check dibuat saat transfer selesai untuk nilai SN yang diterima. Laporan Gudang menampilkan nomor dokumen, Cabang, periode, dan nominal final. Jangan menambahkan `customer_id` palsu atau memakai `invoices` pelanggan.

## Urutan kerja dan bukti uji

### Task 1 — Identitas dokumen dan guard SN

- Tulis tes PHPUnit `BranchInventorySaleEligibilityTest`: SN vendor dan SN auto lolos dengan hasil identik; `QUANTITY`, `ROLL`, SN rusak/karantina/hilang/terpasang, milik pelanggan, sedang transfer, atau di luar Cabang asal ditolak. SN yang sama tidak bisa dijual dua kali ketika transaksi pertama masih terbuka.
- Buat migration/model, factory yang berguna, service validasi. Simpan harga positif per SN dan nomor dokumen unik melalui `NumberSequenceService`. Uji rollback: bila salah satu SN gagal, tidak ada dokumen atau mutasi parsial.

### Task 2 — Pengiriman Cabang A→Cabang B

- Tes `BranchInventorySaleDispatchTest`: setelah dispatch, SN berstatus TRANSFERRED, keluar dari stok A dan belum masuk stok B; ledger dispatch TRANSFER tetap menyimpan HPP unit sumber (ambil dari histori unit, bukan harga pembelian item lain yang lebih baru). Dokumen jual beli menyimpan harga jual terpisah. Transfer stok QUANTITY/ROLL biasa tetap berjalan tanpa menjadi penjualan.
- Tambah method transfer langsung Cabang→Cabang yang hanya dapat dipanggil oleh service jual beli. Jangan membuka arah Cabang→Cabang generik bagi endpoint transfer biasa.

### Task 3 — Penerimaan, selisih, dan idempotensi

- Tes `BranchInventorySaleReceiptTest`: penerima memilih subset SN fisik, hanya SN terkonfirmasi menjadi AVAILABLE di B; sisanya tetap TRANSFERRED dan dapat diterima kemudian atau ditutup hilang dengan bukti. Terima ulang SN yang sama ditolak; klik ganda tidak menggandakan stok ataupun nilai penjualan.
- Hubungkan penerimaan ke `InventoryTransferService::receiveTransfer()` dalam transaksi DB yang sama. Endpoint penerimaan transfer umum wajib menolak dokumen jual beli agar izin `warehouse_branch_sale.receive` tidak bisa dilewati; gunakan endpoint khusus jual beli untuk konfirmasi. Endpoint penutupan sisa hilang juga wajib memeriksa izin dan POP scope dokumen jual beli. Gunakan alur `closeMissingTransfer()` untuk SN hilang tanpa debit stok kedua kali. Ledger penerimaan di B memakai harga jual snapshot sebagai nilai stok B, sedangkan baris kehilangan memakai HPP sumber. Nilai penjualan yang dicatat hanya untuk SN yang diterima B.

### Task 4 — Akses dan UI

- Tes HTTP `BranchInventorySaleAccessTest`: user perlu permission serta POP scope sumber untuk membuat, POP scope tujuan untuk menerima; Cabang ketiga tidak dapat melihat detail/harga. Harga tersembunyi dari role yang hanya boleh melihat mutasi.
- Buat form Gudang dengan pemilih SN terfilter, harga jual per SN, halaman detail dan status; rute bernama dan PRG setelah POST. Tampilkan hubungan ke dokumen transfer/riwayat SN. Pada daftar transfer umum, tautan harga/invoice lama tidak boleh menampilkan HPP sebagai harga jual dokumen ini.

### Task 5 — Nilai stok dan kewajiban pembayaran internal

- Tes `BranchInventorySaleValuationTest`: HPP A Rp200.000 dan harga jual A→B Rp230.000 menghasilkan nilai stok B Rp230.000 setelah konfirmasi, sementara histori HPP A tetap Rp200.000. Laporan periode sebelum/sesudah transfer harus konsisten.
- Tes `BranchInventorySaleObligationTest`: setelah seluruh SN diterima atau sisa ditutup, header jual beli menjadi dokumen kewajiban internal yang hanya menjumlah SN yang benar-benar diterima B, menyimpan Cabang A/B, harga per SN, total, dan tanpa status pembayaran. Pembayaran tidak disimulasikan atau otomatis dinyatakan lunas.
- Buat laporan pembelian B dan penjualan A; pisahkan dari "Barang Terjual ke Pelanggan" dan laporan pembayaran pelanggan. Laporan menampilkan dokumen, unit SN, harga, status diterima/hilang, nominal acuan cross-check pembayaran internal.
- **Keputusan final user 2026-10-26:** tidak ada integrasi Finance. Cross-check pembayaran dilakukan dari laporan Gudang terhadap bukti internal di luar aplikasi; laporan tidak mengklaim status lunas atau belum dibayar.

### Task 6 — Verifikasi akhir

- Jalankan tes tiap task secara terfokus, lalu tes regresi transfer, stok historis, ownership modem, laporan Gudang, dan POP scope. Jalankan `vendor/bin/pint --dirty --format agent` pada PHP terkait dan `git diff --check`.
- Pratinjau migrasi pada database target sebelum penerapan. Migrasi harus aditif; dokumen TRF dan `InventorySerialSale` lama tetap terbaca. Tidak ada perubahan data historis otomatis.
- Demo manual: terima SN vendor dan SN auto di Pusat, transfer ke Cabang A, jual A→B, terima satu dari dua SN, tutup SN sisanya hilang, lalu periksa stok, riwayat, harga, dan laporan masing-masing Cabang.
