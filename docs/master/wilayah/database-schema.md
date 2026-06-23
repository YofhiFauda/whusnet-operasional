# Database Schema: Master Wilayah

## Entitas Tabel

Modul Wilayah terdiri atas 3 tabel yang saling berkaitan membentuk hierarki referensi lokasi.

```mermaid
erDiagram
    cities ||--o{ districts : has_many
    districts ||--o{ villages : has_many
    
    cities ||--o{ customers : has_many
    districts ||--o{ customers : has_many
    villages ||--o{ customers : has_many

    cities {
        bigint id PK
        varchar name
        timestamp created_at
        timestamp updated_at
    }

    districts {
        bigint id PK
        bigint city_id FK
        varchar name
        timestamp created_at
        timestamp updated_at
    }

    villages {
        bigint id PK
        bigint district_id FK
        varchar name
        varchar postal_code
        timestamp created_at
        timestamp updated_at
    }
```

## Field Penting
- `city_id` pada `districts`: Mengaitkan kecamatan dengan kota induknya.
- `district_id` pada `villages`: Mengaitkan kelurahan dengan kecamatan induknya.
- Tabel `customers` merujuk langsung ke *id* kota, kecamatan, dan kelurahan untuk mengunci lokasi tempat tinggal/pemasangan.
