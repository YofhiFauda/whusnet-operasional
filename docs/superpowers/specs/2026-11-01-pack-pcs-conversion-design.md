# Konversi Pack↔PCS untuk Barang Satuan Kemasan (Sleave, Clam, Ties, RJ45)

**Tanggal:** 2026-11-01
**Status:** Disetujui user (chat), implementasi berjalan.

## Latar Belakang

Barang tracking_type `QUANTITY` seperti Sleave, Clam, Ties, RJ45 secara fisik
dikemas per Pack (1 Pack = 50 pcs, bisa beda per barang). Staf menerima,
transfer, dan kirim ke teknisi dalam satuan Pack, tapi sistem hari ini hanya
punya satu kolom `qty` per baris ledger — staf harus mengonversi manual ke
pcs saat input, rawan salah hitung dan histori tidak menunjukkan jumlah Pack
aslinya.

Pemakaian oleh teknisi (pemasangan, maintenance via `task_materials`) **tidak
menulis baris `inventory_transactions`** — dipotong langsung dari
`technician_custody` lewat `InventoryService::consumeFromCustody()` dalam
satuan pcs. Jalur ini tidak tersentuh fitur ini.

## Scope

Konversi Pack↔pcs berlaku HANYA di 3 titik yang menambah/memindah stok dalam
satuan kemasan:

1. **Receive** (`InventoryReceiveService` / barang masuk Pusat)
2. **Transfer** — dispatch (Pusat→Cabang) DAN confirm/receiveTransfer
   (termasuk partial receive), `InventoryTransferService`
3. **Issue** (Cabang → custody Teknisi), `InventoryIssueService`

Tidak berlaku untuk: `RETURN`, `TRANSFER_CUSTODY`, `ADJUSTMENT`,
`STOCK_OPNAME`, dan pemakaian teknisi (`consumeFromCustody`) — semua itu
tetap input pcs langsung, tanpa perubahan.

## Data Model

### `items.pack_size` (baru, nullable `decimal(10,2)`)

Pola sama seperti `meter_per_roll` (per-item, admin isi di Master Barang).
`NULL` = item tidak pakai konversi Pack, form tetap seperti sekarang (input
pcs langsung). Ditambah ke `#[Fillable]` pada `App\Models\Item`, form
create/edit Item (`Master\ItemController`), ditampilkan di tabel/detail Item.

### `inventory_transactions.entered_pack_qty` (baru, nullable `decimal(12,2)`)

Snapshot jumlah Pack yang diketik staf — murni untuk audit/tampilan. Kolom
`qty` (existing) **tetap selalu dalam pcs**, tidak berubah semantiknya; semua
pembaca `qty` saat ini (saldo, custody, FIFO, laporan) tidak perlu diubah.

Relasi: `qty = entered_pack_qty * item.pack_size` pada baris yang
diisi (dihitung Service saat insert, bukan trigger DB). Baris yang bukan
hasil input Pack (item tanpa `pack_size`, atau tipe transaksi di luar
scope) punya `entered_pack_qty = NULL`.

## Perilaku Form & Service

Pada 3 titik di scope: kalau `item->pack_size` terisi, field input qty di
form berganti label jadi "Jumlah Pack" (bukan ganti ke dropdown satuan —
sesuai keputusan user, item berpack_size SELALU input Pack, tidak ada pilihan
pcs langsung di 3 form ini). Service menerima angka Pack dari input,
menghitung `qty_pcs = pack_count * item->pack_size`, menyimpan baris ledger
dengan `qty = qty_pcs` dan `entered_pack_qty = pack_count`.

Item tanpa `pack_size` (mayoritas barang lain): form & service berjalan
identik seperti sekarang, tidak ada perubahan perilaku.

Validasi: `pack_count` harus > 0 (desimal diperbolehkan jika barang butuh,
default integer-friendly). Kalau `pack_size` tidak terisi di master tapi staf
tetap kirim field pack count (mestinya tidak mungkin dari UI normal) —
Service menolak, karena field itu hanya relevan kalau pack_size ada.

## Tampilan / Histori

Di tempat `qty` + `item->unit` ditampilkan untuk baris RECEIVE/TRANSFER/ISSUE
(detail Receive, detail Transfer, detail Issue, Riwayat/Traceability):
kalau `entered_pack_qty` terisi, tampilkan `"{entered_pack_qty} Pack ({qty}
pcs)"`; kalau tidak, tampilkan seperti sekarang (`"{qty} {item->unit}"`).

## Yang Tidak Berubah

- Perhitungan saldo (`inventory_balances`, `WarehouseStockAsOfService`),
  custody, FIFO consumption — semua baca `qty` pcs seperti sebelumnya.
- Barang tanpa `pack_size` — tidak ada perubahan form/alur sama sekali.
- Pemakaian material teknisi (pemasangan/maintenance) — tetap pcs langsung,
  tidak ada baris ledger baru dari fitur ini.
- Tipe transaksi `RETURN`, `TRANSFER_CUSTODY`, `ADJUSTMENT`, `STOCK_OPNAME`.

## Testing

- Test baru per Service (Receive/Transfer/Issue) untuk item berpack_size:
  input Pack → `qty` tersimpan terkonversi, `entered_pack_qty` tersimpan.
- Item tanpa `pack_size` tidak terpengaruh (regresi existing test harus tetap
  lulus tanpa modifikasi assertion qty).
- Partial receive Transfer dengan pack_size: confirm sebagian Pack, baris
  ledger sesuai.
- Tampilan histori menunjukkan format Pack(pcs) yang benar.
