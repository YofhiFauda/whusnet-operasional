# Audit Transisi Hierarki Infrastruktur (Task 1, read-only)

Tanggal: 21 Oktober 2026
Rencana: `docs/superpowers/plans/2026-10-21-hierarki-infrastruktur-server-pop-mini-pop.md` Task 1
Rancangan: `docs/plan/infrastruktur/rancangan-hierarki-infrastruktur-server-pop-mini-pop.md` (§7, §14, §16.4)

## 1. Lingkungan dan cara pengambilan

- Waktu ambil: 2026-10-21 09:49:25 (Asia/Jakarta).
- Lingkungan: `APP_ENV=local`, koneksi `mysql`, database `whusnet_operasional` (container `whusnet-db`).
- Cara: skrip **read-only** dijalankan lewat `docker compose exec -T app php artisan tinker`
  (hanya `SELECT`; tidak ada `insert/update/delete`). Query yang dipakai:

```php
DB::table('pops')->select('id','code','name','parent_id','uplink_node_id')->get();
DB::table('network_nodes as n')->join('network_node_types as t','t.id','=','n.node_type_id')
  ->select('n.id','n.code','n.name','t.code as type','n.parent_id','n.anchor_pop_id','n.capacity','n.status')->get();
DB::table('olt_slots')->get();
DB::table('network_node_ports')->select('network_node_id', DB::raw('count(*) c'), DB::raw('sum(is_used) used'))->groupBy('network_node_id')->get();
DB::table('network_port_assignments as a')->join('network_node_ports as p','p.id','=','a.network_node_port_id')
  ->whereNull('a.released_at')->select('a.id','p.network_node_id','p.number','a.customer_id','a.target_node_id','a.target_port_id','a.manual_label')->get();
DB::table('inventory_serials')->where('status','deployed')->get();
DB::table('inventory_serials')->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c','status');
DB::table('infrastructure_balances')->get();
DB::table('inventory_rolls')->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c','status');
DB::table('inventory_transactions')->where(fn($q)=>$q->whereNotNull('to_network_node_id')->orWhereNotNull('from_network_node_id'))->get();
DB::table('task_infrastructure_actions')->get();
DB::table('audit_logs')->whereIn('module',['Node Jaringan','Slot OLT'])->count();
DB::table('fop_tasks')->whereIn('category',['O-REQ','INFR REQ'])->get();
```

## 2. Baseline per POP

| POP | Tipe | Node di bawahnya | Slot | Port | Assignment aktif | Aset DEPLOYED | Saldo infra |
|---|---|---|---|---|---|---|---|
| WHUSNET (1) | pusat | `server` CORE-WHUSNET-PUSAT (21) + 2 `backbone` (22, 28) | 0 | 0 | 0 | 0 | 0 |
| Jetis (4) | cabang | `aggregation_switch` AGG-JETIS-01 (23) langsung di Cabang | 0 | 0 | 0 | 0 | 0 |
| C1 (5) | mini_pop | `olt_chassis` OLT-JETIS-01 (24) → `pon_port` PON-JETIS-01 (25) → `feeder` FDR-JETIS-01 (26, 20 port) & TEST-FEEDER-2 (38, 24 port) → `odp` ODP-JETIS-01 (27, 30 port) & ODP-JETIS-02 (37, 24 port) | 64 (chassis 24, semua tanpa kartu) | 98 | 7 (5 pelanggan di ODP 27, 2 sambungan feeder 26 → ODP 27/37) | 0 | 0 |
| Sandya (2) | cabang | `aggregation_switch` AGG-SANDYA-01 (29) langsung di Cabang | 0 | 0 | 0 | 0 | 0 |
| D1 (3) | mini_pop | `olt_chassis` OLT-SANDYA-01 (30) → `pon_port` PON-SANDYA-01 (31) → `feeder` FDR-SANDYA-01 (32, 8 port) → `odp` ODP-SANDYA-01 (33, 8 port) | 8 (chassis 30, tanpa kartu) | 16 | 0 | 0 | 0 |

Ringkas global:

- Node: 15 (`server` 1, `backbone` 2, `aggregation_switch` 2, `olt_chassis` 2, `pon_port` 2, `feeder` 3, `odp` 3). Tidak ada `olt_card`, `odc`, `drop_cable`, `ont`.
- `olt_slots`: 72 baris, **semua `card_node_id` null**.
- `network_node_ports`: 114 port; assignment total 8 (7 aktif, 1 sudah dilepas).
- Serial: `available` 371, `issued` 36, `installed` 21, **`deployed` 0**.
- `infrastructure_balances`: **0 baris**. Roll: `available` 116, `issued` 1, `in_use` 1, `scrapped` 2.
- Transaksi ledger: 802 total, **0 yang menyentuh `from/to_network_node_id`**.
- `task_infrastructure_actions`: 0. Audit node/slot: 108 baris.
- Pelanggan: 1.978; 1.691 memiliki teks `customer_technical_details.odp_number` (legacy, belum terhubung ID).
- Task FOP O-REQ/INFR-REQ: 2 (TFOP-20261016-000003/-000004, Cabang Jetis, status `lapor_nanti`, belum ada aksi infrastruktur).

## 3. Orphan, duplikat, dan kasus ambigu

| ID asal | POP | Temuan | Alasan | Keputusan pemetaan |
|---|---|---|---|---|
| node 23 AGG-JETIS-01 | Jetis (Cabang) | Switch langsung di POP Cabang (`aggregation_switch`) | §2/§16.2: Switch operasional hanya di Mini POP; Mini POP tujuan tidak dapat dibuktikan dari data | **Menunggu operator** — tidak dipindah otomatis, tidak dibuat Mini POP fiktif. Tetap terbaca sebagai tipe lama. |
| node 29 AGG-SANDYA-01 | Sandya (Cabang) | Sama seperti di atas | Sama | **Menunggu operator** |
| node 25 PON-JETIS-01 | C1 | `pon_port` sebagai node, anak langsung chassis 24 (tanpa kartu/slot) | Nomor slot dan nomor PON tidak tercatat; tidak ada FK ke `olt_slots` | **Ambigu → daftar manual** (`infrastructure_legacy_mappings` status `ambiguous`). Tidak dibuatkan PON baru otomatis. |
| node 31 PON-SANDYA-01 | D1 | Sama | Sama | **Ambigu → daftar manual** |
| node 26/38 feeder; node 27/37/33 ODP | C1/D1 | ODP berada di bawah feeder → PON node lama, bukan koneksi PON terverifikasi | Uplink PON tidak bisa dibuktikan dengan FK posisi baru | ODP **tidak** disambungkan otomatis ke PON baru; ditampilkan "uplink belum terverifikasi" sampai operator memetakan. |
| node 27 ODP-JETIS-01 & 37 ODP-JETIS-02 | C1 | Nama sama ("ODP contoh Jetis 01"), kode beda | Duplikat label, ID berbeda — bukan konflik FK | Dibiarkan; kode/ID yang jadi identitas. |
| node 38 TEST-FEEDER-2 | C1 | Node uji manual di tengah jalur | Data uji | Menunggu operator (lihat §4). |
| olt_slots 1–72 | C1/D1 | Slot kosong (tanpa kartu) | FK `chassis_node_id` + nomor unik membuktikan posisi | **Dipetakan otomatis** ke `infrastructure_slots` (kind `olt`) — hubungan terbukti FK + nomor unik. |

Tidak ditemukan slot/kartu ganda, port yatim, atau serial DEPLOYED tanpa node.

## 4. Klasifikasi dummy vs nyata

- Seluruh node berasal dari `WhusnetTopologySeeder` (kode `CORE-*`, `BB-*`, `AGG-*`, `OLT-*`, `PON-*`, `FDR-*`, `ODP-*`, nama "contoh") plus satu node uji manual (`TEST-FEEDER-2`). Tidak ada ledger, serial DEPLOYED, atau saldo infrastruktur yang merujuk node mana pun.
- **Namun ODP-JETIS-01 (node 27) memegang 5 assignment pelanggan aktif** (customer_id 44, 1967, 1969, 1973, 1978) dan feeder 26 memegang 2 sambungan aktif ke ODP 27/37. Assignment pelanggan adalah relasi pelanggan, bukan data topologi murni.
- **Keputusan: reset topologi DITOLAK.** Syarat §16.4 "cakupan reset tidak mencakup pelanggan" tidak terpenuhi. Data diperlakukan sebagai **nyata**: migrasi aditif, legacy tetap terbaca, kasus ambigu menunggu keputusan operator, dan tidak ada baris lama yang dihapus.

Baseline rekonsiliasi (dipakai Task 10/11):

| POP | ODP | Pelanggan terhubung (assignment aktif) | Port aktif (terpakai) | Aset DEPLOYED |
|---|---|---|---|---|
| Jetis + C1 | 2 | 5 | 7 | 0 |
| Sandya + D1 | 1 | 0 | 0 | 0 |
| WHUSNET | 0 | 0 | 0 | 0 |

## 5. Temuan untuk dilaporkan

1. **Topologi dummy terhubung pelanggan nyata** — reset dummy seperti yang diizinkan §16.4 tidak aman di lingkungan ini (lihat §4).
2. **Dua Task FOP O-REQ/INFR-REQ berstatus `lapor_nanti`** tanpa akar scope terstruktur. Saat aturan "akar scope minimum" berlaku, task lama ini memakai fallback akar = POP Cabang task (`fop_tasks.pop_id`) supaya laporan tetap bisa dikirim; snapshot scope dicatat saat laporan disimpan.
3. **1.691 pelanggan hanya punya teks `odp_number`** — tidak boleh di-join ke ODP berdasarkan teks (§4.3). Tidak dibackfill.
4. `pops.uplink_node_id` menunjuk node `backbone`/`aggregation_switch` lama — tetap dibaca untuk garis uplink Peta; tidak diubah.

## 6. Status review

Hasil read-only ini menjadi masukan Task 2–3: hanya `olt_slots` yang dibackfill otomatis; PON node, Switch di Cabang, dan uplink ODP lama masuk daftar manual.


## 7. Task 11 - audit cutover sementara (21 Oktober 2026)

- Migration aditif 000007 diterapkan pada database aplikasi. Rekonsiliasi terakhir untuk 2026-10 mencatat selisih nol: WHUSNET 0/0/0/0, Jetis 2 ODP/5 pelanggan/7 port aktif/0 aset, Sandya 1/0/0/0. Eksekusi pertama yang menghitung hanya 5 port ODP di Jetis tidak dipakai sebagai bukti karena 2 port feeder aktif terlewat; tes regresi dan perhitungan diperbaiki sebelum eksekusi kedua.
- Tujuh bukti cutover bagian 16.4: (1) guard tulis legacy tersedia, saklar tetap terbuka untuk data nyata; (2) serial/kabel diuji pada Task 6-8; (3) proyeksi Master Node/Peta diuji pada Task 10; (4) dropdown bertingkat diuji, form legacy tetap terbuka; (5) seeder baru tidak membuat node legacy, seeder contoh lama dinonaktifkan; (6) snapshot saat ini nol, tetapi belum satu siklus tutup periode penuh; (7) histori legacy tetap dibaca, bukti cutover akhir belum ada. Cutover belum selesai.
- Tujuh mapping menunggu keputusan operator: PON node #25/#31 (Slot dan nomor PON), switch #23/#29 (Mini POP fisik), uplink ODP #27/#33/#37 (PON tujuan). Tidak dipetakan otomatis. Lihat perintah infrastructure:legacy-mappings.
- Reset dummy tetap ditolak untuk node yang memiliki assignment pelanggan, audit node, ledger/aset, aksi atau ujung koneksi laporan, maupun relasi lain. Topologi aplikasi mengandung assignment nyata; reset tidak dijalankan.
- Migration 000005 sudah berstatus Ran pada database aplikasi. Perubahan nama FK di worktree adalah perbaikan untuk bootstrap skema baru/rollback; migration tidak dijalankan ulang pada database yang sudah ada.
