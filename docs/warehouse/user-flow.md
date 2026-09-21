# User Flow — Modul Gudang

Mengikuti konvensi 3-pola aksi baru repo ini (CLAUDE.md § Aksi baru): **view-only → modal**, **mutasi data → halaman create tersendiri**, **aksi lanjutan di halaman Detail miliknya sendiri → inline toggle Alpine**. Modul Gudang jadi acuan tertulis pertama untuk 3 pola ini.

## Peran & Cakupan

- **Admin Gudang Pusat** — POP scope ke gudang bertipe `pusat` (dan `all_pop` untuk owner/atasan).
- **Admin Gudang Cabang (`pop_admin`)** — scope hanya ke POP cabangnya sendiri; semua aksi ditegakkan trait `AuthorizesWarehousePop` di titik create/store, bukan hanya filter dropdown.
- **Teknisi** — tidak punya UI Gudang; custody dikonsumsi otomatis lewat form Laporan Pemasangan/Maintenance.

## 1. Dashboard (`warehouse.index`)

Semua peran ber-permission `warehouse.view`. Kartu KPI (low stock, transit, custody, quarantine, pending stock request, opname compliance, arus harian) + kartu per-POP sesuai scope. Murni view, tidak menulis DB.

## 2. Barang Masuk / Receive (Admin Gudang Pusat)

**Halaman create** (`warehouse.receive.create` → `warehouse.receive.show`), bukan modal — form input majemuk (multi-item, textarea SN) butuh `back()->withErrors()->withInput()` yang aman dari refresh/double-submit.

1. Pilih Gudang Pusat (kalau akses lebih dari satu).
2. Tambah baris per item, bentuk form ikut `tracking_type` barangnya: qty+harga (QUANTITY), textarea SN+harga (SERIALIZED manual), jumlah unit+harga (SERIALIZED auto-generate — ODP/Splitter), atau jumlah roll+vendor+harga (ROLL).
3. Submit → `POST /warehouse/receive` → redirect ke `warehouse.receive.show` (pola PRG) menampilkan ringkasan satu `reference_number` (`RCV-...`).

## 3. Transfer Pusat → Cabang (2 halaman, 2 aktor)

**Dispatch** (Admin Pusat, halaman create):
1. `warehouse.transfers.create` — pilih Cabang tujuan, cek stok tersedia (`availableStock` — AJAX/fetch dari halaman yang sama).
2. Tambah baris qty/SN per item.
3. Submit → `POST /warehouse/transfers` → redirect `warehouse.transfers.show`.

**Confirm** (Admin Cabang, di halaman Detail transfer yang sama — **inline toggle**, bukan halaman terpisah, karena sudah di 1 record spesifik):
1. Buka `warehouse.transfers.show` untuk transfer `in_transit` yang ditujukan ke cabangnya.
2. Centang SN yang fisik diterima / isi qty aktual per item.
3. Submit (`POST /warehouse/transfers/{transfer}/receive`) → transfer jadi `RECEIVED` atau `RECEIVED_PARTIAL` (partial diperbolehkan, tidak ada blokir).

## 4. Keluar ke Teknisi / Issue (Admin Gudang Cabang)

**Halaman create** (`warehouse.issues.create` → `warehouse.issues.show`):
1. Pilih teknisi penerima.
2. Cek stok tersedia (`availableStock`), tambah baris qty/SN.
3. Submit → `POST /warehouse/issues` → redirect ke show, satu `reference_number` (`ISS-...`). Stok Cabang berkurang **dan** custody teknisi terbentuk dalam satu aksi.

## 5. Kelola Stok (`warehouse.stock.index`)

Hub gabungan (quantity balance + serial count), titik masuk ke 4 aksi lain (Receive/Transfer/Issue/Adjustment via link/tombol). View-only sendiri; `serials` (`warehouse.stock.serials`) tab tambahan.

**Ambang Stok Rendah** — pengecualian pola: bukan halaman create klasik untuk pergerakan barang, tapi tetap halaman terpisah (`warehouse.stock.threshold.create` → `store`) karena mengubah `minimum_stock`/`maximum_stock` langsung (bukan lewat ledger).

## 6. Adjustment (Admin Gudang Cabang/Pusat) — 4 sub-form, semua halaman create

Semua reuse permission `warehouse_adjustment.create`:

- **Saldo POP** (`warehouse.adjustments.balance.create`) — koreksi manual stok gudang, delta signed, reason wajib.
- **Stock Opname** (`warehouse.adjustments.opname.create`) — input hasil hitung fisik (angka absolut, bukan delta), termasuk hasil pas (selisih 0).
- **Custody** (`warehouse.adjustments.custody.{custody}.create`) — dari halaman Custody index, pilih baris custody teknisi → adjust. Reason dropdown terarah; evidence foto **wajib** untuk `lost`/`damaged`.
- **Serial** (`warehouse.adjustments.serial.{serial}.create`) — dari Traceability/Custody, ubah status SN ke LOST/DAMAGED/SCRAPPED/QUARANTINE. Evidence wajib kecuali QUARANTINE.

## 7. Reassign Custody (Admin Gudang Cabang)

Dua sub-form halaman create, permission `warehouse_reassign.create`, dipicu dari baris Custody index:

- **Custody** (`warehouse.reassign.custody.{custody}.create`) — pilih aksi `return` (ke gudang cabang) atau `transfer` (ke teknisi lain, isi teknisi penerima). Reason wajib (resign/cuti/rotasi/dll).
- **Serial** (`warehouse.reassign.serial.{serial}.create`) — sama, untuk SN.

## 8. Custody Aktif (`warehouse.custody.index`)

**View-only, read-only list** — daftar semua custody aktif (material + serial), KPI per satuan. Titik lompat ke Adjustment/Reassign lewat tombol per baris.

## 9. Traceability (`warehouse.traceability.index`)

**View-only** + satu aksi inline — cari 1 SN, tampilkan seluruh ledger `inventory_transactions` terurut kronologis. Di luar jangkauan POP aktor → tampil "tidak ditemukan" (bukan 403), supaya keberadaan SN di cabang lain tidak bocor.

Badge kondisi fisik (`ItemCondition`, ADHOC-80) tampil di samping badge status. Kalau SN berkondisi bekas & belum dicek, muncul tombol **"Tandai Sudah Dicek"** (inline toggle, bukan halaman baru — `POST warehouse.traceability.serial.condition-check`) — staf pilih hasil cek fisik (Kondisi Baik/Rusak), langsung ke-redirect balik ke halaman ini dengan badge terupdate. SN yang belum dicek tidak bisa di-Issue lagi sampai aksi ini dilakukan — lihat [business-logic.md §12](business-logic.md#12-kondisi-fisik-barang-serialized-adhoc-80).

## 10. Riwayat / History (`warehouse.history.index`)

**View-only** — ledger terpaginasi (30/halaman) + filter type/pop/search/date/**kondisi**/**alasan** (2 filter terakhir ditambah ADHOC-80 — kondisi baca `serial.condition`, alasan baca `resulting_status` khusus baris `adjustment`). Reuse permission `warehouse.view`.

## 11. Laporan (`warehouse.reports.index`)

**View-only** — laporan agregat bulanan, 2 tab (movement + adjustment kerugian) dalam satu halaman. Semua agregat qty dipecah per-item (tidak SUM lintas satuan berbeda).

## 12. Scan (`warehouse.scan.index`)

**View-only, sengaja tidak menulis DB sama sekali** — mode "scan-first": lookup SN → sistem menyarankan aksi kontekstual berdasarkan `SerialStatus` saat ini (mis. `ISSUED` → sarankan link ke Return/Adjustment), tapi eksekusi tetap lewat halaman mutasi asli (Adjustment/Reassign), bukan submit langsung dari sini.

## 13. Permintaan Stok / Stock Request (Admin Gudang Cabang → Pusat)

**Ajukan** (Cabang, halaman create, permission `warehouse_stock_request.create`):
1. `warehouse.stock-requests.create` — tambah baris item + qty diminta, catatan.
2. Submit → redirect ke `warehouse.stock-requests.show`.

**Kelola** (Pusat, di halaman Detail yang sama — **inline toggle**, sesuai pola 3 karena sudah di 1 record spesifik):
- **Catat Pengiriman** (toggle form, `warehouse_stock_request.approve`) — isi qty terkirim per baris (append, clamp ke sisa) → status otomatis `PARTIAL`/`FULFILLED`.
- **Tandai Cukup** (`fulfill`, tombol dengan konfirmasi) — langsung tutup `FULFILLED` apa pun sisanya.
- **Tolak** (`warehouse_stock_request.reject`, toggle form + alasan wajib) — hanya kalau status masih `PENDING` murni.

**Batalkan** (Cabang, pengaju sendiri, `warehouse_stock_request.cancel`) — hanya kalau `PENDING` murni, dicek kepemilikan eksplisit di Controller.

List (`warehouse.stock-requests.index`) menampilkan semua request lintas cabang (bagi Pusat) atau request cabang sendiri (bagi `pop_admin`).
