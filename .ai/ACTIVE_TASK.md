# Active Task

Source of truth: docs/TASKS.md
Last synced at: 2026-06-15 10:07:30

Current Sprint: Fokus Sementara — Migrasi Legacy Pelanggan dan Billing
Current Module: Import Legacy Pelanggan, Paket, Layanan, Tagihan, Pembayaran
Current Task: MIG-T001 — Migrasi Legacy Pelanggan dan Billing dari sand_db_sandya.sql
Status: In Progress

## Task Detail

### MIG-T001 — Migrasi Legacy Pelanggan dan Billing dari sand_db_sandya.sql
Status: In Progress

Sprint/Module:
Fokus Sementara — Migrasi Legacy Pelanggan dan Billing.

Tujuan:
Menyesuaikan import Excel multi-sheet agar cocok dengan struktur dan karakter data lama dari `sand_db_sandya.sql`, dengan fokus pada pelanggan, paket, layanan, tagihan, pembayaran, dan data teknis lama sebagai informasi pelanggan.

Acuan Scope:
- `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/ANALISIS_SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/PLAN_MIGRASI_PELANGGAN_BILLING.md`
- `sand_db_sandya.sql`

Scope Masuk:
- [ ] Sesuaikan import Excel multi-sheet dengan sheet `customers`, `packages`, `services`, `technical_details`, `invoices`, dan `payments`.
- [ ] Mapping data pelanggan lama dari `pengguna` ke master pelanggan baru.
- [ ] Mapping paket lama dari `paket` ke `internet_packages`.
- [ ] Mapping layanan/request lama dari `prosedure_permintaan_wifi` ke `customer_services`.
- [ ] Mapping tagihan/biaya lama dari `biaya_tagihan`, `penagihan`, dan bukti transaksi tagihan ke `invoices`.
- [ ] Mapping pembayaran lama dari tabel `apikeuangan_*` ke `payments`.
- [ ] Simpan data teknis lama sebagai informasi detail pelanggan, bukan workflow teknisi baru.
- [ ] Longgarkan validasi import agar pelanggan lama yang belum lengkap tetap bisa masuk sebagai `perlu_dilengkapi`.
- [ ] Mapping status legacy seperti `ACTIVE`, `PUTUS`, `GAGAL`, `DISURVEI`, dan `PENGAJUAN` ke status sistem baru.
- [ ] Cegah duplikasi import ulang berdasarkan key legacy seperti `old_customer_id`, `old_package_id`, `old_request_id`, `old_invoice_id`/`old_cost_id`, `old_payment_id`, dan `old_report_id`.
- [ ] Data yang tidak bisa dicocokkan tidak boleh hilang; simpan ke import error/review.

Tidak Masuk Scope:
- [ ] Jangan membuat integrasi MikroTik.
- [ ] Jangan membuat payment gateway.
- [ ] Jangan membuat WhatsApp notification.
- [ ] Jangan membuat auto suspend pelanggan.
- [ ] Jangan membuat auto billing bulanan kompleks.
- [ ] Jangan mengembangkan workflow teknisi lapangan lengkap.
- [ ] Jangan membuat inventory perangkat kompleks.
- [ ] Jangan membuat monitoring OLT/SNMP/router.
- [ ] Jangan membuat ticketing gangguan kompleks.
- [ ] Jangan membuat modul keuangan/jurnal kompleks.

Acceptance Criteria:
- [ ] Template/import Excel multi-sheet sesuai kebutuhan migrasi `sand_db_sandya.sql`.
- [ ] Data pelanggan lama dapat masuk walaupun belum lengkap dan diberi status `perlu_dilengkapi`.
- [ ] Paket lama tersimpan sebagai master paket dengan ID legacy.
- [ ] Layanan lama terhubung ke pelanggan dan paket jika relasinya ditemukan.
- [ ] Tagihan/biaya lama tampil sebagai invoice historis jika bisa dicocokkan.
- [ ] Pembayaran lama terhubung ke invoice jika relasinya ditemukan.
- [ ] Data teknis lama tampil sebagai informasi pelanggan, bukan modul operasional teknisi baru.
- [ ] Data invalid atau belum bisa dicocokkan masuk ke import error/review.
- [ ] Import ulang tidak membuat data dobel berdasarkan key legacy.
- [ ] Billing manual existing tetap berjalan setelah data migrasi masuk.
- [ ] Tidak ada fitur post-MVP yang dibuat.

Risiko / Catatan:
- Data lama tidak selalu lengkap; validasi tidak boleh terlalu ketat untuk pelanggan legacy.
- Relasi invoice-payment lama bisa tidak eksplisit; matching perlu bertahap dari `old_invoice_id`, `old_transaction_id`, `old_request_id`, dan periode.
- Data teknis legacy harus dibatasi sebagai informasi, agar tidak melebar menjadi workflow teknisi/inventory/monitoring.
- Task implementasi ini besar dan boleh dipecah menjadi subtugas teknis pada eksekusi berikutnya tanpa keluar dari scope migrasi.

Cara Test Saat Implementasi:
- Import pelanggan dengan wilayah kosong tetap masuk sebagai `perlu_dilengkapi`.
- Import pelanggan dengan `HP = null` atau kosong tidak gagal total jika masih punya identitas legacy.
- Import status legacy berhasil dimapping ke status baru.
- Import paket lama menyimpan `old_package_id`.
- Import layanan lama terhubung ke customer dan paket.
- Import invoice dari `old_cost_id` atau `old_invoice_id` berhasil.
- Import payment dengan `old_transaction_id` dapat cocok ke invoice jika relasinya tersedia.
- Data yang tidak bisa dicocokkan tercatat di import error/review.
- Import ulang tidak membuat duplikasi.
- Jalankan test import, invoice, payment, laporan import, dan build frontend jika ada perubahan kode pada task implementasi berikutnya.

---
