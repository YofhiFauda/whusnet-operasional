# Database Schema — Master POP

## Entity Relationship

```
pops ──self-ref (parent_id)──▶ pops (pusat → cabang → mini_pop)
pops ──hasMany──▶ pop_sequences (registration & cid counter, per-POP)
pops ──hasMany──▶ distributions (lihat docs/master/distribution)
pops ──hasMany──▶ customers, payments, invoices, tasks, fop_tasks (banyak modul FK ke pop_id)
pops ──belongsToMany──▶ users (via user_pops, legacy — RBAC modern pakai user_role_scope_targets)
```

## Tabel `pops`

Migrasi: `2026_06_11_000001_create`, `2026_06_11_104026_add_prefix_fields` (`registration_prefix`, `cid_prefix`, `pop_code`).

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `code` | string(50), unique | | Kode internal bebas format |
| `pop_code` | string | ✔* | Format `[A-Z0-9]+(-[A-Z0-9]+)*`, unique — dipakai resolve segmen Mini POP di CID |
| `registration_prefix` | string | ✔* | Prefix REQ ID, e.g. `RQ` |
| `cid_prefix` | string | ✔* | Huruf kode Cabang di CID final, e.g. `D` |
| `name` | string(150) | | |
| `type` | string(30) | | `pusat`/`cabang`/`mini_pop` |
| `parent_id` | FK → `pops.id`, null on delete | ✔ | Self-reference hierarki |
| `address` | text | ✔ | |
| `village`, `district`, `city` | string(100) | ✔ | |
| `latitude`, `longitude` | decimal(10,7) | ✔ | |
| `pic_name`, `pic_phone` | string | ✔ | |
| `status` | string(30), default `active` | | `active`/`inactive` |
| `created_at`/`updated_at` | timestamp | | |

*`registration_prefix`/`cid_prefix`/`pop_code` nullable di skema DB tapi **wajib diisi** di validasi form create/update (`PopController`) — gak nullable secara de-facto untuk POP yang dibuat lewat UI normal.

## Tabel `pop_sequences`

Migrasi: `2026_06_11_104034_create`. Counter anti-race-condition, terpisah per POP + per jenis sequence.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `pop_id` | FK → `pops.id`, cascade delete | |
| `sequence_type` | string(30) | `registration` atau `cid` (konstanta `PopSequence::TYPE_REGISTRATION`/`TYPE_CID`) |
| `current_number` | unsignedBigInteger, default 0 | Counter terakhir yang dipakai |

Unique: (`pop_id`, `sequence_type`). Row di-lock (`lockForUpdate()`) tiap kali generate ID baru — cegah 2 request bersamaan dapat nomor sama.

## Model Relations (ringkas)

```php
// Pop
parent(): BelongsTo(Pop::class, 'parent_id')
children(): HasMany(Pop::class, 'parent_id')
sequences(): HasMany(PopSequence::class)
distributions(): HasMany(Distribution::class)
users(): BelongsToMany(User::class, 'user_pops')  // legacy, lihat docs/rbac
```

## Audit

`Pop` — trait `RecordsAuditLogs`, module `POP/Cabang`, full CRUD event default (created/updated/deleted).
