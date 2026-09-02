# Flowchart — Customer Verifikasi & Onboarding Lifecycle

## 1. State Machine Keseluruhan

```
registered ──▶ waiting_survey ──▶ survey_in_progress ──┬──▶ waiting_acc ──┐
   │                │                    │              └──▶ surveyed ───┤
   │                │                    │                               │
 rejected        rejected             rejected                           ▼
(final)          (final)              (final)                waiting_installation
                                                                        │
                     ┌──────────────────────────────────────────────────┘
                     ▼
          installation_in_progress ──┬──▶ verification_admin ──┬──▶ active
                     │                │         │              ├──▶ revision_installation
                  installed ──────────┘         ├──▶ waiting_installation (gagal/revisi)
                     │                           └──▶ rejected (final)
                  rejected (final)

active ──┬──▶ suspended ──┬──▶ active (reaktivasi)
         │                └──▶ terminated (final)
         └──▶ terminated (final)
         └──▶ installed / verification_admin / revision_installation (kasus khusus re-verifikasi)
```

Catatan: `terminated` via `CustomerTerminationController` **tidak lewat state machine ini** — bisa terjadi dari status manapun tanpa validasi (lihat [business-logic.md §8](business-logic.md#8-terminasi-layanan-customerterminationcontroller)).

Catatan lain (2026-08-21): edge `registered → waiting_acc` di enum ada khusus buat **Skip Survey** (§2a) — di alur nyata gak lewat state machine ini juga (status di-set langsung di `Customer::create()`, bukan lewat `CustomerWorkflowService::transition()`), tapi edge-nya tetap didaftarkan biar sah kalau ada kode lain yang nanti transisi eksplisit dari `registered`.

## 2. Alur Registrasi → Survey

```
Sales/Admin isi form registrasi (POST /customers)
        │
        ▼
Customer::create() — status = waiting_survey (LANGSUNG, skip 'registered')
customer_code di-generate dari Pop sequence
foto KTP/rumah/kontrak diupload
        │
        ▼
Customer muncul di antrean survey (/surveys/queue)
        │
        ▼
Teknisi klik "Mulai Survey" (POST /customers/{c}/survey/start)
        │
        ▼
   status == registered? ──ya──▶ auto-transition ke waiting_survey dulu
        │
        ▼
   status != waiting_survey? ──ya──▶ TOLAK
        │ tidak
        ▼
   cek konflik: teknisi ada Task in_progress lain? ──ya──▶ TOLAK
        │ tidak
        ▼
CustomerSurvey.started_at = now()
transition → survey_in_progress
Task Survey → start()
broadcast SurveyStarted
        │
        ▼
Teknisi isi & submit laporan (POST /customers/{c}/survey)
        │
        ▼
   status != survey_in_progress? ──ya──▶ TOLAK
        │ tidak
        ▼
simpan CustomerSurvey (foto ODP+rumah wajib, hitung surveyor 1/2/3 dari anggota tim)
  + requested_installation_date (opsional, harus >= hari ini)
        │
        ▼
sync task_materials kind=estimasi ke FopTask kategori SURVEY
  cable_estimation_meter → 1 baris kabel_dropcore (kalau belum ada baris dropcore manual)
  FopTask SURVEY belum ada? → baris material dilewat, laporan TETAP tersimpan
        │
        ▼
   survey_status == completed? ──tidak──▶ selesai (data tersimpan, status tetap)
        │ ya
        ▼
Task Survey → complete()
transition → waiting_acc
broadcast SurveyCompleted + notifikasi Telegram
```

### 2a. Skip Survey — Sales input data survey langsung (baru 2026-08-21)

```
Sales isi form registrasi, centang "Skip Survey" (POST /customers)
        │
        ▼
   punya permission customers.registration.skip_survey? ──tidak──▶ TOLAK (403,
        │                                                           CustomerRegistrationRequest::authorize())
        │ ya
        ▼
   latitude/longitude, ODP terdekat, estimasi kabel,
   tingkat kesulitan, foto rumah, foto ODP semua terisi? ──tidak──▶ TOLAK (422, required_if:skip_survey,1)
        │ ya
        ▼
Customer::create() — status = waiting_acc (LANGSUNG, skip waiting_survey/
  survey_in_progress/surveyed sepenuhnya)
customer_code di-generate dari Pop sequence (sama seperti alur normal)
foto rumah diupload via FileUploadService::uploadSurveyPhoto() folder 'house'
        │
        ▼
CustomerSurvey::create() — survey_status=completed, technician_id=user Sales,
  nearest_odp/cable_estimation_meter dari input, survey_note diberi tag
  "Diinput oleh Sales saat Registrasi (Skip Survey)"
        │
        ▼
Task/FopTask kategori SURVEY TIDAK dibuat sama sekali (beda dari §6 auto-sync)
        │
        ▼
Customer muncul LANGSUNG di antrean ACC Admin (/verifications/queue) — gak
pernah mampir /surveys/queue sama sekali. Lanjut ke §3 seperti pelanggan
survey normal.
```

Guard permission `customers.registration.skip_survey` dua lapis: `@can` sembunyikan checkbox+field di blade (`customers/create.blade.php`), DAN `CustomerRegistrationRequest::authorize()` — klien yang maksa kirim `skip_survey=1` tanpa permission dapat 403, bukan lolos dengan field survey yang jadi wajib padahal gak relevan.

### 2b. Batalkan Survey — sebelum/selagi dikerjakan (baru 2026-07-21)

```
FOP/Admin klik "Batalkan" (tab Survey Customer, ATAU tombol baru di /surveys/queue)
        │
        ▼
   status not in [waiting_survey, survey_in_progress]? ──ya──▶ TOLAK (422)
        │ tidak
        ▼
Isi alasan (wajib) → POST /customers/{c}/survey/cancel
        │
        ▼
Cari Task Survey terkait (belum selesai/dibatalkan)
        │
        ▼
TaskService::cancel() → Task.status=dibatalkan (sync ke FopTask via TaskObserver)
CustomerSurvey.survey_status = failed
transition → rejected (masuk List Pelanggan Gagal)
```

**Catatan:** cancel dari halaman Task/tabel FopTask kategori Survey SENGAJA di-block (`TaskPolicy::cancel()` + `FopTaskController::update()` guard, lihat `docs/fop-task/flowchart.md` § 12) — jalur di atas SATU-SATUNYA cara sah batalin Survey, biar `Customer.status` konsisten ikut ke-update.

## 3. Alur Verifikasi Survey → Proses ke Tim Pemasangan

```
FOP buka /verifications/queue (status: waiting_acc, surveyed, dst)
        │
        ▼
Klik "Proses ke TIM" (POST /verifications/{c}/process-to-team)
        │
        ▼
   status in [waiting_acc, surveyed]? ──tidak──▶ TOLAK (422)
        │ ya
        ▼
CustomerInstallation dibuat/dipakai (status=scheduled)
transition → waiting_installation
  (efek: auto-create Task Pemasangan kalau belum ada)
auto-approve Task Survey (fop_review_status: pending → approved)
```

## 4. Alur Pemasangan

```
Teknisi klik "Mulai Pemasangan" (POST /customers/{c}/installation/start)
        │
        ▼
   status != waiting_installation? ──ya──▶ TOLAK
        │ tidak
        ▼
   cek konflik jadwal teknisi (sama seperti Survey)? ──ya──▶ TOLAK
        │ tidak
        ▼
CustomerInstallation.started_at = now(), status=in_progress
transition → installation_in_progress
Task Pemasangan → start(), broadcast InstallationStarted
        │
        ▼
Teknisi isi & submit laporan (POST /customers/{c}/installation)
        │
        ▼
   status not in [installation_in_progress, revision_installation]? ──ya──▶ TOLAK
        │ tidak
        ▼
   installation_status == completed?
        │
        ├─ ya → cek wajib: foto pemasangan + kontrak + TTD + speedtest ada?
        │         │ tidak lengkap ──▶ TOLAK per-field
        │         │ lengkap
        │         ▼
        │      cek wajib: minimal 1 baris material terpakai (qty>0 & barang terisi)?
        │         │ kosong ──▶ TOLAK (errors: materials)
        │         │ ada
        │         ▼
        │      simpan CustomerTechnicalDetail + CustomerDevice (dobel-tulis)
        │      sync task_materials kind=terpakai ke FopTask kategori PEMASANGAN
        │      hitung speed_conformity_percent
        │      Task Pemasangan → complete()
        │      transition → installed → verification_admin (2x berturutan)
        │      broadcast InstallationCompleted + notifikasi Telegram
        │
        ├─ failed → transition → waiting_installation ("butuh revisi")
        │
        └─ progress (belum completed/failed) → simpan data, status tetap
```

### 4b. Batalkan Pemasangan — sebelum/selagi dikerjakan (baru 2026-07-21)

```
Admin/FOP klik "Batalkan Pemasangan" (tab Pemasangan Customer)
        │
        ▼
   status not in [waiting_installation, installation_in_progress,
                  revision_installation]? ──ya──▶ TOLAK (422)
        │ tidak
        ▼
Isi alasan (wajib) → POST /customers/{c}/installation/cancel
        │
        ▼
Cari Task Pemasangan terkait (belum selesai/dibatalkan)
        │
        ▼
TaskService::cancel() → Task.status=dibatalkan (sync ke FopTask via TaskObserver)
CustomerInstallation.installation_status = failed
transition → rejected (masuk List Pelanggan Gagal)
```

Permission baru: `customers.detail.installation.reject`. Sama kayak Survey — cancel dari Task/FopTask kategori PSB di-block, jalur ini satu-satunya yang sah.

## 5. Alur Verifikasi Admin (Aktivasi / Reject / Revisi)

**Update 2026-07-14 (fix reject-sync gap):** tombol **"Tolak"** sekarang ADA di halaman ini juga (`verifications/admin.blade.php`) — sebelumnya reject cuma bisa dipicu dari halaman queue tahap survey (`verifications/queue.blade.php`). `reject()` sekarang stage-aware: infer tahap dari `customer->status` SEBELUM transition, target Task yang bener (Survey atau Pemasangan). Detail: `docs/project_verifikasi_reject_gap.md`.

```
FOP/Admin buka /verifications/{c}/admin (status: installed atau verification_admin)
        │
        ├──────────────┬──────────────────┬──────────────────┐
        ▼              ▼                  ▼                  ▼
   Approve         Reject             Revisi              (batal, tetap
   (finalVerify)   (reject, BARU      (revisi)             di halaman)
        │           di halaman ini)       │
        ▼              │                  ▼
  Buat Invoice AWAL     ▼              CustomerInstallation
  (input manual     transition        .status = in_progress
   subtotal/fee)     → rejected       + prepend catatan revisi
        │            (FINAL, gak      transition → revision_installation
        ▼             ada reopen)         │
  Generate CID           │                ▼
  (Pop::generate         ▼            auto-revert Task Pemasangan
   ComplexCid)      auto-reject       → in_progress, fop_review_status=rejected
        │           Task PEMASANGAN
        │           (bukan Survey lagi —
        │            stage-aware sejak fix)
        │           fop_review_status=rejected,
        │           Task.status TETAP selesai
        │                │
        │                ▼
        │           masuk list Pelanggan Gagal
        │           + FopTask TETAP → Selesai (kerjaan teknisi sukses,
        │             independen dari keputusan bisnis) — badge KEDUA
        │             "Verifikasi: Ditolak" di Riwayat FOP (overlay,
        │             bukan ganti bucket status utama)
        ▼
  customer.status=active, customer_status=aktif,
  data_completeness_status=siap_billing
  customer_service: service_status=aktif, billing_status=active
        │
        ▼
  auto-approve Task Pemasangan pending
  notifikasi Telegram "Pelanggan Aktif"
```

**Reject di tahap Survey** (dari `verifications/queue.blade.php`, tombol Batalkan/Gagal — behavior lama, gak berubah): sama persis alur di atas, tapi target Task **Survey** (bukan Pemasangan), karena `customer->status` masih `waiting_acc|survey_in_progress|surveyed|waiting_installation` pas reject dipanggil.

**Tombol Delete dihapus dari `/verifications/queue` (2026-07-20):** sebelumnya tiap baris (semua status) punya icon Delete → `customers.destroy` (hard-delete permanen). Diganti icon "Batal" yang manggil modal reject yang sama seperti di atas — jadi SEMUA status di antrean ini sekarang punya jalur batal resmi (sebelumnya cuma `surveyed` yang punya).

## 5b. Aktivasi Manual — pelanggan migrasi legacy (baru 2026-07-20)

```
Admin buka detail Customer, klik "Aktivasi Manual"
        │
        ▼
   punya permission customers.detail.installation.activate? ──tidak──▶ tombol gak muncul
        │ ya
        ▼
   customer.old_customer_id kosong? ──ya──▶ TOLAK (bukan hasil migrasi)
        │ tidak
        ▼
   ada Task type Survey/Pemasangan buat customer ini? ──ya──▶ TOLAK
        │                                                     (harus lewat alur normal §2-§5)
        │ tidak
        ▼
   customerService.request_status !== 'ACTIVE'? ──ya──▶ TOLAK
        │                                               (di sistem lama pun masih stuck SRV/PSB,
        │                                                harus lewat alur normal, bukan bypass)
        │ tidak
        ▼
   status udah active/siap_billing? ──ya──▶ TOLAK (redundant)
        │ tidak
        ▼
   is_ready_billing (data wajib lengkap)? ──tidak──▶ tombol muncul tapi disabled
        │ ya
        ▼
Generate CID (Pop::generateComplexCid())
customer.status=active, customer_status=aktif, data_completeness_status=siap_billing
customer_service.service_status=aktif, billing_status=active
   (BEDA dari finalVerify §5: gak bikin Invoice awal, gak lewat
    CustomerWorkflowService::transition() — update langsung, gak
    tercatat di customer_status_logs)
```

## 5c. Migrasi Legacy → Mapping Status (baru 2026-07-20)

```
php artisan app:import-legacy-sql
        │
        ▼
CustomerController::validateImport() → confirmImport()
        │
        ▼
tiap baris services: mapLegacyServiceStatus(STATUS legacy)
        │
        ├─ PENGAJUAN (belum disurvey) ─────────▶ waiting_survey
        ├─ DISURVEI (survey+ACC selesai,
        │            DIPROSES masih kosong) ───▶ waiting_installation
        ├─ ACTIVE ──────────────────────────────▶ active (+ generate CID)
        ├─ GAGAL ───────────────────────────────▶ rejected
        └─ PUTUS ───────────────────────────────▶ terminated
        │
        ▼
customer->updateQuietly([...])  ← LANGSUNG, BUKAN lewat transition()
        │
        ▼
   $serviceStatus == rejected atau terminated?
        │ ya
        ▼
Bikin AuditLog manual (module+action match transition asli)
  alasan = CustomerService.reason (dari ALASAN legacy)
  tanggal = status_changed_at (TGLSELESAI, fallback updated_at
            baris legacy — bukan now())
        │
        ▼
List Pelanggan Gagal / Putus Langganan bisa nampilin
alasan+tanggal pelanggan migrasi (sebelumnya kosong,
karena updateQuietly() gak pernah nulis AuditLog)
```

`contract_type` (Sewa/Beli) diambil dari kolom `STATUSALAT` (bukan `STATUSLANGGANAN`, yang kosong di semua data legacy) — dinormalisasi lowercase saat import.

## 6. Auto-Sync Task Lintas Layer

```
CustomerWorkflowService::transition()
        │
        ▼
target status ∈ {waiting_survey, waiting_installation}?
        │ ya
        ▼
Task (tabel 'tasks', eksekusi teknisi) dengan tipe sama & status aktif sudah ada?
        │ tidak ada
        ▼
Task::create() — status=pending
        │
        ▼
   (independen, lapis lain)
   FopTaskController::autoSyncAndCalculatePriority() — jalan tiap GET /fop-tasks
        │
        ▼
   Customer status waiting_survey/waiting_installation & belum ada FopTask aktif kategori sama?
        │
        ▼
   FopTask::create() — tiket FOP baru, prioritas dihitung dari SLA
```

Lihat [docs/fop-task/flowchart.md](../fop-task/flowchart.md) untuk detail lapis kedua ini.

Catatan: target `waiting_acc` **tidak** termasuk di daftar ini — makanya Skip Survey (§2a) yang langsung set status `waiting_acc` gak pernah memicu auto-create Task/FopTask kategori SURVEY. Task/FopTask kategori PEMASANGAN tetap ke-trigger normal begitu customer nanti sampai `waiting_installation` (via ACC Admin, §3), gak ada percabangan khusus di sana.
