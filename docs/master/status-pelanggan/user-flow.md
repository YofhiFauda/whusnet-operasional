# User Flow: Master Status Pelanggan

Dokumen ini mendeskripsikan langkah-langkah pengguna (User Flow) dalam menggunakan fitur Master Status Pelanggan.

## Skenario Utama: Memeriksa Ringkasan Pelanggan per Status

**Aktor**: Admin / Pegawai

**Pre-condition**: User sudah login ke dalam aplikasi.

**Langkah-langkah**:
1. User mengklik menu **Master Data** di Sidebar.
2. User memilih sub-menu **Status Langganan**.
3. Sistem menghitung secara otomatis berapa jumlah pelanggan di setiap status (menggunakan fungsi backend `withCount('customers')`).
4. Sistem merender halaman yang menampilkan daftar semua tahapan workflow dari awal (Waiting Survey) hingga akhir (Terminated/Rejected).
5. Pada masing-masing tahap, User dapat melihat lencana angka (badge) yang mengindikasikan berapa pelanggan yang terjebak/berada di status tersebut saat ini.
6. User dapat mengetahui *bottleneck* antrean (misal: "Wah, banyak sekali pelanggan yang numpuk di status Waiting Installation").

**Post-condition**: User memahami ringkasan jumlah antrean dan beban pekerjaan di setiap lini proses operasional ISP.
