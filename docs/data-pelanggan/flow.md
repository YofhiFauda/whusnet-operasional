# Flow Fitur Data Pelanggan

## Flow Daftar Pelanggan

1. User membuka `GET /customers`.
2. Sistem membaca query filter:
   - `search`
   - `status`
   - `district_id`
   - `package_id`
3. `CustomerController@index` membuat query `Customer` dengan eager loading:
   - `city`
   - `district`
   - `village`
   - `InternetPackage`
   - `subscriptionStatus`
4. Sistem menerapkan filter pencarian, status, kecamatan, dan paket.
5. Data dipaginasi 10 baris per halaman.
6. Sistem mengambil data master untuk filter.
7. View `customers.index` menampilkan daftar, badge status, dan progress workflow.

## Flow Registrasi Pelanggan

1. User membuka `GET /customers/create`.
2. Sistem mengambil data kota, kecamatan, dan paket.
3. User mengisi form pelanggan.
4. User submit ke `POST /customers`.
5. Sistem validasi field wajib, relasi wilayah, paket, koordinat, status, dan dokumen.
6. File dokumen disimpan ke disk `public` pada folder `documents`.
7. Sistem membuat `customer_code` dengan format `WHUS-YYYY-0001`.
8. Data disimpan ke tabel `customers`.
9. User diarahkan ke daftar pelanggan dengan pesan sukses.

## Flow Edit Pelanggan

1. User membuka `GET /customers/{customer}/edit`.
2. Sistem mengambil data pelanggan dan master pendukung.
3. User mengubah data.
4. User submit ke `PUT /customers/{customer}`.
5. Sistem validasi field.
6. Jika ada dokumen yang dihapus, file lama dihapus dari storage.
7. Jika ada dokumen baru, file lama diganti dengan file baru.
8. Data pelanggan diperbarui.
9. User diarahkan ke detail pelanggan.

## Flow Detail Pelanggan

1. User membuka `GET /customers/{customer}`.
2. Sistem load relasi wilayah, paket, dan status.
3. Sistem menghitung biaya bulanan:
   - Harga paket.
   - Diskon.
   - Pajak.
   - Total bulanan.
4. Sistem menghitung prorate pembayaran awal.
5. Sistem menyusun timeline berdasarkan status.
6. Sistem menyusun data survey, FOP, pemasangan, aktivasi, teknis, uji layanan, invoice, referral, dan timelog.
7. View `customers.show` menampilkan detail pelanggan.

## Flow Import Pelanggan

1. User membuka `GET /customers/import`.
2. Sistem menyediakan paket dan desa untuk pencocokan import.
3. User menyiapkan data Excel/CSV/copy paste di UI.
4. Frontend mengirim row ke `POST /customers/import/validate`.
5. Sistem memvalidasi:
   - Nama wajib.
   - HP wajib dan dicek duplikasi.
   - ID/NIK wajib dan dicek duplikasi.
   - Desa dicocokkan ke `villages`.
   - Paket dicocokkan ke `internet_packages`.
   - Koordinat diparse sebagai `latitude, longitude`.
6. Sistem mengembalikan status row: `valid`, `warning`, atau `error`.
7. User memperbaiki row yang bermasalah.
8. User confirm import ke `POST /customers/import/confirm`.
9. Sistem menyimpan row valid ke tabel `customers` dalam transaction.

