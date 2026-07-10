> **Arsip.** Dokumen lama/spesifikasi awal — sebagian tidak sesuai skema kode aktual (field fabrikasi seperti `capacity`/`used_ports`/`is_active` gak pernah ada). Lihat `../README.md`, `../business-logic.md`, `../database-schema.md` untuk kondisi kode terkini.

# User Flow: Master POP

Dokumen ini mendeskripsikan skenario penggunaan fitur Master POP (Cabang).

## Skenario Utama: Admin Menambah Cabang Baru

**Aktor**: Admin Pusat / Owner

**Langkah-langkah**:
1. User mengklik menu **Master Data** di Sidebar.
2. User memilih sub-menu **POP (Cabang)**.
3. User melihat daftar cabang yang sudah ada.
4. User mengklik tombol "Tambah POP Baru".
5. User mengisi form: Nama Cabang, Kode Prefix (Misal: `SBY` untuk Surabaya), Alamat, dan Telepon.
6. User menyimpan data. Sistem mencatat POP baru ke tabel `pops`.

## Dampak Lanjutan (Sistem Otomatis):
- Saat CS cabang mendaftarkan pelanggan dan memilih POP "Surabaya", sistem akan secara otomatis di belakang layar membuat record di `pop_sequences` untuk mencatat penomoran pelanggan perdana (Misal: SBY-202606-0001).
- Admin bisa mengatur RBAC agar pegawai B hanya bisa mengakses data di cabang Surabaya.
