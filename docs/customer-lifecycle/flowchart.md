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
        │
        ▼
   survey_status == completed? ──tidak──▶ selesai (data tersimpan, status tetap)
        │ ya
        ▼
Task Survey → complete()
transition → waiting_acc
broadcast SurveyCompleted + notifikasi Telegram
```

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
        │      simpan CustomerTechnicalDetail + CustomerDevice (dobel-tulis)
        │      hitung speed_conformity_percent
        │      Task Pemasangan → complete()
        │      transition → installed → verification_admin (2x berturutan)
        │      broadcast InstallationCompleted + notifikasi Telegram
        │
        ├─ failed → transition → waiting_installation ("butuh revisi")
        │
        └─ progress (belum completed/failed) → simpan data, status tetap
```

## 5. Alur Verifikasi Admin (Aktivasi / Reject / Revisi)

```
FOP/Admin buka /verifications/{c}/admin (status: installed atau verification_admin)
        │
        ├──────────────┬──────────────────┬──────────────────┐
        ▼              ▼                  ▼                  ▼
   Approve         Reject             Revisi              (batal, tetap
   (finalVerify)   (reject)           (revisi)             di halaman)
        │              │                  │
        ▼              ▼                  ▼
  Buat Invoice AWAL   transition       CustomerInstallation
  (input manual       → rejected      .status = in_progress
   subtotal/fee)       (FINAL)        + prepend catatan revisi
        │              │              transition → revision_installation
        ▼              ▼                  │
  Generate CID      auto-reject           ▼
  (Pop::generate     Task Survey      auto-revert Task Pemasangan
   ComplexCid)        pending          → in_progress, fop_review_status=rejected
        │
        ▼
  customer.status=active, customer_status=aktif,
  data_completeness_status=siap_billing
  customer_service: service_status=aktif, billing_status=active
        │
        ▼
  auto-approve Task Pemasangan pending
  notifikasi Telegram "Pelanggan Aktif"
```

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
