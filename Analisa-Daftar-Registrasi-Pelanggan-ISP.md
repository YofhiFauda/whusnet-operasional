# Analisa Daftar dan Registrasi Pelanggan ISP

Tanggal analisa: 9 Juni 2026

## Kesimpulan

Kode modul pelanggan saat ini sudah cukup baik sebagai prototype daftar dan registrasi pelanggan ISP. Alur registrasi dasar sudah tersedia, data pelanggan cukup lengkap, dan tampilan sudah mendukung proses administrasi awal.

Namun, implementasi ini belum siap sebagai sistem operasional penuh untuk perusahaan ISP. Proses bisnis seperti survey nyata, SPK atau FOP, pemasangan, aktivasi billing atau PPPoE, pembayaran awal, audit role, dan perubahan status belum benar-benar dimodelkan sebagai transaksi operasional. Banyak data di halaman detail masih bersifat sintetis atau simulasi berdasarkan tanggal registrasi dan status pelanggan.

## Yang Sudah Baik

1. Form registrasi sudah dibagi menjadi wizard 5 tahap:
   - Data diri dan wilayah.
   - Dokumen lampiran.
   - Layanan dan paket.
   - Informasi referral.
   - Operasional dan teknis.

2. Data pelanggan sudah mencakup elemen penting ISP:
   - NIK.
   - Nomor telepon dan email.
   - Alamat instalasi.
   - Koordinat lokasi.
   - Kota, kecamatan, desa.
   - Paket layanan.
   - Masa kontrak.
   - Diskon dan pajak.
   - Referral sales, agent, atau pelanggan.
   - ONT serial number.
   - IP address.
   - ODP, OLT, dan VLAN.
   - Foto KTP, foto rumah, dan kontrak.

3. Daftar pelanggan sudah memiliki fitur operasional awal:
   - Pencarian berdasarkan nama, kode pelanggan, email, dan nomor telepon.
   - Filter kecamatan.
   - Filter paket layanan.
   - Tab status pelanggan.
   - Indikator kelengkapan data.
   - Indikator progress workflow.

4. Detail pelanggan sudah cukup kaya untuk monitoring:
   - Ringkasan.
   - Data diri.
   - Dokumen.
   - Layanan.
   - Referral.
   - Survey.
   - FOP.
   - Pemasangan.
   - Aktivasi.
   - Teknis.
   - Uji layanan.
   - Pembayaran awal.

5. Master status pelanggan sudah tersedia dan lebih fleksibel daripada hardcode penuh:
   - `registered`.
   - `waiting_survey`.
   - `surveyed`.
   - `waiting_installation`.
   - `installed`.
   - `active`.
   - `suspended`.
   - `terminated`.
   - `rejected`.

6. Upload dokumen sudah divalidasi dan disimpan ke storage.

7. Sudah ada test fitur untuk pembuatan pelanggan, detail pelanggan, dan edit pelanggan.

## Kekurangan Utama

### 1. Status pelanggan belum tervalidasi ke master status

Pada proses `store()` dan `update()`, field `status` masih divalidasi sebagai string biasa. Seharusnya status divalidasi terhadap tabel master `subscription_statuses`.

Rekomendasi:

```php
'status' => 'required|exists:subscription_statuses,code',
```

Selain itu, perubahan status sebaiknya tidak boleh bebas. Sistem perlu aturan transisi status, misalnya:

- `registered` hanya bisa naik ke `waiting_survey` atau `rejected`.
- `waiting_survey` hanya bisa ke `surveyed` atau `rejected`.
- `surveyed` hanya bisa ke `waiting_installation` atau `rejected`.
- `waiting_installation` hanya bisa ke `installed`.
- `installed` hanya bisa ke `active`.
- `active` bisa ke `suspended` atau `terminated`.
- `terminated` sebaiknya terminal dan tidak bisa diaktifkan kembali tanpa proses khusus.

### 2. Alur ISP masih simulasi, belum data operasional nyata

Survey, FOP, pemasangan, aktivasi, speedtest, pembayaran awal, teknisi, redaman, invoice, dan user log banyak dibuat secara sintetis dari `registration_date` dan `status`.

Ini bagus untuk demo UI, tetapi berbahaya untuk operasional karena tampilan dapat memberi kesan data nyata padahal belum tersimpan sebagai proses kerja.

Rekomendasi:

- Buat tabel `customer_surveys`.
- Buat tabel `installation_orders`.
- Buat tabel `installation_materials`.
- Buat tabel `activation_logs`.
- Buat tabel `billing_invoices`.
- Buat tabel `payment_logs`.
- Buat tabel `network_assets`.
- Buat tabel `support_tickets`.
- Buat tabel `customer_status_histories`.

### 3. Aksi di daftar pelanggan belum benar-benar bekerja

Beberapa aksi masih berupa `alert()` di sisi frontend:

- Ganti paket.
- WhatsApp tagihan.
- Cek ONT.
- Buat tiket gangguan.
- Terminasi layanan.
- Toggle koneksi aktif atau nonaktif.

Untuk ISP, aksi seperti isolir, aktifkan ulang, terminasi, atau ganti paket harus mencatat:

- Siapa user yang melakukan aksi.
- Kapan aksi dilakukan.
- Alasan perubahan.
- Status sebelum dan sesudah.
- Dampak ke billing.
- Dampak ke router, PPPoE, OLT, atau sistem provisioning jika ada integrasi.

### 4. Customer code rawan bentrok saat banyak user daftar bersamaan

Kode pelanggan `WHUS-YYYY-XXXX` dibuat dari customer terakhir lalu ditambah 1. Pada kondisi banyak user melakukan registrasi bersamaan, dua request dapat menghasilkan kode yang sama.

Rekomendasi:

- Gunakan transaksi database.
- Gunakan sequence khusus per tahun.
- Atau generate kode setelah insert berdasarkan ID yang sudah pasti.
- Tetap pertahankan unique index di database.

### 5. Role dan permission belum diterapkan

Route pelanggan belum dilindungi dengan middleware auth dan permission. Untuk perusahaan ISP, ini risiko besar karena data pelanggan dan dokumen identitas termasuk data sensitif.

Role minimal yang disarankan:

- CS atau Admin Registrasi.
- Surveyor.
- FOP atau Dispatcher.
- Teknisi.
- NOC.
- Billing atau Finance.
- Support.
- Owner atau Manager.

Contoh pembagian akses:

- CS dapat membuat registrasi dan memperbaiki data diri.
- Surveyor hanya mengisi hasil survey.
- FOP membuat SPK atau penugasan teknisi.
- Teknisi mengisi hasil pemasangan dan material.
- NOC melakukan aktivasi PPPoE, IP, VLAN, dan validasi layanan.
- Billing membuat invoice dan mencatat pembayaran.
- Owner melihat laporan dan KPI.

### 6. Data operasional belum ternormalisasi

Saat ini banyak informasi operasional dicampur ke model `Customer` atau disusun langsung di controller. Untuk sistem ISP, pelanggan sebaiknya hanya menjadi entitas utama, sedangkan proses operasional dibuat sebagai record terpisah.

Struktur yang lebih sehat:

- `customers`: data identitas pelanggan.
- `customer_addresses`: alamat dan koordinat instalasi jika ingin mendukung multi alamat.
- `customer_documents`: dokumen pelanggan.
- `customer_subscriptions`: paket aktif dan histori perubahan paket.
- `customer_surveys`: hasil survey.
- `installation_orders`: SPK pemasangan.
- `installation_results`: hasil instalasi.
- `network_assignments`: ONT, ODP, OLT, VLAN, IP, PPPoE.
- `invoices`: tagihan.
- `payments`: pembayaran.
- `customer_status_histories`: histori status.

### 7. Dokumen pelanggan perlu kontrol akses lebih ketat

Foto KTP dan kontrak disimpan ke disk public. Untuk data sensitif, ini kurang ideal.

Rekomendasi:

- Simpan dokumen di storage private.
- Sajikan dokumen melalui controller yang memeriksa permission.
- Catat akses download dokumen jika diperlukan.
- Terapkan kebijakan retensi dokumen.

## Penilaian UI/UX Berdasarkan Role

### CS atau Admin Registrasi

Cukup baik.

Wizard 5 tahap membantu input bertahap dan mengurangi beban kognitif. Progress kelengkapan juga membantu operator mengetahui data apa yang belum lengkap.

Perbaikan:

- Tambahkan validasi NIK 16 digit.
- Tambahkan validasi nomor HP Indonesia.
- Tambahkan deteksi duplikasi berdasarkan NIK, nomor HP, atau alamat.
- Tambahkan tombol simpan draft.
- Tambahkan pilihan sumber registrasi: walk-in, sales, agent, referral, online.

### Surveyor

Belum ideal.

Surveyor membutuhkan halaman kerja khusus, bukan hanya tab detail. Kebutuhan survey lapangan biasanya mencakup:

- Status kelayakan lokasi.
- ODP terdekat.
- Estimasi panjang kabel.
- Estimasi kebutuhan tiang atau material.
- Foto lokasi.
- Koordinat aktual.
- Catatan kendala jalur.
- Rekomendasi layak atau tidak layak pasang.

### FOP atau Dispatcher

Belum ideal.

FOP perlu fitur penugasan dan antrean kerja:

- Buat SPK survey.
- Buat SPK instalasi.
- Assign teknisi.
- Jadwal kunjungan.
- Prioritas pekerjaan.
- SLA.
- Status pekerjaan.

### Teknisi

Belum ideal.

Teknisi perlu tampilan mobile-friendly yang ringkas:

- Daftar pekerjaan hari ini.
- Navigasi ke lokasi.
- Checklist material.
- Upload foto sebelum dan sesudah.
- Input redaman.
- Input ONT serial number.
- Input ODP port.
- Submit hasil instalasi.

### NOC

Belum ideal.

NOC perlu halaman aktivasi teknis:

- Generate atau input username PPPoE.
- Assign IP pool.
- Assign VLAN.
- Validasi speedtest.
- Catat redaman awal.
- Integrasi router atau OLT jika memungkinkan.

### Billing atau Finance

Belum ideal.

Pembayaran awal masih ditampilkan sebagai simulasi invoice. Billing perlu:

- Invoice nyata.
- Status tagihan.
- Metode pembayaran.
- Tanggal jatuh tempo.
- Diskon.
- Pajak.
- Payment confirmation.
- Histori pembayaran.
- Isolir otomatis atau manual berdasarkan tunggakan.

### Owner atau Manager

Cukup terbantu untuk melihat detail, tetapi belum cukup untuk pengambilan keputusan.

Dashboard yang disarankan:

- Registrasi baru hari ini.
- Pending survey.
- Pending instalasi.
- Aktivasi hari ini.
- Gagal pasang.
- Pelanggan aktif.
- Pelanggan suspend.
- Terminasi.
- Piutang.
- Rata-rata waktu dari registrasi ke aktif.

## Prioritas Perbaikan

### Prioritas 1: Keamanan dan validitas data

- Tambahkan auth.
- Tambahkan role dan permission.
- Validasi status ke master status.
- Validasi NIK dan nomor HP.
- Buat kontrol akses dokumen pelanggan.

### Prioritas 2: Workflow operasional

- Buat histori status pelanggan.
- Buat aturan transisi status.
- Buat data survey nyata.
- Buat data SPK atau FOP nyata.
- Buat data pemasangan nyata.
- Buat data aktivasi nyata.

### Prioritas 3: Billing dan koneksi

- Buat invoice pembayaran awal.
- Buat invoice bulanan.
- Buat payment log.
- Ubah toggle koneksi menjadi aksi backend dengan audit log.
- Siapkan integrasi router atau provisioning jika dibutuhkan.

### Prioritas 4: UI per role

- Buat dashboard CS.
- Buat dashboard surveyor.
- Buat dashboard teknisi.
- Buat dashboard NOC.
- Buat dashboard billing.
- Buat dashboard owner.

### Prioritas 5: Kualitas teknis

- Pindahkan logic besar dari controller ke service class.
- Hindari data mock di controller.
- Tambahkan test untuk validasi status.
- Tambahkan test untuk transisi workflow.
- Tambahkan test untuk permission.
- Tambahkan test untuk upload dokumen private.

## Catatan Implementasi Teknis

Beberapa area kode yang perlu diperhatikan:

- `CustomerController::store()` untuk validasi registrasi dan pembuatan customer code.
- `CustomerController::update()` untuk validasi edit pelanggan dan pengelolaan dokumen.
- `CustomerController::show()` karena saat ini banyak data detail dibuat secara sintetis.
- `Customer::dataCompleteness()` untuk penilaian kelengkapan data.
- `Customer::workflowProgress()` untuk progress workflow di daftar pelanggan.
- `resources/views/customers/index.blade.php` untuk aksi operasional yang masih berupa simulasi.
- `resources/views/customers/create.blade.php` untuk wizard registrasi.
- `resources/views/customers/edit.blade.php` untuk wizard edit pelanggan.
- `resources/views/customers/show.blade.php` untuk detail pelanggan 12 tab.
- `routes/web.php` untuk penambahan auth dan permission middleware.

## Kesimpulan Akhir

Fondasi modul daftar dan registrasi pelanggan sudah baik untuk MVP. Secara tampilan, sistem sudah terlihat operasional dan cukup mudah dipahami oleh admin.

Namun untuk digunakan oleh perusahaan ISP sungguhan, sistem masih perlu dinaikkan dari tampilan operasional menjadi proses operasional yang benar-benar tercatat. Fokus terbesar berikutnya adalah role, audit log, workflow nyata, billing nyata, dan integrasi teknis.
