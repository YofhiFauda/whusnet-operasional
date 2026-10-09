# SUDAH DI KERJAKAN 


# Analisa — 2-Slot Harga (Lama/Baru) untuk Barang QUANTITY, Hapus `BATCH`

Dicatat 2026-09-17, dari diskusi user. **Sudah diimplementasi** — analisa & rancangan dulu, di luar sprint aktif (Sprint 8.10, Audit Trail + Notification).

## 1. Pemicu

Kelola Stok Gudang cuma nampilin 1 harga per barang `tracking_type=QUANTITY`. Kalau item yang sama diterima 2x dengan harga beda (mis. Kabel Dropcore 4 Core: 12 roll @250.000 tanggal 16/09/2026, lalu 10 roll @260.000 tanggal 1/10/2026), keduanya numpuk jadi satu qty tanpa histori harga terpisah — riskan buat pembukuan.

Kabel sendiri sebenarnya sudah aman: `TrackingType::ROLL` bikin 1 baris `inventory_rolls` per roll fisik, `unit_price_snapshot` nempel per baris (`InventoryReceiveService::receiveRoll()`), gak pernah digabung. Masalah cuma nyata di item `tracking_type=QUANTITY` — balance-nya `increment()` polos ke satu angka (`InventoryReceiveService::receiveQuantity()` baris 58), harga lama-baru gak kepisah.

## 2. Opsi yang dibahas & ditolak

1. **BATCH manual** (`lot_no` diketik staf) — ditolak: staf baru bingung kapan pilih QUANTITY vs BATCH; `lot_no` bebas ketik rawan typo/duplikat makna beda.
2. **Auto-generate lot_no** ala `roll_code` (`{item.code}-{tanggal}-{increment}`) — mendekati, tapi tetap nyisain 2 keputusan desain: enum tracking_type ganda per item (bikin bingung pegawai baru pas isi Master Barang) + lot tumbuh tanpa batas kalau tiap RECEIVE bikin baris baru.

## 3. Bukti dari pembukuan real — bukan hipotesis

Dicek langsung `docs/plan/warehouse/laporan/LAPORAN ADMIN GUDANG PER AGST 26.xlsx` (laporan bulanan Admin Warehouse per OLT/cabang, Agustus 2026). Struktur kolom per baris barang:

```
No | Nama Barang | Satuan | STOK BELI(Qty-lama, Harga-lama, Qty-baru, Harga-baru, Nilai)
   | HUTANG(Qty) | STOK AWAL(Qty-lama, Harga-lama, Qty-baru, Harga-baru, Nilai)
   | STOK TERPAKAI(Qty) | STOK AKHIR(Qty-lama, Harga-lama, Qty-baru, Harga-baru, Nilai)
```

Contoh baris **Modem Fiberhome** (OLT Whusnet, Agustus 2026):
- Stok Beli: 0 @0 (lama) + 21 @255.000 (baru) = Rp 5.355.000
- Stok Awal: 13 @185.000 (lama) + 10 @255.000 (baru) = Rp 4.955.000 (13×185.000 + 10×255.000 = 4.955.000, cocok)
- Stok Terpakai: 41
- Stok Akhir: 23 @185.000 (lama) + 0 @255.000 (baru) = Rp 4.255.000

Barang yang harganya gak pernah berubah (kaos, mug, goodiebag) cuma keisi 1 kolom harga terus-terusan — kolom "baru" nganggur 0.

**Kesimpulan:** admin gudang **sudah bertahun-tahun** pakai pola **persis 2 slot harga** (Lama/Baru) per barang, bukan lot tak terbatas ala BATCH. Sistem tinggal niru pola yang sudah terbukti jalan, bukan mengarang mekanisme baru.

## 4. Keputusan desain

- **Hapus `TrackingType::BATCH`** dari enum — gak pernah dipakai di data real (0 item), dan pola lot-nya emang gak cocok sama praktik gudang yang sebenarnya.
- **QUANTITY dapat 2 slot harga bawaan**, tanpa pilihan manual apa pun di Master Barang. Semua barang QUANTITY otomatis begini — barang harga statis ya slot "baru" kepakenya terus, slot "lama" nol permanen.
- **`ROLL` dan `SERIALIZED` tidak berubah** — masing-masing sudah py identitas per-unit + harga sendiri, gak butuh mekanisme ini.
- **Konsumsi (ISSUE/dsb) FIFO** — abisin slot Lama dulu, baru pindah ke Baru begitu Lama = 0.
- **Kasus harga ke-3** (RECEIVE baru masuk sementara slot Lama masih py sisa DAN harga beda dari slot Baru saat ini) — user konfirmasi **belum pernah terjadi di data real** (2026-09-16). Perilaku sistem buat kasus ini **sengaja belum didefinisikan** — lihat §6.

## 5. Dampak implementasi (perkiraan, belum final)

- **Migration** `inventory_balances`: ganti kolom `qty` tunggal jadi `qty_lama`, `harga_lama`, `qty_baru`, `harga_baru`. Kolom `lot_no` kemungkinan dihapus/di-drop begitu `BATCH` resmi tidak ada (cek dulu apakah ada data produksi yang sudah pakai `lot_no` bukan `''`).
- **Enum** `App\Enums\TrackingType` — hapus case `BATCH`, update docblock rasionalnya (yang sekarang eksplisit bilang "3 nilai awal + ROLL belakangan" — perlu direvisi jadi "balik ke 3: SERIALIZED/QUANTITY/ROLL").
- **`InventoryReceiveService::receiveQuantity()`** — hapus parameter `lotNo`, hapus `normalizeLotNo()`/`assertQuantityOrBatchTracking()`. Logic baru: kalau `unitPrice` sama dengan `harga_baru` existing → `qty_baru += qty`; kalau beda → `harga_baru` lama pindah jadi `harga_lama` (kalau `qty_lama` masih ada sisa dari sebelumnya dan harganya beda dari `harga_baru` yang mau digeser → **kasus §6, belum didefinisikan**), `qty_baru`/`harga_baru` diisi data baru.
- **`InventoryIssueService`, `InventoryAdjustmentService`, `InventoryTransferService`** — semua titik yang baca/tulis `InventoryBalance.qty` buat item QUANTITY perlu logic FIFO 2-slot (baca dari Lama dulu).
- **View Kelola Stok Gudang** (`warehouse/stock/index.blade.php`, hasil redesign ADHOC-56) — perlu render 2 kolom harga, bukan 1. Halaman Laporan Agregat Periodik (ADHOC-55) juga kena.
- **Test lama** yang pakai `lot_no`/BATCH di `InventoryReceiveService`, `WarehouseReceiveTest`, dll — perlu diaudit & disesuaikan.

## 6. Belum diputuskan (blocker sebelum mulai koding)

- **Perilaku kedatangan harga ke-3** saat slot Lama belum habis — apakah:
  (a) sistem **menolak** RECEIVE sampai slot Lama habis dulu (paksa selesaikan stok lama sebelum terima harga baru lagi), atau
  (b) slot Lama yang lama di-average-kan ke slot Baru (kehilangan presisi FIFO), atau
  (c) sesuatu yang lain.
  Karena belum pernah terjadi di data real, rekomendasi sementara: **(a) tolak dengan pesan jelas** — biar ketauan begitu kejadian beneran (bukan diam-diam salah hitung), keputusan final nyusul kalau kasusnya muncul.
- **Audit data existing**: apakah ada item di database (dev/staging/produksi) yang sudah `tracking_type=BATCH` dengan saldo berjalan? Kalau ada, perlu strategi migrasi data sebelum enum case dihapus (migration DB gak bisa asal drop kalau ada FK/data nyantol).
- Entri ADHOC baru di `docs/TASKS.md` masih placeholder (ADHOC-71) — detail final nyusul begitu §6 kelar dan siap masuk sprint.

## 7. Test yang wajib ditambah nanti

`InventoryReceiveQuantityTwoTierPriceTest` (slot lama/baru kebentuk & keupdate bener), `InventoryIssueFifoTwoTierTest` (konsumsi abisin lama dulu), `InventoryQuantityThirdPriceGuardTest` (guard §6 opsi a), regresi `WarehouseStockDisplayTest` (2 kolom harga tampil bener di Kelola Stok).
