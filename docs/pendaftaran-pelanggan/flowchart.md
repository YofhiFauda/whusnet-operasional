# Flowchart: Pendaftaran Pelanggan Workflow

Berikut adalah alur sistem (system flow) untuk proses Pendaftaran Pelanggan Baru dari registrasi hingga penagihan.

```mermaid
flowchart TD
    A[Registrasi Pelanggan] -->|Save Draft| B(Status: draft)
    A -->|Submit Form| C(Status: waiting_survey)
    
    B -->|Lengkapi Data| C
    
    C -->|Mulai Survey| D(Status: survey_in_progress)
    D -->|Countdown SLA| E{Batas SLA > 60m?}
    E -->|Yes| F[Notifikasi Telegram]
    E -->|No| G[Lapor Survey Selesai]
    F --> G
    
    D -->|Lapor Data Survey| H(Status: surveyed)
    
    H -->|Proses Ke Tim Pasang| I(Status: waiting_installation)
    
    I -->|Mulai Pasang| J(Status: installation_in_progress)
    J -->|Countdown SLA| K{Batas SLA > 60m?}
    K -->|Yes| L[Notifikasi Telegram]
    K -->|No| M[Lapor Pasang Selesai]
    L --> M
    
    J -->|Input Data Teknis| N(Status: installed / verification_admin)
    
    N -->|Verifikasi Akhir & Generate Invoice| O(Status: active)
    
    O --> P[Pelanggan Aktif, Layanan Aktif, Invoice Pertama Terbit]
```
