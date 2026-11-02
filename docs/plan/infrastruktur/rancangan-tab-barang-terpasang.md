# Rancangan: Tab "Barang Terpasang" di Peta Infrastruktur

Tanggal: 29 Oktober 2026
Status: **selesai diimplementasikan** (29 Oktober 2026).

## Ringkasan eksekusi

Diimplementasikan sesuai rencana di bawah tanpa penyimpangan. Satu catatan UX yang ketahuan saat menulis test: cascading select ini **sekuensial murni** — melompat langsung ke level dalam (misal ODP) lewat URL tanpa mengisi level antara (Slot/PON) akan di-anggap tidak valid dan root jatuh ke level terakhir yang valid. Di UI nyata ini tidak masalah karena tiap submit select membawa serta nilai select lain yang sudah terisi di form yang sama (pengguna jalan drill-down satu level per klik, bukan lompat). Test: `InfrastructureMapTest::tab_barang_terpasang_menyaring_barang_sesuai_titik_subtree_terpilih` + `tab_barang_terpasang_mengabaikan_pop_di_luar_scope_pengguna` (29 test file ini lulus, 256 assertion; seluruh `tests/Feature/Network` 237 lulus). Pint lulus.

## Masalah

Tab Inventaris yang sudah ada di Peta Infrastruktur (`network.map.index`) adalah **daftar device** — satu baris per `NetworkNode` (OLT/Slot/PON/ODP/dst) dengan kapasitas port + daftar aset yang nempel di device itu SENDIRI (tidak termasuk device anaknya). Itu cocok buat "device ini kapasitasnya berapa", tapi TIDAK menjawab kebutuhan: "barang apa saja yang terpasang di bawah naungan POP Jetis" atau "barang apa saja yang terpasang di bawah naungan ODP A" — pertanyaan yang scope-nya SUBTREE (satu titik + semua turunannya), bukan satu device.

Kebutuhan konkret (dari user):
1. Pilih **POP Jetis** → tampil semua barang gudang yang terpasang di device manapun di bawah POP Jetis (termasuk Mini POP, OLT, Slot, PON, ODP di bawahnya), tiap baris nunjukin barang + device tempat dia nempel.
2. Pilih **ODP A** → tampil barang yang terpasang di ODP A (dan turunannya kalau ada), sama formatnya.

Jadi: histori/catatan "barang dari gudang yang terpasang di INFRA", di-scope ke satu titik pilihan user di hierarki jaringan (POP → Mini POP → OLT → Slot → PON → ODP), bukan per-device.

## Keputusan

Tambah **tab ke-4** di halaman Peta Infrastruktur yang sudah ada: **Topologi, Pohon, Inventaris, Barang Terpasang**. Bukan halaman baru, bukan ganti tab Inventaris yang sudah ada (device-centric tetap dipakai untuk urusan kapasitas port) — ini mode tampilan berbeda atas data yang sama.

### Kenapa tab baru, bukan modifikasi tab Inventaris

Dua kebutuhan ini punya bentuk data beda: tab Inventaris = 1 baris per **device**; kebutuhan ini = 1 baris per **barang**, discope ke subtree. Mencampur keduanya di satu tabel bikin dua konsep "baris" yang beda arti tercampur. Entry point terpisah lebih jelas buat user, dan tab Inventaris yang sudah ada + test-nya tidak perlu diubah sama sekali.

### Filter: pilih satu titik di hierarki

6 `<select>` cascading di toolbar tab ini: **POP → Mini POP → OLT → Slot → PON → ODP**. Semuanya opsional kecuali urutan — pilih POP dulu baru Mini POP-nya kebuka, dst. Tiap `<select>` pakai `onchange="this.form.submit()"` (pola yang sama persis dengan dropdown `pop_id`/`sort` yang sudah ada di halaman ini — bukan Alpine/AJAX baru). Titik filter = level **paling spesifik** yang terisi (kalau isi sampai PON tapi gak pilih ODP, root = PON itu, otomatis mencakup semua ODP anaknya).

Param baru, **terpisah** dari `pop_id` yang dipakai tab Topologi/Pohon/Inventaris (supaya tidak mengubah perilaku tab lain sama sekali):
`asset_pop_id`, `asset_mini_id`, `asset_olt_id`, `asset_slot_id`, `asset_pon_id`, `asset_odp_id` — semua `nullable integer`.

Opsi tiap `<select>` dihitung di controller dari data yang **sudah di-scope POP user** (sama seperti `$cabangs`/`$nodes` yang sudah dibangun `InfrastructureMapService::build()`) — tidak ada query baru ke luar scope:
- POP: daftar Cabang (`$cabangs`, sudah ada).
- Mini POP: anak dari Cabang terpilih (`Pop::forUser()->where('type','mini_pop')->where('parent_id', ...)`).
- OLT/Slot/PON/ODP: node di `$nodes` yang tipe-nya cocok (`nodeType->code` masuk `['olt_chassis']`/`['olt_slot']`/`['olt_pon']`/`['odp']`) dan leluhurnya (`anchor_pop_id`/`parent_id`, ditelusuri lewat `$nodes` yang sudah di memori — tidak query lagi) = level di atasnya yang terpilih.

### Subtree & daftar barang: tanpa query baru

`InfrastructureMapService::build()` SUDAH menghitung semua yang dibutuhkan untuk seluruh scope POP user:
- `$nodes` — semua `NetworkNode` dalam scope, dengan `parent_id`/`anchor_pop_id`.
- `$assetsByNode[$node->id]` — barang (SN/ROLL/QTY) yang nempel LANGSUNG di node itu, sudah termasuk nama barang, referensi SN/lot, jumlah, tanggal pasang, teknisi, task, link riwayat gudang.
- `$pathsByNode[$node->id]` — path lengkap device itu (dipakai sebagai kolom "Posisi").

Tambah 2 method murni (tanpa DB) di `InfrastructureMapService`:

```php
/** Node id subtree (termasuk root-nya) ditelusuri dari parent_id, dalam memori. */
private function subtreeNodeIds(Collection $nodes, array $rootNodeIds): array;

/** Barang di seluruh subtree node id di atas, satu baris per barang + posisi. */
public function assetRowsForNodes(Collection $nodes, array $assetsByNode, array $pathsByNode, array $nodeIds): array;
```

Root untuk tiap level filter:
- **ODP/PON/Slot/OLT terpilih** → root = `[node->id]` itu sendiri → `subtreeNodeIds()`.
- **Mini POP terpilih** (tanpa device di bawahnya dipilih) → root = semua node di `$nodes` dengan `anchor_pop_id === $miniPopId` → `subtreeNodeIds()` dari situ.
- **POP (Cabang) terpilih** → root = node ber-`anchor_pop_id` Cabang itu **UNION** node ber-`anchor_pop_id` tiap Mini POP anak Cabang itu (konsisten: pilih Cabang = lihat semua barang di cabang itu, termasuk semua Mini POP-nya).

Hasil akhir: `array<{item_name, item_code, kind: SN|ROLL|QTY, reference, amount, installed_at, technician, task, trace_url, path}>` — dikirim ke view sebagai `$assetRows`.

Kalau belum ada filter terisi sama sekali → `$assetRows = []`, view tampilkan empty state "Pilih POP/Mini POP/OLT/Slot/PON/ODP dulu untuk melihat barang yang terpasang."

### Tampilan tabel

Kolom: **Barang** (nama + kode item) · **Jenis** (SN/ROLL/QTY) · **Referensi** (SN atau lot) · **Jumlah** · **Posisi** (path lengkap) · **Tanggal Pasang** · **Teknisi** · **Task** · (link "Riwayat Gudang" kalau `warehouse_traceability.view`, sama seperti tab Inventaris sekarang).

### Yang tidak berubah

- Tab Topologi/Pohon/Inventaris, cache-nya (`viewCacheKey`, `lazyCacheKey`), dan semua test yang sudah ada — tidak disentuh.
- Tidak ada migration, tidak ada tabel baru. Semua dari data yang sudah dihitung `build()`.
- Scope POP/permission tetap mengikuti `$popIds` dari `EffectiveAccessService` yang sudah dipakai `build()` — tab baru ini otomatis ikut ter-scope karena dibangun dari `$nodes` yang sama.

## Rencana eksekusi

1. `InfrastructureMapService`: tambah `subtreeNodeIds()` + `assetRowsForNodes()` (murni, di memori).
2. `InfrastructureMapController::index()`: terima 6 param baru, hitung opsi tiap `<select>`, hitung `$assetRows` kalau ada root, kirim ke view.
3. `resources/views/network/map/index.blade.php`: tab ke-4 "Barang Terpasang" — toolbar 6 select cascading + tabel hasil + empty state. Render tab ini juga hanya saat aktif (pola `@if($activeTab === ...)` yang sudah ada untuk Pohon/Inventaris).
4. Test baru di `tests/Feature/Network/InfrastructureMapTest.php`: filter POP → semua barang di bawahnya (termasuk lewat Mini POP); filter Mini POP spesifik → cuma barang di situ; filter ODP spesifik → cuma barang di ODP itu; opsi dropdown ke-cascade benar (Mini POP options cuma anak POP terpilih, dst); scope POP user tetap ditegakkan (tidak bisa pilih POP di luar scope).
5. `vendor/bin/pint` sebelum selesai.

## Di luar rancangan

Tidak mengubah ledger/sumber data gudang. Tidak menambah export CSV untuk tab ini (bisa menyusul kalau diminta). Tidak mengubah tab Inventaris yang sudah ada.
