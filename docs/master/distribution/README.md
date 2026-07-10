# Master Distribusi

`Distribution` = titik distribusi jaringan (ODC/ODP/OLT dsb, direpresentasikan cukup dengan **kode** + **nama** + **deskripsi**) yang terikat ke 1 POP. Kode distribusi ini yang jadi segmen ke-3 di CID pelanggan (lihat [docs/master/pop/business-logic.md §4](../pop/business-logic.md#4-generate-cid-popgeneratecomplexcid)).

**Model jauh lebih sederhana dari yang dokumen lama sebutkan** — gak ada kolom `type`/`capacity`/`used_ports`/`status`. Distribusi di sistem ini murni master data kode referensi, bukan inventaris kapasitas port.

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | Aturan keunikan kode (global, bukan per-POP), keterkaitan dengan CID & Customer |
| [flowchart.md](flowchart.md) | Alur create/edit/delete Distribusi |
| [user-flow.md](user-flow.md) | Langkah Admin/NOC kelola Distribusi |
| [database-schema.md](database-schema.md) | Tabel `distributions` |
| [archive/](archive/) | Dokumen lama — field yang disebut (`type`, `capacity`, `used_ports`) gak pernah ada di kode aktual |

## Konsep Inti

```
Distribution
├── pop_id → belongsTo Pop (wajib, many-to-one)
├── code   → unik GLOBAL di seluruh sistem (bukan per-POP)
└── dipakai sebagai:
      - Distribution.code → segmen CID (Pop::generateComplexCid())
      - Customer.distribution_id → assign distribusi ke pelanggan saat aktivasi
```

## Aktor & Permission

**Distribusi gak punya permission fitur sendiri** — reuse permission `pops.*` (dianggap 1 modul dengan Master POP).

| Aksi | Permission |
|------|-----------|
| Lihat daftar Distribusi | `pops.view` |
| Tambah/Edit | `pops.create\|pops.update` |
| Hapus | `pops.delete` |

## File Kode Terkait

| Area | File |
|------|------|
| Model | `app/Models/Distribution.php` |
| Controller | `app/Http/Controllers/Master/DistributionController.php` |
| View | `resources/views/master/distribusi/{index,create,edit}.blade.php` |
| Dipakai CID | `app/Models/Pop.php::generateComplexCid()` |
| Dipakai form teknis | `CustomerInstallationController` (input `distribution_id`, `odp_number`, dll — lihat [docs/customer-lifecycle](../../customer-lifecycle/README.md)) |

## Routes

| Route | Method | Permission | Controller |
|-------|--------|------------|------------|
| `/master/distribusi` | GET | `pops.view` | `DistributionController@index` |
| `/master/distribusi/create`, `POST /master/distribusi` | GET/POST | `pops.create\|pops.update` | `DistributionController@create,store` |
| `/master/distribusi/{distribusi}/edit`, `PUT` | GET/PUT | `pops.create\|pops.update` | `DistributionController@edit,update` |
| `DELETE /master/distribusi/{distribusi}` | DELETE | `pops.delete` | `DistributionController@destroy` |

---

**Last updated:** 2026-07-07
