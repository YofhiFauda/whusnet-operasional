# Master ODP (Node Jaringan ber-port)

ODP sekarang = salah satu **tipe** di pohon generik `network_nodes` (tipe `odp` di `network_node_types`, di-seed `NetworkNodeTypeSeeder`). Awalnya dibangun 2026-10-12 sebagai model flat `Odp`/`OdpPort` (tabel `odps`/`odp_ports`) untuk menjawab temuan audit Dashboard FOP ("Red Zone Watchlist ODP belum bisa dibangun — model `Distribution` gak punya kolom kapasitas port", lihat `docs/TASKS.md` ADHOC-156/162 dan §5 [docs/plan/fop/rancangan-fop-unified-dashboard.md](../../plan/fop/rancangan-fop-unified-dashboard.md)). Tabel lama itu di-reshape satu arah ke `network_nodes`/`network_node_ports` oleh migration `2026_10_12_110300_reshape_odps_into_network_nodes` — model `Odp`/`OdpPort` dan `OdpController` sudah dihapus.

**Terpisah total dari `Distribution`** — jangan dicampur:

| | `Distribution` | `NetworkNode` (tipe ber-port, mis. `odp`) |
|---|---|---|
| Fungsi | Label kode pembentuk CID pelanggan | Entitas kapasitas port fisik |
| Kolom | `pop_id`, `code`, `name`, `description` | `parent_id`/`anchor_pop_id`, `node_type_id`, `code`, `name` + relasi `ports` (child) |
| Kapasitas port | Tidak ada — murni master data referensi | Dari `COUNT(network_node_ports)`, status terpakai/kosong per port |
| Terikat ke | `Customer` (lewat CID) | Tidak berelasi ke `Distribution` maupun pelanggan |

## Model

- **`NetworkNode`** (`app/Models/NetworkNode.php`) — `code` (unique global), `name`, `node_type_id`. Tidak punya kolom `pop_id`: node puncak ditambatkan ke POP lewat `anchor_pop_id`, node anak lewat `parent_id` (XOR, ditegakkan `NetworkNodeService`); cabang di-resolve dari pohon (`resolvePop()`), listing discope lewat `scopeInPops()`. ODP hasil reshape = node puncak dengan `anchor_pop_id = odps.pop_id` lama.
- **`NetworkNodePort`** (`app/Models/NetworkNodePort.php`) — `network_node_id`, `number`, `is_used` (boolean). Unique `(network_node_id, number)`. Status terpakai/kosong ditandai **manual** oleh petugas berizin — tidak ada pemetaan otomatis ke pelanggan/task pada implementasi ini.

## Kapasitas & Red Zone

Kapasitas = `COUNT(network_node_ports)`. Menambah kapasitas = menambah baris port baru (nomor lanjut dari port terakhir), **tidak menghapus status port lama**; batas total dari `network_node_types.max_ports` (null = tanpa batas). Red Zone = port terpakai ≥ `network_node_types.red_zone_threshold` persen (per tipe; null = tidak pernah Red Zone), hanya untuk tipe `has_ports` + `monitors_capacity` — dihitung di `FopUnifiedDashboardService::getOdpSummary()`, discope POP (`hasAllPopAccess()`/`getAllowedPopIds()`), ditampilkan di Dashboard FOP (`fop.partials._distribution_glance`).

## Permission

`network_nodes.view`/`.create`/`.update` (feature `network_nodes`, root — lihat `config/rbac.php`; dulu `network_odps`, di-rename di baris yang sama oleh migration reshape supaya grant role tidak hilang). Dipegang `owner`, `atasan`, dan `fop` (lihat `database/seeders/RolePermissionSeeder.php`). Tidak ada `.delete` — menghapus node/port bukan alur normal (`network_node_ports.network_node_id` → `cascadeOnDelete`, tapi tidak ada UI delete).

## Route & Controller

```
GET  /network/nodes                    network_nodes.view    → NetworkNodeController@index
POST /network/nodes                    network_nodes.create  → NetworkNodeController@store
POST /network/nodes/{node}/ports       network_nodes.update  → NetworkNodeController@addPorts
PUT  /network/node-ports/{port}        network_nodes.update  → NetworkNodeController@updatePort
```

`NetworkNodeController::store()` memvalidasi `anchor_pop_id` terhadap scope POP actor (`Rule::in($allowedPopIds)` kalau bukan `hasAllPopAccess()`); `addPorts()`/`updatePort()` lewat `authorizeNodeScope()` (`inPops()` yang sama dengan listing) — jangan hapus, mencegah user scoped menambah/ubah port node milik POP lain lewat tebak ID.

## Related

- [docs/plan/fop/rancangan-fop-unified-dashboard.md](../../plan/fop/rancangan-fop-unified-dashboard.md) §3 Modul 6, §5 — konteks kenapa fitur ini dibangun & status audit.
- [docs/master/distribution/README.md](../distribution/README.md) — entitas yang SERING disalahartikan sama ODP, baca pembeda di atas sebelum bikin perubahan.

**Last updated:** 2026-10-13
