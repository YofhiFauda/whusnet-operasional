# Flowchart Dashboard

```mermaid
flowchart TD
    A[User membuka dashboard] --> B[GET /]
    B --> C[DashboardController@index]
    C --> D[Hitung customer stats]
    C --> E[Hitung package dan district stats]
    C --> F[Group customers by status]
    C --> G[Join package category]
    C --> H[Group registration trend by month]
    D --> I[Render dashboard.blade.php]
    E --> I
    F --> I
    G --> I
    H --> I
    I --> J[User melihat ringkasan operasional]
```

