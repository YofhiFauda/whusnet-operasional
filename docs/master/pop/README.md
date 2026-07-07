# Master POP (Point of Presence / Cabang)

`Pop` = struktur hierarki 3 level (Pusat → Cabang → Mini POP) yang jadi tulang punggung 2 hal: **scope RBAC** (siapa boleh lihat data POP mana, lihat [docs/rbac](../../rbac/README.md)) dan **penomoran identitas pelanggan** (REQ ID → CID, lihat [docs/master/distribution](../distribution/README.md) untuk peran Distribusi di dalamnya).

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | Hierarki 3 level, aturan kode unik, generate REQ ID & CID, resolve display ID per status, **halaman mana pakai Cabang vs Mini POP** |
| [flowchart.md](flowchart.md) | Alur create/edit POP (cegah circular parent), alur generate registration number, alur generate CID |
| [user-flow.md](user-flow.md) | Langkah Owner/Admin kelola POP |
| [database-schema.md](database-schema.md) | Tabel `pops`, `pop_sequences` |
| [archive/](archive/) | Spesifikasi awal (`spesifikasi-pop-distribusi-cid.md`) + analisa historis + dokumen lama yang field-nya gak sesuai kode aktual |

## Konsep Inti

```
Pop (self-referencing parent_id, 3 level)
├── type=pusat    (level tertinggi, biasanya 1 row, representasi ISP)
│   └── type=cabang    (Cabang POP, punya cid_prefix & registration_prefix sendiri)
│       └── type=mini_pop  (turunan cabang, pop_code = cid_prefix + segmen mini)
│
├── sequences (PopSequence: registration & cid, per-POP counter anti-race-condition)
└── distributions (lihat docs/master/distribution)
```

- **`code`** — kode internal unik (bebas format), dipakai identifikasi umum.
- **`pop_code`** — kode terstruktur (`[A-Z0-9]+(-[A-Z0-9]+)*`), dipakai resolve segmen "Mini POP" di CID (lihat [business-logic.md](business-logic.md)).
- **`registration_prefix`** — prefix REQ ID pelanggan baru, e.g. `RQ`.
- **`cid_prefix`** — huruf kode Cabang POP di CID final, e.g. `D` untuk Jetis.

## Aktor & Permission

| Aksi | Permission |
|------|-----------|
| Lihat daftar/detail POP | `pops.view` |
| Tambah, edit, toggle status POP | `pops.create\|pops.update` (OR — form create & edit share middleware yang sama) |

## File Kode Terkait

| Area | File |
|------|------|
| Model | `app/Models/Pop.php`, `PopSequence.php` |
| Controller | `app/Http/Controllers/Master/PopController.php` |
| View | `resources/views/master/pop/{index,create,edit,show}.blade.php` |
| Dipakai scope RBAC | `app/Services/EffectiveAccessService.php::resolvePopTree()` (lihat [docs/rbac/flowchart.md §4](../../rbac/flowchart.md#4-resolve-scope-pop-getallowedpopids)) |
| Dipakai generate ID pelanggan | `CustomerController::store()` (`generateRegistrationNumber()`), `CustomerVerificationController::finalVerify()` (`generateComplexCid()`) — lihat [docs/customer-lifecycle](../../customer-lifecycle/README.md) |

## Routes

| Route | Method | Permission | Controller |
|-------|--------|------------|------------|
| `/master/pop` | GET | `pops.view` | `PopController@index` |
| `/master/pop/create`, `POST /master/pop` | GET/POST | `pops.create\|pops.update` | `PopController@create,store` |
| `/master/pop/{pop}` | GET | `pops.view` | `PopController@show` |
| `/master/pop/{pop}/edit`, `PUT /master/pop/{pop}` | GET/PUT | `pops.create\|pops.update` | `PopController@edit,update` |
| `POST /master/pop/{pop}/toggle` | POST | `pops.create\|pops.update` | `PopController@toggleStatus` |

---

**Last updated:** 2026-07-07
