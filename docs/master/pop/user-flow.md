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

## 5. FOP/Admin — Assign Mini POP & Distribusi ke Pelanggan (✅ Fixed 2026-07-07)

1. Buka halaman detail pelanggan (`/customers/{customer}`) — kartu "Ringkasan Teknis Jaringan" nampilin CID/REQ ID, POP Cabang, Mini POP, dan Distribusi saat ini.
2. Kalau punya permission `customers.detail.installation.validate`, CID/REQ ID jadi tombol — klik buka modal "Atur Mini POP & Distribusi".
3. Pilih Mini POP dari dropdown (cuma nampilin Mini POP anak Cabang POP pelanggan ini) → dropdown Distribusi otomatis ke-filter ikutan (cuma nampilin Distribusi anak Mini POP terpilih).
4. Submit — ditolak kalau pelanggan masih pra-pemasangan (`registered` s/d `waiting_installation`) atau `rejected`.
5. Kalau pelanggan udah `active`/`suspended`, CID otomatis di-regenerate pakai Mini POP/Distribusi baru begitu disimpan.
6. Bisa diulang kapan aja pasca pemasangan — dipakai buat sinkron manual ke konfigurasi Mikrotik aktual (belum ada integrasi hardware otomatis).

Detail teknis & riwayat gap sebelum fix: [bug.md](bug.md).

## 5a. Admin — Pindah POP Pelanggan (ADHOC-104 → ADHOC-107, final 2026-09-29)

1. Buka `/customers/{customer}/edit` → step **3. POP & Distribusi**. Di bawah dropdown POP, form sudah memberi tahu:
   - **"Belum bisa pindah Cabang: N tagihan wajib lunas dulu (sisa Rp …)"** — piutang bulan-bulan sebelumnya atau tagihan yang sudah dicicil sebagian. Tagih/lunasi dulu.
   - atau **"Kalau pindah Cabang, N tagihan bulan berjalan … ikut pindah"** — tagihan bulan ini yang belum dibayar sama sekali akan dibayar ke Cabang baru.
2. Ganti **POP Cabang** (cuma Cabang dalam scope user).
3. **Mini POP & Distribusi** baru bisa dipilih kalau Cabang sudah diganti, dan hanya kalau:
   - pelanggan sudah masuk tahap pemasangan (pra-pemasangan: cuma POP Cabang yang boleh diatur), **dan**
   - user punya izin atur jaringan (`customers.detail.installation.validate`).
   Selain itu dropdown terkunci dengan keterangan alasannya; Mini POP & Distribusi cabang lama tetap dilepas, dan yang baru diatur pemegang izin lewat modal §5.
   Tanpa ganti Cabang, dropdown hanya informasi (nilai legacy tetap tampil apa adanya) — ubah Mini POP/Distribusi lewat modal §5.
4. Simpan. Ditolak kalau: POP di luar scope, REQ ID sudah dipakai di POP tujuan, masih ada tagihan penghalang, Mini POP/Distribusi terkunci tapi diisi, atau hierarkinya tidak cocok.
5. Hasil:
   - REQ ID tetap; CID dibuat ulang otomatis (kalau `active`/`suspended`).
   - Tagihan bulan berjalan yang belum dibayar pindah ke Cabang baru; tagihan & pembayaran lama tetap di Cabang lama.
   - **Kolektor dilepas** → admin Cabang baru meng-assign kolektor lewat Worksheet Kolektor (daftar "tanpa kolektor").
   - Username PPPoE tidak berubah; kalau tidak lagi cocok dengan CID, Detail Pelanggan & Quick Hub menampilkan peringatan → NOC ubah di Mikrotik lalu di Edit Pelanggan.

Aturan lengkap: [business-logic.md §7a](business-logic.md#7a-pindah-pop-adhoc-104--adhoc-107-final-2026-09-29).

## Guard Ringkas

| Aksi | Permission |
|------|-----------|
| Lihat | `pops.view` |
| Tambah, edit, toggle status | `pops.create\|pops.update` |
| Assign Mini POP & Distribusi ke pelanggan | `customers.detail.installation.validate` |

## Terhubung dengan Modul Lain

- Pelanggan baru registrasi → `registration_prefix` POP itu jadi basis REQ ID (lihat [docs/customer-lifecycle](../../customer-lifecycle/README.md)).
- Assign scope RBAC user ke Cabang POP → otomatis cover semua Mini POP di bawahnya (lihat [docs/rbac](../../rbac/README.md)).
- Distribusi (lihat [docs/master/distribution](../distribution/README.md)) selalu terikat ke 1 Mini POP tertentu.
