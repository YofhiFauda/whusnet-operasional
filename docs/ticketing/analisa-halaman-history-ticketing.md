# Analisa — Halaman History Ticketing

Status: **Selesai diimplementasi — 2026-07-29**
Tanggal: 2026-07-29
Modul: Ticketing (`docs/ticketing/`)

> **Revisi 2026-07-30 (penting):** cakupan halaman dipersempit — History hanya
> menampung tiket yang sudah **lepas dari meja Ticketing** (Selesai, Dibatalkan,
> Assign FOP), dan tiket jalur FOP berhenti di status "Assign FOP". Lihat §2
> keputusan #2/#5 dan §10.

> Penyesuaian implementasi lain ada di §10 "Catatan Implementasi".

## 1. Latar Belakang

Sebelum sistem ini dipakai, Helpdesk mencatat seluruh keluhan pelanggan di satu
sheet Google Sheets ("Helpdesk Task Manager"). Satu sheet itu menampung **semua**
tiket — yang masih open maupun yang sudah solved — lengkap dengan kolom durasi
penanganan dan rata-rata `SOLVING TIME` di header.

Di aplikasi, isi sheet itu sekarang terpecah ke beberapa halaman yang masing-masing
sengaja dipersempit:

| Halaman | Cakupan |
|---|---|
| `/tickets/new` (panel List Task Ticketing) | hanya tiket aktif (`activeForWorksheet()`), dibatasi 30 terbaru |
| `/noc/worksheet` | hanya tiket di tangan NOC |
| `/tickets/selesai` | hanya bucket `selesai` |
| `/tickets/dibatalkan` | hanya bucket `dibatalkan` |

Tidak ada satu pun halaman yang bisa menjawab pertanyaan gaya spreadsheet:
"semua tiket bulan Juli, POP mana saja, siapa yang menyelesaikan, berapa rata-rata
lama penanganannya". Halaman History mengisi lubang itu.

## 2. Keputusan yang Sudah Diambil

| # | Pertanyaan | Keputusan |
|---|---|---|
| 1 | FopTask yang **tidak** lahir dari tiket (disubmit langsung dari `/fop-tasks`) ikut masuk? | **Tidak.** Task FOP dan Ticketing dua hal berbeda; Ticketing khusus melayani keluhan/issue pelanggan. Task FOP tetap dipantau di modulnya sendiri |
| 2 | Waktu selesai untuk tiket jalur FOP diambil dari mana? | **REVISI 2026-07-30:** dari **waktu penyerahan ke FOP**, bukan `tasks.completed_at`. Keputusan awal (waktu teknisi lapor) dibatalkan karena bertabrakan dengan keputusan #5: kalau statusnya berhenti di "Assign FOP", menampilkan waktu selesai lapangan jadi menyesatkan. Durasi lapangan diukur di modul FOP (SLA pengerjaan) |
| 3 | Siapa yang boleh membuka halaman ini? | Fleksibel lewat Role Matrix. Default: **Owner, NOC, Helpdesk** (Admin otomatis tercakup `tickets.*`) |
| 4 | Kolom DESA diambil dari relasi atau di-snapshot? | **Di-snapshot saat tiket dibuat**, sama seperti alamat/HP/paket. History harus akurat secara historis walaupun pelanggan pindah desa |
| 5 | Tiket yang masih dikerjakan ikut masuk History? | **Tidak** (revisi 2026-07-30). Hanya tiket yang sudah lepas dari meja Ticketing: Selesai, Dibatalkan, Assign FOP. Tiket `open` di tangan Helpdesk/NOC adalah pekerjaan berjalan — rumahnya Worksheet Helpdesk & Worksheet NOC. Kalau ikut ditampilkan, History jadi duplikat antrean kerja |
| 6 | Progres lapangan tiket jalur FOP dicerminkan di History? | **Tidak.** Semua tiket `handler=fop` berlabel **"Assign FOP"**, apa pun status FopTask-nya (Terjadwal/In Progress/Selesai/Dibatalkan/FopTask dihapus). Tracking pengerjaan lapangan dibaca di `/fop-tasks` |

## 3. Cakupan Data — Tiket Mana yang Masuk

**Hanya tiket yang sudah LEPAS dari meja Ticketing.** Akar query:

```php
Ticket::query()->applyUserScope()->where(function ($scope) {
    $scope->whereIn('status', ['closed', 'cancelled'])
          ->orWhere('handler', 'fop');
});
```

**Jangan** pakai `scopeActiveForWorksheet()` (justru kebalikannya) maupun
`scopeInBucket()` (bucket `selesai`/`dibatalkan` ikut menarik status FopTask,
padahal History berhenti di "Assign FOP").

| Kondisi | Cara kenali | Masuk? |
|---|---|---|
| Selesai — ditutup Helpdesk/NOC sendiri | `handler` ≠ fop, `status=closed` | ✅ label **Selesai (Helpdesk/NOC)** |
| Dibatalkan pra-FOP | `handler` ≠ fop, `status=cancelled` | ✅ label **Dibatalkan (Helpdesk/NOC)** |
| Diserahkan ke FOP | `handler=fop` (apa pun status FopTask, termasuk selesai/dibatalkan/orphan) | ✅ label **Assign FOP** |
| Masih di meja Helpdesk | `handler=helpdesk`, `status=open` | ❌ ada di Worksheet Helpdesk |
| Sedang diproses NOC | `handler=noc`, `status=open` | ❌ ada di Worksheet NOC |

Konsekuensi yang disengaja:

- **Tiket bisa KELUAR lagi dari History.** NOC mengembalikan tiket ke Helpdesk
  (`returnToHelpdesk()`) ⇒ jadi pekerjaan berjalan lagi ⇒ hilang dari History.
  Itu benar: tiketnya kembali ke meja.
- **Orphan (`handler=fop`, `fop_task_id` NULL) tetap "Assign FOP"** — bukan
  "Terputus". History tidak mencerminkan apa pun yang terjadi setelah
  penyerahan, termasuk FopTask yang dihapus.
- Tiket yang dikembalikan ke Helpdesk **bukan baris baru** — tetap satu tiket,
  jejaknya di `ticket_histories`.

## 4. Perubahan Skema

### 4.1 `tickets.resolved_at` (baru)

`resolved_at` = **kapan tiket lepas dari meja Ticketing**. Satu kolom, dua arti
tergantung jalurnya:

| Jalur | Arti | Penulis |
|---|---|---|
| Ditutup Helpdesk/NOC sendiri | beneran selesai | `TicketService::close()` |
| Diserahkan ke FOP | waktu penyerahan | `TicketService::escalateToFop()` (dan `create()` untuk submit langsung dari halaman Task FOP) |
| Dibatalkan | — tetap NULL | dibatalkan bukan diselesaikan; supaya tidak menyeret rata-rata durasi |

**Progres lapangan TIDAK mengubah nilai ini.** `FopTaskObserver` sengaja tidak
menyentuh `resolved_at` — sempat begitu (diisi dari `tasks.completed_at` saat
FopTask selesai) lalu dibatalkan bersama revisi keputusan #2. Jejak larangan itu
ditulis eksplisit di observer supaya tidak dipasang ulang.

Nilainya di-denormalisasi ke kolom (nullable) + index `(pop_id, resolved_at)`
karena halaman ini butuh sortir & rata-rata durasi di atas ribuan baris.

Backfill bertahap:
- `2026_07_29_000001` — jalur internal dari `ticket_histories` (action `diselesaikan`).
- `2026_07_29_000004` — **memperbaiki** jalur FOP: dari `tasks.completed_at`
  (aturan lama) ke `happened_at` baris riwayat `dieskalasi` → `fop`, dengan
  fallback `created_at` untuk tiket yang lahir langsung di tangan FOP.

Durasi **tidak** disimpan — accessor turunan `resolved_at − created_at`
(`Ticket::resolutionMinutes()` / `solvingTimeLabel()`).

### 4.2 `tickets.customer_village` (baru)

Snapshot nama desa saat tiket dibuat, diisi `TicketService::create()` bersama
snapshot lain yang sudah ada (`customer_address`, `customer_phone`,
`customer_package`, `customer_odp`, `customer_device`, koordinat).

Alasan tidak join ke `customer.village`: pelanggan bisa pindah desa, dan history
yang ikut berubah retroaktif bikin laporan bulan lalu jadi bohong. Migrasi
membackfill tiket lama dari `customers.village_id` — nilai perkiraan terbaik yang
tersedia, dan itu sudah dicatat di sini supaya tidak dikira data asli.

## 5. Peta Kolom Excel → Sumber Data

| Kolom Excel | Sumber |
|---|---|
| DATE | `tickets.created_at` |
| — | `tickets.ticket_number` (tidak ada di Excel, wajib ada di sini) |
| INPUT BY | `creator` (`created_by`) |
| NAMA/CID | `customer_name` + `customer->display_id` |
| HP/CONTACT | `customer_phone` (snapshot) |
| DESA | `customer_village` (snapshot — §4.2) |
| ISSUE/ADUAN | `detail_keluhan` |
| KATEGORI | `issueCategory.name` |
| PAKET | `customer_package` (snapshot) |
| STATUS | `TicketHistoryController::statusLabelFor()` — **3 nilai saja**: Selesai (Helpdesk/NOC), Dibatalkan (Helpdesk/NOC), Assign FOP. SENGAJA bukan `Ticket::statusLabel()` yang menurunkan label dari status FopTask |
| OLEH (dulu SOLVED BY) | `TicketHistoryController::actorLabelFor()` — menyesuaikan hasil akhir: yang **menyelesaikan** (`closedBy()`), yang **membatalkan** (`cancelledBy()`), atau yang **mengirim ke FOP** (`escalatedToFopBy()`) |
| SELESAI / DISERAHKAN (dulu SOLVED TIME) | `resolved_at` (§4.1) |
| DURASI TICKETING (dulu SOLVING TIME) | accessor `resolved_at − created_at` — lama di meja Ticketing, **bukan** sampai teknisi selesai |
| ~~TIM~~ | **Dihapus 2026-07-30.** Tim teknisi adalah data pengerjaan lapangan; konsisten dengan keputusan #6, itu dibaca di `/fop-tasks`. Menghapusnya juga membuang eager-load `fopTask.technicians` dari halaman ini |

## 6. RBAC

- Feature baru `tickets.history` (sub-feature dari `tickets`) di `TicketFeatureSeeder`.
- `config/rbac.php` → `'tickets.history' => [VIEW, EXPORT]` + deskripsi permission.
- `RolePermissionSeeder` → `tickets.history.view` & `tickets.history.export` untuk
  **owner, noc, helpdesk**. Admin sudah tercakup wildcard `tickets.*`. Atasan
  sengaja tidak diberi default — bisa dinyalakan sendiri lewat Role Matrix.
- Halaman ini **tidak boleh** menumpang `tickets.view` generik (aturan #8 bagian
  Sinkronisasi Ticket di CLAUDE.md).
- Query wajib lewat `applyUserScope()` — halaman lintas-POP tanpa scope = kebocoran
  data antar cabang.

## 7. File yang Disentuh

| File | Isi |
|---|---|
| `database/migrations/…_add_resolved_at_to_tickets_table.php` | kolom + index + backfill |
| `database/migrations/…_add_customer_village_to_tickets_table.php` | snapshot desa + backfill |
| `app/Services/TicketService.php` | isi `customer_village` di `create()`, `resolved_at` di `close()` |
| `app/Observers/FopTaskObserver.php` | isi `resolved_at` saat FopTask → `selesai` |
| `app/Models/Ticket.php` | scope filter history + accessor durasi + label "Terputus" |
| `app/Http/Controllers/TicketHistoryController.php` | **baru** — bukan turunan `TicketArchiveController` (kelas itu terikat satu bucket) |
| `routes/web.php` | `/tickets/history` **di atas** route dinamis `{ticket}` |
| `resources/views/tickets/history.blade.php` | tabel + filter + ringkasan |
| `config/rbac.php`, `TicketFeatureSeeder`, `RolePermissionSeeder` | permission |
| `resources/views/layouts/app.blade.php` | menu sidebar (di-gate permission) |
| `tests/Feature/TicketHistoryTest.php` | **baru** |

## 8. UI

Tabel gaya spreadsheet dengan kolom §5, ditambah:

- Filter: rentang tanggal, POP, kategori issue, status (3 nilai di atas),
  handler, pembuat, pencarian bebas (nomor tiket / nama / CID / desa / keluhan).
- Header ringkasan: total + **Selesai Helpdesk/NOC** + **Assign FOP** +
  **Dibatalkan** + **rata-rata durasi di Ticketing** (meniru `RATA² SOLVING TIME`
  di sheet lama, tapi dengan arti yang dipertegas: sampai selesai/diserahkan,
  bukan sampai lapangan tuntas).
- Ekspor lewat `spatie/simple-excel` (sudah jadi dependensi) di
  `tickets.history.export`.
- Paginasi server-side; jangan tarik semua baris ke memori.

`/tickets/selesai` dan `/tickets/dibatalkan` **tetap dipertahankan** — permission-nya
terpisah dan sebagian role memang hanya boleh melihat salah satunya. History adalah
supersetnya, bukan penggantinya.

## 9. Cakupan Test (`TicketHistoryTest`)

1. Yang masuk (selesai/dibatalkan/assign FOP) muncul; yang masih dikerjakan
   (open di Helpdesk/NOC) **tidak** muncul.
2. Tiket yang dikembalikan ke Helpdesk **keluar lagi** dari History.
3. Tiket jalur FOP berlabel "Assign FOP" untuk **semua** status FopTask
   (Terjadwal/In Progress/Selesai/Dibatalkan) — label progres tidak bocor ke sini.
4. Orphan juga berlabel "Assign FOP", bukan "Terputus".
5. Filter status 3 nilai memisahkan ketiganya dengan benar.
6. POP scope: user `selected_pop` tidak melihat tiket POP lain.
7. RBAC: teknisi 403; helpdesk & noc 200; permission terpisah dari `tickets.view`.
8. `resolved_at`: terisi saat close internal; = **waktu penyerahan** untuk jalur
   FOP; **tidak berubah** saat FopTask selesai/dibatalkan; NULL untuk tiket
   dibatalkan.
9. `customer_village` ter-snapshot dan tidak ikut berubah saat pelanggan pindah desa.
10. Durasi dihitung benar; NULL (bukan 0) selama belum ada titik lepas.
11. Ringkasan menghitung per hasil akhir; tiket yang masih dikerjakan tidak ikut.
12. Filter rentang tanggal & ekspor menghormati POP scope.

Hasil: **20 test lulus** (`TicketHistoryTest`), seluruh suite 759 lulus
(1 gagal pre-existing di `CustomerDocumentTest`, tidak terkait).

## 10. Catatan Implementasi (2026-07-29)

Tiga hal yang berbeda / bertambah dari rancangan di atas:

**1. `RolePermissionSeeder` tidak jadi diubah.** Owner sudah punya `*`, sedangkan
NOC, Helpdesk, dan Admin sudah punya wildcard fitur `tickets.*` — dan
`EffectiveAccessService::userCan()` meloloskan `tickets.history.view` lewat
wildcard itu (aturan #3 resolusi permission). Menambahkan entri eksplisit cuma
bikin daftar permission ganda yang gampang menyimpang. Atasan tetap TIDAK
kebagian (daftarnya eksplisit, bukan wildcard) — sesuai keputusan #3, dan bisa
dinyalakan sendiri lewat Role Matrix.

**2. `resolved_at` dikosongkan lagi kalau FopTask keluar dari status `selesai`.**
Tidak ada di rancangan, tapi tanpa ini task yang dibuka ulang (atau dibatalkan
setelah sempat selesai) meninggalkan waktu selesai basi yang ikut terhitung di
rata-rata solving time. Ditulis di penulis yang sama (`FopTaskObserver`), dijaga
test `resolved_at_cleared_when_fop_task_leaves_selesai`.

**3. Fallback `now()` untuk FopTask selesai tanpa Task eksekusi.**
`escalateToFop()` cuma membuat FopTask berstatus Draft — `Task` teknisi baru
lahir ketika FOP menugaskan teknisi. Jadi FopTask bisa saja ditutup manual dari
`/fop-tasks` tanpa pernah punya `task_id`. Kasus itu jatuh ke `now()`; jalur
normal (ada Task) tetap memakai `tasks.completed_at` sesuai keputusan #2.

Catatan tambahan: `POP` ikut dijadikan kolom tampilan & ekspor (tidak ada di
sheet lama) karena halaman ini lintas-cabang — tanpa kolom itu, rekap gabungan
beberapa POP tidak bisa dibaca.

**4. Revisi cakupan & sumber waktu (2026-07-30).** Rancangan awal memasukkan
SEMUA tiket termasuk yang masih dikerjakan, dan mengambil waktu selesai jalur FOP
dari `tasks.completed_at`. Dua-duanya dibatalkan atas koreksi pemilik produk:
History adalah arsip, bukan cermin antrean kerja, dan Ticketing berhenti di titik
penyerahan ke FOP. Yang berubah di kode: akar query (§3), `statusLabelFor()` +
`statusBadgeFor()` di controller, penulis `resolved_at` pindah dari
`FopTaskObserver` ke `TicketService::escalateToFop()`, filter status jadi 3 nilai,
ringkasan header jadi per hasil akhir, dan migrasi `2026_07_29_000004` untuk
memperbaiki data yang sudah di-backfill dengan aturan lama.

**5. Kolom TIM dihapus & SOLVED BY jadi "Oleh" (2026-07-30).** Koreksi lanjutan
pemilik produk: kolom TIM menampilkan teknisi, padahal pengerjaan lapangan bukan
isi halaman ini. Dan untuk tiket Assign FOP, yang berguna dibaca bukan "siapa
yang menyelesaikan" (kosong, karena dari sisi Ticketing tiket itu tidak
diselesaikan) melainkan **siapa yang mengirimnya ke FOP**. Ditambah
`Ticket::cancelledBy()` supaya tiket dibatalkan juga punya aktor.

**Nama kolom `resolved_at` dipertahankan** meski artinya sekarang lebih luas
("lepas dari meja Ticketing", termasuk penyerahan ke FOP). Rename dianggap tidak
sebanding dengan churn-nya; artinya didokumentasikan di §4.1, di docblock
`Ticket::resolutionMinutes()`, dan di komentar kedua penulisnya.
