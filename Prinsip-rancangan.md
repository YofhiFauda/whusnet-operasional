# Rancangan Matang Daftar Pelanggan

Dokumen ini merapikan data mentah pelanggan menjadi rancangan yang siap dijadikan dasar pengembangan modul **Daftar Pelanggan**. Fokus awal adalah membuat data pelanggan tersimpan rapi, mudah dicari, dapat diimpor dari Excel, dan dapat dikembangkan menjadi proses operasional yang lebih kompleks seperti survey, pemasangan, aktivasi, data teknis, pengujian, dan pembayaran awal.

## 1. Tujuan Modul

Modul Daftar Pelanggan bertujuan untuk:

- Menjadi pusat data pelanggan dari awal registrasi sampai aktif berlangganan.
- Menyimpan riwayat proses pelanggan secara bertahap: registrasi, survey, penugasan FOP, pemasangan, aktivasi, uji layanan, dan pembayaran awal.
- Memisahkan data master, data transaksi, dokumen, dan data teknis agar mudah dikembangkan.
- Mendukung input manual dan input massal melalui Excel.
- Menyediakan status pelanggan yang jelas sehingga tim admin, sales, surveyor, teknisi, FOP, dan aktivator dapat bekerja berdasarkan data yang sama.

## 2. Prinsip Rancangan

- **ID registrasi permanen**: setiap pelanggan memiliki ID REG yang tidak berubah walaupun layanan berubah.
- **Langganan dipisah dari pelanggan**: satu pelanggan dapat memiliki lebih dari satu riwayat langganan di masa depan.
- **CID bukan milik permanen pelanggan**: CID berasal dari stok master dan dapat dilepas ketika pelanggan berhenti berlangganan.
- **Dokumen disimpan sebagai lampiran terpisah**: foto KTP, rumah, kontrak, speedtest, dan dokumen lain disimpan sebagai dokumen yang dapat diperluas.
- **Petugas menggunakan master staff**: sales, agent, surveyor, teknisi, aktivator, FOP, dan admin berasal dari satu data staff dengan role berbeda.
- **Lokasi dibuat terstruktur**: kota, kecamatan, dan desa menggunakan data referensi agar pencarian dan laporan lebih konsisten.
- **Status menjadi pengendali alur kerja**: perubahan status harus mengikuti tahapan operasional yang ditentukan.
- **Harga layanan disimpan sebagai snapshot**: harga paket saat pelanggan daftar tetap disimpan walaupun harga paket master berubah di masa depan.

## 3. Ruang Lingkup MVP

Tahap awal pengembangan Daftar Pelanggan minimal mencakup:

- Input data pelanggan baru.
- Import pelanggan dari Excel.
- Daftar pelanggan dengan pencarian dan filter.
- Detail pelanggan.
- Data layanan/langganan.
- Upload dokumen KTP, foto rumah, dan kontrak.
- Status pelanggan dasar.
- Data referral sales, agent, atau pelanggan.
- Data teknis awal dan CID.
- Ringkasan pembayaran awal.

Fitur survey, pemasangan, aktivasi, uji layanan, dan invoice awal dapat dimulai sederhana sebagai tab/detail, lalu dikembangkan menjadi workflow penuh.

## 4. Status Pelanggan

Status pelanggan digunakan untuk membaca posisi pelanggan dalam proses operasional.

| Status | Arti | Kapan Digunakan |
| --- | --- | --- |
| `registered` | Pelanggan baru terdaftar | Setelah data awal disimpan |
| `waiting_survey` | Menunggu survey | Setelah pelanggan siap dijadwalkan survey |
| `surveyed` | Survey selesai | Setelah hasil survey dicatat |
| `waiting_installation` | Menunggu pemasangan | Setelah survey dinyatakan layak |
| `installed` | Pemasangan selesai | Setelah teknisi menyelesaikan pemasangan |
| `active` | Layanan aktif | Setelah aktivasi berhasil |
| `suspended` | Layanan ditangguhkan | Karena tunggakan atau alasan operasional |
| `terminated` | Berhenti langganan | Setelah pelanggan putus layanan |
| `rejected` | Pendaftaran ditolak | Jika tidak layak survey, data tidak valid, atau alasan lain |

## 5. Struktur Data Utama

### 5.1 Data Pelanggan

Data pelanggan adalah identitas permanen pelanggan.

| Field | Wajib | Keterangan |
| --- | --- | --- |
| ID REG | Ya | Dibuat otomatis oleh aplikasi dan bersifat permanen |
| Nama lengkap | Ya | Nama sesuai identitas |
| Nomor identitas | Ya | KTP/NIK atau identitas lain, harus unik |
| Jenis kelamin | Tidak | Laki-laki/perempuan |
| Email | Tidak | Harus format email jika diisi |
| Nomor HP | Ya | Nomor utama pelanggan |
| Alamat lengkap | Ya | Alamat pemasangan atau domisili pelanggan |
| Desa | Tidak | Mengacu ke master desa |
| Kecamatan | Tidak | Mengikuti desa |
| Kota | Tidak | Mengikuti kecamatan |
| Latitude | Tidak | Koordinat lokasi rumah |
| Longitude | Tidak | Koordinat lokasi rumah |
| Tanggal registrasi | Ya | Tanggal pelanggan masuk ke sistem |
| Status pelanggan | Ya | Mengikuti daftar status pelanggan |

### 5.2 Dokumen Pelanggan

Dokumen disimpan per pelanggan agar jenis dokumen bisa bertambah tanpa mengubah struktur utama.

| Jenis Dokumen | Keterangan |
| --- | --- |
| Foto KTP | Bukti identitas pelanggan |
| Foto rumah | Bukti lokasi pemasangan |
| Foto kontrak | Bukti persetujuan berlangganan |
| Dokumen tambahan | Untuk pengembangan berikutnya |

Setiap dokumen minimal menyimpan jenis dokumen, file, tanggal upload, dan catatan.

### 5.3 Data Referral

Referral melekat pada proses langganan, bukan hanya pada data pelanggan, agar satu pelanggan bisa punya riwayat referral berbeda jika mendaftar layanan baru.

| Field | Keterangan |
| --- | --- |
| ID Sales | Staff dengan role sales |
| ID Agent | Staff dengan role agent |
| ID Referral Pelanggan | Pelanggan yang mereferensikan pelanggan baru |
| Catatan referral | Informasi tambahan bila diperlukan |

### 5.4 Data Layanan

Data layanan menyimpan paket yang dipilih pelanggan dan perhitungan biaya bulanannya.

| Field | Wajib | Keterangan |
| --- | --- | --- |
| Nama paket | Ya | Mengacu ke master paket |
| Harga paket | Ya | Snapshot harga saat daftar |
| Kecepatan upload | Ya | Dalam Mbps |
| Kecepatan download | Ya | Dalam Mbps |
| Profile | Tidak | Profile PPPoE/MikroTik/billing |
| Jenis kontrak | Ya | Bulanan, tahunan, atau kontrak khusus |
| Diskon | Tidak | Bisa nominal atau persentase |
| PPN | Tidak | Persentase pajak |
| Total biaya layanan | Ya | Harga paket dikurangi diskon, ditambah PPN |

Rumus dasar:

```text
discount_amount = harga_paket * diskon_persen / 100
atau
discount_amount = diskon_nominal

ppn_amount = (harga_paket - discount_amount) * ppn_persen / 100
total_biaya_layanan = harga_paket - discount_amount + ppn_amount
```

## 6. Workflow Operasional

### 6.1 Registrasi

Tujuan registrasi adalah membuat data pelanggan dan data langganan awal.

Data minimal:

- Data diri pelanggan.
- Alamat dan lokasi.
- Paket layanan.
- Referral jika ada.
- Dokumen awal jika sudah tersedia.

Output:

- ID REG pelanggan.
- Kode langganan.
- Status pelanggan `registered` atau `waiting_survey`.

### 6.2 Survey

Survey digunakan untuk menilai kelayakan pemasangan.

| Field | Keterangan |
| --- | --- |
| Tanggal dan waktu mulai survey | Awal proses survey |
| Tanggal dan waktu selesai survey | Akhir proses survey |
| Foto rumah | Bukti kondisi lokasi |
| Kebutuhan alat | Kabel, tiang, perangkat tambahan, atau catatan kebutuhan lain |
| Durasi survey | Dihitung dari waktu mulai dan selesai |
| Petugas survey | Bisa 1 sampai 3 orang |
| Status survey | Scheduled, in progress, completed, atau cancelled |

Output:

- Hasil survey.
- Kebutuhan alat.
- Status pelanggan menjadi `surveyed`, `waiting_installation`, atau `rejected`.

### 6.3 Penugasan FOP

FOP mencatat penugasan yang menghubungkan survey dan pemasangan.

| Field | Keterangan |
| --- | --- |
| ID FOP | Petugas atau kode penugasan FOP |
| Waktu penugasan survey | Kapan survey ditugaskan |
| Waktu penugasan pemasangan | Kapan pemasangan ditugaskan |
| Status FOP | Assigned, survey assigned, installation assigned, completed, cancelled |

### 6.4 Pemasangan

Pemasangan mencatat pekerjaan teknisi di lokasi pelanggan.

| Field | Keterangan |
| --- | --- |
| Tanggal pemasangan | Tanggal pekerjaan |
| Waktu mulai pemasangan | Awal pekerjaan |
| Waktu selesai pemasangan | Akhir pekerjaan |
| Teknisi bertugas | Bisa 2 sampai 3 orang |
| Status pemasangan | Scheduled, in progress, completed, failed, cancelled |
| Catatan pemasangan | Kendala, material tambahan, atau informasi teknis |

Output:

- Status pelanggan `installed` jika pemasangan selesai.
- Data teknis awal dapat mulai diisi.

### 6.5 Aktivasi

Aktivasi mencatat kapan layanan pelanggan benar-benar aktif.

| Field | Keterangan |
| --- | --- |
| Tanggal aktivasi | Tanggal layanan aktif |
| Waktu aktivasi | Jam layanan aktif |
| Petugas aktivasi | Staff yang melakukan aktivasi |
| Status aktivasi | Pending, active, failed, cancelled |

Output:

- Status pelanggan `active`.
- Tanggal aktif langganan.
- Dasar perhitungan tagihan prorate.

### 6.6 Data Teknis

Data teknis melekat pada langganan aktif.

| Field | Keterangan |
| --- | --- |
| CID | Diambil dari master CID dan dapat dikembalikan ke stok |
| IP address | IP dial-up atau IP layanan |
| SN | Serial number perangkat |
| Perangkat pasif | Splitter, patchcord, adaptor, atau perangkat lain |
| Nomor cabang | Identitas cabang jaringan |
| Nomor POP | Identitas POP |
| Nomor OLT | Identitas OLT |
| Nomor port OLT | Port OLT yang digunakan |
| Nomor ODP | Identitas ODP |
| Nomor port ODP | Port ODP yang digunakan |
| Nomor router | Router yang melayani pelanggan |
| Redaman awal pemasangan | Nilai dBm saat pemasangan |
| Redaman aktual | Nilai dBm terkini |
| VLAN | VLAN layanan pelanggan |
| Catatan teknis | Informasi tambahan |

Aturan CID:

- CID hanya boleh aktif pada satu langganan.
- Saat pelanggan putus langganan, CID dilepas dari pelanggan.
- CID yang dilepas kembali ke stok dengan status yang sesuai, misalnya available, withdrawn, atau damaged.

### 6.7 Laporan Uji

Laporan uji menyimpan hasil validasi kualitas layanan.

| Field | Keterangan |
| --- | --- |
| Tanggal dan waktu uji | Waktu pengujian dilakukan |
| Sinyal redaman awal | Nilai redaman saat uji |
| Foto speedtest | Bukti hasil uji |
| Jitter | Dalam ms |
| Latency | Dalam ms |
| Speed upload | Dalam Mbps |
| Speed download | Dalam Mbps |
| Packet loss | Dalam persentase |
| Persentase sesuai paket | Perbandingan hasil uji dengan paket |
| Skor kualitas | Nilai kualitas yang bisa dihitung otomatis |

Rumus persentase sesuai paket:

```text
persentase_sesuai_paket = hasil_speed_download / download_paket * 100
```

Contoh: paket 10 Mbps, hasil 9 Mbps, maka persentase sesuai paket adalah 90%.

### 6.8 Pembayaran Awal

Pembayaran awal mencatat biaya pertama pelanggan setelah aktivasi.

| Komponen | Keterangan |
| --- | --- |
| Biaya pemasangan | Biaya instalasi standar |
| Tagihan prorate | Tagihan dari tanggal aktivasi sampai akhir bulan |
| Kabel tambahan | Biaya kabel di luar standar |
| Jasa instalasi perangkat tambahan | Biaya pekerjaan tambahan |
| Tambahan tiang | Biaya tiang tambahan |
| Biaya lain | Item tambahan yang belum dikategorikan |

Rumus prorate:

```text
tagihan_prorate = jumlah_hari_aktif_di_bulan_aktivasi * (biaya_bulanan / jumlah_hari_dalam_bulan)
```

Catatan: jumlah hari dalam bulan sebaiknya mengikuti bulan aktual, bukan selalu 30 hari, agar Februari dan bulan 31 hari tetap akurat.

## 7. Tampilan Daftar Pelanggan

Daftar pelanggan harus menampilkan informasi yang cukup untuk operasional harian tanpa membuka detail.

Kolom utama:

- ID REG.
- Nama pelanggan.
- Nomor HP.
- Alamat singkat.
- Desa/kecamatan/kota.
- Paket layanan.
- Total biaya layanan.
- Status pelanggan.
- Tanggal registrasi.
- Sales/agent/referral.
- Aksi detail.

Filter awal:

- Status pelanggan.
- Kota/kecamatan/desa.
- Paket layanan.
- Tanggal registrasi.
- Sales.
- Agent.
- Status aktivasi.

Pencarian:

- ID REG.
- Nama pelanggan.
- Nomor identitas.
- Nomor HP.
- CID.
- IP address.
- SN perangkat.

## 8. Detail Pelanggan

Halaman detail pelanggan sebaiknya dibagi menjadi beberapa tab:

- **Ringkasan**: identitas, status, paket, alamat, dan timeline singkat.
- **Data Diri**: data pelanggan dan kontak.
- **Dokumen**: KTP, rumah, kontrak, dan dokumen tambahan.
- **Layanan**: paket, kontrak, diskon, PPN, total biaya, dan status langganan.
- **Referral**: sales, agent, dan referral pelanggan.
- **Survey**: jadwal, hasil survey, petugas, kebutuhan alat.
- **FOP**: penugasan survey dan pemasangan.
- **Pemasangan**: jadwal, teknisi, hasil pemasangan.
- **Aktivasi**: waktu aktivasi dan petugas.
- **Teknis**: CID, IP, SN, OLT, ODP, redaman, VLAN.
- **Uji Layanan**: hasil speedtest dan kualitas layanan.
- **Pembayaran Awal**: biaya pemasangan, prorate, tambahan, dan status invoice.

## 9. Import Excel

Import Excel adalah fitur unggulan dan harus dirancang dengan validasi yang jelas.

### 9.1 Tujuan Import

- Mempercepat input data pelanggan dalam jumlah besar.
- Mengurangi input manual berulang.
- Memastikan data masuk dengan format yang konsisten.

### 9.2 Template Kolom Excel

Kolom minimal:

- Nama lengkap.
- Nomor identitas.
- Nomor HP.
- Email.
- Jenis kelamin.
- Alamat.
- Desa.
- Kecamatan.
- Kota.
- Latitude.
- Longitude.
- Tanggal registrasi.
- Nama paket.
- Jenis kontrak.
- Diskon.
- PPN.
- ID sales.
- ID agent.
- ID referral pelanggan.

Kolom opsional lanjutan:

- CID.
- IP address.
- SN.
- Nomor POP.
- Nomor OLT.
- Port OLT.
- Nomor ODP.
- Port ODP.
- VLAN.
- Redaman awal.
- Biaya pemasangan.
- Biaya tambahan.

### 9.3 Aturan Validasi Import

- Nomor identitas tidak boleh kosong dan tidak boleh duplikat.
- Nomor HP tidak boleh kosong.
- Nama pelanggan tidak boleh kosong.
- Paket layanan harus terdaftar di master paket.
- Sales dan agent harus terdaftar di master staff jika diisi.
- Referral pelanggan harus terdaftar jika diisi.
- Tanggal harus valid.
- Latitude dan longitude harus berupa angka.
- Diskon dan PPN harus berupa angka.
- CID tidak boleh sedang aktif dipakai pelanggan lain.

### 9.4 Hasil Import

Sebelum data disimpan permanen, sistem perlu menampilkan preview:

- Total baris dibaca.
- Jumlah baris valid.
- Jumlah baris error.
- Detail error per baris dan kolom.
- Tombol simpan hanya aktif jika tidak ada error kritis.

### 9.5 Strategi Duplikasi

Jika nomor identitas sudah ada:

- Default: baris ditolak.
- Opsi lanjutan: update data pelanggan existing dengan konfirmasi admin.

Jika nomor HP sudah ada:

- Default: tampil sebagai peringatan.
- Admin dapat memutuskan apakah tetap disimpan atau diperbaiki.

## 10. Hak Akses Awal

Hak akses dapat dikembangkan bertahap.

| Role | Hak Akses |
| --- | --- |
| Admin | Kelola semua data pelanggan dan master |
| Sales | Input pelanggan dan melihat pelanggan miliknya |
| Agent | Input pelanggan dan melihat pelanggan miliknya |
| Surveyor | Melihat tugas survey dan mengisi hasil survey |
| FOP | Mengatur penugasan survey dan pemasangan |
| Teknisi | Melihat tugas pemasangan dan mengisi hasil pemasangan |
| Aktivator | Mengisi aktivasi dan data teknis tertentu |

## 11. Aturan Audit dan Riwayat

Untuk pengembangan lanjut, setiap perubahan penting sebaiknya memiliki riwayat:

- Perubahan status pelanggan.
- Perubahan paket layanan.
- Perubahan CID.
- Perubahan data teknis.
- Upload atau penghapusan dokumen.
- Pembayaran awal.
- Import Excel.

Data audit minimal:

- User yang melakukan perubahan.
- Waktu perubahan.
- Data sebelum perubahan.
- Data sesudah perubahan.
- Catatan perubahan.

## 12. Rekomendasi Struktur Modul

Modul awal yang disarankan:

- Master wilayah: kota, kecamatan, desa.
- Master staff.
- Master paket layanan.
- Master CID.
- Pelanggan.
- Dokumen pelanggan.
- Langganan pelanggan.
- Referral langganan.
- Survey.
- FOP.
- Pemasangan.
- Aktivasi.
- Data teknis.
- Laporan uji.
- Invoice pembayaran awal.
- Import Excel.

## 13. Prioritas Pengembangan

### Tahap 1: Fondasi Daftar Pelanggan

- Master wilayah.
- Master staff.
- Master paket.
- Input pelanggan.
- Input langganan.
- Upload dokumen.
- Daftar dan detail pelanggan.
- Import Excel data pelanggan dan layanan.

### Tahap 2: Operasional Lapangan

- Survey.
- Penugasan FOP.
- Pemasangan.
- Aktivasi.
- Data teknis.
- Master CID dan assignment CID.

### Tahap 3: Kualitas dan Pembayaran Awal

- Laporan uji layanan.
- Perhitungan prorate.
- Invoice pembayaran awal.
- Item biaya tambahan.

### Tahap 4: Pengembangan Lanjutan

- Audit log.
- Notifikasi.
- Dashboard status pelanggan.
- Riwayat perubahan paket.
- Integrasi billing.
- Integrasi MikroTik/OLT jika diperlukan.
- Export Excel/PDF.
- Role permission yang lebih detail.

## 14. Prioritas Pengisian Data Master

Data master tidak semuanya harus lengkap sejak awal. Untuk memulai modul Daftar Pelanggan, dahulukan master yang menjadi syarat input pelanggan dan layanan. Master lain bisa menyusul ketika workflow operasional mulai dipakai.

### 14.1 Master yang Harus Diisi Terlebih Dahulu

Master berikut wajib disiapkan sebelum input pelanggan manual atau import Excel digunakan.

| Data Master | Alasan Harus Didahulukan | Minimal Data Awal |
| --- | --- | --- |
| Master paket layanan | Pelanggan harus memilih paket saat daftar | Kode paket, nama paket, harga, upload, download, profile, status aktif |
| Master staff | Referral dan petugas operasional mengacu ke staff | Kode staff, nama, role, nomor HP, status aktif |
| Master wilayah | Alamat pelanggan perlu konsisten untuk filter dan laporan | Kota, kecamatan, desa area layanan utama |
| Master role staff | Menentukan fungsi sales, agent, surveyor, teknisi, aktivator, FOP, admin | Daftar role dasar |
| Master status pelanggan/langganan | Status menjadi dasar workflow | Registered, waiting survey, surveyed, waiting installation, installed, active, suspended, terminated, rejected |
| Master jenis kontrak | Dibutuhkan saat pelanggan memilih layanan | Bulanan, tahunan, kontrak khusus |
| Master jenis diskon | Dibutuhkan untuk perhitungan layanan | None, percent, fixed |
| Master jenis dokumen | Dibutuhkan untuk upload dokumen pelanggan | KTP, rumah, kontrak |

Urutan praktis pengisian awal:

1. Master role staff.
2. Master staff.
3. Master wilayah utama.
4. Master paket layanan.
5. Master jenis kontrak, jenis diskon, jenis dokumen, dan status.

Catatan: jika status, role, jenis kontrak, jenis diskon, dan jenis dokumen dibuat sebagai enum di database, data tersebut tidak perlu diinput lewat halaman master. Namun tetap harus didefinisikan sejak awal di kode/database.

### 14.2 Master yang Bisa Menyusul

Master berikut bisa dibuat setelah input pelanggan dasar berjalan, terutama ketika proses survey, pemasangan, aktivasi, dan teknis mulai dipakai.

| Data Master | Kapan Dibutuhkan | Alasan Bisa Menyusul |
| --- | --- | --- |
| Master CID | Saat data teknis dan aktivasi mulai dipakai | Pelanggan bisa diregistrasi dulu tanpa CID |
| Master POP | Saat pemetaan jaringan mulai detail | Tidak wajib untuk data pelanggan awal |
| Master OLT | Saat data teknis pelanggan dicatat | Baru diperlukan pada tahap pemasangan/aktivasi |
| Master ODP | Saat teknisi mengisi port ODP pelanggan | Bisa menyusul setelah area jaringan dirapikan |
| Master router | Saat integrasi atau pencatatan router diperlukan | Tidak wajib untuk registrasi pelanggan |
| Master VLAN | Saat segmentasi jaringan dicatat | Bisa diisi saat data teknis mulai matang |
| Master perangkat pasif | Saat inventaris perangkat ingin distandarkan | Awalnya bisa dicatat sebagai teks bebas |
| Master biaya tambahan | Saat invoice awal mulai dibuat detail | Biaya tambahan bisa dimulai dari item manual |
| Master template Excel | Saat format import sudah stabil | Bisa disempurnakan setelah field final MVP jelas |
| Master alasan penolakan/pembatalan | Saat audit workflow mulai dibutuhkan | Awalnya bisa memakai catatan bebas |

### 14.3 Rekomendasi Urutan Implementasi Master

Urutan paling aman untuk pengembangan bertahap:

1. **Paket layanan**: wajib untuk membuat langganan pelanggan.
2. **Staff**: wajib untuk sales, agent, surveyor, teknisi, aktivator, dan FOP.
3. **Wilayah**: wajib untuk alamat yang rapi dan laporan area.
4. **Dokumen dan status dasar**: wajib untuk upload dan workflow awal.
5. **CID**: mulai saat pelanggan akan diaktivasi.
6. **POP, OLT, ODP, router, VLAN**: mulai saat data teknis ingin dibuat detail.
7. **Biaya tambahan dan invoice item**: mulai saat pembayaran awal dikembangkan.
8. **Audit, alasan status, dan template lanjutan**: mulai saat sistem sudah dipakai operasional.

### 14.4 Minimal Master untuk MVP

Jika ingin mulai secepat mungkin, master minimal yang harus tersedia adalah:

- Paket layanan.
- Staff dengan role admin, sales, agent, surveyor, teknisi, aktivator, dan FOP.
- Wilayah area layanan utama.
- Jenis kontrak.
- Jenis diskon.
- Jenis dokumen.
- Status pelanggan dan status langganan.

Dengan master minimal tersebut, sistem sudah bisa mendukung:

- Input pelanggan manual.
- Import Excel pelanggan.
- Pemilihan paket.
- Referral sales/agent/pelanggan.
- Upload dokumen utama.
- Filter daftar pelanggan berdasarkan wilayah, paket, status, dan staff.

## 15. Catatan Implementasi

- Gunakan `customers` untuk identitas permanen pelanggan.
- Gunakan `customer_subscriptions` untuk layanan/langganan pelanggan.
- Gunakan `customer_documents` untuk semua dokumen pelanggan.
- Gunakan `staff` untuk sales, agent, surveyor, teknisi, aktivator, FOP, dan admin.
- Gunakan `master_cids` dan `cid_assignments` untuk mengelola CID.
- Gunakan status sebagai dasar workflow, bukan hanya teks informasi.
- Simpan file upload di storage aplikasi dan simpan URL/path di database.
- Hindari menyimpan banyak petugas dalam satu kolom teks; gunakan tabel relasi seperti survey staff dan teknisi pemasangan.
- Hindari menggabungkan semua data pelanggan dalam satu tabel besar karena akan sulit dikembangkan.

## 16. Definisi Selesai MVP

MVP Daftar Pelanggan dianggap selesai jika:

- Admin dapat menambah pelanggan secara manual.
- Admin dapat import pelanggan dari Excel dengan preview dan validasi.
- Sistem membuat ID REG unik.
- Pelanggan tampil di daftar dengan filter dan pencarian.
- Detail pelanggan menampilkan data diri, layanan, dokumen, referral, status, dan catatan awal.
- Paket layanan menghitung total biaya dari harga, diskon, dan PPN.
- Dokumen utama dapat diupload.
- Status pelanggan dapat diperbarui sesuai alur dasar.
- Data siap menjadi dasar pengembangan survey, pemasangan, aktivasi, teknis, uji layanan, dan pembayaran awal.
