# Analisa Nilai Unik Proyek: Whusnet Operasional, Warehouse & Portal Pelanggan (Portfolio Guide)

Dokumen ini memuat panduan komprehensif, arsitektur teknis, dan poin diferensiasi tingkat lanjut untuk menyusun portofolio profesional (**CV, LinkedIn, Technical Case Study, System Design Interview**) dari proyek **Whusnet Operasional (Core BSS/OSS & Inventory ERP)** dan **Portal Pelanggan (Decoupled PWA/API)**.

---

## 1. Core Positioning: Bukan Sekadar Aplikasi CRUD Biasa

Banyak proyek portofolio web terjebak dalam persepsi *“aplikasi CRUD standar”* (Input Form → Simpan Database → Tampilkan Tabel). 

Posisikan proyek ini sebagai **Enterprise-Grade Telco BSS/OSS & Inventory ERP**:
- **Bukan hanya billing:** Sistem menjamin konsistensi data dari hulu (gudang/modem) ke lapangan (teknisi & infrastruktur jaringan), hingga rekonsiliasi kas dan pelaporan analitik bisnis.
- **Bukan monolitik rapuh:** Mengisolasi sistem operasional internal dari akses publik pelanggan dengan arsitektur *decoupled*, *zero-trust*, dan otentikasi dua lapis.
- **Bukan hak akses sederhana:** Menangani multi-cabang (POP) dengan kontrol data hierarkis dan *Separation of Duties (SoD)* tanpa *role explosion*.

---

## 2. Whusnet Operasional: Core Billing, Financial & Analytical Integrity

### A. Customer-Centric Billing Pipeline & Data Quality Enforcement
* **Problem Industri:** Pada operasional ISP, kebocoran pendapatan (*revenue leakage*) sering terjadi karena tagihan diterbitkan terpisah dari status riil layanan/modem pelanggan, atau tagihan dibuat serampangan tanpa validasi.
* **Solusi & Nilai Unik:**
  * **Siklus Data Ketat:** Sistem menerapkan aturan invariant:  
    $$\text{Pelanggan} \longrightarrow \text{Paket Layanan} \longrightarrow \text{Validasi Kelengkapan} \longrightarrow \text{Aktivasi} \longrightarrow \text{Invoice Otomatis} \longrightarrow \text{Pembayaran}$$
  * **State Machine Kelengkapan Data:** Pelanggan memiliki status kelengkapan: `Draft` $\rightarrow$ `Perlu Dilengkapi` $\rightarrow$ `Lengkap` $\rightarrow$ `Siap Billing`.
  * **Billing Safety Guard:** Tagihan tidak pernah dibuat manual dari nol. Sistem hanya mengizinkan *invoice generation* jika data wajib (lokasi POP, harga kontrak paket, tanggal jatuh tempo, status layanan aktif) telah tervalidasi lengkap.
  * **State Machine Tagihan:** Rekonsiliasi pembayaran bertahap (`unpaid`, `partially_paid`, `paid`), proteksi *overpayment*, serta isolasi siklus layanan (*suspend*, *terminate*, *reactivate*).

### B. Concurrency Control & Financial Guard (*Pessimistic Locking*)
* **Problem Industri:** Di kantor cabang dengan latensi jaringan tinggi, kasir sering menekan tombol "Bayar" berkali-kali (*double-click*), berisiko memicu *race condition* dan pencatatan pembayaran ganda pada satu tagihan.
* **Solusi Arsitektur:**
  * **Pessimistic Locking Database:** Menggunakan `lockForUpdate()` di dalam `DB::transaction()` pada model `Invoice`. Request konkruen tertahan hingga transaksi pertama *committed*, secara otomatis menolak pembayaran berlebih (*double-spend protection*).
  * **Idempotency Guard:** Kombinasi state *disabled* instan di sisi antarmuka dan proteksi mutasi status di backend.

### C. Triple-Axis Payment Reconciliation (Sumbu Kas, Dokumen/OCR & Portal)
* **Solusi Unik Rekonsiliasi Lapangan:**
  1. **Sumbu KAS (Penyelesaian Finansial):** Alur fisik serah terima uang dari kolektor lapangan ke kasir kantor dengan rekonsiliasi status (`terverifikasi`, `selisih`, `lebih_setor`).
  2. **Sumbu DOKUMEN (Audit Fisik):** Lembar nota fisik (format grid 8/lembar) yang discan massal dan diverifikasi menggunakan **OCR & QR matching** ke catatan pembayaran (`payment_receipts`).
  3. **Kanal PORTAL (Digital Delivery):** Pengiriman kwitansi digital secara otomatis ke akun pelanggan begitu status setoran dinyatakan final di kantor, **tanpa menyandera pelanggan** menunggu proses verifikasi scan fisik/OCR selesai.

### D. Analytical Data Integrity (*Churn Preservation Invariant*)
* **Keputusan Arsitektur Tingkat Lanjut:** Saat pelanggan yang pernah putus langganan mendaftar kembali (**"Langganan Lagi"** / `TERMINATED` $\rightarrow$ `ACTIVE`), sistem **sengaja TIDAK mengosongkan kolom `terminated_at`**.
* **Alasan Bisnis & Analitik:** Dashboard pertumbuhan dan analitik NOC menghitung *churn rate* historis bulanan dengan `whereBetween('terminated_at', ...)`. Jika timestamp ini dikosongkan, laporan keuangan dan churn historis bulan-bulan sebelumnya akan terhapus secara retroaktif (*rewriting history*).

### E. Hierarchical RBAC & Multi-POP Data Scoping
* **Problem Industri:** *Role explosion* — kecenderungan developer menduplikasi role per cabang ("Admin Ponorogo", "Teknisi Siman") yang merusak skalabilitas.
* **Solusi Arsitektur:**
  * **Pemisahan Role (Kapabilitas Fungsi) vs Scope (Wilayah Data):** Role didefinisikan satu kali secara global (*Owner, NOC, FOP, Teknisi, Admin POP, Kasir*), sedangkan jangkauan wilayah data diatur via User Scope (`all_pop`, `selected_pop`, `pop_tree`, `assigned_only`, `own_created`) dan ditegakkan melalui Laravel Global Query Scopes.
  * **Separation of Duties (SoD):** Teknisi dilarang mencatat transaksi keuangan; Helpdesk dilarang mengubah nominal tagihan; Admin POP dilarang mengintip data cabang lain.

### F. Resilient Data Migration Engine
* **Solusi Unik:** Batch importer untuk ribuan data pelanggan warisan (*legacy database*) via Excel/CSV dengan *per-row validation logging* (`import_errors`) dan penampungan data parsial (`Draft`) tanpa mengotori siklus penagihan aktif.

---

## 3. Modul Gudang (Warehouse): *Financial-Grade Inventory Ledger & Custody*

Modul Gudang di Whusnet dirancang dengan arsitektur **Append-Only Event Sourcing / CQRS pattern**, setara dengan sistem inventaris enterprise/perbankan:

### A. Append-Only Immutable Ledger (Single Source of Truth)
* **Bukan CRUD Stok Biasa:** Stok barang tidak pernah diubah dengan `stok = stok - 1`. Setiap mutasi barang dicatat sebagai satu baris permanen di `inventory_transactions`.
* **Observer-Enforced Immutability:** Ditegakkan secara mutlak oleh `InventoryTransactionObserver` yang memblokir operasi `update` dan `delete`.
* **CQRS-Style Read Projections:** Tabel saldo gudang (`inventory_balances`), status nomor seri (`inventory_serials`), dan barang di tangan teknisi (`technician_custody`) adalah **proyeksi baca** yang diturunkan dari akumulasi ledger transaksi. Kesalahan input diselesaikan lewat transaksi penyeimbang (*Adjustment / Stock Opname*), bukan mengedit histori.

### B. End-to-End Serial Number (SN) Lifecycle Traceability
* **Pelacakan Fisik Menyeluruh:** Pelacakan unit modem/ONT sejak datang dari distributor $\rightarrow$ registrasi ke Gudang Pusat $\rightarrow$ transfer antar-cabang $\rightarrow$ diserahkan ke teknisi $\rightarrow$ terpasang di rumah pelanggan $\rightarrow$ dicabut saat terminasi (*Deactivation*) $\rightarrow$ status transit retur $\rightarrow$ kembali ke gudang cabang.
* **Dual-Axis Classification:** Memisahkan status kanonik kepemilikan (*in_stock, custody, installed, returned*) dengan kondisi fisik barang (*normal, rusak, refurbish*).

### C. Automated Custody Consumption via Field Tasks
* **Integrasi Seamless Gudang & Teknisi:** Teknisi tidak perlu menginput data ke modul gudang. Saat teknisi menyelesaikan laporan instalasi atau perbaikan di modul lapangan (FOP), sistem mengeksekusi `InventoryService::consumeFromCustody()`, memotong saldo kuantitas teknisi, dan memvalidasi nomor seri modem ke data pelanggan secara atomik.

### D. Two-Phase Inter-Branch Transfer (Multi-POP Dispatch & Confirm)
* Pengiriman barang dari Pusat ke Cabang menerapkan mekanisme transfer 2 fase: status barang menjadi `in_transit` saat di-dispatch dari Pusat, dan baru menambah saldo cabang setelah Admin Cabang memeriksa dan mengonfirmasi penerimaan fisik barang.

---

## 4. Infrastruktur: *Network Topology & Scalability Roadmap*

### A. Topologi Jaringan & Distribusi Fisik (Telco Infrastructure)
* **Hierarki Wilayah Jaringan (Master Distribusi):**
  * Pemetaan titik distribusi di bawah cabang/POP (`pops` $\rightarrow$ `distributions` $\rightarrow$ `customers`).
  * Setiap pelanggan, ODP, dan tugas lapangan terikat ke titik distribusi tertentu. Memungkinkan pemetaan *coverage area*, pemantauan kapasitas jaringan, serta isolasi identifikasi gangguan massal (*network outage localization*).

### B. Pragmatic Architecture & Scalability Roadmap (Peta Jalan Post-MVP)
* **Engineering Maturity (Pragmatic vs Premature Optimization):**
  * Pada fase awal MVP, sistem sengaja dijalankan secara sinkron untuk menjamin keakuratan relasional data tanpa membebani biaya server dengan Redis/Reverb.
* **Desain Modular Siap Skala Besar (Plug-and-Play Roadmap):**
  * **Redis & Laravel Horizon:** Arsitektur antrean asinkron (*Background Jobs*) yang siap diaktifkan untuk *batch invoicing* ribuan pelanggan di tanggal 1 setiap bulan, integrasi auto-suspend MikroTik tengah malam, dan antrean pengiriman WhatsApp gateway.
  * **Laravel Reverb (WebSockets):** Arsitektur real-time event untuk pemantauan live status OLT/SNMP pada layar NOC dan notifikasi kasir instan.

---

## 5. Portal Pelanggan: *Decoupled PWA & Zero-Trust Architecture*

### A. Zero-Trust Decoupled Architecture (Pemisahan Total DB & Domain)
* **Problem Industri:** Membuka akses database operasional langsung ke portal publik pelanggan adalah celah keamanan fatal (*attack surface* melebar).
* **Solusi Arsitektur:**
  * **Isolasi Mutlak:** Portal Pelanggan beroperasi di domain terpisah (Next.js / PWA) dan **sama sekali tidak memiliki kredensial ke database operasional Whusnet**.
  * **Two-Tier Authentication:** Setiap pemanggilan API `/api/customer-portal/*` harus melewati autentikasi ganda:
    1. *Portal Client Secret* (memastikan request berasal dari aplikasi resmi portal).
    2. *Bearer Token Pelanggan* (identitas sesi pelanggan berbasis format unik `{prefix_pop}-{customer_code}`).

### B. Event-Driven Webhook Outbox (Zero-PII Payload)
* **Solusi Privasi & Efisiensi:** Menggunakan *Outbox Pattern* (`webhook_outbox`) yang terpicu saat rekonsiliasi pembayaran. Sistem mendorong event `invoice.updated` ke Portal Pelanggan secara realtime **tanpa menyertakan PII (data pribadi) dalam payload transmisi**. Portal kemudian memvalidasi pembaruan via channel terenkripsi.

### C. Anti-Hijacking Credential Reset Lifecycle
* **Security Guard saat Reaktivasi Pelanggan:**
  * Saat pelanggan *Terminate*, token API dicabut seketika, status akun menjadi `disabled`, dan kartu QR di-revoke.
  * Saat pelanggan *Langganan Lagi*, akun portal **tidak** langsung aktif dengan password lama (mencegah pembajakan akun), melainkan dipulihkan ke status **`pending_claim`**. Pelanggan wajib mengklaim ulang identitas menggunakan PIN baru dari kartu fisik QR yang dicetak saat pemasangan kembali.

### D. Unified QR Code & Self-Service Pelanggan
* Satu QR Code terpadu yang dapat dipindai oleh pelanggan untuk memeriksa keaslian tagihan dan kwitansi digital, maupun oleh teknisi lapangan untuk identifikasi cepat pelanggan.

---

## 6. Proactive Field Operations (FOP & Teknisi)

* **Dynamic SLA Early-Warning System:** Sistem menghitung mundur durasi SLA survei/instalasi secara dinamis sejak tugas dibuat.
* **Priority Escalation:** Jika sisa waktu penyelesaian kurang dari 2 jam, prioritas tugas otomatis berubah menjadi **Urgent (Merah)** di dashboard FOP dan memicu *alert* internal agar admin cabang segera mengalokasikan teknisi cadangan (*backup technician dispatch*).

---

## 7. Rangkuman Portofolio Siap Pakai

### Bullet Points untuk Resume / CV (STAR Method)

```markdown
- Merancang dan membangun arsitektur BSS/OSS & Inventory ERP ISP berbasis Laravel & Next.js dengan pipeline penagihan customer-centric (State Machine: Draft → Lengkap → Siap Billing) guna mencegah kebocoran pendapatan.
- Mengimplementasikan Enterprise Inventory Ledger berbasis append-only transactions (Observer-enforced immutability) dengan proyeksi saldo CQRS dan pelacakan nomor seri (SN) modem dari distributor hingga retur terminasi pelanggan.
- Mengembangkan Hierarchical RBAC & Multi-POP Data Scoping dinamis yang memisahkan kapabilitas fungsi dari jangkauan data (pop_tree, assigned_only), mencegah role explosion dan menjamin isolasi data antar-cabang via Global Query Scopes.
- Membangun Portal Pelanggan terisolasi (Decoupled, zero direct DB access) dengan Dual-Layer Authentication (Client Secret + Bearer Token) serta integrasi event-driven webhook outbox tanpa eksposur PII.
- Menerapkan Transactional Pessimistic Locking (lockForUpdate) pada mutasi pembayaran guna mencegah double-spend saat latensi tinggi, serta menjaga integritas analitik churn historis pada siklus aktivasi ulang pelanggan.
- Mengintegrasikan manajemen topologi jaringan multi-level (POP → Distribusi → Pelanggan) dan early-warning system SLA dinamis untuk memprioritaskan tugas teknisi lapangan.
```

### Cheatsheet Tanya Jawab Wawancara (System Design & Architecture)

| Topik Pertanyaan | Jawaban Standard Developer | Jawaban Anda (Senior System Architect) |
|---|---|---|
| **Inventory Management** | *"Saya buat tabel barang, lalu kurangi kolom stok saat ada barang keluar."* | *"Saya merancang append-only immutable ledger di mana pergerakan stok dicatat sebagai mutasi transaksi yang diblokir dari update/delete via Observer. Saldo dan status nomor seri modem hanyalah CQRS-style read-projections. Kami melacak siklus fisik unit secara end-to-end hingga retur dan konsumsi otomatis dari custody teknisi."* |
| **Payment Concurrency** | *"Saya simpan pembayaran ke tabel dan ubah status invoice jadi lunas."* | *"Saya menerapkan Database Transactions dengan Pessimistic Locking (`lockForUpdate`) pada model Invoice untuk mencegah race conditions akibat double-submit kasir di jaringan berlatensi tinggi, serta mengintegrasikan rekonsiliasi tiga sumbu (Kas, Dokumen OCR, dan Kwitansi Portal Digital)."* |
| **Customer Portal Security** | *"Portal memanggil API Laravel menggunakan token JWT/Sanctum biasa."* | *"Portal dipisahkan total dari database operasional (zero direct DB access). Arsitektur menerapkan Dual-Layer Auth (Portal Client Secret + Bearer Token Pelanggan) dan event-driven webhook outbox tanpa PII. Saat pelanggan putus dan langganan lagi, akun dialihkan ke `pending_claim` untuk mencegah account hijacking."* |
| **Data Analytics Integrity** | *"Saat pelanggan aktif lagi, saya reset semua status dan tanggal terminasinya."* | *"Kami sengaja mempertahankan timestamp `terminated_at` saat pelanggan aktif kembali, karena metrik churn bulanan dihitung berdasarkan rentang historis. Mengosongkannya akan merusak laporan churn historis bulan lalu secara retroaktif."* |
