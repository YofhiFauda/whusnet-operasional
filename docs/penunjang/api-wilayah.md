# Penunjang API Wilayah

API wilayah digunakan oleh form pelanggan untuk dependent dropdown kota, kecamatan, dan desa.

## Endpoint

| Method | Endpoint | Response |
| --- | --- | --- |
| GET | `/api/cities/{city}/districts` | Daftar kecamatan berdasarkan kota. |
| GET | `/api/districts/{district}/villages` | Daftar desa berdasarkan kecamatan. |

## Flow Kecamatan per Kota

```mermaid
sequenceDiagram
    participant UI as Form Pelanggan
    participant API as Route API
    participant City as City Model
    participant DB as Database

    UI->>API: GET /api/cities/{city}/districts
    API->>City: route model binding City
    City->>DB: districts()->orderBy('name')->get()
    DB-->>City: districts
    City-->>API: collection
    API-->>UI: JSON districts
```

## Flow Desa per Kecamatan

```mermaid
sequenceDiagram
    participant UI as Form Pelanggan
    participant API as Route API
    participant District as District Model
    participant DB as Database

    UI->>API: GET /api/districts/{district}/villages
    API->>District: route model binding District
    District->>DB: villages()->orderBy('name')->get()
    DB-->>District: villages
    District-->>API: collection
    API-->>UI: JSON villages
```

