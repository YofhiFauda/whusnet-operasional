# Rancangan Master Data Distribusi

## 1. Deskripsi Umum
Master Data Distribusi digunakan untuk mengelola data titik distribusi atau sub-area di bawah suatu POP (Point of Presence) / Cabang. Data ini nantinya dapat digunakan untuk pemetaan area, pengelompokan pelanggan, atau manajemen teknis jaringan.

## 2. Struktur Database
Tabel: `distributions`

| Nama Kolom | Tipe Data | Keterangan |
| :--- | :--- | :--- |
| `id` | bigint (pk) | Auto increment (No) |
| `pop_id` | bigint (fk) | Relasi ke tabel `pops` (Cabang/POP) |
| `code` | varchar(50) | Kode Distribusi (unik) |
| `name` | varchar(150) | Nama Distribusi |
| `description` | varchar(255) | Deskripsi Distribusi |
| `created_by` | bigint (fk) | User pembuat data (opsional, untuk audit log) |
| `updated_by` | bigint (fk) | User pengubah data (opsional, untuk audit log) |
| `created_at` | timestamp | Waktu pembuatan |
| `updated_at` | timestamp | Waktu pembaruan |

## 3. Relasi Database
- `distributions.pop_id` memiliki relasi `belongsTo` ke tabel `pops.id`.
- (Opsional) `customers.distribution_id` (jika pelanggan perlu di-assign ke distribusi tertentu ke depannya).

## 4. Kebutuhan Antarmuka (UI/UX)
Lokasi Menu: **Master Data** -> **Distribusi**

### A. Halaman Index (Daftar Distribusi)
- Menampilkan tabel dengan kolom: No, Kode, Nama Distribusi, Deskripsi, Cabang, dan Aksi.
- Terdapat tombol "Tambah Distribusi".
- Aksi: Edit, Hapus (atau non-aktifkan jika ada relasi).

### B. Form Tambah/Edit
- **Kode Distribusi:** Input text, wajib diisi, harus unik.
- **Nama Distribusi:** Input text, wajib diisi.
- **Cabang/POP:** Dropdown list (Select2) yang mengambil data dari tabel `pops`. Wajib diisi.
- **Deskripsi:** Input text/textarea.

## 5. Validasi
- `pop_id`: required, exists on `pops.id`.
- `code`: required, string, max 50, unique on `distributions` table.
- `name`: required, string, max 150.
- `description`: nullable/optional, string, max 255.

## 6. Acceptance Criteria
- [ ] Migration untuk tabel `distributions` dibuat dan berhasil dijalankan.
- [ ] Model `Distribution` dibuat dan memiliki relasi yang benar ke model `Pop`.
- [ ] Controller `DistributionController` dibuat untuk menangani CRUD.
- [ ] Halaman Index menampilkan daftar distribusi dengan paginasi.
- [ ] Form Create berhasil menyimpan data dengan dropdown POP.
- [ ] Form Edit berhasil mengubah data.
- [ ] Hak akses diatur agar hanya role yang berwenang (misal: Owner, Admin Pusat) yang dapat mengubah data master ini (jika RBAC diterapkan).
