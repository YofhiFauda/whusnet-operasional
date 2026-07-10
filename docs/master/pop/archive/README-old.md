> **Arsip.** Dokumen lama/spesifikasi awal — sebagian tidak sesuai skema kode aktual (field fabrikasi seperti `capacity`/`used_ports`/`is_active` gak pernah ada). Lihat `../README.md`, `../business-logic.md`, `../database-schema.md` untuk kondisi kode terkini.

# Master POP (Point of Presence)

Master POP / Cabang menyimpan data lokasi stasiun pemancar atau cabang operasional ISP. POP adalah komponen vital karena beroperasi sebagai referensi area layanan dan berperan aktif dalam mekanisme RBAC (Role-Based Access Control) di mana admin/staf cabang hanya boleh melihat data dari POP mereka sendiri.

## Fungsi Utama
1. Referensi titik distribusi jaringan pusat ISP.
2. Filter akses data pelanggan. Pegawai yang di-assign pada `POP A` tidak akan bisa melihat pelanggan di `POP B`.
3. Pencatatan alamat dan kontak perwakilan kantor cabang.
4. Auto-generate sequence ID/Kode Pelanggan (tiap POP biasanya memiliki kode prefix tersendiri).

## File Terkait
- **Model**: `app/Models/Pop.php`
- **Model Sequence**: `app/Models/PopSequence.php`
