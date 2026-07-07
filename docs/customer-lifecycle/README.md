# Modul Customer Verifikasi & Onboarding Lifecycle

Siklus hidup pelanggan dari registrasi sampai aktif/berhenti: **Registrasi → Survey → Verifikasi Survey (proses ke Tim) → Pemasangan → Verifikasi Admin (Aktivasi) → Aktif → (Suspend/Terminate)**. State machine dikawal `CustomerWorkflowService`, tiap transisi tercatat immutable di `customer_status_logs`.

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | State machine, aturan transisi, guard tiap tahap, integrasi dengan Task/FopTask/Invoice |
| [flowchart.md](flowchart.md) | Alur registrasi→survey→verifikasi→pemasangan→aktivasi, alur reject/revisi/terminasi |
| [user-flow.md](user-flow.md) | Langkah Sales/FOP/Teknisi/Admin di tiap tahap |
| [database-schema.md](database-schema.md) | Tabel `customers`, `customer_surveys`, `customer_installations`, `customer_services`, dll |
| [archive/](archive/) | Dokumen alur/rencana historis (sebagian sudah diimplementasi, sebagian berbeda dari kode aktual) |

## Konsep Inti

Data pelanggan **tersebar di beberapa tabel per-fase** (bukan 1 tabel besar) — tiap fase onboarding nulis ke tabel transaksinya sendiri, `customers.status` cuma nyimpen state mesin status saat ini:

| Tabel | Fase | Diisi Saat |
|-------|------|-----------|
| `customers` | Identitas + status master | Registrasi (Sales/Admin) |
| `customer_addresses` | Alamat detail + foto rumah/KTP/kontrak | Registrasi |
| `customer_surveys` | Hasil survey lapangan | Teknisi submit laporan survey |
| `customer_installations` | Hasil pemasangan | Teknisi submit laporan pemasangan |
| `customer_technical_details` | Data teknis (ODP/OLT/VLAN/speedtest) | Teknisi submit laporan pemasangan |
| `customer_devices` | Device pelanggan (modem/ONT, PPPoE, WiFi) | Teknisi submit laporan pemasangan (duplikat sebagian dari technical_details, legacy) |
| `customer_documents` | Dokumen upload lain (KTP, dsb.) | Kapan saja lewat form dokumen |
| `customer_services` | Paket & billing aktif pelanggan | Registrasi (snapshot awal), diaktifkan saat Verifikasi Admin |
| `customer_status_logs` | Riwayat transisi status (immutable) | Tiap kali `CustomerWorkflowService::transition()` jalan |

## Aktor per Tahap

| Tahap | Aktor | Permission |
|-------|-------|-----------|
| Registrasi | Sales/Admin | `customers.create` |
| Survey (mulai & lapor) | Teknisi | `customers.detail.survey.update` |
| Proses survey ke Tim Pemasangan | FOP | `customers.detail.installation.validate` |
| Pemasangan (mulai & lapor) | Teknisi | `customers.detail.installation.update` |
| Verifikasi Admin (aktivasi/reject/revisi) | FOP/Admin | `customers.detail.installation.validate` |
| Terminasi layanan | Admin | (guarded di route, lihat [business-logic.md](business-logic.md)) |

## File Kode Terkait

| Area | File |
|------|------|
| State machine | `app/Enums/WorkflowTransition.php`, `app/Services/CustomerWorkflowService.php` |
| Model utama | `app/Models/Customer.php`, `CustomerService.php`, `CustomerSurvey.php`, `CustomerInstallation.php`, `CustomerTechnicalDetail.php`, `CustomerDevice.php`, `CustomerDocument.php`, `CustomerAddress.php`, `CustomerStatusLog.php` |
| Controller Survey | `app/Http/Controllers/CustomerSurveyController.php` |
| Controller Verifikasi & Aktivasi | `app/Http/Controllers/CustomerVerificationController.php` |
| Controller Pemasangan | `app/Http/Controllers/CustomerInstallationController.php` |
| Controller Terminasi | `app/Http/Controllers/CustomerTerminationController.php` |
| Controller Device/Dokumen | `app/Http/Controllers/CustomerDeviceController.php`, `CustomerDocumentController.php` |
| Registrasi | `app/Http/Controllers/CustomerController.php@store` |
| Validasi input | `app/Http/Requests/CustomerRegistrationRequest.php`, `app/Services/CustomerValidationService.php` |
| Event | `app/Events/SurveyStarted.php`, `SurveyCompleted.php`, `InstallationStarted.php`, `InstallationCompleted.php` |
| CID generator | `app/Models/Pop.php@generateComplexCid()` |

## Terhubung dengan Modul Lain

- [docs/fop-task](../fop-task/README.md) — tiap transisi ke `waiting_survey`/`waiting_installation` auto-bikin `Task` (via `CustomerWorkflowService`), yang lalu muncul di antrean FOP.
- [docs/billing-pembayaran](../billing-pembayaran/README.md) — Verifikasi Admin (`finalVerify`) generate `Invoice` tipe `awal` sekaligus mengaktifkan `CustomerService`.
- [docs/rbac](../rbac/README.md) — semua guard tahap di atas pakai permission granular `customers.detail.*`.

---

**Last updated:** 2026-07-07
