# Gap — Mini POP Gak Pernah Terhubung ke Pelanggan

**Status:** ✅ Fixed 2026-07-07.
**Severity:** Tinggi — CID gak bisa merepresentasikan OLT spesifik per pelanggan, padahal itu inti desain sistem penomoran.

## Ringkasan

Ditemukan lewat diskusi rancangan pemilik produk (bukan lewat kode/testing) — rancangan aslinya:

> Cabang POP → Mini POP (OLT spesifik, `D1`/`D2`) → Distribusi (`X4A`, anak dari Mini POP) → CID = `{cabang}{mini_pop}{distribusi}{req_id}`. Mini POP & Distribusi baru **di-assign pasca pemasangan/aktivasi** (bukan saat registrasi, biar REQ ID/CID gak berantakan di awal), lewat modal yang muncul kalau klik CID/REQ ID di halaman pelanggan. Bisa diganti-ganti belakangan menyesuaikan konfigurasi Mikrotik aktual (belum ada integrasi hardware otomatis).

Kondisi kode **sebelum** perbaikan:

1. **`customers` gak punya kolom `mini_pop_id`** — Mini POP cuma exist sebagai row `pops` (`type=mini_pop`), gak pernah nyambung ke pelanggan manapun lewat FK.
2. **Segmen "Mini POP" di CID diambil dari `pop_code` milik Cabang POP pelanggan sendiri** (`Pop::resolveMiniPopSegment()`), bukan dari Mini POP spesifik. Karena `customer.pop_id` selalu row Cabang (form registrasi cuma nawarin `type=cabang`), nilai ini **konstan buat semua pelanggan di Cabang yang sama** — gak bisa beda `D1` vs `D2` tergantung OLT fisik pelanggan, padahal itu tujuan utamanya.
3. **Gak ada modal assignment** — Distribusi cuma bisa diubah lewat form Edit Pelanggan biasa (field di tengah form panjang), gak ada UX "klik CID → modal". Gak ada guard status (bisa diubah kapan aja, termasuk sebelum pemasangan).
4. **CID gak regenerate** kalau Distribusi diganti setelah pelanggan aktif — CID tersimpan statis di `customer.cid`.

**Data seeder (`MasterPopSeeder`) ternyata sudah benar** — `Distribution.pop_id` di-set ke **Mini POP**, bukan Cabang. Jadi struktur data (`Cabang → Mini POP → Distribusi`) sebenarnya udah didukung skema, cuma gak ada jalur dari `Customer` buat masuk ke situ.

## Perbaikan Diterapkan

1. **Migrasi** `2026_07_07_154528_add_mini_pop_id_to_customers_table` — tambah `customers.mini_pop_id` (FK nullable → `pops.id`, `nullOnDelete`).
2. **`Customer` model** — `mini_pop_id` masuk `$fillable`, relasi baru `miniPop(): BelongsTo(Pop::class, 'mini_pop_id')`.
3. **`Pop::resolveMiniPopSegment()`** — prioritas baru:
   1. `customer.miniPop` (assignment eksplisit) → segmen dari `pop_code` Mini POP itu.
   2. Fallback lama: `pop_code` Cabang (dipertahankan buat pelanggan lama yang belum di-assign).
   3. Fallback terakhir: `olt_number` free-text dari laporan teknis instalasi.
4. **`CustomerNetworkAssignmentController@update`** (baru) — endpoint `PUT /customers/{customer}/network-assignment`:
   - Guard permission `customers.detail.installation.validate`.
   - Guard status: ditolak kalau status masih pra-pemasangan (`registered`…`waiting_installation`) atau `rejected`.
   - Validasi silang: Mini POP harus anak dari Cabang POP pelanggan; Distribusi harus anak dari Mini POP yang dipilih.
   - Kalau pelanggan udah `active`/`suspended` (udah punya CID) → CID **di-regenerate otomatis** pakai Mini POP/Distribusi baru.
   - Audit log (`update_network_assignment`).
5. **View** `customers/show.blade.php` — CID/REQ ID sekarang jadi tombol (kalau user punya permission validate) yang buka modal "Atur Mini POP & Distribusi". Modal nampilin dropdown Mini POP (anak Cabang pelanggan) + Distribusi (ke-filter otomatis sesuai Mini POP terpilih, pakai Alpine `x-show`). Ditambah 2 field baru di kartu "Ringkasan Teknis Jaringan": Mini POP & Distribusi saat ini.

## Susulan: Tutup Jalur Kedua di Form Edit Pelanggan (✅ Fixed 2026-07-07)

Form Edit Pelanggan (`customers/edit.blade.php`) sempat masih punya field `distribution_id` lama — dropdown nampilin **semua** Distribusi di sistem (gak di-scope ke Mini POP/Cabang), gak ada guard status, gak regenerate CID. Ini jalur kedua yang bisa nyebabin data Distribusi vs CID gak sinkron (pola sama kayak bug approve Task Pemasangan, lihat [docs/task-teknisi/bug.md](../../task-teknisi/bug.md)).

**Perbaikan:** field `distribution_id` + JS `filterDistributionsByPop()` dihapus total dari `customers/edit.blade.php`. `CustomerController::update()` gak lagi validasi/terima `distribution_id` — jadi field itu gak bisa ke-update lewat form edit sama sekali. **Satu-satunya jalur resmi sekarang: modal "Atur Mini POP & Distribusi"** di halaman detail pelanggan.

## Yang Belum Dikerjakan (Scope Lanjutan, Opsional)

- **Data pelanggan lama** (yang udah aktif sebelum fix ini) masih pakai fallback `pop_code` Cabang buat segmen CID-nya — CID mereka **tidak otomatis berubah**. Kalau mau benerin retroaktif, perlu assign `mini_pop_id` manual per pelanggan lewat modal baru ini, lalu CID bakal regenerate otomatis.
