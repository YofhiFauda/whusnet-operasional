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
