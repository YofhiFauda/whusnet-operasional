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

### Skenario C: Task Single-Teknisi ($1$ Teknisi) $\rightarrow$ Manual Drop-In
Jika sebuah task hanya membutuhkan 1 teknisi (misal: Task 3 ditugaskan hanya ke **Dedi**):
* **Default:** Task tersebut berdiri sendiri (solo task) dengan `team_id = null` (atau membentuk solo team).
* **Fleksibilitas FOP (Drop-In):** FOP dapat memilih untuk memasukkan Task 3 ke dalam salah satu Team yang sudah terbentuk pada tanggal tersebut (misal Team `"Andi & Budi"`).
* **Hasil:** Ketika FOP memasukkan Task 3 ke Team tersebut secara manual:
  1. `team_id` pada Task 3 diubah ke ID Team `"Andi & Budi"`.
  2. Roster anggota Team tersebut ditambahkan **Dedi** (Roster final menjadi: `[Andi, Budi, Dedi]`).
  3. Nama Team ter-update dinamis menjadi `"Tim Andi & Budi & Dedi"`.

---

## 3. Penanggung Jawab Task (PIC / Leader)

Sesuai requirement *"Terdapat penanggung jawab pada setiap task"*:
1. Kita akan menambahkan kolom `pic_id` (foreign key ke tabel `users`) langsung pada tabel `fop_tasks`.
2. Saat FOP memilih teknisi di form, FOP wajib menandai salah satu teknisi pilihan tersebut sebagai PIC (misalnya dengan mengeklik ikon ⭐/👑 di samping nama teknisi terpilih di UI).
3. Di backend, data PIC ini disinkronkan ke tabel `task_teams` (tabel eksekusi teknisi) dengan flag `role_in_task = 'lead'`, sementara anggota tim lainnya disimpan dengan `role_in_task = 'teknisi'`.

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

## 8. Perubahan Tombol "Lapor Nanti" → "Pending"

**Konteks:** Di halaman **Detail Task**, teknisi menekan salah satu tombol laporan sesuai tipe task:
- **Laporan Survey**
- **Laporan Pemasangan**
- **Laporan Maintenance**
- Laporan tipe lain yang terkait dengan sistem ticketing

Setelah tombol laporan tersebut ditekan, muncul dua pilihan aksi:

```
[Lapor Sekarang]   [Lapor Nanti]
```

**Perubahan:** Tombol **"Lapor Nanti"** diganti namanya menjadi **"Pending"** agar lebih eksplisit dan tidak ambigu.

**Alur setelah tombol laporan ditekan:**
```
Teknisi tekan [Laporan Survey / Pemasangan / Maintenance / ...]
         │
         ▼
  Muncul dua tombol:
  ┌──────────────────┐   ┌──────────────────┐
  │  Lapor Sekarang  │   │     Pending      │  ← (sebelumnya: "Lapor Nanti")
  └────────┬─────────┘   └────────┬─────────┘
           │                      │
           ▼                      ▼
    Form laporan            Modal alasan
    langsung muncul         pending wajib isi
    (isi & submit)          → task masuk status Pending
```

**Detail perubahan tombol Pending:**
- Warna tombol: amber/kuning (konsisten dengan status `Pending` di seluruh sistem)
- Saat diklik: muncul modal kecil, alasan wajib diisi sebelum bisa submit
- Task masuk status `Pending` di sisi FopTask maupun Task eksekusi teknisi

---

## 9. Request Tanggal Pemasangan & Antrian Otomatis

**Konteks:** Saat FOP melakukan survey, FOP bisa request tanggal kapan pemasangan dilakukan. Field yang digunakan: `client_request_date` (sudah ada di schema).

**Logika antrian:**
- Task dengan `task_date` di masa mendatang → tampil di **bawah** daftar (section "Upcoming / Terjadwal")
- Ketika `task_date` = hari ini atau sudah lewat → **otomatis naik ke atas** (prioritas tinggi, badge "JADWAL HARI INI")

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
| Teknisi klik "Pending" | `Pending` |
| Teknisi submit laporan | `Proses` + badge "Perlu Review" |
| FOP approve laporan | `Selesai` |
| FOP reject laporan | `Proses` kembali |
| FOP cancel | `Cancel` |

**Perubahan:** Status diupdate **otomatis** dari perubahan status `Task` eksekusi yang terkait — sinkronisasi dua arah antara `FopTask` dan `Task`.

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
- Tombol "Lapor Sekarang" & "Pending"

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
| Tambah PIC per task | `fop_tasks` | `pic_id` (FK → users) |
| Tandai role teknisi di task | `fop_task_user` | `role_in_task` enum (lead/teknisi) |
| Rekap laporan per task | `task_reports` *(tabel baru)* | `tools_used`, `total_duration_minutes`, `sla_target_minutes`, `sla_status`, `sla_overrun_minutes` |

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
    ├─ Tandai PIC (⭐)
    └─ Simpan
         │
         ▼
    Sistem otomatis:
    • Rebuild team berdasarkan overlap teknisi hari itu
    • Update nama team dinamis
    • Buat Task eksekusi untuk teknisi
    • Kirim notifikasi ke teknisi
         │
         ▼
    Dashboard FOP:
    • Tampilkan team card (otomatis, tanpa buat manual)
    • FOP bisa drag task antar team (dengan validasi teknisi tujuan)
    • FOP bisa switch teknisi per task (dengan konflik check)
         │
         ▼
    Teknisi /tasks-saya:
    • Lihat list task (minimal — belum ada detail)
    • Klik Mulai → timer jalan, detail + maps muncul
    • Selesai kerja → isi laporan (alat, catatan, foto)
    • Klik "Lapor Sekarang" atau "Pending"
         │
         ▼
    FOP Review:
    • Lihat rekap laporan teknisi (alat, durasi, SLA)
    • Approve / Reject / Pending
         │
         ▼
    Sistem catat:
    • Audit Log
    • SLA tracking per teknisi
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

Analisa solusi dan dampak per poin kebutuhan (versi baru, 11 poin):

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

