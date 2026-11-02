# Koreksi Stok Gudang — Satu Pintu Berbasis Situasi

Tanggal: 2026-10-31 · Status: **menunggu review user** · Modul: Warehouse (Adjustment)

## 1. Masalah

Empat halaman koreksi stok gudang terlihat mirip bagi end user karena dipisah berdasarkan **mekanisme teknis**, bukan situasi lapangan:

| Halaman | Route | Target data | Input |
|---|---|---|---|
| Stock Opname | `opname/create` | qty curah (gudang + item + lot) | jumlah fisik hasil hitung |
| Opname SN / Roll | `opname/unit/create` | 1 SN atau 1 roll | ada/tidak (SN) atau sisa meter (roll) + foto |
| Penyesuaian Stok | `balance/create` | qty curah | delta +/− manual |
| Tandai Status Perangkat | `serial/{id}/create` | 1 SN | status baru (lost/damaged/scrapped/quarantine) |

User harus tahu dulu istilah "opname vs delta" dan "qty vs SN vs roll" sebelum bisa mulai. Judul Penyesuaian Stok bahkan memuat kata "Opname". Merapikan label saja sudah dinilai tidak cukup.

## 2. Tujuan & keberhasilan

- User memilih berdasarkan **situasi**: "Saya menghitung fisik" atau "Ada barang bermasalah".
- Jenis barang (curah / SN / roll) **dideteksi sistem**, bukan dipilih user.
- Tidak ada perubahan logika bisnis, ledger, atau aturan bukti. Hanya UI, pintu masuk, dan satu endpoint baca.
- Keberhasilan: user baru bisa menyelesaikan koreksi tanpa membaca penjelasan istilah; satu menu menggantikan empat.

## 3. Di luar cakupan (keputusan user 2026-10-31)

Tetap seperti sekarang, dipanggil dari tombol per baris:
- Koreksi Custody teknisi (`custody/{id}/create`), koreksi Roll (`roll/{id}/create`), Perbaiki SN (`serial/{id}/repair`).
- `serial/{id}/create` (Tandai Status) **tetap hidup** karena tombol di Daftar Custody memakainya; kartu 2 hanya menjadi pintu tambahan ke aksi yang sama.

Alasan: ketiganya terikat ke satu record sehingga konteksnya sudah jelas; meleburnya menambah pencarian dan kasus tanpa mengurangi kebingungan.

## 4. Desain

### 4.1 Halaman pintu

Route baru `GET /warehouse/adjustments` → `warehouse.adjustments.index`, di blok `permission:warehouse_adjustment.create` yang sudah ada, **sebelum** route dinamis. Dua kartu:

1. **Cek & cocokkan stok** — "Saya menghitung barang di gudang, mau samakan dengan sistem."
2. **Ada barang bermasalah** — "Barang hilang, rusak, dibuang, atau salah catat."

Kartu memakai pola "halaman create tersendiri" (CLAUDE.md: mutasi data bukan modal). Dua form di bawah adalah halaman terpisah dengan `back()->withInput()` yang aman.

Menu sidebar/header gudang: dua link (Penyesuaian, Stock Opname) di `components/warehouse/header.blade.php` baris ±235 & ±792 diganti **satu** link "Koreksi Stok".

### 4.2 Kartu 1 — Cek & cocokkan stok

Route: `GET /warehouse/adjustments/check` → `warehouse.adjustments.check.create`.

Alur satu halaman:
1. Pilih **Gudang** (daftar sudah disaring POP scope, sama seperti `createOpname`).
2. Satu kolom pencarian **"Barang, nomor SN, atau kode roll"**. Server menentukan mode:
   - cocok item curah → mode **qty**: tampil stok sistem saat ini, input "Jumlah di rak sekarang" (+ lot bila perlu), selisih ditampilkan langsung di klien sebagai info.
   - cocok SN → mode **SN**: pilihan "Ditemukan sesuai stok" / "Tidak ditemukan — catat hilang".
   - cocok kode roll → mode **roll**: input "Sisa fisik roll (meter)".
3. Bagian bersama (partial §4.5): catatan + bukti foto. Aturan bukti **sama persis** dengan form lama masing-masing mode (qty: tanpa foto, opsional catatan; SN/roll: alasan + foto wajib).

Penyimpanan memakai endpoint lama, tanpa perubahan:

| Mode | Endpoint simpan |
|---|---|
| qty | `warehouse.adjustments.opname.store` → `recordStockOpname` |
| SN / roll | `warehouse.adjustments.unit-opname.store` → `recordSerialStockOpname` / `recordRollStockOpname` |

Sesuai CLAUDE.md, **URL aksi dirender server** (`route()`) sebagai atribut `data-*` untuk ketiga mode; klien hanya memilih salah satu, tidak merakit path.

### 4.3 Kartu 2 — Ada barang bermasalah

Route: `GET /warehouse/adjustments/problem` → `warehouse.adjustments.problem.create`.

Alur: pilih Gudang → cari barang/SN (resolver sama) → pilih **kejadian**:

| Kejadian | Barang ber-SN | Barang curah | Bukti foto |
|---|---|---|---|
| Hilang | status `lost` | kurangi qty | wajib (SN) |
| Rusak | status `damaged` | kurangi qty | wajib (SN) |
| Dibuang | status `scrapped` | kurangi qty | wajib (SN) |
| Karantina | status `quarantine` | tidak tersedia | tidak |
| Salah catat | tidak tersedia | tambah/kurangi qty (pilih arah) | tidak |

- Opsi yang tidak berlaku untuk jenis barang terpilih **disembunyikan**, bukan di-disable tanpa penjelasan.
- Barang curah: user mengisi **jumlah** positif; arah (−/+) ditentukan kejadian dan dikalikan di form (Alpine) menjadi field `qty_delta` yang dikirim ke `storeBalance`. Server tetap penegak: `storeBalance` menolak delta nol dan mengecek POP scope, jadi tidak ada hak baru dibanding form lama yang menerima delta bebas. Tidak ada endpoint simpan baru.
- Alasan: kejadian menjadi nilai `reason` baku (`hilang`, `rusak`, `dibuang`, `salah_catat`) ditambah catatan bebas. Daftar saran alasan lama (`rusak_terjatuh`, dll.) dipindah ke datalist partial supaya tidak ada dua sumber.

Endpoint simpan lama tetap dipakai: SN → `warehouse.adjustments.serial.store`, curah → `warehouse.adjustments.balance.store`.

### 4.4 Resolver (satu-satunya kode baru yang bukan tampilan)

`GET /warehouse/adjustments/resolve?pop_id=&q=` → JSON `{type: qty|serial|roll, id, label, current, unit, lots[]}`.
- Read-only, di bawah `permission:warehouse_adjustment.create`.
- **Wajib** lewat POP scope (`assertPopInScope` / `AuthorizesWarehousePop`, sama seperti `storeBalance`). Tanpa itu SN gudang lain bisa ditebak.
- Barang kategori `ItemCategory::CODE_MERCHANDISE_MARKETING` dikecualikan seperti di `createBalance`.
- Hasil ambigu (beberapa kecocokan) dikembalikan sebagai daftar pilihan, bukan ditebak.

### 4.5 Partial bersama

`resources/views/warehouse/adjustments/partials/_reason-evidence.blade.php`: catatan, alasan, bukti foto, pesan error. Parameter: label, `evidenceRequired`, daftar saran alasan. Dipakai oleh dua form baru **dan** dimigrasi ke form yang tidak dilebur (`custody`, `roll`, `repair-serial`, `serial`) supaya perubahan aturan bukti cukup di satu tempat. Migrasi form lama dilakukan tanpa mengubah nama field, jadi controller dan test lama tidak terdampak.

### 4.6 Tautan masuk yang diarahkan ulang

| Sumber | Sekarang | Nanti |
|---|---|---|
| Header gudang (2 link) | balance + opname | 1 link `adjustments.index` |
| Dashboard gudang `warehouse/index` ±443 | `opname.create` | `check.create` |
| Papan Kelola Stok `board/_detail` ±41 (`$opnameUrl`, "Sesuaikan") | `balance.create?pop_id&item_id&lot_no` | **`check.create`** dengan parameter sama, terisi. Alasan: variabelnya sudah bernama opname dan konteksnya "samakan dengan hitungan". User bisa pindah ke kartu 2 dari halaman itu. |
| Daftar Custody tombol SN | `serial.create` | tidak berubah |

### 4.7 Route lama

GET lama (`balance/create`, `opname/create`, `opname/unit/create`) menjadi **redirect 302** ke halaman baru dengan query dibawa, supaya bookmark dan link yang terlewat tidak 404. POST lama **tidak diubah**. Redirect dihapus pada rilis berikutnya bila tidak ada pemakaian.

## 5. Yang sengaja tidak diubah

- `InventoryAdjustmentService` dan semua metodenya.
- Validasi di `WarehouseAdjustmentController::store*`, termasuk `assertPopInScope`, `assertPopAdminNotBlockedByActivePicGudang`, `assertPicGudangCanActOnPop`.
- Permission (`warehouse_adjustment.create`) dan skema DB. Tidak ada migrasi.
- Tanpa gerbang approval baru (tetap sesuai keputusan kontrol-anti-manipulasi §1).

## 6. Risiko

1. **Aturan bukti bergeser saat ditumpuk di satu form.** Mitigasi: tabel §4.2/§4.3 memetakan aturan lama persis; test tiap mode memverifikasi foto wajib/tidak.
2. **Resolver ambigu / bocor lintas cabang.** Mitigasi: POP scope + daftar pilihan untuk hasil ganda + test lintas-POP.
3. **Dua pintu ke aksi yang sama** (`serial.create` dan kartu 2). Diterima: entri mengarah ke endpoint simpan yang sama sehingga tidak ada logika ganda.
4. **Salah catat** menggantikan delta bebas dengan arah eksplisit; alasan dan catatan tetap wajib agar audit tidak melemah.

## 7. Test

Pola nama gejala, PHPUnit, tanpa full suite. Tambahan:
- `WarehouseAdjustmentHubTest`: pintu hanya untuk `warehouse_adjustment.create`; dua kartu tampil; link lama redirect dengan query.
- `WarehouseAdjustmentResolveScopeTest`: SN/barang gudang di luar POP scope tidak terselesaikan; hasil ganda mengembalikan daftar.
- `WarehouseCheckFormModeTest`: mode qty/SN/roll merender URL simpan yang benar dari server; foto wajib hanya SN/roll.
- `WarehouseProblemFormEventTest`: pemetaan kejadian → status SN / delta qty; opsi tak berlaku tidak ada; delta nol ditolak.
- Test lama (`WarehouseStockOpnameTest`, `WarehouseUnitOpnameTest`, `InventoryAdjustment*`) dijalankan ulang sebagai regresi POST; harus lulus tanpa diubah.

## 8. Dokumentasi & build

- Perbarui `docs/TASKS.md` dan dokumen modul gudang yang menyebut nama halaman lama.
- Karena Blade/JS berubah: `npm run build` (atau `docker compose run --rm assets`), `vendor/bin/pint`.
