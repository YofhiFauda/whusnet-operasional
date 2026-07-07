# User Flow — Master POP

Aktor: **Owner/Admin** (`pops.view`/`create`/`update`).

## 1. Lihat Daftar POP

1. Buka `/master/pop` — tabel ditampilkan sebagai **tree** (Pusat → Cabang → Mini POP), diindentasi sesuai `depth`.
2. Filter: search (nama/code/pop_code/PIC), tipe (`pusat`/`cabang`/`mini_pop`), status (`active`/`inactive`).
3. Scope otomatis — non-Owner/Admin cuma lihat POP yang dia punya akses (`Pop::scopeForUser()`).

## 2. Tambah POP Baru

1. Klik "Tambah POP" → isi: kode internal, `pop_code` (format terstruktur), `registration_prefix`, `cid_prefix`, nama, tipe, parent (opsional untuk `pusat`, wajib untuk `cabang`/`mini_pop`), alamat, PIC.
2. Submit → semua identifier di-uppercase otomatis. Kalau `pop_code` bentrok atau format salah, ditolak dengan pesan spesifik.

**Penting:** `registration_prefix` & `cid_prefix` menentukan **seumur hidup** identitas pelanggan yang didaftarkan di POP ini — isi dengan hati-hati, ubah belakangan gak akan mengubah kode pelanggan yang sudah terlanjur dibuat pakai prefix lama.

## 3. Edit POP

1. Buka POP dari daftar → form edit terisi data existing + tambahan field `status`.
2. Pilihan `parent_id` otomatis exclude POP itu sendiri + semua turunannya (cegah circular).
3. Submit — validasi sama seperti create (kecuali unique check exclude row sendiri).

## 4. Toggle Status

1. Klik toggle di daftar/detail → status langsung flip `active`↔`inactive`, tanpa konfirmasi tambahan.
2. POP `inactive` tetap kelihatan di daftar (bisa difilter), tapi gak muncul di dropdown pilihan parent POP baru / assignment lain yang filter `where('status','active')`.

## Guard Ringkas

| Aksi | Permission |
|------|-----------|
| Lihat | `pops.view` |
| Tambah, edit, toggle status | `pops.create\|pops.update` |

## Terhubung dengan Modul Lain

- Pelanggan baru registrasi → `registration_prefix` POP itu jadi basis REQ ID (lihat [docs/customer-lifecycle](../../customer-lifecycle/README.md)).
- Assign scope RBAC user ke Cabang POP → otomatis cover semua Mini POP di bawahnya (lihat [docs/rbac](../../rbac/README.md)).
- Distribusi (lihat [docs/master/distribution](../distribution/README.md)) selalu terikat ke 1 POP tertentu.
