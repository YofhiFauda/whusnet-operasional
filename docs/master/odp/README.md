# Master ODP

`Odp` = entitas infrastruktur jaringan (kapasitas port), dibangun 2026-10-12 sebagai jawaban temuan audit Dashboard FOP ("Red Zone Watchlist ODP belum bisa dibangun — model `Distribution` gak punya kolom kapasitas port", lihat `docs/TASKS.md` ADHOC-156/162 dan §5 [docs/plan/fop/rancangan-fop-unified-dashboard.md](../../plan/fop/rancangan-fop-unified-dashboard.md)).

**Terpisah total dari `Distribution`** — jangan dicampur:

| | `Distribution` | `Odp` |
|---|---|---|
| Fungsi | Label kode pembentuk CID pelanggan | Entitas kapasitas port fisik |
| Kolom | `pop_id`, `code`, `name`, `description` | `pop_id`, `code`, `name` + relasi `ports` (child) |
| Kapasitas port | Tidak ada — murni master data referensi | Dari `COUNT(odp_ports)`, status terpakai/kosong per port |
| Terikat ke | `Customer` (lewat CID) | Tidak berelasi ke `Distribution` maupun pelanggan |

## Model

- **`Odp`** (`app/Models/Odp.php`) — `pop_id`, `code` (unique global), `name`. `HasPopScope` (scope POP standar) + audit log (`created`/`updated`).
- **`OdpPort`** (`app/Models/OdpPort.php`) — `odp_id`, `number`, `is_used` (boolean). Unique `(odp_id, number)`. Status terpakai/kosong ditandai **manual** oleh petugas berizin — tidak ada pemetaan otomatis ke pelanggan/task/ODC pada implementasi ini.

## Kapasitas & Red Zone

Kapasitas = `COUNT(odp_ports)`, bukan kolom `capacity` di `odps`. Menambah kapasitas = menambah baris `OdpPort` baru (nomor lanjut dari port terakhir), **tidak menghapus status port lama**. Red Zone = port terpakai ≥85% dari total port — dihitung di `FopUnifiedDashboardService::getOdpSummary()`, discope POP (`hasAllPopAccess()`/`getAllowedPopIds()`), ditampilkan di Dashboard FOP (`fop.partials._distribution_glance`) sebagai pengganti badge "Terkoneksi" yang dulu hardcoded.

## Permission

`network_odps.view`/`.create`/`.update` (feature `network_odps`, root — lihat `config/rbac.php`). Dipegang `owner`, `atasan`, dan `fop` (FOP yang input/update data port di lapangan, lihat `database/seeders/RolePermissionSeeder.php`). Tidak ada `.delete` — menghapus ODP/port bukan alur normal (`odp_ports.odp_id` → `cascadeOnDelete`, tapi tidak ada UI delete).

## Route & Controller

```
GET  /network/odps                     network_odps.view    → OdpController@index
POST /network/odps                     network_odps.create  → OdpController@store
POST /network/odps/{odp}/ports         network_odps.update  → OdpController@addPorts
PUT  /network/odp-ports/{port}         network_odps.update  → OdpController@updatePort
```

`OdpController::store()`/`addPorts()`/`updatePort()` semua validasi `pop_id` terhadap scope POP actor (`Rule::in($allowedPopIds)` kalau bukan `hasAllPopAccess()`), plus `authorizePop()` manual di `addPorts()`/`updatePort()` — jangan hapus validasi ganda ini, mencegah user scoped menambah/ubah port ODP milik POP lain lewat tebak ID.

## Related

- [docs/plan/fop/rancangan-fop-unified-dashboard.md](../../plan/fop/rancangan-fop-unified-dashboard.md) §3 Modul 6, §5 — konteks kenapa fitur ini dibangun & status audit.
- [docs/master/distribution/README.md](../distribution/README.md) — entitas yang SERING disalahartikan sama ODP, baca pembeda di atas sebelum bikin perubahan.

**Last updated:** 2026-10-12
