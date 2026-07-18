# Business Logic — Modul Ticketing

## 1. Tipe Tiket yang Berlaku

Cuma 2 dari 8 nilai `TaskType` yang boleh masuk lewat Ticketing:

```php
// App\Enums\TaskType::ticketValues()
[self::MAINTENANCE->value, self::CREQ->value]  // ['MTN', 'C-REQ']
```

Tipe lain (SURVEY, PSB, O-REQ, RELOKASI, DEAC, INFR) tetap dibuat langsung dari `/fop-tasks` (FopTaskController::store()) atau auto-sync Registrasi Pelanggan — gak pernah lewat Ticketing. `TicketController::store()` menolak tipe di luar 2 itu dengan pesan validasi `"Tipe ticket hanya boleh MTN atau C-REQ."`.

## 2. Dua Jalur Masuk, Satu Logic

`TicketService::create()` melayani dua aktor lewat satu jalur logic, dibedakan lewat parameter `$assignment`:

| Jalur | Siapa | Endpoint | Hasil |
|---|---|---|---|
| Submit biasa | Helpdesk/NOC/Sales/Admin/POP Admin, dari `/tickets/new` | `POST /tickets` tanpa `technicians[]` | `FopTask` Draft, `task_id` null, teknisi kosong — masuk bucket "Ticket Masuk" |
| Submit + assign langsung | FOP, dari modal "Tambah Task FOP" (`/fop-tasks`) | `POST /tickets` dengan `technicians[]` + `origin=fop_tasks` | `FopTask` langsung Terjadwal, `Task` eksekusi langsung dibuat |

**Kenapa satu endpoint, bukan dua:** FOP kan yang paling berwenang assign teknisi — aneh kalau dipaksa dua langkah (submit dulu, assign belakangan) padahal dia bisa langsung tentuin di form yang sama. Membatalkan tiket **memang seharusnya** ikut membatalkan pekerjaannya juga; kalau enggak, Task-nya jadi yatim dan tetap kelihatan aktif di `/tasks-saya`.

**Kenapa aman dari self-assign:** field `technicians[]` dan `task_date` yang dikirim dari form Ticketing (`/tickets/new`, tanpa field ini sama sekali di HTML-nya) TIDAK dihonor gitu aja kalau ada di payload — `TicketController::store()` cuma memprosesnya kalau aktor punya permission `fop_tasks.create`:

```php
$assignment = [];
if (!empty($validated['technicians']) && auth()->user()->hasPermission('fop_tasks.create')) {
    $assignment = ['technicians' => $validated['technicians'], 'task_date' => $validated['task_date'] ?? null];
}
```

Helpdesk yang nge-craft request manual (lewat devtools) dengan `technicians[]` terisi TETAP diabaikan diam-diam (bukan ditolak 422/403) — tiket tetap kebentuk normal sebagai Draft. Redirect tujuan (`origin=fop_tasks` → balik ke `/fop-tasks`, bukan `/tickets/{id}`) dikunci permission yang sama.

## 3. FopTask Draft — Kenapa Bukan Lewat FopTaskController::store()

`TicketService::syncToFopTask()` sengaja gak manggil `FopTaskController::store()` — controller itu mewajibkan minimal 1 teknisi dan langsung bikin `Task` eksekusi, sedangkan tiket dari perusahaan masuk sebagai antrean mentah yang penugasannya jadi keputusan FOP (kecuali FOP sendiri yang submit sambil assign, lihat § 2).

## 4. Snapshot Data Pelanggan

8 kolom pelanggan di `tickets` (`customer_name`, `customer_address`, `customer_phone`, `customer_odp`, `customer_package`, `customer_device`, `customer_latitude`, `customer_longitude`) diisi **sekali** saat tiket dibuat (`TicketService::snapshotCustomer()`), **BUKAN** dibaca live dari relasi `customer()`.

**Kenapa dibekukan:** ticket adalah catatan keluhan pada satu titik waktu. Kalau field-field ini cuma dibaca live, riwayat ticket lama bisa "berubah" begitu pelanggan pindah alamat, ganti paket, atau nomor HP-nya diupdate — padahal itu bukan yang terjadi saat keluhan dilaporkan.

**Pengecualian: POP TIDAK ikut di-snapshot sebagai teks.** `tickets.pop_id` tetap FK ke master data — ID-nya sendiri sudah cukup jadi jangkar historis, beda dari string/angka bebas seperti alamat atau koordinat.

**Pengecualian lain: CID juga TIDAK di-snapshot.** CID (`customers.cid`/`display_id`) itu identitas permanen yang terikat ke `customer_id` (FK), bukan data yang berubah-ubah seperti alamat — jadi selalu dibaca live via `$ticket->customer->display_id` di halaman detail/list. Lihat § 7 buat bug yang pernah terjadi di sini.

**Urutan resolusi ODP:** `customers.odp_code` (kolom denormalisasi) diprioritaskan, fallback ke `customer_devices.odp` kalau kosong — urutan ini diduplikasi identik di `TicketService::snapshotCustomer()` dan `TicketController::customerPayload()` (live-preview form), sengaja gak saling manggil biar independen.

## 5. Status Tiket = Derivasi, Bukan Kolom

```php
// Ticket::resolveStatus()
public function resolveStatus(): ?TaskStatus
{
    return $this->fopTask?->status;
}
```

Dinamai `resolveStatus()`, **BUKAN** `status()` — Eloquent nebak method zero-argument bernama sama kayak attribute access itu relasi. Kalau ada kode nulis `$ticket->status` (properti, bukan manggil method), Eloquent nyoba resolve sebagai relasi, dapet balik `TaskStatus` (bukan `Relation`), lempar `LogicException`. Ini pernah beneran kejadian (500 di `/tickets/masuk`) — lihat § 8.

`fopTask` null (FopTask udah dihapus FOP lewat `nullOnDelete`) → `statusLabel()` balikin `"Terputus"`, bukan crash.

## 6. Bucket Submenu (`TicketBucket`)

| Bucket | Status `FopTask` yang masuk |
|---|---|
| Ticket Masuk | `draft` |
| Ticket di Proses | `terjadwal`, `in_progress`, `pending` (termasuk "Lapor Nanti" — itu `pending` + `Task.report_deferred`, bukan status terpisah) |
| Ticket Selesai | `selesai` |
| Ticket Dibatalkan | `dibatalkan`, **plus** tiket "Terputus" (`fop_task_id` null) |

Empat bucket ini **wajib saling lepas dan menutupi seluruh `TaskStatus`** — dijaga test `test_buckets_cover_every_task_status_exactly_once()`. Kalau nambah case baru di `TaskStatus` dan lupa dipetakan, test itu gagal duluan sebelum tiketnya diam-diam hilang dari semua submenu.

Filter dijalankan lewat `Ticket::scopeInBucket()` — query langsung ke kolom `fop_tasks.status`, bukan cache/computed column, jadi begitu status berubah, tiket otomatis pindah bucket di request berikutnya.

## 7. Bug: CID Tampil Mentah di List (Fixed)

**Gejala:** daftar tiket nampilin `RQ000007` (nomor registrasi mentah) padahal `customers.cid` udah nyimpen CID lengkap (`C1X4CRQ000007`).

**Akar masalah:** `TicketController::index()` eager-load customer dengan kolom dibatasi (`'customer:id,full_name,cid,customer_code'`) — TANPA `pop_id`. `Customer::getDisplayIdAttribute()` butuh akses `$this->pop` (relasi) buat nentuin format; tanpa `pop_id` ke-select, relasi itu selalu `null`, dan accessor diam-diam jatuh ke fallback paling awal (`if (!$pop) return $this->customer_code;`) — skip semua aturan CID (lihat [docs/master/pop/business-logic.md](../master/pop/business-logic.md) buat aturan lengkap format CID).

**Fix:** tambah `pop_id`, `status`, `distribution_id` ke select, plus eager-load `customer.pop:id,name,cid_prefix`. Bug yang sama dicegah di `FopTaskController::index()` (modal Edit) dan `history_detail.blade.php` (Detail Task, cuma 1 row jadi lazy-load langsung, gak perlu eager-load tambahan) dengan pola yang sama.

## 8. RBAC Pembatalan — 3 Lapis, Bukan Satu

Sistem punya **dua objek berbeda** yang bisa dibatalkan, dua permission terpisah, dan satu policy penyambung:

| | `fop_tasks.cancel` | `task.cancel` | `TaskPolicy::cancelViaFopTask()` |
|---|---|---|---|
| Batalin | `FopTask` (tiket) | `Task` (eksekusi teknisi) langsung dari `/tasks` | `Task` sebagai **efek ikutan** cascade dari `/fop-tasks` |
| Role default | owner, admin, fop | owner, fop (**admin TIDAK punya!**) | Otoritasnya = `fop_tasks.cancel`, BUKAN `task.cancel` |

**Kenapa cascade pakai `fop_tasks.cancel`, bukan `task.cancel`:** role `admin` di DB punya `fop_tasks.*` tapi **gak punya** `task.cancel`. Kalau cascade dipaksa lewat `task.cancel`, admin kehilangan kemampuan membatalkan tiket yang selama ini dia punya. Dua permission ini menjaga dua pintu berbeda ke objek berbeda, bukan kelalaian desain.

**Invarian yang tetap dijaga:** `cancelViaFopTask()` memeriksa `task_type` milik **Task yang beneran dibatalkan** (bukan `FopTask.category`) — kalau dua kolom itu menyimpang, tiket MTN gak bisa jadi jalan pintas buat membatalkan Task SURVEY/PSB. `TaskPolicy::before()` sengaja ngecualiin `cancelViaFopTask` dari bypass wildcard owner (`*`), biar invarian ini berlaku ke SEMUA role termasuk owner.

Modul Ticketing sendiri **sengaja gak punya endpoint cancel** — `POST /tickets/{id}/cancel` selalu 404. Satu-satunya pintu pembatalan tiket adalah Task FOP.

## 9. Dua History per Pembatalan

Satu aksi cancel menghasilkan **DUA** baris riwayat, ditulis oleh dua tempat berbeda tergantung jalur:

| Jalur cancel | History FOP (`fop_task_status_history`) | History Ticket (`ticket_histories`) |
|---|---|---|
| `/fop-tasks` (FOP batalin) | `FopTaskController::update()` | `FopTaskObserver` |
| `/tasks` (Task dibatalin, cascade naik) | `TaskObserver` (guard lolos karena `FopTask` belum `dibatalkan`) | `FopTaskObserver` |

`FopTaskObserver` jadi **satu-satunya** penulis sisi Ticket — dua jalur ketutup tanpa nulis dobel (dijaga test `test_assigned_ticket_cancellation_does_not_duplicate_histories`).

**Bug lama yang ditutup di sini:** `TaskObserver` (penulis normal `fop_task_status_history`) punya guard early-return begitu `FopTask` udah berstatus `dibatalkan` (biar cancel manual gak ke-overwrite sync otomatis) — efek sampingnya, cancel dari `/fop-tasks` (yang set status duluan sebelum cascade jalan) gak pernah nulis history FOP sama sekali. Fix: `FopTaskController::update()` nulis `FopTaskStatusHistory` sendiri persis di titik itu, di luar jangkauan guard `TaskObserver`.

## 10. Bug: FopTask Draft Gak Naik Status Meski Udah Di-assign (Fixed)

**Gejala:** FOP assign teknisi ke tiket Draft lewat modal Edit tabel `/fop-tasks` — `Task` eksekusi kebuat, teknisi ke-assign beneran, tapi `fop_tasks.status` nyangkut `draft` selamanya. Tiket macet di bucket "Ticket Masuk" walau udah beneran dijadwalkan.

**Akar masalah:** modal Edit ngirim field `status` sebagai hidden input berisi nilai LAMA (`draft`) — form itu emang sengaja gak punya pilihan ubah status manual (`"Status realtime — otomatis mengikuti status Task teknisi, gak bisa diedit manual di sini"`). Blok assign-teknisi di `FopTaskController::update()` bikin `Task` + sync teknisi, tapi gak pernah ikut naikin `FopTask.status`.

**Fix:** tangkap `$originalStatus` di awal `update()` (sebelum field apa pun diubah). Begitu teknisi baru di-assign (bukan dikosongin) ke `FopTask` yang masih `draft`, status ikut naik ke `terjadwal` + nulis `FopTaskStatusHistory`. Generik — berlaku juga buat `FopTask` Draft non-Ticketing (mis. hasil auto-sync SURVEY yang belum di-assign).

## 11. Edit Modal Ikut Ticketing (Sinkronisasi Penuh)

Sebelumnya, `isTicketMode` di modal "Tambah Task FOP" cuma aktif pas **create** — buka modal Edit buat MTN/C-REQ yang udah nyambung ke tiket balik ke form generik (field "Penugasan/Pelanggan" bisa dicari-ulang ke pelanggan LAIN, lepas dari ticket aslinya).

**Fix:**
- `FopTaskController::index()` eager-load `ticket` + `ticket.customer` (dengan `pop_id`/`status`/`distribution_id` — kolom yang sama dari § 7) + `display_id` di-append manual (`$fopTask->ticket?->customer?->append('display_id')`) karena itu accessor, bukan kolom, jadi gak otomatis ikut ke-`json_encode()`.
- `isTicketMode` di Alpine sekarang juga `true` pas edit kalau `FopTask` punya `ticket` terkait.
- Panel CID/data pelanggan jadi **read-only** (tombol "Ganti" disembunyikan), Detail Keluhan/Catatan Teknis ditampilkan sebagai teks read-only (bukan textarea) — edit beneran cuma lewat Ticketing, biar gak ada dua sumber kebenaran yang bisa menyimpang.
- Tipe Task ikut dikunci (`disabled` + hidden input fallback) kalau ada ticket terkait — mencegah kategori diubah manual sampai desync dari `Ticket.type`.
- POP/Cabang & Desa **otomatis kebaca** dari `fopTask.pop_id`/`village_id` (udah bener sejak `syncToFopTask()`) — field-nya cuma disembunyikan visual (`x-show`), tetap ke-submit gak berubah lewat form yang sama.

## 12. Detail Task Mengikuti Detail Ticketing

`history_detail.blade.php` nampilin section "Detail Ticket" khusus buat `FopTask` category MTN/C-REQ yang punya `ticket` terkait (`$isTicketOriginType && $ticket`). Isinya harus paritas data (bukan visual) sama `/tickets/{id}`: CID, data pelanggan snapshot, Detail Keluhan & Catatan Teknis versi utuh (bukan `$fopTask->issue` yang kepotong 255 karakter), lampiran, **dan** "Assigned by"/"Created" (siapa & kapan tiket dikirim) — dua elemen terakhir sempat ketinggalan, ditambahkan belakangan.

Section "Riwayat Ticketing" (dari `ticket->histories`) tampil berdampingan dengan "Histori Status" (`fopTask->statusHistories`) yang udah ada — dua riwayat independen sesuai § 9, bukan digabung jadi satu.

Section "Laporan" (laporan teknisi: kendala teknis, alat dipakai, foto) **gak diubah** — itu laporan teknisi tentang apa yang dia kerjakan, beda konsep dari keluhan pelanggan yang datang dari Ticketing. Keduanya tampil berdampingan buat MTN yang asalnya dari tiket.

Issue/Gangguan (`detail_keluhan`) dan Catatan Teknis (`catatan_teknis`) juga dipisah jadi 2 blok utuh sendiri (bukan berbagi 1 baris grid) — masing-masing sumbernya beda dan gak boleh ketuker: Issue/Gangguan itu keluhan **pelanggan** (wajib diisi), Catatan Teknis itu asesmen **teknis** NOC (opsional). Baris "Issue"/"Catatan" generik di panel Info Task (dari `$fopTask->issue`/`notes`) disembunyikan khusus buat tipe ticket-origin — dua versi konten yang beda (satu kepotong 255 char, satu utuh) gak boleh tampil bersamaan.

`composeFopNotes()` (isi `fop_tasks.notes`) SENGAJA cuma pointer pendek (`"Ticket TKT-xxx — dikirim oleh yyy."`), **BUKAN** nyalin ulang `catatan_teknis` ke dalamnya — itu kesalahan desain awal yang bikin dua sumber kebenaran (notes vs ticket.catatan_teknis) bisa menyimpang begitu salah satu diedit belakangan.

## 13. Format `tugas`: `{CID}_{Nama}`

`FopTask.tugas` buat SURVEY, PSB, MTN, dan C-REQ semuanya pakai format `"{customer->display_id}_{customer->full_name}"`, mis. `C1X4ARQ000631_Masudah Yuni Fitri` — bukan label generik ("Survey Pelanggan: ...", "Maintenance: ..."). Konsisten sama identitas pelanggan yang dipakai di seluruh sistem (lihat [docs/master/pop/business-logic.md](../master/pop/business-logic.md)).

Diterapkan di 3 tempat: `TicketService::syncToFopTask()` (MTN/C-REQ dari Ticketing) dan `FopTaskController::autoSyncAndCalculatePriority()` (SURVEY & PSB auto-sync). Ketiganya butuh `Customer::pop` ke-eager-load — tanpa itu, `display_id` jatuh ke fallback yang salah (lihat §7).

## 14. Restriksi Hapus Task FOP

`FopTaskController::destroy()` menolak (422) dua kategori:

| Kategori | Kenapa ditolak |
|---|---|
| SURVEY, PSB | `destroy()` beneran mentransisikan customer ke status `rejected` sebagai efek samping — konsekuensi bisnis nyata, harus disengaja lewat halaman Pelanggan, bukan tombol Hapus di tabel Task FOP |
| MTN, C-REQ **yang punya `ticket` terkait** | Riwayat pengirim (`ticket_histories`) harus tetap ke-trace — hapus `FopTask` gak boleh bikin jejak Ticketing jadi yatim tanpa cara ditelusuri |

**MTN/C-REQ yang dibuat MANUAL langsung di `/fop-tasks`** (gak punya `ticket`, `$fopTask->ticket` null) **tetap boleh dihapus** — toleransi buat kasus salah input data. Kategori lain (O-REQ, RELOKASI, DEAC, INFR) gak kena restriksi ini sama sekali.

UI (`fop_tasks/index.blade.php`) menghitung `$canDeleteTask` per baris dan menyembunyikan form Hapus (diganti ikon disabled + tooltip alasan) buat baris yang gak boleh — tapi ini cuma lapisan tampilan, gerbang sebenarnya tetap di `destroy()` server-side.
