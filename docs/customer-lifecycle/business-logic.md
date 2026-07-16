# Business Logic — Customer Verifikasi & Onboarding Lifecycle

## 1. State Machine (`WorkflowTransition` enum)

14 status, tiap status punya daftar tujuan valid (`allowedNextTransitions()`). `CustomerWorkflowService::transition()` **menolak transisi ilegal** — lempar exception kalau target status gak ada di daftar allowed dari status sekarang.

| Status | Transisi Valid Berikutnya |
|--------|---------------------------|
| `registered` | `waiting_survey`, `rejected` |
| `waiting_survey` | `survey_in_progress`, `rejected` |
| `survey_in_progress` | `waiting_acc`, `surveyed`, `rejected` |
| `surveyed` | `waiting_acc`, `waiting_installation`, `rejected` |
| `waiting_acc` | `waiting_installation`, `survey_in_progress`, `rejected` |
| `waiting_installation` | `installation_in_progress`, `rejected` |
| `installation_in_progress` | `verification_admin`, `installed`, `waiting_installation`, `rejected` |
| `installed` | `verification_admin`, `waiting_installation`, `rejected` |
| `verification_admin` | `active`, `waiting_installation`, `revision_installation`, `installation_in_progress`, `rejected` |
| `revision_installation` | `verification_admin`, `installed`, `waiting_installation`, `rejected` |
| `active` | `installed`, `verification_admin`, `revision_installation`, `suspended`, `terminated` |
| `suspended` | `active`, `terminated` |
| `terminated` | *(final, tidak ada transisi keluar)* |
| `rejected` | *(final, tidak ada transisi keluar)* |

**Catatan penting:** registrasi (`CustomerController::store`) langsung set `status = 'waiting_survey'`, **melewati** `registered` — status `registered` di enum lebih sebagai state teoretis/starting point default (`$customer->status ?? 'registered'` di `CustomerWorkflowService`), bukan state yang benar-benar disinggahi di alur normal saat ini.

## 2. Efek Samping Otomatis Tiap Transisi

`CustomerWorkflowService::transition()` gak cuma update `status` — ada 3 efek samping yang jalan otomatis dalam 1 DB transaction:

1. **Audit** — `AuditLog` (module `Customer Workflow`) + `CustomerStatusLog` (immutable, `from_status`/`to_status`/`changed_by`/`note`) — dua-duanya jalan tiap transisi, gak bisa di-skip.
2. **Auto-create Task** — kalau target status `waiting_survey` atau `waiting_installation`, dan belum ada `Task` aktif (status `pending`/`terjadwal`/`in_progress`) dengan tipe yang sama untuk customer itu → bikin `Task` baru status `pending`. Ini yang mengisi antrean kerja teknisi/FOP — **jangan bikin Task manual duplikat** untuk 2 status ini, sistem udah handle.
3. **Notifikasi aktivasi** — target status `active` → dispatch job `SendCustomerActivationNotification` (async, gak blocking response).

## 3. Tahap 1 — Registrasi

- Entry point: `CustomerController::store()`, permission `customers.create`.
- Status langsung `waiting_survey` (lihat catatan di atas) — bukan lewat `registered` dulu.
- `customer_status` (field terpisah, label operasional Bahasa Indonesia) di-mapping dari `status` lewat tabel statis di controller (e.g. `waiting_survey` → `survey`).
- Nomor pelanggan (`customer_code`) di-generate dari sequence per-POP (`Pop::generateRegistrationNumber()`).
- Foto KTP/rumah/kontrak diupload terpisah dari field lain, disimpan di `customers.foto_*` (redundant dengan `customer_addresses.house_photo`/`ktp_photo`/`contract_photo` — dua sumber sama, legacy duplikasi kolom).

## 4. Tahap 2 — Survey (`CustomerSurveyController`)

### Mulai Survey (`start()`)

- Guard status: kalau `registered` → auto-transition ke `waiting_survey` dulu (defensive, jarang kepakai karena registrasi udah langsung `waiting_survey`), lalu **wajib** `waiting_survey` sebelum lanjut.
- **Guard konflik jadwal teknisi:** kalau teknisi (atau anggota tim Task Survey) sedang punya Task lain berstatus `in_progress`, mulai survey **ditolak** — teknisi harus selesaikan/pending-kan task berjalan itu dulu. Berlaku juga di tahap Pemasangan (aturan sama, dicek 2x di controller berbeda).
- Set `CustomerSurvey.started_at`, transition customer ke `survey_in_progress`, mulai `Task` terkait (`TaskService::start()`).
- Broadcast event `SurveyStarted`.

### Lapor Survey (`store()`)

- Guard status: cuma bisa submit kalau status masih `survey_in_progress`.
- Validasi: `survey_status` (`pending`/`completed`/`failed`). `cable_estimation_meter`, `nearest_odp`, `survey_photo`+`house_photo`, `difficulty_level` (`MUDAH`/`SEDANG`/`SULIT`) **wajib hanya kalau `survey_status=completed`** (`required_if`) — kalau `failed`, field teknis ini opsional karena situs mungkin memang tidak bisa disurvei penuh. `difficulty_level` (kalau ada) digabung jadi teks di `survey_note`.
- **Multi-surveyor:** kalau Task punya >1 anggota tim, sistem catat siapa "Petugas Survey 1/2/3" berdasar urutan anggota — `surveyor_2_id`/`surveyor_3_id` diisi otomatis dari anggota lain (maks 3 surveyor tercatat by design kolom).
- Kalau `survey_status = completed` **dan** status customer masih `survey_in_progress` → complete Task Survey, transition ke `waiting_acc`, broadcast `SurveyCompleted`, kirim notifikasi Telegram.
- **Kalau `survey_status = failed` (✅ ditambahkan 2026-07-08 — sebelumnya gap, lihat [bug.md](bug.md)):** `survey_note` jadi **wajib** (alasan tidak layak pasang, ditombolkan lewat tombol "Tidak Layak Pasang" terpisah di UI — bukan dropdown, biar teknisi gak salah pencet). Task Survey terkait di-**cancel** (`TaskService::cancel()`, status `DIBATALKAN` + `cancel_reason`), customer di-**transition ke `rejected`** (state final, sama mekanisme dengan reject di tahap verifikasi — lihat §7). Tiket Pemasangan otomatis **tidak akan pernah terbentuk** karena workflow tidak pernah sampai `waiting_acc`.
- Kalau `survey_status = pending` (laporan draf/belum final) → data tersimpan, status customer/task tidak berubah, technician bisa submit ulang nanti.

### Batalkan Survey — sebelum/selagi dikerjakan (`cancel()`)

**Beda dari §"Lapor Survey" `survey_status=failed` di atas** — itu jalur "teknisi UDAH di lokasi, submit laporan gagal survey". Method `cancel()` ini buat kasus SEBELUM laporan ada sama sekali: **belum ditugaskan** (`waiting_survey`, belum ada teknisi/Task jalan) ATAU **udah ditugaskan tapi gak jadi/dibatalkan** (`waiting_survey` juga — status customer TETAP `waiting_survey` walau Task Survey-nya udah `terjadwal`/teknisi udah di-assign, sampai teknisi beneran pencet "Mulai Survey") ATAU **lagi dikerjakan tapi dibatalkan di tengah jalan** (`survey_in_progress`, belum submit laporan).

- Guard: `customer->status` harus `waiting_survey` atau `survey_in_progress`, permission `customers.detail.survey.reject`.
- 1 transaksi: cari Task Survey terkait yang belum `selesai`/`dibatalkan` → `TaskService::cancel()` (status `dibatalkan`, otomatis sync ke `FopTask` via `TaskObserver`) → `CustomerSurvey.survey_status = failed` (record survey terbaru, dibuat baru kalau belum ada) → `CustomerWorkflowService::transition(customer, REJECTED, $reason)`.
- **Tombol ada di 2 tempat** (2026-07-21): tab Survey halaman Customer detail (`resources/views/customers/tabs/_survey.blade.php`, tombol "Batalkan Survey") DAN tabel Antrean Survey Lapangan (`/surveys/queue`, tombol "Batalkan" per baris — **baru ditambah**, sebelumnya cuma ada "Mulai Survey"/"Lapor Data", gak ada opsi batalkan sama sekali dari halaman ini).
- **Ini SATU-SATUNYA jalur sah buat batalin Task Survey** — `TaskPolicy::cancel()` block cancel langsung dari halaman Task buat task_type SURVEY (berlaku semua role termasuk owner), dan `FopTaskController::update()` nolak (422) kalau coba cancel tiket FOP kategori Survey. Alasannya: cancel dari Task/FopTask doang gak nyentuh `Customer.status` — pelanggan bakal nyangkut permanen di status lama, gak pernah masuk List Pelanggan Gagal. Lihat `docs/fop-task/flowchart.md` § 12.

## 5. Tahap 3 — Verifikasi Survey → Proses ke Tim (`CustomerVerificationController::processToTeam`)

- Guard status: cuma dari `waiting_acc` atau `surveyed`.
- Bikin/pakai record `CustomerInstallation` (status `scheduled`) kalau belum ada yang `scheduled`/`in_progress`.
- Transition ke `waiting_installation` (efek samping: auto-create Task Pemasangan).
- **Auto-approve** Task Survey terkait yang statusnya `fop_review_status = pending` → langsung `approved` (FOP yang klik "Proses ke TIM" dianggap sekaligus meng-approve laporan survey teknisi — 1 aksi, 2 efek).

## 6. Tahap 4 — Pemasangan (`CustomerInstallationController`)

### Mulai Pemasangan (`start()`)

- Guard status: harus `waiting_installation`.
- Guard konflik jadwal teknisi — sama persis logic-nya dengan Survey (lihat §4).
- Transition ke `installation_in_progress`, broadcast `InstallationStarted`.

### Lapor Pemasangan (`store()`)

- Guard status: `installation_in_progress` atau `revision_installation` (revisi bisa submit ulang laporan tanpa perlu "start" ulang).
- **Validasi kondisional ketat saat `installation_status = completed`:** foto pemasangan, foto kontrak, foto TTD pelanggan, DAN foto speedtest **wajib ada** (baik dari upload baru atau yang udah tersimpan sebelumnya) — kalau salah satu kosong, submit ditolak dengan pesan spesifik per field.
- Data teknis disimpan **dobel** ke 2 tabel: `CustomerTechnicalDetail` (sumber utama, field lengkap: ODP/OLT/VLAN/speedtest/attenuation) dan `CustomerDevice` (legacy, subset field device/PPPoE/WiFi) — keduanya di-`updateOrCreate` dalam transaksi yang sama.
- **Speed conformity** dihitung otomatis: `(test_download / paket.download_speed_mbps) * 100` — dipakai buat verifikasi hasil instalasi sesuai spek paket yang dijual.
- Efek per `installation_status`:
  - `completed` → complete Task Pemasangan, transition 2x berturutan: `installed` lalu langsung `verification_admin` (skip berhenti di `installed`), broadcast `InstallationCompleted`, notifikasi Telegram.
  - `failed` → transition balik ke `waiting_installation` ("Instalasi gagal/butuh revisi. Menunggu penjadwalan ulang").
  - lainnya (progress belum selesai) → data tersimpan, status customer/task tidak berubah.

### Batalkan Pemasangan — sebelum/selagi dikerjakan (`cancel()`, **baru 2026-07-21**)

Setara `CustomerSurveyController::cancel()` di §4, tapi buat tahap Pemasangan — sebelumnya **GAK ADA jalur ini sama sekali** buat PSB (gap: satu-satunya cara batalin Task Pemasangan yang lagi jalan ya lewat tombol Cancel langsung di halaman Task, yang BYPASS `Customer.status` sepenuhnya).

- Guard: `customer->status` harus `waiting_installation`, `installation_in_progress`, atau `revision_installation`, permission **`customers.detail.installation.reject`** (baru ditambah ke `config/rbac.php` + role `noc`/`fop` di `RolePermissionSeeder`, sebelumnya cuma ada `.view/.update/.validate/.activate` — gak ada `.reject`).
- 1 transaksi: cari Task Pemasangan terkait yang belum `selesai`/`dibatalkan` → `TaskService::cancel()` → `CustomerInstallation.installation_status = failed` (+ append alasan ke `notes`) → `CustomerWorkflowService::transition(customer, REJECTED, $reason)`.
- Tombol "Batalkan Pemasangan" di tab Pemasangan halaman Customer detail (`resources/views/customers/tabs/_installation.blade.php`).
- Sama kayak Survey: ini satu-satunya jalur sah, `TaskPolicy::cancel()` + `FopTaskController::update()` block cancel langsung buat task_type PEMASANGAN.

## 7. Tahap 5 — Verifikasi Admin & Aktivasi (`CustomerVerificationController`)

### Approve → Aktivasi (`finalVerify`)

- Guard status implisit: view `showAdmin()` cuma terbuka untuk status `installed`/`verification_admin`.
- **Bikin Invoice AWAL** — subtotal/discount/ppn/prorate/extra fee **diinput manual oleh Admin** di form ini (bukan otomatis dari `customer_service.monthly_price` — beda dari invoice bulanan rutin, lihat [docs/billing-pembayaran](../billing-pembayaran/README.md)).
- **Generate CID** (`Pop::generateComplexCid()`) — baru di-generate di titik ini, bukan saat registrasi. Sebelum aktif, pelanggan gak punya CID.
- Set `customer.status=active`, `customer_status=aktif`, `data_completeness_status=siap_billing`; `customer_service.service_status=aktif`, `billing_status=active`, catat siapa & kapan aktivasi (`activated_by_name`, `activated_by_user_id`, `activation_time`).
- **Auto-approve** Task Pemasangan terkait yang `fop_review_status=pending`.
- Ini **satu-satunya jalur normal** yang mengubah status ke `active` — gak ada jalur otomatis lain.

### Reject (`reject`)

- Transition ke `rejected` (final, gak bisa balik) — dipakai kalau pelanggan dibatalkan total di titik verifikasi manapun. Pelanggan yang ditolak otomatis masuk list **Pelanggan Gagal** (`CustomerController` statusGroup `failed`, `whereIn('status', ['failed','rejected','gagal'])`) — no-op, gak ada kode tambahan buat ini.
- **Stage-aware sejak fix reject-sync gap (2026-07-14):** tahap ditentukan dari `$customer->status` SEBELUM `transition()` dipanggil (transition-nya sendiri ngubah status jadi `rejected`, jadi harus direkam duluan) — `installation_in_progress|revision_installation|installed|verification_admin` = tahap install → target **Task Pemasangan**; selain itu (`waiting_acc|survey_in_progress|surveyed|waiting_installation`) = tahap survey → target **Task Survey** (behavior lama). SEBELUM fix ini, `reject()` SELALU nyentuh Task Survey — kalau ditolak di tahap install, Task Pemasangan gak pernah ke-update, nyangkut permanen di antrian FOP aktif. Detail: `docs/project_verifikasi_reject_gap.md`.
- Auto-reject Task (Survey atau Pemasangan, sesuai tahap) yang masih `fop_review_status=pending` → `fop_review_status=rejected` + `reject_reason`. `Task.status` **TETAP `selesai`** (beda dari Revisi di bawah yang revert ke `in_progress`) — downstream `TaskObserver` TETAP sync `FopTask` ke `Selesai` (bukan `Cancel` — kerjaan lapangan teknisi sukses, yang ditolak keputusan bisnis customer-nya, 2 hal beda sengaja gak dicampur), cuma label histori granularnya jadi `selesai_ditolak_verifikasi`. Di Riwayat FOP, badge "Verifikasi: Ditolak" muncul sebagai kolom/badge KEDUA (overlay dari `FopTask::verificationStatus()`), terpisah dari badge status utama "Selesai" (lihat [docs/fop-task/flowchart.md § 9](../fop-task/flowchart.md#9-status-realtime--sync-task-eksekusi--foptask-task-9)).
- UI: tombol "Tolak" tersedia di halaman queue (tahap survey, sudah lama ada) DAN di halaman Verif & Pemasangan `verifications/admin.blade.php` (tahap install, **baru ditambahkan** — sebelumnya cuma ada Approve/Revisi di situ). Modal Tolak eksplisit warning "final, gak bisa dibuka lagi, harus registrasi ulang dari awal".

### Revisi (`revisi`)

- Transition ke `revision_installation` — **bukan final**, pelanggan bisa lanjut lagi (lihat tabel transisi §1: `revision_installation` bisa balik ke `verification_admin`/`installed`/`waiting_installation`).
- Update `CustomerInstallation.installation_status` balik ke `in_progress` (supaya teknisi bisa submit ulang laporan) + prepend catatan revisi ke `installation_note`.
- Auto-revert Task Pemasangan terkait: status Task balik ke `in_progress`, `fop_review_status=rejected`.

## 8. Terminasi Layanan (`CustomerTerminationController`)

- Endpoint tunggal `__invoke()` — set `customer.status=terminated`, `customer_service.service_status=berhenti`.
- **Tidak lewat `CustomerWorkflowService::transition()`** — update status langsung tanpa validasi state machine (beda dari semua transisi lain di atas). Implikasi: terminasi bisa terjadi dari status manapun (termasuk yang secara teori gak valid menurut tabel `WorkflowTransition`), dan **tidak** tercatat di `customer_status_logs` (cuma di `AuditLog` biasa, module `customers`).
- Alasan `required` di form — disimpan cuma di `AuditLog.new_values`, gak ada kolom dedicated buat alasan terminasi di tabel manapun.

## 9. Kaitan dengan Modul Lain

- **FOP Task** — `CustomerWorkflowService` auto-create `Task` (bukan `FopTask`) saat masuk `waiting_survey`/`waiting_installation`. `FopTaskController::autoSyncAndCalculatePriority()` yang kemudian sinkron `FopTask` dari status `Customer` (lihat [docs/fop-task/flowchart.md](../fop-task/flowchart.md)) — jadi ada **2 lapis auto-sync** yang jalan independen: satu bikin `Task` (level workflow), satu bikin `FopTask` (level tiket FOP).
- **FOP Task — status Task selesai + nunggu keputusan**: begitu `Task.status=selesai` (tahap survey maupun install, nunggu aksi Approve/Revisi/Tolak di modul ini), `FopTask` terkait LANGSUNG `selesai` (unifikasi enum 2026-07-20 — `FopTask.status` sekarang mirror langsung `Task.status`, bukan mapping bucket `FopTaskStatus` yang udah dihapus) — otomatis udah gak nangkring di antrian FOP aktif (selesai emang gak pernah masuk antrian, gak butuh logic exclude khusus). Nasib keputusan (Menunggu/Diterima/Ditolak) tampil sebagai badge KEDUA di Riwayat FOP (`FopTask::verificationStatus()`), + link balik ke halaman verifikasi ini kalau masih Menunggu. Keputusan sebenarnya (Approve/Revisi/Tolak) TETAP di modul Customer ini, Riwayat FOP cuma informasional. Lihat `docs/fop-task/flowchart.md` § 11 & `docs/project_verifikasi_reject_gap.md` (§ DESAIN FINAL).
- **FOP Task — cancel SRV/PSB terkunci dari Task/FopTask (2026-07-21)**: `TaskPolicy::cancel()` + `FopTaskController::update()` block cancel/dibatalkan buat task_type SURVEY/PEMASANGAN, di SEMUA role. Satu-satunya jalur sah: `CustomerSurveyController::cancel()` (§4) / `CustomerInstallationController::cancel()` (§6) di modul ini — biar `Customer.status` konsisten ikut `rejected`. Lihat `docs/fop-task/flowchart.md` § 12.
- **Billing** — `finalVerify()` satu-satunya titik yang bikin Invoice tipe `awal`. Invoice bulanan rutin (`billing:generate-monthly-invoices`) baru mulai jalan bulan **setelah** bulan aktivasi ini (guard anti-dobel-tagih, lihat [docs/billing-pembayaran/flowchart.md](../billing-pembayaran/flowchart.md)).
- **RBAC** — semua guard di atas granular per sub-fitur (`customers.detail.survey.*`, `customers.detail.installation.*`) — role Teknisi biasanya cuma punya `.update` (submit laporan), role FOP/Admin punya `.validate` (approve/reject/revisi/aktivasi).
