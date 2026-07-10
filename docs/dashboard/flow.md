# Flow Dashboard Operasional

## Flow Data

1. User membuka route `GET /`.
2. `DashboardController@index` menghitung statistik utama dari tabel `customers`.
3. Controller menghitung total paket dari `internet_packages`.
4. Controller menghitung total kecamatan dari `districts`.
5. Controller membuat data chart:
   - Distribusi pelanggan per status.
   - Distribusi pelanggan per kategori paket.
   - Tren registrasi bulanan.
6. Data dikirim ke view `resources/views/dashboard.blade.php`.
7. Dashboard menampilkan kartu statistik dan grafik ringkasan.

## Query Utama

| Kebutuhan | Sumber data |
| --- | --- |
| Total pelanggan | `customers` |
| Pelanggan aktif | `customers.status = active` |
| Pelanggan inactive | `customers.status in terminated, rejected` |
| Pelanggan suspend | `customers.status = suspended` |
| Pelanggan pending | Status registrasi sampai installed |
| Total paket | `internet_packages` |
| Total kecamatan | `districts` |
| Paket per kategori | Join `customers.internet_package_id` ke `internet_packages.id` |
| Tren registrasi | `customers.registration_date` |

