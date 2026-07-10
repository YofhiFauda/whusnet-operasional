# Master Status Pelanggan

Master Status Pelanggan (atau Status Langganan) adalah modul referensi yang menyimpan daftar urutan state / status dalam workflow onboarding pelanggan. Data ini esensial untuk menggerakkan State Machine `CustomerWorkflowService`.

## Fungsi Utama
1. Melihat daftar status secara berurutan sesuai alur bisnis.
2. Memonitor jumlah pelanggan yang berada di masing-masing status secara real-time.
3. Menandai mana status yang bersifat *terminal* (akhir alur yang tidak bisa berlanjut lagi seperti `active`, `rejected`, `terminated`).

## File Terkait
- **Controller**: `app/Http/Controllers/Master/SubscriptionStatusController.php`
- **Model**: `app/Models/SubscriptionStatus.php`
- **View**: `resources/views/master/status-langganan/index.blade.php` (Catatan: View-nya ada di `/master/status-langganan`)

## Status Default (Urutan Workflow Onboarding)

| Order | Code | Nama | Badge | Terminal | Keterangan |
| --- | --- | --- | --- | --- | --- |
| 1 | `waiting_survey` | Waiting Survey | sky | Tidak | Menunggu tim survey |
| 2 | `survey_in_progress` | Survey In Progress | sky | Tidak | Sedang disurvey |
| 3 | `surveyed` | Surveyed | blue | Tidak | Selesai disurvey, menunggu verifikasi lapangan (ACC) |
| 4 | `waiting_installation` | Waiting Installation | amber | Tidak | Menunggu jadwal pasang |
| 5 | `installation_in_progress` | Installation In Progress | amber | Tidak | Sedang dipasang |
| 6 | `verification_admin` | Verification Admin | purple | Tidak | Admin mengecek hasil pasang (FOP) |
| 7 | `installed` | Installed | blue | Tidak | Pemasangan beres, menunggu aktivasi sistem |
| 8 | `active` | Active | green | Ya | Pelanggan aktif dan siap billing bulanan |
| 9 | `suspended` | Suspended | amber | Tidak | Isolir sementara |
| 10 | `terminated` | Terminated | red | Ya | Putus langganan |
| 11 | `rejected` | Rejected | red | Ya | Ditolak sistem/admin |
