# Rancangan — Master Issue/Kategori Keluhan Ticketing + Redesign Create Service Ticket

Dokumen turunan dari `docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD` (kebutuhan bisnis) dan template
`create_service_ticket.html` (referensi visual). Dokumen ini rancangan teknis final setelah 3
keputusan dikonfirmasi user:

1. Data kategori issue = **dummy/contoh**, bukan data final — wajib direvisi user/NOC sebelum go-live.
2. Master Issue **masuk RBAC** (permission terpisah, bukan bebas diedit siapa saja).
3. Pekerjaan **dipecah jadi 3 task terpisah** (bukan 1 task raksasa) — lihat bagian F.

---

## A. Skema Database

### Migration 1 — `ticket_issue_categories`

```
id
name                 varchar   -- "Lemot", "LOS", "Backbone CUT", "ODP LOS"
default_priority     varchar   -- value dari FopTaskPriority enum (low/medium/high/urgent)
sla_source           enum('paket','prioritas')  default 'prioritas'
is_active            boolean   default true
created_at / updated_at
```

`sla_source` cuma flag klasifikasi/pelaporan — **tidak** mengubah mesin SLA. SLA Pengerjaan tetap
di `TaskService`/`PackageSlaSetting`, Handling SLA tetap di `fop_tasks.handling_sla_hours`
([[project_sla_pengerjaan_vs_handling_sla]]). Kalau nanti mau master issue override jam SLA,
itu fase terpisah, di luar rancangan ini.

### Migration 2 — `tickets` tambah kolom

```
issue_category_id  FK nullable → ticket_issue_categories.id, nullOnDelete
```

`detail_keluhan` (kolom existing, required) **tidak diganti** — perannya jadi keterangan
tambahan/narasi pelanggan. `issue_category_id` klasifikasi terstruktur, nullable (mendukung
"bisa diisi manual" sesuai `RANCANGAN_WORKSHEET_TICKETING.MD`).

**Kenapa nullable, bukan hard delete kategori**: kategori yang sudah dipakai tiket lama tidak
boleh hilang jejaknya kalau di-nonaktifkan — pola sama dengan `InternetPackage` (toggle
`is_active`, tidak ada route delete keras).

### Data Dummy (Seeder — WAJIB direview user sebelum production)

| name | default_priority | sla_source |
|---|---|---|
| Lemot | low | prioritas |
| LOS | medium | prioritas |
| Backbone CUT | high | prioritas |
| ODP LOS | high | prioritas |

Ditandai jelas di seeder (`TicketIssueCategorySeeder.php`) sebagai `// DATA CONTOH — ganti sebelum go-live`.

---

## B. RBAC

### Feature baru

Ikuti pola `TicketFeatureSeeder.php` (feature ticketing didefinisikan terpisah dari
`FeatureSeeder.php` utama) — bikin `TicketIssueCategoryFeatureSeeder.php`:

```
feature_code : ticket_issue_categories
name         : Master Issue/Kategori Keluhan
type         : ROOT
sort_order   : 9   (setelah tickets = 8)
```

### Actions (`config/rbac.php` → `allowed_actions`)

```php
'ticket_issue_categories' => [
    ActionCode::VIEW,
    ActionCode::CREATE,
    ActionCode::UPDATE,
    ActionCode::DELETE,   // digenerate, TIDAK dipasang ke route (toggle status, bukan hard delete — sama pola packages)
],
```

Permission hasil generate: `ticket_issue_categories.view`, `.create`, `.update`, `.delete`.

### Role Matrix (`RolePermissionSeeder.php`)

| Role | Permission |
|---|---|
| owner | `*` (bawaan — otomatis dapat `create`/`update`/`delete`) |
| admin, atasan, noc, helpdesk, pop_admin | tidak diberi permission `ticket_issue_categories.*` untuk sementara |
| fop, teknisi, sales | tidak ada akses |

**Sementara: create/update/delete cuma owner** (lewat wildcard `*`, bukan permission eksplisit).
User akan nambah sendiri ke role lain lewat UI RBAC nanti setelah modul jalan — jangan generate
matrix `admin`/`noc`/dst di `RolePermissionSeeder.php` untuk feature ini dulu. Kalau nanti mau
dibuka ke role lain, cukup assign lewat UI Role Management (tidak perlu ubah seeder), sesuai
[[project_context]] pola RBAC dinamis.

**Catatan penting**: dropdown kategori di form Create Service Ticket **tidak butuh** permission
`ticket_issue_categories.view` terpisah — daftar kategori aktif di-load lewat controller
`tickets.create` yang sudah digerbangi `tickets.create`. Permission `ticket_issue_categories.*`
di atas khusus untuk halaman **CRUD master**-nya (Master Data → Master Issue).

### Prosedur wajib (per `docs/rbac/business-logic.md`)

1. Edit `config/rbac.php` (tambah `allowed_actions`).
2. `php artisan rbac:generate-permissions`.
3. Assign ke role — **additive** (`syncWithoutDetaching`), bukan re-run `RolePermissionSeeder`
   mentah-mentah kalau environment sudah dikustom lewat UI.
4. `EffectiveAccessService::clearCache()` / `Cache::flush()`.
5. Pasang guard: route middleware `permission:ticket_issue_categories.view|...`, cek di
   controller, sembunyikan menu di sidebar kalau tidak punya akses.

---

## C. Form Create Service Ticket — Behavior Kategori

- Dropdown "Kategori Issue" isi dari `ticket_issue_categories` aktif + opsi terakhir
  **"Lainnya (isi manual)"**.
- Pilih kategori → `default_priority` auto-fill ke dropdown Prioritas (user tetap bisa override).
- Pilih "Lainnya" → `issue_category_id` null, textarea `detail_keluhan` jadi satu-satunya sumber
  klasifikasi (perilaku sekarang, tidak berubah).
- Textarea `detail_keluhan` selalu tampil, selalu required — terlepas dari pilihan kategori.

---

## D. Submit Mode (Worksheet — Stay on Page)

Form submit via `fetch()` JSON, bukan native POST — supaya:
- User tetap di `/tickets/new` setelah simpan (kebutuhan worksheet, entry berturut-turut).
- Tujuan PRG (cegah double-submit saat refresh) tetap terpenuhi karena tidak ada navigasi/history
  entry POST yang bisa di-refresh — bedanya cuma mekanisme, bukan melanggar prinsipnya.

```
Alpine ticketForm() @submit.prevent submitForm()
  → fetch(POST /tickets, Accept: application/json)
  → 201 → toast sukses, reset form, panel kanan update (lihat E)
  → 422 → error inline per field, form TIDAK direset
  → 500 → toast error, form TIDAK direset
```

`TicketController@store` bercabang: `wantsJson()` → JSON response; non-JS fallback → PRG normal
ke `tickets.show` (aturan `docs/PRG_REDIRECT_CONVENTION.md` tetap dipatuhi di jalur ini).

---

## E. Panel Kanan — Realtime

- Event baru `App\Events\TicketQueueUpdated implements ShouldBroadcast`, broadcast ke
  `PrivateChannel('tickets.{pop_id}')`, dispatch dari `TicketService::create()` setelah commit.
- Channel auth baru di `routes/channels.php`, pakai `EffectiveAccessService::getAllowedPopIds()`
  (jalur benar sesuai CLAUDE.md — bukan `$user->pops()` legacy yang dipakai channel `fop.{pop_id}`
  lama).
- Payload card: `code, priority, kategori/detail_keluhan ringkas, cid, bucket, time` — **bukan**
  salinan `catatan_teknis` (jaga satu sumber kebenaran, sama prinsip sync Ticket↔FopTask).
- Initial load panel tetap lewat data yang di-pass server-side saat halaman pertama dimuat;
  realtime cuma nambah item baru ke array Alpine (`tasks.unshift(card)`), bukan pengganti initial
  fetch.

---

## F. Breakdown Task (Terpisah)

Tiga task independen, urutan pengerjaan disarankan berurutan karena ada dependency satu arah
(C bergantung A, D independen, tapi digabung ke halaman yang sama jadi lebih efisien dikerjakan
setelah A siap):

### Task 1 — Master Issue/Kategori Keluhan (fondasi + RBAC)
- Migration `ticket_issue_categories` + `tickets.issue_category_id`
- Model `TicketIssueCategory`
- Seeder dummy (ditandai jelas sebagai contoh)
- RBAC: feature + actions + role matrix (bagian B)
- Controller CRUD `Master/TicketIssueCategoryController` + view `master/ticket-issue-categories/*`
- Route + entri sidebar "Master Data"
- Test: CRUD, permission gate per role, toggle status tidak menghapus riwayat tiket lama

### Task 2 — Redesign Create Service Ticket (visual + AJAX stay-on-page)
- Rewrite `tickets/create.blade.php` pakai tata letak template, `@extends('layouts.app')`
- Dropdown kategori dari Task 1 + auto-fill prioritas + fallback "Lainnya"
- `TicketController@store` — branch JSON response untuk AJAX
- Alpine `ticketForm()` — submit via fetch, reset in-place, error inline
- Test: submit AJAX tidak redirect, tiket tersimpan benar, fallback non-JS tetap PRG

### Task 3 — Realtime Panel Kanan (List Task Ticketing)
- Event `TicketQueueUpdated` + dispatch dari `TicketService::create()`
- Channel `tickets.{pop_id}` di `routes/channels.php` (POP-scoped, `EffectiveAccessService`)
- Alpine Echo listener di `tickets/create.blade.php`, update panel tanpa refetch
- Test: user beda POP tidak menerima broadcast; payload tidak bocor `catatan_teknis`

**Dependency**: Task 2 butuh Task 1 selesai (kolom & dropdown kategori). Task 3 bisa paralel
dengan Task 2 setelah kerangka `ticketForm()` ada, tapi realistis dikerjakan setelah Task 2 supaya
tidak dua kali ubah file Blade yang sama.

---

## G. Keputusan Final

1. Data dummy bagian A dipakai apa adanya sebagai seed — tidak perlu daftar kategori real dulu.
2. Role akses master kategori: **owner saja** untuk sementara (lewat wildcard `*`). Role lain
   ditambahkan manual oleh user lewat UI RBAC nanti — jangan hardcode ke `RolePermissionSeeder.php`
   untuk role selain owner.
3. **Tidak masuk `docs/TASKS.md`.** Modul ini POST-MVP, dilacak terpisah dari sprint tracker
   utama — jangan tambahkan sebagai task/sprint baru di `docs/TASKS.md`.
