# User Flow: Master Wilayah

Dokumen ini mendeskripsikan langkah-langkah pengguna (User Flow) dalam dua konteks penggunaan Master Wilayah.

## Skenario 1: Admin Mengelola Data Master Wilayah

**Aktor**: Admin

**Langkah-langkah**:
1. User mengklik menu **Master Data** di Sidebar.
2. User memilih sub-menu **Wilayah**.
3. Sistem menampilkan hierarki/daftar Kabupaten, Kecamatan, dan Kelurahan/Desa.
4. Jika User ingin mencari daerah tertentu, user mengetik nama Desa/Kecamatan pada kolom pencarian dan menekan Enter.
5. Sistem memfilter tabel untuk hanya menampilkan wilayah yang cocok dengan pencarian.

## Skenario 2: Customer Service Menambahkan Pelanggan Baru

**Aktor**: Customer Service / Sales

**Langkah-langkah**:
1. User membuka halaman registrasi pelanggan baru.
2. Saat mengisi form bagian Alamat, User memilih *Kota* dari Dropdown.
3. Begitu kota dipilih, Dropdown *Kecamatan* otomatis aktif dan memuat (melalui API AJAX) daftar kecamatan yang ada di dalam kota tersebut.
4. Begitu kecamatan dipilih, Dropdown *Desa/Kelurahan* otomatis aktif dan memuat daftar desa terkait.
5. User memilih kelurahan, melengkapi kode pos otomatis (jika ada), lalu melanjutkan ke step registrasi selanjutnya.
