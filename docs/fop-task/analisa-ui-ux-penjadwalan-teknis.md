# 📋 Analisa & Blueprint UI/UX: Modul FOP & Penjadwalan Teknis Lapangan
> **Fokus Utama:** Eliminasi *interaction friction*, penyesuaian dengan alur kerja riil koordinator lapangan & teknisi ISP Whusnet, dan transformasi dari sekadar form data statis menjadi **Operational Dispatch Workbench** yang cepat, tangguh, dan bebas *fluff/AI slop*.

---

## 1. Realita Operasional Lapangan (The Real Daily Operational Reality)

Sistem penjadwalan ISP tidak beroperasi di ruang hampa. Di lapangan, Koordinator FOP berhadapan dengan dinamika berikut:

```
[07:30 Pagi] ──────────► [11:30 Siang] ──────────► [14:00 Siang] ──────────► [16:30 Sore]
Morning Dispatch          Emergency Incident        Field Blockers             Daily Closing
15-30 task malam         Kabel FO Cut putus        Pelanggan tidak ada        Review laporan,
diplot ke 3-4 tim         Tarik teknisi instan      di rumah / redaman loss    cek barang modal
dalam waktu < 5 mnt.      tanpa bikin task kacau.   Pending & geser jadwal     (modem, dropcore).
```

### Mengapa UI/UX Saat Ini Menghambat (Root Cause):
1. **FOP Menyerah dan Kembali ke WhatsApp:** Jika memindahkan teknisi butuh 4 kali klik, 2 modal konfirmasi, dan reload halaman, FOP akan memilih mengetik penugasan di grup WhatsApp. Sistem web akhirnya hanya menjadi beban administratif penginputan ganda (*double entry*).
2. **Teknisi Lapangan Menghadapi Hambatan Fisik:** Teknisi bekerja di atas tiang/tangga, membawa kabel, tangan kotor, terkena sinar matahari terik, dan sinyal HP tidak selalu 4G stabil. Form yang panjang dan tombol kecil tidak akan pernah dipakai dengan benar di lapangan.
3. **Aturan Bisnis Keras (Hard Business Rules) yang Harus Dijaga UI:**
   - Task `SURVEY` & `PSB` **dilarang dibuat atau diubah tipenya secara manual** di menu FOP (wajib auto-sync dari registrasi pelanggan).
   - Task `DEAC` (Ambil Modem) berasal dari proses Putus Langganan.
   - Task `MTN` & `C-REQ` wajib terikat ke Customer ID (`CID`) dan membaca data teknis tiket (ODP, redaman, perangkat).
   - Hanya `O-REQ` dan `INFRA` (FO Cut, perbaikan tiang/kabel) yang dibuat murni manual.
   - Task dengan `client_request_date` di masa depan wajib berada di dasar antrean, dan hanya naik ke prioritas atas pada tanggal yang diminta pelanggan.

---

## 2. Bedah 5 Skenario Bottleneck UI/UX & Solusi Konkret

---

### Skenario 1: Morning Dispatch (Plotting 25 Task Pagi dalam < 5 Menit)

#### Masalah Saat Ini:
- FOP harus membuka satu per satu baris task, mengklik modal edit atau switch, memilih teknisi di dropdown checkbox sempit, lalu klik simpan.
- FOP "buta" kapasitas: tidak tahu teknisi mana yang sudah dapat 4 task dan mana yang masih 0 task.
- Filter pencarian berbasis reload penuh (`<form method="GET">`) membuat ritme kerja terhenti berkali-kali.

#### Solusi UX Konkret:
1. **Interactive Team Roster Strip (Pita Alokasi Tim di Header):**
   - Header menampilkan kartu ringkas tim hari ini beserta beban kerjanya:
     ```text
     [ Tim 1 (Andi, Budi) · 4 Task ]  [ Tim 2 (Karim, Joko) · 2 Task ]  [ Standby: Samsul (0) ]
     ```
   - Klik kartu tim langsung memfilter tabel hanya menampilkan task tim tersebut (tanpa reload halaman).
2. **Inline Quick-Assign Popover (1-Klik Tanpa Modal):**
   - Di kolom *Teknisi/Tim*, klik area sel memunculkan popover ringan (bukan modal layar penuh):
     - Menampilkan daftar tim aktif hari ini + tombol `[+ Bikin Tim Baru]`.
     - FOP tinggal klik nama tim $\rightarrow$ sel otomatis ter-update via AJAX dan mengalirkan event Alpine/Echo.
3. **Workload Indicator Pill:**
   - Di setiap pemilihan personil, tampilkan status real:
     - `🟢 Samsul (0 task · Siap)`
     - `🟡 Andi (2 task · Normal)`
     - `🔴 Budi (4 task · Penuh)`
4. **Tombol "Salin Format WA Dispatch":**
   - FOP sering harus mengabari tim via WA. Sediakan tombol copy 1-klik yang meng-generate pesan terformat rapi:
     ```text
     *PENUGASAN TIM 1 (Andi & Budi) - 23/09/2026*
     1. [MTN] Bpk Ahmad - Ds. Siman (ODP-SMN-04) - FO Cut Dropcore
        Maps: https://maps.google.com/?q=-7.87,111.46
     2. [PSB] Ibu Siti - Ds. Ronowijayan - Paket 20 Mbps
     ```

---

### Skenario 2: Emergency Incident / FO Cut (Penanganan Gangguan Massal)

#### Masalah Saat Ini:
- Jika terjadi kabel putus tertabrak truk atau tiang roboh, FOP harus membuat task darurat dan menarik 2 teknisi dari task lain.
- Fitur *Switch Teknisi* saat ini menerapkan validasi kaku: harus ada teknisi pengganti 1-ke-1 (*swap*), dan task tujuan harus memiliki tanggal kerja yang sama persis.
- Jika FOP ingin meminjam 1 teknisi saja untuk bantuan darurat (*reinforcement*), sistem menolak jika task asal ditinggalkan kosong.

#### Solusi UX Konkret:
1. **Preset Cepat "Emergency / FO Cut":**
   - Pada tombol tambah task, sediakan preset 1-klik untuk `INFRA / FO CUT`.
   - Form otomatis mengisi: Prioritas `Urgent`, Status `Terjadwal Sekarang`, dan fokus kursor langsung ke pemilihan lokasi tiang/area.
2. **Split Team / Bantuan Darurat Mode:**
   - Berikan opsi di alur switch: *"Tugaskan sebagai Bantuan Sementara"* tanpa memaksa menukar personil secara 1-ke-1.
   - Task yang ditinggalkan sementara otomatis diberi flag visual `⏸️ Menunggu Teknisi Kembali` agar tidak dianggap terbengkalai.

---

### Skenario 3: Penanganan Task Pending & Penjadwalan Ulang (Reschedule)

#### Masalah Saat Ini:
- Teknisi tiba di rumah pelanggan, namun pelanggan sedang keluar kota atau hujan badai. Teknisi menekan "Pending" di HP.
- Di dashboard FOP, task tersebut kembali menjadi pending, namun FOP kesulitan melihat:
  - Apa alasan pendingnya? (Alasan tersembunyi di dalam modal edit).
  - Kapan pelanggan minta dijadwalkan ulang?
- FOP harus membuka edit task, menghapus centang teknisi lama, mengubah tanggal, lalu menyimpan kembali.

#### Solusi UX Konkret:
1. **Pending Alert Strip & Tab "Perlu Reschedule":**
   - Tambahkan counter badge merah di tab filter: `Pending Lapangan (3)`.
   - Mengklik tab ini menampilkan daftar task yang macet hari ini beserta alasan langsung di kartu/baris:
     > `⚠️ Bpk. Bambang — Pending: Pelanggan minta pasang Sabtu pagi (Alasan: Kerja luar kota)`
2. **One-Click Quick Reschedule Drawer:**
   - Tombol cepat `[Reschedule Jadwal]` langsung memunculkan input tanggal cepat:
     - Pilihan instan: `[Besok Pagi]` `[Sabtu Depan]` `[Pilih Tanggal...]`.
   - Saat tanggal baru dipilih, task otomatis masuk ke antrean *Future Client Date* dan tidak mengotori antrean kerja hari ini.

---

### Skenario 4: Rekonsiliasi & Closing Harian (16:30 Sore)

#### Masalah Saat Ini:
- FOP harus memeriksa apakah semua task selesai dan apakah laporan teknisi valid.
- Riwayat task (`fop-tasks/history`) dan task aktif terpisah total.
- Tidak ada rekapitulasi penggunaan material lapangan (berapa modem yang terpasang hari ini, berapa rol dropcore yang terpakai dari stok teknisi).

#### Solusi UX Konkret:
1. **Closing Progress Ring:**
   - Tampilkan progress bar ringkas di kanan atas:
     `Selesai: 18 | Pending: 2 | Berjalan: 3 | Total: 23 Task (78% Complete)`
2. **Direct Link ke Verifikasi Laporan:**
   - Untuk task yang statusnya `Lapor Nanti` atau `Menunggu Review FOP`, sediakan tombol `[Review Laporan]` langsung mengarah ke detail foto redaman OPM dan serial number modem yang diinput teknisi.

---

### Skenario 5: Antarmuka Teknisi Lapangan (`tasks/own` di Smartphone)

#### Masalah Saat Ini:
- Teknisi membuka web lewat browser HP. Tombol aksi kecil, form laporan teknis panjang.
- Jika sinyal di desa terpencil drop, submit form error dan foto instalasi hilang.

#### Solusi UX Konkret:
1. **Thumb-Zone Action Layout (Ramah Satu Tangan):**
   - Semua tombol aksi utama (`Mulai`, `Maps`, `Lapor Selesai`, `Pending`) diletakkan di **separuh bawah layar HP** (area jangkauan jempol).
2. **Navigasi 1-Klik Langsung ke Maps:**
   - Tombol besar hijau berlogo Google Maps: saat ditekan, langsung membuka aplikasi Google Maps native ke koordinat latitude/longitude pelanggan (`geo:lat,lng` atau URL maps).
3. **Template Cepat Alasan Pending (Radio Chips, Bukan Ketik Keyboard):**
   - Mengetik keyboard di HP saat di lapangan itu menyulitkan. Sediakan pilihan chip alasan yang umum:
     - `[Rumah Kosong / Pelanggan Kerja]`
     - `[Hujan Badai / Petir]`
     - `[Redaman Tinggi (> -27 dB)]`
     - `[Tiang Jauh / Dropcore Kurang]`
     - `[Jalur Pohon / Butuh Ijin Warga]`
     - `[Lainnya...]`

---

## 3. Desain Komponen UI Baru (Anti-AI Slop Specifications)

### A. Roster Summary Bar (Dipasang Tepat di Atas Tabel Desktop)

```text
┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│ 👥 TIM KERJA HARI INI (Rabu, 23 Sep 2026)                                                              │
│                                                                                                        │
│ ┌──────────────────────┐  ┌──────────────────────┐  ┌──────────────────────┐  ┌──────────────────────┐ │
│ │ TIM 1 · PONOROGO     │  │ TIM 2 · SIMAN        │  │ TIM 3 · JETIS        │  │ STANDBY (SOLO)       │ │
│ │ 👤 Andi  👤 Budi     │  │ 👤 Karim  👤 Joko    │  │ 👤 Samsul  👤 Yanto  │  │ 👤 Wito              │ │
│ │ 3 Task (1 On-Prog)   │  │ 2 Task (Semua Beres) │  │ 4 Task (1 Overdue!)  │  │ 0 Task Aktif         │ │
│ └──────────────────────┘  └──────────────────────┘  └──────────────────────┘  └──────────────────────┘ │
└────────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

*Aturan Interaksi:*
- Klik pada salah satu kotak tim $\rightarrow$ tabel otomatis ter-filter menampilkan task tim tersebut.
- Kotak tim dengan task yang *Overdue / Kritis* otomatis berkedip aksen merah lembut (`border-rose-500 bg-rose-50/20`).

---

### B. Baris Tabel Desktop dengan Aksen Visual yang Jelas

Kolom tabel harus efisien, tidak boleh teks bertumpuk tanpa hierarki:

```text
┌───┬──────────┬──────────────┬────────────────────────┬─────────────┬─────────────────┬──────────┬────────┐
│[X]│ Tipe     │ Jadwal       │ Tugas & Pelanggan      │ Lokasi      │ Teknisi / Tim   │ Status   │ Aksi   │
├───┼──────────┼──────────────┼────────────────────────┼─────────────┼─────────────────┼──────────┼────────┤
│[ ]│ [MTN]    │ 10:00 WIB    │ Bpk. Ahmad Dahlan      │ Ds. Siman   │ 👥 Tim 1        │ 🟡 Prog  │ [⋮]    │
│   │ Gangguan │ [HARI INI]   │ CID: SMN-00124         │ RT 02/01    │ (Andi, Budi)    │ 01:20:00 │ Detail │
│   │          │              │ Keluhan: Los Merah     │ ODP-SMN-04  │ [Ganti Tim]     │ SLA Ok   │        │
├───┼──────────┼──────────────┼────────────────────────┼─────────────┼─────────────────┼──────────┼────────┤
│[ ]│ [PSB]    │ 13:00 WIB    │ Ibu Siti Aminah        │ Ds. Ronow.  │ ⚠️ Belum Ada Tim│ ⚪ Terj. │ [⋮]    │
│   │ Pasang   │ [HARI INI]   │ CID: RNW-00891         │ RT 01/03    │ [+ Pilih Tim]   │ Menunggu │ Plot   │
│   │ Baru     │              │ Paket: 20 Mbps Home    │ ODP-RNW-02  │                 │          │        │
└───┴──────────┴──────────────┴────────────────────────┴─────────────┴─────────────────┴──────────┴────────┘
```

*Pembeda Visual Baris:*
1. **Task Hari Ini:** Border tepi kiri tebal amber `border-l-4 border-l-amber-500`.
2. **Task Overdue (Melewati SLA):** Border tepi kiri tebal merah `border-l-4 border-l-rose-600 bg-rose-50/15`.
3. **Task Belum Ditugaskan Teknisi:** Badge kuning menyala `⚠️ Belum Ada Tim` dengan link 1-klik untuk memilih tim.

---

### C. Floating Bulk Action Bar (Saat FOP Mencentang >1 Baris)

Saat FOP mencentang 2 atau lebih task sekaligus, muncul bilah aksi melayang di bagian bawah tengah layar:

```text
┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│  ✓ 3 Task Terpilih   │  [👥 Masukkan ke Tim...]  │  [📅 Reschedule Tanggal]  │  [✕ Batal Pilihan]       │
└────────────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- Aksi ini menyelesaikan masalah pagi hari: FOP memilih 4 task di area Siman sekaligus $\rightarrow$ klik `Masukkan ke Tim...` $\rightarrow$ pilih `Tim 1`. Selesai dalam 2 detik.

---

### D. Perbaikan Modal Tambah/Edit Task (Ganti Modal Raksasa Menjadi Slide-Over Drawer)

Alih-alih dialog pop-up di tengah layar yang menutup seluruh pandangan:
1. **Gunakan Slide-Over Drawer (Sisi Kanan Layar):**
   - Layar geser dari kanan selebar 480px–540px.
   - FOP tetap bisa melihat daftar tabel di sebelah kiri sebagai referensi konteks saat mengisi data.
2. **Tab Separator yang Tegas:**
   - **Tab 1: Form Gangguan / Pelanggan (MTN / C-REQ):**
     - Input utama hanya 1: Ketik CID atau Nama Pelanggan dengan auto-complete.
     - Begitu pelanggan dipilih, seluruh data (POP, Desa, Alamat, Paket, ODP, Koordinat) **terisi otomatis dan terkunci** (tidak perlu dipilih manual lagi).
   - **Tab 2: Form Infrastruktur / Internal (O-REQ / INFRA):**
     - Form manual bebas untuk perbaikan tiang, penarikan kabel distribusi, atau perbaikan switch POP.

---

## 4. Matriks Perbandingan: Sebelum vs Sesudah Perbaikan

| Fitur / Alur | Alur Lama (Beban Tinggi) | Alur Baru (Cepat & Tepat Sasaran) |
| :--- | :--- | :--- |
| **Menugaskan 3 task ke Tim 1** | Buka modal edit 3x, pilih teknisi 3x, klik simpan 3x (Total ~15 klik + 3x reload). | Centang 3 baris di tabel $\rightarrow$ klik `Tugaskan ke Tim` di Bulk Bar $\rightarrow$ pilih Tim 1 (Total 3 klik, tanpa reload). |
| **Melihat siapa teknisi yang kosong** | Harus hafal di luar kepala atau buka tab daftar user. | Terlihat langsung di *Roster Summary Bar* header dan *Workload Pill* samping nama teknisi. |
| **Emergency FO Cut** | Buka form biasa, isi manual semua field, atur prioritas ke Urgent secara manual. | Klik tombol preset `[+ Lapor FO Cut]` $\rightarrow$ form otomatis prioritas Urgent & tanggal sekarang. |
| **Kirim tugas ke WhatsApp teknisi** | Ketik ulang nama, alamat, nomor HP, dan link koordinat satu per satu di HP. | Klik tombol `[Salin Format WA]` pada baris task $\rightarrow$ paste langsung di grup WhatsApp tim. |
| **Teknisi lapor pending di lapangan** | Buka form pending web, ketik teks alasan panjang, simpan. | Pilih chip alasan instan (`Rumah Kosong` / `Redaman Drop`) $\rightarrow$ klik `Simpan Pending`. |

---

## 5. Rencana Tahapan Eksekusi (Implementation Plan)

### Fase 1: Optimasi Tabel & Dispatch Cepat (Sprint Berjalan)
1. Pasang **Roster Summary Bar** di atas tabel `fop_tasks.index` memanfaatkan data `$teams` yang sudah dihitung controller.
2. Tambahkan **Workload Badge** (`🟢 0 task`, `🟡 2 task`, `🔴 4 task`) pada dropdown pemilihan teknisi di form modal.
3. Buat fitur **Bulk Selection & Assignment** (checkbox baris + floating bar untuk assign tim massal).
4. Tambahkan tombol **Salin Format WA Penugasan** di menu dropdown baris task.
5. Tambahkan aksen visual garis tepi kiri (`border-l-4`) untuk task hari ini dan task overdue.

### Fase 2: Peningkatan Interaksi & Penyederhanaan Form
1. Ubah modal form Tambah/Edit menjadi **Slide-Over Right Drawer** agar tidak menutup konteks tabel.
2. Tambahkan pilihan chip alasan pending cepat pada antarmuka teknisi lapangan (`tasks/own`).
3. Sediakan **Inline Popover Quick-Assign** pada kolom tim untuk ganti penugasan tanpa buka form penuh.

### Fase 3: Realtime Workbench & Dispatch Board (Post-MVP)
1. Mode tampilan ganda: Toggle antara **Tabel Grid** dan **Papan Tim Harian (Kanban Board per Tim)**.
2. Integrasi visual peta rute per hari untuk mendeteksi penugasan teknisi yang jaraknya tidak efisien.
