# GEMINI.md

## Project Name
Website Billing ISP Berbasis Master Data Pelanggan

## Main Product Goal
Bangun website billing ISP internal yang menjadikan master data pelanggan lengkap sebagai pusat sistem.

Prinsip utama sistem:

Pelanggan
→ Paket Internet
→ Layanan Aktif
→ Tagihan
→ Pembayaran
→ Laporan

Billing tidak boleh berdiri sendiri tanpa data pelanggan.

## Required Reading Before Any Task
Sebelum mengerjakan task apa pun, AI wajib membaca file berikut:

1. `docs/PROJECT_CONTEXT.md`
2. `docs/MVP_SCOPE.md`
3. `docs/IMPLEMENTATION_PLAN.md`
4. `docs/TASKS.md`
5. `docs/ACCEPTANCE_CRITERIA.md`
6. `docs/DATABASE_CONCEPT.md`
7. `docs/PROMPTS.md`
8. `docs/TOMORROW_START.md`
9. `docs/Sprint 11 — Advanced Hierarchical RBAC Planning & Documentation.md`
10. `docs/RBAC_MATRIX.md`
11. `docs/analisa-rbac-dinamis-whusnett.md`
12. `docs/AGENT_EXECUTION_GUIDEE.md`

Jika tersedia, baca juga PRD asli di:
- `docs/PRD.md`
- atau file PRD asli dari user.

## Main Development Rule
AI hanya boleh mengerjakan task yang sedang aktif di `docs/TASKS.md`.

AI tidak boleh:
- loncat sprint,
- membuat fitur di luar MVP,
- membuat asumsi sendiri tanpa konfirmasi,
- mengerjakan modul berikutnya sebelum modul saat ini selesai,
- mengubah file yang tidak berhubungan dengan task aktif.

## MVP Development Order
Urutan development wajib:

1. Login
2. User Management
3. Role
4. Permission
5. RBAC dasar
6. POP/Cabang
7. Assign user ke POP
8. Master Paket Internet
9. Input Manual Pelanggan
10. Import Excel/CSV Pelanggan Lama
11. Validasi Kelengkapan Data Pelanggan
12. Aktivasi Layanan Pelanggan
13. Tagihan Manual
14. Pembayaran
15. Dashboard
16. Laporan Sederhana
17. Audit Log
18. Data teknis pelanggan setelah billing dasar stabil

## Features Not Allowed in MVP
Fitur berikut tidak boleh dibuat pada tahap MVP:

- Integrasi MikroTik
- Payment gateway
- Auto suspend pelanggan
- Auto generate tagihan bulanan kompleks
- WhatsApp notification
- Ticketing gangguan kompleks
- Monitoring OLT/SNMP
- Inventory perangkat kompleks
- Aplikasi mobile teknisi
- Multi-company
- Sistem akuntansi kompleks
- Integrasi otomatis router/OLT

Jika user meminta fitur di atas, AI wajib menjawab:

"Fitur ini termasuk post-MVP. Berdasarkan scope MVP, fitur ini belum dikerjakan sekarang. Apakah Anda ingin tetap memasukkannya atau tetap mengikuti urutan MVP?"

## Current Product Logic
Sistem harus mengikuti logika:

1. POP/Cabang dibuat lebih dahulu.
2. User dan hak akses dibuat dengan RBAC.
3. Paket internet dibuat sebagai master layanan.
4. Pelanggan dimasukkan manual atau import Excel/CSV.
5. Sistem memvalidasi kelengkapan data pelanggan.
6. Pelanggan yang belum lengkap tetap boleh disimpan.
7. Pelanggan yang belum lengkap tidak boleh masuk billing aktif.
8. Pelanggan lengkap dapat diubah menjadi siap billing.
9. Tagihan dibuat berdasarkan pelanggan aktif dan paket aktif.
10. Pembayaran harus terhubung ke invoice dan pelanggan.
11. Status invoice berubah berdasarkan pembayaran.
12. Semua perubahan penting dicatat di audit log.

## Task Execution Protocol
Setiap menjalankan task, AI wajib melakukan langkah berikut:

### 1. Scope Check
Sebelum coding, jawab:

- Task ini masuk sprint berapa?
- Modul apa yang disentuh?
- Requirement PRD mana yang relevan?
- File apa saja yang akan dibuat/diubah?
- File apa saja yang tidak boleh disentuh?
- Acceptance criteria apa yang harus terpenuhi?

### 2. Implementation Plan
AI wajib membuat rencana singkat:

- Tujuan task
- Langkah pengerjaan
- Dependency
- Risiko
- Cara test

### 3. Coding
AI hanya boleh coding setelah scope check dan implementation plan jelas.

### 4. Review
Setelah coding, AI wajib menjelaskan:

- File yang dibuat/diubah
- Alasan perubahan
- Cara test manual
- Status acceptance criteria
- Apakah ada risiko atau TODO

### 5. Update Task
Setelah selesai, AI wajib mengupdate `docs/TASKS.md`:

- Task selesai dipindah ke Done
- Task berikutnya dipindah ke In Progress
- Catatan risiko dimasukkan ke Blocked atau Notes

## Coding Style Rules
Gunakan struktur kode yang sederhana, mudah dibaca, dan sesuai kebutuhan MVP.

Hindari:
- overengineering,
- membuat service terlalu kompleks sebelum dibutuhkan,
- membuat fitur otomatis sebelum flow manual stabil,
- membuat tabel yang tidak diperlukan MVP,
- mencampur banyak modul dalam satu task.

## Database Rules
Database harus mengikuti konsep:

- users
- roles
- permissions
- role_permissions
- pops
- user_pops
- internet_packages
- customers
- customer_addresses
- customer_services
- customer_surveys
- customer_installations
- customer_devices
- customer_documents
- invoices
- payments
- import_batches
- import_errors
- audit_logs

Jangan membuat tabel post-MVP seperti:
- mikrotik_routers
- olt_devices
- snmp_logs
- payment_gateway_transactions
- whatsapp_notifications
- technician_mobile_sessions

kecuali user secara eksplisit memutuskan masuk post-MVP.

## RBAC Rules
Minimal role:

1. Owner
2. Admin Pusat
3. Admin Cabang
4. Finance/Kasir
5. Teknisi
6. Customer Service

Aturan utama:

- Owner memiliki akses penuh.
- Admin Pusat dapat mengelola semua cabang.
- Admin Cabang hanya melihat POP/cabang yang ditugaskan.
- Finance/Kasir fokus pada tagihan dan pembayaran.
- Teknisi fokus pada data survey, pemasangan, dan perangkat.
- Customer Service hanya boleh melihat data pelanggan dan mengubah data kontak terbatas.
- Teknisi tidak boleh mencatat pembayaran.
- Finance tidak boleh mengubah data modem.
- CS tidak boleh mengubah nominal tagihan.
- Admin cabang tidak boleh melihat data cabang lain.

## Customer Data Rules
Pelanggan boleh disimpan walaupun belum lengkap.

Status kelengkapan data:

1. Draft
2. Perlu Dilengkapi
3. Lengkap
4. Siap Billing

Pelanggan hanya bisa masuk billing jika field wajib berikut terisi:

- Nama lengkap
- Nomor HP
- Alamat lengkap
- Desa/Kelurahan
- Kecamatan
- Kota/Kabupaten
- POP/Cabang
- Paket internet
- Harga bulanan
- Tanggal aktivasi
- Tanggal jatuh tempo
- Status layanan

## Billing Rules
Tagihan tidak boleh dibuat manual dari nol.

Tagihan harus berasal dari:

Pelanggan Aktif
+ Paket Aktif
+ Harga Layanan
+ Periode Tagihan

Aturan:

- Tagihan hanya bisa dibuat untuk pelanggan aktif atau siap billing.
- Tagihan mengambil harga dari layanan pelanggan.
- Tagihan memiliki periode.
- Tagihan memiliki tanggal jatuh tempo.
- Tagihan tidak boleh dobel untuk periode yang sama.
- Tagihan lunas tidak boleh dihapus sembarangan.

## Payment Rules
Pembayaran wajib terhubung ke:

- invoice
- pelanggan
- POP/cabang

Aturan:

- Jika nominal bayar sama dengan total tagihan, invoice menjadi lunas.
- Jika nominal bayar kurang dari total tagihan, invoice menjadi dibayar sebagian.
- Jika pembayaran ditolak, invoice tidak boleh berubah menjadi lunas.
- Perubahan pembayaran wajib masuk audit log.

## Output Format After Every Task
Setiap selesai task, AI wajib menjawab dengan format:

```md
## Task Selesai
Nama task:

## File Diubah
- file 1
- file 2

## Alasan Perubahan
Penjelasan singkat.

## Cara Test
1. ...
2. ...
3. ...

## Acceptance Criteria
- [x] Kriteria 1
- [x] Kriteria 2
- [ ] Kriteria belum selesai

## Risiko / Catatan
Catatan jika ada.

## Next Task
Task berikutnya sesuai `docs/TASKS.md`.
Stop Condition

AI wajib berhenti dan bertanya jika:

Requirement ambigu.
Task menyentuh modul di luar sprint aktif.
Ada conflict antara PRD dan instruksi user.
Ada kebutuhan membuat fitur post-MVP.
Perubahan berpotensi merusak data penting.


