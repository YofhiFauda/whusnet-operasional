# Database Schema: Pendaftaran Pelanggan

Modul Pendaftaran Pelanggan melibatkan modifikasi dan pembuatan relasi pada tabel-tabel berikut:

## `customers`
Tabel utama untuk master pelanggan.
- `id` (PK)
- `cid` (ID unik pelanggan, di-generate dari gabungan POP dan urutan ketika verifikasi akhir selesai)
- `status` — nilai dari `App\Enums\WorkflowTransition` (14 state): `registered`, `waiting_survey`, `survey_in_progress`, `surveyed`, `waiting_acc`, `waiting_installation`, `installation_in_progress`, `installed`, `verification_admin`, `revision_installation`, `active`, `suspended`, `terminated`, `rejected`. Registrasi normal langsung set `waiting_survey`; **Skip Survey** langsung set `waiting_acc` (lompat `waiting_survey`/`survey_in_progress`/`surveyed`) — lihat `WorkflowTransition::REGISTERED->allowedNextTransitions()`.
- `pop_id` (FK to `pops`)

## `customer_surveys`
Penyimpanan hasil survey lapangan. Baris ini SELALU ada begitu status lewat `survey_in_progress` (normal) atau begitu Skip Survey dipakai (dibuat langsung `completed` saat registrasi) — kolom sama, sumber datanya beda.
- `id` (PK)
- `customer_id` (FK)
- `survey_status` (`pending` / `completed` / `failed`)
- `nearest_odp`, `cable_estimation_meter` (estimasi jarak & kabel ke ODP)
- `house_photo`, `survey_photo` (path disk `public`, folder `surveys/rumah` & `surveys/odp` — sama persis dipakai jalur teknisi maupun Skip Survey, lewat `FileUploadService::uploadSurveyPhoto()`)
- `survey_note` (teks bebas — juga nampung "Tingkat Kesulitan: …" dan, untuk Skip Survey, tag "Diinput oleh Sales saat Registrasi (Skip Survey)")
- `technician_id` (siapa yang mengisi — teknisi survey normal, **atau user Sales** kalau lewat Skip Survey)
- `requested_installation_date` (opsional, tanggal request pemasangan dari pelanggan)
- `started_at`, `completed_at` (kosong/null untuk baris hasil Skip Survey — gak ada kunjungan lapangan beneran)

Titik koordinat pelanggan sendiri **bukan** kolom tabel ini — tersimpan di `customer_addresses.latitude`/`longitude` (lihat di bawah), diisi saat registrasi dan **wajib** kalau Skip Survey aktif.

## `customer_addresses`
Alamat & titik koordinat instalasi.
- `id` (PK)
- `customer_id` (FK)
- `latitude`, `longitude` — opsional di registrasi normal, **wajib** kalau Skip Survey aktif (`CustomerRegistrationRequest`: `required_if:skip_survey,1`)
- `full_address`, `city`, `district`, `village` (+ `*_id` FK ke master wilayah)

## `customer_installations`
Penyimpanan data log proses instalasi.
- `id` (PK)
- `customer_id` (FK)
- `started_at` (Waktu mulai pemasangan)
- `completed_at` (Waktu selesai pemasangan)

## `customer_technical_details` (Data Teknis / Perangkat)
Menyimpan spesifikasi teknis dan perangkat yang digunakan pelanggan.
- `id` (PK)
- `customer_id` (FK)
- `router_brand`, `router_sn`, `modem_sn`
- `olt_number`, `olt_slot`, `vlan`
- `speedtest_ping`, `speedtest_download`, `speedtest_upload`

## `customer_services`
Layanan atau langganan yang aktif pada pelanggan.
- `id` (PK)
- `customer_id` (FK)
- `internet_package_id` (FK)
- `status` (aktif / non_aktif)
- `total_monthly_bill` (Harga final berlangganan)

## `invoices`
Tagihan pertama akan di-generate ketika pelanggan diaktivasi.
- `id` (PK)
- `customer_id` (FK)
- `internet_package_id` (FK)
- `pop_id` (FK)
- `billing_period`, `issue_date`, `due_date`
- `subtotal`, `discount`, `ppn`, `total_amount`
- `status` (`belum_dibayar`)

## `tasks` / `fop_tasks` (Survey)
Registrasi normal auto-create satu baris `tasks` (`task_type=SURVEY`, `status=pending`) + `fop_tasks` (`category=SURVEY`, `status=draft`) sebagai anchor antrean teknisi & `task_materials`/`task_work_tools`. **Skip Survey TIDAK membuat baris ini sama sekali** — gak ada teknisi yang perlu ditugaskan survei, jadi gak ada anchor yang perlu dibuat.

## State Machine
Alur tabel dikelola menggunakan `App\Services\CustomerWorkflowService` yang merekam proses menggunakan tabel-tabel di atas. Audit perubahan state juga tersimpan di tabel `audit_logs`.

Pengecualian: transisi awal saat registrasi (`waiting_survey` maupun `waiting_acc` untuk Skip Survey) di-set **langsung** di `Customer::create()`, bukan lewat `CustomerWorkflowService::transition()` — customer belum ada baris buat ditransisikan. `WorkflowTransition::REGISTERED->allowedNextTransitions()` tetap mendaftarkan `WAITING_ACC` sebagai edge sah, konsisten kalau ada kode lain yang nanti perlu transisi eksplisit dari `registered`.
