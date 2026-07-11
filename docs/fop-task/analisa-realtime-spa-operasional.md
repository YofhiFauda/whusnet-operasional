# Analisa Implementasi Real-Time (SPA-Like) pada Aplikasi Whusnet Operasional

Dokumen ini mencatat analisis arsitektur, metode implementasi real-time tanpa reload/polling, serta daftar halaman-halaman operasional yang sangat krusial untuk ditingkatkan menjadi real-time dengan pengalaman pengguna mirip Single Page Application (SPA).

---

## 1. Fondasi Arsitektur Real-Time Existing
Aplikasi saat ini telah dilengkapi dengan infrastruktur modern yang siap mendukung pembaruan data real-time:
*   **Backend:** [Laravel Reverb](https://laravel.com/docs/11.x/reverb) (WebSocket Server bawaan Laravel) yang dikombinasikan dengan Broadcast Event Laravel (`ShouldBroadcast` / `ShouldBroadcastNow`).
*   **Frontend:** [Laravel Echo](https://laravel.com/docs/11.x/broadcasting#receiving-broadcasts) terkonfigurasi secara dinamis di [echo.js](file:///d:/Whusnet/whusnet-operasional/resources/js/echo.js) dan diintegrasikan dengan state reactive [Alpine.js](https://alpinejs.dev/).

---

## 2. Realisasi Real-Time pada Halaman Task FOP
Pada halaman [Task FOP](file:///d:/Whusnet/whusnet-operasional/resources/views/fop_tasks/index.blade.php), pembaruan data (ubah status, prioritas, switch teknisi, dll.) masih memicu `window.location.reload()`.

Untuk mengubah perilaku ini menjadi **SPA-Like**, diterapkan metode **Dynamic DOM Swapping (SPA-Lite)**:

### A. Alur Event (Backend)
1. Buat event penyiaran baru `FopTaskChanged` yang mengimplementasikan `ShouldBroadcastNow`.
2. Siarkan perubahan ke Private Channel `fop.{pop_id}` agar mematuhi aturan pembatasan wilayah data (POP Scope) sesuai kebijakan RBAC proyek.
3. Trigger event tersebut di [FopTaskController.php](file:///d:/Whusnet/whusnet-operasional/app/Http/Controllers/FopTaskController.php) pada akhir method `store()`, `update()`, `destroy()`, `assignToTeam()`, dan `switchTechnician()`.

### B. Alur Sinkronisasi (Frontend)
1. Di sisi client, Alpine.js mendaftar listener ke Laravel Echo saat halaman di-load:
   ```javascript
   window.Echo.private(`fop.${popId}`).listen('FopTaskChanged', () => this.reloadTable());
   ```
2. Fungsi `reloadTable()` melakukan request `fetch(window.location.href)` secara asinkron (di belakang layar), lalu menukar elemen `<tbody>` tabel, paginasi, dan teks counter data tanpa perlu menyegarkan (refresh) halaman seutuhnya.
3. Ganti panggilan `window.location.reload()` lokal pasca-aksi AJAX dengan pemanggilan langsung fungsi `this.reloadTable()` untuk pembaruan instan.

*Keuntungan Utama:* Menjaga seluruh logic otentikasi Blade, parameter query URL (filter, pencarian), dan paginasi tetap aman diatur dari server tanpa merusak state Alpine.js di client.

---

## 3. Kandidat Halaman Lain yang Membutuhkan Real-Time (SPA-Like)

Berikut adalah daftar halaman operasional selain Task FOP yang direkomendasikan untuk beralih ke arsitektur real-time tanpa polling:

### A. Notifikasi In-App & Lonceng Notifikasi
*   **Target File:** [notification-dropdown.blade.php](file:///d:/Whusnet/whusnet-operasional/resources/views/components/notification-dropdown.blade.php)
*   **Urgensi Real-Time:** Pengguna harus segera mengetahui kejadian penting (misal: pengaduan baru, tugas mendesak, atau konfirmasi keuangan) tanpa perlu me-refresh halaman web secara berkala.
*   **Pengalaman SPA:** Angka badge notifikasi langsung bertambah secara instan (`+1`) dan memicu visual pop-up **Toast Notification** di sudut layar.

### B. Daftar Tugas Kerja Teknisi (Tasks Saya)
*   **Target File:** [own.blade.php](file:///d:/Whusnet/whusnet-operasional/resources/views/tasks/own.blade.php) & [own-card.blade.php](file:///d:/Whusnet/whusnet-operasional/resources/views/tasks/partials/own-card.blade.php)
*   **Urgensi Real-Time:** Teknisi di lapangan umumnya mengakses aplikasi melalui browser ponsel cerdas. Melakukan refresh manual pada perangkat seluler sangat menguras waktu dan kuota data.
*   **Pengalaman SPA:** Begitu dispatcher/FOP memasukkan tugas baru ke team, tugas tersebut langsung muncul di layar daftar kerja teknisi secara instan. Sebaliknya, jika ada pembatalan tugas atau reschedule, tugas tersebut langsung menghilang dari daftar dengan transisi animasi halus.

### C. Dashboard Pemantauan SLA & Statistik Utama
*   **Target File:** [dashboard.blade.php](file:///d:/Whusnet/whusnet-operasional/resources/views/dashboard.blade.php) (Utama) & [fop-dashboard.md](file:///d:/Whusnet/whusnet-operasional/docs/fop-task/fop-dashboard.md)
*   **Urgensi Real-Time:** Dashboard pimpinan, manajemen, atau ruang kontrol NOC membutuhkan akurasi data detik demi detik untuk memantau beban kerja teknisi dan pencapaian SLA.
*   **Pengalaman SPA:** Angka statistik "Sedang Berjalan", "Selesai Hari Ini", dan diagram progress langsung ter-update otomatis ketika status kerja di lapangan berubah.

### D. Konfirmasi Pembayaran Kasir & Status Tagihan
*   **Target Folder:** [invoices](file:///d:/Whusnet/whusnet-operasional/resources/views/invoices) & [payments](file:///d:/Whusnet/whusnet-operasional/resources/views/payments)
*   **Urgensi Real-Time:** Mencegah terjadinya pembagian tagihan ganda atau penagihan berulang oleh kasir pusat apabila pelanggan sudah melakukan pembayaran di mini POP/cabang pembantu.
*   **Pengalaman SPA:** Ketika kasir cabang mencatat pembayaran, status tagihan (invoice) pelanggan bersangkutan di monitor kasir pusat langsung berubah dari **Unpaid (Merah)** menjadi **Paid (Hijau)** saat itu juga tanpa memuat ulang halaman.

### E. Logs Audit & Log Aktivitas Sistem
*   **Target Folder:** [audit-logs](file:///d:/Whusnet/whusnet-operasional/resources/views/audit-logs)
*   **Urgensi Real-Time:** Berguna bagi admin keamanan dan owner untuk melacak alur mutasi data master secara aktual.
*   **Pengalaman SPA:** Setiap ada perubahan data pelanggan, data audit log terbaru langsung meluncur ke tabel pemantauan seperti streaming log konsol.

### F. Siklus Hidup Pelanggan & Kelengkapan Validasi Data
*   **Target Folder:** [customers](file:///d:/Whusnet/whusnet-operasional/resources/views/customers)
*   **Urgensi Real-Time:** Billing tidak boleh diaktifkan jika data pelanggan belum lengkap.
*   **Pengalaman SPA:** Di layar Admin Aktivasi, data pelanggan yang awalnya berstatus `Draft` atau `Perlu Dilengkapi` akan langsung berubah status kelengkapannya menjadi `Lengkap (Siap Billing)` begitu teknisi menyelesaikan survei dan koordinat GPS tersinkron secara real-time dari lapangan.

---

## 4. Keuntungan Penerapan Pola Real-Time
1.  **Penghematan Resource Server & Bandwidth:** Mengurangi overhead database dan jaringan dibandingkan metode *polling* (request AJAX berulang setiap sekian detik).
2.  **Meningkatkan Kepuasan Pengguna (Wow Factor):** Aplikasi terasa responsif, modern, dan bernilai premium.
3.  **Konsistensi Data Multi-User:** Meminimalisir kesalahan operasional akibat data yang basi (stale data) di layar admin yang berbeda.
