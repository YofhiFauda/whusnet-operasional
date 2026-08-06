# BELUM DI IMPLEMENTASIKAN

# Analisa Kebutuhan In-App Notification & Push Notification
**Sistem Billing & Operasional ISP (Whusnet Operasional)**

---

## 1. Pendahuluan & Konsep Pemisahan Notification Channel

Sistem operasional ISP melibatkan banyak peran (*roles*) dengan kebutuhan mobilitas yang berbeda-beda—mulai dari staff admin di kantor hingga teknisi di lapangan. Oleh karena itu, notifikasi dibagi menjadi 2 kanal utama:

| Kanal Notifikasi | Karakteristik Utama | Target Pengguna Utama | Kasus Penggunaan (Use Case) |
| :--- | :--- | :--- | :--- |
| **In-App Notification** *(Lonceng Web App, Dropdown Alert, Real-time Toast & Sound via WebSockets/Reverb)* | • Aktif saat pengguna sedang **buka/login di dashboard web**.<br>• Memiliki riwayat (history/unread badge).<br>• Terhubung langsung dengan `action_url` (drawer/halaman detail). | Admin Pusat, Admin POP, Finance, NOC, CS/Helpdesk, FOP Dispatcher. | Perubahan status data, review dokumen, approval transaksi, alert batasan validasi data. |
| **Push Notification** *(Web Push API / PWA, Mobile Push FCM, & Telegram Bot Alert)* | • Muncul di **HP/Desktop pengguna bahkan saat browser/aplikasi ditutup**.<br>• Beresiko *spamming* jika tidak difilter, khusus pesan high-priority & butuh respons cepat. | Teknisi Lapangan (FOP), Supervisor/Atasan, NOC On-Call, Collector Lapangan. | Penugasan task baru di lapangan, perubahan jadwal mendadak, SLA Breach warning, tiket gangguan darurat. |

---

## 2. Analisa Kebutuhan Berdasarkan Modul Operasional

### A. Modul Customer Onboarding & Lifecycle (Registrasi → Survey → Pemasangan → Aktivasi)

1. **Pendaftaran Pelanggan Baru**
   - **In-App (Admin POP & FOP):** Pemberitahuan ada pendaftaran pelanggan baru yang masuk dan perlu diperiksa/ditugaskan.
   - **In-App (Sales):** Pemberitahuan bahwa pelanggan yang didaftarkan berhasil diverifikasi atau ditolak.

2. **Survey Lapangan**
   - **In-App + Push (Teknisi):** Push notification ke HP Teknisi saat FOP menugaskan tim untuk survey lokasi.
   - **In-App (FOP):** Alert di lonceng FOP ketika Teknisi selesai mengunggah Laporan Hasil Survey di lapangan (butuh review/approval FOP).

3. **Pemasangan Layanan Baru (PSB)**
   - **In-App + Push (Teknisi):** Push notification penugasan jadwal Pemasangan (PSB) lengkap dengan lokasi koordinat & perangkat yang dialokasikan.
   - **In-App (FOP & NOC):** Alert ketika Pemasangan selesai (Redup kabel OK, Perangkat terpasang) dan pelanggan siap diverifikasi admin/NOC.

4. **Aktivasi & Kesiapan Billing**
   - **In-App (Sales & Admin POP):** Alert saat status pelanggan resmi berubah dari *Verification* menjadi **Active / Ready for Billing**, dan tagihan prorata terbit.

---

### B. Modul Operasional Lapangan & Task Management (FOP & Teknisi)

1. **Pembatalan / Cancel Task Mendadak**
   - **In-App + Push (Teknisi):** Jika task yang sedang dikerjakan (*in_progress*) dibatalkan oleh FOP/Admin, sistem wajib mengirimkan Push Notification & In-App alert berjenis `error/warning` ke HP teknisi agar mereka menghentikan pengerjaan di lokasi.

2. **Rebuild / Swapping Tim Teknisi**
   - **In-App + Push (Teknisi):** Notifikasi ke Teknisi A & Teknisi B saat FOP melakukan penggantian/penukaran anggota tim di task tertentu.

3. **Eskalasi & Alert SLA (Master Timeline)**
   - **In-App + Push (FOP & Supervisor):** Notifikasi peringatan (misal H-2 jam sebelum SLA Survey/Pemasangan habis) dan **Alert Overdue** jika pekerjaan melewati batas SLA.

---

### C. Modul Ticketing & Gangguan (Helpdesk, NOC, & Teknisi)

1. **Pengaduan Tiket Baru**
   - **In-App (NOC & Helpdesk):** Alert real-time di lonceng dashboard saat tiket gangguan baru di-submit oleh CS/Helpdesk.

2. **Eskalasi Tiket (Helpdesk → NOC / Field)**
   - **In-App + Push (NOC / Teknisi Field):** Notifikasi eskalasi tiket dengan tingkat prioritas *High/Critical* (misal: Kabel FO Putus / Mass Outage POP) ke grup/HP teknisi on-call.

3. **Resolusi & Close Ticket**
   - **In-App (Helpdesk / CS):** Alert bahwa penanganan tiket telah diselesaikan oleh NOC/Teknisi, memicu CS untuk melakukan konfirmasi akhir ke pelanggan.

---

### D. Modul Billing, Pembayaran, & Collector (Finance, Admin POP, & Collector)

1. **Tagihan & Batch Invoice**
   - **In-App (Finance & Admin POP):** Notifikasi ringkasan setelah sistem selesai melakukan *generate* tagihan bulanan massal (jumlah invoice berhasil & gagal).

2. **Setoran / Pembayaran Lapangan (Collector)**
   - **In-App (Finance Pusat):** Notification saat Collector / Admin POP mengajukan setoran pembayaran tunai dari lapangan yang memerlukan konfirmasi/rekonsiliasi Finance Pusat.
   - **In-App + Push (Collector):** Notification ke Collector saat setoran mereka telah disetujui (Approved) atau ditolak (Rejected) oleh Finance.

3. **Pengajuan Penyesuaian / Diskon Tagihan**
   - **In-App (Owner / Lead Finance):** Notification pengajuan koreksi tagihan / diskon khusus yang membutuhkan hak akses verifikasi tingkat atas.

---

### E. Modul System, Data Import, & Audit Security

1. **Import Pelanggan Massal (Excel/CSV)**
   - **In-App (Admin Data):** Alert progres & hasil impor asynchronous (misal: "Import 500 pelanggan selesai: 485 sukses, 15 gagal").

2. **Keamanan & Anomali Access**
   - **In-App + Push (Owner / Lead Admin):** Notification saat terjadi kegagalan login berulang (*brute force attempt*), atau perubahan role & permission sensitif.

---

## 3. Matriks Pemetaan Event & Jenis Notifikasi

| Event / Kejadian Skenario | Trigger Source | Penerima (Roles) | In-App | Push Notif | Tipe/Severity | Action URL |
| :--- | :--- | :--- | :---: | :---: | :--- | :--- |
| **New Task Assignment (Survey/PSB/Ticket)** | FOP / System | Teknisi Lapangan | `YES` | `YES` *(Telegram/Push)* | `INFO` | `/tasks/{id}` |
| **Task Cancelled saat In-Progress** | FOP / Admin | Teknisi Lapangan | `YES` | `YES` *(Push)* | `ERROR` / `WARNING` | `/tasks/{id}` |
| **Laporan Survey/Pemasangan Selesai** | Teknisi | FOP / Admin POP | `YES` | `NO` | `SUCCESS` | `/tasks/{id}/review` |
| **SLA Warning / SLA Overdue** | System (Cron Job) | FOP & Supervisor | `YES` | `YES` *(Push)* | `WARNING` | `/fop-tasks?status=overdue` |
| **Tiket Gangguan Prioritas High/Critical** | CS / Helpdesk | NOC & Teknisi | `YES` | `YES` *(Push)* | `ERROR` | `/tickets/{id}` |
| **Setoran Collector Di-Submit** | Collector | Finance Pusat | `YES` | `NO` | `INFO` | `/payments/collector-deposits` |
| **Setoran Collector Approved/Rejected** | Finance | Collector | `YES` | `YES` *(Push)* | `SUCCESS`/`ERROR` | `/payments/my-deposits` |
| **Import Data Pelanggan Selesai** | Queue Job | Admin Import | `YES` | `NO` | `INFO` | `/customers/import-batches/{id}` |
| **Pengajuan Diskon/Adjustment Invoice** | Admin POP | Owner / Finance Lead | `YES` | `NO` | `WARNING` | `/invoices/{id}/adjustments` |

---

## 4. Arsitektur Teknis & Rekomendasi Fitur

1. **In-App Notification Engine (Laravel Default + Broadcast)**:
   - Memanfaatkan `App\Notifications\AppNotification` yang sudah tersedia.
   - `via()` mengembalikan `['database', 'broadcast']`.
   - **Real-time updates:** Terintegrasi dengan **Laravel Reverb** untuk membroadcast event lonceng (unread count & toast notification) secara langsung tanpa perlu refresh halaman.

2. **Push Notification Engine (Internal Operational Staff)**:
   - **Telegram Bot Integration:** Direkomendasikan untuk Teknisi Lapangan, FOP, dan NOC. Telegram sangat stabil digunakan di area dengan sinyal kurang baik dan hemat baterai HP.
   - **Web Push API (VAPID / Service Worker):** Untuk Staff Web Dashboard yang membutuhkan notifikasi latar belakang saat tab browser sedang tidak aktif.

---

## 5. Catatan Alokasi Scope (MVP vs Post-MVP)

Berdasarkan aturan MVP di `AGENTS.md`:

- **In-Scope MVP:**
  - In-App Notification (Database storage + Real-time WebSockets via Laravel Reverb).
  - Internal Operational Push Alert (Telegram Bot ke Teknisi/FOP untuk Penugasan Task & Warning SLA).
- **Post-MVP (Di luar scope MVP):**
  - Notification massal via WhatsApp Gateway ke Pelanggan (Tagihan H-3, Peringatan Isolir).
  - Mobile App Native Push Notification (FCM/APNS) untuk Pelanggan / App Teknisi dedicated.
