# Peta Jalan Infrastruktur (Post-MVP Infrastructure)

Dokumen ini berisi analisis arsitektur infrastruktur untuk aplikasi **Website Billing ISP Berbasis Master Data Pelanggan (WHUSNET)**. 
Saat ini, proyek berada pada fase **MVP (Minimum Viable Product)** yang berfokus pada akurasi data relasional dasar. Oleh karena itu, penggunaan teknologi *Advanced* ditunda agar tidak menyebabkan *overhead* dan kerumitan server di awal.

Berikut adalah panduan kapan dan di mana layanan **Redis**, **Laravel Horizon**, dan **Laravel Reverb** akan digunakan di masa depan (Fase Post-MVP / Otomatisasi Skala Besar).

---

## 1. REDIS (In-Memory Data Store)

**Status Saat Ini:** Belum diperlukan. Cache masih menggunakan sistem *File-based* dan Database.
**Kapan Mulai Digunakan?** Ketika jumlah transaksi harian dan beban baca/tulis (*read/write*) aplikasi mulai tinggi sehingga *database* mulai melambat.

**Fungsi di Fase Post-MVP:**
- **Caching Super Cepat (RBAC):** `EffectiveAccessService` yang bertugas membaca hak akses pengguna akan dipindahkan dari *File Cache* ke *Redis*. Mengingat hak akses dicek pada setiap HTTP Request (melalui *Middleware*), membaca dari RAM/Redis menjamin kecepatan hampir instan (~0 ms).
- **Queue Driver Utama:** Redis adalah *backend* terbaik dan tercepat untuk Laravel Queue. Saat kita mulai memiliki ribuan pekerjaan latar belakang (seperti generate tagihan massal), Redis menjadi ruang penyimpanan antrean (*Queue Storage*) yang tidak akan membebani database utama (MySQL/PostgreSQL).
- **Rate Limiting & Throttling:** Mencegah serangan *brute force* pada halaman login atau menjaga limit hit API (contoh: *webhook* dari Payment Gateway) dengan presisi tinggi.

---

## 2. LARAVEL HORIZON (Queue Manager & Dashboard)

**Status Saat Ini:** Belum diperlukan. Aplikasi berjalan murni *Synchronous* (langsung diproses).
**Kapan Mulai Digunakan?** Saat aplikasi masuk ke fase otomatisasi tagihan, isolir otomatis, dan notifikasi massal.

**Fungsi di Fase Post-MVP:**
- **Auto-Generate Invoices:** Di tanggal tagihan (misal tanggal 1 setiap bulan), sistem harus menerbitkan ribuan tagihan. Menggunakan Horizon, tugas ini dipecah menjadi ribuan *Background Jobs* (contoh: `GenerateMonthlyInvoiceJob`) yang dieksekusi secara asinkron (paralel) tanpa membuat web aplikasi utama menjadi lambat/down.
- **Auto-Suspend / Isolir Otomatis:** Menjalankan tugas terjadwal (*cronjob*) di tengah malam yang mencari pelanggan menunggak, lalu berkomunikasi dengan API MikroTik untuk melakukan isolir koneksi (PPPoE/Hotspot).
- **WhatsApp / Email Gateway:** Mengirim notifikasi "Tagihan Terbit" atau "Peringatan Isolir" secara massal. Horizon menyediakan *Dashboard UI* yang elegan untuk memantau seberapa cepat antrean berjalan, beban RAM, dan meninjau pesan mana yang berstatus *Failed* (misal: nomor WA tidak valid).

---

## 3. LARAVEL REVERB (WebSockets / Real-Time Events)

**Status Saat Ini:** Belum diperlukan. Halaman di-refresh secara manual oleh *user*.
**Kapan Mulai Digunakan?** Saat aplikasi membutuhkan interaktivitas langsung (*Real-Time*) tanpa *refresh browser*, terutama untuk modul integrasi perangkat teknis dan komunikasi.

**Fungsi di Fase Post-MVP:**
- **Live Notifications (UI/UX):** Saat sistem Payment Gateway mendeteksi pembayaran sukses, ikon lonceng di layar Kasir/Finance akan langsung memberikan notifikasi suara (Ting!) dan angka akan bertambah secara instan menggunakan WebSocket (Reverb Broadcast).
- **Dashboard Teknis (SNMP / OLT Monitoring):** Jika ada alat yang terputus (LOS / *Loss of Signal* pada perangkat pelanggan), layar pemantauan NOC akan langsung berkedip merah detik itu juga (tanpa perlu melakukan *AJAX Polling* terus-menerus yang memberatkan server).
- **Ticketing & Chat:** Memungkinkan komunikasi teks *live* layaknya WhatsApp di dalam sistem antara Teknisi di lapangan (menggunakan aplikasi seluler) dengan Tim Helpdesk/NOC di kantor.

---

### Kesimpulan Arsitektur
Fokus MVP WHUSNET saat ini adalah **Membangun Pondasi Logika Bisnis** (Role/Permission, Data Pelanggan, Layanan, Tagihan Manual, Pembayaran, dan Laporan). 

Setelah pondasi ini teruji di lapangan (*Production Ready*), infrastruktur di atas dapat diaktifkan hanya dengan mengubah koneksi di file `.env` dan sedikit konfigurasi *Driver*, tanpa perlu merombak ulang (*rewrite*) arsitektur inti dari program yang sudah kita bangun.
