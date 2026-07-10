# Analisis Tampilan dan Keselarasan RBAC (Role & Permission)

Dokumen ini berisi analisis mendalam berdasarkan visualisasi nyata halaman Permission Matrix (`halaman-rbac.png`), struktur navigasi (`sidebar1.png`, `sidebar2.png`), serta analisis kompleksitas di tingkat aksi/fitur halaman kerja.

---

## 1. Analisis Ketidakselarasan Visual (Mismatches)

Berdasarkan perbandingan antara tangkapan layar **Sidebar** dan **Halaman Matrix**, ditemukan ketidakselarasan yang sangat membingungkan bagi pengguna/operator:

### A. Pengelompokan Menu vs Fitur Matrix
* **Manajemen User & POP**:
  * **Di Sidebar**: Menu digabung menjadi satu item navigasi bernama **"Manajemen User & POP"** di bawah grup SISTEM.
  * **Di Matrix**: Fitur ini dipecah menjadi dua modul terpisah, yaitu **"POP/Cabang"** dan **"User Management"**. Hal ini membuat operator bingung mencari checkbox yang mengontrol menu tersebut.
* **Master Data**:
  * **Di Sidebar**: Terdapat 6 sub-menu: *Master Data Wilayah*, *Master POP/Cabang*, *Master Distribusi*, *Master Paket Internet*, *Master Status Pelanggan*, dan *Master Timeline SLA*.
  * **Di Matrix**: Hanya ada 3 modul: *POP/Cabang*, *Paket Internet*, dan *Master Timeline SLA*.
  * **Dampak**: Menu **Master Data Wilayah**, **Master Distribusi**, dan **Master Status Pelanggan** tidak dapat dikontrol hak aksesnya melalui matrix karena fiturnya tidak terdaftar secara eksplisit.
* **Laporan**:
  * **Di Sidebar**: Laporan dipecah detail menjadi *Laporan Pelanggan*, *Laporan Tagihan*, *Laporan Pembayaran*, dan *Laporan Import Data*.
  * **Di Matrix**: Hanya ada satu modul tunggal bernama **"Laporan"** dengan aksi *View*, *Export*, dan *Print*. Operator tidak bisa membatasi hak akses staf (misal: hanya boleh melihat Laporan Pelanggan tetapi tidak boleh melihat Laporan Pembayaran).
* **Tagihan**:
  * **Di Sidebar**: Dipecah menjadi *Tagihan Belum Lunas*, *Tagihan Lunas*, dan *Semua Tagihan*.
  * **Di Matrix**: Hanya ada satu modul bernama **"Tagihan"**.
* **Modul Task & Penugasan**:
  * **Di Sidebar**: Menggunakan istilah *FOP Dashboard*, *Task FOP*, *Riwayat Task FOP*, dan *Task Saya*.
  * **Di Matrix**: Menggunakan istilah **"Task FOP"** dan **"Task Management"** yang dipecah lagi ke dalam sub-modul teknis *Aksi FOP (Koordinator Lapangan)* dan *Aksi Teknisi (Eksekutor Lapangan)*.

---

## 2. Analisis Kompleksitas Tingkat Fitur / Aksi Halaman (Feature Actions)

Inkonsistensi dan kesulitan pemahaman menjadi jauh lebih parah ketika masuk ke tingkat fungsional/fitur di dalam halaman kerja (seperti pada modul **Task FOP**):

### A. Kasus: Fitur "Rubah Kategori/Tipe Task"
Di halaman operasional, staf lapangan sering kali perlu mengubah kategori atau tipe pekerjaan. Namun di matrix RBAC, pengaturan ini sangat membingungkan karena:
* **Dualisme / Tumpang Tindih Permission (Overlapping)**: 
  * Terdapat modul **"Task FOP"** dengan checkbox **"Update"**.
  * Terdapat pula modul **"Task Management"** $\rightarrow$ **"Aksi FOP"** dengan checkbox **"Ubah Tipe Task"** dan **"Edit Task"**.
  * **Pertanyaan Operator**: Checkbox mana yang sebenarnya memberikan hak akses untuk merubah kategori? Apakah harus mencentang `Task FOP -> Update`, atau `Aksi FOP -> Ubah Tipe Task`, atau keduanya?
* **Ketidakcocokan Istilah Halaman vs Matrix**:
  * Tombol di halaman web riil menggunakan nama **"Rubah Kategori"**.
  * Di halaman matrix, checkbox dinamakan **"Ubah Tipe Task"**. Perbedaan istilah ini memaksa operator melakukan *trial-and-error* (coba-coba) untuk mengetahui mana checkbox yang tepat.
* **Granularitas Mikro yang Tidak Praktis**:
  * Memisahkan izin edit dasar menjadi checkbox super detail (seperti *Edit Task*, *Ubah Tipe Task*, dan *Ubah Jadwal Task (via Edit)*) tidak efisien secara operasional. 
  * Di lapangan, seorang koordinator yang memiliki hak untuk *Edit Task* umumnya secara otomatis berhak mengubah jadwal dan tipenya. Pemisahan mikro ini hanya menambah beban admin saat mengatur role.

---

## 3. Analisis Kemudahan Pemahaman (Understandability)

Halaman Permission Matrix saat ini **sangat sulit dipahami** oleh operator baru karena beberapa faktor:

### A. Penggunaan Istilah yang Terlalu Teknis (Developer-Centric)
Di dalam modul **Task Management**, terdapat checkbox dengan nama-nama fungsi backend/database, bukan aktivitas bisnis yang dipahami operator:
* *"Pencarian Pelanggan & Cek Konflik (Utility API)"* $\rightarrow$ Operator biasa tidak tahu apa itu "Utility API".
* *"Override Konflik"* $\rightarrow$ Tidak jelas konflik apa yang dimaksud (konflik jadwal, konflik IP, atau konflik wilayah?).
* *"Laporan Nanti"* $\rightarrow$ Istilah ini sangat ambigu untuk dijadikan sebuah nama permission.
* *"View Sensitive"* / *"Update Timer SLA"* $\rightarrow$ Penggunaan simbol peringatan merah (`⚠`) membantu, namun nama parameternya terlalu teknis.

### B. Beban Kognitif yang Terlalu Tinggi (Visual Overload)
* Halaman matrix menampilkan terlalu banyak checkbox secara linear dalam satu halaman panjang (pada gambar terdapat **69 permission aktif**).
* Pengelompokan detail pelanggan pada *Detail Pelanggan* $\rightarrow$ *Identitas Pelanggan*, *Alamat Pelanggan*, *Paket & Layanan*, dsb. terlalu granular. Bagi seorang admin, ia harus mencentang setidaknya 15-20 checkbox hanya untuk membuat satu peran "Customer Service" agar berfungsi normal.

### C. Ambiguitas Hubungan Parent-Child
* Tidak ada hubungan visual yang jelas bahwa sub-fitur di bawah *Detail Pelanggan* (seperti *Survey Pelanggan*, *Pemasangan Pelanggan*) membutuhkan centang pada checkbox *View* di tingkat atasnya (*Detail Pelanggan* & *Pelanggan*). Jika operator baru hanya mencentang di tingkat bawah, halaman web tujuan akan memunculkan error 403 (Unauthorized) tanpa memberi tahu alasannya.

---

## 4. Rekomendasi Solusi & Desain Masa Depan

Untuk membuat sistem RBAC ini **langsung dipahami** oleh operator baru, perubahan non-code berikut sangat disarankan untuk direncanakan:

1. **Penyelarasan Nama (Label Aliasing)**:
   * Nama feature di matrix harus dipetakan 1-to-1 dengan nama menu yang ada di Sidebar.
   * Contoh: Feature `fop_tasks` diganti label tampilannya menjadi **"Pekerjaan FOP / Lapangan"**.
2. **Penyederhanaan Granularitas (Coarse-Grained Permissions)**:
   * Gabungkan permission teknis mikro menjadi satu permission bisnis yang lebih luas.
   * Contoh: Aksi seperti *Mulai Task*, *Selesaikan Task*, *Upload Bukti Foto*, dan *Laporan Nanti* dapat digabung menjadi satu checkbox saja: **"Menjalankan & Melaporkan Tugas Lapangan (Teknisi)"**.
   * Aksi *Edit Task*, *Ubah Tipe Task*, dan *Ubah Jadwal Task* digabung menjadi **"Mengubah Detail & Jadwal Tugas (FOP)"**.
3. **Penyediaan Preset/Template Role**:
   * Sediakan tombol shortcut di bagian atas halaman (misal: "Gunakan Template Owner", "Gunakan Template Teknisi", "Gunakan Template Finance") yang jika diklik akan otomatis mencentang checkbox standar untuk role tersebut.
4. **Alur Otomatis Checkbox (Dependency Chaining)**:
   * Jika checkbox induk (misal *Pelanggan $\rightarrow$ View*) tidak dicentang, maka semua checkbox anak di bawahnya otomatis tidak bisa dicentang (disabled) atau sebaliknya, jika anak dicentang, sistem otomatis mencentang induknya.

---

## 5. Verifikasi Pasca-Implementasi (2026-07-09) — Temuan Sisa dari Screenshot Matrix Terbaru

Setelah S1-S6 (lihat [migrasi-mapping-permission.md](migrasi-mapping-permission.md)) dikerjakan, dicek ulang pakai screenshot nyata halaman matrix (`halaman-matrix-rbac.png`, role Admin, 82 permission aktif) dibanding `sidebar1.png`/`sidebar2.png` yang sama. Sebagian besar temuan bagian 1-3 di atas sudah terbukti selesai (Master Data 3 modul baru muncul, label Task FOP/Task Management udah gak ambigu, Action label kontekstual, dependency chaining jalan). Tapi 3 gap baru ketauan, dan 1 dari 3 itu sebenarnya **temuan lama dari bagian 1.A yang kelewat, gak pernah masuk ke daftar solusi S1-S7**.

### A. "Manajemen User & POP" — mismatch asli belum kesentuh
Ini poin pertama di bagian 1.A dokumen ini, tapi gak pernah diangkat jadi solusi (S1-S7 di dokumen migrasi cuma bahas Master Data/Task/Action label). Sidebar (`sidebar1.png`) tetap gabung jadi 1 menu **"Manajemen User & POP"**. Matrix (`halaman-matrix-rbac.png`) masih pecah jadi 2 modul terpisah berdampingan: **"POP/Cabang"** dan **"User Management"**. Operator yang cari checkbox buat kontrol menu gabungan itu tetap harus nebak "yang mana yang ngatur menu ini — POP/Cabang, User Management, atau dua-duanya?" — persis pertanyaan yang mau dihindari di bagian 1.A.

### B. Grouping visual Master Data gak niru sidebar
Permission-nya udah lengkap (S3 selesai — `master_wilayah`, `master_distribusi`, `master_status_pelanggan` semua ada). Tapi **urutan/posisi** modul di matrix gak niru pengelompokan sidebar. Sidebar (`sidebar2.png`) nampilin 6 item Master Data (Wilayah, POP/Cabang, Distribusi, Paket Internet, Status Pelanggan, Timeline SLA) sebagai **1 grup dropdown yang nyambung**. Di matrix, "POP/Cabang" & "Paket Internet" nongol di urutan atas (dekat Dashboard/User Management — `sort_order` lama), sedangkan "Master Data Wilayah/Distribusi/Status Pelanggan" nongol di **paling bawah** (`sort_order` 12-14, ditambahin belakangan pas S3). Admin harus scroll ke atas DAN ke bawah buat ngatur 1 grup menu yang sama. Akar masalah: `sort_order` cuma nambah nomor baru di belakang, gak reorder biar nyatu sama modul Master Data existing (`pops`, `packages`).

### C. Styling "⚠ sensitif" gak konsisten antar level nesting
Ketemu pas cek screenshot: checkbox **"Update Timer SLA"** (kode: `fop_tasks.update_sensitive`) di baris root "Tiket FOP" render **polos** — gak merah, gak ada ikon ⚠ — padahal ini permission paling beresiko di modul itu (siapa boleh ubah kategori & prioritas tiket). Bandingin sama `customers.detail.devices.update_sensitive` yang muncul jelas merah + ⚠ di level *grandchild* (nested Detail Pelanggan). Root cause: `resources/views/roles/matrix.blade.php` cuma taro logic `$isSensitive = str_contains($perm->code, 'sensitive')` di loop level grandchild (baris ~229) — loop level **root** (tempat `fop_tasks.*` di-render, karena `fop_tasks` itu `FeatureType::ROOT`) gak punya logic itu sama sekali. Jadi permission sensitif yang kebetulan nempel di Feature ROOT (bukan nested/grandchild) kehilangan visual warning-nya.

**Status A-C: sudah dikerjakan** (2026-07-09) — detail perbaikan & mapping ada di [migrasi-mapping-permission.md bagian 7](migrasi-mapping-permission.md#7-temuan-verifikasi-pasca-implementasi-2026-07-09--screenshot-halaman-matrix-rbacpng), S8-S10.

### D. Rename label belum kepasang di data real — ✅ Selesai (2026-07-09)
Tambahan pengamatan (bukan bug desain): label `fop_tasks.update_sensitive` di screenshot masih nampilin nama lama "Update Timer SLA", bukan "Ubah Kategori & Prioritas Tiket" yang udah di-set di migrasi (`2026_07_09_000000_migrate_rbac_permissions.php`, step 3).

**Temuan pas dicek:** migrasi ini sebenarnya sudah tercatat "Ran" (`migrate:status`, batch 1) sejak database di-fresh — tapi kolom `name` buat `fop_tasks.update_sensitive`, `customers.detail.devices.update_sensitive`, `customers.detail.devices.view_sensitive` tetap `null` (`updated_at` = `created_at`, gak pernah ke-update sama sekali di seluruh tabel `permissions`). Artinya step 3 (rename) di migrasi itu gak pernah benar-benar mengubah data di run yang tercatat itu, walau kodenya sendiri sudah benar. Root cause exact-nya gak ketemu (kemungkinan versi file yang benar-benar dieksekusi saat itu beda dari versi final yang sekarang ada di working tree) — investigasi lebih detail bisa disambung kalau kejadian lagi pas migrasi ke staging/prod.

**Fix langsung:** rename dijalankan manual lewat `php artisan tinker` (logic identik sama step 3 migrasi) buat 3 kode di atas — dikonfirmasi sekarang tampil benar di database (`name` udah keisi ketiga label baru). Lihat detail di [migrasi-mapping-permission.md bagian 7](migrasi-mapping-permission.md#7-temuan-verifikasi-pasca-implementasi-2026-07-09--screenshot-halaman-matrix-rbacpng).

### E. Deskripsi fitur di matrix (`$descriptions` array) — 3 salah, 1 minor — ✅ Selesai (2026-07-09)

Setelah bagian A-D kelar, tampilan matrix ditambah 1 baris deskripsi per fitur (array `$descriptions` di `matrix.blade.php`, di luar cakupan S1-S10). Dicek satu-satu ke kode/dokumentasi modul terkait, ketemu ketidakakuratan:

* **`dashboard`** — klaim "grafik billing" padahal `DashboardController.php`/`dashboard.blade.php` gak ada chart/canvas sama sekali, cuma stat card angka (KPI).
* **`sla_timeline`** — klaim "batas waktu pengerjaan untuk **penyelesaian** tiket", padahal `docs/master/sla-timeline/README.md` eksplisit bilang sebaliknya: *"batas waktu wajib **mulai** ditangani... bukan durasi pengerjaan teknisi di lapangan"*. Deskripsi lama kebalik dari konsep aslinya.
* **`customers.detail.packages`** — klaim scope "status aktivasi layanan", padahal action `ACTIVATE` di `config/rbac.php` cuma ada di `customers.detail.installation` (`VIEW/UPDATE/VALIDATE/ACTIVATE`). Fitur `customers.detail.packages` sendiri cuma `VIEW/UPDATE` — nyerempet klaim wewenang fitur tetangga.
* **`invoices`** (minor) — nyebut "pembatalan" padahal `config/rbac.php` gak ada action `CANCEL` buat `invoices` (cuma VIEW/CREATE/UPDATE/DELETE/PRINT).

**Fix:** keempat teks deskripsi direvisi di `matrix.blade.php` biar sesuai scope permission & dokumentasi modul yang sebenarnya. Detail per baris ada di [migrasi-mapping-permission.md bagian 7](migrasi-mapping-permission.md#7-temuan-verifikasi-pasca-implementasi-2026-07-09--screenshot-halaman-matrix-rbacpng), S11.

### F. Bug fungsional S6 (dependency chaining) ketemu pas verifikasi runtime — ✅ Selesai (2026-07-09)

Dicek langsung lewat HTTP request beneran (bukan cuma baca kode) — submit form matrix cuma centang `task.manage`, ternyata `task.view.all` gak ikut ke-grant otomatis, padahal itu tepat kasus yang S6 klaim udah ditangani. Root cause: logic auto-grant cuma jalan dari feature **parent**, gak pernah cek feature **milik permission itu sendiri** — jadi permission yang sibling-nya (bukan ancestor-nya) yang punya "view", kayak semua permission di `tasks.fop`/`tasks.teknisi`, gak pernah ke-cover. Gap yang sama juga nyerempet mini-feature nested (`customers.detail.survey.update` tanpa `.view`) — artinya S6 versi awal cuma nutup separuh dari komplain 403-tanpa-alasan di poin 3.C.

**Fix + verifikasi ulang** (5 skenario HTTP nyata: sibling-view, ancestor-chain, sibling+ancestor sekaligus, sanity-check fitur flat, permission id invalid) — semua lolos. Detail lengkap di [migrasi-mapping-permission.md bagian 8](migrasi-mapping-permission.md#8-bug-ditemukan-saat-verifikasi-runtime-2026-07-09--s6-sibling-view-gap).

**Update — root cause ketemu & fix permanen (✅ Selesai):** label rename (poin D) sempat balik `null` lagi setelah database di-refresh (`migrate:fresh --seed`). Ditelusuri ulang: penyebabnya migration one-off `2026_07_09_000000...php` step 1 manggil `PermissionGeneratorService::generate()` di fase *migrations* — padahal tabel `actions` baru keisi belakangan di fase `--seed` (`ActionSeeder`). Jadi permission `fop_tasks.update_sensitive` dkk belum ada saat step 3 (rename) jalan, `update()` kena 0 baris. Fix dipindah ke `config/rbac.php` (`permission_name_overrides`) + `PermissionGeneratorService::generate()` (set label pas create, backfill kalau ketemu masih null) — jadi otomatis "self-healing" tiap kali `generate()` dipanggil, gak peduli urutan migration/seeder. Detail di [migrasi-mapping-permission.md bagian 9](migrasi-mapping-permission.md#9-anomali-rename-label-balik-null--root-cause-ketemu--fix-permanen-2026-07-09).
