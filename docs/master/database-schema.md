# Database Schema Master

## Master Wilayah

```mermaid
erDiagram
    CITIES ||--o{ DISTRICTS : has
    DISTRICTS ||--o{ VILLAGES : has
```

| Tabel | Primary Key | Unique | Index |
| --- | --- | --- | --- |
| `cities` | `id` | `name` | - |
| `districts` | `id` | `city_id`, `name` | - |
| `villages` | `id` | `district_id`, `name` | `postal_code` |

## Master Paket Layanan

| Tabel | Primary Key | Unique | Index |
| --- | --- | --- | --- |
| `internet_packages` | `id` | `package_code` | `category, package_group`, `is_active` |

## Master Status Langganan

| Tabel | Primary Key | Unique | Index |
| --- | --- | --- | --- |
| `subscription_statuses` | `id` | `code`, `workflow_order` | `is_active, workflow_order` |

## Relasi ke Pelanggan

```mermaid
erDiagram
    CITIES ||--o{ CUSTOMERS : city_id
    DISTRICTS ||--o{ CUSTOMERS : district_id
    VILLAGES ||--o{ CUSTOMERS : village_id
    internet_packages ||--o{ CUSTOMERS : internet_package_id
    SUBSCRIPTION_STATUSES ||--o{ CUSTOMERS : status
```

