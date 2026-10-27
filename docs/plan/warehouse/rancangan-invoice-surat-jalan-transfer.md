# Rancangan: Invoice & Surat Jalan untuk Transfer Gudang Pusat → Cabang

Status: **Done — 2026-09-17 (ADHOC-81, docs/TASKS.md).** Diimplementasikan persis sesuai keputusan §7, di luar sprint aktif (Sprint 8.10), atas permintaan eksplisit user.

**Koreksi 2026-09-17 (setelah implementasi awal):** TTD Kepala Gudang di Invoice **BUKAN** gambar statis — sama seperti Surat Jalan, murni nama+jabatan (hardcode) + garis kosong, diisi tangan di kertas, gak ada mekanisme upload/gambar TTD sama sekali di dokumen manapun. Draft awal §3/§4.1/§6 di bawah sempat salah asumsi "Invoice py satu TTD berupa gambar" — sudah diperbaiki di kode & di dokumen ini.

**Redesign 2026-09-17 (template resmi):** user kasih template asli perusahaan — `docs/plan/warehouse/laporan/Surat_Jalan_Transfer_Gudang_WHUSNET.{docx,md,png}` (banner WHUSNET, meta No. Surat Jalan+Referensi WO/Tiket+Jenis Transfer, blok Pengirim/Penerima lengkap alamat+telp+PJ, tabel barang+Qty Terima+Keterangan manual, catatan 4 poin, lembar pengesahan) dan `INVOICE.{docx,md,png}` (brand kiri atas, blok Tagihan ke/Kirim ke, tabel Qty/Keterangan/Harga Satuan/**Diskon**/Jumlah, Ringkasan Total Diskon/Subtotal/**Pajak**/Total). Kedua Blade dirombak total ngikutin struktur ini apa adanya (bukan cuma "mirip"). Field yang gak ada padanan warehouse-nya (Diskon, Pajak) tetap ditampilkan **selalu Rp 0** demi kesetiaan ke template, bukan dihapus. Field customer-invoice generik (Pelanggan/ID Pelanggan/Penjual/Pembayaran) diadaptasi ke konteks Pusat→Cabang (lihat §4.1 baru). **No. Surat Jalan** ternyata field TERPISAH dari `reference_number` transfer (yang cuma jadi "Referensi WO/Tiket") — diturunkan deterministik dari `id`+`created_at` transfer (`SJ/WHUS/{Y}/{m}/{id:03d}`), BUKAN counter baru tersimpan (tetap gak ada migration). Alamat Pengirim/Penerima & header brand Invoice ditarik dari `pops.address/village/district/city/pic_phone` (data asli POP, bukan hardcode) — field-field itu ternyata sudah ada di kolom Pop, gak perlu tabel baru.

## 1. Latar Belakang

Alur Transfer Pusat→Cabang **sudah ada** (`InventoryTransferService`, `InventoryTransfer` model, dua fase `dispatch`/`receive` — lihat `docs/warehouse/business-logic.md` §4). Task ini **bukan** bikin alur transfer baru, melainkan menambahkan dua dokumen cetak (PDF) di atas transfer yang sudah terjadi:

1. **Invoice** — dokumen berharga, ke Cabang, bukti nilai barang yang dikirim. Ditandatangani kepala gudang yang mengetahui (Nadya Naralita Setiadi).
2. **Surat Jalan** — dokumen pengiriman fisik, **tanpa harga**, cuma kode barang/SN + jumlah. Ditandatangani admin Gudang Pusat (pengirim) dan PJ Cabang tujuan (penerima) — **di atas kertas**, bukan di sistem (§5).

Referensi format lama: `docs/plan/warehouse/laporan/INVOICE.docx` (template lama "NN Network", generik — dipakai sebagai referensi tata letak header/tabel, bukan dikirim ulang apa adanya karena belum ada kolom kode SN/barang atau blok tanda tangan kepala gudang).

## 2. Data yang Sudah Tersedia (tidak perlu tabel baru)

Ditelusuri ke migration & service yang sudah jalan supaya rancangan gak reinvent:

| Kebutuhan | Sumber data existing |
|---|---|
| Header transfer (no. referensi, dari POP, ke POP, tanggal, status) | `inventory_transfers` — `reference_number` (`TRF-{tahun}-{4digit}`), `from_pop_id`, `to_pop_id`, `created_at`, `received_at` |
| Baris barang: item, qty, kode SN | `inventory_transactions` leg **dispatch** (`type=transfer`, `from_pop_id` terisi, `inventory_transfer_id`) — join `items` (kode+nama) & `inventory_serials` (kode SN, kalau item tracked serial) |
| **Harga satuan per baris** | `inventory_transactions.unit_price_snapshot` — sudah dicatat di titik dispatch (`InventoryTransferService::createTransfer()`, last-cost dari RECEIVE terakhir), **tidak perlu field harga baru di `items`** |
| Siapa yang dispatch (admin Gudang Pusat, penandatangan kiri Surat Jalan) | `inventory_transfers.created_by` → `users` |
| **PJ Cabang tujuan** (nama, penandatangan kanan Surat Jalan) | `pops.pic_name` + `pops.pic_phone` — **sudah ada**, tidak perlu kolom baru |

## 3. Gap yang Perlu Ditambah

| Gap | Kenapa | Keputusan |
|---|---|---|
| **Nama+jabatan Kepala Gudang (Nadya)** untuk Invoice | Dia bakal punya akun `User`, tapi nama lengkap di sistem kemungkinan gak sama persis/gak lengkap dibanding nama resmi di dokumen | **Hardcode** nama+jabatan di `config/warehouse.php`, bukan ditarik dari `users.name`. TTD-nya **tetap manual** (garis kosong), sama seperti dua TTD di Surat Jalan — lihat §6. |
| **TTD Kepala Gudang (Invoice) + Admin Gudang Pusat & PJ Cabang (Surat Jalan)** | Dokumen serah-terima/persetujuan fisik | **Tidak ada TTD digital tersimpan/diupload ke sistem sama sekali, di dokumen manapun.** Sistem cuma generate PDF blanko dengan nama (Invoice: nama hardcode Kepala Gudang; Surat Jalan: kiri nama admin yang dispatch dari `created_by`, kanan `pops.pic_name` cabang tujuan) + garis kosong di bawahnya buat TTD basah di kertas. Lihat §5. |
| **Kop surat perusahaan (alamat, telp) untuk PDF** | Ada di `INVOICE.docx` lama tapi hardcode di dokumen, belum ada di config aplikasi | Tambah ke `config/company.php` (baru) atau reuse config existing kalau sudah dipakai kwitansi pembayaran pelanggan — cek `payments.receipt.blade.php` dulu sebelum bikin baru |

## 4. Desain Dokumen

### 4.1 Invoice (berharga)

Kolom tabel: **Qty | Nama Barang | Harga Satuan | Diskon | Jumlah**, ditutup Ringkasan **Total Diskon | Subtotal | Pajak | Total**. **Tanpa kolom Kode Barang/SN** (koreksi 2026-09-17) — daftar per-SN/kode/lot udah ada di Surat Jalan, dobel di sini cuma redundan. Baris digabung **per item+harga** (bukan per baris transaksi/per SN) — dua SN item yang sama dengan harga sama jadi SATU baris qty=2, grouping dilakukan di controller (`WarehouseTransferController::invoice()`), bukan cuma disamarkan di Blade. Header: no. invoice = `reference_number` transfer apa adanya (`TRF-{tahun}-{4digit}`, **bukan** seri baru — lihat §7 keputusan #1), tanggal, dari (Gudang Pusat), ke (Gudang Cabang + nama POP).

Blok tanda tangan: **kanan bawah**, satu blok — "Mengetahui, Kepala Gudang" + nama Nadya Naralita Setiadi (hardcode, §3) + garis kosong. Sama seperti Surat Jalan — **tidak ada gambar TTD** di dokumen manapun, semua manual di kertas.

Akses: cuma role dengan visibilitas harga barang. Reuse permission `warehouse_transfer.view` **tidak cukup** kalau Cabang juga punya permission itu (mereka perlu Surat Jalan, bukan Invoice) — invoice butuh permission terpisah `warehouse_transfer.invoice.view`.

### 4.2 Surat Jalan (tanpa harga)

Kolom tabel: **Qty | Kode Barang/SN | Nama Barang** — **tanpa kolom harga sama sekali** (bukan cuma disembunyikan di tampilan, kolomnya memang gak ada di query/view builder-nya supaya gak ada risiko harga kebocor lewat print-preview/inspect element).

Blok tanda tangan: **dua kolom berdampingan**, keduanya cuma nama + garis kosong (TTD basah manual) —
- Kiri: "Diserahkan oleh, Admin Gudang Pusat" — nama dari `inventory_transfers.created_by`.
- Kanan: "Diterima oleh, Penanggung Jawab Cabang" — nama dari `pops.pic_name` milik `to_pop_id`.

Akses: permission lebih longgar dari Invoice — Cabang (penerima) wajar bisa lihat/cetak Surat Jalan mereka sendiri (dibatasi POP scope biasa lewat `EffectiveAccessService`, pola `AuthorizesWarehousePop`).

## 5. Titik Cetak & Alur — FINAL

**Surat Jalan digenerate di titik dispatch, TTD kedua pihak 100% manual di kertas, gak ada apa pun yang ditulis balik ke sistem.** Gak ada field baru di `inventory_transfers` buat nyimpen bukti TTD, gak ada perubahan ke `receiveTransfer()`. Dokumen ini murni PDF cetak — sekali digenerate, isinya statis (kecuali dicetak ulang, §6 permission).

Invoice bisa dicetak kapan saja setelah dispatch (gak nunggu confirm — nilai barang gak berubah dari sisi Pusat begitu keluar gudang).

## 6. Perubahan Teknis (kalau rancangan disetujui)

- **Tidak ada migration baru.** Nama+jabatan Nadya hardcode di `config/warehouse.php` → `kepala_gudang.name`, `kepala_gudang.title`, bukan kolom DB, bukan gambar — TTD-nya garis kosong seperti Surat Jalan.
- **Controller**: dua endpoint baru di `WarehouseTransferController` (atau controller PDF terpisah) — `invoice` & `surat-jalan`, pola sama `PortalPaymentController::receipt()` (dompdf, `->setPaper('a4')`, `download()`/`stream()` toggle lewat query `?download=1`).
- **View**: `resources/views/warehouse/transfers/invoice.blade.php`, `surat-jalan.blade.php` — layout A4 print.
- **Permission baru**: `warehouse_transfer.invoice.view` — role Gudang Pusat (siapa pun yang pegang permission ini bisa cetak ulang kapan saja, gak ada gate tambahan/watermark, sesuai §7 keputusan #5). Surat Jalan reuse `warehouse_transfer.view` yang sudah ada.
- **Test**: minimal 1 feature test per dokumen — generate PDF gak error, kolom harga **tidak muncul** di response Surat Jalan (assert string harga gak ada di output — cara paling murah mastiin gak reuse view yang sama dengan Invoice secara ceroboh).

## 7. Keputusan (dikonfirmasi user, 2026-09-17)

1. **Nomor Invoice** — reuse `reference_number` transfer apa adanya, **tidak** bikin seri `INV-` terpisah. (Format aktual di migration tetap `TRF-{tahun}-{4digit}` — bukan format tanggal penuh; dipakai apa adanya begitu transfer dibuat, gak ada perubahan skema penomoran.)
2. **Titik TTD Surat Jalan** — gak ada opsi A/B, **TTD gak pernah masuk sistem**. Sistem cuma generate dokumen (nama + garis kosong), TTD basah di kertas ada di luar sistem sepenuhnya.
3. **Nadya (kepala gudang)** — bakal punya akun `User`, tapi nama+jabatan di dokumen **di-hardcode** di kode, gak ditarik dari `users.name` (nama sistem kemungkinan gak lengkap/gak match nama resmi). TTD-nya tetap manual di kertas — **bukan** gambar statis, koreksi dari draft awal (lihat catatan di atas).
4. **Relasi Invoice/Surat Jalan ↔ Transfer** — **1:1**, 1 transfer = 1 Invoice = 1 Surat Jalan. Gak ada batching multi-transfer.
5. **Cetak ulang** — semua user dengan role Gudang Pusat + permission terkait (`warehouse_transfer.invoice.view` / `warehouse_transfer.view`) boleh cetak ulang kapan saja. Gak perlu audit log/watermark "SALINAN" khusus.

## 8. Non-Goals

- Tidak mengubah alur dispatch/confirm transfer yang sudah ada.
- Tidak bikin sistem approval/persetujuan baru sebelum dispatch — dokumen ini cuma pencatatan, bukan gerbang baru di alur transfer.
- Tidak menyentuh Issue/Adjustment/Custody — cuma Transfer Pusat→Cabang.
- Tidak menyimpan bukti TTD digital dalam bentuk apa pun (foto/gambar/tanda tangan elektronik) — Kepala Gudang, Admin Gudang Pusat, maupun PJ Cabang. Ketiganya manual di kertas.

---

## 9. Addendum rancangan: jual barang antar OLT/cabang melalui Gudang Pusat (2026-10-12)

**Status: rancangan post-MVP, belum diimplementasikan.** Bagian 1–8 di atas mendokumentasikan transfer dan PDF yang sudah berjalan (ADHOC-81); addendum ini hanya berlaku untuk mode jual antar unit yang berbeda dari transfer/restock biasa. Latar belakang dan jejak pertanyaan ada di docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md, Gap 5.

### 9.1 Tujuan dan batas sistem

Barang yang dibeli untuk OLT A dapat berada secara fisik di Gudang Pusat setelah retur. Ketika barang itu dialihkan untuk OLT B, transaksi dicatat sebagai jual dari unit operasional asal A ke unit tujuan B, dikelola oleh Pusat. Investor yang terkait dengan masing-masing OLT tidak menjadi master data aplikasi: aplikasi ini khusus operasional. Identitas pihak penjual secara hukum, hak investor, jurnal, pajak, dan penyelesaian uang ke investor tetap di luar aplikasi. Lokasi fisik Pusat tidak boleh ditafsirkan sebagai asal/pemilik barang.

Untuk setiap barang/SN, rancangan perlu membedakan **unit OLT/cabang asal**, **gudang fisik saat ini**, **gudang cabang penerima**, dan **OLT/cabang tujuan penggunaan**. Transfer yang berjalan sekarang hanya menerima gudang bertipe Cabang sebagai tujuan fisik; jika OLT B adalah Mini POP di bawah Cabang B, OLT tujuan tidak boleh dipaksa masuk ke field gudang penerima yang sama. Asal operasional tetap terlacak setelah retur ke Pusat. Stok barang kuantitas dari beberapa asal perlu tetap dapat dipisahkan.

### 9.2 Keputusan bisnis yang sudah dikonfirmasi user

| Hal | Aturan |
|---|---|
| Penentu harga jual | **Gudang Pusat** mengisi harga jual per barang. PIC Gudang Cabang tidak dapat menentukan atau mengubahnya. Harga jual berbeda dari nilai taksiran retur dan unit_price_snapshot biaya/nilai stok. |
| Waktu pembayaran | **Boleh setelah pengiriman dan penerimaan.** Belum dibayar tidak menghalangi dispatch atau konfirmasi fisik. Pusat melacak status pembayaran untuk tindak lanjut operasional. |
| Waktu barang boleh dipakai | **Setelah SN/qty yang benar dikonfirmasi diterima secara fisik** di gudang tujuan, barang boleh tersedia dan di-issue/dipasang untuk OLT tujuan walaupun belum lunas. Barang dalam perjalanan atau selisih penerimaan tidak boleh dipakai. |
| Hak memulai transaksi | Hanya operator dengan permission jual khusus **dan** kewenangan Gudang Pusat. Scope all_pop saja tidak cukup. PIC Gudang Cabang tidak dapat memulai jual-beli/transfer antar cabang. |
| Barang rusak | Tidak boleh dijual. Kandidat awal Gap 5 adalah barang retur used_good yang sudah diperiksa di Pusat; used_damaged, DAMAGED, QUARANTINE, SCRAPPED, dan bekas belum diperiksa ditolak di service. |

### 9.3 Alur dan data transaksi

1. Pusat memilih SN/qty yang tersedia di Gudang Pusat, sudah layak jual, dan memiliki asal operasional yang jelas. Pusat memilih gudang cabang penerima serta OLT/cabang tujuan penggunaan, lalu mengisi harga jual per baris. Transaksi jual harus dibedakan dari transfer/restock biasa.
2. Dispatch membuat barang keluar dari stok Pusat dan berstatus dalam perjalanan. Surat jalan tetap berisi barang/SN/qty tanpa harga. Harga jual dan asal/tujuan dibekukan sebagai snapshot transaksi agar riwayat tidak berubah mengikuti penilaian stok atau perubahan master POP.
3. Petugas penerima di cabang tujuan dapat mengonfirmasi barang fisik yang cocok melalui aksi penerimaan terbatas. Hak ini tidak mencakup pembuatan jual, pemilihan asal lain, perubahan harga, atau melihat rekap harga lintas cabang. Stok tujuan bertambah hanya sebesar SN/qty yang dikonfirmasi; selisih tetap berstatus perlu investigasi.
4. Setelah diterima, barang boleh dipakai untuk OLT tujuan tanpa menunggu pembayaran. Status fisik dan status pembayaran adalah dua alur independen. Pusat mencatat konfirmasi pembayaran dan memantau transaksi belum dibayar; pencatatan kas/bank dan pembukuan investor tetap di luar aplikasi.
5. Riwayat SN menyambungkan OLT/cabang asal → retur → Pusat → jual → cabang penerima/OLT tujuan. Pembatalan/koreksi yang sudah bergerak fisik harus memakai jejak pembalik, bukan menghapus transaksi.

Minimal data baru yang perlu dirancang saat implementasi: penanda jenis transaksi pada transfer, identitas unit asal dan OLT tujuan terpisah dari gudang fisik, harga jual per baris, status/referensi pembayaran operasional, serta audit aktor dan waktu. Jangan menimpa unit_price_snapshot yang sudah bermakna biaya/nilai stok. Existing inventory_serials.ownership (company/customer) tetap untuk relasi modem terhadap pelanggan, bukan identitas asal OLT.

### 9.4 Dokumen, laporan, dan akses

PDF Invoice transfer pada Bagian 4 mengambil unit_price_snapshot dari ledger, sehingga **belum merupakan dokumen harga jual mode baru**. Saat implementasi, tentukan dokumen bernilai khusus mode jual dengan harga jual dari snapshot transaksi; surat jalan fisik tetap tanpa harga. Dokumen ini tidak menjadi invoice pelanggan dan tidak masuk ke invoices/payments pelanggan.

Pusat melihat daftar transaksi jual antar OLT/cabang, SN/qty, asal/tujuan, harga jual, status fisik, status pembayaran, serta daftar belum dibayar dan selisih penerimaan. Cabang penerima hanya melihat kiriman masuk dan stok dalam scope-nya. PIC Gudang Cabang tidak melihat harga atau rekap lintas cabang. Laporan ini terpisah dari "Barang Terjual ke Pelanggan" dan dari transfer/restock biasa.

**Masih perlu keputusan sebelum implementasi:** dasar persetujuan harga oleh pihak asal yang disimpan/dirujuk Pusat; apakah barang new ikut dijual; format dokumen bernilai mode jual; apakah pembayaran sebagian didukung dan bagaimana bukti konfirmasinya dicatat. Aturan pajak serta pihak penjual formal diputuskan di luar aplikasi setelah status hukum diketahui.
