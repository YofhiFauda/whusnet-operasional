# Rancangan: List Pelanggan Isolir + Realtime Isolir & Tagihan

Status: **DIGANTIKAN (2026-11-19).** User memutuskan rebuild penuh berbasis
lifecycle kasus — lihat `docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md`
§21 (Addendum Implementasi Laravel). Dokumen ini (termasuk §6) jadi arsip
diagnosa awal & jejak keputusan sebelum keputusan rebuild — JANGAN dipakai
lagi sebagai acuan desain antrean/skema `isolir_queue_entries`. Implementasi
ADHOC-306 (belum commit) akan diganti total, bukan ditambah.
Tanggal: 2026-11-18 (awal), 2026-11-19 (revisi §6, lalu digantikan).

## 0. Keputusan final user (sebelum coding)

1. Role Customer Service = kode `customer_service` (sudah ada).
2. Halaman List Isolir = penampung calon isolir (antrean).
3. Tunggakan = `Invoice::scopePiutang()` (periode < bulan berjalan, belum lunas).
4. Eksekutor: Admin, Customer Service, NOC — Admin ditambahkan `customers.isolir` (sebelumnya TIDAK punya).
5. Entri manual boleh dibatalkan oleh siapa pun yang berhak mengelola antrean (bukan cuma pembuatnya), asal tercatat di AuditLog.
6. Role lain TIDAK boleh menambah entri manual (hanya Admin/CS/NOC lewat `customers.isolir_queue.create`).
7. Halaman "Tagihan" di keluhan realtime = `/invoices/belum-lunas` (`InvoiceController::index` dengan `status_group=belum_lunas`).

## 1. Diagnosa kondisi sekarang

### 1.1 Badge tab Isolir tidak bertambah saat isolir dari List Pelanggan

- Badge tab `Isolir` dirender server-side dari `$statusCounts['suspended']`
  (`app/Http/Controllers/Concerns/RendersCustomerList.php` ~L393, view
  `resources/views/customers/partials/_list_filters.blade.php` ~L125).
- Toggle isolir (`CustomerController::toggleSuspend()`, L326–391) mengembalikan
  JSON, tapi **tidak** memancarkan event apa pun.
- JS di `resources/views/customers/partials/_list_scripts.blade.php`
  (`submitHubToggleConnection`, ~L543–600) hanya mengubah sel status di baris
  tabel dan UI Quick Hub. Angka badge di tab **tidak** disentuh → tetap sampai
  halaman di-reload.

### 1.2 Pelanggan yang diaktifkan kembali masih ada di tab Isolir

- Tab Isolir = `?status=suspended`, daftar difilter di server.
- Setelah aktifkan kembali, JS hanya mengubah sel status. **Baris tidak
  dihapus** dari daftar yang sedang tampil, dan badge tidak turun.

### 1.3 Akar masalah

Tidak ada sumber realtime untuk perubahan status pelanggan. Halaman List
Pelanggan menggabungkan dua hal yang harus disinkronkan: (a) isi daftar
berdasarkan filter status, dan (b) angka badge. Keduanya hanya di-update oleh
respons aksi di tab pelaku, bukan oleh perubahan data.

Akibat tambahan: pengguna lain yang sedang membuka List Pelanggan juga tidak
melihat perubahan sampai reload.

### 1.4 Pola yang sudah ada di repo

- Event realtime `ShouldBroadcastNow` (tanpa queue), contoh
  `app/Events/InvoiceStatusUpdated.php` → channel `PrivateChannel('invoices.{pop_id}')`.
- Listener halaman: `window.pageEcho(channel).listen(event, cb)`
  (`resources/js/page-echo.js`). Otomatis lepas saat pindah halaman (htmx boost).
- `resources/views/invoices/index.blade.php` ~L440–450 sudah memakai pola ini
  untuk daftar tagihan.

### 1.5 Tagihan (poin 8)

Belum diverifikasi halaman mana yang dimaksud "Halaman Tagihan" (kemungkinan
`invoices/index`). Jika iya, listener `invoices.{pop_id}` sudah ada, dan
masalahnya kemungkinan di jalur pembayaran yang tidak memancarkan
`InvoiceStatusUpdated` atau di logika baris yang tidak menghapus baris lunas.
**Perlu dicek sebelum keputusan final.**

## 2. Keputusan dari user

| # | Keputusan |
|---|---|
| 1 | Role Customer Service = kode `customer_service` (ada di `RoleSeeder`). |
| 2 | Halaman List Isolir = penampung calon isolir. |
| 3 | Masuk antrean: (a) manual dipilih dari List Pelanggan, atau (b) tunggakan = invoice `periode < bulan berjalan` belum lunas (`Invoice::scopePiutang()`). |
| 4 | Quick Hub = modal di List Pelanggan; isolir dari halaman antrean dan dari modal hasilnya sama. |
| 5 | Eksekutor isolir: `admin`, `customer_service`, `noc`. Wajib catat pelaku + waktu. |
| 6 | Isolir cukup di sistem, manual. Belum ada integrasi hardware. Otomatis ditunda ke fase berikutnya. |
| 7 | List Pelanggan + tab Isolir realtime (badge dan isi tab), tanpa reload. |
| 8 | Halaman Tagihan realtime: pelanggan yang sudah bayar hilang dari daftar tanpa reload. |

## 3. Desain yang diusulkan

### 3.1 Antrean isolir

Daftar antrean = pelanggan dengan `status = active` AND (

- ada entri manual di tabel `isolir_queue_entries`, ATAU
- ada invoice piutang (`Invoice::scopePiutang()`)
).

Konsekuensi penting: **setelah diisolir, pelanggan otomatis keluar dari antrean**
karena syarat `status = active` tidak lagi terpenuhi. Kalau diaktifkan kembali
dan tunggakan belum lunas, dia muncul lagi di antrean. Ini perilaku yang
diinginkan.

Tabel baru `isolir_queue_entries` (sederhana, sesuai aturan "tabel baru kalau
kolom cukup" — di sini kolom di `customers` tidak cukup karena butuh pelaku,
waktu, dan alasan):

- `customer_id` (unique), `source` (`manual`), `note` (wajib diisi),
  `marked_by` (user), `marked_at`.
- Hapus entri manual saat pelanggan diisolir atau diterminate (dibersihkan di
  service, bukan di controller).

### 3.2 Eksekusi isolir

- Tetap pakai `CustomerController::toggleSuspend()` → diganti / dibungkus
  service supaya dipakai dua tempat (List Pelanggan, Quick Hub, dan halaman
  antrean) tanpa logika kedua.
- Pelaku + waktu: `AuditLog` dan `CustomerStatusLog` sudah menyimpan
  `user_id`/`changed_by` dan `created_at`. **Sudah memenuhi syarat.**
- Permission: pakai `customers.isolir` yang sudah ada. Pastikan grant ke
  `admin`, `customer_service`, `noc` di matrix role (`RolePermissionSeeder`),
  **belum diverifikasi**. Halaman antrean butuh permission view baru,
  dibuat lewat feature/action seeder, bukan hardcode.
- POP scope wajib di setiap query antrean dan aksi isolir.

### 3.3 Realtime

**Event baru:** `CustomerStatusChanged` (`ShouldBroadcastNow`).

- Dipancarkan dari `CustomerObserver::updated()` saat `status` berubah,
  supaya semua jalur (controller, import, artisan) ikut. Dispatch dengan
  `DB::afterCommit` agar tidak broadcast data yang rollback.
- Channel: `PrivateChannel('customers.pop.{pop_id}')`, otorisasi lewat
  `EffectiveAccessService` (sama dengan pola `invoices.{pop_id}`).
- Payload minimal: `customer_id`, `pop_id`, `old_status`, `new_status`,
  `status_label`. Tanpa data pribadi lain.

**Sisi klien (List Pelanggan + Antrean):**

1. Event masuk → fetch `counts` ringan (JSON, scope-aware, memakai query yang
   sama dengan `RendersCustomerList`) → update badge tab.
2. Jika baris ada di tabel dan tidak lagi sesuai tab aktif → hapus baris.
   Jika pelanggan masuk tab aktif → fetch baris/fragment tabel dan sisipkan.
3. Pakai `pageEcho`, bukan `Echo.private` langsung (hindari listener menumpuk
   setelah htmx boost).
4. Quick Hub ikut update lewat event yang sama, tidak perlu logika terpisah.

Alasan pakai fetch counts, bukan hitung delta di klien: badge harus benar
untuk scope POP user, dan delta mudah salah saat banyak aksi cepat.

### 3.4 Tagihan (poin 8, menunggu verifikasi 1.5)

Jika halaman yang dimaksud `invoices/index`:

- Pastikan semua jalur pembayaran (`CollectorPaymentService`, pembayaran admin)
  memancarkan `InvoiceStatusUpdated`. Cek apakah sudah.
- Di klien: saat `new_status = lunas` (atau sisa 0), hapus baris dari daftar
  yang sedang tampil, tanpa reload.

## 4. Pertanyaan terbuka

1. Halaman "Tagihan" yang dimaksud poin 8 itu `invoices/index` atau halaman lain?
2. Antrean: apakah entri manual boleh dibatalkan oleh orang lain selain
   pembuatnya? (Saran: boleh, asal dicatat.)
3. Apakah teknisi/role lain boleh menambah entri manual? (Saran: tidak, sesuai
   daftar eksekutor.)

## 5. Rencana test (sebelum coding selesai)

- Feature: isolir dari antrean dan dari List Pelanggan mencatat `AuditLog` +
  `CustomerStatusLog` dengan pelaku dan waktu.
- Feature: pelanggan diisolir keluar dari antrean; diaktifkan kembali dengan
  tunggakan muncul lagi.
- Feature: role di luar eksekutor (mis. `teknisi`, `sales`) ditolak 403.
- Feature: scope POP — user POP A tidak melihat / tidak bisa isolir pelanggan POP B.
- Feature: `CustomerStatusChanged` dipancarkan saat status berubah dan tidak
  saat rollback.
- Feature: otorisasi channel `customers.pop.{id}`.
- Pengujian realtime di browser (manual): dua tab, satu isolir, tab kedua
  badge berubah tanpa reload.

Jalankan hanya test yang terdampak (`php artisan test --compact --filter=...`),
sesuai aturan repo.

## 6. Revisi desain antrean (2026-11-19)

### 6.1 Masalah yang ditemukan setelah implementasi awal

Desain §3.1 pakai syarat `status = active`. Begitu NOC eksekusi isolir,
baris **hilang seketika** dari Antrean (pindah ke `/customers?status=suspended`,
halaman beda). Admin/CS kehilangan jejak — gak tau pelanggan itu sudah
diisolir atau ke mana, apalagi kalau ratusan pelanggan. Antrean harusnya jadi
satu tempat transit yang nunjukin seluruh siklus (nunggak → isolir → lunas/
aktif lagi), bukan cuma daftar "yang belum dieksekusi".

### 6.2 Definisi antrean baru

Syarat tampil di Antrean (ganti total §3.1): `ada piutang (Invoice::scopePiutang())`
ATAU `ada entri manual aktif` — **tanpa syarat `status`**. Baris cuma hilang kalau
kedua alasan itu sama-sama tidak ada (piutang lunas DAN tidak ada entri manual
aktif).

Dua tab di halaman yang sama (bukan dua halaman):
- **"Menunggu Eksekusi"** — `status = active`. NOC belum eksekusi.
- **"Sudah Diisolir"** — `status = suspended`. Sudah dieksekusi, masih
  tercatat di sini sampai alasan masuknya hilang. Tombol **"Aktifkan Kembali"**
  langsung di baris (reuse `toggleSuspend`, tanpa loncat halaman — ini yang
  dulu bikin orang bingung).

Pindah tab & hilang dari antrean semuanya realtime (event yang sama dari
ADHOC-306: `CustomerVerificationStatusChanged` utk perubahan status,
`InvoiceStatusUpdated`/pelunasan utk piutang clear).

### 6.3 `isolir_queue_entries` jadi log riwayat, bukan 1 baris per pelanggan

Masalah lama: `updateOrCreate` + unique `customer_id` + hapus baris saat
dibatalkan → catatan CS ("sudah ditelpon 3x", dst) hilang permanen begitu
dibatalkan atau diisolir. User minta ini jadi **bukti/riwayat**, bukan cuma
AuditLog (susah dibaca staff).

Perubahan skema (menimpa §3.1 soal tabel ini):
- Hapus unique constraint di `customer_id` — satu pelanggan boleh punya
  banyak baris riwayat sepanjang waktu.
- Tambah kolom `cancelled_at` (nullable timestamp), `cancelled_by` (nullable
  FK `users`).
- **Entri aktif** pelanggan = baris terbaru miliknya dengan `cancelled_at IS NULL`.
  Query antrean & cek "ada entri manual aktif" pakai definisi ini.
- Tandai manual: kalau pelanggan belum punya entri aktif → INSERT baris baru
  (riwayat nambah). Kalau sudah punya entri aktif → UPDATE note-nya di tempat
  (edit, bukan bikin baris kedua yang tumpang tindih).
- Batalkan: **bukan delete** — isi `cancelled_at` + `cancelled_by` (soft-cancel).
  Boleh dibatalkan oleh siapa pun yang berhak kelola antrean, kapan pun
  termasuk saat pelanggan masih diisolir & piutang belum lunas — asal tercatat
  (siapa, kapan). Ini juga yang jawab pertanyaan terbuka §4.2.
- UI: baris pelanggan bisa di-expand untuk lihat riwayat lengkap (semua baris
  miliknya, aktif maupun sudah dibatalkan) — siapa nandai kapan + catatan,
  siapa batalkan kapan. AuditLog tetap dicatat juga (konsisten pola existing),
  tapi riwayat yang dilihat staff sehari-hari dari tabel ini sendiri, bukan
  AuditLog.

### 6.4 Dampak ke `CustomerObserver`

Hapus logic "hapus entri manual saat status keluar dari active" yang
ditambahkan di ADHOC-306 (§3.1 lama) — bertentangan dengan §6.3 (entri gak
boleh hilang otomatis, cuma lewat batalkan manual atau alasan clear).

### 6.5 Konfirmasi user (2026-11-19)

1. Entri manual boleh dibatalkan kapan pun meski masih diisolir/belum lunas,
   asal tercatat — **dikonfirmasi**.
2. Tombol "Aktifkan Kembali" langsung di baris tab "Sudah Diisolir" — **dikonfirmasi,
   boleh dicoba**.
3. Tab pemisah "Menunggu Eksekusi" / "Sudah Diisolir" (bukan filter dropdown) —
   **dikonfirmasi**.

### 6.6 Dampak ke implementasi ADHOC-306 yang sudah dikoding (belum commit)

Karena belum di-commit, revisi ini jadi perubahan langsung di kode yang sama,
bukan task baru:
- Migration `isolir_queue_entries`: ubah sebelum pernah jalan di DB manapun
  yang permanen (masih lokal) — aman diubah langsung, bukan migration baru.
- `CustomerIsolirQueueService`: `markManual()`/`unmarkManual()` diubah sesuai
  §6.3.
- `CustomerIsolirQueueService::queueQuery()`: ganti syarat sesuai §6.2.
- View `customers/isolir-queue/index.blade.php`: tambah dua tab + expand
  riwayat + tombol Aktifkan Kembali.
- `CustomerObserver`: hapus logic penghapusan otomatis (§6.4).
- Test `IsolirQueueTest` perlu direvisi menyesuaikan perilaku baru (beberapa
  test lama akan gagal by design, bukan regresi).
