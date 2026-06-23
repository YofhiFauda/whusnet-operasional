# Implementation Plan: Alur Registrasi → Survey → Verifikasi Admin → Pemasangan → Aktivasi

**Project:** WHUSNET Admin Payment
**Module:** Customer Onboarding Workflow
**Tanggal:** 20 Juni 2026
**Berdasarkan:** Analisa `whusnet_operasional.sql` (live schema)

---

## 1. Ringkasan Hasil Analisa Schema

Schema existing Anda **sudah jauh lebih siap** dari perkiraan awal. Berikut peta kesesuaian tabel existing terhadap alur yang Anda jelaskan:

| Tahap Alur | Tabel Existing | Status Kesiapan |
|---|---|---|
| Registrasi (Data Diri + Dokumen + Layanan) | `customers`, `customer_addresses`, `customer_documents`, `customer_services` | ✅ Siap, perlu sedikit kolom tambahan |
| Survey (Antrian, Countdown, Form Hasil) | `customer_surveys` | ⚠️ Siap struktur, butuh kolom countdown timestamp |
| Verifikasi Admin (Menunggu ACC → Proses ke Tim) | `customers.status` + `subscription_statuses` | ⚠️ Value status belum lengkap |
| Pemasangan (Countdown, Lapor Pemasangan) | `customer_installations` | ⚠️ Sama seperti survey, butuh kolom countdown |
| Modal Data Perangkat + Speedtest | `customer_technical_details` | ✅ Sudah lengkap (sesuai keputusan Anda) |
| Modal Buat Tagihan Manual | `invoices` | ✅ Siap dipakai langsung |
| Pelanggan Baru (status final) | `customers.status = 'active'`, `customer_services` | ✅ Siap |

**Kesimpulan:** Ini **bukan fitur dari nol**. Ini adalah pekerjaan *state machine orchestration* + *real-time countdown* + *modal forms* di atas tabel yang sudah punya 80% kolom yang dibutuhkan. Effort terbesar ada di: (1) memperjelas & memperluas value status, (2) menambah kolom timestamp untuk Reverb, (3) membangun Service Layer + Event Broadcasting, (4) frontend countdown timer + modal flow.

---

## 2. Keputusan Arsitektur (Berdasarkan Konfirmasi Anda)

### 2.1 Status Utama: Extend kolom `customers.status`

Kolom `status` di `customers` akan jadi **single source of truth** untuk workflow. Value baru yang diperlukan (menggantikan/melengkapi isi `subscription_statuses` yang sudah ada):

```
registered              → Pelanggan baru daftar (existing)
waiting_survey           → Masuk antrian survey (existing)
survey_in_progress        → BARU - countdown survey berjalan ("Proses Survey")
surveyed                → Survey selesai, lapor data submitted (existing)
waiting_acc              → BARU - "Menunggu ACC" di Verifikasi Admin
waiting_installation       → Sudah di-ACC, menunggu mulai pasang (existing, reuse)
installation_in_progress     → BARU - countdown pemasangan berjalan ("Mulai Pasang")
installed                → Pemasangan selesai, lapor data submitted (existing)
verification_admin         → BARU - menunggu admin isi tagihan & verifikasi akhir
active                  → Resmi jadi Pelanggan Baru (existing)
rejected                 → Ditolak (existing, dipakai di titik manapun)
```

> **Catatan teknis penting:** karena `subscription_statuses` sudah jadi reference/lookup table dengan `workflow_order` dan `badge_color`, kita **tambah baris baru** ke tabel ini untuk 4 status baru di atas (bukan bikin enum hardcoded di kode). Ini menjaga konsistensi dengan pola yang sudah dipakai di kode Anda.

### 2.2 Source of Truth Data Perangkat: `customer_technical_details`

`customer_devices` akan **di-deprecate secara bertahap** (tidak dihapus dulu untuk backward compatibility data lama), dan seluruh form baru ("Modal Input Data Perangkat" + "Modal Speedtest") menulis ke `customer_technical_details`.

### 2.3 Countdown: Tambah kolom timestamp baru

`customer_surveys` dan `customer_installations` masing-masing dapat tambahan kolom:

```sql
-- customer_surveys
ALTER TABLE customer_surveys
  ADD COLUMN started_at TIMESTAMP NULL AFTER assigned_at,
  ADD COLUMN completed_at TIMESTAMP NULL AFTER started_at;

-- customer_installations
ALTER TABLE customer_installations
  ADD COLUMN started_at TIMESTAMP NULL AFTER assigned_at,
  ADD COLUMN completed_at TIMESTAMP NULL AFTER started_at;
```

Kolom `start_time`/`end_time` (TIME) dan `survey_date`/`scheduled_date` (DATE) **tetap dipertahankan** untuk laporan/histori harian, tapi **tidak dipakai untuk logic countdown real-time**. `started_at`/`completed_at` (TIMESTAMP penuh) adalah yang dipakai Reverb broadcast & perhitungan durasi.

---

## 3. Arsitektur Real-Time (Laravel Reverb)

```
Action "Survey Data" ditekan
        ↓
SurveyController::start()
  → UPDATE customer_surveys SET started_at = NOW(), survey_status = 'in_progress'
  → UPDATE customers SET status = 'survey_in_progress'
  → broadcast(new SurveyStarted($survey)) on channel: private-survey.{customer_id}
        ↓
Frontend (Echo listener) menerima event → mulai countdown lokal dari started_at
        ↓
Action "Lapor Data" ditekan → buka FORM ANTRIAN SURVEI
        ↓
SurveyController::complete()
  → UPDATE customer_surveys SET completed_at = NOW(), survey_status = 'completed', ...field hasil survey
  → UPDATE customers SET status = 'surveyed'
  → broadcast(new SurveyCompleted($survey))
```

**Pola yang sama** diulang untuk pemasangan (`InstallationStarted`, `InstallationCompleted`).

**Kenapa tetap broadcast meski countdown bisa dihitung dari `started_at` di client:** broadcast dipakai supaya **admin/CS lain yang sedang melihat list yang sama** (multi-user) langsung lihat status berubah tanpa refresh — bukan cuma untuk akurasi countdown si user yang menekan tombol.

Channel disarankan: `private-customer.{customer_id}.workflow` — satu channel per pelanggan, dipakai bersama untuk event survey & installation, supaya halaman Detail Pelanggan cukup subscribe 1 channel.

---

## 4. Implementation Plan per Modul

### MODUL A — Registrasi Pelanggan
**Tabel:** `customers`, `customer_addresses`, `customer_documents`, `customer_services`

Field yang Anda minta sudah hampir semua match dengan kolom existing. Gap yang ditemukan:
- `JENIS KONTRAK {sewa, beli}` → **belum ada kolom**. Perlu tambah `contract_type` enum di `customers` (catatan: `customer_services` sudah punya `contract_type` varchar tapi kosongan di data sample — sebaiknya dipakai itu, jangan duplikat di `customers`).
- `STATUS AWAL ALUR KERJA` → otomatis di-set `registered` oleh sistem saat create, tidak perlu input manual dari form (rawan human error kalau manual).
- `Rincian Estimasi Biaya Bulanan` → ini **computed field di frontend** (preview kalkulasi dari `internet_package.monthly_price - discount + ppn + other_fee`), tidak perlu disimpan terpisah — sudah match pola `customer_services.total_monthly_bill`.

### MODUL B — Survey Pelanggan
**Tabel:** `customer_surveys`

Mapping Form "Input Hasil Survey Lapangan" ke kolom existing: `required_tools`, `cable_estimation_meter`, `nearest_odp`, `survey_photo`, `house_photo`, `survey_note` — **semua sudah ada**, tidak perlu kolom baru selain `started_at`/`completed_at`.

### MODUL C — Verifikasi Admin & Teknisi
**Tabel:** `customers.status`, `customer_installations`, `customer_technical_details`, `invoices`

Ini modul paling kompleks karena 4 sub-state dalam satu halaman (Menunggu ACC → Menunggu Pemasangan → Mulai Pasang → Verifikasi Admin). Saya rekomendasikan satu Controller `VerificationController` dengan method terpisah per transisi state, bukan satu method besar — supaya gampang di-test dan di-audit lewat `audit_logs` (yang sudah ada di schema Anda).

### MODUL D — Aktivasi & Tagihan
**Tabel:** `invoices`, `customer_services`

Saat "Modal Buat Tagihan Manual" di-save:
1. Insert ke `invoices` (status pelanggan jadi `active`)
2. Update `customer_services.service_status = 'active'`, `activation_date = NOW()`, `activated_by_user_id`
3. Update `customers.status = 'active'`

---

## 5. Sprint Plan

Estimasi: **4 sprint @ 1 minggu** (asumsi 1 developer fokus). Sequencing wajib mengikuti urutan ini karena tiap sprint depend ke schema/service sprint sebelumnya.

### 🏃 Sprint 1 — Foundation: Schema + State Machine Service
**Goal:** Database siap, state machine logic teruji, belum ada UI.

| # | Task | Detail |
|---|---|---|
| 1.1 | Migration: tambah status baru ke `subscription_statuses` | Insert 4 row baru (`survey_in_progress`, `waiting_acc`, `installation_in_progress`, `verification_admin`) dengan `workflow_order` yang benar |
| 1.2 | Migration: tambah `started_at`, `completed_at` | Ke `customer_surveys` dan `customer_installations` |
| 1.3 | Migration: tambah `contract_type` ke `customer_services` jika belum ada constraint enum | Cek dulu apakah kolom existing cukup atau perlu enum constraint |
| 1.4 | Buat `CustomerWorkflowService` | Single class yang handle semua transisi status + validasi (cegah skip step, misal tidak bisa dari `registered` langsung ke `active`) |
| 1.5 | Buat `WorkflowTransition` enum/config (PHP 8.1 enum) | Definisikan allowed transitions sebagai data, bukan if-else bertingkat |
| 1.6 | Unit test state machine | Test semua transisi valid & invalid (mis. reject transisi dari `rejected` ke status manapun) |

**Definition of Done:** Migration jalan tanpa error di staging, `CustomerWorkflowService::transition()` punya test coverage untuk seluruh alur happy path + reject path.

---

### 🏃 Sprint 2 — Modul Registrasi & Survey (Backend + Frontend)
**Goal:** Pelanggan bisa didaftarkan dan masuk antrian survey dengan countdown live.

| # | Task | Detail |
|---|---|---|
| 2.1 | `CustomerRegistrationController@store` | Multi-step form: Data Diri → Dokumen → Layanan, transaksi DB (semua atau gagal semua) |
| 2.2 | Validasi form registrasi | NIK 16 digit, no HP format Indonesia, foto KTP required & mime check |
| 2.3 | Endpoint List Antrean Survey | Filter `status = waiting_survey`, dengan kolom sesuai contoh tabel Anda (ID, Nama, HP, Desa, Status, Inserted At) |
| 2.4 | `SurveyController@start` (action "Survey Data") | Set `started_at`, ubah status ke `survey_in_progress`, broadcast event |
| 2.5 | Event `SurveyStarted` + Reverb channel setup | Private channel `customer.{id}.workflow`, broadcast payload minimal (id, started_at) |
| 2.6 | `SurveyController@complete` (action "Lapor Data") | Buka Form Antrian Survei, validasi, save ke `customer_surveys`, set `completed_at`, status → `surveyed` |
| 2.7 | Event `SurveyCompleted` | Broadcast supaya list di sisi lain auto-update |
| 2.8 | Frontend: Halaman List Antrean Survey | Tabel + action buttons (Detail, Survey Data, Delete) |
| 2.9 | Frontend: Countdown component (Laravel Echo & Reverb) | Live countdown dari `started_at` via WebSocket, reusable untuk survey & pemasangan |
| 2.10 | Frontend: Form Antrian Survei modal | Field sesuai `customer_surveys`: alat, estimasi kabel, ODP terdekat, foto, catatan |
| 2.11 | Frontend: Data Survey Pelanggan di Detail Pelanggan | Tampilkan `started_at` → `completed_at` sebagai "Waktu Mulai – Waktu Selesai" |

**Definition of Done:** End-to-end bisa: daftar pelanggan → muncul di antrian survey → tekan Survey Data → countdown jalan real-time → Lapor Data → data tersimpan → status pindah ke Verifikasi Admin.

---

### 🏃 Sprint 3 — Modul Verifikasi Admin & Pemasangan
**Goal:** Alur 4-tahap di halaman Verifikasi (ACC → Proses Tim → Mulai Pasang → Verifikasi Admin).

| # | Task | Detail |
|---|---|---|
| 3.1 | Endpoint List Antrean Proses Verifikasi | Filter `status IN (surveyed, waiting_acc, waiting_installation, installation_in_progress, installed, verification_admin)`, kolom sesuai contoh Anda |
| 3.2 | `VerificationController@processToTeam` (action "Proses ke Tim") | CS konfirmasi data/paket ke pelanggan, status `surveyed` → `waiting_installation`, action label berubah jadi "Start Proses" (state di frontend, bukan field DB baru) |
| 3.3 | `InstallationController@start` (action "Start Proses") | Set `customer_installations.started_at`, status → `installation_in_progress`, broadcast `InstallationStarted` |
| 3.4 | Frontend: Countdown pemasangan | Reuse component dari Sprint 2.9 |
| 3.5 | `InstallationController@complete` (action "Lapor Pemasangan") | Buka form gabungan: ringkasan Pendaftaran + Survey (read-only) + Modal Data Perangkat + Modal Speedtest |
| 3.6 | Form Modal Data Perangkat → `customer_technical_details` | Field perangkat: ONT serial, ODP, OLT port, MAC, dst |
| 3.7 | Form Modal Speedtest → `customer_technical_details` | Field: test_upload, test_download, jitter_ms, latency_ms, packet_loss_percent |
| 3.8 | Setelah save → status `installed` → `verification_admin` | Action di list berubah dari "Lapor Pemasangan" jadi "Verifikasi" |
| 3.9 | Fitur SCAN QR di list (disebut di kolom Action) | Klarifikasi scope: QR untuk apa — absensi teknisi di lokasi, atau validasi device? *(Lihat Open Question #1 di bawah)* |
| 3.10 | Audit log integration | Setiap transisi status tercatat ke `audit_logs` (tabel sudah ada): module, action, old_values, new_values |

**Definition of Done:** Dari status `surveyed`, admin bisa proses sampai `verification_admin` dengan seluruh data perangkat & speedtest tersimpan di `customer_technical_details`.

---

### 🏃 Sprint 4 — Modul Aktivasi & Tagihan + Polish
**Goal:** Pelanggan resmi aktif, masuk list Pelanggan, sistem siap produksi.

| # | Task | Detail |
|---|---|---|
| 4.1 | `VerificationController@finalVerify` (action "Verifikasi") | Buka Modal Buat Tagihan Manual |
| 4.2 | Modal Buat Tagihan Manual → `invoices` | Generate `invoice_number`, isi subtotal/discount/ppn dari `customer_services` snapshot |
| 4.3 | Activation flow | Update `customer_services` (`service_status=active`, `activation_date`, `activated_by_user_id`), `customers.status = active` |
| 4.4 | Pelanggan masuk List Pelanggan utama | Pastikan query List Pelanggan existing sudah filter `status = active` dengan benar |
| 4.5 | Notifikasi Telegram per transisi penting | Reuse `TelegramBotService` yang sudah ada — notif saat survey selesai, pemasangan selesai, aktivasi |
| 4.6 | Cron/job: auto-reminder countdown lewat batas waktu | Misal jika survey `in_progress` > X menit tanpa lapor, kirim alert ke supervisor (pakai Laravel Scheduler) |
| 4.7 | Testing end-to-end (Registrasi → Active) | Manual QA + automated feature test Laravel |
| 4.8 | Dokumentasi alur untuk tim CS/Teknisi lapangan | SOP singkat per role |

**Definition of Done:** Satu pelanggan bisa melalui seluruh alur dari Registrasi sampai muncul di List Pelanggan dengan status Active, tagihan pertama tergenerate, dan notifikasi terkirim di tiap tahap penting.

---

## 6. Open Questions (Perlu Dijawab Sebelum/Selama Sprint 3)

1. **Fitur "SCAN QR"** di kolom Action Verifikasi Admin — belum dijelaskan fungsinya di spesifikasi Anda. Apakah ini untuk: (a) teknisi scan QR di lokasi sebagai bukti kehadiran, (b) scan QR di perangkat ONT untuk auto-fill serial number, atau (c) lainnya?
2. **Role & Permission per action** — siapa yang boleh tekan "Proses ke Tim" vs "Verifikasi" vs "Survey Data"? Tabel `roles`/`permissions` sudah ada, perlu mapping permission baru untuk setiap action di alur ini.
3. **Apa yang terjadi jika di-reject di tengah jalan** (misal saat Verifikasi Admin ternyata lokasi tidak layak)? Apakah balik ke status sebelumnya untuk re-survey, atau langsung `rejected` final?
4. **Durasi standar countdown** — apakah survey & pemasangan punya batas waktu maksimal yang sama, atau beda per jenis (KABEL vs jenis lain di `network_type`)?

**JAWAB**
1. **Sebenarnya untuk melihat kecepatan waktu survey, pemasangan, dan aktivasi jadi dari pada tekan action di web mending di SCAN. Namun fitur tersebut masih belum di implementasikan lebih jauh jadi untuk sekarang diabaikan saja**
2. **untuk Sekarang owner saja sudah cukup, karena nanti ada pengembangan RBAC**
3. **Masuk Kedalam Halaman List Pelanggan Gagal**
4. **Untuk durasi standar countdown survey & pemasangan mengikuti ketentuan yang berlaku di internal kami yaitu survey 2x24 jam dan pemasangan 2x24 jam, dan Sebetulnya Countdown ini untuk melihat seberapa cepat teknisi melakukan survey, laporan survey, pemasangan, laporan pemasangan, dan aktivasi, serta membantu mengejar target waktu**

---

## 7. Ringkasan File/Kode yang Akan Dihasilkan

```
app/
  Enums/WorkflowStatus.php                 (Sprint 1)
  Services/CustomerWorkflowService.php     (Sprint 1)
  Events/SurveyStarted.php                 (Sprint 2)
  Events/SurveyCompleted.php               (Sprint 2)
  Events/InstallationStarted.php           (Sprint 3)
  Events/InstallationCompleted.php         (Sprint 3)
  Http/Controllers/CustomerRegistrationController.php  (Sprint 2)
  Http/Controllers/SurveyController.php    (Sprint 2)
  Http/Controllers/VerificationController.php  (Sprint 3)
  Http/Controllers/InstallationController.php  (Sprint 3)
database/migrations/
  xxxx_add_workflow_statuses.php           (Sprint 1)
  xxxx_add_countdown_timestamps.php        (Sprint 1)
resources/views/reverb/
  survey-queue.blade.php                   (Sprint 2)
  countdown-timer.blade.php                (Sprint 2, reused Sprint 3)
  verification-queue.blade.php             (Sprint 3)
```

---

*Dokumen ini disusun berdasarkan pembacaan langsung schema `whusnet_operasional.sql` — bukan asumsi. Semua referensi kolom di atas adalah kolom yang benar-benar ada di database Anda saat ini.*
