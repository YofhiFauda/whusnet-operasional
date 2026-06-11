# Flowchart System

Dokumen ini menggambarkan alur sistem utama WHUSNET Operasional berdasarkan route dan controller yang tersedia.

## Flow Sistem Utama

```mermaid
flowchart TD
    A[User membuka aplikasi] --> B[Dashboard Operasional]
    B --> C{Pilih menu}

    C --> D[Data Pelanggan]
    C --> E[Master Wilayah]
    C --> F[Master Paket Internet]
    C --> G[Master Status Langganan]

    D --> D1[Daftar pelanggan]
    D1 --> D2[Filter/search/status tab]
    D1 --> D3[Tambah pelanggan]
    D1 --> D4[Import pelanggan]
    D1 --> D5[Detail pelanggan]
    D5 --> D6[Edit pelanggan]

    E --> E1[Tampilkan kota]
    E1 --> E2[Tampilkan kecamatan]
    E2 --> E3[Tampilkan desa]

    F --> F1[Query internet_packages]
    F1 --> F2[Filter search, kategori, status]
    F2 --> F3[Tampilkan daftar paket]

    G --> G1[Ambil status langganan]
    G1 --> G2[Hitung jumlah pelanggan per status]
    G2 --> G3[Tampilkan workflow status]

    D3 --> H[Validasi form pelanggan]
    D6 --> H
    H --> I{Valid?}
    I -->|Tidak| J[Kembali ke form dengan error]
    I -->|Ya| K[Simpan customers]
    K --> L[Redirect daftar/detail pelanggan]

    D4 --> M[Parse data Excel/CSV/copy paste di frontend]
    M --> N[POST validate import]
    N --> O{Row valid?}
    O -->|Error| P[Tampilkan error row]
    O -->|Warning| Q[Tampilkan warning dan perbaikan manual]
    O -->|Valid| R[POST confirm import]
    R --> S[Simpan batch customers]
```

## Flow Request MVC

```mermaid
sequenceDiagram
    participant User
    participant Route as routes/web.php
    participant Controller
    participant Model
    participant DB as Database
    participant View as Blade View

    User->>Route: HTTP request
    Route->>Controller: Arahkan ke action controller
    Controller->>Model: Query atau validasi data
    Model->>DB: Baca/tulis data
    DB-->>Model: Result
    Model-->>Controller: Collection/model
    Controller->>View: Kirim data compact
    View-->>User: HTML response
```

## Modul dan Route Utama

| Modul | Route | Controller |
| --- | --- | --- |
| Dashboard | `GET /` | `DashboardController@index` |
| Daftar pelanggan | `GET /customers` | `CustomerController@index` |
| Form import | `GET /customers/import` | `CustomerController@importForm` |
| Validasi import | `POST /customers/import/validate` | `CustomerController@validateImport` |
| Simpan import | `POST /customers/import/confirm` | `CustomerController@confirmImport` |
| Form tambah pelanggan | `GET /customers/create` | `CustomerController@create` |
| Simpan pelanggan | `POST /customers` | `CustomerController@store` |
| Detail pelanggan | `GET /customers/{customer}` | `CustomerController@show` |
| Form edit pelanggan | `GET /customers/{customer}/edit` | `CustomerController@edit` |
| Update pelanggan | `PUT /customers/{customer}` | `CustomerController@update` |
| Master wilayah | `GET /master/wilayah` | `RegionController@index` |
| Master paket internet | `GET /master/paket` | `InternetPackageController@index` |
| Master status langganan | `GET /master/status-langganan` | `SubscriptionStatusController@index` |
| API kecamatan per kota | `GET /api/cities/{city}/districts` | Closure route |
| API desa per kecamatan | `GET /api/districts/{district}/villages` | Closure route |
