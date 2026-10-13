# Analisa: Inventaris Aset Gudang Terpasang sebagai Infrastruktur

Status: **diimplementasi sebagai ADHOC-167** (`/network/inventaris`). Dibuat 2026-10-13.
Dasar: `docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md` (v3) dan
prototipe `docs/plan/infrastruktur/peta-infrastruktur-isp.html` (mock, belum terhubung DB).

## 1. Kebutuhan

Bukti bahwa barang gudang sudah terpasang di infrastruktur, sama seperti bukti modem
terpasang di pelanggan. Tidak perlu menggambar topologi. Cukup daftar yang bisa dicari,
difilter, dan diekspor.

## 2. Yang sudah ada di sistem

| Kebutuhan | Sumber data |
|---|---|
| SN terpasang | `inventory_serials`: `status = DEPLOYED`, `current_network_node_id` |
| Qty kabel/aksesoris | `infrastructure_balances` (`item_id`, `lot_no`, `network_node_id`, `qty`) |
| Lokasi (breadcrumb) | `NetworkNode::breadcrumbLabel()` |
| Cabang untuk scope | `NetworkNode::resolvePop()`, `NetworkNode::scopeInPops()` |
| Kapasitas & port | `network_node_ports`, `capacity` |
| Bukti modem pelanggan (analog) | `inventory_serials.customer_id`, `installed_at` |
| Halaman node + aset | `/network/nodes` (blok "Aset Terpasang" per node) |

Belum ada: daftar datar lintas node, kolom tanggal pasang dan sumber task di listing,
ekspor, dan bukti foto/BAP saat deploy (belum diverifikasi di `InventoryService`).

## 3. Sumber tanggal pasang dan task

Tanggal pasang, teknisi, dan task asal tidak ada di `inventory_serials`. Ambil dari
ledger `inventory_transactions` tipe `DEPLOY` (`to_network_node_id`, `to_pop_id`,
`created_at`, referensi task). Listing perlu join ke ledger, dengan entri `DEPLOY`
terakhir per SN/lot yang masih `DEPLOYED` atau qty > 0.

## 4. Prototipe `peta-infrastruktur-isp.html`: temuan

Catatan: file 448 KB, sebagian besar bundle JS minified. Yang dibaca: token warna
(CSS variable), struktur tab, dan komponen tabel. Belum dilihat sebagai screenshot.

### 4.1 Struktur halaman
- Header: judul "Peta Infrastruktur ISP", subjudul "Kapasitas, slot kosong, dan topologi
  dari pusat sampai ONT pelanggan", kolom pencarian (lebar 240 px), tombol Tampilan.
- Tab `role="tablist"` berlabel "Tampilan": **Topologi** dan **Inventaris**. Untuk
  fitur ini cukup tab Inventaris.
- Strip ringkasan di atas konten: "Pelanggan (ONT)", "Port PON terpakai",
  "Slot OLT kosong" (angka mono).
- Sidebar kiri: chip filter tipe perangkat (`role="group"`, `aria-label="Filter tipe perangkat"`,
  `aria-pressed` per chip).
- Area kanan: panel detail per perangkat (kartu `iv` berjudul "Kapasitas", "Peta port",
  "Pelanggan", "Slot dan port PON"). Dipakai juga untuk detail aset.
- Legenda warna: `aria-label="Legenda warna"`, bulat kecil per tipe.

### 4.2 Tabel Inventaris
- Kolom: **Perangkat, Lokasi, Terpakai / kapasitas, Kosong, Utilisasi, Status**.
- Lebar minimum tabel 760 px, di dalam wrapper `overflow-x-auto`, kartu `bg-card`
  dengan border.
- Header: `bg-muted/50`, teks `text-muted-foreground`, label `tag` (uppercase kecil).
- Sort (`inv-sort`, lebar 190 px): **Urutan topologi** (default), **Utilisasi tertinggi**,
  **Kosong terbanyak**. Untuk kita: ganti "Urutan topologi" dengan "Lokasi (A-Z)" atau
  "Tanggal pasang terbaru".
- Pencarian: nama, model, serial, paket, alamat, catatan, tipe.
- Empty state: kalimat ringkas di kartu `max-w-sm`, `text-muted-foreground`.

### 4.3 Status dan warna
- Status: `aktif` Aktif, `rencana` Rencana, `gangguan` Gangguan, `nonaktif` Nonaktif.
  Untuk aset gudang: `DEPLOYED` (sukses) dan qty 0 atau dicabut (nonaktif).
- Token warna (light/dark, `:root` dan `[data-theme=dark]`):
  - Umum: `--background` 210 22% 95% (dark 214 30% 8%), `--primary` 214 55% 22%
    (dark 197 80% 56%), `--border` 212 18% 82% (dark 214 20% 21%), `--signal` 197 90% 36%.
  - Status: `--ok` 152 55% 30%, `--warn` 34 92% 38%, `--crit`/`--destructive` 2 72% 46%.
  - Tipe perangkat (`--c-*`): dc, core, service, transport, pop, agg, olt, pon, feeder,
    odp, drop, ont (`--c-ont` hanya di dark, 332 70% 70%).
- Font: IBM Plex Sans (teks), IBM Plex Sans Condensed (judul), IBM Plex Mono (angka,
  serial). Dimuat dari Google Fonts.
- Radius `--radius: .375rem`.

### 4.4 Yang tidak dipakai dari prototipe
- Tab Topologi dan SVG diagram.
- Editor perangkat (form tambah ONT, pasang pelanggan, edit serial di panel). Di sistem
  kita, mutasi lewat halaman create tersendiri sesuai `CLAUDE.md`.
- Data mock (`Math.random`, generator ONT).

## 5. Pemetaan ke implementasi Laravel

- Blade + Alpine (bukan React). Ambil layout dan interaksi, bukan kodenya.
- Token warna jadi CSS variable di `resources/css/app.css` (Tailwind 4), sesuai pola yang
  sudah ada.
- Route baru: `/network/inventaris` (atau tab di `/network/nodes`), permission
  `network_nodes.view`, query lewat `scopeInPops()` dengan `EffectiveAccessService`.
- Filter tipe dan sort: query string, bukan state klien saja, supaya bisa dibagikan.
- Ekspor CSV/PDF untuk bukti: sesuai pola ekspor yang sudah ada di repo.
- Test wajib: scope POP, filter tipe, pencarian serial, qty > 0 untuk ROLL/QUANTITY, dan
  urutan sort.

## 6. Keputusan user (2026-10-13)

1. **Bukti = barang yang dipakai + tanggal pasang + teknisi + task.** Tidak wajib foto/BAP.
   Semua data ini ada di ledger `DEPLOY`, jadi alur O-REQ/INFR REQ tidak berubah.
2. **Prototipe adalah desain resmi.** Ikuti layout tab Inventaris.
3. **Cakupan:** lihat §7.

## 7. Cakupan: per cabang vs lintas cabang

- **Per cabang:** daftar hanya berisi node yang anchor-nya berada di satu cabang
  (atau Mini POP di bawah cabang itu).
- **Lintas cabang:** daftar berisi semua node dalam scope user. Owner dan atasan
  (`all_pop`) melihat semua cabang. Admin cabang melihat cabangnya saja.

Keduanya bukan dua halaman. Satu halaman dengan filter cabang. Default-nya cakupan
scope user, dan user dengan scope lebih dari satu cabang dapat dropdown cabang.

Aturan pembeda:
- **Posisi sekarang** memakai cabang saat ini (`resolvePop()` lewat anchor).
- **Riwayat** memakai snapshot `to_pop_id` di ledger. Barang yang dipasang di cabang X
  bulan lalu tetap tercatat di X walau node sudah dipindah ke Y.

**Keputusan user (2026-10-13): lintas cabang.** Satu halaman, cakupan = semua node dalam
scope user, dengan filter cabang sebagai opsi. Ekspor per cabang dilakukan lewat filter
cabang yang sama (bukan fitur terpisah).
