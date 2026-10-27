# Rancangan: Pencatatan Kabel Melintang Jalan (Pemasangan → Deact)

Status: **sudah diimplementasi** (kode lokal, belum di-commit). Sprint baru, tercatat sebagai **ADHOC-205** di `docs/TASKS.md`.

## 1. Latar belakang

Saat Pemasangan, kabel drop kadang ditarik melintang jalan. Jika dibiarkan, kabel ini membahayakan pengguna jalan lain di kemudian hari. Sistem belum mencatat hal ini, sehingga saat Deact tidak ada daftar kabel yang harus ditarik.

Tujuan:
1. Setiap kabel melintang jalan tercatat saat Pemasangan, lengkap dengan rutenya.
2. Saat Deact, kabel tersebut wajib ditarik, atau diberi alasan jika tidak bisa ditarik.
3. Titik yang belum ditarik tetap terpantau sampai beres.

## 2. Keputusan (hasil diskusi)

| No | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Hasil penarikan | Hanya dua status: `ditarik` / `tidak_bisa_ditarik` |
| 2 | Penarikan masuk stok? | **Tidak dicatat ke stok.** Kabel yang sudah keluar/dibeli tidak kembali ke gudang. Tidak ada `InventoryReturnTransferService`, tidak ada stok rusak. |
| 3 | Lokasi | **Wajib.** Berupa koordinat Latitude & Longitude |
| 4 | Sprint | Sprint baru (ADHOC-205) |

Catatan: Lokasi bukan satu titik. Lokasi adalah **rute kabel dari titik awal (mis. tiang / ODP / titik sambung) sampai ke pelanggan**, berisi beberapa titik koordinat berurutan.

## 3. Konsep data

Satu **Rute Kabel Melintang** = satu lintasan kabel yang melintang jalan untuk satu pelanggan. Satu pelanggan bisa punya lebih dari satu rute.

Satu rute punya **banyak titik koordinat** yang diurutkan dari titik awal sampai pelanggan.

### 3.1 Tabel `customer_cable_crossings` (header rute)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `customer_id` | FK `customers` | `restrictOnDelete` |
| `customer_installation_id` | FK `customer_installations`, nullable | Pemasangan asal rute. `restrictOnDelete` |
| `inventory_roll_id` | FK `inventory_rolls`, nullable | Roll kabel pemasangan (pointer dari form Pemasangan). `nullOnDelete` |
| `nama_jalan` | string(191) | **Wajib** |
| `jenis_jalan` | string(100), nullable | Opsional (jalan raya, gang, sungai, dll.) |
| `meter_terpakai` | decimal(8,2), nullable | Informasi saja, bukan mutasi stok |
| `status` | enum `CableCrossingStatus` | `terpasang` / `ditarik` / `tidak_bisa_ditarik` |
| `foto_bukti` | string | Foto bukti pemasangan (**wajib** untuk rute baru) |
| `dicatat_oleh` | FK `users`, nullable | Teknisi pencatat pertama |
| `dicatat_at` | datetime, nullable | |
| `deact_task_id` | FK `tasks`, nullable | Task DEAC yang menyelesaikan rute (untuk kedua hasil) |
| `diselesaikan_at` | datetime, nullable | |
| `foto_penarikan` | string, nullable | Foto bukti saat `ditarik` (**wajib** untuk `ditarik`) |
| `alasan_tidak_ditarik` | text, nullable | **Wajib** jika `tidak_bisa_ditarik` |
| timestamps | | |

Perubahan dari rancangan awal:
- `pop_id` **dihapus**. POP diambil dari `customers.pop_id` supaya ikut berpindah saat pelanggan pindah Cabang dan tidak ada dua sumber kebenaran.
- `ditarik_task_id` / `ditarik_at` diganti `deact_task_id` / `diselesaikan_at`, karena kolomnya juga dipakai untuk hasil `tidak_bisa_ditarik`.
- `foto_penarikan` ditambahkan (bukti penarikan wajib, sesuai §4).

### 3.2 Tabel `customer_cable_crossing_points` (titik koordinat rute)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `customer_cable_crossing_id` | FK, cascade dari header | Titik tidak punya nilai keuangan/riwayat sendiri |
| `urutan` | unsigned int | 1 = titik awal, angka terakhir = pelanggan |
| `label` | string(150), nullable | Mis. "Tiang TR-12", "Rumah pelanggan" |
| `latitude` | decimal(10,7) | **Wajib**, rentang -90..90 |
| `longitude` | decimal(10,7) | **Wajib**, rentang -180..180 |

Unique: `(customer_cable_crossing_id, urutan)`.

### 3.3 Enum `CableCrossingStatus`

- `terpasang` — kabel masih melintang jalan. Satu-satunya status yang **menghalangi** penutupan task DEAC.
- `ditarik` — sudah ditarik saat Deact.
- `tidak_bisa_ditarik` — tidak bisa ditarik, ada alasan. Tidak menghalangi Deact, tetap masuk daftar pemantauan.

Enum ini tidak dipetakan ke `TaskStatus`/`TicketBucket` karena rute kabel bukan task.

## 4. Aturan validasi

**Saat Pemasangan (form Laporan Pemasangan, simpan draft):**
- Checkbox "Kabel melintang jalan?" dicentang → rute wajib diisi. Kalau tidak dicentang, rute yang ikut terkirim **diabaikan**.
- Tiap rute: `nama_jalan` wajib; minimal **2 titik** (awal dan pelanggan); tiap titik wajib latitude & longitude valid.
- Rute **baru** (tanpa `id` yang cocok dengan pemasangan ini) wajib foto bukti. Rute lama boleh tanpa foto baru, foto lama dipertahankan.
- Rute dicatat sebagai draft (sama seperti pointer roll & SN). Setiap simpan **menyinkronkan** rute: rute yang tidak ada di form dihapus, rute yang ada diperbarui, titik ditulis ulang sesuai urutan form.

**Saat Deact (task DEAC, form laporan):**
- Setiap rute `terpasang` pelanggan wajib diberi hasil: `ditarik` + foto penarikan, atau `tidak_bisa_ditarik` + alasan.
- Berlaku untuk **semua** hasil modem (diambil / tidak ditemukan / ditolak), karena kabel melintang tetap ada walau modem tidak diambil.
- Task DEAC tidak bisa ditutup selama masih ada rute `terpasang`. Pengecekan ulang dilakukan di service, di dalam transaksi.

**Tidak ada pencatatan stok** pada penarikan.

## 5. Alur

```
Pemasangan (form Laporan Pemasangan, simpan)
  └─ checkbox "Kabel melintang jalan?" = ya
       └─ isi nama jalan, jenis, meter, foto bukti (rute baru)
       └─ isi titik koordinat berurutan (awal → pelanggan, min. 2)
       └─ rute disinkron, status = terpasang

Deact (task DEAC, form laporan)
  └─ seksi "Kabel Melintang Jalan": semua rute terpasang pelanggan ini
       ├─ Kabel sudah ditarik + foto  → status = ditarik, deact_task_id & diselesaikan_at diisi
       └─ Tidak bisa ditarik + alasan → status = tidak_bisa_ditarik
  └─ Deact boleh ditutup jika semua rute sudah punya hasil
```

## 6. Tampilan

1. **Form Pemasangan** (`installations/report`): seksi "Kabel melintang jalan" di bawah Roll Kabel. Koordinat bisa diisi manual atau dari tombol GPS perangkat.
2. **Detail Pelanggan** (`customers/show`): badge "Kabel melintang: N" di header, untuk rute yang belum selesai (`terpasang` + `tidak_bisa_ditarik`).
3. **Form Deact** (`tasks/device-retrieval-report`): seksi "Kabel Melintang Jalan" di atas Catatan. Hanya tampil kalau ada rute terbuka.
4. **Daftar pemantauan** "Kabel Melintang" (`/customer-cable-crossings`): filter status, default "Belum selesai", dibatasi POP scope.

Pola aksi mengikuti `CLAUDE.md`:
- Isi rute saat Pemasangan = bagian dari form penyelesaian pemasangan (mutasi data, validasi server).
- Hasil penarikan di Deact = inline di halaman laporan task DEAC.

## 7. Hak akses & keamanan

- Feature baru `customer_cable_crossings`, **hanya `view`**, lewat `config/rbac.php`. Diberikan ke `admin` dan `pop_admin` (dibatasi POP scope). `owner` lewat `*`.
- Penulisan rute **tidak** punya permission sendiri: ikut `customers.detail.installation.update` (Pemasangan) dan ikut izin task DEAC. Ini sengaja, supaya tidak ada jalur tulis kedua.
- Daftar pemantauan lewat `EffectiveAccessService` (POP scope). Pelanggan di luar scope tidak terlihat.
- Foto bukti disimpan lewat `FileUploadService::uploadCableCrossingPhoto()`, folder `cable-crossings/`, disk `public` (sama dengan foto pemasangan & kondisi alat yang sudah ada).
- Perubahan dicatat di audit log (`RecordsAuditLogs`, modul `Pemasangan`). Foto disembunyikan dari audit.

## 8. Yang tidak termasuk (scope luar)

- Pencatatan stok kabel saat penarikan (keputusan: tidak).
- Peta visual rute di Peta Infrastruktur (bisa jadi sprint lanjutan).
- Migrasi data pelanggan lama yang punya kabel melintang (diisi manual setelah fitur jadi).
- Sidebar role `fop` / `noc` untuk daftar pemantauan (belum diberikan; bisa ditambah lewat Role Matrix).

## 9. Implementasi

| Bagian | Berkas |
|---|---|
| Enum | `app/Enums/CableCrossingStatus.php` |
| Migration | `database/migrations/2026_10_22_120000_*`, `2026_10_22_120001_*` |
| Model | `app/Models/CustomerCableCrossing.php`, `CustomerCableCrossingPoint.php`, relasi `Customer::cableCrossings()` |
| Factory | `database/factories/CustomerCableCrossing*Factory.php` |
| Service | `app/Services/CableCrossingService.php` (aturan validasi, sinkron Pemasangan, penyelesaian Deact, hitungan badge) |
| Upload | `FileUploadService::uploadCableCrossingPhoto()` |
| Controller | `CustomerInstallationController` (`storePemasangan`, `report`), `TaskDeviceRetrievalController` (`report`, `store`), `CustomerController::show`, `CustomerCableCrossingController` (baru) |
| View | `installations/report.blade.php`, `tasks/device-retrieval-report.blade.php`, `customers/show.blade.php`, `customer-cable-crossings/index.blade.php`, sidebar |
| RBAC | `config/rbac.php`, `CustomerCableCrossingFeatureSeeder` (baru), `RolePermissionSeeder`, `DatabaseSeeder` |
| Route | `routes/web.php`: `customer-cable-crossings.index` |

## 10. Test

| Berkas | Cakupan |
|---|---|
| `tests/Feature/CableCrossingServiceTest.php` | Sinkron rute (buat, ulang, hapus, centang dilepas), aturan foto, invariant Deact, badge, isolasi antar pelanggan |
| `tests/Feature/CableCrossingPemasanganTest.php` | Form Pemasangan: dua titik tersimpan, satu titik ditolak, foto baru wajib, koordinat di luar rentang ditolak, rute tanpa centang diabaikan, form menampilkan rute tersimpan, centang dilepas menghapus rute |
| `tests/Feature/CableCrossingDeactTest.php` | Deact tidak bisa ditutup tanpa hasil per rute, `ditarik` butuh foto, `tidak_bisa_ditarik` butuh alasan, penarikan tidak mengubah stok, rute pelanggan lain tidak ikut |
| `tests/Feature/CustomerCableCrossingIndexTest.php` | Permission 403, owner lihat semua POP, admin dibatasi POP scope, filter default, badge Detail Pelanggan |

Jalankan dengan `php artisan test --compact <berkas>`.

## 11. Asumsi yang perlu dikonfirmasi

- Minimal titik koordinat = 2 (awal + pelanggan). Ubah di `CableCrossingService::pemasanganRules()` kalau ingin lebih ketat.
- Rute yang sudah selesai tetap disimpan sebagai riwayat (tidak dihapus).
- Kalau pemasangan dibatalkan (`cancel`) setelah rute tercatat, rute `terpasang` dari pemasangan itu **tetap ada**. Kabelnya memang masih terpasang di jalan, jadi Deact tetap wajib menanganinya. Pemasangan ulang bisa menghasilkan rute ganda; itu harus dibereskan manual lewat penyelesaian Deact.
- Foto disimpan di disk `public`, konsisten dengan foto pemasangan yang sudah ada. Kalau foto pelanggan harus privat (aturan `CLAUDE.md` untuk lampiran tiket), perlu keputusan terpisah.
