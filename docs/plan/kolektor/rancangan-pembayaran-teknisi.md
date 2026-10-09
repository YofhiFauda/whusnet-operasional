# Rancangan — Pembayaran Dicatat Teknisi

> Status: **IN PROGRESS** — backend selesai (ADHOC-122), UI belum. Lihat `docs/TASKS.md` ADHOC-122.
> Keputusan final user (2026-10-05): jam tutup **23:59**, "wajib setor" = **peringatan saja**, `pic_gudang` **boleh** mencatat, ADHOC baru, CLAUDE.md/RBAC ikut diubah.
> Keputusan batch (2026-10-05, berlaku untuk teknisi, kolektor, dan portal staf): saldo pelanggan **boleh** dipakai per tagihan; **tidak ada lebih bayar** lewat batch (tunai + saldo ≤ sisa tagihan), kelebihan hanya lewat Tagihan admin. Detail: `docs/kolektor/business-logic.md` §"Pakai saldo pelanggan" dan "Jalur masuk selain kolektor".
> Tanggal: 2026-10-05. Sumber: diskusi user + analisa kode modul kolektor.

---

## 1. Latar Belakang

Teknisi di lapangan kadang menerima pembayaran tagihan dari pelanggan yang **tidak di-assign** ke dirinya dan **tidak ada di worklist kolektor**. Saat ini uang seperti itu hanya bisa masuk lewat jalur admin (Tagihan / Bayar) — padahal uangnya ada di tangan teknisi, bukan admin.

Kebutuhan:

1. Teknisi bisa mencatat pembayaran lewat Portal, mirip kolektor.
2. Pelanggan **tidak perlu** di-assign admin terlebih dulu.
3. Pembayaran tercatat sebagai pembayaran **dari Teknisi**, dengan badge "Teknisi" (kolektor = badge "Kolektor").
4. Admin tahu: invoice dibayar oleh teknisi atau kolektor; saldo yang dipegang tiap teknisi/kolektor.
5. Semua ada rincian, tidak ada yang di-skip.

---

## 2. Keputusan (sudah disetujui user)

| # | Keputusan | Sumber |
|---|---|---|
| K1 | Aturan "Teknisi tak boleh catat pembayaran" **diubah** menjadi "teknisi boleh catat pembayaran terbatas". CLAUDE.md (Larangan keras #4) dan `docs/rbac/` ikut diubah. | user, 2026-10-05 |
| K2 | **Tidak ada hardcode baru.** Teknisi tetap satu role; kemampuan mencatat pembayaran diberikan lewat **matrix role** (permission), bukan kode `where('code','teknisi')`. Pakai `Role::TECHNICIAN_CODES` / `User::isTechnician()` bila perlu pengecekan role teknisi. | user, 2026-10-05 |
| K3 | Batas: hanya pelanggan **dalam POP scope** teknisi (`EffectiveAccessService`). Tidak ada batas nominal per hari. | user, 2026-10-05 |
| K4 | **Wajib setor sebelum tutup hari.** Saldo teknisi yang belum disetor saat tutup hari ditandai dan muncul di Worksheet Admin. | user, 2026-10-05 (default usulan) |

K4 belum punya definisi "tutup hari" yang pasti — lihat §7.1 dan §9 (pertanyaan terbuka).

---

## 3. Prinsip Yang Tidak Boleh Dilanggar

Diambil dari aturan kolektor yang sudah ada (`docs/kolektor/business-logic.md`):

- **Saldo = angka turunan.** `saldo(X) = Σ payment(collected_by = X, VALID, collector_deposit_id IS NULL)`. Dilarang membuat kolom `saldo` yang di-`+=`/`-=`.
- **Satu jalur pencatatan.** Pembayaran teknisi memakai `CollectorPaymentService::record()` — tidak menulis logika pembayaran sendiri.
- **Idempotensi dan transaksi all-or-nothing** tetap berlaku (sudah ada di `record()`).
- **POP scope wajib** di setiap query pelanggan/invoice (aturan Larangan #3).
- **Teknisi tidak mengubah `customers.collector_id`.** Pencatatan oleh teknisi tidak mengubah penugasan kolektor pelanggan.
- **Tidak ada kolom saldo, tidak ada `payments.update` untuk teknisi.** Koreksi mengikuti jalur ADHOC-108 (edit pembayaran oleh admin).

---

## 4. Hak Akses (RBAC)

### 4.1 Permission

Tidak membuat permission untuk "teknisi" secara khusus. Dua opsi, pilih satu saat implementasi:

| Opsi | Cara | Catatan |
|---|---|---|
| **A (dipakai)** | Tambah `kolektor.pay` + `kolektor.qr.pay` + `kolektor.deposit` ke role `teknisi` lewat matrix (`RolePermissionSeeder`) | Teknisi = kolektor untuk jalur pembayaran. Tidak ada kode baru. **`kolektor.view` (Lihat Worklist) SENGAJA tidak diberikan**: teknisi mencatat lewat pencarian pelanggan (§5), bukan worklist yang berbasis penugasan admin. Sempat tertulis ikut di sini; sudah dikoreksi. |
| B | Permission baru `teknisi.pay` | Lebih granular, tapi duplikasi logika dan dua jalur pembayaran. Tidak dipilih. |

Alasan A: user menyebut teknisi "merangkap" kolektor; memakai permission yang sama menghindari dua jalur uang.

### 4.2 Perubahan aturan tertulis

- `CLAUDE.md` → Larangan keras #4: ganti "Teknisi tak boleh catat pembayaran" dengan "Teknisi boleh catat pembayaran hanya untuk pelanggan dalam POP scope-nya dan wajib menyetor saldo (lihat `docs/plan/kolektor/rancangan-pembayaran-teknisi.md`)".
- `docs/rbac/business-logic.md` & `docs/rbac/README.md` → tambah baris matrix role `teknisi`.
- `docs/kolektor/business-logic.md` → tambah bagian "Teknisi sebagai pencatat".

### 4.3 Yang tetap dilarang untuk teknisi

- Tidak boleh setor/verifikasi setoran orang lain.
- Tidak boleh hapus buku, reject payment, atau edit payment (itu jalur admin/owner).
- Tidak boleh melihat worksheet admin.

---

## 5. Worklist & Pencarian Pelanggan

Ini satu-satunya perubahan kode besar.

- Worklist kolektor (`CollectorWorklistService::dueInvoices()`) hanya menampilkan pelanggan dengan `customers.collector_id = dia`. Teknisi **tidak** boleh lewat worklist ini untuk pelanggan lain.
- Teknisi mencatat lewat **pencarian pelanggan**:
  - Scan QR pelanggan (Portal, `StaffPortalToken` purpose baru `teknisi_bayar`, TTL 15 menit, sama polanya dengan `kolektor`), atau
  - Cari CID / nama dalam POP scope.
- Setelah pelanggan dipilih, tampil daftar invoice belum lunas (periode berjalan + piutang). Submit memakai `CollectorPaymentService::record()` dengan `$collector = teknisi` dan `$actor = teknisi`.
- Validasi POP scope ditegakkan di **server** (`validateRows()`), bukan hanya di UI: invoice di luar POP scope teknisi ditolak per baris.

Pola URL: rute baru tanpa parameter `{collector}`, identitas dari `auth()->user()` (sama seperti `collector-worklist/pay`).

---

## 6. Penanda Sumber Pembayaran (Badge)

Badge "Kolektor" / "Teknisi" **tidak diturunkan dari role user saat ini**, karena user bisa berganti role. Simpan snapshot:

- Tambah kolom `payments.collected_by_role` (string nullable, nilai dari enum baru `CollectorRole`: `kolektor`, `teknisi`). Diisi saat `record()` sesuai jalur masuk (`kolektor-worklist/pay` → `kolektor`; jalur pencari teknisi → `teknisi`).
- Enum baru `App\Enums\CollectorRole` (aturan: jangan string literal).
- Migrasi: data lama dengan `collected_by` terisi → `kolektor`. Tidak ada backfill tebakan.
- Tampilan:
  - Detail invoice: "Dibayar oleh **Teknisi** {nama}" / "**Kolektor** {nama}" / "Admin".
  - Tabel pembayaran, laporan pembayaran: kolom "Sumber" + filter.
  - Kuitansi: nama + badge sesuai snapshot.

---

## 7. Saldo & Setoran Teknisi

Memakai mesin yang sudah ada, tanpa tabel baru:

- **Saldo Belum Disetor teknisi** = `CollectorBalanceService::balance($teknisi)` (sama persis dengan kolektor).
- **Setoran** = `CollectorDepositService::submit($teknisi)`. Siklus `menunggu_verifikasi → terverifikasi / selisih / lebih_setor` berlaku sama.
- **Kurang setor** dan **hapus buku** berlaku sama.
- Setoran teknisi memakai kolom `collector_deposits.collector_id` = teknisi. Tidak ada kolom "jenis setoran" baru, kecuali admin butuh filter — tambahkan filter berdasarkan `payments.collected_by_role` (bukan kolom baru di setoran).

### 7.1 Aturan setor sebelum tutup hari (K4)

- "Tutup hari" = jam tutup operasional (config `billing.collector_close_hour`, default **18:00 WIB** — **perlu konfirmasi user**).
- Saat jam tutup: setiap teknisi dengan Saldo Belum Disetor > 0 ditandai **"Belum Setor Hari Ini"**.
- Tanda itu muncul di:
  - Worksheet Admin (tab Teknisi): daftar teknisi + jumlah saldo + jam pembayaran terakhir.
  - Dashboard owner/admin (angka ringkas).
  - Notifikasi ke teknisi (in-app + Telegram bila aktif).
- **Pilihan penegakan** (perlu konfirmasi user):
  - (a) Hanya peringatan (dipakai di rancangan ini sebagai default).
  - (b) Blokir pencatatan pembayaran baru sampai setor hari itu (keras). Tidak dipilih dulu karena bisa menghambat pelanggan di lapangan.

### 7.2 Yang dihitung admin

Worksheet Admin tab "Teknisi" (tab terpisah dari "Kolektor"):

| Kolom | Sumber |
|---|---|
| Nama teknisi, POP | `users`, `Pop` |
| Saldo Belum Disetor | `CollectorBalanceService::balance` |
| Selisih terbuka (kurang setor) | `CollectorBalanceService::openShortfallDeposits` |
| Terakhir setor | `collector_deposits` |
| Status tutup hari (§7.1) | perhitungan baru di `CollectorBalanceService` |

Rincian per teknisi (halaman detail): daftar pembayaran belum disetor + daftar setoran, sama dengan `collector-worksheet.show`.

---

## 8. Dampak ke Modul Lain (wajib dicek saat implementasi)

| Modul | Dampak |
|---|---|
| `AdminCashBalanceService::isVisibleTo()` & `representativePopId()` | Teknisi harus dikenali sebagai pemegang uang. Teknisi hanya melihat saldo/setoran miliknya. |
| `PaymentObserver` | Jalur masuk pembayaran teknisi tetap lewat observer (audit + nominal > 0). |
| `CustomerRelocationService` | Kolektor selalu dilepas saat pindah POP; teknisi tidak punya penugasan, jadi tidak ada yang dilepas. Pembayaran teknisi yang belum disetor tetap milik teknisi, tidak ikut pindah. |
| `PaymentService::revise()` (ADHOC-108) | Pembayaran yang tertaut setoran membeku metode & pencatat. Berlaku sama untuk `collected_by_role = teknisi`. |
| Laporan pendapatan (`CashReports`, `PaymentReportService`) | Harus memisahkan sumber: admin, kolektor, teknisi. Pembayaran saldo otomatis (`PaymentMethod::SALDO`) tetap dikecualikan. |
| `DashboardController` | Statistik pembayaran per sumber. |
| Role `pic_gudang` (ADHOC-120) | Role ini juga turunan teknisi (`Role::TECHNICIAN_CODES`). Pastikan permission pembayaran ikut diberikan atau tidak, sesuai keputusan user. |
| `DemoUsersSeeder`, `RolePermissionSeeder` | Tambah permission ke role `teknisi`. Seeder demo untuk akun teknisi. |
| Portal (`PortalStaffKolektorController`) | Tambah endpoint paralel untuk purpose `teknisi_bayar`, memakai `RecordsCollectorBatch`. |

---

## 9. Pertanyaan Terbuka

1. Jam tutup hari: 18:00 WIB benar?
2. Penegakan K4: peringatan saja (a) atau blokir (b)?
3. Pic Gudang (ADHOC-120): ikut boleh mencatat pembayaran atau tidak?
4. Teknisi yang juga ditugaskan sebagai kolektor (merangkap dua role): badge mengikuti jalur yang dipakai (sudah dirancang di §6).
5. Sprint/ADHOC untuk modul ini: ditetapkan user di `docs/TASKS.md`.

---

## 10. Rencana Implementasi (urutan)

1. **Dokumen & aturan** — ubah CLAUDE.md (Larangan #4), `docs/rbac/`, `docs/kolektor/business-logic.md`.
2. **Enum & kolom** — `CollectorRole`, migrasi `payments.collected_by_role` + backfill `kolektor` untuk data lama yang `collected_by` terisi.
3. **Permission** — matrix role `teknisi` (`RolePermissionSeeder`), uji `RolePermissionMatrixTest`.
4. **Worklist pencarian** — endpoint pencarian pelanggan dalam POP scope + pembayaran oleh teknisi lewat `record()`; `collected_by_role = teknisi`.
5. **Portal QR** — `StaffPortalToken` purpose `teknisi_bayar` + endpoint paralel.
6. **Saldo & setoran** — tab Teknisi di Worksheet Admin, status "Belum Setor Hari Ini" (§7.1), notifikasi.
7. **Tampilan** — badge Sumber di detail invoice, tabel pembayaran, kuitansi, laporan (filter sumber).
8. **Test** — minimal:
   - Teknisi di luar POP scope ditolak per baris.
   - Teknisi tidak bisa melihat pelanggan lain lewat worklist kolektor.
   - `collected_by_role` benar untuk jalur kolektor vs teknisi.
   - Saldo teknisi turun saat setoran terverifikasi; pembayaran ditolak menurunkan saldo sendiri.
   - Kurang setor & hapus buku berlaku sama untuk teknisi.
   - Status "Belum Setor Hari Ini" muncul di worksheet admin.
   - Badge Sumber tampil benar di detail invoice dan kuitansi.
   - Teknisi tidak bisa setor/verifikasi/hapus buku milik orang lain.
9. **Docs** — update `docs/TASKS.md` (Notes/In Progress), dokumen modul terkait.

Jalankan `vendor/bin/pint --dirty` sebelum commit. Jangan jalankan full suite (aturan user); jalankan filter test yang terdampak saja.
