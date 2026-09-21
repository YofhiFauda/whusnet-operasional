# Flowchart — Gate "Menunggu Verifikasi BD" & Invoice Awal

## Alur Utama: CS Verifikasi → (Gate?) → Invoice Awal → ACTIVE

```mermaid
flowchart TD
    A[CS buka /verifications/id/admin<br/>tab Verifikasi] --> B[CS isi issue_date + biaya tambahan<br/>submit finalVerify]
    B --> C{Customer::needsBusdevInstallationFeeVerification<br/>kategori paket punya installation_fee_approval_role_id?}

    C -->|TIDAK — mis. Home Broadband| D[InitialInvoiceService::issue<br/>Invoice AWAL terbit LANGSUNG]
    D --> E[customers.status = ACTIVE<br/>service_status=aktif, CID digenerate]
    E --> F[CustomerObserver bikin customer_acquisitions]
    F --> G[Selesai — pelanggan resmi aktif]

    C -->|YA — mis. Bisnis Broadband| H[extra_installation_fee dipaksa 0<br/>billing dihitung tapi TIDAK diterbitkan]
    H --> I[Snapshot billing + issue_date disimpan ke<br/>customers.pending_initial_invoice]
    I --> J[customers.status = WAITING_BUSINESS_DEVELOPMENT_VERIFICATION<br/>service_status tetap diset aktif — CID digenerate]
    J --> K[Muncul di antrean /business-development-verifications]

    K --> L[BD buka halaman detail — reuse verifications/admin.blade.php<br/>tab Verifikasi cabang BD: kartu Hasil Verifikasi CS badge Belum Terbit]
    L --> M[BD isi Biaya Instalasi, klik Verifikasi & Aktifkan]
    M --> N{Customer::canInstallationFeeBeValidatedBy<br/>permission override ATAU role cocok?}
    N -->|TIDAK| O[403 Forbidden]
    N -->|YA| P[Transaksi DB BusinessDevelopmentVerificationController::verify]

    P --> P1[1. withInstallationFee suntik nominal BD ke snapshot CS<br/>subtotal/PPN/total dihitung ULANG]
    P1 --> P2[2. InitialInvoiceService::issue<br/>SATU Invoice AWAL terbit — sudah termasuk Biaya Instalasi]
    P2 --> P3[3. customers.status = ACTIVE<br/>pending_initial_invoice ditimpa null]
    P3 --> F
```

## Kartu "Hasil Verifikasi CS" — Dua Bentuk

```mermaid
flowchart LR
    Q[BD buka halaman detail] --> R{CustomerVerificationDetailService::load<br/>ada Invoice AWAL/REAKTIVASI sungguhan?}
    R -->|Ya — initialInvoice| S[Kartu: nomor invoice sungguhan<br/>badge Lunas/Belum Dibayar<br/>link ke invoices.show]
    R -->|Belum — pendingInitialInvoice| T[Kartu: angka dari snapshot JSON<br/>badge amber Belum Terbit<br/>tanpa link — invoice belum ada]
```

## Kenapa Dua Cabang Ini Penting

Sebelum fix 2026-09-14, cabang kanan (kategori Bisnis) langsung menerbitkan Invoice AWAL di titik **B**, sama seperti cabang kiri — cuma statusnya yang mampir ke `WAITING_BUSINESS_DEVELOPMENT_VERIFICATION`. Efeknya: pelanggan sudah punya tagihan resmi walau BD belum menyetujui apa pun. Sekarang titik penerbitan invoice untuk cabang kanan pindah ke **P2**, setelah BD ikut menyetujui — konsisten dengan makna "gate".

Sebelum fix 2026-09-16, **P1-P2 dulu dua langkah terpisah yang masing-masing menerbitkan invoice sendiri** (Invoice Awal + Invoice Biaya Instalasi INSIDENTAL) — satu pelanggan berakhir dengan dua tagihan nyangkut di satu aktivasi. Sekarang **P1 cuma menyuntik angka, P2 satu-satunya titik yang menerbitkan invoice** — hasilnya satu invoice yang mencatat kedua komponen biaya.

Lihat juga [business-logic.md §3](business-logic.md#3-gate-menunggu-verifikasi-bd--state-machine) untuk penjelasan tiap langkah, dan [docs/customer-lifecycle/flowchart.md](../customer-lifecycle/flowchart.md) untuk alur registrasi→survey→pemasangan sebelum titik **A**.
