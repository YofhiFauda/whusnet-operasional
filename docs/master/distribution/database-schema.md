# Database Schema — Master Distribusi

## Entity Relationship

```
pops ──hasMany──▶ distributions ──hasMany──▶ customers (via customers.distribution_id, nullOnDelete)
```

## Tabel `distributions`

Migrasi: `2026_06_17_161046_create` (kolom awal: `pop_id`, `code` unique, `description` NOT NULL) → `2026_06_17_170019_add_name` (tambah `name`, nullable) → `2026_06_18_084816_update_unique_index` (unique jadi composite `pop_id`+`code`, `description` dilonggarkan jadi nullable) → `2026_06_19_140908_make_code_globally_unique` (unique index diubah lagi jadi `code` doang, global — supersede composite sebelumnya).

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `pop_id` | FK → `pops.id`, cascade delete | | Wajib, many-to-one |
| `code` | string(50), **unique global** | | Riwayat: unique global → sempat jadi composite (`pop_id`+`code`) → balik unique global lagi (keputusan final sesuai spesifikasi) |
| `name` | string(150) | ✔ | Ditambah lewat migrasi terpisah, nullable di DB tapi **wajib** di validasi form (`required|string|max:150`) |
| `description` | string(255) | ✔ | Awalnya wajib di migrasi create, dilonggarkan jadi nullable — konsisten dengan validasi form (`nullable`) |

**Tidak ada kolom:** `type`, `capacity`, `used_ports`, `status`, `location` — semua itu fabrikasi di dokumentasi lama (`archive/database-schema-old.md`), gak pernah ada di migrasi manapun.

## Model Relations (ringkas)

```php
// Distribution
pop(): BelongsTo(Pop::class)
```

Tidak ada relasi `hasMany(Customer::class)` didefinisikan eksplisit di model — relasi ke pelanggan cuma via FK `customers.distribution_id`, diakses dari sisi `Customer` (`belongsTo`) kalau dibutuhkan, bukan dari sisi `Distribution`.

## Audit

`Distribution` **tidak** pakai trait `RecordsAuditLogs` — perubahan/hapus data Distribusi **tidak tercatat** di audit log manapun. Beda dari `Pop` yang full-audited (lihat [docs/master/pop/database-schema.md](../pop/database-schema.md)).
