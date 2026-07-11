# ⚠️ KEBUTUHAN DARI TASK FOP DAN TASK TEKNISI

### 1. Ketika FOP membuatkan Jadwal untuk Task yang di tugaskan pada teknisi maka teknisi yang di tugaskan tersebut akan otomatsi membuat TEAM (Tidak seperti sekarang ini yang dimana pembuatan teknisi masih manual dan terbilang membuang Waktu serta tidak edisien) dengan ketentuan jika teknisi yang di tugaskan pada Task tersebut lebih dari 1 ( >1) maka akan otomatis membuat Team, Jika teknisi yang di tugaskan pada task tersebut Kurang dari 1 ( < 1 ) maka itu termasuk solo. Task yang hanya di kerjakan 1 orang teknisi maka dapat di lempar kedalam team dengan validasi di letakan untuk di letakkan pda team mana. jika teknisi tersebut sebelumnya sudah berada di dalam team lain maka Task yang di kerjakan 1 orang teknisi tadi bisa otomatis masuk kedalam team karena pada Task lain dia sudah masuk team
**CONTOH**
Pada Task A yang di tugaskan oleh FOP untuk Teknisi Abul dan Teknisi Karim maka otomatis akan membuat TEAM 1 yang berisikan Task A dengan yang di kerjakan oleh Abdul dan Karimm
Pada Task B yang di tugaskan oleh FOP untuk Teknisi Karim dan Teknisi Joko maka otomatis akan masuk kedalam TEAM 1 yang berisikan Task A dengan yang di kerjakan oleh Abdul dan Karimm dan Task B yang di kejarkan oleh oleh Teknisi Karim dan Teknisi Joko
Pada Task C yang di tugaskan oleh FOP untuk Teknisi Joko maka otomatis akan masuk kedalam TEAM 1 yang berisikan Task A dengan yang di kerjakan oleh Abdul dan Karimm dan Task B yang di kejarkan oleh oleh Teknisi Karim dan Teknisi Joko dan TASK C yang di kerjakan oleh teknisi JOKO
Pada Task D yang di tugaskan oleh FOP untuk Teknisi Samsul karena samsul tersebut masih belum ada team karena sebelumnya samsul belum di tugaskan oleh FOP untuk Task apapun yang memiliki team, disini maka FOP bisa memindahkan Task D yang di kerjakan oleh samsul kedalam TIM 1, setiap memasukan team harus ada validasi
Pada Task E yang di tugaskan oleh FOP untuk Teknisi Yanto dan Teknisi Wito maka otomatis akan membuat TEAM 2 yang berisikan Task E dengan yang di kerjakan oleh Yanto dan Wito, hal ini berbeda dengan Team 1 karena yanto dan wito ini sebelumnya belum di tugaskan pada task apapun dan pada team manapun, maka dari itu yanto dan wito membuat TIM 2 Secara Otomatis

### 2. Dapat melakukan Switch Teknisi dari Team 1 Kedalam Team 2 dengan validasi untuk di tugaskan dengan Task apa pada team tersebut. Dan Switch Teknisi ini dilakukan dengan sangat cepat dan efisien dan tidak disarankan Switch dengan manual dan dengan proses yang ribet dan lama
**CONTOH** 
Pada Task FOP terdapat TASK A yang dikerjakan oleh Teknisi Abul dan Teknisi Karim yang dimana berada didalam TIM 1, Lalu FOP ingin memindahkan Teknisi Abdul dengan Task A ingin di pindah kedalam Task E yang berada di TIM 2 yang sudah ada teknisi yanto dan wito. pada saat pemindahan ini FOP akan mendapati Validasi yang dimana Task A yang sebeumnya di kerjakan oleh Abdul akan di pindah tugaskan ke siapa dan Abdul yang di pindah ke TIM 2 akan mengerjakan Task Apa


### 3. Dapat melakukan Switch Task yang sudah di tugaskan pada Team 1 dan di pindah ke Team 2 dengan Validasi siapa yang akan mengerjakan Task yang di switch tersebut. Proses ini juga harus di lakukan dengan instant dan hindari dengan cara yang manual yang bisa memakan banyak Waktu dan ribet
**CONTOH**
Pada Task FOP / Dashboard FOP (ini nanti bisa kita diskusikan mana yang bisa flexible) terdapat Task A yang di kerjakan oleh Abdul dan Karim yang terdapat di TIM 1 lalu FOP ingin Memindahkan  TASK A dari TIM 1 menjadi TASK B. pada saat pemindahan Task tersebut harus ada Validasi Task tersebut di kerjakan oleh teknisi siapa pada TIM 2

### 4. Terdapat penganggung Jawab pada setiap Team, Penanggung Jawab tersebut dapat di tunjuk pribadi oleh FOP
**CONTOH**
Pada TIM FOP dapat menunjuk Penanggung jawab dari TIM 1 tersebut

### 5. FOP TASK memiliki tampilan like Excel Agar Ketika di gunakan bisa flexible dan semuah mungkin sehingga terhindar dari system yang masih manual.

### 6. Pada Detail Task kektika menekan tombol Laporan Survey/Laporan Pemasangan / Laporan Maintenance, dll,  nanti kan muncul Dialog Aler dengan button Lapor sekarang atau Lapor nanti dengan status pada Task tersebut dan FOP menjadi Lapor Nanti

### 7. Pada Detail Task Button Lapor nanti yang sekarang ini di ganti menjadi pending dengan status Pending

### 8. FOP bisa request pemamsangan tanggal berapa pada saat survey yang nantinya tanggal request tersebut akan muncul di paling bawah dan ketika sudah jadwalnya pemasangan nantinya akan menjadi paling atas

### 9. Kolom Status yang ada pada Task FOP ganti dari Dropdown menjadi Status realtime bisa dari task teknisi, task pending. Jika terdapat Task yang pending nantinya akan Kembali ke halaman TASK FOP yang dimana nanti akan di jadwalkan oleh teknisi yang berbeda, namun harus tercatat pada Riwayat 

### 10. Pada Riwayat Task FOP tersebut semua status yang didapat dari task dengan detail lengkap seperti pada detail Task namun harus beserta dengan Laporan survey, laporan pemasangan, Laporan Maintenance, dll. terdapat SLA Deadline(untuk mengetahui total Waktu pengerjaan pada sebuah task, termasuk itu Ketika tasknya nanti menjadi pending, lapor nanti atau laporan selesai).

### 11. pada /tasks-saya dengan Default hanya ada button Mulai Survey, Mulai Pemasangan, Mulai Maintenance, dll, dan Ketika tombol Mulai tadi di tekan maka Tombolnya akan menjadi 2 yaitu ada Detail dan isi Laporan dan nanti di Bawah Card tersebut terdapat Card yang dimana teradpat Koordinat dengan tombol Maps

### 12. FOP atau role lain yang berwenang bisa cancel Survey atau Pemasangan karena suatu alasan (seperti data ganda, rumah di renovasi, salah input POP, dll)

### 13. Pada saat Survey terdapat kolom untuk request hari pemasangan. Jika terdapat request hari pemasangan akan masuk Task namun berada di paling bawah, dan ketika sudah hari pemasangan maka akan berada di paling atas (untuk SLA nanti didiskusikan bagaimana caranya)

### 14. Untuk penjadwalan FOP di modal Tambah Task FOP, ketika Task tersebut Survey, Pemasangan, Deac, Relokasi, C-REQ harus menyertakan ID Pelanggan di kolom Penugasan/Pelanggan. Ketika ID Pelanggan terisi maka POP dan Area(Desa) mengikuti data dari pelanggan tersebut. Sedangkan untuk O-REQ dan INFR REQ, itu bisa request POP dan Area(Desa) secara manual

### 15. saya akana konfirmasi untuk survey dan pemasangan harus bberdasarkan alur registrai, survey dan pemasangan. tidak boleh menambah atau mengedit task dengan tipe survey atau pemasangan di TASK FOP. untuk Tambah Task hilangkan Survey dan Pemasangan, untuk edit task buat survey dan pemasngan menjadi disable

---


# 🔍 Analisis Sistem Penjadwalan Task FOP Otomatis (Auto-Team & Excel-Like)

Sistem penjadwalan ini dirancang untuk meminimalkan beban administratif bagi Koordinator Field Operations (FOP). FOP tidak perlu lagi membuat tim secara manual satu-persatu sebelum menugaskan tiket kerja; sebaliknya, **sistem secara reaktif menghitung dan membentuk struktur tim** berdasarkan penugasan teknisi pada hari/tanggal tersebut.

---

## 1. Pergeseran Alur Kerja (Workflow Conceptual Shift)

| Proses | Model Lama (Manual) | Model Baru (Otomatis & Excel-Like) |
| :--- | :--- | :--- |
| **Pembuatan Team** | FOP masuk menu "Kelola Team", membuat tim, menentukan nama, memilih roster, baru kemudian mengaitkan task ke team. | FOP **tidak perlu membuat team**. FOP langsung membuat task, mengisi tanggal, dan memilih teknisi serta PIC task tersebut. |
| **Penentuan Team** | FOP memilih `team_id` secara manual di form edit/create task. | Sistem mendeteksi relasi overlap teknisi pada tanggal yang sama untuk membentuk **Auto-Team**. |
| **Fleksibilitas Roster** | Roster tim kaku per hari dan memicu error konflik jika seorang teknisi dimasukkan ke dua tim berbeda. | Roster tim dinamis. Jika teknisi dialihkan dari Task A ke Task B, sistem otomatis merekonstruksi struktur tim hari itu. |

---

## 2. Aturan Pengelompokan Team Otomatis (Auto-Team Logic Rules)

Sistem akan menggunakan algoritma berbasis **Connected Components (Komponen Terhubung)** pada graf relasi teknisi harian untuk mengelompokkan tim secara deterministik:

### Skenario A: Task Multi-Teknisi ($>1$ Teknisi) $\rightarrow$ Auto-Team
Setiap task yang ditugaskan ke lebih dari 1 teknisi akan otomatis didefinisikan sebagai tim dasar.
* **Contoh:** Task 1 ditugaskan ke **Andi** & **Budi**. Sistem otomatis membuat `FopTaskTeam` (misal bernama: `"Tim Andi & Budi"`) untuk tanggal tersebut.

### Skenario B: Hubungan Transitif (Overlapping Technicians)
Jika seorang teknisi dijadwalkan pada beberapa task berbeda dengan teknisi yang berbeda-beda pula pada hari yang sama, maka seluruh teknisi dan task tersebut akan digabungkan ke dalam **satu team yang sama**.
* **Contoh:**
  * Task 1 (Tanggal 10/07): Ditugaskan ke **Andi** & **Budi**.
  * Task 2 (Tanggal 10/07): Ditugaskan ke **Budi** & **Candra** (Budi dijadwalkan pada task lain).
  * **Hasil:** Karena **Budi** menjadi jembatan (overlap) antara kedua task tersebut, sistem secara otomatis menggabungkan **Andi**, **Budi**, dan **Candra** ke dalam **1 Team yang sama** (Roster: `[Andi, Budi, Candra]`). Task 1 dan Task 2 akan memiliki `team_id` yang sama.

### Skenario C: Task Single-Teknisi ($1$ Teknisi) $\rightarrow$ Auto-Merge (jika ada jembatan) atau Manual Drop-In

Jika sebuah task hanya membutuhkan 1 teknisi, ada 2 sub-kasus:

**C1 — Auto-merge (teknisi sudah punya team lain di tanggal sama):** Misal Task 3 ditugaskan ke **Joko**, dan Joko sudah jadi anggota Team 1 lewat task lain di hari yang sama. Task 3 **otomatis** masuk Team 1 tanpa aksi FOP — ini murni perpanjangan Skenario B (Joko jadi jembatan).

**C2 — Manual Drop-In (teknisi belum punya team sama sekali):** Misal Task 4 ditugaskan hanya ke **Dedi**, dan Dedi belum overlap ke task manapun hari itu.
* **Default:** Task berdiri sendiri (solo task) dengan `team_id = null`.
* **Fleksibilitas FOP (Drop-In):** FOP dapat memilih memasukkan Task 4 ke salah satu Team yang sudah terbentuk pada tanggal tersebut (misal Team `"Andi & Budi"`), dengan validasi wajib.
* **Hasil:** `team_id` Task 4 diubah ke ID Team tujuan, roster ditambahkan **Dedi**, nama Team ter-update dinamis (mis. `"Tim Andi & Budi & Dedi"`).

**Edge case C3 — solo task narik teknisi dari 2 team berbeda:** kalau 1 task menugaskan 2 teknisi yang masing-masing sudah ada di team terpisah (mis. Karim di Tim 1, Wito di Tim 2), sistem TIDAK auto-union kedua team. Sistem munculkan dialog validasi ke FOP untuk memilih: taruh di Tim 1, Tim 2, atau bikin Tim baru gabungan. Lihat detail di SOLUSI poin 1.

---

## 3. Penanggung Jawab Team (PIC / Leader)

Sesuai requirement *"Terdapat penanggung jawab pada setiap Team"* (dikonfirmasi: level **Team**, bukan per-Task):
1. Kolom `pic_id` (foreign key ke tabel `users`) ditambahkan pada tabel `fop_task_teams`, bukan `fop_tasks`.
2. FOP menunjuk PIC dari salah satu anggota roster Team tersebut lewat dropdown/toggle di team card — dilakukan di level Team, terlepas dari task mana pun di dalamnya.
3. Di backend, data PIC disinkronkan ke tabel `task_teams` (tabel eksekusi teknisi) dengan flag `role_in_task = 'lead'` untuk seluruh task yang berada di team tersebut, anggota lain `role_in_task = 'teknisi'`.
4. Karena roster team dinamis (bisa berubah lewat rebuild/switch), kalau PIC ter-switch keluar dari team, sistem wajib re-prompt FOP pilih PIC baru — lihat SOLUSI poin 4.

---

## 4. Alur Sinkronisasi & Switch Teknisi (Real-time Rebuilding)

Ketika FOP mengubah teknisi atau PIC pada suatu task (karena urgensi atau perpindahan tugas dari Task A ke Task B):
1. Database `fop_task_user` (pivot teknisi) dan `pic_id` pada task tersebut diperbarui.
2. Event listener atau Controller memicu fungsi **`FopTaskTeamService::rebuildTeamsForDate($task_date)`**.
3. Fungsi rebuild tersebut akan:
   * Mengambil semua task aktif pada tanggal tersebut.
   * Membangun graf relasi teknisi (siapa berpartner dengan siapa).
   * Menemukan kelompok-kelompok terhubung (Base Teams).
   * Membuat `FopTaskTeam` baru atau mengupdate `FopTaskTeam` yang ada dengan roster terbaru (termasuk menerapkan manual drop-in untuk task single-teknisi).
   * Memperbarui `team_id` pada masing-masing task agar merujuk ke tim yang tepat.
   * Menghapus instansi `FopTaskTeam` yang sudah kosong (tidak memiliki task lagi).
   * Memperbarui nama tim secara dinamis (contoh: `"Tim Andi & Budi"` menjadi `"Tim Andi & Candra"`).
4. Melakukan sinkronisasi ke tabel execution `tasks` dan `task_teams` lewat `TaskService` agar teknisi di lapangan langsung mendapatkan info terbaru di dashboard mereka.

---

## 5. Rancangan Tampilan & Interaksi UI (Excel-Like Experience)

Agar proses penjadwalan terasa fleksibel dan mudah seperti Excel:
* **Inline Assignment:** FOP bisa mengeklik kolom "Teknisi" langsung di tabel utama untuk memunculkan dropdown multi-select teknisi dan toggle PIC tanpa perlu membuka modal besar yang lambat.
* **Inline Drop-In untuk Single-Teknisi:** Jika sebuah task dideteksi hanya memiliki 1 teknisi, akan muncul tombol/dropdown kecil di kolom "Team" bertuliskan `"+ Masukkan ke Team..."` yang berisi daftar tim aktif pada tanggal tersebut.
* **Auto-Update Visual:** Roster tim yang aktif pada hari itu ditampilkan di panel ringkasan samping atau atas, yang otomatis ter-update menggunakan Alpine.js / Livewire secara real-time setiap kali FOP melakukan perubahan teknisi pada tabel.

---

## 6. Masalah Saat Ini (Pain Points)

Sistem Task FOP yang sekarang masih mengikuti model **manual-first**:

| Masalah | Kondisi Saat Ini | Dampak |
|---|---|---|
| **Team harus dibuat dulu** | FOP wajib masuk panel "Kelola Team", buat tim, isi roster, *baru* assign tiket | Proses lambat, banyak klik, seperti mengurus spreadsheet manual |
| **Tidak ada PIC per task** | Tidak ada kolom/field yang menandai siapa penanggung jawab satu task tertentu | Akuntabilitas kabur ketika ada masalah di lapangan |
| **Switch teknisi sulit** | Untuk memindahkan teknisi dari Task A ke Task B, FOP harus edit dua tiket secara terpisah | Tidak fleksibel, apalagi saat situasi urgensi |
| **Single-teknisi terisolir** | Task dengan 1 teknisi tidak punya jembatan ke team yang ada | Sulit memantau dari dashboard |
| **Dashboard team statis** | Team card tidak bisa di-drag/move dari UI | Penjadwalan terasa kaku seperti form admin biasa |
| **Laporan & tombol status** | Tombol "Lapor Nanti" ambigu (bukan `pending` secara eksplisit) | Membingungkan teknisi di lapangan |
| **Halaman /tasks-saya terlalu terbuka** | Teknisi langsung melihat detail task sebelum mulai | Tidak ada kontrol kapan info sensitif (koordinat, maps) muncul |
| **Tidak ada pencatatan alat & SLA per task** | Laporan teknisi belum mencatat inventaris alat yang dipakai + durasi riil vs SLA | Tidak bisa evaluasi kinerja teknisi & efisiensi operasional |
| **Request tanggal pemasangan tidak ada urutan** | Saat FOP minta tanggal pemasangan di survey, tidak ada mekanisme antrian otomatis | Task tidak naik ke atas secara otomatis ketika jadwal tiba |

---

## 7. Switch Task antar Team (Drag & Drop Dashboard)

**Konsep:** Di dashboard FOP, setiap team card bisa menerima drag-drop task dari team lain.

**Aturan drag-drop:**
- Task yang sedang `in_progress` **tidak bisa** di-drag
- Task yang sudah `Selesai`/`Cancel` **tidak bisa** di-drag
- Teknisi yang dipilih untuk task baru tidak boleh sedang `in_progress` di task lain

**Validasi wajib saat drop:**
1. Muncul prompt mini untuk memilih nama teknisi di team tujuan yang akan mengerjakan task tersebut
2. Jika konfirmasi diberikan:
   - `team_id` task diupdate ke team tujuan
   - Teknisi yang dipilih ditambahkan ke pivot task
   - Rebuild team dipicu otomatis
   - Catat Audit Log

---

## 8. Tombol `Pending` (top-level) vs `Lapor Nanti` (dalam dialog laporan) — Dua Mekanisme Berbeda

**Koreksi dari draft awal:** `Pending` dan `Lapor Nanti` sebelumnya dianggap 1 hal (cuma ganti nama tombol). Ini sudah dikoreksi — keduanya adalah 2 mekanisme berbeda dengan efek berbeda. Detail lengkap lihat SOLUSI poin 6 & 7.

**Kondisi existing:** Detail Task punya tombol `Lapor Nanti` + tombol `Lapor Survey/Pemasangan/Maintenance, dll`.

**Perubahan final:**
- Tombol top-level Detail Task jadi: **`Pending`** + `Lapor Survey/Pemasangan/Maintenance, dll` (tombol laporan tetap ada).
- Begitu tombol laporan ditekan → muncul **Dialog Alert** dengan 2 pilihan: **`Lapor Sekarang`** dan **`Lapor Nanti`**.

**Alur:**
```
Detail Task
├─ [Pending]  ← top-level, langsung reschedule ke besok, balik ke antrian FOP
└─ [Laporan Survey / Pemasangan / Maintenance / ...]
        │
        ▼
   Dialog Alert muncul:
   ┌──────────────────┐   ┌──────────────────┐
   │  Lapor Sekarang  │   │   Lapor Nanti    │
   └────────┬─────────┘   └────────┬─────────┘
            │                      │
            ▼                      ▼
     Form laporan            Status task jadi
     langsung muncul         `lapor_nanti`, tetap
     (isi & submit)          di teknisi yang sama,
                             laporan dilanjut nanti
```

**Fungsi masing-masing:**
| Tombol | Lokasi | Status hasil | Efek |
|---|---|---|---|
| **Pending** | Top-level Detail Task | `pending` | Task direschedule ke hari lain, balik ke antrian Task FOP untuk dijadwalkan ulang (dipakai untuk urgensi: request tanggal lain, infra belum siap, dll). Wajib isi alasan di modal sebelum submit. |
| **Lapor Sekarang** | Dalam dialog laporan | lanjut ke form laporan | Teknisi isi laporan saat itu juga. |
| **Lapor Nanti** | Dalam dialog laporan | `lapor_nanti` | Kerja lapangan sudah selesai, laporan ditunda — task tetap di teknisi yang sama, TIDAK balik ke antrian FOP. |

**Detail tombol `Pending`:** warna amber/kuning (konsisten status `Pending` di seluruh sistem), wajib isi alasan di modal sebelum submit, sinkron ke FopTask maupun Task eksekusi teknisi.

---

## 9. Request Tanggal Pemasangan & Antrian Otomatis

**Konteks:** Saat FOP melakukan survey, FOP bisa request tanggal kapan pemasangan dilakukan. Field yang digunakan: `client_request_date` (sudah ada di schema).

**Logika antrian (koreksi field: pakai `client_request_date`, bukan `task_date`):**
- Task dengan `client_request_date` di masa mendatang → tampil di **bawah** daftar (section "Upcoming / Terjadwal")
- Ketika `client_request_date` = hari ini atau sudah lewat → **otomatis naik ke atas** (prioritas tinggi, badge "JADWAL HARI INI")
- `task_date` tetap dipakai buat jadwal aktual pengerjaan — beda field dari `client_request_date` yang murni request tanggal dari pelanggan saat survey. Lihat Task 8 (Sprint Backlog) buat implementasi konkret di `FopTaskController::index()`.

**Sorting otomatis:**
```
Urgent/Overdue → Hari Ini → Upcoming
```

---

## 10. Status FopTask Berbasis Status Teknisi

**Konsep baru:** Status `FopTask` harus mencerminkan kondisi riil teknisi di lapangan — tidak lagi dikontrol penuh secara manual oleh FOP.

**Mapping status teknisi → status FopTask:**

| Kondisi Teknisi | Status FopTask |
|---|---|
| Task di-assign tapi belum dimulai | `Proses` (jadwal aktif) |
| Teknisi klik "Mulai" | `Proses` + indikator ⚡ Sedang Dikerjakan |
| Teknisi klik "Pending" (top-level Detail Task) | `pending` — direschedule, balik ke antrian Task FOP |
| Teknisi pilih "Lapor Nanti" (dalam dialog laporan) | `lapor_nanti` — kerja selesai, laporan gantung, task tetap di teknisi sama |
| Teknisi submit laporan | `Proses` + badge "Perlu Review" |
| FOP approve laporan | `Selesai` |
| FOP reject laporan | `Proses` kembali |
| FOP cancel (dengan `cancel_reason`) | `Cancel` |

**Perubahan:** Status diupdate **otomatis** dari perubahan status `Task` eksekusi yang terkait — sinkronisasi dua arah antara `FopTask` dan `Task`. Status minimal 5 nilai: `proses`, `pending`, `lapor_nanti`, `selesai`, `cancel` — bukan cuma pending/selesai. Detail lihat SOLUSI poin 9.

---

## 11. Halaman `/tasks-saya` — Default Minimal, Detail Bertahap

**Kondisi saat ini:** Teknisi langsung melihat semua info (detail, maps, koordinat) tanpa tahapan.

**Desain baru:**

**Tampilan default (sebelum mulai):**
```
┌─────────────────────────────────┐
│  [Badge PIC jika berlaku]       │
│  Nama Task / Pelanggan          │
│  Jenis: Pemasangan Fiber        │
│  Jadwal: 09/07/2026 08:00       │
│  Status: Terjadwal              │
│                                 │
│         [MULAI TASK]            │
└─────────────────────────────────┘
```

**Setelah klik "Mulai Task"** — baru muncul:
- Detail pelanggan lengkap (alamat, nomor HP)
- Koordinat & tombol buka Google Maps
- Catatan/issue dari FOP
- Riwayat maintenance sebelumnya (jika ada)
- Timer berjalan
- Tombol Upload Foto Bukti
- Tombol `Pending` (top-level, reschedule — Task 7) + tombol `Laporan Survey/Pemasangan/Maintenance/dll` yang begitu ditekan munculkan dialog `Lapor Sekarang`/`Lapor Nanti` (Task 6) — 3 aksi beda, bukan cuma 2 tombol datar seperti draft awal.

**Tujuan:** Mencegah teknisi melihat info sensitif sebelum benar-benar siap berangkat, dan memastikan timer SLA akurat sejak mulai kerja.

---

## 12. Pencatatan Laporan + SLA Tracking

**Data yang dicatat saat teknisi submit laporan:**

| Field | Keterangan |
|---|---|
| `task_type` | Tipe task (Survey / Pemasangan / MTN, dll) |
| `customer_name` | Nama pelanggan |
| `customer_address` | Alamat lengkap |
| `tools_used` | Array alat yang digunakan (kabel, ODP, modem, patchcord, sleeve, dll) |
| `started_at` | Waktu mulai (dari Task status → in_progress) |
| `completed_at` | Waktu selesai |
| `total_duration_minutes` | Durasi pengerjaan (dihitung otomatis) |
| `sla_target_minutes` | Target SLA berdasarkan tipe task |
| `sla_status` | `on_target` / `breach` |
| `sla_overrun_minutes` | Selisih menit jika melebihi SLA |

**Tampilan rekap untuk FOP (di detail task):**
```
┌── Hasil Laporan Teknisi ────────────────────────┐
│ Teknisi: Andi Saputra (PIC)                     │
│ Tipe: Pemasangan Fiber                          │
│ Pelanggan: Budi Hartono                         │
│ Alamat: Jl. Melati No. 12, Ponorogo             │
│                                                 │
│ Alat Digunakan:                                 │
│  • Kabel fiber 50m                              │
│  • ODP 1 buah                                   │
│  • Patchcord SC/APC 2m                          │
│                                                 │
│ Durasi: 3j 45m | SLA Target: 4j | ✅ ON TARGET  │
└─────────────────────────────────────────────────┘
```

**Widget evaluasi SLA di Dashboard FOP:**
- Rata-rata durasi pengerjaan per teknisi vs SLA target
- Filter per periode & per tipe task
- Tren: apakah teknisi semakin cepat atau melambat dari waktu ke waktu

---

## 13. Ringkasan Perubahan per Komponen

### Database

| Perubahan | Tabel | Kolom Baru |
|---|---|---|
| Tambah PIC per Team (bukan per Task) | `fop_task_teams` | `pic_id` (FK → users) |
| Tandai role teknisi di task | `fop_task_user` | `role_in_task` enum (lead/teknisi) |
| Rekap laporan per task | `task_reports` *(tabel baru)* | `tools_used`, `total_duration_minutes`, `sla_target_minutes`, `sla_status`, `sla_overrun_minutes`, siklus (`started_at`, `pending_at`, `resumed_at`, `completed_at`) |
| Alasan cancel | `fop_tasks` | `cancel_reason` (text, nullable, `required_if:status,Cancel`) |
| Status histori per transisi | `fop_task_status_history` *(tabel baru)* | `fop_task_id`, `from_status`, `to_status`, `changed_by`, `changed_at` |

> **Catatan:** Tabel `fop_task_teams` tetap ada, namun tidak lagi dibuat manual oleh FOP. Dibuat & dikelola sepenuhnya oleh sistem via `FopTaskTeamService::rebuildTeamsForDate()`.

### Service / Controller

| Komponen | Perubahan |
|---|---|
| `FopTaskTeamService` | Method `rebuildTeamsForDate($date)` — Connected Components algorithm |
| `FopTaskController` | Hapus endpoint create/edit team manual; tambah endpoint drop-in single task ke team |
| `FopTaskController` | Endpoint baru `POST /fop-tasks/{task}/switch-technician` dengan validasi konflik |
| `FopDashboardController` | Tambah data drag-drop state; hapus panel "Kelola Team" manual |
| `TaskMaintenanceController` | Tambah field `tools_used`, kalkulasi `sla_status` otomatis saat submit |

### UI/UX

| Halaman | Perubahan |
|---|---|
| `/fop` Dashboard | Drag-drop antar team card; hapus tombol "Buat Team"; tambah widget SLA per teknisi |
| `/fop-tasks` | Inline assignment teknisi; dropdown "Masukkan ke Team" untuk solo task; hilangkan panel "Kelola Team" manual |
| `/tasks-saya` | Tampilan minimal default (hanya nama task + tombol Mulai); detail + maps muncul setelah "Mulai Task" |
| Form laporan | Field inventaris alat; rename tombol "Pending" |
| Detail task (FOP review) | Tabel rekap laporan + SLA achievement |

---

## 14. Alur Baru End-to-End

```
FOP Buat Task
    │
    ├─ Pilih teknisi (1 atau lebih)
    └─ Simpan (TIDAK ada tandai PIC di sini — PIC di level Team, bukan Task)
         │
         ▼
    Sistem otomatis:
    • Rebuild team berdasarkan overlap teknisi hari itu (FopTaskTeamService, Task 1)
    • Update nama team dinamis
    • Buat Task eksekusi untuk teknisi
    • Kirim notifikasi ke teknisi
         │
         ▼
    Dashboard FOP:
    • Tampilkan team card (otomatis, tanpa buat manual)
    • FOP tunjuk PIC dari roster team yang sudah terbentuk (Task 4) — belakangan, bukan barengan bikin task
    • FOP bisa drag task antar team (dengan validasi teknisi tujuan, Task 3)
    • FOP bisa switch teknisi per task (dengan konflik check, Task 2)
         │
         ▼
    Teknisi /tasks-saya:
    • Lihat list task (minimal — belum ada detail)
    • Klik Mulai → timer jalan, detail + maps muncul
    • Selesai kerja → isi laporan (alat, catatan, foto)
    • Klik tombol Laporan → dialog "Lapor Sekarang" atau "Lapor Nanti" (Task 6, assignment tetap)
    • ATAU klik tombol "Pending" top-level kapan saja → reschedule, lepas assignment, balik ke FOP Task (Task 7)
         │
         ▼
    FOP Review (untuk laporan yang masuk):
    • Lihat rekap laporan teknisi (alat, durasi, SLA)
    • Approve / Reject / Cancel (dengan `cancel_reason`, Task 12) — bukan "Pending" di sini, itu aksi teknisi bukan aksi review FOP
         │
         ▼
    Sistem catat:
    • Audit Log
    • Status histori tiap transisi (`fop_task_status_history`, Task 9)
    • SLA tracking per teknisi (dual-cycle kalau pernah reschedule, Task 10)
    • Rekap alat yang digunakan
```

---

## 15. Yang Tidak Berubah

- Auto-sync pelanggan → FopTask (survey/PSB dari status customer)
- Kalkulasi prioritas dinamis berbasis SLA
- Guard permission RBAC per aksi
- POP-scope isolation untuk FOP
- Riwayat (`/fop-tasks/history`) untuk audit
- Batas maksimal 3 teknisi per task eksekusi (di level `tasks`)

---

## 16. Risiko & Catatan Implementasi

> **⚠️ Rebuild team otomatis bisa mahal secara query** jika jumlah task per hari banyak. Pastikan ada indeks yang tepat di kolom `task_date` dan proses rebuild dijalankan sebagai **background job (Queue)** bukan sinkron di request, agar UI tidak lambat.

> **⚠️ Data team lama (yang dibuat manual)** perlu migrasi. Saat fitur ini aktif, team yang sebelumnya dibuat manual harus di-konversi ke format baru atau dibersihkan agar tidak ada inkonsistensi.

> **ℹ️ Drag-drop hanya untuk FOP di halaman dashboard.** Teknisi tidak punya akses ke fitur ini. Validasi nama teknisi saat drop adalah gating penting untuk mencegah task salah masuk team.

> **💡 Untuk SLA tracking,** pertimbangkan untuk membuat view/report terpisah di menu FOP (bukan hanya di detail task) agar FOP bisa melihat performa semua teknisi sekaligus, bukan satu per satu.

---

## SOLUSI

Analisa solusi dan dampak per poin kebutuhan (14 poin, mencakup poin 12-14 hasil pembahasan lanjutan; poin 15 sudah menyatu ke solusi poin 14):

### 1. Auto-Team + Solo Drop-in (jembatan teknisi)

**Solusi:** Algoritma connected components tetap dipakai. Solo task (1 teknisi) default `team_id = null`. Solo task **auto-merge ke team yang sudah ada** HANYA jika teknisi tersebut sudah jadi anggota team lain di tanggal sama (kasus Joko di contoh Task C). Kalau teknisi benar-benar belum punya team sama sekali (kasus Samsul), FOP wajib assign manual lewat dropdown "Masukkan ke Team" dengan validasi.

**Dampak:**
- Rebuild graf jalan tiap ada perubahan assignment → butuh index `task_date` + `technician_id` di pivot supaya query tidak lambat.
- UI perlu 2 jalur berbeda: auto (tanpa aksi FOP) dan manual (dropdown + validasi) — nambah percabangan logic di form assignment.

**Edge case: 1 task narik 2 teknisi dari 2 team berbeda (dikonfirmasi bisa terjadi, jarang tapi valid).**

Contoh: Team 1 (Abdul, Karim) dan Team 2 (Yanto, Wito) sudah kebentuk terpisah hari itu. FOP bikin Task F, assign **Karim** (dari Team 1) dan **Wito** (dari Team 2) sekaligus.

**Solusi:** sistem TIDAK auto-merge Team 1 dan Team 2 begitu saja. Sistem munculkan **dialog validasi** ke FOP: *"Karim terdaftar di Tim 1, Wito terdaftar di Tim 2 — Task F ini mau ditaruh di Tim 1, Tim 2, atau bikin Tim baru gabungan keduanya?"* FOP yang putuskan final, sistem gak asumsi sendiri.

**Dampak:**
- Ini pengecualian dari algoritma connected-components murni (yang harusnya auto-union) — butuh flag khusus: kalau overlap terdeteksi lintas 2 team yang SUDAH ada (bukan team baru terbentuk), rebuild berhenti sejenak dan minta konfirmasi FOP dulu sebelum commit merge.
- Kasus ini jarang terjadi tapi waktu/histori tetap harus tercatat penuh (siapa pilih apa, kapan) di audit log, gak boleh silent auto-merge.

### 2. Switch Teknisi antar Team

**Solusi:** Endpoint `switch-technician` menerima 1 payload sekali submit: teknisi yang dipindah, task tujuan, DAN pengganti di task asal — supaya validasi 2 sisi (siapa gantikan Abdul di Task A, apa tugas Abdul di Task E) selesai dalam 1 transaksi DB.

**Dampak:**
- Wajib atomic transaction — kalau pengganti tidak valid, rollback total, jangan sampai Task A kosong teknisi.
- Trigger rebuild team 2 kali (tanggal asal & tujuan, kalau beda hari perlu diperjelas apakah switch lintas hari diperbolehkan).
- Notifikasi ganda (teknisi keluar + teknisi masuk) dan audit log wajib.

### 3. Switch Task antar Team

**Solusi:** Drag-drop / tombol switch di dashboard, validasi wajib pilih teknisi pengerjaan dari roster team tujuan sebelum commit.

**Dampak:**
- Perlu keputusan eksplisit: teknisi lama di task tersebut dilepas otomatis atau tetap menempel plus teknisi baru ditambah? Kalau tidak didefinisikan, roster bisa membengkak tanpa sengaja.
- Riwayat wajib catat: task pindah dari team mana ke team mana, kapan, siapa yang eksekusi switch (audit log, bukan opsional).

### 4. Penanggung Jawab per Team (bukan per Task)

**Solusi:** PIC sekarang ditunjuk di level **Team**, bukan Task. Kolom `pic_id` pindah ke `fop_task_teams`, dipilih FOP dari salah satu anggota roster team lewat dropdown di team card.

**Dampak — perubahan besar dari analisa versi lama:** Bagian ini **kontradiksi dengan Bagian 3** dokumen (di atas), yang menaruh `pic_id` pada `fop_tasks` sebagai penanggung jawab per-task. Perlu revisi skema: drop rencana `pic_id` di `fop_tasks`, pindahkan ke `fop_task_teams`.
- Karena roster team dinamis (rebuild bisa ganti anggota), kalau PIC ter-switch keluar dari team, sistem wajib re-prompt FOP pilih PIC baru — kalau tidak, PIC bisa jadi orang yang sudah tidak ada di team tersebut.
- Akuntabilitas jadi level tim, bukan granular per tiket — kalau butuh audit "siapa kerjakan Task X", tetap harus dari data pivot teknisi task, bukan dari PIC.

### 5. Tampilan Excel-Like

**Solusi:** Inline-edit langsung di tabel (assignment teknisi, status, pilih PIC) pakai Alpine.js/Livewire, tanpa modal besar bolak-balik.

**Dampak:** Kompleksitas frontend nambah signifikan — perlu state management untuk optimistic update + rollback kalau request gagal. Butuh testing UX ekstra karena banyak edit terjadi cepat berurutan (race antara 2 inline-edit berbeda kolom sebelum response pertama selesai).

### 6 & 7. Tombol Detail Task: `Pending` (top-level) vs `Lapor Nanti` (dalam dialog laporan) — DUA MEKANISME BEDA

**Koreksi dari draft SOLUSI sebelumnya:** sebelumnya `Pending` dan `Lapor Nanti` disamakan jadi 1 istilah — ini salah. Keduanya punya fungsi dan efek berbeda total.

**Kondisi sekarang (existing):** Detail Task punya tombol `Lapor Nanti` + tombol `Lapor Survey/Pemasangan/Maintenance, dll`.

**Perubahan:**
- Tombol top-level Detail Task jadi: `Pending` + `Lapor Survey/Pemasangan/Maintenance, dll` (tombol laporan tetap ada, cuma "Lapor Nanti" lama dicabut dari sini).
- Begitu tombol laporan (`Lapor Survey/Pemasangan/dll`) ditekan → muncul **Dialog Alert** baru dengan 2 pilihan: `Lapor Sekarang` dan `Lapor Nanti`.

**Fungsi masing-masing (final):**
| Tombol | Lokasi | Status hasil | Efek |
|---|---|---|---|
| **Pending** | Top-level Detail Task | `pending` | Task ditunda ke **besok**, dikembalikan ke halaman Task FOP untuk **dijadwalkan ulang** (reschedule) — dipakai untuk urgensi: pelanggan minta pasang lain hari, infra belum siap, dll. |
| **Lapor Sekarang** | Dalam dialog laporan | task lanjut ke form laporan | Teknisi isi laporan saat itu juga. |
| **Lapor Nanti** | Dalam dialog laporan | `lapor nanti` | Pekerjaan lapangan **sudah selesai**, cuma laporannya ditunda karena kondisi tertentu (misal sinyal jelek, buru-buru ke lokasi berikut) — task **tetap di tangan teknisi yang sama**, TIDAK balik ke pool FOP, laporan tinggal dilanjutkan kapan saja. |

**Dampak:**
- Butuh 2 nilai enum status berbeda di DB: `pending` dan `lapor_nanti` — bukan 1 istilah gabungan seperti draft awal saya.
- `pending` = reschedule penuh (task lepas dari teknisi, balik ke antrian FOP, jadwal ulang hari lain). `lapor_nanti` = pekerjaan closed di lapangan, cuma laporan yang gantung (task masih milik teknisi yang sama, tidak reschedule).
- Riwayat/status history (lihat poin 9) harus bisa bedakan dua histori ini — kalau digabung jadi 1 status, FOP gak bisa bedakan "task ini nunggu dijadwal ulang" vs "task ini tinggal nunggu laporan masuk".
- Widget/badge di dashboard FOP juga butuh 2 warna/label beda supaya gak ketuker.

### 8. Antrian Tanggal Request Pemasangan

**Solusi:** Sorting otomatis berdasar `client_request_date`/`task_date` — task masa depan di bawah (Upcoming), begitu jadwalnya tiba (hari ini/lewat) naik otomatis ke atas (Urgent/Overdue).

**Dampak:** Perlu cron job harian (tengah malam) untuk re-evaluasi urutan tanpa nunggu ada aksi user — kalau tidak ada cron, urutan baru ke-update saat ada request/edit berikutnya (telat sehari).

### 9. Status Realtime (Hapus Dropdown Manual)

**Solusi:** Status Task FOP full derive dari status Task eksekusi teknisi (sync 2 arah via event listener). Task yang di-`Pending` (bukan `Lapor Nanti`) otomatis balik ke halaman FOP Task untuk dijadwalkan ulang ke teknisi lain (hari lain), histori tetap tercatat penuh.

**Dampak:**
- FOP kehilangan override manual bebas (dropdown dihapus) — perlu jalur eksplisit tetap ada (approve/reject/cancel laporan) sebagai pengganti, supaya FOP tidak buntu kalau ada kasus di luar mapping otomatis.
- Status yang dipantau bukan cuma 2 (Pending/Selesai) tapi minimal 4: `proses`, `pending` (reschedule), `lapor_nanti` (nunggu laporan, task masih di teknisi sama), `selesai`. Tabel log terpisah (`fop_task_status_history`) tetap wajib supaya Riwayat (poin 10) bisa tampilkan histori lengkap per transisi, bukan cuma status terakhir.

### 10. Riwayat Lengkap + SLA Deadline

**Solusi:** Halaman Riwayat gabung detail task + semua jenis laporan (survey/pemasangan/maintenance) + SLA deadline (total waktu pengerjaan).

**Dikonfirmasi:** SLA dihitung **wall-clock penuh, termasuk waktu nunggu** — tidak di-exclude. Waktu tetap harus tercatat utuh dari mulai sampai selesai, apa pun statusnya di tengah jalan (proses/pending/lapor nanti). Catatan penting: begitu task kena `Pending`, itu bukan sekadar jeda — task **direschedule ke hari lain** dan balik ke antrian FOP. Jadi perhitungan SLA untuk task yang pernah `Pending` perlu pisahkan dua siklus: durasi sebelum pending (siklus 1) + durasi setelah dijadwal ulang sampai selesai (siklus 2), keduanya dicatat dan dijumlah di Riwayat — bukan dianggap 1 durasi berkelanjutan tanpa jeda tercatat.

**Dampak:** Tabel `task_reports`/histori butuh kolom untuk catat tiap siklus (`started_at`, `paused_at`/`pending_at`, `resumed_at`, `completed_at`) supaya SLA total bisa dihitung akurat dari akumulasi seluruh siklus, bukan cuma selisih timestamp pertama-terakhir (yang bakal salah kalau ada reschedule lintas hari di antaranya).

### 11. `/tasks-saya` Tombol Mulai per Jenis Task

**Solusi:** Card default tampilkan tombol sesuai tipe task aktif (`Mulai Survey` / `Mulai Pemasangan` / `Mulai Maintenance`), bukan 1 tombol generic "Mulai". Setelah diklik, tombol berubah jadi 2 (`Detail`, `Isi Laporan`), muncul card koordinat + tombol Maps di bawahnya.

**Dampak:** Perlu mapping `task_type` → label tombol spesifik (nambah sedikit percabangan view, bukan besar). Timer/SLA harus tetap mulai saat tombol "Mulai X" diklik, bukan saat buka Detail — supaya SLA akurat sejak teknisi benar-benar mulai kerja (konsisten dengan tujuan poin 11 lama soal info sensitif baru muncul setelah mulai).

### 12. Cancel Survey/Pemasangan dengan Alasan

**Kondisi kode saat ini:** status `Cancel` dan kolom `cancelled_at` **sudah ada** (`FopTaskStatus::CANCEL`, di-set otomatis di `FopTaskController::store`/`update`). Yang **belum ada**: kolom alasan cancel yang dedicated — `pending_reason` cuma wajib diisi kalau status `Pending`, bukan `Cancel`.

**Solusi:** tambah kolom `cancel_reason` (text, nullable) di `fop_tasks`, wajib diisi (`required_if:status,Cancel`) sama seperti pola `pending_reason`. Role yang boleh cancel: FOP + role lain yang di-gate lewat permission RBAC (mis. `fop-task.cancel`), bukan cuma FOP tunggal — supaya Koordinator/Supervisor juga bisa kalau perlu.

**Dampak:**
- Task yang sudah `in_progress` (teknisi lagi kerja) kalau di-cancel harus juga cancel/sync ke `Task` eksekusi + notifikasi ke teknisi yang lagi jalan, bukan cuma ubah status di FOP side.
- Riwayat (poin 10) perlu tampilkan `cancel_reason` di tabel histori, sejajar dengan `pending_reason`.
- Perlu keputusan: task yang sudah masuk Team (auto-team) dan di-cancel — apakah rebuild team dipicu ulang (kalau task itu satu-satunya jembatan penghubung 2 teknisi, cancel bisa mecah team jadi 2)? Jawaban: ya, cancel juga termasuk trigger `rebuildTeamsForDate()`, sama seperti perubahan assignment lain.

### 13. Antrian Request Tanggal Pemasangan (Sorting)

**Kondisi kode saat ini:** kolom `client_request_date` **sudah ada** di `fop_tasks` (dipakai buat requirement `Pending`, ditampilkan di form & index). Tapi **belum dipakai buat sorting** — query index (`FopTaskController.php`) cuma order by priority & created_at, `client_request_date` gak masuk `orderBy` sama sekali.

**Solusi:** tambah logic sorting di query index:
```php
->orderByRaw("CASE WHEN client_request_date IS NOT NULL AND client_request_date > CURDATE() THEN 1 ELSE 0 END")
->orderBy('client_request_date')
```
Task dengan `client_request_date` di masa depan diselipkan ke bawah (section "Upcoming"), begitu tanggalnya sama dengan hari ini atau lewat, otomatis ikut aturan sorting normal (naik ke prioritas atas / Urgent-Overdue).

**Dampak:**
- Perlu section/badge visual pembeda ("Terjadwal — {tanggal}" vs "Hari Ini"/"Overdue") di `index.blade.php`, bukan cuma perubahan query.
- Kalau gak ada cron harian, transisi dari "Upcoming" ke "Hari Ini" cuma kejadian pas ada request baru/refresh — untuk kasus ini sebenarnya cukup aman karena sorting dihitung ulang tiap kali halaman di-load (bukan berdasarkan job terjadwal), jadi begitu tanggal sistem sudah berubah, urutan otomatis benar tanpa perlu cron.
- **SLA untuk kasus ini** ditunda dulu sesuai permintaan anda — dibahas terpisah, karena beda dengan SLA siklus pending/lapor-nanti di poin 10 (di sini task belum pernah dikerjakan sama sekali, cuma nunggu tanggal jadwal).

### 14. Customer ID Wajib per Tipe Task + Auto-fill POP/Area

**Kondisi kode saat ini:**
- `Customer` model sudah punya `pop_id` dan `village_id`.
- Endpoint pencarian customer (`GET /api/tasks/search-customers`) **sudah mengembalikan** `pop_id` dan `village_id` per customer.
- **Bug/gap ditemukan:** fungsi Alpine `selectCustomer()` di `fop_tasks/index.blade.php` cuma nge-set `tugas` dan `customer_id` — **tidak** copy `pop_id`/`village_id` ke form, padahal datanya sudah tersedia dari API. Jadi auto-fill POP/Area **belum jalan** meski infrastrukturnya sudah 90% ada.
- `TaskType` enum sudah punya semua tipe yang disebut: `SURVEY`, `PSB` (Pemasangan), `DEAC`, `RELOKASI`, `CREQ` (C-REQ), `OREQ` (O-REQ), `INFR` (INFR REQ). `SURVEY` dan `PEMASANGAN` saat ini sudah `autoOnlyValues()` — **ini dikonfirmasi memang harus tetap begitu**, bukan bug yang perlu dibuka.

**Dikonfirmasi:** Survey dan Pemasangan **wajib murni ikut alur Registrasi → Survey → Pemasangan** (auto-sync dari status pelanggan). **Tidak boleh** ditambah atau diedit manual sebagai Task FOP.

**Solusi:**
1. **Modal Tambah Task FOP:** hilangkan opsi `Survey` dan `Pemasangan` dari dropdown/pilihan `category` — form create cuma tampilkan `DEAC`, `RELOKASI`, `CREQ`, `OREQ`, `INFR`. (Ini konsisten dengan `autoOnlyValues()` yang sudah ada, cuma sekarang ditegaskan juga hilang dari UI, bukan cuma dari validasi backend.)
2. **Modal Edit Task FOP:** kalau `category` existing task = `SURVEY` atau `PSB`, field `category` (dan field terkait: `customer_id`, `pop_id`, `village_id`) jadi **disabled/readonly** — FOP cuma bisa lihat, gak bisa ubah. Field lain yang boleh tetap diedit (assignment teknisi, jadwal, dll) tetap aktif seperti biasa.
3. Fix `selectCustomer()` di Alpine: begitu customer dipilih (buat category `DEAC`/`RELOKASI`/`CREQ`), langsung set `form.pop_id` dan `form.village_id` dari response API, field itu ikut **disable/readonly** selama customer_id terisi.
4. Validasi backend (`FopTaskController::store`/`update`):
   - `category` in `[SURVEY, PSB]` → tolak di `store()` (403/422, gak boleh dibuat manual sama sekali). Di `update()`, tolak perubahan `category`, `customer_id`, `pop_id`, `village_id` kalau existing record tipe ini.
   - `category` in `[DEAC, RELOKASI, CREQ]` → `customer_id` wajib, `pop_id`/`village_id` di-override paksa dari data customer di server side.
   - `category` in `[OREQ, INFR]` → `customer_id` nullable, `pop_id`/`village_id` manual (dropdown biasa).

**Dampak:**
- Perlu guard di level Policy/Controller, bukan cuma sembunyikan dropdown di UI — kalau cuma disembunyikan di frontend, API `store`/`update` tetap bisa diakali lewat request langsung. Validasi backend jadi wajib, bukan opsional.
- Task Survey/Pemasangan yang sudah kepalang dibuat manual (data lama, kalau ada) perlu diaudit — apakah dibiarkan sebagai legacy exception atau dipaksa convert/hapus.
- Field POP/Area di form edit perlu logic disabled kondisional berdasarkan `category`, bukan cuma berdasarkan ada-tidaknya `customer_id` — nambah 1 state check lagi di Alpine.
- Karena auto-team & rebuild pakai teknisi (bukan POP) buat grouping, perubahan ini tidak berdampak ke logic Auto-Team.

---

## SPRINT BACKLOG

### Task 1 — Auto-Team Formation Engine (Connected Components + Solo Drop-in)

**Status:** `Done`

**Post-completion bugfix (SUDAH DI-FIX SEMUA):** detail lengkap di `docs/fop-task/analisa-sync-execution-task.md`.
1. **Bug team kosong:** task multi-teknisi yang di-shrink jadi solo (1 teknisi dicabut) salah nge-nullify `team_id` sisa teknisinya dan bikin team-nya ikut kehapus (dianggap kosong), padahal teknisi itu masih aktif kerja di task tersebut. Fix di `FopTaskTeamService::rebuildTeamsForDate()` (blok solo-task handling, cek anchor `$existingTeamOf` sebelum nullify).
2. **Desync ke execution Task:** nama team di `Task.title` (dipakai halaman teknisi `/tasks-saya` & detail task) dulu di-bake ad-hoc pas create/edit dan gak pernah ke-refresh pas auto-team rebuild ganti roster/nama team. Fix: `rebuildTeamsForDate()` sekarang sinkronin `Task.title` ke nama `FopTaskTeam` asli tiap kali jalan (method privat `syncExecutionTaskTitle()`); `FopTaskController::store()`/`update()` disederhanakan (gak nebak nama team lagi, biar rebuild yang isi).

Regression test: `test_shrinking_multi_technician_task_to_solo_keeps_its_team_alive` + 4 test sync title (`test_execution_task_title_*`) di `tests/Feature/Services/FopTaskTeamServiceTest.php`. Hasil akhir: 13/13 test service hijau, 26/26 gabungan sama `FopTasksTest`, gak ada regresi.

**Tujuan:** Ganti mekanisme pembuatan Team FOP dari manual (FOP bikin team dulu baru assign task) jadi otomatis — Team terbentuk/berubah sendiri berdasar graf overlap teknisi per `work_date`, sesuai kebutuhan poin 1. Ini fondasi buat Task 2-4 berikutnya (switch teknisi/task, PIC per team), jadi dikerjakan duluan.

**Kondisi kode saat ini (baseline):**
- Tabel `fop_task_teams` sudah ada (`name`, `work_date`, `created_by`), kolom `pop_id` sudah di-drop lewat migrasi terpisah.
- Pivot `fop_task_team_user` sudah ada (roster teknisi per team).
- `fop_tasks.team_id` sudah ada (FK ke `fop_task_teams`).
- Belum ada service/class yang hitung graf overlap — Team masih 100% dibuat manual oleh FOP.
- Belum ada kolom `pic_id` di `fop_task_teams` (masuk cakupan Task 4, bukan Task 1).

**Kondisi kode nyata (koreksi dari draft awal — team manual BUKAN sekadar "kalau ada", sudah full-featured):**
`FopTaskController` sudah punya method manual Team CRUD lengkap: `teamStore()` (baris 388), `teamUpdate()` (420), `teamDestroy()` (469), dengan conflict detection (`FopTaskTeam::findMemberConflicts()`). Route-nya: `POST fop-tasks/teams` (`fop-tasks.teams.store`, baris 342), `PUT fop-tasks/teams/{team}` (`fop-tasks.teams.update`, 346), `DELETE fop-tasks/teams/{team}` (`fop-tasks.teams.destroy`, 350) — digate permission yang SAMA dengan task biasa (`fop_tasks.create/update/delete`), belum ada permission khusus team. Di UI, `resources/views/fop_tasks/index.blade.php` baris 489 ada panel/modal penuh **"Kelola Team Harian"** dengan Alpine state `teamForm` (nama, `work_date`, pencarian & dropdown anggota). `store()`/`update()` FopTask sendiri juga sudah terima `team_id` langsung sebagai field nullable (baris 161/188 store, 253/282 update) — artinya FOP saat ini pilih team manual dari dropdown pas create/edit task juga, bukan cuma dari panel terpisah.

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `app/Services/FopTaskTeamService.php` | **Baru** — core logic: `rebuildTeamsForDate(Carbon $date)`, graf builder (union-find/BFS di atas pivot teknisi per task), auto-merge solo task (Skenario C1), manual drop-in (Skenario C2), edge-case dialog 2-team (Skenario C3). |
| `database/migrations/2026_07_xx_add_manual_override_to_fop_tasks_table.php` | **Baru** — tambah kolom `manual_override_at` (timestamp, nullable) di `fop_tasks` buat pin hasil drop-in manual dari ke-overwrite rebuild otomatis. |
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — `store()`/`update()`: hapus field `team_id` dari validasi/form (gak lagi dipilih manual), panggil `FopTaskTeamService::rebuildTeamsForDate()` di akhir kedua method setelah assignment teknisi disimpan. |
| `app/Http/Controllers/FopTaskController.php` | **Hapus** method `teamStore()` (388), `teamUpdate()` (420), `teamDestroy()` (469), `formatConflictMessage()` (456) — seluruh alur manual Team CRUD dicabut sesuai kebutuhan poin 1 & 5. |
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — tambah endpoint baru `POST /fop-tasks/{task}/assign-to-team` buat drop-in manual (Skenario C2) dengan validasi, menggantikan fungsi `teamStore()` yang dihapus. |
| `routes/web.php` | **Hapus** 3 route: `fop-tasks.teams.store` (342), `fop-tasks.teams.update` (346), `fop-tasks.teams.destroy` (350). **Tambah** route baru `POST /fop-tasks/{task}/assign-to-team`. |
| `resources/views/fop_tasks/index.blade.php` | **Hapus** panel "Kelola Team Harian" (baris 489 dst.) + tombol pembukanya (baris 22) + Alpine state `teamForm`. **Hapus** dropdown pilih `team_id` manual dari modal create/edit task (baris 264+). **Tambah** UI dropdown kecil "+ Masukkan ke Team..." di kolom Team untuk solo task (Skenario C2); modal validasi 2-team (Skenario C3). |
| `tests/Feature/Services/FopTaskTeamServiceTest.php` | **Baru** — unit/feature test untuk semua skenario (A, B, C1, C2, C3). |
| `database/migrations/xxxx_add_index_to_fop_tasks_task_date.php` | **Baru** (kalau index belum ada) — index komposit `(task_date, ...)` di kolom relevan pivot teknisi biar query graf gak lambat. |

**Checklist:**
- [x] Buat `FopTaskTeamService::rebuildTeamsForDate($date)` dengan algoritma connected components di atas relasi teknisi-task per `task_date`.
- [x] Implementasi Skenario A: task dengan >1 teknisi otomatis jadi dasar team baru.
- [x] Implementasi Skenario B: overlap teknisi lintas task (jembatan) otomatis union jadi 1 team, nama team ter-update dinamis.
- [x] Implementasi Skenario C1: solo task otomatis merge ke team existing kalau teknisinya sudah jadi anggota team lain di tanggal sama.
- [x] Implementasi Skenario C2: solo task tanpa overlap sama sekali tetap `team_id = null`, sediakan endpoint + UI drop-in manual dengan validasi.
- [x] Implementasi Skenario C3: solo/multi-task yang narik teknisi dari 2 team berbeda sekaligus → jangan auto-union, munculkan validasi FOP pilih Team 1/Team 2/Team baru.
- [x] Kolom `manual_override_at` dipakai supaya hasil drop-in manual (C2/C3) gak ketimpa rebuild otomatis berikutnya, sampai teknisi task tersebut diganti lagi lewat assignment biasa.
- [x] Hapus `teamStore()`/`teamUpdate()`/`teamDestroy()` + 3 route terkait + panel "Kelola Team Harian" + dropdown `team_id` manual di modal task — bukan cuma disembunyikan, benar-benar dicabut dari controller & routes.
- [x] Migrasi data existing: task/team yang `team_id`-nya udah keburu dibuat manual sebelum fitur ini aktif — jalankan `rebuildTeamsForDate()` sekali secara retroaktif per `task_date` yang punya task aktif, biar konsisten sama roster hasil algoritma (bukan dibiarkan nyangkut di struktur lama).
- [x] Index database di kolom yang dipakai query graf (`task_date` + pivot teknisi) supaya rebuild gak berat.
- [x] Audit log: catat tiap kali rebuild membentuk/membubarkan/mengubah roster team (siapa trigger, task apa, hasil sebelum-sesudah).
- [x] Test coverage untuk seluruh skenario A/B/C1/C2/C3 termasuk kasus edge (task cancel jadi satu-satunya jembatan → team pecah, lihat SOLUSI poin 12).

**Acceptance Criteria:**
1. [x] FOP bikin task baru dengan >1 teknisi → Team baru otomatis terbentuk tanpa FOP buka panel "Kelola Team Harian" sama sekali (panel itu sendiri sudah tidak ada lagi di UI).
2. [x] FOP tambah task baru dengan 1 teknisi yang sudah ada di Team X pada tanggal sama → task tersebut otomatis masuk Team X, tanpa aksi tambahan dari FOP.
3. [x] FOP tambah task baru dengan 1 teknisi yang belum overlap ke task manapun hari itu → task berdiri solo (`team_id = null`), dan FOP bisa assign manual ke Team yang ada lewat UI drop-in dengan validasi (gak bisa asal pilih tanpa konfirmasi).
4. [x] FOP assign 1 task ke 2 teknisi yang masing-masing sudah di Team berbeda → sistem tidak diam-diam menggabungkan 2 team, melainkan menampilkan pilihan eksplisit ke FOP.
5. [x] Route `fop-tasks.teams.store/update/destroy` sudah tidak ada lagi (404 kalau diakses), dropdown `team_id` manual sudah hilang dari modal create/edit task.
6. [x] Setiap perubahan assignment teknisi (tambah/switch task) memicu rebuild otomatis dan hasilnya konsisten (tidak ada task nyangkut di team yang salah, tidak ada team kosong tersisa di database).
7. [x] Semua transisi rebuild tercatat di audit log (siapa, kapan, task apa, roster sebelum/sesudah).
8. [x] Test suite `FopTaskTeamServiceTest` hijau untuk seluruh skenario A/B/C1/C2/C3 (7/7 pass).


**Terdapat Bug dan Perbaikan**
terdapat pada file docs\fop-task\analisa-sync-execution-task.md.


---

### Task 2 — Switch Teknisi antar Team (Atomic Endpoint)

**Status:** `Done`

**Tujuan:** Endpoint 1x-submit buat mindahin teknisi dari Task A (Team 1) ke Task B (Team 2) sekaligus wajib isi pengganti di Task A — sesuai kebutuhan poin 2.

**Kondisi kode nyata:** `FopTaskController::update()` (baris 242-365) **sudah** bisa ganti teknisi 1 task — validasi array `technicians` (261-262), `$fopTask->technicians()->sync($technicians)` (311-313), lalu sync ke `Task` eksekusi lewat `TaskService::update()` (331). Pivot-nya `fop_task_user` (relasi `FopTask::technicians()`, `FopTask.php:85-87`). **TAPI ini cuma ubah 1 task per submit** — gak ada endpoint yang ubah 2 task (asal+tujuan) sekaligus dalam 1 transaksi, jadi endpoint atomic Task 2 tetap perlu dibangun baru, reuse pola `sync()` yang sudah ada, bukan bikin mekanisme pivot baru.

**Konflik jadwal — gak ada conflict-check yang relevan sekarang:** `FopTaskTeam::findMemberConflicts()` (`FopTaskTeam.php:64-88`) cuma cek konflik roster **Team** per `work_date`, BUKAN cek availability teknisi per-task (in_progress di task lain). Conflict-check yang beneran relevan justru ada di sistem **eksekusi** (`Task`, bukan `FopTask`): `TaskService.php:135-146` cek task lain yang `in_progress` dengan overlap anggota team (bagian dari aturan "maks 3 teknisi per task eksekusi"). Task 2 harus **reuse/panggil logic di `TaskService`** ini buat validasi "pengganti gak lagi in_progress di task lain", bukan bikin conflict-check terpisah yang bisa gak sinkron dengan aturan eksekusi yang udah ada.

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — tambah method `switchTechnician(Request $request)`, DB transaction, panggil `sync()` pivot `fop_task_user` di 2 task sekaligus (pola sama dgn `update()` baris 311-313), validasi konflik reuse logic `TaskService.php:135-146`. |
| `routes/web.php` | **Rubah** — tambah `POST /fop-tasks/switch-technician` (dekat grup `fop-tasks.*` baris 337-350). |
| `resources/views/fop_tasks/index.blade.php` | **Rubah** — modal 2-dropdown (pengganti di Task asal + tugas baru di Task tujuan). |
| `tests/Feature/FopTaskSwitchTechnicianTest.php` | **Baru** — test transaksi sukses & rollback. |

**Checklist:**
- [x] Payload terima: `technician_id`, `from_task_id`, `to_task_id`, `replacement_technician_id`.
- [x] Wajib `DB::transaction()` — kalau pengganti invalid (reuse cek `in_progress` dari `TaskService.php` — logic sama, cek `Task::where('status', 'in_progress')->whereHas('teamMembers', ...)`), rollback total.
- [x] Sync pivot `fop_task_user` di 2 task (asal & tujuan) dalam 1 transaksi, lalu sync ke `Task` eksekusi lewat `TaskService::update()` sama seperti `update()` existing lakukan.
- [x] Panggil `FopTaskTeamService::rebuildTeamsForDate()` untuk tanggal asal & tujuan setelah commit.
- [x] Notifikasi ke 2 teknisi (yang keluar & masuk).
- [x] Audit log — **deviasi dari checklist asli**: bukan pakai trait `RecordsAuditLogs` (itu butuh nambah trait ke `FopTask` model, di luar scope file Task 2 ini), tapi reuse pola `AuditLog::log()` manual yang UDAH dipakai di controller yang sama (`store()`/`update()`/`assignToTeam()`) — hasil akhirnya sama (before/after + siapa + kapan tercatat), cuma beda mekanisme pemicu.
- [x] Validasi: switch cuma boleh intra-hari (`from_task.task_date == to_task.task_date`), tolak kalau beda hari.

**Catatan implementasi penting (bug yang KETEMU & langsung difix pas development):** draf awal method ini niru pola `update()` yang nge-null-in `team_id` SEBELUM manggil `rebuildTeamsForDate()`. Ternyata itu balik ngerusak fix Task 1 (`analisa-sync-execution-task.md` bagian 1) — kalau salah satu Task nyusut jadi solo abis di-switch, anchor `$existingTeamOf` di service jadi gak kebaca (soalnya team_id-nya udah keburu di-null-in duluan sama controller), jadi teamnya malah ilang lagi. Fix: `switchTechnician()` SENGAJA gak nge-null-in `team_id` sebelum rebuild — cuma `manual_override_at` yang dilepas. Dikonfirmasi lewat test `test_switch_triggers_rebuild_and_updates_team_rosters`.

**Acceptance Criteria:**
1. [x] Switch teknisi lintas team berhasil dalam 1 submit, tanpa FOP edit 2 form terpisah. (`test_switch_technician_succeeds_in_one_submit`)
2. [x] Kalau pengganti di Task asal tidak dipilih/invalid, seluruh perubahan rollback (Task A tidak pernah kosong teknisi). (`test_switch_rejects_when_replacement_missing_from_request`, `test_switch_rejects_when_replacement_is_same_as_departing_technician`, `test_switch_rejects_when_replacement_is_in_progress_elsewhere`)
3. [x] Rebuild Team ke-trigger otomatis di kedua tanggal terdampak. (`test_switch_triggers_rebuild_and_updates_team_rosters`)
4. [x] Switch lintas hari ditolak dengan pesan jelas. (`test_switch_rejects_across_different_days`)
5. [x] Audit log tercatat lengkap (teknisi lama, teknisi baru, waktu, siapa eksekusi). (`test_switch_records_audit_log_entries`)

Test suite: `tests/Feature/FopTaskSwitchTechnicianTest.php` 7/7 hijau, gabungan seluruh test FOP (`FopTasksTest` + `FopTaskTeamServiceTest` + `FopTaskSwitchTechnicianTest`) 38/38 hijau, gak ada regresi.

---

### Task 3 — Switch Task antar Team (Drag & Drop Dashboard)

**Status:** `To Do` (depends on Task 1, Task 2)

**Tujuan:** FOP bisa drag-drop Task dari 1 Team card ke Team card lain di `/fop` dashboard, dengan validasi wajib pilih teknisi pengerjaan dari roster tujuan — sesuai kebutuhan poin 3.

**Kondisi kode nyata:** `FopDashboardController` saat ini **100% read-only** — cuma 1 method publik `index()` (baris 27) + helper privat `getTeknisiList()` (227) dan `initials()` (281), semuanya nampilin data (stats, antrian survey, team card). Tidak ada endpoint mutasi/aksi sama sekali di controller ini. Drag-drop dan `switchTeam()` jadi fitur benar-benar baru, bukan extend logic yang sudah ada.

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `app/Http/Controllers/FopDashboardController.php` | **Rubah** — tambah method mutasi baru `switchTeam(Request $request)` (validasi drag-drop) — ini method pertama yang mengubah data di controller ini. |
| `resources/views/fop/dashboard.blade.php` | **Rubah** — drag-drop pakai Alpine/Sortable, modal validasi teknisi tujuan saat drop. |
| `routes/web.php` | **Rubah** — tambah `POST /fop-tasks/{task}/switch-team` (dekat grup `fop-tasks.*` baris 337-350). |
| `tests/Feature/FopTaskSwitchTeamTest.php` | **Baru** — test drag-drop constraint (task `in_progress`/`Selesai`/`Cancel` gak bisa di-drag). |

**Checklist:**
- [x] Guard: task `in_progress`, `Selesai`, `Cancel` tidak bisa di-drag (disabled di UI + validasi backend).
- [x] Saat drop: modal minta pilih teknisi dari roster Team tujuan sebelum commit.
- [x] Keputusan eksplisit diterapkan: teknisi lama di task dilepas otomatis (default), teknisi baru ditambahkan — bukan numpuk tanpa sengaja.
- [x] Trigger `rebuildTeamsForDate()` setelah commit.
- [x] Audit log: task pindah dari Team mana ke Team mana, kapan, siapa yang eksekusi.

**Acceptance Criteria:**
1. Drag task dari Team card A ke Team card B memicu modal validasi (bukan langsung pindah tanpa konfirmasi).
2. Task `in_progress`/`Selesai`/`Cancel` tidak bisa di-drag sama sekali (UI + backend reject).
3. Setelah drop, roster Team A dan B ter-update benar (teknisi lama lepas, teknisi baru masuk sesuai pilihan FOP).
4. Riwayat mencatat perpindahan task antar-team lengkap dengan aktor & waktu.

---

**** 
### Task 4 — Penanggung Jawab per Team (PIC) 'Pending = karena masih perlu diskusi'
 
**Status:** `To Do` (depends on Task 1) 

**Tujuan:** Pindahkan PIC dari level Task (rencana lama, sudah dikoreksi) ke level Team — FOP menunjuk 1 anggota roster jadi PIC — sesuai kebutuhan poin 4 & SOLUSI poin 4.

**Catatan permission:** `config/rbac.php` pakai konvensi underscore `fop_tasks.<action>` (contoh existing: `fop_tasks.view/create/update/delete/update_sensitive`), bukan hyphen. Endpoint baru di task ini pakai permission existing `fop_tasks.update` (belum ada permission khusus per-team di rbac saat ini, gak perlu bikin baru kecuali nanti diputuskan granularity lebih detail).

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `database/migrations/2026_07_xx_add_pic_id_to_fop_task_teams_table.php` | **Baru** — tambah `pic_id` (FK → `users`, nullable) di `fop_task_teams`. |
| `app/Models/FopTaskTeam.php` | **Rubah** — tambah relasi `pic()`, validasi PIC harus anggota roster. |
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — tambah method baru `setPic(Request $request, FopTaskTeam $team)`, guard permission `fop_tasks.update`. |
| `routes/web.php` | **Rubah** — tambah route baru `POST /fop-tasks/teams/{team}/set-pic` (pola sama dengan route team existing `fop-tasks.teams.*` baris 342-350, meski `teamStore`/`teamUpdate`/`teamDestroy`-nya sendiri dihapus di Task 1). |
| `app/Services/FopTaskTeamService.php` | **Rubah** — di `rebuildTeamsForDate()`, kalau PIC ter-switch keluar roster, set `pic_id = null` dan flag `needs_pic_reassignment`. |
| `resources/views/fop_tasks/index.blade.php`, `resources/views/fop/dashboard.blade.php` | **Rubah** — dropdown pilih PIC di team card, badge "Perlu Pilih PIC" kalau `pic_id` null. |
| `tests/Feature/FopTaskTeamPicTest.php` | **Baru** |

**Checklist:**
- [ ] Migrasi kolom `pic_id` di `fop_task_teams` (bukan di `fop_tasks`).
- [ ] Sinkron ke `task_teams` (tabel eksekusi): `role_in_task = 'lead'` untuk PIC, `'teknisi'` untuk lainnya, di semua task dalam team tsb.
- [ ] Guard: PIC harus anggota roster aktif team — reject kalau assign PIC dari luar roster.
- [ ] Rebuild handler: PIC yang ke-switch keluar team → reset `pic_id`, tampilkan badge butuh pilih ulang.
- [ ] Audit log tiap perubahan PIC.

**Acceptance Criteria:**
1. FOP bisa tunjuk PIC dari dropdown berisi cuma anggota roster team tsb.
2. Kalau PIC ter-switch (lewat Task 2/3) keluar dari team, sistem tandai team itu "Perlu Pilih PIC" — tidak diam-diam nyisain PIC lama yang sudah bukan anggota.
3. `task_teams.role_in_task` konsisten dengan PIC yang aktif di `fop_task_teams`.

---

### Task 5 — Tampilan Excel-Like (Inline Assignment) 'Pending = karena masih perlu diskusi'

**Status:** `To Do` (depends on Task 1)

**Tujuan:** Ubah form assignment teknisi/PIC dari modal besar jadi inline-edit langsung di tabel `/fop-tasks` — sesuai kebutuhan poin 5.

**Kondisi kode nyata (lebih sempit dari draft awal):** Inline-edit **BUKAN konsep baru total** di halaman ini — Status dan Priority **sudah** inline-editable sekarang: `index.blade.php` baris 172 (`<select @change="updateStatus(...)">`) dan baris 197 (`<select @change="updatePriority(...)">`), keduanya `<select>` di dalam tabel yang langsung fire AJAX update, terpisah dari modal create/edit besar (baris 264+). Yang MASIH modal-only cuma kolom **Teknisi/PIC**. Task 5 tinggal terapkan pola yang sama (`updateStatus`/`updatePriority` style) ke kolom Teknisi, bukan bangun sistem inline-edit dari nol.

**Catatan penting:** Task 9 (status realtime) akan **menghapus** inline-select status di baris 172 (diganti badge read-only). Urutan pengerjaan: kalau Task 5 dan Task 9 jalan bersamaan, pastikan Task 5 tidak menduplikasi pekerjaan di kolom status yang toh bakal dihapus Task 9 — fokus Task 5 murni ke kolom Teknisi/PIC.

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `resources/views/fop_tasks/index.blade.php` | **Rubah** — kolom Teknisi jadi inline-editable, reuse pola Alpine yang sama dengan `updateStatus()`/`updatePriority()` (baris 172/197): dropdown multi-select teknisi + toggle PIC langsung di tabel, fire request tanpa buka modal besar (264+). |
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — tambah method `updateTechnicians(Request $request, FopTask $task)` (pola sama dengan handler `updateStatus`/`updatePriority` yang sudah ada di controller ini), panggil `rebuildTeamsForDate()` di akhir. |
| `routes/web.php` | **Rubah** — tambah route `PATCH /fop-tasks/{task}/technicians` (kalau route serupa buat status/priority belum ada sebagai named route, cek dulu apakah `updateStatus`/`updatePriority` existing pakai route terpisah atau ikut `fop-tasks.update`). |
| `tests/Feature/FopTaskInlineTechnicianUpdateTest.php` | **Baru** |

**Checklist:**
- [ ] Klik kolom Teknisi → dropdown multi-select + toggle PIC muncul inline, tanpa modal — reuse pattern Alpine `updateStatus`/`updatePriority` yang sudah terbukti jalan di baris 172/197.
- [ ] Optimistic UI: perubahan langsung tampil, rollback ke nilai lama kalau request backend gagal (tampilkan toast error) — cek dulu apakah `updateStatus`/`updatePriority` existing sudah optimistic atau full-reload; ikuti pola yang sama biar konsisten.
- [ ] Debounce/lock supaya 2 edit cepat berurutan di kolom berbeda tidak race sebelum response pertama selesai.
- [ ] Panel ringkasan roster team ter-update real-time (Alpine reactive) tiap ada perubahan assignment.
- [ ] Tidak duplikasi kerja dengan Task 9 (yang akan menghapus inline-select status di baris 172) — koordinasi urutan implementasi.

**Acceptance Criteria:**
1. FOP ubah teknisi langsung dari tabel utama tanpa buka modal terpisah.
2. Kalau backend reject (mis. konflik jadwal), UI rollback ke nilai sebelumnya dan tampil pesan error jelas — tidak silently stuck di nilai salah.
3. Roster panel ter-update tanpa refresh manual.

---

### Task 6 — Dialog Laporan: `Lapor Sekarang` / `Lapor Nanti` (extend mekanisme existing)

**Status:** `Done `

**✅ Gap sementara DITUTUP (sebelumnya nunggu Task 7, sekarang Task 7 & 9 udah `Done`):** eksekusi Task 6 sempat MENGHAPUS tombol `Pending` berdiri sendiri milik teknisi (toggle lama baris 752-758, satu-satunya jalan teknisi set task ke Pending di luar konteks laporan) — window waktu di mana teknisi kehilangan opsi Pending generik sampai Task 7 (tombol `Pending` top-level, reschedule) selesai. Task 7 udah nutup gap ini (tombol `Pending` top-level terpisah, lihat bagian Task 7), dan Task 9 nutup sisi sinkronisasi/histori-nya. Sesuai desain final poin 8: `Pending` (top-level, reschedule) = Task 7, `Lapor Nanti` (dalam dialog laporan) = Task 6 — dua mekanisme berbeda, sekarang lengkap keduanya.

**Tujuan:** Setelah tombol Laporan Survey/Pemasangan/Maintenance/dll ditekan, tampilkan Dialog Alert 2 pilihan (`Lapor Sekarang`/`Lapor Nanti`) — sesuai kebutuhan poin 6.

**Kondisi kode saat ini (koreksi dari draft awal, dan koreksi kedua dari eksekusi Task 6):** `resources/views/tasks/show.blade.php` baris 752-756 (bukan 750-758) punya tombol tunggal berlabel kondisional — **"Laporan Nanti"** (untuk `SURVEY`/`PEMASANGAN`/`MAINTENANCE`) atau **"Pending"** (tipe lain) — yang buka 1 modal (`pending-task`, baris 976-1004). Modal itu submit ke `route('tasks.pending')`. **Koreksi penting:** route ini di-handle oleh **`TaskStatusController::pending()`** (baris 59-70, `authorize('statusPending', ...)`, panggil `TaskService::setPending()`) — **BUKAN** `TaskController::pending()` seperti tertulis semula. `TaskController::pending()` (baris 327-348) itu handler route **`tasks.fop-pending`** yang berbeda (`authorize('fopPending', ...)`, dipakai modal `fop-pending-task` FOP-side). Dua route/controller/modal ini mirip nama tapi beda tujuan — jangan tertukar. `TaskStatusController::pending()` + `TaskService::setPending()` sudah berperilaku benar: set `Task.status = TaskStatus::PENDING` + `pending_reason`, **TIDAK melepas assignment teknisi, TIDAK reschedule** — ini yang dipakai jadi basis `Lapor Nanti`.

Selain itu, tombol "Laporan Survey/Pemasangan/Maintenance/Isi Laporan" yang langsung ke form (statusComplete block, baris 760-791 lama) adalah tombol **terpisah** dari toggle Pending/Laporan Nanti di atas — bukan tombol yang sama seperti tersirat di draft awal. Implementasi final menggabungkan kedua tombol ini jadi satu entry point (gated `statusComplete`, tampil saat status `in_progress`) yang membuka dialog 2 pilihan; toggle lama (752-756) dan modal lama (976-1004) dihapus karena fungsinya dipindah ke komponen shared `<x-task.report-choice-dialog>`.

**File yang dibuat/dirubah (realisasi):**
| File | Aksi |
|---|---|
| `resources/views/components/task/report-choice-dialog.blade.php` | **Baru** — komponen shared: tombol trigger + modal Dialog Alert 2 pilihan (`Lapor Sekarang` → link form laporan; `Lapor Nanti` → modal alasan → submit `route('tasks.pending', $task)` dengan `report_deferred=1`). Dipakai di `tasks/show.blade.php` dan `tasks/own.blade.php` (bukan implementasi terpisah). |
| `resources/views/tasks/show.blade.php` | **Rubah** — toggle button lama (752-758) dan modal `pending-task` lama (976-1004) dihapus; blok statusComplete (760-791 lama) diganti pemanggilan `<x-task.report-choice-dialog>` saat status `in_progress`, atau link langsung "Lanjutkan Laporan" saat status `pending` (laporan ditunda, tidak perlu dialog lagi). |
| `resources/views/tasks/own.blade.php` | **Rubah** — "Isi Laporan" (242-264 lama) diganti sama seperti di atas: `<x-task.report-choice-dialog>` saat `in_progress`, link "Lanjutkan Laporan" saat `pending`. |
| `app/Http/Controllers/TaskStatusController.php` | **Rubah (minor)** — `pending()` (baris 59-70) terima input `report_deferred` (boolean, opsional) dan teruskan ke `TaskService::setPending()`. |
| `app/Services/TaskService.php` | **Rubah (minor)** — `setPending()` terima parameter `bool $reportDeferred = false`, simpan ke kolom `report_deferred`. |
| `app/Models/Task.php` | **Rubah** — tambah `report_deferred` ke `$fillable` dan `$casts` (boolean). |
| `database/migrations/2026_07_11_000001_add_report_deferred_to_tasks_table.php` | **Baru** — kolom `report_deferred` (boolean, default false) di `tasks`. |
| `tests/Feature/TaskReportDialogTest.php` | **Baru** — test reuse `TaskStatusController::pending()`/`TaskService::setPending()` untuk `Lapor Nanti` + rendering dialog di `tasks/show.blade.php` dan `tasks/own.blade.php`. |

**Gap ke-4 & ke-5 DITUTUP (2026-07-11, pasca Task 9 `Done`):** sinkronisasi `FopTask` dan badge pembeda sekarang jalan otomatis lewat `TaskObserver` (Task 9) — gak ada kode tambahan yang perlu ditulis di Task 6 sendiri, cuma perlu test integrasi buat verifikasi end-to-end lewat jalur HTTP asli Task 6. Detail:
- `FopTask.status` (kolom `FopTaskStatus` enum) tetap `Pending` (bukan case baru `lapor_nanti` — lihat keputusan desain di Task 9), TAPI `fop_task_status_history.to_status` nyimpen `'lapor_nanti'` granular, beda dari `pending_reschedule` (Task 7) & `pending_fop` (existing `fopPending`).
- Badge status realtime di `fop_tasks/index.blade.php` (Task 9) nampilin label dari histori terbaru — jadi tiket yang lagi `Lapor Nanti` kelihatan teksnya "Lapor Nanti", bukan cuma "Pending" generik.
- Diverifikasi test baru `test_lapor_nanti_syncs_fop_task_status_and_writes_dedicated_history_row` & `test_lapor_nanti_shows_distinct_badge_label_on_fop_tasks_dashboard` di `tests/Feature/TaskReportDialogTest.php`.

**Checklist:**
- [x] Dialog Alert muncul begitu tombol Laporan (Survey/Pemasangan/Maintenance/dll) ditekan — BUKAN langsung ke modal `pending-task` seperti sekarang. (di `show.blade.php` dan `own.blade.php`)
- [x] `Lapor Sekarang` → lanjut ke form laporan (`customers.survey.report`, `customers.installation.report`, `tasks.maintenance.report`) langsung.
- [x] `Lapor Nanti` → reuse `TaskStatusController::pending()` + `TaskService::setPending()` existing (status `TaskStatus::PENDING`, assignment tetap), set `report_deferred = true` biar beda query dari `Pending` reschedule (Task 7) yang statusnya sama-sama bisa disebut "pending" tapi behavior beda.
- [x] Sinkron ke `FopTask` (Task 9): histori transisi jadi `lapor_nanti` (granular di `fop_task_status_history.to_status`) kalau `report_deferred = true` — otomatis lewat `TaskObserver`, gak butuh kode tambahan di Task 6. **DITUTUP pasca Task 9.**
- [x] Badge/label beda dari tombol `Pending` (Task 7) di UI supaya FOP gak ketuker di dashboard — badge status realtime (Task 9) di `fop_tasks/index.blade.php` nampilin teks "Lapor Nanti" granular, beda dari "Pending (Reschedule)"/"Pending". **DITUTUP pasca Task 9.**
- [x] Rapikan label tombol existing yang sekarang inconsistent ("Laporan Nanti" vs "Pending") jadi konsisten "Lapor Nanti" di semua tempat — toggle lama dihapus, diganti dialog seragam untuk semua task_type.

**Acceptance Criteria:**
1. ✅ Klik tombol Laporan apa pun → selalu muncul dialog 2 pilihan, tidak langsung ke form maupun langsung ke modal pending lama.
2. ✅ Pilih `Lapor Nanti` → task tetap terdaftar ke teknisi yang sama (perilaku `pending()` existing tidak berubah), `report_deferred = true`, laporan bisa dilanjut kapan saja.
3. ✅ FopTask yang terkait tercatat histori `lapor_nanti` (granular) dan badge dashboard FOP nampilin label "Lapor Nanti" — diverifikasi via `TaskReportDialogTest` pasca Task 9.

---

### Task 7 — Tombol `Pending` Top-Level (Reschedule Penuh) — FITUR BARU, BUKAN REUSE

**Status:** `Done`

**Tujuan:** Tombol `Pending` di top-level Detail Task (teknisi-triggered) yang melepas assignment & reschedule task ke hari lain, balik ke antrian Task FOP — sesuai kebutuhan poin 7.

**Kondisi kode saat ini (koreksi dari draft awal, line number di-refresh pasca eksekusi Task 6):** Ini **BUKAN sekadar rename tombol lama** — mekanisme reschedule-penuh-lepas-assignment belum ada sama sekali di kode. Yang mirip cuma "Set Pending" (baris 807, `resources/views/tasks/show.blade.php`) tapi itu **FOP-triggered** (`$this->authorize('fopPending', $task)`) dan modalnya (baris 890-902) eksplisit bilang: *"Tim teknisi yang sudah di-assign **tidak akan terhapus**"* — berlawanan arah dari yang kita mau. Task 7 harus bikin jalur **teknisi-triggered** yang baru, terpisah dari `fopPending`.

**Prioritas naik:** Task 6 (sudah `Done`) menghapus satu-satunya tombol Pending generik milik teknisi (lihat catatan gap di Task 6). Sampai Task 7 ini selesai, teknisi tidak punya cara set task ke Pending di luar konteks laporan (Lapor Nanti). Kerjakan Task 7 lebih dulu dari task-task lain yang tidak dependent.

**File yang dibuat/dirubah (realisasi):**
| File | Aksi |
|---|---|
| `app/Enums/TaskStatus.php` | **Rubah** — tambah case baru `RESCHEDULE = 'reschedule'` + entry di `label()` ("Reschedule") dan `badgeClasses()` (kuning, satu grup sama `PENDING`/`WAITING_*`). Kolom `tasks.status` cuma `string(30)` di DB (bukan DB-level enum), jadi gak perlu migrasi buat nilai baru ini. |
| `resources/views/tasks/show.blade.php` | **Rubah** — tombol baru `Pending` (warna amber/kuning, ikon jam) ditaruh di awal blok "Action Buttons" top-level (sebelum tombol Mulai/Laporan), gated `@can('statusReschedule', $task)`. Modal terpisah `reschedule-task-{id}` (alasan wajib) ditaruh berdekatan dengan modal "Set Pending" FOP-side existing tapi named-scope beda — tidak menyentuh toggle FOP (baris ~805-813) atau dialog Task 6. |
| `app/Http/Controllers/TaskController.php` | **Rubah** — method baru `reschedule(Request, Task)`: `authorize('statusReschedule')` → validasi `pending_reason` (reuse kolom existing, bukan kolom baru) → `DB::transaction`: `Task.status = RESCHEDULE` + `pending_reason` + `updated_by`, `$task->teamMembers()->delete()` (lepas pivot eksekusi), cari `FopTask::where('task_id', $task->id)`, kalau ada: `technicians()->detach()`, `status = FopTaskStatus::PENDING`, `pending_reason` disamakan, `team_id = null`, `manual_override_at = null` (di-set langsung via `$model->save()`, bukan `update()`, karena `manual_override_at` gak masuk `#[Fillable]` di `FopTask`), lalu `AuditLog::log()` untuk Task & FopTask, lalu `app(FopTaskTeamService::class)->rebuildTeamsForDate(Carbon::parse($fopTask->task_date))`. Redirect ke `tasks.own` (bukan `tasks.show` — teknisi udah bukan member lagi setelah pivot lepas). |
| `routes/web.php` | **Rubah** — tambah `POST /tasks/{task}/reschedule` (`tasks.reschedule`) di dalam group `permission:task.execute` yang sama dengan `tasks.pending` existing. |
| `app/Policies/TaskPolicy.php` | **Rubah** — ability baru `statusReschedule(User, Task): bool` = `hasPermission('task.execute') && isMember && status in [terjadwal, in_progress]` (beda dari `statusPending` yang gak ngecek status secara eksplisit di policy). |
| `tests/Feature/TaskRescheduleTest.php` | **Baru** — 5 test: set status + lepas pivot, wajib alasan (422/validation error), sync ke FopTask terkait (status/team_id/technicians), guard hanya member, guard status `selesai` ditolak. |

**Deviasi dari rencana awal (disengaja, dicatat biar gak dianggap bug):**
- **Gak ada kolom `reschedule_reason` baru** — reuse kolom `pending_reason` yang udah ada di `tasks` (dipakai bareng sama `fopPending` dan Task 6 `Lapor Nanti`). Ini cuma reuse kolom teks alasan, BUKAN reuse makna status — `TaskStatus::RESCHEDULE` tetap nilai enum sendiri, terpisah total dari `TaskStatus::PENDING`.
- **Riwayat/status history (checklist poin ke-5) tadinya BELUM dikerjakan di sini** — audit trail dulu cuma lewat `AuditLog::log()` generik (pola yang sama dipakai `FopTaskController`), bukan tabel `fop_task_status_history` dedicated (tabel itu belum ada di codebase saat Task 7 dikerjakan). **DITUTUP (2026-07-11) pasca Task 9 `Done`:** `TaskObserver` (Task 9) otomatis nulis baris `fop_task_status_history` tiap kali `Task.status` berubah, TERMASUK `RESCHEDULE` — gak butuh kode tambahan di `reschedule()` sendiri (Observer hook di level model `Task`, jalan buat SEMUA jalur transisi termasuk yang udah ada). `AuditLog::log()` yang lama tetap ada juga (2 mekanisme audit trail komplementer, bukan saling gantiin) — histori transisi granular (buat Riwayat FOP, Task 10) baca dari `fop_task_status_history`, sedangkan `AuditLog` tetap buat audit umum siapa-ubah-apa.

**Checklist:**
- [x] Tombol `Pending` (reschedule) HANYA di top-level Detail Task, benar-benar terpisah dari tombol "Laporan" (Task 6) dan dari tombol "Set Pending" FOP-side existing — 3 tombol beda, gak campur baur.
- [x] Modal wajib isi alasan sebelum submit (`required` di textarea + validasi backend `required|string|max:500`).
- [x] Task lepas dari teknisi (pivot `task_teams` dihapus via `teamMembers()->delete()`, plus `fop_task_user` di-detach), `team_id` FopTask di-reset — trigger `rebuildTeamsForDate()` karena roster berubah.
- [x] Task balik muncul di halaman `/fop-tasks` untuk dijadwalkan ulang ke teknisi lain/hari lain (FopTask status `Pending`, `team_id = null`, technicians kosong).
- [x] Riwayat catat histori reschedule lengkap di tabel dedicated (`fop_task_status_history`, entry `to_status = 'pending_reschedule'`) — **DITUTUP pasca Task 9**, otomatis lewat `TaskObserver`, diverifikasi test baru `test_reschedule_writes_dedicated_status_history_row` di `tests/Feature/TaskRescheduleTest.php`.

**Acceptance Criteria:**
1. [x] Klik tombol `Pending` (reschedule) di Detail Task selalu minta alasan dulu sebelum submit. (`test_reschedule_requires_reason`)
2. [x] Task yang di-reschedule hilang dari `/tasks-saya` teknisi tsb (pivot lepas), muncul lagi di `/fop-tasks` untuk dijadwal ulang. (`test_reschedule_sets_status_and_releases_technician_assignment`, `test_reschedule_syncs_related_fop_task_and_resets_team`)
3. [x] Rebuild Team ke-trigger karena roster tanggal itu berubah (task lepas dari teknisi). (dicek lewat assertion `team_id` null + `technicians` kosong pasca rebuild di `test_reschedule_syncs_related_fop_task_and_resets_team`)
4. [x] 3 mekanisme beda — `Lapor Nanti` (Task 6, assignment tetap), `Pending` reschedule (Task 7, assignment lepas), `fopPending`/"Set Pending" existing (FOP-side, assignment tetap) — masing-masing punya test terpisah dan tidak saling menimpa status/behavior satu sama lain, TERMASUK di level histori granular (`lapor_nanti`/`pending_reschedule`/`pending_fop` beda entry walau `fop_tasks.status` sama-sama `Pending`). (`TaskRescheduleTest` vs `TaskReportDialogTest` vs `TaskFopActionsTest`, jalan bareng tanpa regresi — 88/88 gabungan `--filter=Task` hijau)

Test suite: `tests/Feature/TaskRescheduleTest.php` 6/6 hijau (5 asli + 1 baru nutup gap histori). Regression check gabungan (`TaskReportDialogTest`, `FopTasksTest`, `FopTaskTeamServiceTest`, `FopTaskSwitchTechnicianTest`, `FopTaskSwitchTeamTest`, `TaskFopActionsTest`, `TaskBroadcastingTest`, `FopTaskStatusSyncTest`) `--filter=Task` 88/88 hijau, `--filter=FopTask` 65/65 hijau, gak ada regresi.

**Status blocker (semua DITUTUP pasca Task 9, 2026-07-11):**
- ~~Task 6 checklist poin "Badge/warna beda dari tombol `Pending` (Task 7) di UI"~~ — **DITUTUP**, badge status realtime Task 9 nampilin label granular per transisi.
- ~~Task 7 checklist poin "Riwayat catat histori reschedule lengkap"~~ — **DITUTUP**, `TaskObserver` (Task 9) nulis histori otomatis.
- Task 9 sekarang selesai dipakein prasyarat dari Task 6 (`report_deferred`) dan Task 7 (`TaskStatus::RESCHEDULE`) buat mapping status FopTask otomatis — lingkaran Task 6/7/9 sekarang tertutup penuh, gak ada gap tersisa di antara ketiganya.

---

### Task 8 — Antrian Sorting Berdasarkan `client_request_date`

**Status:** `Done`

**Tujuan:** Task dengan `client_request_date` di masa depan tampil di bawah (Upcoming), begitu jadwalnya tiba naik otomatis ke atas — sesuai kebutuhan poin 8 (dan poin 13, lihat catatan di Task 13).

**Kondisi kode nyata:** `FopTaskController::index()` (baris 31, line number geser dikit dari draft) sudah punya `orderByRaw` di baris 49-56: CASE berdasar `priority` (Urgent→High→Medium→Low→else), lalu CASE `category IN ('Survey','PSB')` → `created_at ASC` else `created_at DESC`. **`client_request_date` sama sekali belum masuk sorting** — cuma dipakai di validasi/set saat `store()`/`update()`, belum pernah dibaca balik buat urutan tampilan. Konfirmasi ini masih akurat, cuma line number bergeser (drift wajar pasca Task 1-7).

**File yang dibuat/dirubah (realisasi):**
| File | Aksi |
|---|---|
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — `index()`: sisipkan 1 CASE tambahan SEBELUM CASE priority yang sudah ada, tanpa menghapus 2 CASE lama. |
| `resources/views/fop_tasks/index.blade.php` | **Rubah** — kolom "Tanggal" ditambah badge "JADWAL HARI INI" (kalau `client_request_date <= hari ini`) atau "Terjadwal — {tanggal}" (kalau di masa depan), muncul di bawah `task_date` per baris. |
| `tests/Feature/FopTaskSortingTest.php` | **Baru** — 6 test: sink-below untuk upcoming, normal-sort untuk hari-ini, regression priority, regression category Survey/PSB, 2 test badge. |

**Deviasi dari rencana awal (disengaja, ketemu pas development, dicatat biar gak dianggap bug):**
- **`CURDATE()` diganti jadi parameter binding tanggal** — rencana awal pakai `CURDATE()` (fungsi MySQL-only). Test suite proyek ini jalan di SQLite in-memory (`phpunit.xml`), dan SQLite gak punya `CURDATE()` — query langsung error `no such function: CURDATE` kalau dipertahankan. Diganti jadi `client_request_date >= ?` dengan binding `now()->addDay()->toDateString()` (portable, jalan di MySQL maupun SQLite).
- **Threshold diubah dari `> CURDATE()` jadi `>= besok`** — ditemukan bug lewat test: kolom `client_request_date` (meski migrasinya `$table->date()`) ternyata disimpan dengan suffix waktu (`'2026-07-11 00:00:00'`, bukan `'2026-07-11'` murni) di kedua driver. Perbandingan string `'...00:00:00' > '2026-07-11'` SELALU true (string yang punya suffix dianggap "lebih besar" dari prefix-nya secara leksikografis) — akibatnya task dengan `client_request_date` HARI INI pun ikut ke-sink ke bucket "upcoming", padahal harusnya ikut sorting normal. Fix: bandingkan `>= tanggal besok` (bukan `> hari ini`) — hasil akhirnya identik secara semantik (upcoming = strictly setelah hari ini) tapi kebal dari isu suffix waktu di kedua driver. Ke-cover di test `test_task_with_today_client_request_date_follows_normal_priority_sort`.
- **Test regression category pakai `category = 'PSB'`, bukan `'SURVEY'`** — ditemukan (bukan dikerjakan/difix, di luar scope Task 8) bug pre-existing: literal CASE existing `category IN ('Survey', 'PSB')` pakai title-case `'Survey'`, padahal backing value enum `TaskType::SURVEY` sekarang `'SURVEY'` (uppercase, hasil migrasi `2026_07_01_111156_update_task_type_values_to_uppercase`). String comparison case-sensitive di kedua driver bikin literal `'Survey'` itu **gak pernah match** kategori SURVEY asli — CASE ASC-by-created_at buat Survey secara diam-diam gak pernah kepakai, task SURVEY malah ikut jalur DESC (sama kayak kategori lain). `'PSB'` di literal itu masih exact-match sama `TaskType::PEMASANGAN`, jadi dipakai buat regression test yang akurat. **Catatan blocker (bukan dikerjakan di sini):** literal `'Survey'` di 2 CASE existing (baris category, gak disentuh Task 8) perlu diganti `'SURVEY'` di task terpisah kalau mau match asli sesuai intent aslinya — di luar scope "jangan hapus/ubah 2 CASE lama" punya Task 8, tapi worth di-flag buat siapa pun yang pegang controller ini berikutnya.

**Checklist:**
- [x] Tambah CASE baru di `orderByRaw` existing: `client_request_date IS NOT NULL AND client_request_date >= {besok}` → taruh di bucket "upcoming" (1) yang di-ranking di bawah (0); digabung TANPA menghapus 2 CASE lama (priority, category Survey/PSB ASC/DESC).
- [x] Badge "JADWAL HARI INI" begitu `client_request_date <= hari ini`.
- [x] Badge "Terjadwal — {tanggal}" untuk task dengan tanggal request di masa depan.
- [x] Regression test: sorting existing (priority/created_at) tidak rusak.

**Acceptance Criteria:**
1. [x] Task dengan `client_request_date` besok atau lebih tampil di bawah, walau priority-nya lebih tinggi dari task lain. (`test_task_with_future_client_request_date_sinks_below_others`)
2. [x] Begitu hari sistem sama dengan `client_request_date`, task otomatis ikut sorting normal (priority) tanpa perlu cron/refresh manual (dihitung ulang tiap page load, bukan job terjadwal). (`test_task_with_today_client_request_date_follows_normal_priority_sort`)
3. [x] Sorting lama (priority, category Survey/PSB ASC/DESC) tetap jalan untuk task tanpa `client_request_date`. (`test_priority_sorting_regression_unaffected`, `test_category_survey_psb_created_at_ascending_regression_unaffected`)

Test suite: `tests/Feature/FopTaskSortingTest.php` 6/6 hijau. Regression check gabungan `--filter=FopTask` 55/55 hijau, `--filter=Task` 75/75 hijau, gak ada regresi.

---

### Task 9 — Status Realtime (Hapus Dropdown Manual)

**Status:** `Done`

**Tujuan:** Status `FopTask` full derive dari status `Task` eksekusi (sync 2 arah), dropdown status manual dihapus — sesuai kebutuhan poin 9.

**Kondisi kode nyata (konfirmasi, line number geser dikit pasca Task 8):** `resources/views/fop_tasks/index.blade.php` punya dropdown status manual inline (`<select @change="updateStatus(...)">`, sekarang di baris ~226) dan modal create/edit punya `<select name="status" x-model="modal.data.status">` (sekarang di baris ~477) — DUA tempat status bisa diubah manual bebas. **Koreksi:** `updateStatus()` BUKAN method PHP terpisah di `FopTaskController` — itu cuma fungsi Alpine.js yang manggil endpoint generik `PUT /fop-tasks/{id}` (method `update()` yang sudah ada, sama kayak `updatePriority()`), jadi gak ada "handler updateStatus()" PHP yang perlu dihapus — cuma pemicu JS/UI-nya yang dicabut.

**File yang dibuat/dirubah (realisasi):**
| File | Aksi |
|---|---|
| `app/Observers/TaskObserver.php` | **Baru** — hook `updated(Task $task)`: cari `FopTask` terkait (`task_id`), skip kalau `FopTask.status` udah `Cancel` (proteksi override manual FOP, lihat Task 12), resolve target `FopTaskStatus` + label histori granular dari kombinasi `Task.status`/`report_deferred`/`fop_review_status`, update `FopTask.status` (idempotent) + tulis 1 baris `fop_task_status_history`. |
| `database/migrations/2026_07_17_000001_create_fop_task_status_history_table.php` | **Baru** — tabel `fop_task_status_history` (`fop_task_id`, `from_status`, `to_status`, `changed_by`, `changed_at`). |
| `app/Models/FopTaskStatusHistory.php` | **Baru** — tambah method `label()`: map `to_status` (string granular) ke label human-readable. |
| `resources/views/fop_tasks/index.blade.php` | **Hapus** dropdown status inline di tabel dan dropdown status di modal edit — ganti jadi badge read-only (pakai label granular dari histori terbaru) + tombol "Cancel" eksplisit + link "Review Laporan →" kalau laporan lagi nunggu approve. Modal CREATE (bukan edit) tetap punya `<select>` tapi cuma 2 opsi (`Proses`/`Pending`) — ini klasifikasi awal tiket BARU (belum ada status buat di-derive), bukan "ubah status tiket existing" yang dilarang. |
| `tests/Feature/FopTaskStatusSyncTest.php` | **Baru** — 10 test: semua mapping status + skip saat `FopTask` udah manual Cancel + no-op kalau field gak berubah + no-op kalau `Task` gak punya `FopTask` terkait. |

**Deviasi dari rencana awal (disengaja, dicatat biar gak dianggap bug/scope-creep):**
- **`fop_tasks.status` (kolom, `FopTaskStatus` enum) TETAP 4 nilai** (`Proses`/`Pending`/`Selesai`/`Cancel`) — **TIDAK** ditambah case `lapor_nanti` seperti tersirat di deskripsi file-list draft awal ("FopTask.status = lapor_nanti"). Nambah case baru di enum itu bakal mecahin banyak `whereIn('status', ['Proses', 'Pending'])` yang tersebar di codebase (`FopTaskTeamService::rebuildTeamsForDate()`, `FopTaskController::index()`, `switchTargetTasks`, `conflictDates`, `autoSyncAndCalculatePriority`, dst) — semua file itu TIDAK ada di file-list Task 9, jadi nambah case baru berarti keluar scope + resiko regresi luas. Sebagai gantinya: `fop_tasks.status` tetap `Pending` buat KETIGA kasus (`pending_reschedule`/`lapor_nanti`/`pending_fop`), bedanya cuma di kolom granular `fop_task_status_history.to_status` (string bebas, bukan enum) + label di UI. Ini tetap penuhin maksud Kebutuhan poin 9 (FOP bisa bedain "task ini nunggu dijadwal ulang" vs "task ini tinggal nunggu laporan") — cuma bedanya di level tampilan/histori, bukan di level skema `fop_tasks.status`.
- **`app/Models/FopTask.php` disentuh** (di luar file-list literal) — nambah relasi `statusHistories(): HasMany` (perlu didefinisikan biar bisa di-eager-load `with('statusHistories')` dari `index()`). Ini plumbing minimal yang gak terhindarkan, sama kelasnya dengan companion-file lain di bawah.
- **`app/Providers/AppServiceProvider.php` disentuh** (di luar file-list literal) — 2 baris `Task::observe(TaskObserver::class)`, ngikutin pola registrasi Observer existing (`Invoice::observe`, `Payment::observe`) yang udah ada di file yang sama. Tanpa ini Observer gak pernah ke-trigger sama sekali — bukan penambahan fitur, murni wiring wajib.
- **Endpoint approve/reject laporan TIDAK dibikin baru di `FopTaskController`** — `fop_review_status` itu kolom di tabel `tasks` (execution), bukan `fop_tasks`, dan `TaskController::review()` udah punya business logic approve/reject LENGKAP (transisi customer workflow, guard CID/Invoice buat PSB, notifikasi) yang bakal keduplikasi kalau dibikin ulang versi FopTask-nya — resiko divergensi 2 sumber logic yang sama. Solusi: badge di `fop_tasks/index.blade.php` nampilin link **"Review Laporan →"** ke `route('tasks.show', $task->task_id)` kalau `Task.status=selesai` dan `fop_review_status=pending` — FOP diarahkan ke tombol Approve/Reject eksplisit yang udah ada di halaman itu (bukan dropdown, satu kali klik). Ini "jalur eksplisit" yang dimaksud AC3, cuma lokasinya di halaman Task, bukan halaman FopTask. **Cancel** tetap dapet tombol baru langsung di `fop_tasks/index.blade.php` (reuse endpoint `update()` existing, payload `{status: 'Cancel'}`) karena itu murni operasi level `fop_tasks`.
- **`FopTaskController.php` gak ada method PHP yang dihapus** (gak ada `updateStatus()` PHP method dari awal, cuma dugaan draft). Perubahan riilnya cuma eager-load: `index()` nambah kolom `task.status/report_deferred/fop_review_status` + relasi `statusHistories` biar badge & link "Review Laporan" gak N+1.

**Checklist:**
- [x] Setiap transisi status `Task` → `FopTask` tercatat di `fop_task_status_history` (bukan cuma overwrite kolom `status`).
- [x] Dropdown status manual dihapus dari UI (tabel & modal edit) — modal create sengaja disisakan 2-opsi buat klasifikasi tiket baru, lihat deviasi.
- [x] Jalur eksplisit tetap ada: Cancel (tombol baru, reuse `update()`), approve/reject laporan (link ke tombol existing di `tasks/show.blade.php`, lihat deviasi) — supaya FOP tidak buntu.
- [x] Task `pending_reschedule` (Task 7) dan `lapor_nanti` (Task 6) masing-masing punya baris histori jelas dengan `to_status` beda, walau `fop_tasks.status` sama-sama `Pending`.

**Acceptance Criteria:**
1. [x] Tidak ada lagi dropdown bebas ubah status FopTask **existing** di UI — badge read-only + tombol aksi eksplisit. (Verifikasi manual: `grep updateStatus(\|name="status" x-model` cuma nyisa 1 hit, itu select 2-opsi khusus create-mode.)
2. [x] Tiap transisi status tercatat di `fop_task_status_history` dengan waktu (`changed_at`) & aktor (`changed_by`). (`FopTaskStatusSyncTest`, 8 skenario mapping.)
3. [x] FOP tetap bisa approve/reject (link eksplisit ke Task review) /cancel (tombol eksplisit baru) — bukan dropdown generik.

Test suite: `tests/Feature/FopTaskStatusSyncTest.php` 10/10 hijau. Regression check gabungan `--filter=FopTask` 65/65 hijau, `--filter=Task` 85/85 hijau. Full suite (`php artisan test`) ada 59 test gagal pre-existing (modul billing/invoice/payment/customer/RBAC — `RolePermissionMatrixTest`, `UserAuditHardeningTest`, `PaymentInputTest`, dst) yang **udah gagal sebelum Task 9 disentuh** (dikonfirmasi lewat `git stash` + re-run) — sama sekali gak nyentuh domain FOP Task/Task, di luar scope buat difix di sini.

**Catatan blocker untuk Task lain:**
- Task 12 (Cancel dengan `cancel_reason` wajib) belum dikerjakan — tombol "Cancel" baru di Task 9 masih pakai jalur `update()` existing yang BELUM wajibkan alasan (kolom `cancel_reason` di `fop_tasks` belum ada). Begitu Task 12 jalan, tombol Cancel ini perlu diarahkan ke modal alasan, bukan langsung submit.
- Task 10 (Riwayat Lengkap) sekarang punya sumber data solid dari `fop_task_status_history` (baru ada di Task 9) buat nampilin histori transisi lengkap per tiket — belum ada UI buat nampilin tabel ini di halaman `/fop-tasks/history`, itu scope Task 10.

---

### Task 10 — Riwayat Lengkap + SLA Deadline (Dual-Cycle)

**Status:** `To Do` (depends on Task 6, Task 7, Task 9)

**Tujuan:** Halaman Riwayat gabung detail task + semua laporan + SLA total (wall-clock penuh, dipecah 2 siklus kalau pernah `Pending`) — sesuai kebutuhan poin 10.

**Kondisi kode nyata:**
- `Task::actualDurationMinutes()` dan `isOverSla()` (`Task.php:121-137`) **sudah ada tapi single-cycle murni**: `$this->started_at->diffInMinutes($this->completed_at)` — gak ada konsep multi-siklus sama sekali, jadi dual-cycle beneran kerjaan baru.
- **Tidak ada** field `tools_used` di mana pun (`Task.php` fillable, atau tabel lain) — `task_reports` genuinely tabel baru, bukan extend existing.
- `PackageSlaSetting::getSlaHoursAttribute()` (baris 43-46) cuma konversi `sla_duration`→jam. **Temuan penting:** query lookup existing di `app/Http/Controllers/Master/SlaTimelineController.php:37` cuma filter `internet_package_id`, **TIDAK filter `task_type`** — padahal kolom `task_type` ada di tabel `package_sla_settings`. Ini kemungkinan bug/gap existing yang perlu diperbaiki DULU sebelum Task 10 bisa reuse lookup ini dengan benar (kalau dibiarkan, target SLA yang keambil bisa salah tipe task).
- `resources/views/fop_tasks/history.blade.php` kolomnya sekarang cuma: Kategori, Tanggal, Tugas, Area, Issue, Teknisi, Team, Status, Prioritas, Aksi (baris 86-95) — **tidak ada kolom SLA/durasi/alat sama sekali**, jadi ini benar-benar nambah kolom baru, bukan modifikasi kecil.

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `app/Http/Controllers/Master/SlaTimelineController.php` | **Rubah (prasyarat)** — baris 37: tambah filter `->where('task_type', $task->category->value)` ke query `PackageSlaSetting`, supaya lookup SLA target sesuai tipe task, bukan cuma paket internet. Kerjakan ini SEBELUM reuse di `TaskReport`. |
| `database/migrations/2026_07_xx_create_task_reports_table.php` | **Baru** — `task_reports` (`task_id`, `tools_used` json, `started_at`, `pending_at`, `resumed_at`, `completed_at`, `total_duration_minutes`, `sla_target_minutes`, `sla_status`, `sla_overrun_minutes`, `package_sla_setting_id` FK nullable). |
| `app/Models/TaskReport.php` | **Baru** — accessor hitung total durasi dari akumulasi siklus (bukan selisih timestamp pertama-terakhir seperti `actualDurationMinutes()` existing). |
| `resources/views/fop_tasks/history.blade.php` | **Rubah** — tambah kolom baru SLA Deadline + tools_used di tabel existing (baris 86-95), gabung tampilan laporan Survey/Pemasangan/Maintenance. |
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — method `history()` eager-load `TaskReport` + `FopTaskStatusHistory` (Task 9). |
| `tests/Feature/TaskReportSlaCalculationTest.php` | **Baru** — test kasus dengan & tanpa siklus pending, plus regression test filter `task_type` di `SlaTimelineController`. |

**Checklist:**
- [ ] Fix lookup `PackageSlaSetting` di `SlaTimelineController.php:37` — tambah filter `task_type` (prasyarat, bukan opsional).
- [ ] Kolom siklus (`started_at`, `pending_at`, `resumed_at`, `completed_at`) dicatat tiap kali status berubah (via `TaskObserver` Task 9).
- [ ] `total_duration_minutes` dihitung dari akumulasi seluruh siklus (siklus 1 + siklus 2 dst.), bukan `completed_at - started_at` polos seperti `actualDurationMinutes()` existing.
- [ ] `sla_target_minutes` diambil dari `PackageSlaSetting` (setelah fix filter `task_type` di atas) — bukan hardcode ulang, bukan juga cuma reuse `Task::sla_minutes` yang statis per tipe (`TaskType::slaMinutes()`) tanpa mempertimbangkan paket pelanggan.
- [ ] Riwayat tampilkan histori laporan (alat, durasi, SLA) + histori status (`pending_reason`/`cancel_reason`) dalam 1 halaman — kolom baru di `history.blade.php` yang sekarang belum ada sama sekali.

**Acceptance Criteria:**
1. Task yang selesai tanpa pernah Pending → SLA dihitung 1 siklus lurus dari `started_at` ke `completed_at`.
2. Task yang pernah kena `Pending` lalu dijadwal ulang → SLA dihitung dari akumulasi 2 (atau lebih) siklus, bukan selisih timestamp pertama-terakhir yang bakal salah menghitung jeda reschedule sebagai waktu kerja.
3. Halaman Riwayat menampilkan laporan Survey/Pemasangan/Maintenance + SLA achievement dalam 1 tampilan gabungan.

---

### Task 11 — `/tasks-saya` Tombol Mulai per Jenis Task + Card Bertahap

**Status:** `To Do`

**Tujuan:** Default card di `/tasks-saya` cuma tombol `Mulai Survey`/`Mulai Pemasangan`/`Mulai Maintenance` sesuai tipe, detail+maps baru muncul setelah diklik — sesuai kebutuhan poin 11.

**Kondisi kode nyata — SEBAGIAN BESAR SUDAH ADA, scope jauh lebih kecil dari draft awal:**
- Tombol per-jenis **sudah ada persis**: `own.blade.php` baris 205-239 — status `terjadwal` + tipe `SURVEY` → tombol "Mulai Survey" (213), tipe `PSB` → "Mulai Pemasangan" (224), tipe `MAINTENANCE` → "Mulai Maintenance" (235), tipe lain → "Mulai Task" generic (235, fallback).
- Tombol kedua **"Isi Laporan" sudah ada** (baris 242-264), muncul begitu status `in_progress`/`pending` — link langsung ke `customers.survey.report`/`customers.installation.report`/`tasks.maintenance.report` tergantung tipe.
- `started_at` **sudah** di-set pas teknisi klik Mulai (`TaskService.php`, method start via `TaskStatusController::start()` baris 18-20) — timer SLA sudah akurat sejak mulai kerja, bukan sejak buka Detail. Ini bagian yang SUDAH benar.
- **Gap sebenarnya:** info pelanggan (nama, alamat, POP — baris 128-135) dan link **"Buka Detail"** (baris 200-203) **tampil UNCONDITIONAL**, tidak digate status `terjadwal` vs `in_progress` sama sekali — teknisi bisa langsung klik "Buka Detail" (yang berisi koordinat/Maps di `tasks/show.blade.php`) SEBELUM klik Mulai. Ini yang melanggar kebutuhan poin 11 ("info sensitif baru muncul setelah mulai"), bukan tombol Mulai-nya (itu udah benar).
- **Cross-reference ke Task 6 (SUDAH DIKERJAKAN saat eksekusi Task 6):** "Isi Laporan" di `own.blade.php` (baris 242-264 lama) sudah diarahkan ke dialog `Lapor Sekarang`/`Lapor Nanti` (`<x-task.report-choice-dialog>`, shared dengan `show.blade.php`) — bukan lagi link langsung. **Sisa gap:** `resources/views/tasks/partials/own-card.blade.php` (baris 80-95) — partial AJAX-refresh dari `own.blade.php` — MASIH pakai link langsung ke form laporan, BELUM diarahkan ke dialog yang sama. Ini di luar scope Task 6 (Task 6 hanya menyebut `own.blade.php`, bukan partial-nya) — jadi teknisi masih bisa bypass dialog lewat versi partial/AJAX-refresh dari `/tasks-saya`. Task 11 (atau siapa pun yang menuntaskan partial ini) harus terapkan `<x-task.report-choice-dialog>` yang sama di sini juga.

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `resources/views/tasks/own.blade.php` | **Rubah** — gate info pelanggan (baris 128-135) dan link "Buka Detail" (200-203): sembunyikan/disable selama `status === 'terjadwal'` (belum mulai), tampilkan penuh begitu `status != 'terjadwal'`. |
| `resources/views/tasks/own.blade.php` | ~~**Rubah**~~ — **SUDAH DIKERJAKAN di Task 6**: "Isi Laporan" (242-264 lama) sudah trigger dialog `<x-task.report-choice-dialog>`. |
| `resources/views/tasks/partials/own-card.blade.php` | **Rubah** — MASIH belum konsisten, partial AJAX-refresh dari `own.blade.php` ini belum diterapkan dialog yang sama (baris 80-95 masih link langsung). |
| `tests/Feature/TaskOwnCardStageTest.php` | **Baru** — fokus ke gating info sensitif (gap real), bukan ke tombol Mulai/Isi Laporan yang sudah benar. |

**Checklist:**
- [x] ~~Mapping `task_type` → label tombol (`Mulai Survey`/`Mulai Pemasangan`/`Mulai Maintenance`)~~ — **SUDAH ADA** (baris 205-239), tinggal regression test.
- [x] ~~Timer/SLA (`started_at`) mulai persis saat tombol "Mulai X" diklik~~ — **SUDAH ADA** (`TaskStatusController::start()`), tinggal regression test.
- [ ] Gate info pelanggan (nama/alamat/POP, baris 128-135) — sembunyikan sebelum status berubah dari `terjadwal`.
- [ ] Gate link "Buka Detail" (200-203) — disable/sembunyikan sebelum mulai, karena itu jalan pintas ke koordinat/Maps di halaman Detail.
- [x] "Isi Laporan" (`own.blade.php`) diarahkan ke dialog `Lapor Sekarang`/`Lapor Nanti` (Task 6) — **SUDAH** (dikerjakan saat eksekusi Task 6).
- [ ] Sinkron perubahan ke `own-card.blade.php` (partial AJAX), jangan cuma `own.blade.php` — **BELUM**, sisa gap.

**Acceptance Criteria:**
1. Teknisi buka `/tasks-saya` sebelum klik Mulai — nama pelanggan/alamat/POP dan link "Buka Detail" tidak tampil (cuma jenis task, jadwal, tombol Mulai sesuai jenis).
2. Setelah klik Mulai (mekanisme existing, tidak berubah), info pelanggan + "Buka Detail" muncul, `started_at` sudah tercatat (perilaku existing, regression-tested).
3. Klik "Isi Laporan" dari card `/tasks-saya` memicu dialog `Lapor Sekarang`/`Lapor Nanti` yang sama dengan Task 6 — tidak langsung ke form laporan seperti perilaku existing sekarang.

---

### Task 12 — Cancel Survey/Pemasangan dengan Alasan

**Status:** `To Do`

**Tujuan:** FOP/role berwenang bisa cancel Task dengan alasan wajib (data ganda, rumah direnovasi, salah input POP, dll) — sesuai kebutuhan poin 12.

**Kondisi kode saat ini:** status `Cancel` + `cancelled_at` sudah ada di `FopTaskController::store`/`update`. Belum ada kolom alasan dedicated.

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `database/migrations/2026_07_xx_add_cancel_reason_to_fop_tasks_table.php` | **Baru** — kolom `cancel_reason` (text, nullable). |
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — validasi `required_if:status,Cancel` untuk `cancel_reason`; sync cancel ke `Task` eksekusi + notifikasi teknisi kalau task sedang `in_progress`. |
| `config/rbac.php` | **Rubah** — tambah permission baru `fop_tasks.cancel` (ikuti konvensi underscore existing: `fop_tasks.view/create/update/delete/update_sensitive`, bukan `fop-task.cancel` hyphen). |
| `resources/views/fop_tasks/index.blade.php` | **Rubah** — modal cancel dengan textarea alasan wajib. |
| `resources/views/fop_tasks/history.blade.php` | **Rubah** — tampilkan `cancel_reason` sejajar `pending_reason`. |
| `tests/Feature/FopTaskCancelTest.php` | **Baru** |

**Checklist:**
- [ ] `cancel_reason` wajib diisi sebelum submit cancel.
- [ ] Role yang boleh cancel di-gate lewat permission `fop_tasks.cancel` baru di `config/rbac.php`, bukan hardcode role FOP saja.
- [ ] Cancel task yang `in_progress` ikut cancel/sync ke `Task` eksekusi + notif ke teknisi yang lagi jalan.
- [ ] Cancel trigger `rebuildTeamsForDate()` — kalau task itu satu-satunya jembatan penghubung 2 teknisi, team pecah otomatis (lihat Task 1 checklist edge case).
- [ ] Riwayat tampilkan `cancel_reason`.

**Acceptance Criteria:**
1. Cancel tanpa isi alasan ditolak (validasi 422).
2. Role selain FOP yang diberi permission `fop_tasks.cancel` juga bisa cancel.
3. Cancel task yang sedang dikerjakan teknisi memicu notifikasi ke teknisi tsb, task eksekusi ikut ke-cancel.
4. Cancel yang memutus jembatan team memicu rebuild — team lama pecah jadi sesuai roster baru.

---

### Task 13 — Audit Antrian Sorting Duplikat (Konsolidasi dengan Task 8)

**Status:** `To Do` — **cakupan minimal, sebagian besar sudah tercakup di Task 8**

**Tujuan:** Kebutuhan poin 13 secara substansi sama dengan poin 8 (request tanggal pemasangan saat survey, antrian bawah→atas). Task ini fokus verifikasi tidak ada logic ganda/konflik, plus polish yang belum ke-cover Task 8.

**Konfirmasi cross-check kode:** Sudah dipastikan **tidak ada** implementasi sorting/ordering lain buat FOP task di luar `FopTaskController::index()` (baris 45-52) — bukan asumsi lagi. `FopTaskController::history()` (baris ~514) pakai sort yang beda total (`->orderBy('updated_at', 'desc')`), gak ada hubungan dengan `client_request_date`, jadi gak perlu disentuh Task 13. Tidak ketemu widget/Livewire/API endpoint lain yang bikin urutan FOP task sendiri.

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `resources/views/fop_tasks/index.blade.php` | **Rubah (kecil)** — pastikan badge "Terjadwal"/"Hari Ini" konsisten dipakai juga di context Survey (bukan cuma index umum). |
| `tests/Feature/FopTaskSortingTest.php` | **Rubah** — tambah test case khusus dari alur Survey (bukan cuma manual set `client_request_date`). |

**Checklist:**
- [ ] Konfirmasi: tidak ada 2 implementasi sorting terpisah untuk requirement yang sama (poin 8 vs 13) — pakai 1 query di `FopTaskController` (Task 8), jangan duplikat.
- [ ] Test tambahan: dari alur Survey selesai → `client_request_date` terisi → task otomatis masuk section bawah → sampai tanggalnya, naik ke atas.
- [ ] SLA untuk skenario ini tetap **ditunda**, dibahas terpisah dari SLA siklus pending/lapor-nanti (Task 10) — jangan campur logic.

**Acceptance Criteria:**
1. Tidak ada kode sorting duplikat/konflik antara implementasi poin 8 dan 13.
2. Alur end-to-end dari Survey → request tanggal → antrian bawah → naik ke atas terverifikasi test, bukan cuma diasumsikan dari Task 8.

---

### Task 14 — Customer ID Wajib per Tipe Task + Lock Survey/Pemasangan + Auto-fill POP/Area

**Status:** `To Do`

**Tujuan:** Modal Tambah/Edit Task FOP: Survey & Pemasangan gak bisa ditambah/diedit manual (murni dari alur registrasi); Deac/Relokasi/C-REQ wajib `customer_id` dengan POP/Area auto-fill; O-REQ/INFR REQ boleh request POP/Area manual — sesuai kebutuhan poin 14 & 15 (konfirmasi).

**Kondisi kode nyata (scope jauh lebih sempit dari draft awal — sebagian besar sudah jalan):**
- `TaskType` enum (`app/Enums/TaskType.php`) sudah punya `autoOnlyValues()` (baris 130-133, isinya `[SURVEY, PEMASANGAN]`) dan `manualValues()`/`manualOptions()` (136-152) — komentarnya eksplisit: *"gak boleh dipilih manual saat buat/edit task (baik di /tasks maupun /fop-tasks)"*.
- `FopTaskController::store()` (baris 155) **SUDAH** pakai `Rule::in(TaskType::manualValues())` — create sudah terguard, gak bisa bikin Survey/Pemasangan manual dari `store()`.
- `index.blade.php` modal create **SUDAH** pakai `manualCategoriesData` (dropdown `availableCategories` getter, baris 635-637: `modal.isEdit ? allCategoriesData : manualCategoriesData`) — dropdown create sudah otomatis exclude Survey/Pemasangan. Ada juga hint text baris 320: *"Survey & Pemasangan Baru otomatis dibuat saat Registrasi Pelanggan."*
- **Gap sebenarnya ada di EDIT, bukan create:** `FopTaskController::update()` (baris 247) pakai `Rule::enum(TaskType::class)` — FULL enum, TIDAK dibatasi `manualValues()`. Dropdown edit di Blade pakai `allCategoriesData` (SEMUA tipe termasuk Survey/Pemasangan), gate-nya cuma `canEditCategory` yang murni permission (`fop_tasks.update_sensitive`, `FopTaskController.php` baris 114/573) — **TIDAK cek apakah record existing itu sendiri bertipe Survey/Pemasangan**. Jadi user dengan permission itu SAAT INI bisa ubah task apa pun jadi/dari Survey/Pemasangan lewat edit, atau ubah `customer_id`/`pop_id`/`village_id` task Survey/Pemasangan existing — ini yang harus dikunci, terlepas dari permission.
- **Bug terpisah:** Alpine `selectCustomer()` belum copy `pop_id`/`village_id` ke form meski API `search-customers` sudah return keduanya.

**File yang dibuat/dirubah:**
| File | Aksi |
|---|---|
| `resources/views/fop_tasks/index.blade.php` | **Rubah** — (1) getter `availableCategories` (baris 635-637): saat edit, kalau `modal.data.category` existing record adalah `SURVEY`/`PSB`, paksa dropdown disabled total (bukan cuma exclude dari list) — beda dari sekadar switch `allCategoriesData`/`manualCategoriesData`, karena masalahnya bukan daftar pilihan tapi record itu sendiri yang gak boleh disentuh. (2) Fix `selectCustomer()`: copy `pop_id`/`village_id` dari response API ke `modal.data`, disable field-nya selama `customer_id` terisi. (3) Field `customer_id`/`pop_id`/`village_id` ikut disabled di modal Edit kalau record existing `SURVEY`/`PSB` (bukan cuma `category`-nya). |
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — `update()` (baris 247): tambah rule kondisional — kalau `$task->category` existing sudah `SURVEY`/`PSB`, TOLAK (422) perubahan `category`/`customer_id`/`pop_id`/`village_id` sama sekali, terlepas dari permission `fop_tasks.update_sensitive`. Kalau bukan Survey/Pemasangan, tetap ganti validasi `category` dari `Rule::enum(TaskType::class)` jadi `Rule::in(TaskType::manualValues())` biar sama ketatnya dengan `store()` (saat ini `update()` justru lebih longgar — celah nyata). |
| `app/Http/Controllers/FopTaskController.php` | **Rubah** — wajib `customer_id` utk `[DEAC, RELOKASI, CREQ]` + override `pop_id`/`village_id` dari data customer server-side; `customer_id` nullable utk `[OREQ, INFR]`. |
| `tests/Feature/FopTaskCustomerLockTest.php` | **Baru** — fokus utama ke `update()` (gap real), plus regression test `store()` (yang sudah benar, pastikan gak keregresi). |

**Checklist:**
- [x] ~~Dropdown Tambah Task FOP tidak lagi menampilkan `Survey`/`Pemasangan`~~ — **SUDAH ADA** (`manualCategoriesData`), tinggal regression test.
- [x] ~~`store()` guard `Survey`/`Pemasangan`~~ — **SUDAH ADA** (`Rule::in(TaskType::manualValues())`, baris 155), tinggal regression test.
- [ ] Getter `availableCategories` (baris 635-637): tambah cek `modal.isEdit && task existing category is SURVEY/PSB` → force disabled total, bukan cuma switch daftar opsi.
- [ ] `update()` (baris 247): tambah guard — kalau `$task->category` existing `SURVEY`/`PSB`, tolak (422) perubahan `category`/`customer_id`/`pop_id`/`village_id` mutlak, apa pun permission user.
- [ ] `update()`: ganti `Rule::enum(TaskType::class)` jadi `Rule::in(TaskType::manualValues())` buat kasus non-Survey/Pemasangan (menutup celah yang lebih longgar dari `store()` saat ini).
- [ ] Fix `selectCustomer()`: auto-fill `pop_id`/`village_id` dari API, field ikut disabled selama `customer_id` terisi.
- [ ] Wajib `customer_id` utk `[DEAC, RELOKASI, CREQ]`, `pop_id`/`village_id` di-override server-side dari data customer.
- [ ] `[OREQ, INFR]` tetap bisa isi POP/Area manual, `customer_id` opsional.
- [ ] Audit data lama: cek ada gak task Survey/Pemasangan yang kadung dibuat manual sebelum validasi ini aktif (harusnya minim/nihil karena `store()` sudah lama terguard, tapi tetap perlu dicek).

**Acceptance Criteria:**
1. Form Tambah Task FOP tidak punya opsi `Survey`/`Pemasangan` — regression-tested (sudah begitu di kode existing).
2. Buka Edit Task existing bertipe Survey/Pemasangan → dropdown category + `customer_id`/`pop_id`/`village_id` full disabled TERLEPAS dari permission `fop_tasks.update_sensitive`, field lain (assignment teknisi, jadwal) tetap bisa diedit.
3. Coba edit `category` task non-Survey/Pemasangan jadi `SURVEY`/`PSB` lewat `update()` → ditolak 422 (celah existing tertutup).
4. Pilih customer di form Deac/Relokasi/C-REQ → POP & Area otomatis terisi dan terkunci, tidak bisa diubah manual.
5. Form O-REQ/INFR REQ tetap bisa isi POP/Area manual tanpa wajib pilih customer.
6. Request langsung ke API (bypass UI, permission `fop_tasks.update_sensitive` sekalipun) untuk ubah Survey/Pemasangan existing tetap ditolak backend.

