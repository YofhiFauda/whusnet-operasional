# Dashboard Operasional

Dashboard adalah halaman awal aplikasi dan menampilkan ringkasan kondisi operasional pelanggan.

## Route dan Controller

| Method | Route | Action |
| --- | --- | --- |
| GET | `/` | `DashboardController@index` |

## Data yang Ditampilkan

| Data | Query |
| --- | --- |
| Total pelanggan | `Customer::count()` |
| Pelanggan aktif | `Customer::where('status', 'active')->count()` |
| Pelanggan tidak aktif | `Customer::whereIn('status', ['terminated', 'rejected'])->count()` |
| Pelanggan suspend | `Customer::where('status', 'suspended')->count()` |
| Pelanggan pending | Status `registered`, `waiting_survey`, `surveyed`, `waiting_installation`, `installed` |
| Total paket | `InternetPackage::count()` |
| Total kecamatan | `District::count()` |
| Distribusi status | Group by `customers.status` |
| Distribusi kategori paket | Join `customers` ke `internet_packages`, group by `category` |
| Tren registrasi bulanan | Group collection berdasarkan `registration_date` format `Y-m` |

## Flowchart

```mermaid
flowchart TD
    A[GET /] --> B[DashboardController@index]
    B --> C[Hitung statistik pelanggan]
    B --> D[Hitung total paket dan kecamatan]
    B --> E[Group status pelanggan]
    B --> F[Group kategori paket]
    B --> G[Group tren registrasi bulanan]
    C --> H[Render dashboard]
    D --> H
    E --> H
    F --> H
    G --> H
```

