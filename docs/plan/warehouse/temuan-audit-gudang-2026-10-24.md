# Temuan Audit Alur Gudang — 2026-10-24

Sumber: code review `high` atas modul Gudang (Receive, Transfer, Issue, Install, Retur, Adjustment, Laporan).
Status awal audit: **baru dicatat, belum ada yang diperbaiki.** Status tindak lanjut per 2026-10-24 dicatat di bawah; bagian temuan tetap dipertahankan sebagai bukti kondisi saat audit.
Tiap fix wajib punya test regresi bernama sesuai gejala (konvensi repo), dan hanya jalankan file test yang terdampak.

Kesimpulan: kerangka alur sudah benar (Receive di Pusat, Transfer dua fase, Issue ke custody teknisi, Install,
Retur tiga tahap, ledger append-only), tetapi ada 10 bug dan 3 gap alur di bawah.

## Ringkasan

| # | Sev | Temuan | File | Tipe |
|---|---|---|---|---|
| 1 | Tinggi | Modem `used_damaged` jadi AVAILABLE, bisa di-Issue ulang | `InventoryReturnTransferService.php:162` | Integritas barang |
| 2 | Tinggi | Laporan stok awal/akhir menghitung DEPLOY/REGISTER_INFRA sebagai stok | `WarehouseStockAsOfService.php:266` | Laporan salah |
| 3 | Tinggi | Return custody bisa ke cabang lain tanpa Transfer | `InventoryReassignService.php:104` | Stok lintas cabang |
| 4 | Tinggi | Transfer retur Cabang→Pusat masuk antrean Terima biasa | `InventoryTransferService.php:238` | Lompat tahap |
| 5 | Sedang | Walk-in menerima modem milik pelanggan (sudah dibeli) | `InventoryReassignService.php:371` | Kepemilikan aset |
| 6 | Sedang | Aset infrastruktur yang dicabut kembali "baru" tanpa inspeksi | `InventoryService.php:1146` | Baru vs bekas |
| 7 | Sedang | Nomor ledger `max()+1`, prefix `RTR-` ganda | `InventoryService.php:446` | Race + melanggar CLAUDE.md |
| 8 | Sedang | `DeviceRetrievalLog` CREQ_SWAP tanpa `warehouse_pop_id` | `InventoryService.php:320` | Riwayat hilang di scope |
| 9 | Sedang | Custody terpotong dua kali saat revisi instalasi | `InventoryService.php:1249` | Stok teknisi hilang |
| 10 | Rendah | Adjustment pakai model basi tanpa lock | `InventoryAdjustmentService.php:144` | Lost update |

---

## 1. Modem `used_damaged` jadi AVAILABLE

**Gejala.** `confirmAtPusat()` selalu set `status = AVAILABLE`, apa pun kondisinya. `InventorySerial::isClearedForIssue()`
(`InventorySerial.php:158`) hanya memeriksa `condition_checked_at !== null`, tidak melihat hasil kondisi. SN rusak muncul di
dropdown Issue (`WarehouseIssueController.php:156` memakai filter yang sama), bisa di-Transfer, dan `installSerial()` tidak
mengecek kondisi. Jalur `markSerialConditionChecked(USED_DAMAGED)` di `InventoryReassignService.php:608` punya masalah sama.

**Solusi (tiga lapis, jangan hanya satu).**
1. `confirmAtPusat()`: kalau `$condition === USED_DAMAGED` set `status = SerialStatus::DAMAGED` (case sudah ada di enum),
   bukan AVAILABLE. Ledger tetap ditulis, `to_pop_id` tetap terisi supaya tercatat sebagai penerimaan Pusat.
   Lakukan hal yang sama di `markSerialConditionChecked()` saat hasilnya `USED_DAMAGED`.
2. `isClearedForIssue()`: tolak `USED_DAMAGED` apa pun nilai `condition_checked_at` (pertahanan lapis kedua).
3. `installSerial()` dan `InventoryIssueService` (baris 107): panggil guard yang sama, supaya jalur lain (import, tinker) tidak lolos.

**Cek dampak sebelum ubah status.** Halaman "Modem Rusak", `WarehouseStockPositionService` (baris ~133), dan filter kondisi di
`WarehouseHistoryController` (baris ~118) membaca `condition`. Pastikan angka stok tersedia tidak lagi menghitung SN `DAMAGED`
dan halaman Modem Rusak tetap menampilkannya (filter pakai `condition`, bukan `status = AVAILABLE`).

**Test.** `ModemReturUsedDamagedTidakBisaDiIssueTest`: konfirmasi retur `used_damaged` → status `DAMAGED`, tidak ada di
`availableStock`, `IssueService` menolak, `installSerial()` menolak.

## 2. Replay stok menghitung DEPLOY/REGISTER_INFRA sebagai stok gudang

**Gejala.** `unitsAsOf()` memilih baris ledger terakhir per unit lalu `where to_pop_id = pop`. Komentar kelas menyebut
INSTALL/ADJUSTMENT tidak menulis `to_pop_id`, tapi DEPLOY dan REGISTER_INFRA sekarang menulisnya (lokasi aset tepat POP,
ADHOC-208..212). Pada QUANTITY, `quantityAsOf()` menambahkan baris DEPLOY positif setelah ISSUE sudah mengurangi.

**Solusi.** Tambahkan filter `type` pada pembacaan stok: baris dengan type `DEPLOY` dan `REGISTER_INFRA` bukan stok gudang.
- SERIALIZED/ROLL: di query kedua `unitsAsOf()` tambahkan `whereNotIn('type', [DEPLOY, REGISTER_INFRA])`. Karena yang dipilih
  adalah baris TERAKHIR, unit yang sudah terpasang gugur dengan benar.
- QUANTITY: di `quantityAsOf()`/`deployQuantityAt()` jangan jumlahkan baris DEPLOY ke saldo gudang.
- Perbarui komentar kelas supaya daftar type yang "menulis to_pop_id tapi bukan stok" tertulis eksplisit.
- Alternatif lebih bersih tapi lebih besar: lokasi infrastruktur ditulis ke kolom lain (`to_node_id`), bukan `to_pop_id`.
  Hanya ambil kalau ada rencana migrasi data ledger.

**Test.** `StokAkhirTidakMenghitungAsetTerpasangInfrastrukturTest`: Receive → Issue → Deploy, Stok Akhir cabang = 0 untuk
SERIALIZED, QUANTITY, dan ROLL.

## 3. Return custody ke cabang mana pun

**Gejala.** `returnSerialToWarehouse()`, `returnToWarehouse()`, `returnRollToWarehouse()` menerima `Pop $cabang` bebas selama
dalam scope aktor. Admin `all_pop` bisa memindahkan stok Jetis ke Sandya tanpa Transfer.

**Solusi.** Paksa tujuan = cabang asal Issue.
- SN: `abort`/throw jika `$cabang->id !== $serial->issued_from_pop_id`. Untuk SN hasil REGISTER_INFRA (kolom itu null, lihat #6)
  pakai `current_pop_id` sebagai fallback.
- Custody QUANTITY/ROLL: ambil cabang asal dari custody (cek skema `docs/warehouse/database-schema.md`; kalau tidak ada kolomnya,
  turunkan dari baris ledger ISSUE terakhir untuk custody itu) dan bandingkan.
- Parameter `$cabang` di signature lebih baik dihapus dan diturunkan di service, supaya pemanggil tidak bisa salah.
- Perpindahan lintas cabang yang memang disengaja tetap lewat Transfer.

**Test.** `ReturnCustodyLintasCabangDitolakTest` untuk tiga tipe barang.

## 4. Retur Cabang→Pusat muncul di antrean Terima biasa

**Gejala.** Header retur (`IN_TRANSIT`, `to_pop_id` = Pusat) lolos filter `WarehouseTransferActionQueue::forUser()`.
`receiveTransfer()` lalu menandai SN AVAILABLE tanpa tahap 3 (kondisi final, harga, `DeviceRetrievalLog`, penutupan header).

**Solusi.**
- `receiveTransfer()`: tolak jika pop asal transfer **bukan Pusat** (alur Transfer biasa selalu Pusat→Cabang; retur selalu
  Cabang→Pusat). Pesan: "Pengiriman retur dikonfirmasi per SN lewat halaman Retur ke Pusat."
- `WarehouseTransferActionQueue::forUser()`: tambahkan kondisi asal = Pusat, supaya tombol Terima tidak muncul.
- Pembeda lebih eksplisit (opsional): kolom `kind` (`distribution`/`return`) di `inventory_transfers`. Hanya ambil kalau
  pembeda via pop asal dianggap rapuh.

**Test.** `TransferReturCabangKePusatTidakBisaDiTerimaLewatReceiveBiasaTest`.

## 5. Walk-in menerima modem milik pelanggan

**Gejala.** `pickupSerialFromCustomer()` menolak SN ber-ownership CUSTOMER, tetapi `receiveSerialFromCustomerAtWarehouse()` dan
`claimSerialFromCustomer()` tidak. SN dengan `InventorySerialSale` masuk stok perusahaan sementara Laporan Barang Terjual
masih mencatatnya terjual.

**Solusi.** Ekstrak satu guard privat `assertNotCustomerOwned(InventorySerial $serial)` (cek ownership dan keberadaan
`InventorySerialSale`), panggil di ketiga jalur. Kalau bisnis ingin mendukung pembelian kembali modem pelanggan, itu
alur terpisah (jual-balik) dengan jurnal sendiri, bukan lewat jalur retur.

**Test.** `WalkInModemMilikPelangganDitolakTest`.

## 6. Aset infrastruktur dicabut kembali "baru"

**Gejala.** `retrieveSerialFromLocation()` mengembalikan SN ke ISSUED tanpa menyentuh kondisi. Lalu `returnSerialToWarehouse()`
membuatnya AVAILABLE dengan kondisi `new`/null sehingga lolos gate Issue. `issued_from_pop_id` juga kosong untuk SN hasil
REGISTER_INFRA.

**Solusi.**
- Saat dicabut: set `condition = USED_GOOD` dan `condition_checked_at = null`, pola sama dengan jalur modem retur
  (`InventoryReassignService.php:188-192`). Gate `isClearedForIssue()` otomatis tertutup sampai staf memeriksa lewat
  `markSerialConditionChecked()`.
- Isi `issued_from_pop_id` dari `current_pop_id` saat REGISTER_INFRA atau saat dicabut, supaya #3 punya acuan.
- Pastikan SN ini muncul di daftar "belum dicek" (`WarehouseHistoryController` baris ~118 sudah menyaring `condition != new`
  dan `condition_checked_at` null).

**Test.** `AsetInfrastrukturDicabutWajibDiperiksaSebelumIssueTest`.

## 7. Nomor referensi ledger `max()+1`

**Gejala.** `generateReferenceNumber()` di `InventoryService.php:446` (dan padanannya di service Reassign, Transfer,
ReturnTransfer, Issue, Receive, Adjustment, Opname) mengambil `max()` atas tabel dokumen. CLAUDE.md melarang ini.
Dua request bersamaan mendapat nomor sama; pada `inventory_transfers.reference_number` (unique) salah satu gagal 500.
Prefix `RTR-` dipakai dua dokumen (baris transaksi cabut aset dan header retur ke Pusat).

**Solusi.**
- Tambah method umum di `NumberSequenceService`, mis. `ledgerNumber(string $prefix): string`, memakai mekanisme counter atomik
  yang sama dengan `fopTaskNumber()` (key per prefix + tanggal). Format output tetap `{PREFIX}-{YYYYMMDD}-{6 digit}`.
- Ganti semua `generateReferenceNumber()` privat di modul gudang dengan pemanggilan itu, lalu hapus method privatnya.
  Cari dulu dengan `grep -rn "generateReferenceNumber" app/Services`.
- Beri prefix berbeda untuk baris ledger cabut aset (usulan `CBT`) agar tidak bentrok dengan header `RTR`.
  Nomor lama tidak diubah.
- Pastikan counter baru diinisialisasi dari nilai tertinggi yang sudah ada per prefix, supaya tidak mundur ke nomor
  yang sudah terpakai pada hari berjalan (cara inisialisasi mengikuti implementasi `NumberSequenceService`).

**Test.** `NomorReferensiLedgerUnikSaatBersamaanTest` (panggil berurutan banyak kali, assert unik dan naik) dan
`PrefixRtrHanyaUntukHeaderReturPusatTest`.

## 8. `DeviceRetrievalLog` CREQ_SWAP tanpa `warehouse_pop_id`

**Gejala.** Jalur DEAC mengisi `warehouse_pop_id`, jalur CREQ_SWAP (`InventoryService.php:320`) tidak. `WarehouseRetrievalHistoryController`
memfilter non-`all_pop` dengan `whereIn('warehouse_pop_id', ...)`, jadi log Ganti Modem/Migrasi tidak terlihat oleh admin
cabang dan PIC. `confirmReturnedSerial()` juga tidak melengkapinya.

**Solusi.**
- Isi `warehouse_pop_id` saat create, dengan sumber yang sama dipakai jalur DEAC (cabang gudang tempat modem akan diterima).
- `confirmReturnedSerial()`: bila `warehouse_pop_id` masih null, isi dari cabang konfirmasi.
- Data lama yang NULL: buat command artisan backfill sekali jalan, **jalankan hanya setelah persetujuan user** (mengubah data
  historis). Sebelum itu ukur dulu: `select count(*) from device_retrieval_logs where warehouse_pop_id is null`.

**Test.** `RiwayatPengambilanCreqSwapTampilUntukAdminCabangTest`.

## 9. Custody terpotong dua kali saat revisi instalasi

**Gejala.** Pada `revision_installation`, `storePemasangan()`/`storeSpeedtest()` bisa diulang. `TaskMaterialService::sync()`
menghapus baris terpakai, lalu `reconcileMaterialsAgainstCustody()` memotong custody lagi. Potongan pertama tidak pernah
dikembalikan: `qty_remaining` turun dua kali dan sisanya hilang tanpa jejak ledger.

**Solusi.** Buat rekonsiliasi idempoten dalam satu transaksi:
1. Sebelum menghapus baris terpakai, kembalikan potongan lamanya: untuk tiap baris yang berasal dari `consumeFromCustody()`
   (punya `lot_no` + `unit_price_snapshot`) tambahkan lagi ke `qty_remaining` custody lot itu dan buka status `DEPLETED` bila perlu.
   Untuk roll, kembalikan `length_remaining` pada cara yang sama.
2. Baru potong ulang sesuai isi laporan terbaru.
3. Alternatif: hitung selisih (delta) antara yang sudah terpotong dan target baru, lalu potong/kembalikan selisihnya saja.
   Lebih sedikit tulisan DB, tapi butuh pemetaan baris↔custody yang pasti.
- Jalur infrastruktur sudah mengembalikan qty; samakan polanya, jangan bikin mekanisme baru.

**Test.** `RevisiInstalasiTidakMemotongCustodyDuaKaliTest`: laporan pertama pakai 5 pcs, revisi pakai 5 pcs, sisa custody harus
turun 5 (bukan 10); revisi dengan pemakaian lebih kecil mengembalikan selisih.

## 10. Adjustment pakai model basi tanpa lock

**Gejala.** `adjustCustody()`, `adjustSerialStatus()`, `adjustRollStatus()` menghitung dari model parameter, di luar
re-fetch `lockForUpdate()` dalam transaksi (service lain di modul yang sama sudah benar). `newRemaining = qty_remaining basi + delta`
bisa menimpa potongan `consumeFromCustody()` yang terjadi di antaranya.

**Solusi.** Di awal tiap `DB::transaction`, `findOrFail()->lockForUpdate()` ulang model, lalu semua guard (SCRAPPED, status
sama, hasil negatif) dan perhitungan memakai salinan segar. Pola contoh: `InventoryReassignService::returnSerialToWarehouse()`
baris 111.

**Test.** `AdjustmentMemakaiDataTerbaruTest`: lewatkan model basi sebagai parameter setelah custody dipotong, assert hasil
memakai nilai terbaru.

---

## Gap alur (bukan bug kode, butuh keputusan)

| Gap | Kondisi saat audit | Opsi saat audit |
|---|---|---|
| Terima transfer buta | `WarehouseTransferController::receive()` selalu konfirmasi 100%. Partial di service tidak terjangkau UI. Kalau partial, sisa QUANTITY hilang dari ledger dan SN sisa `TRANSFERRED` tanpa jalur konfirmasi ulang. | UI input jumlah/centang SN diterima, status `PARTIAL`, header tetap terbuka sampai sisa diselesaikan (diterima/dilaporkan hilang). |
| DAMAGED/QUARANTINE tanpa jalan keluar | `adjustSerialStatus` hanya arah masuk. Tidak ada perbaikan/RMA/rongsok. | Tambah transisi keluar: `DAMAGED → AVAILABLE` (setelah diperbaiki, wajib cek kondisi), `→ SCRAPPED`, `→ LOST`, lengkap dengan alasan + ledger. |
| Logistik balik dan opname | Transfer hanya Pusat→Cabang. Tidak ada opname per SN/roll. Barang non-SN yang dijual tidak punya jalur di ledger. | Prioritaskan sesuai praktik lapangan; tiap item layak jadi ADHOC sendiri. |

### Status tindak lanjut 2026-10-24

- Bug kode #1–#10 sudah diperbaiki dan diuji regresi (ADHOC-213). Command backfill `warehouse_pop_id` data CREQ lama tersedia dalam mode pratinjau; perubahan data historis belum dijalankan.
- Gap **terima transfer buta**: penerimaan parsial per SN/roll/qty dan penerimaan susulan sudah tersedia (ADHOC-214). Sisa tampil di antrean dan posisi stok dalam perjalanan. Penutupan sisa yang dinyatakan hilang kini tersedia untuk TRF dua arah dengan alasan, bukti, rugi, dan status `closed_lost`.
- Gap **DAMAGED/QUARANTINE tanpa jalan keluar**: keputusan harga ditegaskan—perbaikan internal tanpa biaya tidak mengubah harga perolehan barang yang kembali layak. Alur inspeksi, perubahan ke AVAILABLE, dan pembalikan rugi untuk kerusakan yang diakui sudah diimplementasikan; harga perolehan unit tetap.
- Gap **logistik balik dan opname**: transfer stok biasa Cabang→Pusat dan opname SN/roll sudah diimplementasikan. Usulan penjualan non-SN dibatalkan oleh keputusan user 2026-10-26: barang habis pakai tanpa SN tidak dijual lewat Gudang. Penjualan Gudang hanya untuk unit ber-SN (bawaan alat atau buatan sistem). Transfer stok antar gudang tetap dicatat sebagai mutasi, bukan jual beli.
- Tambahan ADHOC-214: jejak pemakaian roll, filter teknisi Riwayat Mutasi, verifikasi rollover stok bulanan, serta kerugian sisa roll yang dimusnahkan. Poin pencatatan berdasarkan status aktif pelanggan dibatalkan user.

## Urutan pengerjaan yang disarankan

1. **#1, #4, #5, #6** — mencegah barang rusak/bukan milik perusahaan masuk stok. Satu kelompok karena menyentuh gate kondisi yang sama.
2. **#2, #3** — kebenaran laporan dan batas cabang.
3. **#7, #9, #10** — integritas dan race. #7 menyentuh banyak file, kerjakan sebagai satu commit terpisah.
4. **#8** — kecil, tapi backfill data lama menunggu persetujuan.
5. Gap alur — setelah keputusan bisnis.

## Pertanyaan yang perlu keputusan user

- #1: modem rusak di Pusat dibiarkan `DAMAGED` sampai ada jalur perbaikan/rongsok (usulan), atau dibuat status baru?
- #5: apakah perlu alur jual-balik modem pelanggan, atau cukup ditolak?
- #8: setuju backfill `warehouse_pop_id` lama lewat command artisan?
