# Fitur Data Pelanggan

Fitur Data Pelanggan adalah modul utama untuk mengelola calon pelanggan dan pelanggan ISP. Modul ini mencakup pendaftaran pelanggan, antrean survey, verifikasi lapangan, pemasangan, hingga aktivasi dan import batch.

## File Terkait

| Bagian | File |
| --- | --- |
| Controller | `app/Http/Controllers/CustomerController.php`<br>`app/Http/Controllers/CustomerRegistrationController.php`<br>`app/Http/Controllers/CustomerSurveyController.php`<br>`app/Http/Controllers/CustomerVerificationController.php`<br>`app/Http/Controllers/CustomerInstallationController.php` |
| Service | `app/Services/CustomerWorkflowService.php` |
| Model | `app/Models/Customer.php`<br>`app/Models/CustomerSurvey.php`<br>`app/Models/CustomerInstallation.php`<br>`app/Models/CustomerTechnicalDetail.php` |
| Daftar | `resources/views/customers/index.blade.php` |
| Antrean | `resources/views/surveys/queue.blade.php`<br>`resources/views/verifications/queue.blade.php` |
| Route | `routes/web.php` |

## Fungsi Utama

1. Menampilkan daftar pelanggan dengan pencarian dan filter.
2. Menambahkan pelanggan baru melalui multi-step form registrasi.
3. Mengelola antrean survey dan verifikasi secara real-time.
4. Memfasilitasi workflow status (survey, acc, pemasangan, verifikasi).
5. Menyimpan data teknis (perangkat, OLT, VLAN, speedtest) secara terstruktur.
6. Mengimport banyak pelanggan sekaligus.

## Relasi Model

| Relasi | Keterangan |
| --- | --- |
| `Customer belongsTo City/District/Village` | Wilayah lokasi pelanggan. |
| `Customer belongsTo InternetPackage` | Paket layanan yang dipilih. |
| `Customer belongsTo SubscriptionStatus` | Status workflow. |
| `Customer hasMany CustomerSurvey` | Histori / data survey pelanggan. |
| `Customer hasMany CustomerInstallation` | Histori / data pemasangan pelanggan. |
| `Customer hasOne CustomerTechnicalDetail` | Data perangkat (ONT, Router), FOP, OLT, VLAN. |

## Status Workflow Pelanggan

Urutan status pelanggan dalam alur Onboarding:

| Urutan | Code | Nama | Terminal | Keterangan |
| --- | --- | --- | --- | --- |
| 1 | `waiting_survey` | Waiting Survey | Tidak | Menunggu tim survey |
| 2 | `survey_in_progress` | Survey In Progress | Tidak | Sedang disurvey (Live Countdown) |
| 3 | `surveyed` | Surveyed | Tidak | Selesai survey, menunggu ACC |
| 4 | `waiting_installation`| Waiting Installation | Tidak | Menunggu jadwal pasang |
| 5 | `installation_in_progress` | Installation In Progress | Tidak | Sedang dipasang (Live Countdown) |
| 6 | `verification_admin` | Verification Admin | Tidak | Review perangkat & speedtest |
| 7 | `installed` | Installed | Tidak | Pemasangan selesai & valid |
| 8 | `active` | Active | Tidak | Siap ditagih (Layanan aktif) |

## Tab Detail Pelanggan

Halaman detail menyusun informasi pelanggan dalam beberapa area operasional:
1. Ringkasan dan identitas.
2. Timeline workflow terperinci.
3. Survey (termasuk foto rumah, petugas).
4. FOP & Perangkat (ONT, OLT, Speedtest).
5. Pemasangan (teknisi).
6. Aktivasi & Billing.

