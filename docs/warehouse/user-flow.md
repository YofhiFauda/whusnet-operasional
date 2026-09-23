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

### 8a. Tab "Return dari Pelanggan" (di Custody Aktif, ADHOC-88)

Tab keempat (setelah Perangkat Serial Number, Perangkat Pasif, Roll Kabel): modem hasil pengambilan alat yang **masih dipegang teknisi** (`SerialStatus::RETURNED`, transit). Kolom: teknisi, barang, SN, dari pelanggan, tanggal diambil (+ "berapa lama"), gudang tujuan; tombol **Terima** → Terima Retur untuk yang ber-permission `warehouse_reassign.create`. Ikut filter teknisi/gudang/cari (SN, barang, nama pelanggan) dan scope POP gudang tujuan. Beda arah dari tab Perangkat Serial Number (barang yang **dibawa** ke lapangan, `ISSUED`) — ini barang yang dibawa **pulang**. Hilang dari tab begitu gudang menerima.

## 8b. Terima Retur (`warehouse.returns.index` → `warehouse.returns.receive.create`, ADHOC-86)

Konfirmasi gudang cabang atas modem hasil task Ambil Modem (DEAC). **Halaman create tersendiri** (mutasi data, pola 2), permission **reuse `warehouse_reassign.create`**, scope POP lewat gudang tujuan (`issued_from_pop_id`); menu sidebar "Terima Retur".

1. Daftar modem `RETURNED` di cabang aktor (SN, barang, dari pelanggan, teknisi pemegang, gudang tujuan).
2. **Terima** → halaman form: periksa fisik, pilih **kondisi** (Bekas — Kondisi Baik / Bekas — Rusak; bukan Baru), boleh **mengoreksi model** (mis. dari "Modem Pelanggan Lama"), **nilai taksiran (Rp)** opsional (kosong = Rp 0 di Laporan Bulanan). Ada petunjuk merek dari data lama pelanggan kalau tersedia (hanya bantuan).
3. Submit → SN jadi `AVAILABLE` di gudang (siap di-Issue lagi), ledger `RETURN` kedua tertulis, log riwayat dilengkapi penerimanya. Menerima dua kali ditolak.

Gudang **tidak** menginput SN manual untuk modem yang ditarik lewat task: SN diinput teknisi di lapangan dan didaftarkan otomatis (termasuk SN legacy yang belum pernah tercatat). Satu-satunya input manual gudang adalah menambah **model baru di Master Barang** kalau belum ada.

## 8c. Terima modem dari pelanggan (`warehouse.returns.from-customer.create`, ADHOC-88)

Pelanggan yang sudah putus **mengantar modem sendiri ke gudang, tanpa task DEAC**. Halaman create tiga keadaan yang di-render server lewat query string (tanpa endpoint JSON): **cari** pelanggan (nama/kode/CID/HP/SN; hanya yang `terminated` dan dalam scope POP) → **pilih** → **form**. Permission `warehouse_reassign.create`.

Form: gudang penerima, SN (baris repeatable, SN sesuai stiker), model (wajib hanya untuk SN yang belum tercatat), kondisi, foto kondisi (wajib), kelengkapan, nilai taksiran opsional, catatan. **Satu langkah, tanpa transit** — staf sudah memegang fisiknya, jadi SN langsung `AVAILABLE`. Banyak SN dalam satu form diproses atomik (satu ditolak → semua batal, foto yatim dihapus). Alat pelanggan ikut ditandai diambil (`device_retrieved_at`).

Guard: ditolak kalau masih ada **task Ambil Alat yang berjalan** untuk pelanggan itu (modem bisa tercatat dua kali). Sengaja **bukan** lewat Barang Masuk (jalur pengadaan: kondisi dipaksa baru, harga wajib, hanya Pusat, tidak tertaut ke pelanggan).

## 9. Traceability (`warehouse.traceability.index`)

**View-only** + satu aksi inline — cari 1 SN, tampilkan seluruh ledger `inventory_transactions` terurut kronologis. Di luar jangkauan POP aktor → tampil "tidak ditemukan" (bukan 403), supaya keberadaan SN di cabang lain tidak bocor.

Badge kondisi fisik (`ItemCondition`, ADHOC-80) tampil di samping badge status. Kalau SN berkondisi bekas & belum dicek, muncul tombol **"Tandai Sudah Dicek"** (inline toggle, bukan halaman baru — `POST warehouse.traceability.serial.condition-check`) — staf pilih hasil cek fisik (Kondisi Baik/Rusak), langsung ke-redirect balik ke halaman ini dengan badge terupdate. SN yang belum dicek tidak bisa di-Issue lagi sampai aksi ini dilakukan — lihat [business-logic.md §12](business-logic.md#12-kondisi-fisik-barang-serialized-adhoc-80).

## 10. Riwayat / History (`warehouse.history.index`)

**View-only** — ledger terpaginasi (30/halaman) + filter type/pop/search/date/**kondisi**/**alasan** (2 filter terakhir ditambah ADHOC-80 — kondisi baca `serial.condition`, alasan baca `resulting_status` khusus baris `adjustment`). Reuse permission `warehouse.view`.

## 10a. Riwayat Pengambilan Alat (`warehouse.retrievals.index`, ADHOC-88)

**View-only**, permission `warehouse.view`, menu sidebar "Riwayat Ambil Alat". Log **per SN**: pelanggan, SN + model, **teknisi/petugas pengambil**, sumber (diambil teknisi via task DEAC / diantar pelanggan), status (**Transit di teknisi** atau **Diterima** + gudang + penerima + tanggal), kondisi, dan foto. Filter: cari (SN/nama/kode pelanggan), teknisi, status, sumber, periode. Tunduk pada scope POP gudang tujuan. Terpisah dari Riwayat Mutasi (§10, ledger per dokumen) karena pertanyaannya beda: "siapa yang menarik modem ini", bukan "apa saja mutasinya". Dari halaman ini ada pintasan ke Terima Retur dan Terima modem dari pelanggan. Sumber data `device_retrieval_logs` — tidak ikut hilang saat Langganan Lagi.

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
