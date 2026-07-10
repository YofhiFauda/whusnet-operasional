# User Flow — Master Distribusi

Aktor: **Admin/NOC** (`pops.view`/`create`/`update`/`delete` — reuse permission Master POP).

## 1. Lihat Daftar Distribusi

1. Buka `/master/distribusi` — tabel semua Distribusi + POP induknya.
2. Filter: search (kode/nama/deskripsi), POP tertentu.

## 2. Tambah Distribusi Baru

1. Klik "Tambah Distribusi" → pilih POP induk, isi kode (harus **unik di seluruh sistem**, bukan cuma di POP itu), nama, deskripsi.
2. Submit — kode di-uppercase otomatis. Kalau kode udah dipakai Distribusi lain (POP manapun), ditolak.

**Konvensi penamaan disarankan:** sisipkan identitas POP di kode (misal `X4A` untuk cabang tertentu) supaya gampang dibedakan secara visual walau constraint-nya global, bukan per-POP.

## 3. Edit Distribusi

1. Ubah POP induk, kode, nama, atau deskripsi — validasi sama seperti create (unique check exclude row sendiri).

## 4. Hapus Distribusi

1. Klik hapus → **langsung terhapus tanpa konfirmasi dependency**. Kalau ada pelanggan yang masih pakai Distribusi ini, `distribution_id` mereka otomatis jadi kosong (CID yang udah ter-generate sebelumnya tidak berubah, tapi assignment distribusinya hilang).
2. **Disarankan:** cek dulu apakah Distribusi ini masih dipakai pelanggan aktif sebelum hapus (sistem belum otomatis mem-blok ini).

## 5. Pemakaian di Alur Pemasangan (Hilir)

1. Saat teknisi submit laporan pemasangan, mereka pilih Distribusi yang sesuai lokasi ODP/OLT fisik pelanggan.
2. Pilihan ini nempel ke `customer.distribution_id`, dipakai nanti saat Verifikasi Admin generate CID final.

Lihat [docs/customer-lifecycle/user-flow.md §4](../../customer-lifecycle/user-flow.md#4-teknisi--proses-pemasangan) untuk detail form pemasangan.

## Guard Ringkas

| Aksi | Permission |
|------|-----------|
| Lihat | `pops.view` |
| Tambah/Edit | `pops.create\|pops.update` |
| Hapus | `pops.delete` |
