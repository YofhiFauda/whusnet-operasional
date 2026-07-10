# Database Schema: Master Paket Internet

## Entitas Tabel

Tabel `internet_packages` adalah tabel master yang berdiri sendiri namun direferensikan oleh banyak entitas lain, utamanya tabel `customers` dan `invoices`.

```mermaid
erDiagram
    internet_packages ||--o{ customers : subscribed_by
    internet_packages ||--o{ invoices : billed_as

    internet_packages {
        bigint id PK
        varchar package_code
        varchar name
        varchar category
        varchar package_group
        varchar bandwidth_label
        decimal download_speed_mbps
        decimal upload_speed_mbps
        tinyint contention_ratio
        decimal monthly_price
        varchar modem
        json features
        int max_users
        varchar ip_address_type
        smallint contract_period_months
        decimal installation_fee
        varchar installation_fee_label
        varchar profile
        text terms
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
```

## Field Penting
- `package_code`: Kode unik paket (misal: `Net138`).
- `monthly_price`: Harga dasar langganan bulanan paket, dipakai saat generate tagihan.
- `is_active`: Flag aktif/tidaknya paket. Hanya paket aktif yang dapat dipilih saat registrasi pelanggan.
