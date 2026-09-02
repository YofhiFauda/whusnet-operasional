# Modul Customer Verifikasi & Onboarding Lifecycle

Siklus hidup pelanggan dari registrasi sampai aktif/berhenti: **Registrasi → Survey → Verifikasi Survey (proses ke Tim) → Pemasangan → Verifikasi Admin (Aktivasi) → Aktif → (Suspend/Terminate)**. State machine dikawal `CustomerWorkflowService`, tiap transisi tercatat immutable di `customer_status_logs`.

**Skip Survey** (2026-08-21, permission `customers.registration.skip_survey`, default Sales): jalur pintas **Registrasi → Verifikasi Survey** langsung — Sales input data survey (ODP, koordinat, foto) di form registrasi, tahap Survey teknisi dilewati sepenuhnya. Lihat [business-logic.md §3.1](business-logic.md).

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | State machine, aturan transisi, guard tiap tahap, integrasi dengan Task/FopTask/Invoice, §8 teknisi fieldwork page (2026-07-28) |
| [flowchart.md](flowchart.md) | Alur registrasi→survey→verifikasi→pemasangan→aktivasi, alur reject/revisi/terminasi |
| [user-flow.md](user-flow.md) | Langkah Sales/FOP/Teknisi/Admin di tiap tahap |
| [database-schema.md](database-schema.md) | Tabel `customers`, `customer_surveys`, `customer_installations`, `customer_services`, dll |
| [bug.md](bug.md) | Gap survey "tidak layak pasang" yang gak pernah ditangani — fixed 2026-07-08 |
| [archive/](archive/) | Dokumen alur/rencana historis (sebagian sudah diimplementasi, sebagian berbeda dari kode aktual) |

## Konsep Inti

Data pelanggan **tersebar di beberapa tabel per-fase** (bukan 1 tabel besar) — tiap fase onboarding nulis ke tabel transaksinya sendiri, `customers.status` cuma nyimpen state mesin status saat ini:

| Tabel | Fase | Diisi Saat |
|-------|------|-----------|
| `customers` | Identitas + status master | Registrasi (Sales/Admin) |
| `customer_addresses` | Alamat detail + foto rumah/KTP/kontrak | Registrasi |
| `customer_surveys` | Hasil survey lapangan | Teknisi submit laporan survey — **atau Sales saat registrasi** kalau Skip Survey dipakai |
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
| Skip Survey saat Registrasi | Sales (default) | `customers.registration.skip_survey` |
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
| **Controller Fieldwork (NEW 2026-07-28)** | **`app/Http/Controllers/CustomerFieldworkController.php`** — teknisi view/edit perangkat & pemasangan via `/customers/{id}/perangkat-pemasangan` (blok akses Detail Pelanggan umum) |
| Registrasi | `app/Http/Controllers/CustomerController.php@store` |
| Validasi input | `app/Http/Requests/CustomerRegistrationRequest.php`, `app/Services/CustomerValidationService.php` |
| Event | `app/Events/SurveyStarted.php`, `SurveyCompleted.php`, `InstallationStarted.php`, `InstallationCompleted.php` |
| CID generator | `app/Models/Pop.php@generateComplexCid()` |
| Redirect `return_to` (NEW 2026-08-06) | `app/Support/SafeUrl.php@resolveReturnTo()` — dipakai form Laporan Survey/Pemasangan biar "Kembali" & redirect sukses ikut halaman asal, bukan hardcoded |

## Terhubung dengan Modul Lain

- [docs/fop-task](../fop-task/README.md) — tiap transisi ke `waiting_survey`/`waiting_installation` auto-bikin `Task` (via `CustomerWorkflowService`), yang lalu muncul di antrean FOP.
- [docs/billing-pembayaran](../billing-pembayaran/README.md) — Verifikasi Admin (`finalVerify`) generate `Invoice` tipe `awal` sekaligus mengaktifkan `CustomerService`.
- [docs/rbac](../rbac/README.md) — semua guard tahap di atas pakai permission granular `customers.detail.*`. Lihat juga [docs/rbac/customer-permission-hierarchy.md](../rbac/customer-permission-hierarchy.md) untuk detail segregasi 4 permission independen (List/Putus/Gagal/Detail) + fieldwork page (2026-07-28).

---

**Last updated:** 2026-08-06 — fix redirect Laporan Survey/Pemasangan (`return_to`) biar "Kembali" & redirect sukses ikut halaman asal, bukan hardcoded ke Antrean Survey/Verifikasi Queue

<details><summary>Riwayat update sebelumnya</summary>

**2026-07-28** — added fieldwork page for technician device/installation data (separate from Detail Pelanggan per RBAC segregation)

</details>
