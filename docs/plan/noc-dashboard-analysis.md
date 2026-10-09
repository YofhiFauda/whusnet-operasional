# Analisa Penyajian Data Dashboard NOC — Gap Analysis & Rekomendasi Pengembangan (Revisi 3)

Dokumen ini mencatat evaluasi mendalam terhadap penyajian data pada **Dashboard NOC** (`/noc/dashboard`), status implementasi fitur saat ini, gap/kekurangan operasional ISP, serta rekomendasi pengembangan bertahap.

---

## 1. Status Implementasi Saat Ini (Revisi 2 — SUDAH TERPASANG)

Berdasarkan kode aktif di `NocDashboardController.php` dan `resources/views/noc/dashboard.blade.php`:

1. **Toolbar & Filter**:
   - Filter preset waktu (*1 Bulan Berjalan, 7 Hari, 30 Hari, Bulan Ini, Bulan Lalu, Semua Waktu, Custom*).
   - Filter cakupan POP/Cabang (dengan `applyUserScope()`).
   - Tombol manual refresh & listener realtime via Laravel Echo (`tickets.{pop_id}`).
2. **Stat Cards Eksekutif**:
   - Total Tiket, Tiket Selesai, Tiket Assign FOP, Tiket Dibatalkan (lengkap dengan delta % MoM/PoP).
   - Rata-rata durasi di meja NOC (*Average Handling Duration*).
3. **Visualisasi Chart.js**:
   - Tren Harian Volume Tiket (Line chart: Masuk vs Selesai vs Dibatalkan, maks. 60 hari).
   - Hotspot Tren Matriks Daerah vs Kategori Issue (Stacked bar chart).
   - SLA Compliance (Donut chart: On-time vs Breach pada tiket resolved ber-SLA).
   - Distribusi Aging Tiket Aktif (Horizontal bar: 0-8 jam, 8-24 jam, >24 jam).
   - Tren Bulanan Komplain Pelanggan 12 Bulan (Grouped bar: breakdown per daerah & breakdown per issue).
   - Performa & Analisa per POP (Dual-axis chart: Bar Komplain vs Line Total Pelanggan + narasi insight rule-based).
4. **Operasional & Antrean**:
   - Leaderboard performa individu (Helpdesk vs NOC: Jumlah Selesai, Eskalasi, Avg Durasi, SLA Breach).
   - Antrean Tiket Aktif NOC (Urut aging paling lama di atas + drawer detail interaktif).
   - Live Activity Feed (20 log riwayat aksi tiket terakhir).

---

## 2. Analisa Celah & Kekurangan Penyajian Data (Gap Analysis)

Meskipun metrik dasar dan analitik bulanan sudah lengkap, terdapat beberapa celah utama jika ditinjau dari kebutuhan operasional riil ISP NOC (*Network Operations Center*):

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    GAP UTAMA DASHBOARD NOC SAAT INI                    │
├───────────────────────┬────────────────────────┬────────────────────────┤
│ 1. INCIDENT & OUTAGE  │ 2. SLA HEALTH PROAKTIF │ 3. NETWORK DIAGNOSTICS │
│ • No Outage Detection │ • Donut SLA Reaktif    │ • No Chronic Customer  │
│ • No Batch Incident   │ • No Breach Countdown  │ • No FCR vs Escalation │
│ • Flat Severity/P1-P4 │ • No First-Response-T  │ • No Segmen ODP/OLT    │
└───────────────────────┴────────────────────────┴────────────────────────┘
```

### A. Deteksi Insiden & Outage Management (*Kritikal Operasional*)
1. **Tidak Ada Deteksi Anomali / Lonjakan Gangguan Massal (*Mass Outage Detection*)**:
   - Belum ada deteksi otomatis jika terjadi lonjakan tiket (spike) pada area/ODP yang sama dalam rentang waktu singkat (misal: >5 tiket dalam 30 menit).
   - Akibatnya, NOC terlambat menyadari terjadinya insiden kabel optik putus (*cut fiber*) atau perangkat distribusi/OLT down.
2. **Ketiadaan Visibilitas Tiket Batch / Parent Incident (`TicketBatchMember`)**:
   - Sistem sudah memiliki relasi `batchMembers` untuk insiden massal, namun dashboard belum menampilkan ringkasan insiden massal yang sedang aktif beserta jumlah total pelanggan terdampak.
3. **Matriks Prioritas / Severity Level Rata (*Flat Weight*)**:
   - Stat card menyamakan 1 tiket P1 (Dedicated/Backbone Down) dengan 1 tiket P4 (Ubah Password Wifi). Belum ada widget breakdown tiket aktif berdasarkan tingkat kegawatan (P1 Critical, P2 High, P3 Medium, P4 Low).

---

### B. Pemantauan SLA Proaktif (Bukan Sekadar Historis)
1. **SLA Compliance Bersifat Reaktif (Hanya Mengukur Tiket Selesai)**:
   - Donut SLA saat ini hanya menghitung tiket yang sudah ditutup (`resolved_at`).
   - **Kekurangan**: Tidak ada panel **"Tiket Kritis / At-Risk SLA"** untuk tiket yang *masih aktif berjalan* (misal: tiket aktif dengan sisa waktu SLA < 1 jam). NOC membutuhkan peringatan dini *sebelum* tiket melanggar SLA.
2. **Ketiadaan Pengukuran First Response Time (FRT / MTTA)**:
   - Durasi saat ini hanya mengukur waktu dari pembuatan sampai tiket lepas/selesai. Belum ada metrik waktu respons pertama (*Mean Time to Acknowledge*).

---

### C. Diagnostik Teknis Jaringan & Kualitas Layanan
1. **Tidak Ada Analisa Pelanggan Berulang (*Chronic / Repeat Trouble Tickets*)**:
   - Belum ada identifikasi pelanggan atau ODP yang komplain berulang kali dalam 7/14/30 hari. Ini adalah indikator utama masalah teknis kronis (misal redaman jelek / kabel dropcore aus).
2. **Ketiadaan Rasio First Contact Resolution (FCR) vs Field Escalation**:
   - Belum ada visualisasi perbandingan tiket yang berhasil diselesaikan secara *remote* oleh NOC (FCR) vs tiket yang harus dieskalasi ke teknisi lapangan (FOP). Rasio ini merupakan indikator efisiensi utama tim NOC.
3. **Ketiadaan Pemetaan ke Segmen Distribusi / ODP**:
   - Belum ada visualisasi pemetaan masalah berdasarkan segmen infrastruktur (apakah isu terkonsentrasi di Core/OLT, Distribusi/ODP, atau Dropcore/CPE).

---

### D. Interaktivitas & Tata Letak UI/UX
1. **Hierarki Halaman Terlalu Panjang (*Dashboard Fatigue*)**:
   - Dashboard saat ini memiliki 13 kontainer (stat cards, region, issue, trend matrix, tren harian, SLA compliance, aging buckets, tren komplain per-daerah, tren komplain per-issue, analisa per-POP, leaderboard, antrean tiket aktif, activity feed) dalam 1 halaman panjang. Antrean **Tiket Aktif** dan **Live Activity Feed** berada di bagian paling bawah. Padahal untuk operasional harian monitor NOC, antrean kerja aktif adalah hal pertama yang harus dipantau.
2. **Grafik Bersifat Statis (*No Drill-Down*)**:
   - Mengeklik bar daerah atau kategori issue tidak memfilter daftar tiket aktif secara dinamis.
3. **Filter Terbatas**:
   - Toolbar hanya memiliki filter POP dan tanggal; belum ada filter instan untuk Prioritas (P1/P2/P3), Status Tiket, atau Kategori Issue.
4. **Ketiadaan Alert Suara / Visual Flash untuk Tiket P1**:
   - Saat tiket P1 baru masuk via WebSocket, update terjadi secara pasif tanpa penanda visual kontras / bunyi alarm.
5. **Ketiadaan Rekapitulasi Handover Shift**:
   - Belum ada ringkasan cetak / ekspor untuk laporan pergantian shift tim NOC.

---

## 3. Matriks Rekomendasi Peningkatan

| Komponen | Status Saat Ini | Usulan Solusi Peningkatan | Dampak Operasional |
| :--- | :--- | :--- | :--- |
| **SLA At-Risk Alert** | Donut tiket resolved | Tambahkan badge/box counter **"SLA < 1 Jam (At-Risk)"** & **"Lewat SLA (Aktif)"** pada baris paling atas | Proaktif mencegah breach SLA |
| **Deteksi Outage** | Tidak ada | Banner deteksi otomatis jika ada > N tiket pada ODP/wilayah yang sama dalam 1 jam | Deteksi cepat kabel putus / OLT mati |
| **Pemisahan Mode Tampilan** | 1 halaman panjang 11 container | Dua tab: **"Live Command Center"** (antrean, alarm, tren harian) vs **"Analitik & Laporan"** (tren 12 bulan, analisa POP, leaderboard) | Monitor NOC lebih fokus dan ringkas |
| **Chronic / Repeat Tickets** | Tidak ada | Widget **Top 5 Pelanggan Komplain Berulang (30 Hari Terakhir)** | Identifikasi titik redaman/dropcore bermasalah |
| **Severity Breakdown** | Total flat | Counter badge prioritas: Critical (P1), High (P2), Medium (P3), Low (P4) | Triage cepat tiket jalur vital/korporat |
| **Interaktivitas Grafik** | Display only | Klik pada bar grafik langsung memfilter list antrean tiket aktif | Mempercepat investigasi isu per area |

---

## 4. Rencana Tahapan Eksekusi (Roadmap)

### Fase 1: Quick Wins (Tanpa Perubahan Skema Database)
1. **SLA At-Risk Counter**: Hitung tiket aktif yang memiliki `sla_deadline_at` mendekati batas waktu (< 1 jam dan overdue).
2. **Severity Priority Counter**: Tambahkan badge P1/P2/P3/P4 pada ringkasan tiket aktif.
3. **Chronic Repeat Customer Query**: Identifikasi pelanggan yang memiliki > 1 tiket dalam 30 hari terakhir.
4. **Reorganisasi Tata Letak**: Pindahkan daftar Tiket Aktif & SLA Alert ke bagian atas (posisi strategis) atau pisahkan tab *Command Center* vs *Analitik*.

### Fase 2: Peningkatan Interaktivitas & Alerting
1. **Interactive Drill-down**: Implementasi filter dinamis pada antrean tiket saat chart/bar diklik.
2. **Visual Flash / Notification**: Efek visual pada dashboard saat ada tiket P1/Critical baru via Echo.
3. **Outage Detection Logic**: Service ringan pendeteksi konsentrasi komplain pada ODP/Desa dalam 1 jam terakhir.

---

*Dokumen ini diperbarui pada 2026-09-10 sebagai blueprint penyempurnaan fitur Dashboard NOC.*
