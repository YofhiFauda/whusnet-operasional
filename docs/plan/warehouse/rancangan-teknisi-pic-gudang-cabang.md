# Rancangan: Teknisi Merangkap PIC Gudang Cabang

> **Status:** RANCANGAN — belum ada kode yang diubah.
> **Dibuat:** 2026-09-29 · **Revisi:** 2026-09-30a (tambah pembagian tugas PIC Gudang vs POP Admin — §7; PIC boleh lapor rusak/hilang/opname) · **Revisi:** 2026-09-30b (pisahkan penunjukan PIC dari scope POP — §5.3, tabel `warehouse_pop_pics` — untuk kasus teknisi scope global yang PIC di 1 cabang saja)
> **Modul tersentuh saat implementasi:** RBAC, Gudang (Warehouse), FOP Task, Task Teknisi, Customer (antrean Survey/Verifikasi).
> **Dokumen terkait:** `docs/rbac/business-logic.md`, `docs/warehouse/business-logic.md`, `docs/plan/warehouse/kontrol-anti-manipulasi.md`, `docs/plan/analisa-celah-scope-pop.md`.

---

## Daftar Isi

1. [Ringkasan](#1-ringkasan)
2. [Kebutuhan Lapangan](#2-kebutuhan-lapangan)
3. [Kenapa Sistem Sekarang Tidak Bisa](#3-kenapa-sistem-sekarang-tidak-bisa)
4. [Opsi yang Dipertimbangkan](#4-opsi-yang-dipertimbangkan)
5. [Solusi Terpilih](#5-solusi-terpilih)
6. [Hak Akses Role `pic_gudang`](#6-hak-akses-role-pic_gudang)
7. [Pembagian Tugas PIC Gudang vs POP Admin](#7-pembagian-tugas-pic-gudang-vs-pop-admin)
8. [Inventaris Titik Kode yang Harus Diubah](#8-inventaris-titik-kode-yang-harus-diubah)
9. [Seeder](#9-seeder)
10. [Kontrol Anti-Manipulasi](#10-kontrol-anti-manipulasi)
11. [Migrasi Data](#11-migrasi-data)
12. [Rencana Test](#12-rencana-test)
13. [Urutan Implementasi](#13-urutan-implementasi)
14. [Yang TIDAK Berubah](#14-yang-tidak-berubah)
15. [Keputusan](#15-keputusan)

---

## 1. Ringkasan

Di lapangan, **tiap cabang punya satu teknisi yang sekaligus menjadi PIC (penanggung jawab) gudang cabang**, dan **tiap cabang juga punya POP Admin**. PIC harus:

- tetap bisa **diberi task** oleh FOP seperti teknisi biasa, dan
- bisa **mengurus gudang cabangnya** (terima kiriman Pusat, keluarkan barang ke teknisi, ajukan permintaan stok, lapor rusak/hilang/opname).

Sistem sekarang **belum mendukung** kombinasi ini — termasuk kasus teknisi yang **scope-nya lintas cabang** (`all_pop`, misalnya teknisi senior/keliling) tapi cuma jadi PIC di **satu** cabang tertentu. Solusinya bertumpu pada lima hal:

| # | Pilar | Isi singkat |
|---|---|---|
| 1 | **Satu definisi "siapa teknisi"** | Konstanta `Role::TECHNICIAN_CODES = ['teknisi', 'pic_gudang']`. Semua kode yang mencari teknisi memakai daftar ini, bukan string `'teknisi'`. |
| 2 | **Satu role global `pic_gudang`** | Hak akses = hak Teknisi + hak gudang cabang. Berlaku untuk semua cabang. |
| 3 | **Penunjukan PIC TERPISAH dari scope POP** | Scope (`user_role_scopes`) menjawab "data mana yang boleh dia lihat". Tabel baru `warehouse_pop_pics` menjawab "gudang cabang mana dia jadi penanggung jawab" — dua pertanyaan berbeda, dua sumber berbeda. Detail §5.3. |
| 4 | **PIC pelaksana, POP Admin pemeriksa** | PIC menjalankan gudang sehari-hari. POP Admin menangani hal yang tidak boleh PIC lakukan untuk dirinya sendiri (issue barang **ke** PIC). Pusat memantau laporan rusak/hilang. Detail §7. |
| 5 | **Aksi tulis gudang dibatasi ke cabang penunjukan, bukan ke seluruh scope** | PIC dengan scope `all_pop` tetap bisa lihat & kerjakan task di semua cabang, tapi issue/terima/adjustment/request stok cuma jalan di cabang yang dia ditunjuk. Detail §8 Kelompok H. |

Role `pic_gudang_jetis` yang sempat dibuat lewat UI **tidak dipakai** dan dihapus (lihat §11).

---

## 2. Kebutuhan Lapangan

Contoh cabang Jetis: **Budi** (teknisi, ditunjuk PIC gudang) dan **Sari** (POP Admin).

| Kebutuhan Budi (PIC) | Wajib? |
|---|---|
| Muncul di daftar teknisi saat FOP membuat/mengatur task | Ya |
| Bisa mengerjakan task (mulai, lapor, absen QR) seperti teknisi lain | Ya |
| Hanya melihat antrean Survey/Pemasangan yang ia ikut kerjakan (sama seperti teknisi) | Ya |
| Bisa menerima barang kiriman Pusat ke gudang Jetis | Ya |
| Bisa mengeluarkan barang (issue) dari gudang Jetis ke teknisi lain | Ya |
| Bisa mengajukan permintaan stok untuk Jetis | Ya |
| Bisa lapor rusak/hilang/opname — dipantau Pusat | Ya |
| Bisa memegang barang (custody) untuk dipakai di lapangan | Ya — **diberikan oleh Sari**, bukan oleh dirinya sendiri |
| **Tidak** bisa melihat/mengurus gudang cabang lain | Ya |

---

## 3. Kenapa Sistem Sekarang Tidak Bisa

### 3.1 Satu user hanya satu role

Kolom `users.role_id` — satu user, satu role. Tidak ada tabel user↔banyak role. `User::hasRole()` hanya membaca `$this->role->code`.

| Role Budi | Akibat |
|---|---|
| `teknisi` | Bisa dapat task, **tidak bisa** mengurus gudang. |
| `pic_gudang_jetis` (dibuat di UI) | Bisa mengurus gudang (kalau diberi permission), tapi **hilang dari daftar teknisi** — FOP tidak bisa memberinya task. |

### 3.2 Teknisi dikenali dari role code yang ditulis langsung

```php
User::whereHas('role', fn ($q) => $q->where('code', 'teknisi'))
```

Pola ini (dan variannya `hasRole('teknisi')`, `whereIn('code', ['teknisi', 'fop'])`) tersebar di **±20 titik** — daftar lengkap di §8. User dengan role code lain tidak akan pernah dianggap teknisi.

### 3.3 Role per cabang dilarang

`CLAUDE.md` → RBAC → Larangan keras no. 1: *dilarang bikin role per cabang*. Role global, cabang dibatasi lewat scope POP. Role `pic_gudang_jetis` melanggar aturan ini: begitu ada PIC Siman, Ponorogo, dst., jumlah role membengkak dan setiap role harus ikut didaftarkan di semua titik §8.

---

## 4. Opsi yang Dipertimbangkan

| Opsi | Cara | Kenapa ditolak / dipilih |
|---|---|---|
| A. Role per cabang (`pic_gudang_jetis`, `pic_gudang_siman`, …) | Satu role per cabang | **Ditolak.** Melanggar larangan keras RBAC. Tiap cabang baru = role baru + ubah semua titik §8. |
| B. Beri permission gudang ke role `teknisi` | Semua teknisi dapat hak gudang | **Ditolak.** Semua teknisi jadi bisa terima kiriman & issue barang — bukan hanya PIC. |
| C. Beri permission langsung ke user Budi | Permission per user | **Ditolak.** Melanggar larangan keras RBAC no. 2. Sistem juga tidak punya jalur ini. |
| D. User multi-role (tabel pivot user↔role) | Budi pegang `teknisi` + `pop_admin` | **Ditolak untuk sekarang.** Perubahan fondasi: `hasRole`, `EffectiveAccessService` (cache permission, scope per role), `user_role_scopes`, form user, ±40 file yang membaca `$user->role`. Terlalu besar untuk satu kebutuhan. |
| E. Kenali teknisi lewat permission (`task.execute`) | Teknisi = siapa pun yang punya `task.execute` | **Ditolak.** Owner (`*`) & role berwildcard ikut terhitung teknisi → muncul di dropdown assign. Batas "teknisi" bisa berubah diam-diam dari Role Matrix. |
| **F. Role global `pic_gudang` + `Role::TECHNICIAN_CODES` + scope POP** | Lihat §5 | **Dipilih.** Perubahan terlokalisasi, patuh aturan RBAC, bisa dipakai semua cabang. |

---

## 5. Solusi Terpilih

### 5.1 Pilar 1 — Satu definisi "siapa teknisi"

Tambah di `app/Models/Role.php`:

```php
/**
 * Role code yang dihitung sebagai TEKNISI LAPANGAN — muncul di dropdown
 * assign task, dihitung beban kerjanya, boleh menerima custody barang, dan
 * dibatasi ke antrean survey/pemasangan miliknya sendiri.
 *
 * SATU-SATUNYA sumber jawaban "apakah role ini teknisi?". Jangan tulis
 * `where('code', 'teknisi')` baru di mana pun — pakai konstanta ini atau
 * helper di bawah. Role baru yang juga turun ke lapangan cukup ditambah
 * di sini. Lihat docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md.
 */
public const TECHNICIAN_CODES = ['teknisi', 'pic_gudang'];

public function isTechnicianRole(): bool
{
    return in_array($this->code, self::TECHNICIAN_CODES, true);
}
```

Helper pendamping:

```php
// app/Models/User.php
public function isTechnician(): bool
{
    return $this->hasRole(Role::TECHNICIAN_CODES);
}

/** Query scope: user yang role-nya teknisi lapangan. */
public function scopeTechnicians(Builder $query): Builder
{
    return $query->whereHas('role', fn ($q) => $q->whereIn('code', Role::TECHNICIAN_CODES));
}
```

Catatan: `Role::isTechnicianRole()` sekarang membandingkan **name** (`'Teknisi'`) — ikut dibetulkan ke code. `User::isTechnician()` & `Role::isTechnicianRole()` saat ini belum dipanggil di mana pun; setelah refactor, titik §8 memakai helper ini.

### 5.2 Pilar 2 — Role global `pic_gudang`

| Atribut | Nilai |
|---|---|
| `code` | `pic_gudang` |
| `name` | `Teknisi PIC Gudang` *(nama final: lihat §15)* |
| `description` | Teknisi lapangan sekaligus PIC gudang cabang (cabang lewat scope POP) |
| `is_system` | `true` — code terkunci di UI, karena dirujuk `TECHNICIAN_CODES` |
| `is_package_restricted` | `true` — sama seperti Teknisi. Whitelist `restricted_packages` bersifat global (tanpa `role_id`), jadi **tidak perlu konfigurasi tambahan** di Business Development. |

### 5.3 Pilar 3 — Penunjukan PIC terpisah dari scope POP

#### 5.3.1 Kenapa scope saja tidak cukup

Rancangan awal (2026-09-29) menyimpulkan "cabang PIC" langsung dari scope-nya: `selected_pop → Jetis` berarti "PIC Jetis". Ini gagal begitu ada **teknisi yang scope-nya lintas cabang** (`all_pop`, misalnya teknisi senior yang membantu task di cabang mana pun) tapi cuma ditunjuk jadi PIC di **satu** cabang. Scope-nya "semua", tapi "PIC di mana" gak bisa dijawab dari situ.

Jadi scope dan penunjukan PIC adalah **dua pertanyaan berbeda**, dan harus punya **dua sumber data berbeda**:

| Pertanyaan | Dijawab oleh |
|---|---|
| Data pelanggan/task mana yang boleh dia lihat & kerjakan | Scope (`user_role_scopes`) — bisa `all_pop`, `selected_pop`, `pop_tree` |
| Gudang cabang mana dia jadi penanggung jawab (boleh issue/terima/adjustment/request stok) | Tabel baru **`warehouse_pop_pics`** — independen dari scope |

#### 5.3.2 Struktur data baru

Tabel pivot kecil, bukan kolom tunggal di `pops` — supaya mendukung baik "1 cabang banyak PIC" maupun "1 orang PIC di beberapa cabang kecil" (§15, keputusan disetujui 2026-09-30):

```
warehouse_pop_pics
  id          bigint, PK
  pop_id      bigint, FK -> pops.id
  user_id     bigint, FK -> users.id
  created_at, updated_at

  UNIQUE (pop_id, user_id)   -- gak boleh baris duplikat
```

Model tipis `app/Models/WarehousePopPic.php` + relasi:

```php
// app/Models/Pop.php
public function gudangPics(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'warehouse_pop_pics')->where('status', 'active');
}

// app/Models/User.php
public function picGudangPops(): BelongsToMany
{
    return $this->belongsToMany(Pop::class, 'warehouse_pop_pics');
}

public function isPicGudangOf(Pop $pop): bool
{
    return $this->picGudangPops()->whereKey($pop->id)->exists();
}
```

#### 5.3.3 Validasi — dua arah, biar penunjukannya gak jadi mati

Halaman Gudang sudah difilter oleh scope (`applyUserScope()`) **sebelum** sampai ke pengecekan "apakah dia PIC cabang ini". Kalau scope user cuma `selected_pop → Jetis` tapi ditunjuk PIC di Siman, dia gak akan pernah bisa membuka gudang Siman sama sekali — penunjukannya tersimpan tapi gak pernah bisa dipakai, dan itu membingungkan saat troubleshooting. Karena itu **wajib divalidasi dari dua arah**:

| Arah | Aturan |
|---|---|
| **Menunjuk PIC baru** | Kalau scope user `selected_pop`/`pop_tree` → cabang yang ditunjuk **wajib** ada di dalam scope-nya (cek lewat `EffectiveAccessService::getAllowedPopIds()`). Kalau scope `all_pop` → bebas ditunjuk ke cabang mana pun. |
| **Mengubah scope user yang sudah jadi PIC** | Kalau perubahan scope membuat cabang PIC-nya jadi di luar scope baru → **ditolak** (bukan dihapus otomatis). Admin harus lepas/pindah dulu penunjukan PIC-nya, baru boleh ubah scope. Alasan: status PIC adalah keputusan operasional, gak boleh hilang diam-diam gara-gara form lain diedit. |

#### 5.3.4 Contoh skenario

| # | Role | Scope | Ditunjuk PIC di (`warehouse_pop_pics`) | Hasil |
|---|---|---|---|---|
| 1 | `pic_gudang` | `selected_pop` (Jetis) | Jetis | Kasus dasar — scope & penunjukan nyambung |
| 2 | `pic_gudang` | **`all_pop`** (keliling) | **Jetis saja** | **Kasus yang melatarbelakangi revisi ini.** Lihat & kerja task semua cabang; hak TULIS gudang (issue/terima/adjustment/request stok) cuma jalan di Jetis |
| 3 | `pic_gudang` | `all_pop` | Jetis **+ Siman** | 1 orang jadi PIC 2 cabang kecil sekaligus |
| 4 | `pic_gudang` | `selected_pop` (Jetis) | Siman | **Ditolak validasi** — PIC di tempat yang gak bisa dia lihat sendiri |
| 5 | `teknisi` biasa | `all_pop` (keliling, tanpa tugas PIC) | — | Kerja task di mana saja, **tanpa** hak gudang sama sekali (role `teknisi` gak dapat permission `warehouse_*`) |

| User | Role | Scope | Ditunjuk PIC di |
|---|---|---|---|
| Budi (PIC Jetis, scope sempit) | `pic_gudang` | `selected_pop` → Jetis | Jetis |
| Sari (POP Admin Jetis) | `pop_admin` | `selected_pop` → Jetis | — |
| Andi (PIC keliling) | `pic_gudang` | `all_pop` | Jetis |

Semua query pelanggan/task tetap discope lewat `EffectiveAccessService` seperti biasa — yang berubah cuma **sumber jawaban** "gudang mana yang dia urus".

#### 5.3.5 Aturan scope role `pic_gudang` (direvisi)

Sebelumnya (2026-09-29): wajib `selected_pop`. **Direvisi** (2026-09-30): role `pic_gudang` boleh scope apa saja (`selected_pop`, `pop_tree`, `all_pop`) — scope tidak lagi dipakai untuk menyimpulkan cabang PIC-nya, jadi tidak ada alasan membatasinya. Yang tetap dibatasi hanya **cabang mana yang boleh diisi di `warehouse_pop_pics`**, lewat validasi §5.3.3.

#### 5.3.6 Konsekuensi UX (perlu diputuskan, §15)

PIC dengan scope `all_pop` yang membuka halaman Gudang akan **melihat** semua cabang (karena scope), tapi tombol Issue/Terima/Request Stok cuma aktif di cabang yang dia ditunjuk. Perlu:
- Default filter cabang saat buka halaman Gudang → cabang PIC-nya (`picGudangPops()`), bukan cabang pertama di daftar.
- Label jelas di cabang lain ("Bukan gudang yang Anda kelola") supaya gak dikira bug saat tombolnya nonaktif.

### 5.4 Pilar 4 — PIC pelaksana, POP Admin pemeriksa

Lihat §7.

---

## 6. Hak Akses Role `pic_gudang`

### 6.1 Bagian Teknisi — salinan persis role `teknisi`

Sumber: `RolePermissionSeeder` (salinan Role Matrix UI, 2026-09-29).

| Permission | Guna |
|---|---|
| `customers.create` | Daftarkan pelanggan dari lapangan |
| `customers.detail.survey.view` / `.update` | Antrean & laporan survey |
| `customers.detail.installation.view` / `.update` / `.activate` | Antrean & laporan pemasangan |
| `task.view.own` | Lihat task miliknya |
| `task.execute` | Mulai/selesaikan/pending task |
| `tasks.qr_attendance.create` | Absen task lewat scan QR |
| `qr_scan.view` | Scan QR internal |
| `tickets.qr.create` | Lapor komplain lewat QR |

> **Aturan sinkron:** kalau hak role `teknisi` diubah di Role Matrix, hak bagian ini **wajib** ikut diubah. Test §12 no. 9 menjaganya.

### 6.2 Bagian PIC Gudang Cabang

| Permission | Guna | Diberikan? |
|---|---|---|
| `warehouse.view` | Dashboard gudang cabangnya | Ya |
| `warehouse_transfer.view` | Lihat kiriman Pusat → cabang (Surat Jalan) | Ya |
| `warehouse_transfer.receive` | Konfirmasi terima kiriman Pusat | Ya |
| `warehouse_issue.view` / `.create` | Keluarkan barang ke teknisi cabang (**bukan ke diri sendiri** — §7.3) | Ya |
| `warehouse_custody.view` | Lihat barang yang dipegang teknisi | Ya |
| `warehouse_traceability.view` | Lacak riwayat SN/roll | Ya |
| `warehouse_reassign.create` | Pindahkan custody teknisi resign/cuti (**bukan ke diri sendiri** — §7.3) | Ya |
| `warehouse_report.view` | Laporan gudang cabangnya | Ya |
| `warehouse_stock_request.view` / `.create` / `.cancel` | Ajukan & batalkan permintaan stok | Ya |
| `warehouse_adjustment.create` | Lapor rusak/hilang/opname — **dipantau Pusat** (§10) | **Ya** *(keputusan user 2026-09-30)* |
| `warehouse_transfer.create` | Kirim barang antar gudang | **Tidak** — keputusan Pusat |
| `warehouse_stock_request.approve` / `.reject` | Setujui/tolak permintaan stok | **Tidak** — keputusan Pusat |
| `warehouse_transfer_invoice.view` | Lihat harga satuan (invoice transfer) | **Tidak** — harga hanya sisi Pusat |

### 6.3 Yang sengaja TIDAK diberikan

`customers.view` (list pelanggan penuh), `payments.*`, `invoices.*`, `fop_tasks.*`, `users.*`, `roles.*` — sama dengan teknisi. PIC gudang bukan admin cabang.

---

## 7. Pembagian Tugas PIC Gudang vs POP Admin

> **Keputusan user 2026-09-30:** Opsi B — PIC pelaksana, POP Admin pemeriksa. PIC **tetap boleh** lapor rusak/hilang/opname, dipantau Pusat.

### 7.1 Tabel pembagian

| Tugas gudang cabang | PIC Gudang | POP Admin | Pusat (admin/owner) |
|---|---|---|---|
| Terima kiriman dari Pusat | ✅ | ❌ lihat saja *(lihat §7.2 untuk cabang tanpa PIC)* | — |
| Keluarkan barang ke teknisi | ✅ kecuali ke diri sendiri | ✅ **hanya ke PIC** *(lihat §7.2)* | — |
| Pindah custody teknisi resign/cuti | ✅ kecuali ke diri sendiri | ✅ | — |
| Ajukan / batalkan permintaan stok | ✅ | ✅ | Setujui / tolak |
| Lapor rusak/hilang/opname | ✅ | ✅ | **Memantau** di laporan gudang (§10) |
| Lihat custody, traceability, laporan | ✅ | ✅ | ✅ |
| Kirim barang antar gudang | ❌ | ❌ | ✅ |
| Lihat harga (invoice transfer) | ❌ | ❌ | ✅ |

Intinya:

- **Barang masuk & keluar sehari-hari** → PIC.
- **Barang untuk PIC sendiri** → selalu lewat POP Admin (PIC tidak bisa memberi barang ke dirinya sendiri).
- **Kerugian** (rusak/hilang/opname) → boleh dilaporkan PIC maupun POP Admin, semuanya tercatat dan **dipantau Pusat**.

### 7.2 Cabang yang belum punya PIC (masa transisi)

Tidak semua cabang langsung punya PIC. Supaya cabang tanpa PIC tidak macet, pembagian §7.1 **aktif otomatis per cabang**:

| Kondisi cabang | POP Admin boleh terima kiriman? | POP Admin boleh issue ke siapa? |
|---|---|---|
| **Belum ada PIC aktif** | ✅ Ya (seperti sekarang) | Teknisi mana pun di cabangnya (seperti sekarang) |
| **Sudah ada ≥1 PIC aktif** | ❌ Tidak — terima kiriman oleh PIC | **Hanya ke PIC** cabang itu |

Karena itu **permission `pop_admin` TIDAK dicabut** (`warehouse_transfer.receive` & `warehouse_issue.create` tetap ada). Pembatasan dilakukan di kode berdasarkan "apakah cabang ini punya PIC aktif". Begitu PIC ditunjuk lewat Manajemen User, aturan B berlaku sendiri tanpa mengubah Role Matrix.

**Definisi "PIC aktif cabang X":** user dengan role `pic_gudang`, `status = active`, dan punya baris di **`warehouse_pop_pics`** untuk POP X (§5.3.2) — **bukan** dari scope-nya. Ini sengaja dipisah dari scope: PIC bisa saja scope-nya `all_pop`, tapi tetap cuma "PIC aktif" untuk cabang yang dia ditunjuk secara eksplisit. Dihitung di **satu tempat** (`Pop::hasActivePicGudang()` / `Pop::gudangPics()`, §8 Kelompok H) — jangan ditulis ulang di tiap controller.

### 7.3 Aturan "tidak ke diri sendiri"

Berlaku untuk **semua** user (bukan cuma PIC), karena memang tidak pernah masuk akal:

- **Issue:** pengeluar barang ≠ penerima barang.
- **Reassign custody:** pelaku ≠ penerima custody baru.

Ditegakkan di **service** (`InventoryIssueService`, `InventoryReassignService`) supaya berlaku dari semua jalur, plus disembunyikan dari dropdown penerima supaya user tidak bingung.

### 7.4 Contoh alur

1. Pusat mengirim 20 modem ke Jetis → **Budi** konfirmasi terima.
2. Teknisi Joko butuh 3 modem → **Budi** issue ke Joko.
3. Budi sendiri butuh 2 modem untuk task besok → **Sari** (POP Admin) issue ke Budi. Budi tidak bisa melakukannya sendiri.
4. Satu modem di tangan Budi rusak → **Budi** lapor rusak dengan bukti foto → muncul di laporan gudang Pusat dengan tanda *"dilaporkan oleh pemegang barang sendiri"* (§10).
5. Sari mencoba terima kiriman berikutnya → ditolak, karena Jetis sudah punya PIC aktif.

---

## 8. Inventaris Titik Kode yang Harus Diubah

Hasil pemindaian 2026-09-29/30 (`grep` `'teknisi'` / `hasRole('teknisi')` / `isTechnicianRole` di `app/`, `resources/`, `routes/`, `config/`, plus entry point gudang). Nomor baris bisa bergeser — cari ulang dengan pola yang sama sebelum mengerjakan.

### Kelompok A — Daftar teknisi untuk assign task (WAJIB)

Kalau tidak diubah, PIC **tidak muncul** di dropdown dan tidak bisa diberi task.

| # | Lokasi | Sekarang | Menjadi |
|---|---|---|---|
| A1 | `app/Http/Controllers/FopTaskController.php:153` | `where('code', 'teknisi')` | `User::technicians()` |
| A2 | `app/Http/Controllers/FopTaskController.php:287` (cek ketersediaan/konflik per tanggal) | sama | sama |
| A3 | `app/Http/Controllers/FopTaskController.php:1436` | sama | sama |
| A4 | `app/Http/Controllers/TaskController.php:663` (`getTeknisiForUser()`) | sama | sama |
| A5 | `app/Services/TeknisiWorkloadService.php:36` | sama | sama |

### Kelompok B — Penerima custody barang di Gudang (WAJIB)

Kalau tidak diubah, PIC tidak bisa **menerima** barang dan custody-nya tidak bisa dipindah.

| # | Lokasi | Sekarang | Menjadi |
|---|---|---|---|
| B1 | `app/Http/Controllers/Warehouse/WarehouseIssueController.php:57` | `whereIn('code', ['teknisi', 'fop'])` | `whereIn('code', [...Role::TECHNICIAN_CODES, 'fop'])` |
| B2 | `app/Http/Controllers/Warehouse/WarehouseReassignController.php:43` | sama | sama |
| B3 | `app/Http/Controllers/Warehouse/WarehouseReassignController.php:89` | sama | sama |
| B4 | `app/Http/Controllers/Warehouse/WarehouseReassignController.php:140` | sama | sama |
| B5 | `app/Http/Controllers/Warehouse/WarehouseScanController.php:93` | sama | sama |

> Tiga baris di `WarehouseReassignController` sebaiknya disatukan jadi satu method privat saat diubah — hanya kalau memang sederhana; jangan memperluas refactor.

### Kelompok C — Pembatasan "teknisi hanya lihat miliknya" (WAJIB, rawan terlewat)

Teknisi hanya melihat pelanggan Survey/Pemasangan yang **ia ikut kerjakan**. Kalau titik ini tidak diubah, PIC akan melihat **seluruh** antrean survey/pemasangan cabangnya — tanpa error, **kebocoran diam-diam**.

| # | Lokasi | Sekarang | Menjadi |
|---|---|---|---|
| C1 | `app/Http/Controllers/CustomerSurveyController.php:55` | `hasRole('teknisi')` | `isTechnician()` |
| C2 | `app/Http/Controllers/CustomerVerificationController.php:63` | sama | sama |
| C3 | `app/Http/Controllers/CustomerVerificationController.php:116` | sama | sama |
| C4 | `app/Http/Controllers/CustomerVerificationController.php:170` | sama | sama |
| C5 | `app/Providers/AppServiceProvider.php:317` (badge jumlah antrean survey) | sama | sama |
| C6 | `app/Providers/AppServiceProvider.php:341` (badge jumlah antrean verifikasi) | sama | sama |

### Kelompok D — Model/helper (WAJIB)

| # | Lokasi | Sekarang | Menjadi |
|---|---|---|---|
| D1 | `app/Models/Role.php:31` `isTechnicianRole()` | `$this->name === 'Teknisi'` | `in_array($this->code, self::TECHNICIAN_CODES, true)` |
| D2 | `app/Models/User.php:112` `isTechnician()` | `hasRole('teknisi')` | `hasRole(Role::TECHNICIAN_CODES)` |
| D3 | `app/Models/User.php` | — | tambah `scopeTechnicians()` |
| D4 | `app/Models/Role.php` | — | tambah `TECHNICIAN_CODES` |

### Kelompok E — Aturan scope user (WAJIB, direvisi 2026-09-30b)

| # | Lokasi | Perubahan |
|---|---|---|
| E1 | `resources/views/users/_form.blade.php:342` (`validScopes` JS) | Tambah `'pic_gudang': ['all_pop', 'selected_pop', 'pop_tree']` — **bukan** dibatasi ke `selected_pop` saja (lihat §5.3.5: cabang PIC ditentukan `warehouse_pop_pics`, bukan scope). |
| E2 | `app/Http/Controllers/UserController.php:150` & `:239` (validasi server create/update) | **Tidak perlu** aturan wajib-scope-tertentu untuk `pic_gudang` (beda dari `pop_admin`). Yang divalidasi bukan scope-nya, tapi §5.3.3 di bawah (Kelompok I). |

### Kelompok F — Butuh keputusan (lihat §15)

| # | Lokasi | Pertanyaan |
|---|---|---|
| F1 | `app/Http/Controllers/DashboardController.php:309` | Statistik "pelanggan yang didaftarkan teknisi" (`salesUser.role.code === 'teknisi'`). Pelanggan yang didaftarkan PIC ikut dihitung? Saran: **ya** → `in_array(..., Role::TECHNICIAN_CODES)`. |
| F2 | `config/rbac.php:35` `role_management_scope` `'admin' => ['teknisi', 'helpdesk']` | Admin boleh mengelola role `pic_gudang` di Role Matrix? Saran: **tidak** — cukup Owner. |

### Kelompok G — Diperiksa, TIDAK perlu diubah

| Lokasi | Alasan |
|---|---|
| `routes/channels.php:111` `teknisi.{user_id}` | Otorisasi berdasarkan `user_id`, bukan role. |
| `app/Services/TaskService.php:296` | Mencari role `fop`, bukan teknisi. |
| `WarehouseScanController.php:288` & `:393` | String tampilan (`?? 'teknisi'`). |
| `FopAnalyticsController.php:573`, `tasks/creq-billing/show.blade.php:446` | Label/kunci array tampilan. |
| `TaskPolicy` | Berbasis permission (`task.view.own`, `task.execute`) — otomatis berlaku untuk `pic_gudang`. |
| `role_in_task = 'teknisi'` (tabel `task_teams`) | Peran dalam tim task, bukan role user. |
| `app/Services/CollectorDepositService.php:457`, `CollectorPaymentService.php:350` | Merujuk `pop_admin` untuk urusan kolektor/kas, tidak terkait gudang. |
| `app/Services/StockRequestService.php` | Tidak mengirim notifikasi ke role tertentu — tidak ada asumsi "satu POP Admin per cabang". |

### Kelompok H — Pembagian tugas PIC vs POP Admin (WAJIB, baru — §7)

| # | Lokasi | Perubahan |
|---|---|---|
| H1 | Helper baru — `app/Models/Pop.php` (`gudangPics(): BelongsToMany`, `hasActivePicGudang(): bool`) | Satu-satunya definisi "PIC aktif cabang X" (§7.2). Sumber: tabel `warehouse_pop_pics` (§5.3.2) + `status = active` — **bukan** scope. |
| H2 | `app/Services/InventoryIssueService.php` | (a) Tolak kalau pengeluar = penerima (§7.3). (b) Kalau pelaku `pop_admin` **dan** cabang punya PIC aktif → penerima wajib salah satu PIC cabang itu. (c) Kalau pelaku `pic_gudang` → POP gudang asal barang wajib salah satu cabang di `picGudangPops()`-nya (§5.3.6/§8.5 Kelompok I — PIC scope `all_pop` gak boleh issue dari gudang di luar cabang penunjukannya). |
| H3 | `app/Http/Controllers/Warehouse/WarehouseIssueController.php` `create()` (±baris 42–65) | Dropdown penerima: sembunyikan diri sendiri; untuk `pop_admin` di cabang ber-PIC tampilkan PIC saja. Selektor cabang gudang: untuk `pic_gudang`, default & batasi ke `picGudangPops()`-nya. Tampilan saja — penegakan tetap di H2. |
| H4 | `app/Services/InventoryReassignService.php` + `WarehouseReassignController` `store*` | Tolak kalau pelaku = penerima custody baru (§7.3); sembunyikan diri sendiri di dropdown. Untuk `pic_gudang`: POP asal custody wajib salah satu cabang penunjukannya (sama pola H2c). |
| H5 | `app/Http/Controllers/Warehouse/WarehouseReceiveController.php` (konfirmasi terima kiriman) | Kalau pelaku `pop_admin` **dan** cabang tujuan punya PIC aktif → tolak dengan pesan "Terima kiriman dilakukan PIC Gudang cabang ini". Kalau pelaku `pic_gudang` → cabang tujuan kiriman wajib salah satu cabang penunjukannya (H2c). Tombol ikut disembunyikan/dibatasi sesuai kondisi. |
| H6 | `app/Http/Controllers/Warehouse/WarehouseReportController.php` (baris kerugian/adjustment) | Tandai baris di mana pelapor (`created_by`) = pemegang custody barang itu → label *"dilaporkan oleh pemegang sendiri"*, supaya Pusat memprioritaskan pemeriksaan (§10). |
| H7 | `app/Http/Controllers/Warehouse/WarehouseStockRequestController.php` `store()` | Kalau pelaku `pic_gudang` → POP asal permintaan wajib salah satu cabang penunjukannya (H2c). |
| H8 | `app/Http/Controllers/Warehouse/WarehouseAdjustmentController.php` `storeBalance()`/`storeOpname()` (adjustment level **saldo POP**, opname) | Kalau pelaku `pic_gudang` → POP wajib salah satu cabang penunjukannya (H2c). **Tidak berlaku** untuk `storeCustody()`/`storeSerial()`/`storeRoll()` (adjustment atas barang di custody-nya sendiri) — itu gak terikat cabang mana pun, barangnya memang di tangan dia. |

> Pesan penolakan H2/H4/H5/H7/H8 dalam bahasa Indonesia dan menyebut siapa/apa yang seharusnya (mis. "Barang untuk PIC dikeluarkan oleh POP Admin", "Cabang ini bukan gudang yang Anda kelola").

### Kelompok I — Penunjukan & validasi PIC (WAJIB, baru — §5.3)

| # | Lokasi | Perubahan |
|---|---|---|
| I1 | Migration baru | Tabel `warehouse_pop_pics` (§5.3.2). |
| I2 | Model baru `app/Models/WarehousePopPic.php` + relasi `Pop::gudangPics()` / `User::picGudangPops()` / `User::isPicGudangOf()` | §5.3.2. |
| I3 | UI penunjukan PIC — disarankan panel baru di halaman Edit User (muncul kalau role dipilih `pic_gudang`): multi-select cabang, **terpisah** dari selektor scope POP. | Nama field beda dari `pop_ids` (punya scope) supaya gak ketuker — mis. `pic_gudang_pop_ids`. |
| I4 | Validasi server saat simpan I3 | Kalau scope user `selected_pop`/`pop_tree` → tiap cabang di `pic_gudang_pop_ids` wajib ∈ `EffectiveAccessService::getAllowedPopIds()`. Scope `all_pop` → bebas (§5.3.3 arah 1). |
| I5 | Validasi server saat user **mengubah scope**-nya sendiri (`UserController::update`) | Kalau user itu terdaftar di `warehouse_pop_pics` untuk cabang X, dan scope baru gak lagi mencakup X → tolak, pesan sebut cabang mana & suruh lepas/pindah PIC dulu (§5.3.3 arah 2). |

### Aturan untuk kode BARU setelah refactor

Dilarang menulis `where('code', 'teknisi')` / `hasRole('teknisi')` baru. Pakai `User::technicians()`, `$user->isTechnician()`, atau `Role::TECHNICIAN_CODES`. Dilarang menulis ulang definisi "PIC aktif cabang" — pakai helper H1. Test §12 no. 10 menjaga aturan pertama.

---

## 9. Seeder

### 9.1 `database/seeders/RoleSeeder.php` — tambah entri

```php
[
    // Teknisi yang merangkap PIC gudang cabang. Role GLOBAL — cabangnya
    // ditentukan scope POP user (selected_pop → Jetis/Siman/…), BUKAN nama
    // role. Dilarang bikin 'pic_gudang_jetis' / 'pic_gudang_siman'.
    // Dihitung sebagai teknisi lewat Role::TECHNICIAN_CODES.
    // docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md
    'code' => 'pic_gudang',
    'name' => 'Teknisi PIC Gudang',
    'description' => 'Teknisi lapangan sekaligus PIC gudang cabang (cabang lewat scope POP)',
    'is_system' => true,
    'is_package_restricted' => true,
],
```

### 9.2 `database/seeders/RolePermissionSeeder.php` — tambah ke `$permissionsByRole`

```php
'pic_gudang' => [
    // — Tugas teknisi: WAJIB sama persis dengan role 'teknisi' —
    'customers.create',
    'customers.detail.installation.activate',
    'customers.detail.installation.update',
    'customers.detail.installation.view',
    'customers.detail.survey.update',
    'customers.detail.survey.view',
    'qr_scan.view',
    'task.execute',
    'task.view.own',
    'tasks.qr_attendance.create',
    'tickets.qr.create',

    // — PIC gudang cabang (pelaksana; POP Admin pemeriksa) —
    // TANPA warehouse_transfer.create (kirim = keputusan Pusat), TANPA
    // stock_request.approve/reject, TANPA transfer_invoice (harga).
    // Issue/reassign ke DIRI SENDIRI ditolak di service, bukan lewat
    // permission — barang untuk PIC dikeluarkan POP Admin.
    'warehouse.view',
    'warehouse_transfer.view',
    'warehouse_transfer.receive',
    'warehouse_issue.view',
    'warehouse_issue.create',
    'warehouse_custody.view',
    'warehouse_traceability.view',
    'warehouse_reassign.create',
    'warehouse_report.view',
    'warehouse_stock_request.view',
    'warehouse_stock_request.create',
    'warehouse_stock_request.cancel',
    // Lapor rusak/hilang/opname — diberikan (keputusan user 2026-09-30),
    // DIPANTAU Pusat: laporan gudang menandai laporan yang dibuat oleh
    // pemegang barang itu sendiri (kontrol-anti-manipulasi.md §1).
    'warehouse_adjustment.create',
],
```

### 9.3 Permission `pop_admin` — **tidak berubah**

Sengaja tidak dicabut (`warehouse_transfer.receive`, `warehouse_issue.create` tetap). Pembagian tugas ditegakkan di kode berdasarkan ada/tidaknya PIC aktif di cabang (§7.2, Kelompok H), supaya cabang tanpa PIC tetap berjalan.

### 9.4 Contoh user PIC (opsional, pola `TechnicianSeeder`) — data demo, bukan wajib

Dua varian, sesuai §5.3.4 skenario 1 dan 2.

**Varian A — PIC scope sempit (skenario 1, kasus dasar):**

```php
$role = Role::where('code', 'pic_gudang')->firstOrFail();
$jetis = Pop::where('name', 'Jetis')->firstOrFail(); // cari lewat nama — id beda tiap server

$user = User::updateOrCreate(
    ['email' => 'pic.gudang.jetis@whusnet.net'],
    [
        'name' => 'PIC Gudang Jetis',
        'phone' => '081200000000',
        'password' => bcrypt('password'),
        'status' => 'active',
        'role_id' => $role->id,
    ]
);

// Scope — data pelanggan/task yang boleh dia lihat.
$scope = UserRoleScope::updateOrCreate(
    ['user_id' => $user->id, 'role_id' => $role->id],
    ['scope_type' => ScopeType::SELECTED_POP->value]
);

UserRoleScopeTarget::firstOrCreate([
    'user_role_scope_id' => $scope->id,
    'pop_id' => $jetis->id,
]);

// Penunjukan PIC — gudang mana yang dia urus. TERPISAH dari scope di atas
// (§5.3), walau di varian ini kebetulan sama-sama Jetis.
WarehousePopPic::firstOrCreate(['user_id' => $user->id, 'pop_id' => $jetis->id]);

app(EffectiveAccessService::class)->clearCache($user);
```

**Varian B — PIC scope global, tapi PIC cuma 1 cabang (skenario 2, §5.3.4):**

```php
$role = Role::where('code', 'pic_gudang')->firstOrFail();
$jetis = Pop::where('name', 'Jetis')->firstOrFail();

$user = User::updateOrCreate(
    ['email' => 'pic.gudang.keliling@whusnet.net'],
    [
        'name' => 'Andi (Teknisi Keliling, PIC Jetis)',
        'phone' => '081200000001',
        'password' => bcrypt('password'),
        'status' => 'active',
        'role_id' => $role->id,
    ]
);

// Scope LUAS — boleh lihat & kerja task di semua cabang.
UserRoleScope::updateOrCreate(
    ['user_id' => $user->id, 'role_id' => $role->id],
    ['scope_type' => ScopeType::ALL_POP->value]
);

// Tapi penunjukan PIC-nya SEMPIT — cuma Jetis.
WarehousePopPic::firstOrCreate(['user_id' => $user->id, 'pop_id' => $jetis->id]);

app(EffectiveAccessService::class)->clearCache($user);
```

Kalau dibuat, daftarkan di `DatabaseSeeder` sebagai baris **terkomentar** bersama seeder demo lain (`TechnicianSeeder`, `AdminGudangCabangSeeder`, …).

### 9.5 Catatan sinkron dengan kondisi repo

- `RolePermissionSeeder` sejak 2026-09-29 berisi **salinan persis Role Matrix UI** (daftar kode eksplisit, bukan wildcard). Kalau `pic_gudang` diatur lewat UI dulu, salin ulang daftar dari DB — jangan menulis dua versi berbeda.
- Semua role di `RoleSeeder` wajib `is_system = true` (dijaga test `test_all_seeded_roles_are_system_roles_so_their_code_is_locked`).

---

## 10. Kontrol Anti-Manipulasi

PIC memegang **dua sisi** sekaligus: pengelola gudang **dan** pemegang barang (custody) sebagai teknisi. Kontrolnya:

| Risiko | Contoh | Kontrol |
|---|---|---|
| **Issue ke diri sendiri** | PIC mengeluarkan 10 modem ke custody-nya sendiri, tidak dipasang, lalu hilang. | **Diblokir** di `InventoryIssueService` (§7.3, H2). Barang untuk PIC dikeluarkan POP Admin — ada orang kedua yang tercatat sebagai pengeluar. |
| **Reassign ke diri sendiri** | PIC memindahkan custody teknisi resign ke dirinya lalu menahan barang. | **Diblokir** di `InventoryReassignService` (§7.3, H4). |
| **Hapus selisih sendiri lewat laporan rusak/hilang** | Barang di custody PIC kurang, PIC catat "hilang". | **Diizinkan, dipantau** (keputusan user 2026-09-30). Semua adjustment tercatat append-only dengan `created_by`; laporan gudang Pusat menandai *"dilaporkan oleh pemegang sendiri"* (H6). Sejalan `kontrol-anti-manipulasi.md` §1: monitoring pasca-fakta, bukan approval sebelum tercatat. Bukti foto tetap wajib sesuai aturan adjustment yang berlaku. |
| **Terima kiriman tanpa cek** | Konfirmasi "Terima Semua Barang" tanpa hitung fisik. | Sudah ada: modal peringatan wajib (`kontrol-anti-manipulasi.md` §4, amandemen 2026-09-18). Selisih lewat adjustment — ikut terpantau. |
| **POP Admin melewati PIC** | Di cabang ber-PIC, POP Admin terima kiriman / issue ke teknisi lain. | **Diblokir** di kode (H2, H5) selama cabang punya PIC aktif. |

Semua transaksi tetap tercatat di ledger append-only — HQ bisa menelusuri siapa mengeluarkan, siapa menerima, siapa melapor.

---

## 11. Migrasi Data

| Langkah | Detail |
|---|---|
| 1 | Jalankan migration `warehouse_pop_pics` (Kelompok I1, §5.3.2). |
| 2 | Jalankan `RoleSeeder` → role `pic_gudang` terbentuk. |
| 3 | Jalankan `RolePermissionSeeder` → permission `pic_gudang` ter-sync. |
| 4 | Beri user role `pic_gudang` + scope sesuai kebutuhannya (sempit ke 1 cabang, atau `all_pop` kalau memang teknisi keliling — §5.3.5). |
| 5 | **Terpisah dari langkah 4:** tunjuk PIC-nya lewat UI baru (Kelompok I3) → isi `warehouse_pop_pics` untuk cabang yang jadi tanggung jawabnya. Begitu disimpan, aturan §7 aktif untuk cabang itu. |
| 6 | Barang yang **sudah** di custody calon PIC (dari masa ia masih role `teknisi`) tetap tercatat atas namanya — tidak perlu dipindah. |
| 7 | Hapus role `pic_gudang_jetis` lewat UI. Kondisi 2026-09-29: **0 user, 0 permission** → aman dihapus. Cek ulang jumlah user sebelum menghapus. |
| 8 | `EffectiveAccessService::clearCache()` untuk user yang dipindah (`RolePermissionSeeder` sudah `Cache::flush()`). |

Ada 1 migration skema baru: tabel `warehouse_pop_pics` (§5.3.2, I1). Tidak ada perubahan kolom di tabel yang sudah ada.

---

## 12. Rencana Test

Nama test mengikuti gejala (konvensi repo). File utama: `tests/Feature/TeknisiPicGudangCabangTest.php` (Pilar 1–3) dan `tests/Feature/PembagianTugasPicGudangPopAdminTest.php` (Pilar 4).

| # | Skenario | Harapan |
|---|---|---|
| 1 | User `pic_gudang` muncul di dropdown teknisi halaman FOP Task (A1–A3) | Ada |
| 2 | User `pic_gudang` muncul di daftar teknisi Task (A4) & beban kerja (A5) | Ada |
| 3 | FOP meng-assign task ke PIC, PIC memulai & menyelesaikan task | Berhasil |
| 4 | PIC hanya melihat antrean survey/pemasangan di mana ia anggota tim (C1–C6) | Pelanggan cabang lain yang bukan tugasnya **tidak** tampil |
| 5 | PIC (scope Jetis) membuka gudang | Hanya gudang Jetis |
| 6 | PIC membuka gudang Siman lewat URL langsung | 403 / 404 |
| 7 | PIC menerima kiriman Pusat → Jetis | Berhasil |
| 8 | PIC issue barang ke teknisi Jetis | Berhasil |
| 9 | Hak bagian teknisi `pic_gudang` ⊇ hak role `teknisi` (hasil seeder) | Lolos — penjaga §6.1 |
| 10 | Tidak ada `where('code', 'teknisi')` / `hasRole('teknisi')` baru di `app/` (pola `PostTargetRenderedServerSideTest`) | Lolos — penjaga §8 |
| 11 | Simpan user `pic_gudang` dengan scope `all_pop` (E2, direvisi 2026-09-30b) | **Diterima** — scope `pic_gudang` gak lagi dibatasi ke `selected_pop` (§5.3.5) |
| 12 | PIC issue barang ke dirinya sendiri (H2a) | Ditolak |
| 13 | PIC reassign custody ke dirinya sendiri (H4) | Ditolak |
| 14 | POP Admin issue barang ke PIC di cabang ber-PIC | Berhasil |
| 15 | POP Admin issue barang ke teknisi non-PIC di cabang ber-PIC (H2b) | Ditolak |
| 16 | POP Admin terima kiriman di cabang ber-PIC (H5) | Ditolak |
| 17 | POP Admin terima kiriman & issue ke teknisi mana pun di cabang **tanpa** PIC (§7.2) | Berhasil — perilaku lama tetap |
| 18 | PIC dinonaktifkan (`status` ≠ active) → cabang dianggap tanpa PIC | POP Admin kembali bisa terima kiriman |
| 19 | PIC lapor rusak barang di custody-nya sendiri | Berhasil; baris laporan gudang bertanda "dilaporkan oleh pemegang sendiri" (H6) |
| 20 | PIC cabang Siman tidak dihitung sebagai PIC Jetis (H1) | POP Admin Jetis tetap boleh terima kiriman kalau Jetis tanpa PIC |
| 21 | PIC scope `all_pop`, ditunjuk PIC Jetis saja — buka gudang Jetis, issue dari Jetis (§5.3.4 skenario 2) | Berhasil |
| 22 | PIC scope `all_pop` yang sama di atas — coba issue dari gudang Siman (bukan cabang penunjukannya) (H2c) | Ditolak, walau scope-nya mengizinkan dia **melihat** Siman |
| 23 | PIC scope `all_pop` mengerjakan task Survey di cabang Siman (bukan cabang penunjukan gudangnya) | Berhasil — hak task gak dibatasi penunjukan PIC, cuma hak gudang (§5.3.6) |
| 24 | Tunjuk PIC baru untuk cabang yang **di luar** scope-nya (`selected_pop` sempit) (I4) | Ditolak validasi |
| 25 | Tunjuk PIC baru untuk cabang yang di dalam scope `all_pop`-nya (I4) | Diterima |
| 26 | User yang sudah jadi PIC cabang X mencoba ubah scope-nya jadi tidak lagi mencakup X (I5) | Ditolak validasi, pesan sebut cabang X |
| 27 | 1 user ditunjuk PIC di 2 cabang (Jetis + Siman) (§5.3.4 skenario 3) | Berhasil, kedua cabang tercatat di `warehouse_pop_pics` |
| 28 | 2 user berbeda sama-sama ditunjuk PIC di 1 cabang | Berhasil (§15, keputusan "boleh >1 PIC per cabang") |

Test lama yang wajib tetap hijau: `tests/Feature/Seeders/RolePermissionSeederTest.php`, `tests/Feature/RolePermissionTest.php`, test Warehouse*, FopTask*, Task*, CustomerVerification*. Jalankan per file (aturan repo: **bukan** full suite).

---

## 13. Urutan Implementasi

Setiap langkah bisa di-commit terpisah.

1. **Fondasi** — `Role::TECHNICIAN_CODES`, `Role::isTechnicianRole()`, `User::isTechnician()`, `User::scopeTechnicians()` (Kelompok D). Belum ada perilaku berubah karena `pic_gudang` belum ada.
2. **Refactor pencarian teknisi** — Kelompok A, B, C. Perilaku tetap sama untuk role `teknisi`. Jalankan test FopTask*, Task*, Warehouse*, CustomerVerification*.
3. **Test penjaga §12 no. 10** — mencegah string `'teknisi'` baru.
4. **Role & permission** — `RoleSeeder`, `RolePermissionSeeder` (§9.1–9.2), aturan scope (Kelompok E, sudah direvisi ke "bebas").
5. **Penunjukan PIC** — migration `warehouse_pop_pics`, model, relasi, UI, validasi 2 arah (Kelompok I).
6. **Aturan "tidak ke diri sendiri"** — H2(a), H4. Berlaku untuk semua user, aman dikerjakan terpisah.
7. **Pembagian PIC vs POP Admin** — H1, H2(b), H3, H5, H7, H8 (semuanya baca dari Kelompok I, bukan scope).
8. **Penanda laporan untuk Pusat** — H6.
9. **Kelompok F** — sesuai keputusan §15.
10. **Test skenario** §12.
11. **Migrasi data** §11 (manual lewat UI, di server).
12. **Dokumentasi** — update `docs/rbac/business-logic.md` (daftar role + aturan `TECHNICIAN_CODES`), `docs/warehouse/business-logic.md` (pembagian PIC vs POP Admin, tabel `warehouse_pop_pics`), `docs/plan/warehouse/kontrol-anti-manipulasi.md` (tambahan §7.3 & H6), `docs/database-schema.md` (tabel baru), `docs/TASKS.md`.

---

## 14. Yang TIDAK Berubah

- Satu user tetap **satu role** (`users.role_id`). Tidak ada multi-role.
- Role `teknisi` tetap seperti sekarang.
- **Permission** `pop_admin` tetap seperti sekarang; yang berubah hanya perilakunya di cabang yang sudah punya PIC aktif (§7.2).
- Cabang tanpa PIC berjalan persis seperti sekarang.
- **Scope (`user_role_scopes`) tidak berubah strukturnya** — tetap 3 `ScopeType`, tetap mekanisme yang sama. Yang baru cuma dipakainya di sisi lain (`warehouse_pop_pics`) untuk urusan penunjukan PIC.
- Tidak ada perubahan kolom di tabel yang sudah ada — hanya **1 tabel baru** (`warehouse_pop_pics`, §5.3.2).
- Alur Ticket ↔ FopTask ↔ Task tidak tersentuh — hanya daftar siapa yang boleh jadi anggota tim.
- Persetujuan permintaan stok & pengiriman antar gudang tetap di Pusat.

---

## 15. Keputusan

### 15.1 Sudah diputuskan

| # | Keputusan | Tanggal |
|---|---|---|
| 1 | Pakai solusi F (role global `pic_gudang` + `TECHNICIAN_CODES` + scope POP) — bukan role per cabang | 2026-09-29 |
| 2 | Tiap cabang punya PIC Gudang **dan** POP Admin → pembagian **Opsi B**: PIC pelaksana, POP Admin pemeriksa (§7) | 2026-09-30 |
| 3 | PIC **boleh** lapor rusak/hilang/opname (`warehouse_adjustment.create`), **dipantau Pusat** | 2026-09-30 |
| 4 | PIC **tidak boleh** issue/reassign barang ke dirinya sendiri — barang untuk PIC dikeluarkan POP Admin | 2026-09-30 (bagian dari Opsi B) |
| 5 | Penunjukan PIC **dipisah dari scope POP** lewat tabel baru `warehouse_pop_pics` — menjawab kasus teknisi scope global yang PIC cuma di 1 cabang (§5.3) | 2026-09-30 |
| 6 | Validasi penunjukan PIC berlaku **2 arah**: cabang PIC wajib dalam scope saat ditunjuk, dan scope gak boleh diubah sampai keluar dari cabang yang sudah dia PIC-i (§5.3.3) | 2026-09-30 |
| 7 | Satu cabang boleh punya **lebih dari satu** PIC, dan satu PIC boleh pegang **lebih dari satu** cabang — makanya dipilih tabel pivot, bukan kolom tunggal di `pops` (§5.3.2) | 2026-09-30 |

### 15.2 Masih terbuka — wajib dijawab sebelum implementasi

| # | Pertanyaan | Opsi | Saran |
|---|---|---|---|
| 1 | Nama role | "Teknisi PIC Gudang" / istilah kantor lain | "Teknisi PIC Gudang" |
| 2 | Masa transisi cabang tanpa PIC (§7.2) | Otomatis per cabang (POP Admin tetap bisa selama belum ada PIC) / cabut permission POP Admin sekaligus | **Otomatis per cabang** |
| 3 | Penanda "dilaporkan oleh pemegang sendiri" di laporan gudang (H6) | Pakai / tidak | **Pakai** — ini yang membuat pemantauan Pusat bermakna |
| 4 | Statistik dashboard "didaftarkan teknisi" (F1) | PIC ikut dihitung / tidak | **Ikut** |
| 5 | Admin boleh mengelola role `pic_gudang` di Role Matrix (F2) | Ya / Tidak (Owner saja) | **Tidak** |
| 6 | Lokasi UI penunjukan PIC (I3) | Panel tambahan di halaman Edit User / halaman terpisah "Kelola PIC Gudang per Cabang" (mis. di bawah menu Gudang) | **Halaman terpisah** — penunjukan PIC itu keputusan gudang, bukan atribut user biasa; lebih gampang dilihat "cabang X PIC-nya siapa saja" dalam satu layar |
| 7 | Dikerjakan di sprint mana | — | Menyentuh modul di luar sprint aktif — tentukan di `docs/TASKS.md` |
