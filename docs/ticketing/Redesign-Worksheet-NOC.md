# ADHOC-09 — Redesign Worksheet NOC jadi Tabel Padat + Tab + Cari + Filter

## Context

`/noc/worksheet` sekarang hanya daftar kartu vertikal (`resources/views/noc/worksheet.blade.php`):
satu tiket makan ~120px tinggi, tombol aksi menempel di tiap kartu, dan **tidak ada
pencarian/filter apa pun** (`NocWorksheetController::index()` cuma
`where handler=noc, status=open` → `paginate(20)`). Begitu antrean NOC puluhan tiket,
halaman jadi scroll panjang dan NOC tidak bisa menemukan tiket tertentu.

Tujuan: NOC bisa membaca banyak tiket sekaligus — satu baris = satu tiket dengan
informasi yang dibutuhkan NOC — plus pencarian, filter, dan dua tab:

- **Tiket Masuk** — tiket yang di-assign ke NOC oleh Helpdesk (`handler=noc`, `status=open`). Bisa diaksi.
- **Assign FOP** — tiket yang sudah NOC teruskan ke FOP. Read-only; progres lapangan tetap dibaca di `/fop-tasks`.

Acuan tampilan: History Ticketing (`resources/views/tickets/history.blade.php`, tabel
padat + bar filter GET) dan tab bercounter Worksheet Helpdesk (ADHOC-08,
`resources/views/tickets/create.blade.php:462-481`).

**Batas keras:** ini BUKAN penghidupan Pending NOC / Oncheck NOC (ADHOC-06). Tab kedua
diturunkan dari data yang sudah ada (`handler=fop` + jejak `ticket_histories`), TIDAK ada
kolom baru, TIDAK ada aksi "ambil/terima", TIDAK ada migrasi. Gerbang tetap satu
permission `noc_worksheet.view` — dua permission tab lama
(`noc_worksheet.masuk.view`/`.diproses.view`) tetap pensiun, jangan dihidupkan.

## Perubahan

### 1. `app/Http/Controllers/NocWorksheetController.php` (rewrite `index()`)

Pola query mengikuti `TicketHistoryController` (`baseQuery()` / `activeFilters()` /
`statusOptions()`) — bukan pola baru:

- `index(Request $request)`; tab dari `?tab=`, default `masuk`, divalidasi ke
  `['masuk', 'assign_fop']` (nilai asing → `masuk`).
- `private function baseQuery(Request $request, string $tab): Builder`
  - **selalu** `Ticket::query()->applyUserScope()` (POP scope wajib).
  - `masuk` → `where('handler', TicketHandler::NOC)->where('status', TicketHandlingStatus::OPEN)`
  - `assign_fop` → `where('handler', TicketHandler::FOP)` **AND** `whereHas('histories', fn ($h) =>
    $h->where('action', TicketHistoryAction::DIESKALASI)->where('to_status', TicketHandler::NOC->value))`
    → "pernah lewat meja NOC". Sengaja pakai jejak riwayat, bukan kolom baru; tiket yang
    Helpdesk kirim langsung ke FOP tidak muncul di worksheet NOC.
  - Filter (semua opsional, semua `$request->query()`): `q` (ticket_number,
    detail_keluhan, customer_name, customer_village, + `whereHas customer` full_name/cid/
    customer_code — copy pola `TicketHistoryController::baseQuery():137-149`), `pop_id`,
    `issue_category_id`, `type`, `priority` (validasi lewat `FopTaskPriority::tryFrom`),
    `created_by`, `date_from`/`date_to` atas `created_at`.
- `tabCounts` = 2× `count()` atas baseQuery per-tab **dengan filter yang sama ikut
  diterapkan** (biar angka badge konsisten dengan isi tabel).
- Eager-load sama seperti sekarang + `histories.actor` (dipakai
  `escalatedToFopBy()` untuk kolom "Dikirim oleh" di tab Assign FOP). `paginate(50)->withQueryString()`.
- Kirim ke view: `tickets`, `tab`, `tabCounts`, `filters`, `popOptions`
  (`Pop::forUser(auth()->user())`), `categoryOptions` (`TicketIssueCategory`),
  `typeOptions` (`TaskType::ticketOptions()`), `priorityOptions` (`FopTaskPriority::cases()`),
  `creatorOptions` (user yang pernah bikin tiket dalam scope — pola
  `TicketHistoryController::creatorOptions():271-276`).
- `abort_unless(...'noc_worksheet.view')` tetap di baris pertama.
- Update docblock kelas: tab sekarang **dua**, dan tulis eksplisit bahwa ini bukan
  Pending NOC yang balik.

### 2. `resources/views/noc/worksheet.blade.php` (rewrite)

- Header + badge total (dipertahankan).
- **Tab**: dua link `<a href="?tab=...">` yang membawa query filter aktif
  (`request()->query()` merge), masing-masing badge counter dari `$tabCounts`. Gaya
  segmented control mengikuti `tickets/create.blade.php:472-484`.
- **Bar filter**: `<form method="GET">` seperti `tickets/history.blade.php:80-170` —
  hidden input `tab`, field Cari, Dari/Sampai Tanggal, POP, Kategori, Tipe, Prioritas,
  Dikirim oleh + tombol Terapkan/Reset. Reuse class input yang sama (jangan bikin gaya baru).
- **Tabel padat** `overflow-x-auto` (pola history), kolom:
  `Masuk (tanggal) | Tiket | Nama / CID | HP | Desa | POP | Aduan (truncate) | Kategori | Prioritas | Umur`.
  - Tab `masuk`: kolom **Umur** = `created_at->diffForHumans()` / jam-menit sejak masuk,
    diwarnai (>24 jam merah, >8 jam amber) — sinyal antrean menumpuk.
  - Tab `assign_fop`: kolom terakhir jadi **Diserahkan** (`resolved_at`) +
    **Dikirim oleh** (`$ticket->escalatedToFopBy()?->name`), badge status statis "Assign FOP"
    (reuse `TicketHistoryController::statusLabelFor()`/`statusBadgeFor()` — jangan tulis label baru).
- **Aksi lewat baris terpilih (drawer), bukan kolom tombol:**
  - Halaman dibungkus `x-data="nocWorksheet()"` dengan `selected` (object) + `select(t)`
    yang `$dispatch('open-drawer', 'noc-ticket')`.
  - Tiap `<tr>` `@click="select({...})"` — payload JSON per baris berisi field yang
    ditampilkan drawer **plus** `actions` (hasil `$ticket->actionFlagsFor(auth()->user())`)
    dan URL aksi (`route('tickets.close'|'tickets.escalate'|'tickets.return-to-helpdesk'|'tickets.cancel', $ticket)`)
    + `route('tickets.show', $ticket)`. Baris tetap punya link nomor tiket ke halaman detail
    (`@click.stop`) supaya navigasi langsung tidak hilang.
  - Drawer pakai komponen yang sudah ada: `<x-ui.drawer name="noc-ticket" title="Detail Tiket" maxWidth="lg">`
    (`resources/views/components/ui/drawer.blade.php`, saat ini belum dipakai di mana pun —
    slot-nya dirender di dalam scope Alpine halaman jadi `x-text="selected?.…"` jalan).
    Isi: identitas pelanggan, POP/desa/ODP/paket, keluhan penuh, kategori, prioritas,
    pengirim, waktu. Slot `footer` = tombol Selesai / Assign FOP / Kembalikan / Batalkan,
    masing-masing `x-show="selected?.actions.can_*"`.
  - JS aksi: **pindahkan apa adanya** `performTicketAction()` + `confirmTicketRowAction()`
    dari view lama (baris 107-164) — tetap `fetch` POST ke endpoint `TicketController` yang
    sudah ada, tetap lewat `window.confirmTicketAction` (`@include('tickets.partials.action-dialog')`).
    Bedanya: setelah sukses, tutup drawer (`close-drawer`) lalu hapus `<tr>` terkait
    (`data-ticket-row="{{ $ticket->id }}"` dipertahankan sebagai anchor). Tab `assign_fop`
    tidak merender tombol aksi apa pun.
- Empty state per tab (pesan beda: "Belum ada ticket yang diproses NOC" vs "Belum ada tiket
  yang diteruskan NOC ke FOP") + `{{ $tickets->links() }}`.

### 3. `tests/Feature/NocWorksheetTest.php`

Test yang harus **diubah** (jangan dibiarkan bikin false-fail):
- `test_worksheet_has_no_tab_navigation_anymore` → rename jadi
  `test_worksheet_has_no_pending_noc_window` dan pertahankan intinya:
  `assertDontSee('Oncheck NOC')`, `assertDontSee('Pending NOC')`, dan tidak ada
  `tickets.oncheck-noc`. Assertion "tidak ada tab" dihapus karena tab sekarang memang ada
  (dan bukan tab Pending).
- `test_worksheet_shows_close_and_escalate_buttons` → URL aksi sekarang ada di payload baris,
  assertion `assertSee(route(...), false)` tetap valid; sesuaikan nama jadi menyebut drawer.

Test **baru**:
- tab default = `masuk`, hanya memuat `handler=noc, status=open`;
- `?tab=assign_fop` memuat tiket yang NOC eskalasi ke FOP, dan **tidak** memuat tiket yang
  Helpdesk kirim langsung ke FOP tanpa lewat NOC;
- tiket tab `masuk` tidak muncul di tab `assign_fop` dan sebaliknya;
- `?q=` menemukan tiket via nomor tiket, nama pelanggan, dan desa; `?q=` yang tidak cocok → kosong;
- filter `pop_id`, `issue_category_id`, `priority`, `date_from`/`date_to` masing-masing menyaring;
- **POP scope**: user NOC dengan `selected_pop` tidak melihat tiket POP lain di kedua tab;
- `?tab=ngawur` → jatuh ke `masuk` (bukan 500);
- tab `assign_fop` tidak merender URL aksi (`tickets.close`/`tickets.cancel`);
- counter kedua tab ikut menyesuaikan saat filter aktif.

### 4. Dokumentasi (wajib per alur kerja repo)

- `docs/TASKS.md` — baris ADHOC-09 di tabel Ad-Hoc + blok detail singkat; catat eksplisit
  "bukan pembalikan ADHOC-06".
- `docs/ticketing/user-flow.md` + `business-logic.md` + `README.md` — bagian yang menyebut
  "Worksheet NOC satu halaman tanpa tab" diperbarui: dua tab (Tiket Masuk / Assign FOP),
  tab kedua turunan data, bukan window baru.
- `CLAUDE.md` poin 3 bagian sinkronisasi: tambahkan satu kalimat bahwa tab Worksheet NOC ≠ Pending NOC.

## File yang disentuh

| File | Aksi |
|---|---|
| `app/Http/Controllers/NocWorksheetController.php` | rewrite `index()` + helper filter/tab/counter |
| `resources/views/noc/worksheet.blade.php` | rewrite: tab, bar filter, tabel padat, drawer aksi |
| `tests/Feature/NocWorksheetTest.php` | 2 test diperbarui + ~10 test baru |
| `docs/TASKS.md`, `docs/ticketing/{README,business-logic,user-flow}.md`, `CLAUDE.md` | catatan tab baru |

Tanpa migrasi, tanpa kolom baru, tanpa route baru (`/noc/worksheet` + dua redirect legacy tetap),
tanpa perubahan `config/rbac.php`/seeder, tanpa menyentuh `TicketService`/`TicketController`.

## Verifikasi

1. `php artisan test --compact tests/Feature/NocWorksheetTest.php`
2. Regresi tetangga: `php artisan test --compact --filter="TicketingRbacTest|TicketHistory|NocDashboardTest"`
3. `vendor/bin/pint --dirty --format agent`
4. Manual (`composer dev`, login role `noc`): buka `/noc/worksheet` →
   - tab Tiket Masuk terisi, counter cocok; klik baris → drawer terbuka berisi detail benar;
   - tombol Assign FOP di drawer → toast sukses, drawer tutup, baris hilang, tiket pindah ke tab Assign FOP setelah refresh;
   - isi Cari + POP + Prioritas → hasil tersaring, counter kedua tab ikut turun, paginasi
     tetap membawa query (`withQueryString`);
   - tab Assign FOP: tidak ada tombol aksi;
   - cek light & dark mode, dan lebar layar tablet (tabel scroll sendiri, halaman tidak melebar).


## REVISI DAN PERBAIKAN

### 1. Kesetaraan Konten Drawer vs Halaman Detail Ticket (`ticket/{id}`)

**Permasalahan** : Permasalahanya adalah Kontent yang ada di Detail Drawer belum sama seperti pada halaman ticket/{id}

Ada di dua-duanya: nomor tiket + prioritas, status badge, dibuat oleh + waktu, kategori issue, snapshot pelanggan (nama, CID, HP, paket, alamat, POP, ODP, perangkat), keluhan + catatan teknis, panel aksi, riwayat Ticketing, daftar lampiran.

Ada di halaman, TIDAK ada di drawer:

┌──────────────────────────────────────────────────┬────────────────────────────────────────────┐
│                       Isi                        │          Sumber di show.blade.php          │
├──────────────────────────────────────────────────┼────────────────────────────────────────────┤
│ Riwayat Task FOP (jejak status lapangan +        │ fopTask.statusHistories —                  │
│ pelaku)                                          │ show.blade.php:347                         │
├──────────────────────────────────────────────────┼────────────────────────────────────────────┤
│ Teknisi yang ditugaskan + tombol "Buka Task FOP" │ fopTask.technicians —                      │
│                                                  │ show.blade.php:86-110                      │
├──────────────────────────────────────────────────┼────────────────────────────────────────────┤
│ Metadata lampiran: ukuran file + pengunggah      │ humanSize(), uploader->name —              │
│                                                  │ show.blade.php:379                         │
├──────────────────────────────────────────────────┼────────────────────────────────────────────┤
│ Label tipe panjang (MTN — Maintenance), sekarang │ type->label()                              │
│  drawer cuma MTN                                 │                                            │
├──────────────────────────────────────────────────┼────────────────────────────────────────────┤
│ Koordinat GPS mentah (drawer cuma link Maps)     │ show.blade.php:262                         │
├──────────────────────────────────────────────────┼────────────────────────────────────────────┤
│ Ikon lampiran per tipe (gambar vs dokumen)       │ drawer kirim is_image tapi belum dipakai   │
│                                                  │ di markup                                  │
└──────────────────────────────────────────────────┴────────────────────────────────────────────┘

Ada di drawer, tidak di halaman: resolved_at + durasi di meja Ticketing (solving_time).

Jadi drawer masih kalah di satu hal yang penting buat NOC: tiket yang sudah di FOP tak kelihatan progres lapangannya — padahal itu justru yang dibaca dari tab Assign FOP.

Mau saya samakan? Yang saya usulkan tambah ke drawer: riwayat Task FOP + teknisi + link Task FOP, metadata lampiran, label tipe panjang, ikon per tipe file. Semua dari data yang sudah ada, cukup tambah field di detailJson() + blok di partial — tanpa endpoint atau query baru selain eager-load fopTask.technicians & fopTask.statusHistories.

### 2. Posisi Layout Drawer Terhadap Navbar Utama

**Permasalahan** : Header atau bagian atas dari Drawer saat ini berada di belakang Navbar utama, yang menyebabkan area Navbar menjadi transparan dan tumpang tindih dengan header Drawer.

**Solusi & Perbaikan Layout** :
- **Top Offset & Position**: Sesuaikan posisi container Drawer (`<x-ui.drawer>`) agar bagian atasnya dimulai dari bawah Navbar (misalnya dengan menerapkan `top-[var(--navbar-height,4rem)]` atau `top-16` serta penyesuaian `fixed top-16 bottom-0 right-0` / `pt-16`).
- **Z-Index Layering**: Atur hirarki `z-index` agar Navbar utama berada di atas backdrop/header Drawer (atau Drawer dimuat tepat di bawah koordinat Y Navbar), sehingga Navbar tidak tertutup atau menjadi transparan secara visual saat Drawer aktif.