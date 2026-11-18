# Analisa & Rancangan: Review Penarikan Alat Gagal (DEAC) — Toleransi 5 Percobaan

**Status:** DIKERJAKAN — **ADHOC-305** (2026-11-18, kode lokal, belum commit). Analisa final 2026-11-17 (revisi K1 + K6 2026-11-18). Penyimpangan dari rancangan yang ditemukan saat coding dicatat di **§9** — baca itu sebelum mengubah fitur ini.

**Terkait:**
- `docs/plan/warehouse/analisa-ambil-modem-deac-ke-gudang.md` (ADHOC-86) — form laporan DEAC, gerak SN ke gudang.
- `docs/plan/warehouse/analisa-riwayat-dan-terima-modem-dari-pelanggan.md` (ADHOC-88) — riwayat SN, Terima Retur.
- `docs/plan/billing/rancangan-terminate-reactivate-state-machine.md` (ADHOC-85) — Langganan Lagi.

---

## 0. Studi Kasus (dari user)

Deact yang dijadwalkan FOP ke teknisi harus di-review. Kalau modem **tidak berhasil diambil**, teknisi wajib melampirkan **alasan + foto**. FOP me-review alasan itu: ditolak → deact diulang (teknisi dapat task DEAC lagi); diterima → kasus ditutup ("dibebaskan"). FOP boleh menerima alasan di percobaan berapa pun kalau masuk akal; setelah **minimal 5 percobaan** alasan boleh ditoleransi (FOP lebih longgar). Customer Service & Business Development bisa memantau progress tiap percobaan dan memberi komentar. Foto percobaan yang ditolak hilang setelah 30 hari; foto percobaan yang di-approve permanen.

## 1. Keputusan User (2026-11-17)

| # | Pertanyaan | Keputusan |
|---|---|---|
| K1 | Arti "minimal 5" | **Ambang toleransi, BUKAN kunci** (revisi 2026-11-18; versi 2026-11-17 mengunci approve sebelum ke-5 — dibatalkan). FOP boleh **approve di percobaan berapa pun** kalau alasan masuk akal (mis. ke-3). Mulai percobaan ke-5, alasan yang biasa pun boleh ditoleransi. Angka 5 hanya penanda di UI (§3.3) dan syarat catatan approve (K6). |
| K2 | Setelah ≥5 kali gagal | Tergantung FOP: approve → dibebaskan; tolak → teknisi dapat **task DEAC baru** lagi (tanpa batas atas). |
| K3 | Review laporan `diambil` | **Tidak** di-review di sini. Modem yang berhasil diambil sudah diverifikasi gudang lewat Terima Retur. Review FOP **hanya untuk laporan gagal**. |
| K4 | Siapa boleh apa | **Approve/Tolak hanya FOP.** Customer Service (`customer_service`) & Business Development (`business_development`) hanya **lihat + komentar**. |
| K5 | Retensi foto | Foto percobaan **ditolak** dihapus setelah 30 hari. Foto percobaan yang **di-approve** (= percobaan terakhir, modem tidak kembali) disimpan **permanen**. |
| K6 | Catatan approve | **Wajib** kalau approve sebelum percobaan ke-5 (jejak kenapa dibebaskan lebih awal, dibaca CS/BD). Mulai percobaan ke-5 boleh kosong. Ditegakkan server-side (revisi 2026-11-18). |

## 2. Diagnosa Kondisi Sekarang

### 2.1 Alur yang berjalan
1. List Putus Langganan → tombol **Ambil Alat** → `CustomerController::retrieveDevice()` → `TicketService::createDeviceRetrievalTask()` membuat FopTask DEAC (DRAFT). Ditolak kalau masih ada FopTask DEAC yang belum selesai/batal.
2. FOP menjadwalkan → Task teknisi → form laporan `TaskDeviceRetrievalController::store()`.
3. Laporan disimpan di `task_device_retrievals` (**1 baris per task**): `outcome` (`diambil` / `tidak_ditemukan` / `ditolak`), `condition_photo`, `accessories`, `notes`.
4. `diambil` → SN langsung jalan ke gudang (transit) lalu dikonfirmasi lewat Terima Retur. `TaskService::complete()` mengisi `customer_devices.device_retrieved_at`.
5. Gagal → `device_retrieved_at` tetap kosong → tombol Ambil Alat muncul lagi → FOP **membuat task baru secara manual**.

### 2.2 Akar masalah
| # | Masalah | Bukti |
|---|---|---|
| D1 | **Tidak ada konsep "percobaan ke-N".** Tiap percobaan jadi FopTask/Task terpisah tanpa pengelompokan, sehingga list cepat penuh dan tidak bisa dihitung "sudah berapa kali". | `task_device_retrievals` cuma punya `task_id`, tanpa nomor atau kasus induk. |
| D2 | **Laporan gagal tidak di-review.** Gagal = otomatis boleh diulang, tidak ada keputusan "alasan valid / tidak". | `TaskDeviceRetrievalController::store()` |
| D3 | **Foto tidak wajib untuk laporan gagal.** Validasi `condition_photo` cuma `required_if:outcome,diambil`. | `TaskDeviceRetrievalController.php:112` |
| D4 | **Review FOP generik bertabrakan.** Semua task yang selesai otomatis `tasks.fop_review_status = pending` (`TaskService::complete()` l.387). Untuk DEAC, `tasks/show.blade.php` (~l.1165) menampilkan panel "Laporan Teknisi (Khusus FOP)" dengan satu tombol **Reject Laporan** → `TaskController::review()` action `reject` mengembalikan task yang **sama** ke `in_progress` supaya teknisi mengisi ulang laporan. Itu alat "laporan salah isi", bukan "ulangi deact". Studi kasus minta tiap pengulangan = task **baru** supaya percobaan terhitung. Kalau dua jalur ini sama-sama hidup untuk laporan gagal, hitungan percobaan dan status review kacau (laporan di-update ulang di task yang sama). Tidak ada tombol Approve generik untuk DEAC (dihapus keputusan 2026-09-28, lihat komentar `TaskObserver::resolveTarget()`). | `TaskController.php` ~l.634–666, `tasks/show.blade.php` ~l.1139–1180 |
| D5 | **Tidak ada status akhir "dibebaskan".** Selain `device_retrieved_at`, tidak ada penanda bahwa kasus ditutup tanpa modem kembali, sehingga tombol Ambil Alat akan terus muncul. | `customer_devices` |
| D6 | **Foto disimpan di disk `public`**, padahal foto rumah pelanggan termasuk data pelanggan (bandingkan aturan lampiran tiket di CLAUDE.md: disk `local`). Belum ada pembersihan otomatis. | `FileUploadService::uploadDeviceRetrievalPhoto()` |
| D7 | **Tidak ada badge** untuk laporan gagal yang menunggu review. | `ActionQueueBadgeService` belum punya query DEAC. |

## 3. Rancangan

### 3.1 Data — tambah kolom, tanpa tabel induk baru
Kasus = **semua laporan DEAC gagal milik satu `customer_id` yang `created_at ≥ customers.terminated_at`** (kolom sudah ada, migrasi `2026_07_24_135019`). Nomor percobaan dihitung dari urutan `created_at`, tidak disimpan. Putus langganan ulang (setelah Langganan Lagi) mengisi `terminated_at` baru → kasus otomatis mulai dari 1 lagi. Halaman dan badge hanya menghitung pelanggan yang `status = terminated`, jadi pelanggan yang Langganan Lagi (Q5) otomatis keluar dari antrian **tanpa** perlu menulis ulang baris review-nya.

Kolom baru di `task_device_retrievals` (hanya bermakna kalau `outcome` ≠ `diambil`):
- `review_status` — enum baru `DeviceRetrievalReviewStatus`: `menunggu`, `ditolak`, `disetujui`. Null untuk `diambil`.
- `reviewed_by` (FK users, nullable), `reviewed_at`, `review_note` (catatan FOP; wajib saat tolak, wajib saat approve di percobaan < 5 — K6).
- `photo_purged_at` (nullable) — jejak bahwa foto sudah dihapus retensi, supaya UI bisa menulis "Foto dihapus (retensi 30 hari)" alih-alih gambar rusak.

Kolom baru di `customer_devices`:
- `retrieval_waived_at` + `retrieval_waived_by` — kasus ditutup tanpa modem kembali (approve FOP). Dipakai List Putus Langganan untuk badge **"Dibebaskan"** dan menyembunyikan tombol Ambil Alat. Di-reset saat Langganan Lagi, sama seperti `device_retrieved_at` (ADHOC-88).

Komentar CS/BD — **tabel baru `device_retrieval_comments`** (`task_device_retrieval_id`, `user_id`, `body`, timestamps). Komentar banyak per percobaan dan tidak muat di kolom. Append-only, tidak bisa diedit atau dihapus (jejak review).

### 3.2 Alur
```
Laporan DEAC dikirim teknisi
 ├─ outcome = diambil  → alur ADHOC-86 apa adanya (Terima Retur gudang). review_status = null.
 └─ outcome gagal      → WAJIB alasan + foto (perbaiki D3)
                          review_status = menunggu   (badge +1)
                          Tombol Ambil Alat disembunyikan selama menunggu review
                          │
                 FOP review di halaman "Review Penarikan Alat"
                          │
        ┌─────────────────┴──────────────────┐
     TOLAK (wajib note)                  APPROVE (percobaan berapa pun;
        │                                 catatan wajib kalau N < 5 — K6)
        │                                    │
  review_status = ditolak             review_status = disetujui
  FopTask DEAC BARU                   customer_devices.retrieval_waived_at = now
  (createDeviceRetrievalTask;         Foto percobaan ini permanen
   FOP pilih: DRAFT, atau langsung    Kasus selesai, badge "Dibebaskan"
   assign teknisi + tanggal — Q4)
  Foto masuk antrian hapus 30 hari
```

Aturan:
1. **Approve boleh di percobaan berapa pun** (K1). Satu-satunya aturan angka: konstanta `TOLERANCE_ATTEMPTS = 5` + method di service (mis. `isWithinTolerance()`), dipakai untuk (a) validasi server: approve dengan N < 5 wajib `review_note` (K6), dan (b) penanda UI §3.3. **Tidak ada** blok server berdasarkan N.
2. **Laporan gagal tidak lagi lewat `TaskController::review()` reject** (D4, keputusan Q6). Untuk task DEAC yang `outcome` gagal: panel "Laporan Teknisi (Khusus FOP)" di `tasks/show` diganti tautan "Review di halaman Review Penarikan Alat", **dan** `TaskController::review()` menolak server-side (bukan cuma tombol disembunyikan). Reject generik untuk DEAC `diambil` (salah input SN) tetap seperti sekarang. Saat review penarikan diputuskan, `tasks.fop_review_status` di-set `approved` supaya panel generik tidak muncul lagi dan `TaskObserver` tetap mencatat label `selesai` (tidak mengubah status FopTask).
3. Task DEAC yang gagal tetap `selesai`. **Tidak ada `TaskStatus` baru**, jadi tidak perlu pemetaan bucket baru. Review hanyalah lapisan di atas laporan.
4. Kalau percobaan berikutnya `diambil`, kasus selesai lewat jalur gudang biasa. Percobaan gagal sebelumnya tetap di riwayat.
5. Semua aksi review dan komentar → `AuditLog`. Notifikasi in-app ke teknisi saat ditolak/di-approve.

### 3.3 Halaman "Review Penarikan Alat"
- Route baru, permission sendiri (generator `features` × `actions`):
  - `device_retrieval_review.view` → FOP, CS, BD (+ owner/atasan lewat wildcard)
  - `device_retrieval_review.review` → **FOP saja** (approve/tolak)
  - `device_retrieval_review.comment` → FOP, CS, BD
- **Wajib POP scope** lewat `EffectiveAccessService` (`customers.pop_id`).
- Satu baris per **pelanggan**, collapse/expand (Alpine `x-collapse`):
  ```
  ▾ C1X4ARQ000702_Budi     │ Percobaan 5 ⚑ boleh ditoleransi │ ⏳ Menunggu Review │ POP Jetis
      #1 12 Nov  Tidak ditemukan  "rumah kosong"   [foto dihapus]  ❌ FOP: alasan kurang
      #2 …
      #5 16 Nov  Pelanggan menolak "modem dibeli"  [foto]  ⏳  [Approve] [Tolak]
         💬 CS Rina: pelanggan konfirmasi via WA modem dibeli putus
  ▾ C1X4ARQ000631_Masudah  │ Percobaan 3                     │ ⏳ Menunggu Review │ POP Siman
      #3 17 Nov  Tidak ditemukan  "rumah dijual, pemilik pindah luar kota"  [foto]  ⏳
         [Approve — catatan wajib (< 5 percobaan)] [Tolak]
  ```
- Filter tab: **Menunggu Review** (default) / Ditolak (sedang diulang) / Dibebaskan. Hanya pelanggan `status = terminated`.
- **Penanda toleransi:** kasus dengan N ≥ 5 diberi label **"Sudah 5× percobaan — boleh ditoleransi"** dan diurutkan paling atas di tab Menunggu Review. Form Approve menampilkan hint: N < 5 → "Catatan wajib: jelaskan kenapa dibebaskan sebelum 5 percobaan"; N ≥ 5 → catatan opsional.
- **Menu (Q9):** sidebar grup FOP, tepat di bawah "Task FOP", dengan badge §3.4.
- **Dua tempat aksi (Q7: keduanya):**
  1. **List** — baris yang di-expand memuat Approve/Tolak (FOP) dan form komentar (FOP/CS/BD) secara inline.
  2. **Detail per pelanggan** (`…/{customer}`) — timeline lengkap percobaan 1..N, foto ukuran besar, semua komentar, aksi yang sama (inline toggle, pola #3 CLAUDE.md). Dibuka dari tombol "Detail" di baris List.
  - Keduanya memakai **endpoint POST yang sama** (satu controller, satu service). Form mengirim field tersembunyi `return_to` berisi URL yang dirender server (`route()` list atau detail); controller hanya menerima nilai dari whitelist dua route itu, lalu redirect PRG ke sana.
  - **Penjaga error di List:** form inline di List bertabrakan dengan alasan konvensi pola #2 (`back()->withErrors()` menutup baris dan error hilang). Penanganan: form mengirim `retrieval_id`; saat validasi gagal, baris dengan `old('retrieval_id')` otomatis ter-expand dan error tampil di dalam baris itu. Ada test khusus (§6).
- Foto disajikan lewat controller yang mengecek permission + scope (§3.6).

### 3.4 Badge
`ActionQueueBadgeService::pendingRetrievalReviews(User)` → hitung `review_status = menunggu` dalam POP scope. Ditampilkan di menu halaman ini, hanya untuk pemegang `device_retrieval_review.review`.

### 3.5 Retensi foto (30 hari)
- Command terjadwal `device-retrieval:prune-photos` (harian), mengikuti pola `notifications:prune-read`.
- Target: `review_status = ditolak` dan `reviewed_at < now - 30 hari` dan `photo_purged_at` null → hapus file, isi `photo_purged_at`. **Alasan teks tidak dihapus.**
- **Tidak pernah** menyentuh `disetujui`, `menunggu`, atau `outcome = diambil`.
- Opsi `--dry-run`.
- File yang sudah dihapus: UI menampilkan "Foto dihapus (retensi 30 hari)" dari `photo_purged_at`.

### 3.6 Foto privat (D6, keputusan Q3)
- `FileUploadService::uploadDeviceRetrievalPhoto()` menyimpan ke disk **`local`** (bukan `public`). Berlaku untuk **semua** foto DEAC, termasuk `diambil` (foto `diambil` permanen, tidak ikut retensi).
- Route baru `GET` foto laporan DEAC → controller cek: login + (`device_retrieval_review.view` **atau** boleh melihat task itu lewat `TaskPolicy`) + pelanggan dalam POP scope. Pola `TicketController::download()`.
- Ganti `foto_publik($retrieval->condition_photo)` di `tasks/partials/device-retrieval-report.blade.php` dan `fop_tasks/history_detail.blade.php` → URL route tersebut.
- Command one-off `OneOff` memindahkan file `device-retrieval/*` dari `public` ke `local` (path di kolom tetap sama, cuma disknya pindah). Ada `--dry-run`. Di dev folder ini kosong; jumlah di produksi belum dicek, jalankan dry-run dulu di sana.
- Di luar scope: foto kabel melintang (`penarikan`, ADHOC-205) dan foto lain yang masih `public`.

## 4. Data Lama
Tidak ada (keputusan user 2026-11-17, Q2): fitur baru dan belum ada laporan DEAC gagal yang perlu dibawa. Hitungan percobaan mulai dari laporan gagal pertama setelah fitur rilis; laporan dengan `review_status` null tidak dihitung.

## 5. Keputusan Lanjutan (2026-11-17)
| # | Pertanyaan | Keputusan |
|---|---|---|
| Q1 | Percobaan 1–4 gagal: review atau otomatis? | **Manual oleh FOP.** Tiap laporan gagal menunggu keputusan FOP; tidak ada penjadwalan ulang otomatis. Approve & Tolak tersedia di semua percobaan (K1 revisi 2026-11-18). |
| Q2 | Laporan lama dihitung? | **Tidak**, lihat §4. |
| Q3 | Foto DEAC ke disk privat (`local`)? | **Ya, privat**, lihat §3.6. |
| Q4 | Task DEAC baru setelah tolak | **Pilihan FOP saat menolak** (checkbox "Langsung jadwalkan"): (a) tidak dicentang → FopTask DEAC baru DRAFT, tetap di halaman review; (b) dicentang → FopTask DEAC baru DRAFT, lalu redirect ke `/fop-tasks?search={TFOP-…}` sehingga FOP menugaskan teknisi lewat form yang sudah ada (Q8). Tidak ada logic assign baru; cek konflik + `FopTaskTeamService::rebuildTeamsForDate()` tetap di `FopTaskController`. Teknisi sebelumnya ditampilkan sebagai info di halaman review, bukan diisi otomatis. |
| Q5 | Langganan Lagi saat kasus berjalan | **Kasus berhenti.** Riwayat percobaan tetap ada, `retrieval_waived_at` di-reset di `CustomerController::reactivate()` (bersama `device_retrieved_at`). |
| Q6 | Tombol lama "Reject Laporan" untuk DEAC gagal | **Disembunyikan + ditolak server**, lihat §3.2 aturan 2. |
| Q7 | Lokasi Approve/Tolak/komentar | **Keduanya: List (inline) dan Detail per pelanggan**, lihat §3.3. |
| Q8 | Cara "langsung jadwalkan" | **Redirect ke form assign `/fop-tasks` yang sudah ada**, lihat Q4. |
| Q9 | Menu | **Sidebar grup FOP, di bawah Task FOP.** |

## 6. Rencana Test (nama sesuai gejala)
- `DeviceRetrievalFailedReportRequiresPhotoTest` — gagal tanpa foto ditolak validasi.
- `DeviceRetrievalEarlyApprovalRequiresNoteTest` — approve di N=3 tanpa catatan ditolak validasi, dengan catatan lolos (kasus dibebaskan); di N=5 tanpa catatan lolos.
- `DeviceRetrievalToleranceFlagShownFromFifthAttemptTest` — label "boleh ditoleransi" muncul & kasus naik ke atas mulai N=5, tidak di N=4.
- `DeviceRetrievalReviewRejectCreatesNewDeacTaskTest` — tolak → FopTask DEAC baru, nomor percobaan naik.
- `DeviceRetrievalReviewOnlyFopCanDecideTest` — CS/BD 403 untuk approve/tolak, 200 untuk komentar.
- `DeviceRetrievalReviewRespectsPopScopeTest` — pelanggan luar scope tidak tampil dan tidak bisa diakses.
- `DeviceRetrievalRejectedPhotoPrunedAfter30DaysTest` — hanya foto ditolak > 30 hari yang terhapus; foto disetujui utuh.
- `DeviceRetrievalGenericRejectDisabledForFailedOutcomeTest` — `TaskController::review()` reject untuk DEAC gagal tidak lagi mengembalikan task ke `in_progress` (D4).
- `TerminatedListShowsWaivedBadgeTest` — Ambil Alat hilang setelah dibebaskan dan selama menunggu review; `retrieveDevice()` juga menolak server-side.
- `DeviceRetrievalReviewRejectRedirectsToFopAssignTest` — centang "Langsung jadwalkan" → redirect `/fop-tasks?search=TFOP-…`; tanpa centang → kembali ke halaman review.
- `DeviceRetrievalReviewInlineListKeepsErrorVisibleTest` — Tolak tanpa catatan dari List → redirect ke List, baris tersebut ter-expand, pesan error tampil.
- `DeviceRetrievalReviewReturnToWhitelistTest` — `return_to` di luar dua route resmi diabaikan (tidak jadi open redirect).
- `DeviceRetrievalPhotoPrivateAccessTest` — foto baru tersimpan di disk `local`; tanpa permission / luar scope → 403; URL `/storage/device-retrieval/…` tidak lagi dipakai di view.
- `DeviceRetrievalCaseResetsAfterReactivateTest` — Langganan Lagi → keluar dari antrian, `retrieval_waived_at` null; putus lagi → hitungan percobaan mulai dari 1.
- `DeviceRetrievalReviewBadgeCountsPendingInScopeTest` — badge hanya menghitung `menunggu` dalam POP scope.

## 7. File yang Akan Disentuh (perkiraan)
Migration (2 alter + 1 create), `app/Enums/DeviceRetrievalReviewStatus.php`, `TaskDeviceRetrieval`, `CustomerDevice`, model `DeviceRetrievalComment`, service baru `DeviceRetrievalReviewService`, controller baru `DeviceRetrievalReviewController`, `TaskDeviceRetrievalController` (validasi foto), `TaskController::review()` (guard D4), `tasks/show.blade.php` (panel review DEAC gagal), `CustomerController::retrieveDevice()` (blok saat menunggu/dibebaskan) + `reactivate()` (reset `retrieval_waived_at`), `ActionQueueBadgeService`, `layouts/app.blade.php` (menu + badge), view list + detail halaman baru, `customers/terminated.blade.php`, `FileUploadService` (disk `local`), `tasks/partials/device-retrieval-report.blade.php` & `fop_tasks/history_detail.blade.php` (URL foto), controller/route foto privat, seeder feature/permission (`RolePermissionSeeder`: fop, customer_service, business_development), command prune + `routes/console.php`, command one-off pindah foto, dokumen modul `docs/task-teknisi/` & `docs/warehouse/`, `docs/TASKS.md`.

## 8. Risiko
- **Menyentuh panel review generik `tasks/show`** (dipakai semua tipe task). Perubahan dibatasi kondisi `task_type = DEAC && outcome gagal`; tipe lain tidak boleh berubah perilaku.
- **Pindah disk foto**: view lama yang masih memakai `foto_publik()` untuk foto DEAC akan menampilkan gambar rusak. Semua pemakaian dicari dengan grep `condition_photo` sebelum rilis; urutan rilis: deploy → jalankan command one-off pindah foto.
- **Aksi inline di List** menyimpang dari pola #2 CLAUDE.md atas permintaan user (Q7). Ditebus dengan penjaga error §3.3 dan test-nya; dicatat di sini supaya tidak ditiru tanpa penjaga yang sama.

## 9. Catatan Implementasi (ADHOC-305, 2026-11-18)

Yang dibangun sesuai §3, dengan penyimpangan/temuan berikut:

1. **Field foto dipindah.** Dulu input `condition_photo` ada di dalam blok `x-show="outcome === 'diambil'"` → foto bukti untuk laporan gagal tidak bisa diunggah sama sekali. Sekarang satu field bersama di atas Catatan; label berganti "Foto Kondisi Alat" / "Foto Bukti Lokasi / Kondisi". Validasi wajib, kecuali laporan sudah punya foto (kirim ulang).
2. **Foto privat menyentuh 6 view, bukan 2.** Selain `tasks/partials/device-retrieval-report` & `fop_tasks/history_detail`, foto yang sama tampil di `customers/tabs/_device`, `warehouse/retrievals/index`, `warehouse/returns/index`, `warehouse/returns/receive`. Semua kini lewat `photoUrl()` (route `device-retrievals.photo` / `device-retrieval-logs.photo`). Walk-in gudang (`WarehouseCustomerReturnController`) memakai fungsi upload yang sama, jadi fotonya ikut privat.
3. **Foto lama dibaca dari dua disk.** `App\Support\DeviceRetrievalPhoto` mencari di `local` dulu lalu `public`, sehingga foto lama tetap tampil sebelum command pindah dijalankan. Urutan rilis: deploy → migrate → seed `DeviceRetrievalReviewFeatureSeeder` + `RolePermissionSeeder` → command one-off `device-retrieval:move-photos-private` (cek dengan `--dry-run` dulu). Sebelum command dijalankan, URL `/storage/device-retrieval/...` lama masih terbuka.
4. **`fop_review_status` ikut disinkronkan** (direvisi setelah code review, keputusan user #5-A 2026-11-18). Awalnya sengaja tidak diubah; akibatnya task DEAC gagal tertahan `pending` selamanya (riwayat Ticketing "Status: PENDING", keputusan tidak tercatat di riwayat FOP). Sekarang `DeviceRetrievalReviewStatus::taskReviewStatus()`: disetujui/ditutup → `approved`, ditolak → `rejected` (task lama tetap `selesai`; ulangannya task baru). `TaskObserver` mencatat tepat satu baris `fop_task_status_history` per keputusan. Jejak detail tetap di AuditLog `device_retrieval_review_approved` / `_rejected` / `_closed`.
5. **Guard `TaskController::review()` menutup ketiga aksi** (approve/reject/pending), bukan hanya reject, untuk task DEAC ber-`review_status`. Laporan gagal lama (tanpa `review_status`) tetap lewat jalur lama.
6. **`return_to` = token `list|detail`, bukan URL.** Lebih ketat dari "whitelist dua route": nilai lain gagal validasi; URL tujuan selalu dirakit server.
7. **Action code baru `review` & `comment`** (`ActionCode`, `ActionSeeder`). Permission `device_retrieval_review.view|review|comment`; fop = ketiganya, customer_service & business_development = view + comment.
8. **Badge sidebar** di item "Review Penarikan Alat" + induk grup "Penjadwalan Teknis"; hanya untuk pemegang `.view` + `.review` (CS/BD tidak dapat badge). Grup sidebar kini juga terbuka untuk `device_retrieval_review.view`.
9. **Pelanggan dibebaskan lalu Langganan Lagi** mengikuti jalur ACTIVE yang sudah ada (modem dianggap masih di lokasi karena `device_retrieved_at` kosong) — perilaku lama tidak diubah; `retrieval_waived_at/by` dikosongkan.
10. **Status keempat `ditutup` (code review 2026-11-18).** Laporan yang masih MENUNGGU saat kasus berhenti tanpa keputusan FOP ditutup otomatis oleh `DeviceRetrievalReviewService::closeOpenCase()`: dari walk-in gudang (`InventoryReassignService::receiveSerialFromCustomerAtWarehouse()`) dan Langganan Lagi (`CustomerController::reactivate()`). Sebelumnya: walk-in → laporan tetap di antrian, Tolak memicu task DEAC untuk modem yang sudah di gudang; Langganan Lagi → laporan yatim (tak tampil di halaman review, review generik diblokir). Foto laporan `ditutup` tidak di-prune (retensi 30 hari hanya untuk `ditolak`).
11. **Penjaga `lockReviewable()` tambahan (code review):** menolak laporan pelanggan yang modemnya sudah kembali (`device_retrieved_at`), dan laporan di luar kasus berjalan (masa putus sebelumnya — dulu bisa di-Approve dari tab lama sebagai "percobaan ke-0").
12. **Foto lama boleh dipakai ulang saat kirim ulang laporan hanya kalau jenis hasilnya sama dan filenya masih ada** — dulu foto kondisi modem dari laporan "diambil" yang di-reject bisa lolos sebagai "bukti lokasi" laporan gagal.
13. **Definisi kasus satu sumber di SQL:** `caseQuery()` / `customerIdsWithPendingReview()` / `pendingForUser()` memakai `constrainToCase()`; `attemptNumber()` jadi satu COUNT (dulu memuat seluruh kasus + relasi di dalam transaksi lock). `filterToCase()` tetap salinan in-memory untuk relasi eager load.
14. **`photoUrl()` tetap mengecek file ada** (temuan review soal I/O per baris tidak diambil): konvensi repo `foto_publik()` + `FotoHilangDiDiskTidakDirenderTest` — foto hilang tidak boleh dirender sebagai gambar rusak. Command pindah foto kini menghapus salinan public yang isinya identik dengan salinan privat; yang isinya beda dilaporkan (masih terbuka, cek manual).
15. **Test:** nama sesuai §6, kecuali `DeviceRetrievalReviewBadgeCountsPendingInScopeTest` digabung ke `DeviceRetrievalReviewRespectsPopScopeTest::badge_hanya_menghitung_laporan_menunggu_dalam_scope`. Data bersama di `tests/Concerns/BuildsDeviceRetrievalReviewScenario.php`. Test lama yang disesuaikan (laporan gagal kini wajib foto, disk `local`): `DeviceRetrievalDeacToWarehouseTest`, `DeviceRetrievalHistoryAndWalkInTest`, `CableCrossingDeactTest`.
