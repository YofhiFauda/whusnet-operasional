# Database Schema: Pendaftaran Pelanggan

Modul Pendaftaran Pelanggan melibatkan modifikasi dan pembuatan relasi pada tabel-tabel berikut:

## `customers`
Tabel utama untuk master pelanggan.
- `id` (PK)
- `cid` (ID unik pelanggan, di-generate dari gabungan POP dan urutan ketika verifikasi akhir selesai)
- `status` (Enum: `draft`, `waiting_survey`, `survey_in_progress`, `surveyed`, `waiting_installation`, `installation_in_progress`, `installed`, `verification_admin`, `active`, `suspended`, `inactive`)
- `pop_id` (FK to `pops`)

## `customer_surveys`
Penyimpanan hasil survey lapangan.
- `id` (PK)
- `customer_id` (FK)
- `started_at` (Waktu mulai survey)
- `completed_at` (Waktu selesai survey)
- `fat_distance`, `fat_name`, `coordinates` dll.

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

## State Machine
Alur tabel dikelola menggunakan `App\Services\CustomerWorkflowService` yang merekam proses menggunakan tabel-tabel di atas. Audit perubahan state juga tersimpan di tabel `audit_logs`.
