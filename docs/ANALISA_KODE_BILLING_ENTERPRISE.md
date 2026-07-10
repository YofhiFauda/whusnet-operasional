# Analisis Kode dan Arsitektur Billing (Menuju Skala Enterprise)

Dokumen ini berisi dua bagian:
1. **Analisis Kode Saat Ini:** Evaluasi implementasi kode *existing* untuk Pelanggan, Tagihan, dan Pembayaran berdasarkan standar Laravel *Best Practices*.
2. **Analisis Skala Enterprise:** Evaluasi kesenjangan (gap) fitur, alur, dan struktur data untuk membawa project ini dari MVP menuju aplikasi kelas *Enterprise*.

---

## Bagian 1: Analisis Kode Saat Ini & Kebutuhan Refactor (MVP Scope)

Berdasarkan implementasi kode saat ini (terutama di `CustomerController`, `InvoiceController`, dan `PaymentController`), fungsionalitas dasar sudah berjalan, namun ada beberapa hal krusial yang perlu diperbaiki dari sisi arsitektur dan keamanan data:

### A. Tagihan (Invoices)
1. **Fat Controller & Tanggung Jawab Salah Tempat:** 
   - Pembuatan tagihan manual (`storeManualInvoice`) diletakkan di dalam `CustomerController` yang saat ini sudah sangat membengkak (~2600 baris). 
   - **Solusi:** Fitur tagihan wajib didelegasikan ke `InvoiceService` atau minimal ditangani oleh `InvoiceController` untuk menjaga prinsip *Single Responsibility*.
2. **Race Condition (Potensi Tagihan Dobel):** 
   - Pengecekan *invoice* dobel per periode dilakukan di luar `DB::transaction()`. Jika sistem menerima *request* ganda dalam waktu bersamaan (misal user klik tombol 2 kali dengan cepat), sistem bisa lolos dan membuat 2 tagihan untuk pelanggan di periode yang sama.
   - **Solusi:** Pengecekan eksistensi tagihan harus dilakukan di dalam transaksi dengan `lockForUpdate()`.
3. **Missing Database Constraint:** 
   - Tabel `invoices` tidak memiliki *unique constraint* kombinasi `customer_id` dan `billing_period`.
   - **Solusi:** Tambahkan `$table->unique(['customer_id', 'billing_period']);` di file *migration* untuk memastikan integritas data pada level database.
4. **Duplikasi Audit Log:** 
   - Model `Invoice` sudah menggunakan trait `RecordsAuditLogs`, namun pada `storeManualInvoice` controller masih memanggil `AuditLog::create(...)` secara manual. Ini tidak efisien dan berpotensi mencatat *log* ganda.

### B. Pembayaran (Payments)
1. **Logika Bisnis Terperangkap di Controller:** 
   - Proses perhitungan matematis (menghitung sisa tagihan, status lunas/sebagian), manipulasi file *upload*, dan *database transaction* ditulis secara *inline* di dalam `PaymentController::store`.
   - **Solusi:** Pindahkan ke `PaymentService::processPayment()`.
2. **Inkonsistensi Implementasi Audit Log:** 
   - Model `Payment` mendaftarkan *event listener* `created/updated/deleted` secara manual di fungsi `booted()`, padahal model lain menggunakan trait `RecordsAuditLogs`. Ini membuat standar kode tidak seragam.
3. **Hardcode Array (Tanpa Enum):** 
   - Validasi seperti `['cash', 'transfer', 'qris', 'lainnya']` ditulis menggunakan string array biasa.
   - **Solusi:** Gunakan fitur Enum bawaan PHP 8.1+ & Laravel untuk validasi dan penentuan tipe data.

### C. Keseluruhan (General)
- **Minim Penggunaan Form Request:** Validasi `$request->validate()` sering dilakukan inline, membuat *controller* kotor.
- **Duplikasi Query Filter:** Blok pencarian (pencarian teks, rentang tanggal) sering ditulis berulang. Seharusnya di-ekstrak menjadi *Local Scopes* di Model.

---

## Bagian 2: Analisis Kesenjangan Menuju Skala Enterprise

Jika project ini ingin ditingkatkan dari sekadar MVP menjadi **Sistem Billing ISP Berbasis Enterprise**, maka ada sejumlah alur (*workflow*), proses, dan arsitektur data yang saat ini masih kurang atau belum ada.

> **PENTING (ATURAN MVP):** Sebagian besar fitur di bawah ini **termasuk dalam daftar Post-MVP** (seperti Payment Gateway, Auto Suspend, Integrasi MikroTik). Berdasarkan instruksi, fitur-fitur ini **belum boleh dikerjakan pada tahap MVP saat ini** kecuali diminta secara eksplisit. Analisis ini murni sebagai *roadmap* arsitektur.

### 1. Modul Pelanggan & Layanan (Customer Data)
*Di sistem Enterprise, pelanggan bukanlah entitas tunggal dengan layanannya. Pelanggan adalah "Akun Penagihan" (Billing Account).*

- **Kelemahan Data Saat Ini:** Relasi Customer ke CustomerService adalah `1-to-1` (`hasOne`). Artinya 1 pelanggan hanya bisa punya 1 titik koneksi/layanan internet.
- **Kebutuhan Enterprise:** Relasi harus **1-to-Many**. Satu entitas Pelanggan (misal: PT ABC) bisa memiliki banyak Layanan (Cabang A, Cabang B). Sistem tagihan harus bisa mengakumulasi semua layanan ke dalam 1 *Invoice* gabungan, atau memisahkannya sesuai instruksi.
- **Deposit & Saldo Akun (Wallet):** Sistem Enterprise membutuhkan entitas saldo (*wallet*) pelanggan. Jika pelanggan membayar lebih (*overpayment*), sisanya masuk ke deposit untuk memotong tagihan bulan depan.
- **Customer Portal / Self-Service App:** Pelanggan memiliki akses *dashboard* mandiri untuk mengecek tagihan, bayar *online*, dan membuat tiket gangguan tanpa harus menghubungi CS.

### 2. Modul Tagihan (Invoices)
*Sistem Enterprise tidak membuat tagihan secara manual.*

- **Automated Billing Engine (Cron Jobs):** Sistem memerlukan *background scheduler* (Cron) kompleks yang berjalan setiap tengah malam. Mesin ini bertugas mencari pelanggan aktif, menghitung prorata (jika ada), dan secara otomatis membuat *invoice* (Auto Generate Tagihan Kompleks).
- **Sistem Denda & Pajak Multi-Tier (Late Fees & Taxes):** Perlu penambahan sistem perhitungan otomatis untuk denda keterlambatan (misal: +10% jika telat 5 hari) dan pajak dinamis (PPN 11%, PPh, dll).
- **Prorata Dinamis (Proration):** Jika pelanggan pasang di tanggal 20, dan *billing cycle* adalah tanggal 1, sistem Enterprise harus bisa menghitung tagihan tanggal 20 s/d 30/31 secara otomatis (Tagihan prorata).
- **Auto Suspend / Isolir:** Mesin harus mendeteksi tagihan yang lewat jatuh tempo, otomatis mengubah status pelanggan menjadi `isolir`, lalu **berkomunikasi via API ke Router MikroTik/Radius** untuk memutus akses internet secara *real-time*.

### 3. Modul Pembayaran (Payments)
*Pembayaran Enterprise berjalan tanpa campur tangan manusia (Zero Touch).*

- **Payment Gateway Integration:** Sistem tidak lagi bergantung pada unggah bukti transfer manual. Membutuhkan integrasi dengan *Payment Gateway* (Xendit, Midtrans, dll) yang menyediakan *Virtual Account* (VA) unik per pelanggan atau QRIS dinamis.
- **Automated Reconciliation (Rekonsiliasi Otomatis):** Saat pelanggan bayar VA, sistem menerima *Webhook* dari Payment Gateway, otomatis memvalidasi nominal, menandai *Invoice* lunas, dan mengirim perintah API ke MikroTik untuk membuka blokir isolir.
- **Reminders & Notifikasi (WhatsApp/Email):** Sistem secara otomatis mengirim pesan "H-3 Jatuh Tempo", "Tagihan Terbit", dan "Terima Kasih atas Pembayaran Anda" melalui WhatsApp API.
- **Sistem Akuntansi Buku Besar (General Ledger):** Semua pergerakan uang pembayaran harus terhubung dengan kode akun (Chart of Accounts) untuk laporan jurnal, laba rugi, dan neraca yang valid secara akuntansi.

### Kesimpulan Arsitektur
Untuk saat ini, struktur *database* yang ada (`customers`, `invoices`, `payments`) sudah cukup menopang operasional manual tingkat MVP. Namun, untuk menjadi Enterprise, diperlukan perombakan struktur relasi (terutama `1-to-many` services), penambahan tabel *Wallet/Deposit*, dan pembuatan *daemon/worker* (Jobs & Queues) untuk menjalankan otomatisasi tagihan, pemblokiran, dan integrasi API pihak ketiga secara masif.
