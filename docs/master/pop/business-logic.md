# Business Logic — Master POP

## 1. Hierarki 3 Level

| Level (`type`) | Contoh | Aturan |
|-----------------|--------|--------|
| `pusat` | Nama ISP | Level tertinggi, representasi perusahaan itu sendiri |
| `cabang` | Jetis | Anak dari `pusat`, punya `cid_prefix`+`registration_prefix` sendiri |
| `mini_pop` | C1, C2, C3 | Anak dari `cabang`, `pop_code`-nya nempel prefix cabang + segmen sendiri |

Self-referencing via `parent_id`. **Semua kode ditentukan manual oleh admin** (bukan auto-generate) — keputusan eksplisit dari spesifikasi awal (lihat [archive/spesifikasi-pop-distribusi-cid.md](archive/spesifikasi-pop-distribusi-cid.md) §1).

## 2. Kode-Kode yang Wajib Unik

| Kolom | Unik | Format | Fungsi |
|-------|------|--------|--------|
| `code` | Global (`unique:pops,code`) | Bebas | Identitas umum |
| `pop_code` | Global | `[A-Z0-9]+(-[A-Z0-9]+)*`, di-uppercase paksa (`normalizeIdentifierInput()`) | Resolve segmen Mini POP di CID |
| `registration_prefix` | Tidak divalidasi unik secara eksplisit, tapi konvensinya beda per Cabang | `[A-Z0-9]+`, uppercase | Prefix REQ ID pelanggan |
| `cid_prefix` | Sama, gak divalidasi unik eksplisit | `[A-Z0-9]+`, uppercase | Huruf kode Cabang di CID final |

**Cegah circular parent:** `PopController::edit()`/`update()` exclude semua descendant (rekursif turun) dari daftar pilihan `parent_id` — POP gak bisa jadi induk dari leluhurnya sendiri.

## 3. Generate REQ ID (`Pop::generateRegistrationNumber()`)

Dipanggil saat registrasi pelanggan baru (`CustomerController::store()`).

- Format: `{registration_prefix}{6 digit}` — e.g. `RQ000001`.
- **Permanen** — REQ ID ini nempel ke pelanggan seumur hidup, jadi basis CID setelah aktivasi, dan **muncul lagi apa adanya** kalau pelanggan di-terminate (lihat §5).
- Counter (`PopSequence`, `sequence_type=registration`) di-lock (`lockForUpdate()`) dalam transaction — race-condition safe untuk registrasi concurrent.
- **Self-healing terhadap data import:** sebelum increment, sistem cek angka REQ ID tertinggi yang sudah ada di `customers` untuk POP itu (`MAX(SUBSTRING(customer_code...))`) — kalau counter di `pop_sequences` ternyata lebih rendah dari data riil (misal abis migrasi data lama), counter di-sync naik dulu. Ini mencegah collision kalau data lama pernah insert kode lebih tinggi dari counter yang tercatat.
- Loop `do...while` cek `Customer::where('customer_code', $candidate)->exists()` — extra safety net di luar lock, walau practically jarang kepakai karena lock udah cukup.

## 4. Generate CID (`Pop::generateComplexCid()`)

Dipanggil **cuma** di `CustomerVerificationController::finalVerify()` — satu-satunya jalur resmi aktivasi (lihat [docs/customer-lifecycle](../../customer-lifecycle/README.md) & [docs/task-teknisi/bug.md](../../task-teknisi/bug.md) soal kenapa harus satu jalur ini).

Format: `{cid_prefix}{segmen_mini_pop}{kode_distribusi}{req_id}` — e.g. `D2X6CRQ000021` (sebelum suffix `_DESA_NAMA` yang ditambah terpisah di `generatePppoeUsername()`).

**Resolusi segmen Mini POP** (`resolveMiniPopSegment()`) — urutan prioritas:
1. Kalau `customer.pop.pop_code` diawali `cid_prefix` POP itu → ambil sisa string setelah prefix (dibersihkan non-alphanumeric).
2. Kalau gagal, fallback ke `customerTechnicalDetail.olt_number` (dibersihkan sama).
3. Kalau masih gagal, default `'1'`.

⚠️ **`customer.pop` di sini SELALU row type `cabang`** (lihat §8) — bukan row Mini POP. Jadi segmen ini diambil dari `pop_code` milik **Cabang POP pelanggan sendiri**, bukan dari Mini POP yang di-pilih terpisah. Kalau admin isi `pop_code` Cabang dengan pola `{cid_prefix}{angka}` (e.g. cid_prefix=`D`, pop_code=`D1`), angka `1` itu yang muncul jadi segmen — bukan hasil relasi ke row Mini POP manapun.

**Kode Distribusi** — dari `Distribution.code` yang di-assign ke pelanggan; kalau belum ada distribusi, pakai placeholder `'XX'`.

## 5. Resolve Display ID per Status (`Pop::resolveDisplayId()`)

Aturan tampilan ID pelanggan berbeda tergantung status — ini **bukan** kolom tersimpan, dihitung on-the-fly tiap kali ditampilkan:

| Status Pelanggan | ID yang Ditampilkan | Contoh |
|-------------------|----------------------|--------|
| `terminated`/`failed`/`rejected`/`putus`/`gagal` | REQ ID murni | `RQ001296` |
| `active`/`suspended` + **punya** `distribution_id` & `cid` | CID lengkap | `D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH` |
| `active`/`suspended` + **belum** punya distribusi | Format default | `C00RQ001296` |
| Status lain (registrasi, survey, pemasangan, dst) | Format default | `C00RQ001296` |

**Prinsip kunci:** REQ ID **tidak pernah berubah/hilang** — cuma "dibungkus" beda tergantung status. Saat terminate, sistem gak generate ID baru, cuma balik nampilin REQ ID murni yang dari awal udah ada (`extractBareRegistrationId()` strip prefix `cid_prefix+"00"` dari `customer_code`/CID).

## 6. Peran di RBAC Scope

`Pop.parent_id` juga jadi basis `EffectiveAccessService::resolvePopTree()` — user dengan scope `selected_pop` yang di-assign ke 1 Cabang otomatis dapat akses ke **semua Mini POP di bawahnya** (BFS turun lewat `parent_id`). Lihat [docs/rbac/business-logic.md §6](../../rbac/business-logic.md#6-scope-pop--3-tipe).

## 7. Mini POP Gak Pernah Dipilih User — Cuma Node Hierarki

**Row `type=mini_pop` tidak pernah di-assign langsung ke pelanggan/task/invoice manapun.** Dicek di seluruh controller — cuma **1 tempat** yang benar-benar exclude/filter berdasar `type`:

| Halaman | Query POP | Level yang tampil |
|---------|-----------|---------------------|
| Master POP (`/master/pop`) | Semua | Pusat/Cabang/Mini POP — buat kelola struktur (`parent_id`) |
| **Registrasi & Edit Pelanggan** | `Pop::where('type','cabang')` | **Cuma Cabang.** Mini POP gak pernah muncul di dropdown ini |
| Filter tabel (index Pelanggan/Task/FOP Dashboard/FOP Tasks) | Semua (gak difilter type) | Semua level muncul, tapi cuma buat filter tampilan, bukan assignment |

`customers.pop_id` **selalu** menunjuk row Cabang. Konsekuensi: segmen "Mini POP" di CID (§4) bukan hasil relasi ke row Mini POP manapun — itu string yang di-derive dari `pop_code` milik Cabang itu sendiri, fallback ke `olt_number` yang teknisi isi manual di form pemasangan.

**Row Mini POP di tabel `pops` cuma berguna untuk 1 hal:** node RBAC scope tree (§6) — assign user ke scope Cabang otomatis mencakup semua Mini POP anaknya. Kalau tim operasional berekspektasi Mini POP = representasi 1 OLT fisik yang dipilih eksplisit per pelanggan, itu **belum ada** di kode — sekadar konvensi penamaan `pop_code`, bukan relasi data yang di-enforce sistem.

## 8. Hal yang Belum/Sengaja Tidak Divalidasi

- `registration_prefix` dan `cid_prefix` **tidak** ada unique constraint di level DB maupun validasi form — 2 Cabang POP secara teknis bisa punya prefix yang sama, yang akan bikin REQ ID/CID pelanggan dari 2 cabang berbeda kelihatan identik. Ini bukan bug yang ditemukan aktif, tapi celah desain yang perlu disiplin operasional (isi manual dengan hati-hati) sampai divalidasi eksplisit.
- `generateCid()` (bukan `generateComplexCid()`) ditandai `@deprecated`, dipertahankan cuma untuk kompatibilitas panggilan lama — jangan pakai di kode baru.
