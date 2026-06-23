# Database Schema: Master POP

## Entitas Tabel

Tabel `pops` merepresentasikan data titik pusat (cabang), dan `pop_sequences` digunakan untuk menjamin agar urutan ID / kode pendaftaran pelanggan per POP tetap berurutan tanpa race condition.

```mermaid
erDiagram
    pops ||--o{ customers : registered_at
    pops ||--o{ user_pops : has_staff
    pops ||--|| pop_sequences : generates_id

    pops {
        bigint id PK
        varchar code
        varchar name
        varchar address
        varchar phone
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    
    pop_sequences {
        bigint id PK
        bigint pop_id FK
        varchar entity_type
        varchar year_month
        bigint last_sequence
        timestamp created_at
        timestamp updated_at
    }
```

## Field Penting
- `code` pada `pops`: Singkatan / inisial POP (misal: `MLG` untuk Malang). Seringkali digunakan sebagai Prefix penomoran.
- `last_sequence` pada `pop_sequences`: Mencatat angka terakhir ID pelanggan yang dibuat untuk suatu entitas (misal: "customer") pada rentang `year_month` tertentu di POP tersebut.
