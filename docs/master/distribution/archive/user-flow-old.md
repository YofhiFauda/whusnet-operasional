> **Arsip.** Dokumen lama/spesifikasi awal — sebagian tidak sesuai skema kode aktual (field fabrikasi seperti `capacity`/`used_ports`/`is_active` gak pernah ada). Lihat `../README.md`, `../business-logic.md`, `../database-schema.md` untuk kondisi kode terkini.

# User Flow: Master Distribusi

Dokumen ini mendeskripsikan langkah-langkah pengguna (User Flow) dalam mengelola Master Distribusi.

## Skenario Utama: NOC Menginput Daftar OLT dan ODP Baru

**Aktor**: Teknisi NOC / Admin Jaringan

**Langkah-langkah**:
1. User mengklik menu **Master Data** di Sidebar.
2. User memilih sub-menu **Distribusi Jaringan**.
3. Sistem memuat tabel inventaris perangkat yang terdaftar.
4. User mengklik tombol "Tambah Perangkat Distribusi".
5. User mengisi form: Tipe (Misal "OLT"), Kode (Misal "OLT-SBY-02"), Kapasitas Port (Misal: 64).
6. User menyimpan data. Sistem mencatatnya di tabel `distributions`.

## Hubungan Saat Instalasi Pelanggan (Skenario Hilir)
1. Saat Teknisi Lapangan melakukan *Pemasangan FOP* ke pelanggan, mereka mengisi `CustomerTechnicalDetail`.
2. Di dalam form tersebut, Teknisi bisa melihat daftar OLT/ODP yang bersumber dari Master Distribusi ini.
3. Teknisi memilih OLT terkait.
