# Requirements Document

## Introduction

Fitur **Task Scheduler — Penjadwalan Teknisi** adalah halaman khusus untuk FOP (Field Operation Planner) di sistem WHUSNET yang menampilkan antrean task yang belum di-assign teknisi. Dari halaman ini FOP dapat: (1) melihat semua task yang masuk ke antrean penjadwalan, (2) assign 1–3 teknisi ke sebuah task, (3) set jadwal (tanggal + jam), dan (4) menyimpan setelah validasi konflik jadwal teknisi lolos.

Ada dua sumber task yang masuk ke antrean ini:
- **Task manual** — FOP membuat task baru via form "Buat Task" (sudah ada) tanpa assign teknisi → task masuk antrean penjadwalan.
- **Task dari alur Survey/Pemasangan** — setelah FOP klik "Proses ke TIM" di slide-over FOP Dashboard, sistem otomatis membuat task pemasangan yang *sudah ter-assign teknisi* sekaligus — sehingga task ini **tidak** masuk antrean penjadwalan.

Fitur ini melengkapi sistem Task Management yang sudah ada (Sprint 8.2 selesai) tanpa mengubah service layer `TaskService`, `TaskController`, atau schema database yang sudah berjalan.

---

## Glossary

- **Task_Scheduler**: Halaman penjadwalan teknisi — daftar task yang belum di-assign tim teknisi.
- **Antrean_Task**: Kumpulan task dengan status `terjadwal` atau `pending` yang belum memiliki anggota `task_teams`.
- **FOP**: Field Operation Planner — role yang berwenang mengatur penjadwalan dan penugasan teknisi.
- **Teknisi**: Role pelaksana lapangan yang akan di-assign ke task.
- **Task_Team**: Relasi antara task dan user teknisi, tersimpan di tabel `task_teams` (kolom: `task_id`, `user_id`, `role_in_task`: `lead`|`teknisi`).
- **Konflik_Jadwal**: Kondisi di mana seorang teknisi sudah memiliki task lain yang waktu pengerjaannya (berdasarkan `scheduled_at` + `sla_minutes`) tumpang tindih dengan jadwal yang hendak disimpan.
- **Assign_Panel**: Panel slide-over atau modal inline di dalam halaman Task Scheduler yang digunakan FOP untuk memilih teknisi dan set jadwal sebelum menyimpan.
- **POP_Scope**: Pembatasan data berdasarkan POP — FOP hanya boleh melihat task dan teknisi dari POP yang termasuk dalam scope aksesnya.
- **Task_Manual**: Task yang dibuat FOP via form `tasks.create` tanpa mengisi `team_member_ids`, sehingga `task_teams` kosong → masuk antrean.
- **Task_Pemasangan_Proses_TIM**: Task pemasangan yang dibuat otomatis oleh sistem saat FOP "Proses ke TIM" — sudah memiliki data `task_teams` → tidak masuk antrean.

---

## Requirements

---

### Requirement 1: Halaman Antrean Task (Task Scheduler)

**User Story:** Sebagai FOP, saya ingin melihat semua task yang belum di-assign teknisi dalam satu halaman terpusat, agar saya bisa dengan cepat mengetahui task mana yang perlu segera dijadwalkan.

#### Acceptance Criteria

1. THE Task_Scheduler SHALL menampilkan daftar task yang memenuhi semua kondisi berikut: task tidak memiliki entri pada tabel `task_teams` (belum ada teknisi yang di-assign), dan status task adalah `terjadwal` atau `pending`.
2. WHEN FOP mengakses halaman Task Scheduler, THE Task_Scheduler SHALL menerapkan POP_Scope secara otomatis sehingga hanya task dari POP yang termasuk dalam scope FOP yang login yang tampil.
3. THE Task_Scheduler SHALL menampilkan informasi berikut untuk setiap task dalam antrean: nomor task (`task_number`), judul task (`title`), tipe task (`task_type`), nama pelanggan terkait (jika ada), nama POP, status task, tanggal dan jam jadwal (`scheduled_at` jika sudah diisi), dan tanggal task dibuat.
4. WHEN task dalam antrean belum memiliki jadwal (`scheduled_at` null), THE Task_Scheduler SHALL menampilkan label "Belum Dijadwalkan" pada kolom jadwal task tersebut.
5. THE Task_Scheduler SHALL menampilkan jumlah total task dalam antrean sebagai ringkasan di bagian atas halaman.
6. THE Task_Scheduler SHALL dapat diakses hanya oleh user yang memiliki permission `task.view.all`.

---

### Requirement 2: Filter dan Pencarian Antrean

**User Story:** Sebagai FOP, saya ingin memfilter dan mencari task di antrean berdasarkan kriteria tertentu, agar saya tidak kewalahan saat antrean panjang.

#### Acceptance Criteria

1. THE Task_Scheduler SHALL menyediakan filter berdasarkan tipe task (`task_type`: semua, survey, pemasangan, maintenance, lainnya).
2. THE Task_Scheduler SHALL menyediakan filter berdasarkan POP (dropdown POP yang ada dalam scope FOP).
3. THE Task_Scheduler SHALL menyediakan filter berdasarkan status task (`terjadwal`, `pending`).
4. WHEN FOP mengetik kata kunci pada field pencarian, THE Task_Scheduler SHALL memfilter daftar task secara real-time berdasarkan `task_number`, `title`, atau nama pelanggan yang mengandung kata kunci tersebut.
5. WHEN FOP mengubah nilai filter, THE Task_Scheduler SHALL memperbarui daftar antrean tanpa reload halaman penuh.

---

### Requirement 3: Assign Teknisi ke Task

**User Story:** Sebagai FOP, saya ingin menugaskan 1 hingga 3 teknisi ke sebuah task langsung dari halaman antrean, agar alur penjadwalan tidak memerlukan navigasi ke halaman lain.

#### Acceptance Criteria

1. WHEN FOP mengklik tombol "Assign & Jadwalkan" pada sebuah task di antrean, THE Task_Scheduler SHALL membuka Assign_Panel berupa slide-over atau modal inline tanpa berpindah halaman.
2. THE Assign_Panel SHALL menampilkan informasi ringkas task yang dipilih: nomor task, judul, tipe, nama pelanggan (jika ada), dan POP.
3. THE Assign_Panel SHALL menyediakan dropdown multi-select untuk memilih teknisi, dengan daftar teknisi yang dibatasi hanya pada teknisi dalam POP_Scope FOP yang login.
4. THE Assign_Panel SHALL membatasi jumlah teknisi yang dapat dipilih minimum 1 orang dan maksimum 3 orang.
5. THE Assign_Panel SHALL menetapkan teknisi pertama yang dipilih secara otomatis sebagai `lead` dan teknisi berikutnya sebagai `teknisi` pada kolom `role_in_task` di tabel `task_teams`.
6. WHEN FOP telah memilih teknisi, THE Assign_Panel SHALL menampilkan nama dan inisial setiap teknisi yang dipilih sebagai chip/tag yang dapat dihapus.
7. THE Task_Scheduler SHALL memerlukan permission `task.assign.team` untuk mengakses fitur assign teknisi.

---

### Requirement 4: Set Jadwal Task

**User Story:** Sebagai FOP, saya ingin menentukan tanggal dan jam jadwal task dari halaman yang sama dengan assign teknisi, agar semua informasi penugasan dapat dikonfirmasi sekaligus dalam satu aksi.

#### Acceptance Criteria

1. THE Assign_Panel SHALL menyediakan input tanggal (`date picker`) dan jam (`time picker`) untuk menetapkan nilai `scheduled_at` task.
2. IF jadwal task sudah terisi sebelumnya (`scheduled_at` tidak null), THEN THE Assign_Panel SHALL menampilkan nilai jadwal yang sudah ada sebagai nilai awal pada input tanggal dan jam.
3. THE Assign_Panel SHALL memerlukan input tanggal dan jam terisi sebelum tombol simpan dapat diklik (jadwal wajib diisi saat assign).
4. THE Task_Scheduler SHALL memerlukan permission `task.schedule` untuk menyimpan perubahan jadwal task.

---

### Requirement 5: Validasi Konflik Jadwal

**User Story:** Sebagai FOP, saya ingin mengetahui apakah ada konflik jadwal teknisi sebelum menyimpan penugasan, agar tidak terjadi satu teknisi dijadwalkan untuk dua task pada waktu yang sama.

#### Acceptance Criteria

1. WHEN FOP telah memilih teknisi dan mengisi jadwal pada Assign_Panel, THE Assign_Panel SHALL memanggil endpoint `tasks.check-conflict` secara otomatis untuk mendeteksi konflik jadwal menggunakan logika `TaskService::detectConflicts()` yang sudah ada.
2. IF endpoint `tasks.check-conflict` mengembalikan konflik pada satu atau lebih teknisi, THEN THE Assign_Panel SHALL menampilkan peringatan berisi nama-nama teknisi yang konflik beserta detail task yang bertabrakan (nomor task dan jadwalnya).
3. WHILE peringatan konflik ditampilkan dan FOP belum mengambil tindakan, THE Assign_Panel SHALL menonaktifkan tombol "Simpan Penugasan" sehingga data tidak dapat disimpan dalam kondisi konflik.
4. WHEN FOP memilih untuk mengganti teknisi yang konflik dengan teknisi lain yang tidak konflik, THE Assign_Panel SHALL menjalankan ulang pengecekan konflik secara otomatis.
5. IF FOP memiliki permission `task.conflict.override` dan memilih opsi "Abaikan Konflik", THEN THE Assign_Panel SHALL mengizinkan penyimpanan dengan mencatat flag `conflict_override = true` pada task.
6. IF FOP tidak memiliki permission `task.conflict.override`, THEN THE Assign_Panel SHALL menyembunyikan opsi "Abaikan Konflik" sehingga FOP wajib mengganti teknisi yang konflik sebelum bisa menyimpan.

---

### Requirement 6: Simpan Penugasan

**User Story:** Sebagai FOP, saya ingin menyimpan hasil assign teknisi dan jadwal dalam satu aksi, agar task langsung keluar dari antrean dan teknisi mendapat notifikasi.

#### Acceptance Criteria

1. WHEN FOP mengklik "Simpan Penugasan" pada Assign_Panel dengan teknisi valid dan jadwal terisi tanpa konflik yang belum diselesaikan, THE Task_Scheduler SHALL menyimpan data teknisi ke tabel `task_teams` dan memperbarui `scheduled_at` pada tabel `tasks` menggunakan `TaskService::update()` yang sudah ada.
2. WHEN penyimpanan berhasil, THE Task_Scheduler SHALL menghapus task tersebut dari daftar antrean secara langsung tanpa reload halaman penuh.
3. WHEN penyimpanan berhasil, THE Task_Scheduler SHALL mengirimkan notifikasi ke semua teknisi yang di-assign melalui mekanisme `SendTaskNotificationJob` yang sudah ada.
4. WHEN penyimpanan berhasil, THE Task_Scheduler SHALL menampilkan pesan sukses berisi nomor task yang baru saja dijadwalkan.
5. IF penyimpanan gagal karena error server, THEN THE Task_Scheduler SHALL menampilkan pesan error yang deskriptif dan mempertahankan data yang sudah diisi FOP di Assign_Panel agar tidak perlu mengulang dari awal.
6. WHEN penyimpanan berhasil, THE Task_Scheduler SHALL memperbarui ringkasan jumlah task di antrean.

---

### Requirement 7: Sumber Task — Task Manual Tanpa Assign

**User Story:** Sebagai FOP, saya ingin task yang saya buat secara manual tanpa assign teknisi langsung masuk ke antrean penjadwalan, agar tidak ada task yang terlewat tanpa teknisi.

#### Acceptance Criteria

1. WHEN FOP menyimpan task baru via form `tasks.create` tanpa mengisi `team_member_ids` (field tim dikosongkan), THE Task_Scheduler SHALL memasukkan task tersebut ke dalam Antrean_Task.
2. THE Task_Manual SHALL memiliki kondisi `task_teams` kosong (tidak ada entri) sebagai penanda bahwa task belum di-assign teknisi.
3. WHEN task manual berhasil disimpan tanpa tim, THE Task_Scheduler SHALL menampilkan task tersebut di antrean pada saat FOP berikutnya membuka atau me-refresh halaman Task Scheduler.

---

### Requirement 8: Sumber Task — Task Pemasangan dari "Proses ke TIM" Tidak Masuk Antrean

**User Story:** Sebagai FOP, saya ingin task pemasangan yang dibuat otomatis dari alur "Proses ke TIM" tidak muncul di antrean penjadwalan, karena teknisi sudah di-assign pada saat proses tersebut.

#### Acceptance Criteria

1. WHEN sistem membuat Task_Pemasangan_Proses_TIM melalui `FopDashboardController::processToTim()`, THE Task_Scheduler SHALL tidak memasukkan task ini ke dalam Antrean_Task karena task tersebut langsung memiliki entri pada tabel `task_teams`.
2. THE Task_Scheduler SHALL menggunakan kondisi "tidak ada entri di `task_teams`" sebagai satu-satunya penentu apakah sebuah task masuk antrean, sehingga task yang sudah memiliki teknisi (termasuk Task_Pemasangan_Proses_TIM) otomatis tidak tampil di antrean.

---

### Requirement 9: Validasi Batas Kapasitas Tim

**User Story:** Sebagai FOP, saya ingin sistem mencegah saya menjadwalkan tim teknisi yang sudah terlalu banyak task pada hari yang sama, agar beban kerja tim tetap wajar.

#### Acceptance Criteria

1. WHEN FOP mencoba menyimpan penugasan pada tanggal tertentu, THE Assign_Panel SHALL memvalidasi batas 4 task aktif per tim per hari menggunakan logika `TaskService::teamCanAddTask()` yang sudah ada.
2. IF tim teknisi yang dipilih sudah memiliki 4 task aktif (`terjadwal` atau `in_progress`) pada tanggal yang dipilih, THEN THE Assign_Panel SHALL menampilkan peringatan dan mencegah penyimpanan.

---

### Requirement 10: Keamanan dan POP Scope

**User Story:** Sebagai administrator sistem, saya ingin memastikan FOP hanya dapat melihat dan men-assign task serta teknisi dalam cakupan POP-nya, agar tidak ada kebocoran data antar cabang.

#### Acceptance Criteria

1. THE Task_Scheduler SHALL membatasi daftar task yang tampil di antrean hanya pada task yang `pop_id`-nya termasuk dalam POP scope FOP yang sedang login, menggunakan scope `applyUserScope()` pada model Task yang sudah ada.
2. THE Assign_Panel SHALL membatasi daftar teknisi yang dapat dipilih hanya pada user dengan role `teknisi` yang berada dalam POP scope FOP yang sedang login.
3. IF FOP mencoba mengakses data task yang `pop_id`-nya berada di luar scope aksesnya melalui manipulasi request langsung, THEN THE Task_Scheduler SHALL mengembalikan response HTTP 403.
4. THE Task_Scheduler SHALL mencatat setiap aksi assign teknisi dan perubahan jadwal ke audit log sistem dengan menyimpan `user_id` FOP, `model_type` Task, `model_id`, `before_values`, dan `after_values`.

---

### Requirement 11: Notifikasi Real-Time ke Teknisi

**User Story:** Sebagai teknisi, saya ingin mendapat notifikasi segera saat FOP menjadwalkan saya ke sebuah task dari halaman Task Scheduler, agar saya bisa langsung melihat jadwal baru di dashboard saya.

#### Acceptance Criteria

1. WHEN FOP berhasil menyimpan penugasan dari Task_Scheduler, THE Task_Scheduler SHALL memicu broadcast event `TaskScheduled` ke channel `teknisi.{user_id}` untuk setiap teknisi yang di-assign, menggunakan mekanisme Reverb yang sudah ada.
2. WHEN event `TaskScheduled` diterima di Teknisi Dashboard, THE Teknisi Dashboard SHALL menampilkan banner notifikasi "Task baru dijadwalkan: [Judul Task] — [Tanggal Jadwal]" sesuai dengan mekanisme yang sudah diimplementasikan di Sprint 8.2-T010.
