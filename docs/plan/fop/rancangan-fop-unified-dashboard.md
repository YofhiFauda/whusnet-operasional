# Rancangan FOP Unified Dashboard (Command Center Operasional 1 Halaman)

Dokumen ini merinci analisa mendalam atas keterbatasan FOP Dashboard saat ini serta rancangan arsitektur dan antarmuka visual baru: **FOP Operational Command Center**, yang menyatukan pemantauan seluruh modul lapangan dalam satu halaman kerja terpadu.

---

## 1. Analisa Mendalam: Mengapa Dashboard FOP Saat Ini Kurang & Terlalu Polos?

### 1.1. Kelemahan Visual (Terlalu Polos & Monoton)
1. **Stat Cards Monoton**: Hanya menampilkan 4 kartu angka skalar (`Antrean Survey`, `Perlu Aksi FOP`, `Sedang Berjalan`, `Selesai Hari Ini`) dalam kotak polos tanpa elemen visual grafis (tidak ada progress ring, visual ratio bar, status comparison, atau micro-trend).
2. **Ketiadaan Hierarki Visual Kritis**: Seluruh angka dan kartu tampak berbobot sama. Insiden darurat (seperti SLA breach atau keterlambatan eskalasi) hanya ditandai badge teks kecil tanpa alarm visual berdenyut atau visual alert banner yang memicu atensi cepat.
3. **Papan Tim Datar**: Kotak tim FOP berupa list teks putih/abu-abu monoton. Tidak ada progress visual yang memperlihatkan berapa persen target harian tim tercapai secara grafis, serta tidak ada indikator visual kapasitas teknisi.
4. **Desain Belum Memanfaatkan Komponen Modern**: Aplikasi memiliki design system kaya (Alpine.js, drawer slide-over, dynamic badge token), namun Dashboard FOP saat ini belum memanfaatkannya secara optimal.

### 1.2. Keterbatasan Informasi (Terlalu Terbatas & Terisolasi)
Operator FOP bertanggung jawab penuh atas kelancaran eksekusi lapangan, namun dashboard saat ini hanya menyajikan data yang sangat terisolasi:
1. **Buta Terhadap Pipeline Pelanggan (Customer Onboarding & Delivery)**: FOP hanya melihat "Antrean Survey". FOP tidak bisa memantau tahapan setelah survey: berapa pelanggan yang tertahan di review hasil survey, berapa yang menunggu jadwal pasang, berapa yang sedang dipasang, dan berapa yang tertahan di Verifikasi Pemasangan oleh Admin.
2. **Terisolasi dari Tiket Gangguan & Eskalasi (Ticketing)**: Tiket gangguan lapangan (MTN), permintaan ganti modem/relokasi (C-REQ), dan gangguan massal (Backbone CUT / ODP LOS) tidak tampak sama sekali di dashboard FOP sebelum diubah manual menjadi task. FOP tidak memiliki *early visibility* terhadap beban komplain lapangan yang sedang menumpuk.
3. **Informasi Gudang & Material Lapangan Sangat Minimal**: Hanya menampilkan 3 angka skalar (keluar, terpakai, sisa di teknisi). FOP tidak dapat melihat:
   - Apakah stok material vital (ONT/Modem, Drop Core, Roset) di gudang cabang menipis?
   - Berapa modem hasil retur / cabut pelanggan (DEAC / Swap) yang masih transit di tangan teknisi dan belum disetor ke gudang cabang?
4. **Workload Teknisi Tidak Proporsional**: Hanya daftar statis nama teknisi dan status "aktif/standby". FOP tidak dapat melihat rasio pembagian tugas (apakah ada teknisi yang overcapacity 6 task sementara teknisi lain 0 task) serta alat kerja (Work Tools: OPM, Fusion Splicer) apa yang dibawa masing-masing teknisi.
5. **Ketiadaan Visibilitas Infrastruktur ODP**: Saat merencanakan penugasan pasang baru, FOP butuh mengetahui kondisi ODP di wilayah terkait (apakah port ODP sudah hampir penuh >85% sehingga butuh penanganan khusus).

---

## 2. Solusi: Konsep Unified Operational Command Center (1 Halaman)

FOP Dashboard diubah menjadi **Command Center Operasional 1 Halaman** yang mengintegrasikan monitoring seluruh modul yang bersentuhan dengan FOP tanpa perlu berpindah-pindah halaman menu.

### Wireframe & Struktur 6 Zona

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ ZONA 1: COMMAND HEADER & REAL-TIME EMERGENCY TICKER                                    │
│ [POP Cabang Selector] [Live Shift Indicator] [Alert Banner: 2 SLA Breach & 1 ODP LOS]   │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ ZONA 2: OPERATIONAL HEALTH PULSE (6 HERO METRIC TILES DENGAN RING & MINI PROGRESS)     │
│ ┌──────────────┬──────────────┬──────────────┬──────────────┬──────────────┬──────────┐ │
│ │ 1. Pipeline  │ 2. Task Hari │ 3. Tiket     │ 4. SLA       │ 5. Material  │ 6. Armada│ │
│ │    Delivery  │    Ini (82%) │    Gangguan  │    Health    │    & Transit │    Teknisi│
│ │    18 Aktif  │    14/17 Sls │    5 Open    │    94% OnTime│    4 SN Retur│ 8/10 On  │ │
│ └──────────────┴──────────────┴──────────────┴──────────────┴──────────────┴──────────┘ │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ ZONA 3: CUSTOMER DELIVERY PIPELINE (VISUAL STEPPER FUNNEL)                             │
│ [Registrasi] ➔ [Antrean Survey (4)] ➔ [Review ACC (2)] ➔ [Antrean Pasang (5)] ➔       │
│ ➔ [Instalasi Berjalan (3)] ➔ [Verifikasi Admin (4)] ➔ [Siap Billing / Aktif]          │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ ZONA 4: PUSAT EKSEKUSI OPERASIONAL (SPLIT 2 KOLOM RESPONSIF)                           │
│                                                                                        │
│ ┌── KOLOM KIRI (60%): TACTICAL BOARD ──────────┐ ┌── KOLOM KANAN (40%): TELEMETRY ───┐ │
│ │ [Tab: Tim FOP] [Tab: Antrean] [Tab: Tiket]   │ │ 1. RADAR TEKNISI & BEBAN KERJA    │ │
│ │                                              │ │    • Tek. Budi: [███████-] 3/4 Aktif │ │
│ │ (Papan Tim Interaktif Drag & Drop dengan     │ │    • Tek. Dani: [████████] 4/4 Seles │ │
│ │  Progress Bar Visual, SLA Timer Countdown,   │ │    • Tek. Rian: [--------] Standby   │ │
│ │  Avatar Anggota, & Quick Preview Drawer)     │ │ 2. MATERIAL & MODEM TRANSIT       │ │
│ │                                              │ │    • Drop Core: 420m terpakai     │ │
│ │ ATAU                                         │ │    • ONT Stok Cabang: 8 unit (LOW)│ │
│ │ (Antrean Survey & Tiket Gangguan Lapangan)   │ │    • Retur Transit: 4 SN di tekn. │ │
│ │                                              │ │ 3. JARINGAN & ODP WATCHLIST       │ │
│ │                                              │ │    • ODP-JTS-04: Port 7/8 (88% RED│ │
│ └──────────────────────────────────────────────┘ └───────────────────────────────────┘ │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ ZONA 5: QUICK ACTION DRAWERS & MODALS (ZERO PAGE RELOAD)                               │
│ Slide-over drawer untuk inspeksi cepat detail pelanggan / task / tiket tanpa navigasi  │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Rincian Modul dalam 1 Halaman

### Modul 1: Customer Onboarding & Delivery Pipeline (Funnel Visual)
- **Visual**: Stepper horizontal interaktif berdesain modern dengan garis alur, nomor kuantitas per tahap, dan alert badge jika ada antrean tertahan > SLA.
- **Tahapan**:
  1. *Registrasi*: Pelanggan baru terverifikasi CS.
  2. *Antrean Survey*: Pelanggan siap disurvey (SLA countdown 1x24 jam).
  3. *Review Survey*: Menunggu validasi kelayakan teknis/kabel oleh FOP/Admin.
  4. *Siap Pasang*: Hasil survey disetujui, siap dijadwalkan pemasangan.
  5. *Sedang Dipasang*: Task instalasi sedang dikerjakan teknisi hari ini.
  6. *Verifikasi Admin*: Pemasangan selesai fisik, menunggu verifikasi speedtest/SN modem/redaman oleh FOP/Admin.
- **Aksi Cepat**: Klik salah satu tahapan akan memfilter list di tab antrean bawah secara langsung.

### Modul 2: Tactical Field Tasks & Tim FOP (Papan Kerja Tim)
- **Visual**: Kartu tim modern dengan header gradient halus, progress bar persentase penyelesaian tim, avatar tumpuk, dan kartu task dengan drag & drop di desktop serta modal switch di mobile.
- **Data Integrasi**: Setiap task menampilkan nomor task, tipe task (Survey, PSB, MTN, C-REQ Swap Modem, Relokasi, DEAC), nama pelanggan, alamat ringkas, dan timer countdown SLA dinamis.

### Modul 3: Tiket Gangguan & Insiden Lapangan (Ticketing Telemetry)
- **Visual**: List tiket gangguan terbuka yang dialokasikan ke POP FOP dengan status prioritas berwarna:
  - Merah: Backbone CUT, ODP LOS, Komplain Emergency.
  - Oranye: Maintenance (MTN) Pelanggan.
  - Biru: Customer Request (C-REQ: Pindah Lokasi, Ganti Modem, Tambah Modem).
- **Aksi FOP**: Tombol 1-klik untuk "Buat Task FOP & Jadwalkan" langsung ke tim aktif.

### Modul 4: Radar Armada & Kapasitas Teknisi (Resource Utilization)
- **Visual**: Roster teknisi dilengkapi:
  - Status pulse real-time: **Aktif** (hijau berdenyut), **Terjadwal** (biru), **Standby** (abu-abu), **Lapor Nanti** (ungu).
  - Bar beban kerja harian: Menampilkan rasio task selesai vs total task hari ini.
  - Badge Alat Kerja: Menampilkan apakah teknisi membawa Fusion Splicer, OPM, atau Cleaver (berdasarkan `TaskWorkTool` / master alat).
  - Indikator Workload Imbalance: Peringatan jika seorang teknisi memiliki > 4 task sementara teknisi lain menganggur.

### Modul 5: Material & Gudang Lapangan (Field Inventory & Custody Watchlist)
- **Visual**:
  - Telemetri pemakaian material pasif (kabel drop core dalam meter, roset, fast connector).
  - Indikator stok kritis ONT/Modem di gudang POP (indikator merah jika < safety stock).
  - **Watchlist Modem Retur Transit**: Daftar modem yang dicabut teknisi (dari task DEAC / Swap) yang statusnya masih `RETURNED` di tangan teknisi dan belum diterima gudang cabang. Mencegah modem hilang di lapangan!

### Modul 6: Kapasitas ODP/ODC (Network Glance)
- Status kode per 2026-10-23: widget Red Zone sudah memakai `NetworkNode` bertipe `has_ports` dan `monitors_capacity`, termasuk ODP/ODC. `Distribution` adalah label CID dan bukan sumber kapasitas.
- Kapasitas berasal dari jumlah `network_node_ports`; port dengan `is_used=true` dihitung terpakai. `FopUnifiedDashboardService::getOdpSummary()` membatasi POP scope, menghitung Red Zone, lalu menampilkan lima node terpadat di `_distribution_glance.blade.php`.
- Ambang mengikuti `network_node_types.red_zone_threshold`; seeder ODP/ODC memakai 80%, bukan 85% tetap pada rancangan awal. Nilainya dapat diubah melalui master tipe node.
- Angka bergantung pada ketepatan data port. Tujuh mapping topologi lama yang ambigu masih menunggu operator; widget belum membuktikan kapasitas fisik semua jalur atau kapasitas PON.

---

## 4. Keamanan & RBAC Scope

1. **Scope POP Mutlak**: Seluruh query menggunakan `EffectiveAccessService::getAllowedPopIds($user)` dan `hasAllPopAccess($user)`. User FOP cabang Jetis hanya melihat data di cabang Jetis.
2. **Pemisahan Otorisasi**:
   - Guard halaman: `$this->authorize('viewAll', Task::class)`
   - Tiket: Dilindungi gerbang `tickets.view`
   - Gudang: Dilindungi gerbang `warehouse_custody.view`
3. **Optimasi Performa**:
   - Nilai agregat angka skalar di-cache selama 30–60 detik per user dan per POP.
   - Tidak ada caching objek Eloquent Collection (menghindari bug un-serialization).
   - Menghindari N+1 query melalui subquery SQL agregat dan eager loading terukur.

---

**Koreksi 2026-10-23:** Baris audit 12 Oktober yang menyebut model `Odp`/`OdpPort` adalah catatan implementasi lama. Kode saat ini memakai `NetworkNode`/`NetworkNodePort` dan `getOdpSummary()` untuk widget Red Zone; ambang bawaan seeder ODP/ODC adalah 80%.

## 5. Audit implementasi terhadap temuan dashboard FOP (12 Oktober 2026)

Ini adalah status kode lokal pada 12 Oktober 2026 setelah implementasi temuan. Status ini belum membuktikan bahwa migrasi, seeder, atau perubahan kode sudah terpasang pada server operasional.

| Temuan awal | Hasil audit kode lokal | Bukti utama |
| --- | --- | --- |
| Rekap `/warehouse/usage` ada tetapi FOP tidak punya `warehouse.view`. | **Sudah ditangani di kode.** Route menerima `warehouse.view\|warehouse.usage.view`; FOP diberi izin sempit `warehouse.usage.view` dan Dashboard FOP menautkan Rekap Pemakaian. Akses pada database yang sudah berjalan bergantung pada sinkronisasi `RolePermissionSeeder`. | `routes/web.php` (route `warehouse.usage.index`), `database/seeders/RolePermissionSeeder.php` (role `fop`), `resources/views/fop/partials/_inventory_telemetry.blade.php` |
| Jarak GPS dan flag radius mulai kerja direkam tetapi tidak tampil. | **Sudah tampil.** Blok “Lokasi Mulai Kerja” pada detail Task dan riwayat FOP menampilkan metode QR, jarak, status `ok/perlu_review/out_of_radius/tanpa_koordinat`, serta tautan Maps bila titik absen tersimpan. | `app/Models/Task.php::attendanceFlag()`, `resources/views/tasks/show.blade.php`, `resources/views/fop_tasks/history_detail.blade.php` |
| `qr_scan_logs` tidak dibaca controller/view. | **Selesai di kode:** riwayat semua hasil scan, termasuk gagal, dapat dibuka Owner/Atasan/FOP melalui navigasi. Halaman menampilkan waktu, user, POP, alasan, jarak, dan tautan lokasi bila koordinat tersedia. Filter serta isi dibatasi scope POP. | `QrScanLogController`, `resources/views/qr-scan-logs/index.blade.php`, `resources/views/layouts/app.blade.php` |
| Kontrol gudang mengutamakan kendala struktural dan mengakui celah kolusi. | **Keputusan user:** tetap memakai jejak transaksi, kontrol serah-terima fisik, dan stock opname sesuai kebutuhan; tidak menambah penanda risiko otomatis. Kolusi tetap memerlukan pemeriksaan manusia. | `docs/plan/warehouse/kontrol-anti-manipulasi.md` |
| “SLA Compliance %” memakai rasio buatan dari tiga antrean berbeda. | **Selesai di kode:** persentase memakai pelanggaran SLA nyata dari tiket terbuka dan task hari ini. Label serta footer menyebut dua populasi itu dan menampilkan jumlah pelanggaran masing-masing. | `FopUnifiedDashboardService::getHeroMetrics()`, `_hero_metrics.blade.php` |
| Teknisi yang baru terjadwal dihitung sebagai “utilized”. | **Sudah diperbaiki.** Persentase sekarang `activeTech / totalTech`; status `terjadwal` dihitung terpisah dan tidak masuk pembilang. | `app/Services/FopUnifiedDashboardService.php::getHeroMetrics()` |
| Distribution Glance menampilkan badge “Terkoneksi” hardcoded. | **Selesai di kode:** widget lama diganti kapasitas ODP yang bersumber dari entitas ODP dan port child, bukan label Distribusi CID. Red Zone dihitung dari port terpakai ≥85%, dibatasi POP. | `Odp`, `OdpPort`, `OdpController`, `FopUnifiedDashboardService::getOdpSummary()` |
| Modem “Maintenance” pasti nol karena jalur pemasangan modem maintenance belum ada. | **Selesai di kode:** jalur INSTALL maintenance sudah ada; angka serta seri chart Maintenance kini ditampilkan, C-REQ dipisahkan dari Instalasi, dan komentar usang dihapus. | `TaskMaintenanceController`, `FopAnalyticsController::buildModemUsageRanking()`, `fop/analytics.blade.php` |
| Semua tabel Analitik tanpa tautan. | **Selesai di kode:** backlog dan durasi menautkan detail task sesuai izin. Ranking alat, material, kecamatan, dan leaderboard teknisi menautkan rincian sumber angka dengan filter periode/POP serta pembatasan scope pada query. | `FopAnalyticsController::drilldown()`, `fop/analytics-drilldown.blade.php`, parsial tabel analitik |

### Status implementasi lanjutan

Sembilan temuan telah ditangani pada kode lokal. Riwayat gudang dan stock opname tetap menjadi kontrol manual sesuai pilihan user. Master ODP terpisah dari Distribusi CID; pencatatan port dan kapasitasnya manual. Halaman Red Zone belum memiliki pemetaan port ke pelanggan atau entitas ODC.

Sebelum dipakai pada database operasional, jalankan migrasi ODP dan sinkronkan `OdpFeatureSeeder` serta `RolePermissionSeeder`; izin `warehouse.usage.view` dan `qr_scan_logs.view` juga perlu tersinkron. Tes lokal memverifikasi akses dan query; tes tidak membuktikan bahwa migrasi/seeder sudah dijalankan pada database operasional.
