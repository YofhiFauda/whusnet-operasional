# Analisis & Rancangan Sistem Centralized Inventory & Asset Traceability

## 1. Konsep Besar Sistem

Sistem yang dibutuhkan bukan sekadar aplikasi stok/gudang, tetapi **Centralized Inventory & Asset Traceability System**.

Struktur utama:

```text
                    CENTRAL WAREHOUSE
                           │
                 Stock Distribution
                           │
          ┌────────────────┼────────────────┐
          ▼                ▼                ▼
        POP A            POP B            POP C
          │                │                │
          ▼                ▼                ▼
     TECHNICIANS      TECHNICIANS      TECHNICIANS
          │                │                │
          ▼                ▼                ▼
       CUSTOMER          CUSTOMER          CUSTOMER
```

Terdapat tiga level utama inventory:

### 1.1 Gudang Pusat

Bertanggung jawab terhadap:

- Stok utama.
- Penerimaan barang.
- Distribusi ke POP.
- Monitoring kebutuhan POP.
- Replenishment.
- Procurement/restock.
- Stok minimum dan maksimum.
- Audit inventory.

### 1.2 Gudang POP

Setiap POP mempunyai inventory sendiri.

Contoh:

```text
POP-PON
├── Modem
├── ONT
├── Router
├── Kabel
├── Connector
├── Adaptor
└── Sparepart
```

Gudang POP dapat:

- Menerima barang dari Gudang Pusat.
- Menyimpan barang.
- Mengeluarkan barang ke teknisi.
- Menerima barang kembali dari teknisi.
- Melakukan stock adjustment.
- Melihat penggunaan barang.
- Mengajukan kebutuhan barang ke Gudang Pusat.

### 1.3 Teknisi

Teknisi bukan hanya user, tetapi juga dapat menjadi **custodian inventory**.

Contoh:

```text
Teknisi: Ahmad

Modem:
  ZTE-ABC001
  ZTE-ABC002
  ZTE-ABC003

ONT:
  HW-ONT001
  HW-ONT002
```

Barang yang sudah diberikan kepada teknisi belum tentu sudah digunakan.

---

# 2. Bedakan Stok dan Custody

Jangan hanya menggunakan konsep:

```text
stock = 95
```

Gunakan pemisahan antara stok tersedia dan barang yang sedang berada dalam custody teknisi.

Contoh:

```text
POP Warehouse
Available       : 95
Issued          : 5

Technician A
Custody         : 3

Technician B
Custody         : 2
```

Barang masih menjadi bagian dari inventory perusahaan, tetapi custody-nya sudah berpindah dari gudang ke teknisi.

---

# 3. Inventory Ledger

Jangan menjadikan angka stok sebagai satu-satunya sumber histori.

Gunakan **inventory transaction/ledger**.

Contoh:

| Waktu | Transaksi | Barang | Qty | Dari | Ke |
|---|---|---|---:|---|---|
| 09:00 | RECEIVE | Modem | 50 | Supplier | Gudang Pusat |
| 10:00 | TRANSFER | Modem | 20 | Pusat | POP-PON |
| 13:00 | ISSUE | Modem | 2 | POP-PON | Teknisi A |
| 14:00 | INSTALL | Modem SN001 | 1 | Teknisi A | Customer |
| 15:00 | RETURN | Modem SN002 | 1 | Teknisi A | POP-PON |

Dengan ledger, sistem dapat menjawab:

> "Modem SN001 sekarang berada di mana?"

Secara deterministik.

---

# 4. Serial Number sebagai Entitas Penting

Untuk barang serialized, setiap unit memiliki identitas unik.

```text
Product
   │
   ├── Modem ZTE F670L
   │
   ├── SN001
   ├── SN002
   ├── SN003
   └── SN004
```

Contoh lifecycle:

```text
SN001
   │
   ▼
RECEIVED
   │
   ▼
CENTRAL_WAREHOUSE
   │
   ▼
TRANSFERRED
   │
   ▼
POP-PON
   │
   ▼
ISSUED
   │
   ▼
TECHNICIAN-A
   │
   ▼
INSTALLED
   │
   ▼
CUSTOMER
```

Data yang dapat diketahui dari sebuah SN:

```text
SN001
Product       : ZTE F670L
Status        : INSTALLED
Location      : Customer
Technician    : Ahmad
Customer      : CUST-000123
Installed     : 2026-09-02
```

---

# 5. Validasi Serial Number pada Laporan Pemasangan

Ketika teknisi membuat Laporan Pemasangan dan memasukkan SN modem, sistem tidak cukup hanya memeriksa apakah SN ada di database.

Sistem harus memvalidasi:

```text
Apakah SN ada?
        │
        ▼
Apakah barang serialized?
        │
        ▼
Apakah status valid?
        │
        ▼
Apakah sedang berada pada teknisi tersebut?
        │
        ▼
Apakah belum pernah terpasang?
        │
        ▼
Apakah compatible dengan layanan?
        │
        ▼
VALID
```

Contoh:

Teknisi Ahmad memiliki:

```text
ZTE001
ZTE002
ZTE003
```

Maka:

```text
ZTE002 → Allowed
```

Sedangkan:

```text
ZTE999
```

yang masih berada di Gudang Pusat:

```text
→ Rejected
```

Dan SN yang sudah terpasang pada pelanggan lain juga:

```text
→ Rejected
```

Ini merupakan konsep **inventory custody validation**.

---

# 6. Serialized dan Non-Serialized Item

Tidak semua barang harus menggunakan Serial Number.

## 6.1 Serialized Item

Setiap unit mempunyai identitas unik.

Contoh:

- Modem.
- ONT.
- Router.
- ONU.
- Access Point.
- Perangkat elektronik.
- Modul perangkat tertentu.

Contoh:

```text
MOD-ZTE-F670L
SN: ZTE123456
```

## 6.2 Non-Serialized Item

Dikelola berdasarkan quantity.

Contoh:

- Kabel UTP.
- RJ45.
- Cable tie.
- Connector.
- Baut.
- Fiber patch cord.
- Isolasi.

Contoh:

```text
RJ45 CAT6
Qty: 500
```

Product dapat memiliki:

```text
tracking_type

SERIALIZED
QUANTITY
```

---

# 7. Lifecycle Status Barang

Jangan hanya menggunakan status "Ada/Tidak Ada".

Contoh lifecycle:

```text
RECEIVED
   ↓
AVAILABLE
   ↓
RESERVED
   ↓
ISSUED
   ↓
IN_USE
   ↓
INSTALLED
```

Kemungkinan lainnya:

```text
AVAILABLE
   ↓
TRANSFERRED
   ↓
AVAILABLE
```

```text
ISSUED
   ↓
RETURNED
   ↓
AVAILABLE
```

```text
INSTALLED
   ↓
REMOVED
   ↓
RETURNED
   ↓
INSPECTION
   ↓
AVAILABLE / DAMAGED
```

Status tambahan:

```text
DAMAGED
LOST
SCRAPPED
```

---

# 8. Monitoring Kebutuhan POP

Gudang Pusat harus dapat mengetahui kebutuhan setiap POP.

Contoh:

```text
POP-PON

Modem
Current Stock : 8
Minimum Stock : 10
Maximum Stock : 30
```

Sistem mendeteksi:

```text
LOW STOCK
```

Kemudian menghitung rekomendasi:

```text
Current : 8
Minimum : 10
Maximum : 30

Recommended Replenishment:
22
```

---

# 9. Stock Request

POP dapat membuat permintaan barang ke Gudang Pusat.

Contoh:

```text
REQ-POP-PON-000123

Requested by:
POP PON

Item:
Modem ZTE F670L

Current Stock:
8

Requested:
22

Reason:
Below Minimum Stock
```

Flow:

```text
POP
 │
 │ Request
 ▼
Gudang Pusat
 │
 │ Review
 ▼
APPROVED
 │
 ▼
PICKING
 │
 ▼
TRANSFER
 │
 ▼
POP
 │
 ▼
RECEIVED
```

Status:

```text
DRAFT
SUBMITTED
REVIEWED
APPROVED
REJECTED
PICKING
SHIPPED
RECEIVED
CANCELLED
```

---

# 10. Stock Transfer

Contoh transfer dari Gudang Pusat:

```text
TRF-20260902-0001

FROM:
Gudang Pusat

TO:
Gudang POP-PON

Items:
Modem  : 20
Router : 10
RJ45   : 50
```

Untuk barang serialized, SN harus dicatat ketika picking.

Contoh:

```text
Modem
SN:
ZTE001
ZTE002
ZTE003
...
```

---

# 11. Picking

Operator gudang tidak seharusnya hanya mengurangi angka stok.

Flow:

```text
Transfer Request
       ↓
Picking
       ↓
Scan SN
       ↓
Validation
       ↓
Packed
       ↓
Shipped
```

Ketika scan:

```text
ZTE001
```

Sistem memvalidasi:

```text
✓ Valid
✓ Available
✓ Belongs to Central Warehouse
✓ Not Reserved
```

Kemudian:

```text
ZTE001 → PICKED
```

---

# 12. Receiving di POP

POP melakukan penerimaan berdasarkan barang yang diharapkan.

Contoh:

```text
Expected:
20

Received:
20
```

Jika terdapat mismatch:

```text
Expected:
ZTE001

Received:
ZTE099
```

Sistem:

```text
SERIAL NUMBER MISMATCH
```

Barang tidak boleh langsung masuk ke available inventory tanpa proses verifikasi.

---

# 13. Teknisi Mengambil Barang

Contoh teknisi mengambil:

```text
Modem
Qty: 2

SN:
SN001
SN002
```

Transaksi:

```text
ISSUE

FROM:
POP-PON

TO:
TECH-AHMAD
```

Saldo berubah:

```text
POP Available:
100 → 98

Ahmad Custody:
0 → 2
```

---

# 14. Issued Tidak Sama dengan Installed

Ini penting untuk audit.

Teknisi mengambil 5 modem:

```text
Issued = 5
```

Namun hanya 3 yang dipasang:

```text
Installed = 3
```

Maka:

```text
Issued     = 5
Installed  = 3
Remaining  = 2
```

Dua modem masih berada dalam custody teknisi.

---

# 15. Setelah Pemasangan

Contoh:

```text
Customer:
CUST-000123

Modem:
SN001

Technician:
AHMAD

Installation:
INST-20260902-0012
```

Relasi:

```text
SN001
 │
 ├── Product: ZTE F670L
 ├── Technician: Ahmad
 ├── Customer: CUST-000123
 ├── Installation: INST-20260902-0012
 ├── Installed At: 2026-09-02
 └── Status: INSTALLED
```

Sistem dapat melakukan **asset traceability**.

---

# 16. Customer Termination dan Device Return

Ketika pelanggan berhenti berlangganan:

```text
INSTALLED
    ↓
SERVICE TERMINATED
    ↓
DEVICE REMOVED
    ↓
RETURNED
    ↓
INSPECTION
```

Hasil inspeksi:

```text
GOOD
```

→ kembali ke inventory.

Atau:

```text
DAMAGED
```

→ masuk repair/quarantine.

---

# 17. Quarantine

Disarankan memiliki status/lokasi **QUARANTINE**.

Digunakan untuk barang:

- Rusak.
- Belum diverifikasi.
- SN bermasalah.
- Retur customer.
- Selisih audit.
- Menunggu pemeriksaan.

Flow:

```text
QUARANTINE
    │
    ├── APPROVED → AVAILABLE
    │
    ├── REPAIR → REPAIR
    │
    └── SCRAP → SCRAPPED
```

Barang tidak boleh langsung menjadi AVAILABLE sebelum pemeriksaan selesai.

---

# 18. Stock Adjustment

Perubahan inventory tidak boleh dilakukan dengan mengedit angka stok secara langsung.

Contoh hasil stock opname:

```text
System:
100

Physical:
98

Difference:
-2
```

Buat transaksi:

```text
ADJUSTMENT

System Qty : 100
Physical   : 98
Difference : -2

Reason:
Physical Stock Audit

Approved By:
Warehouse Manager
```

Ledger mencatat:

```text
ADJUSTMENT -2
```

Dengan demikian histori tetap tersedia.

---

# 19. Audit Trail

Setiap perubahan penting harus dapat diketahui:

```text
Who
What
When
Where
Why
Before
After
```

Contoh:

```text
2026-09-02 08:15

User:
Warehouse Admin

Action:
ISSUE ITEM

Item:
Modem ZTE F670L

SN:
ZTE123456

From:
POP-PON

To:
Technician Ahmad

Reference:
ISS-20260902-0012
```

---

# 20. Laporan yang Dapat Dihasilkan

## Stock Summary

```text
Gudang Pusat

Modem      250
Router     120
ONT        180
```

## POP Stock

```text
POP PON

Modem      20
Router      8
ONT        13
```

## Technician Custody

```text
Ahmad

Modem       3
ONT         2
Router      1
```

## Usage

```text
Modem

Received       500
Transferred    350
Issued         300
Installed      280
Returned        20
Damaged          5
Lost             2
Available      213
```

## Installation

Sistem dapat menelusuri:

```text
Modem SN
     ↓
Installation
     ↓
Customer
     ↓
Technician
     ↓
Date
```

---

# 21. Asset Traceability

Fitur penting:

> "Where is this item?"

Input:

```text
SN: ZTE123456
```

Hasil:

```text
ZTE123456
ZTE F670L

Status:
INSTALLED

Current Location:
Customer

Customer:
CUST-000123

Technician:
Ahmad

POP:
POP-PON

Installed:
02 Sep 2026
```

Histori:

```text
01 Sep
Supplier
   ↓
Gudang Pusat

01 Sep
Gudang Pusat
   ↓
POP-PON

02 Sep
POP-PON
   ↓
Ahmad

02 Sep
Ahmad
   ↓
Customer
```

---

# 22. Struktur Modul Aplikasi

```text
WAREHOUSE
│
├── Dashboard
│
├── Master Data
│   ├── Products
│   ├── Categories
│   ├── Units
│   ├── Warehouses
│   ├── Warehouse Locations
│   └── Serial Numbers
│
├── Inventory
│   ├── Stock Overview
│   ├── Stock by Warehouse
│   ├── Serialized Items
│   ├── Non-Serialized Items
│   └── Stock Movement
│
├── Procurement / Receiving
│   ├── Goods Receipt
│   └── Receiving History
│
├── Distribution
│   ├── Stock Requests
│   ├── Picking
│   ├── Transfers
│   └── Receiving Confirmation
│
├── Technician
│   ├── Technician Stock
│   ├── Issue Item
│   ├── Return Item
│   └── Technician History
│
├── Installation
│   ├── Installation Reports
│   ├── Installed Devices
│   └── Device Assignment
│
├── Returns
│   ├── Customer Return
│   ├── Technician Return
│   ├── Inspection
│   └── Repair / Quarantine
│
├── Stock Control
│   ├── Stock Opname
│   ├── Adjustment
│   ├── Lost Items
│   └── Damaged Items
│
├── Reports
│   ├── Stock Report
│   ├── Usage Report
│   ├── Movement Report
│   ├── Technician Report
│   ├── Installation Report
│   └── Asset Traceability
│
└── Audit
    ├── Inventory Ledger
    ├── Activity Log
    └── Audit Trail
```

---

# 23. Konsep Database Utama

Secara konseptual, database dapat terdiri dari:

```text
warehouses
warehouse_locations

products
product_categories
product_units

inventory_items
inventory_serials

inventory_balances
inventory_transactions

stock_requests
stock_request_items

stock_transfers
stock_transfer_items

stock_issues
stock_issue_items

technician_inventories

installations
installation_items

customer_assets

inventory_adjustments
inventory_adjustment_items

stock_opnames
stock_opname_items

quarantines
repairs

audit_logs
```

Namun tabel-tabel tersebut sebaiknya tidak langsung dibuat semuanya. Domain model, lifecycle, dan business rules perlu ditetapkan terlebih dahulu agar tidak terjadi over-engineering.

---

# 24. Relasi Inti

```text
PRODUCT
   │
   ├───────────────┐
   ▼               ▼
SERIAL ITEM     STOCK BALANCE
   │
   ▼
INVENTORY TRANSACTION
   │
   ├── Warehouse
   ├── POP
   ├── Technician
   └── Customer
```

Untuk serialized item:

```text
SERIAL ITEM
     │
     ├── Current Location
     ├── Current Custodian
     ├── Current Status
     │
     └── Transaction History
```

---

# 25. Prinsip Arsitektur Utama

Prinsip yang sangat disarankan:

> **Jangan menjadikan `stock` sebagai sumber kebenaran utama. Jadikan inventory transaction/ledger sebagai sumber histori, sedangkan balance adalah state yang dapat diverifikasi terhadap ledger.**

Hindari menjadikan operasi seperti ini sebagai satu-satunya proses:

```text
UPDATE products
SET stock = stock - 1
```

Lebih baik:

```text
Create Inventory Transaction
        ↓
ISSUE
        ↓
Serial / Quantity Validation
        ↓
Update Inventory Balance
        ↓
Create Audit Log
```

Dengan demikian, ketika ditanya:

> "Kenapa stok modem berkurang 37 unit?"

sistem dapat menjelaskan:

```text
12 unit → Installation
10 unit → Technician Issue
8 unit  → Transfer
5 unit  → Damaged
2 unit  → Adjustment
```

Bukan sekadar:

```text
stock = 143
```

---

# 26. Integrasi dengan Sistem Customer, Service, Installation, dan Technician

Inventory dapat menjadi bagian dari **end-to-end service lifecycle**:

```text
CUSTOMER
   │
   ▼
ORDER / REGISTRATION
   │
   ▼
INSTALLATION REQUEST
   │
   ▼
TECHNICIAN ASSIGNMENT
   │
   ▼
TECHNICIAN TAKES DEVICE
   │
   ▼
SERIAL NUMBER ASSIGNMENT
   │
   ▼
INSTALLATION
   │
   ▼
CUSTOMER ASSET
   │
   ▼
ACTIVE SERVICE
```

Ketika teknisi memasukkan:

```text
SN Modem = ZTE123456
```

sistem mencari dan memvalidasi:

```text
ZTE123456
    │
    ├── Product = ZTE F670L
    ├── Status = ISSUED
    ├── Custodian = Technician Ahmad
    ├── POP = POP-PON
    └── Available for Installation = YES
```

Setelah laporan pemasangan disubmit:

```text
TECHNICIAN
     ↓
CUSTOMER ASSET
     ↓
INSTALLED
```

Custody teknisi otomatis berkurang.

---

# 27. Inventory Control Tower

Konsep dashboard tingkat enterprise dapat menyediakan overview:

```text
┌────────────────────────────────────────────────────┐
│                 INVENTORY CONTROL                   │
├────────────────────────────────────────────────────┤
│                                                    │
│ Total Items          12,540                       │
│ Serialized            3,240                       │
│ Available             7,821                       │
│ Technician Custody      421                       │
│ Installed             3,902                       │
│ Quarantine              112                       │
│                                                    │
├────────────────────────────────────────────────────┤
│ LOW STOCK POPS                                     │
│                                                    │
│ POP-PON       Modem       8 / Min 20      LOW     │
│ POP-MADIUN    ONT         4 / Min 15      LOW     │
│ POP-NGAWI     Router     11 / Min 10      OK      │
│                                                    │
├────────────────────────────────────────────────────┤
│ RECENT MOVEMENTS                                   │
│                                                    │
│ ZTE123456 → Technician Ahmad                     │
│ ZTE123457 → Customer CUST-00123                  │
│ 20 Modem    → POP-MADIUN                         │
│                                                    │
└────────────────────────────────────────────────────┘
```

---

# 28. Kesimpulan

Sistem yang dibutuhkan lebih tepat disebut:

> **Centralized Inventory & Asset Traceability System**

Bukan sekadar:

> **Aplikasi Gudang**

Fondasi utamanya:

```text
                    CENTRAL WAREHOUSE
                           │
                 Stock Distribution
                           │
          ┌────────────────┼────────────────┐
          ▼                ▼                ▼
        POP A            POP B            POP C
          │                │                │
          ▼                ▼                ▼
     TECHNICIANS      TECHNICIANS      TECHNICIANS
          │                │                │
          ▼                ▼                ▼
       CUSTOMER          CUSTOMER          CUSTOMER
```

Setiap barang memiliki lifecycle yang dapat diaudit:

```text
SUPPLIER
   ↓
CENTRAL
   ↓
POP
   ↓
TECHNICIAN
   ↓
INSTALLATION
   ↓
CUSTOMER
   ↓
RETURN / REPAIR / SCRAP
```

Untuk barang serialized:

```text
Serial Number
     ↓
Current Status
     ↓
Current Location
     ↓
Current Custodian
     ↓
Customer Asset
     ↓
Complete Transaction History
```

## Next Design Stage

Sebelum implementasi Laravel, tahap berikutnya yang paling tepat adalah:

1. **Business Rules & Inventory Lifecycle**
2. **ERD / Database Schema**
3. **State Machine untuk Inventory**
4. **Role & Permission**
5. **Flow Stock Request → Transfer → Receiving**
6. **Flow Issue → Technician → Installation**
7. **Flow Return → Inspection → Repair/Quarantine**
8. **Serial Number Validation Rules**
9. **Inventory Ledger & Audit Architecture**
10. **UI/UX setiap modul**
11. **API & Service Layer Laravel**
12. **Reconciliation dan Stock Opname**

Dokumen ini menjadi **baseline konseptual** untuk pengembangan modul Warehouse/Inventory selanjutnya.

---

## 29. Koreksi untuk Konteks ISP Lokal (Gudang Pusat = Perusahaan, Anak Gudang = POP)

Konsep di atas ditulis generik ala enterprise multi-warehouse. Untuk skala **ISP lokal** dengan struktur "Gudang Pusat = Perusahaan, Anak Gudang = POP", ada beberapa koreksi wajib sebelum lanjut ke ERD — dicocokkan ke struktur data yang **sudah ada** di codebase (`app/Models/Pop.php`, `Item`/`ItemCategory`/`WorkTool`/`TaskMaterial`/`TaskWorkTool`, `CustomerTechnicalDetail`), bukan dirancang dari nol.

### 29.1 "Anak Gudang = POP" perlu diperjelas — `pops` sudah 3 level

`pops` sudah hierarkis: `pusat → cabang → mini_pop` (`parent_id` self-referencing, kolom `type`). Mini POP itu titik distribusi fisik (klaster ODC), **bukan** kantor cabang berstaf — tidak masuk akal punya gudang + admin gudang sendiri di situ.

**Koreksi:** Anak Gudang cuma di level `type=cabang`. `type=pusat` = Gudang Pusat. `type=mini_pop` **nol** gudang — stok teknisi yang kerja di wilayah mini_pop tetap nempel ke gudang cabang induknya (`parent_id`).

### 29.2 RBAC & POP Scope wajib eksplisit — draf awal gak menyebutnya sama sekali

Aturan keras repo: **dilarang bikin role per cabang**, dan **setiap query wajib lewat POP scope**. Draf awal implisit mengasumsikan tiap POP punya "admin gudang" sendiri — kalau diimplementasi naif jadi role per cabang (`Admin Gudang PON`, dst), melanggar aturan itu.

**Koreksi:** satu role global baru (mis. `gudang`, atau extend `pop_admin`) + permission `warehouse.*` lewat matrix `features`×`actions` yang sudah ada. Otorisasi per-cabang dibatasi lewat **POP scope** (`EffectiveAccessService::getAllowedPopIds()`), pola sama persis modul lain — bukan bikin gerbang otorisasi baru.

### 29.3 Jangan duplikasi tabel yang sudah ada — risiko dua sumber kebenaran

§23 mengusulkan `installation_items`, `stock_issue_items`, `customer_assets` baru. Tapi:

- Konsumsi material pemasangan **sudah** dicatat `task_materials` (anchor `fop_task_id`, kolom `kind` estimasi/terpakai, `unit_price_snapshot` sudah disiapkan tapi masih kosong — lihat 29.4). **Jangan** bikin `installation_items` baru — sambungkan `inventory_transactions` baru ke `task_materials.item_id` yang sudah ada.
- SN device terpasang di pelanggan **sudah** tercatat di `customer_technical_details.router_or_ont_serial` (+ `odp_number`/`odp_port`/`olt_number`/`olt_slot`/`vlan`/`passive_device*`). **Jangan** bikin `customer_assets` sebagai sumber kebenaran kedua soal "device apa terpasang di pelanggan mana" — repo eksplisit melarang pola dua-sumber-kebenaran-yang-gampang-menyimpang di modul lain (lihat CLAUDE.md § sinkronisasi Ticket/FopTask). Kalau butuh histori serial lengkap, `inventory_serials`/ledger cukup **menunjuk** `customer_id` saat status `INSTALLED`, bukan menyalin ulang field device.
- `work_tools` **sengaja** tanpa qty/kepemilikan (didokumentasikan di migration-nya). Kalau mau dilacak custody-nya, itu perluasan `task_work_tools`/`work_tools`, bukan tabel custody terpisah.

### 29.4 Tiga pertanyaan Batch C (`docs/TASKS.md`, keputusan user 2026-07-31) wajib dijawab desain ini

1. **Stok per-POP atau global** → per Anak Gudang cabang (konsisten dengan struktur di atas).
2. **Siapa berwenang mengurangi stok** → decrement terjadi di **ISSUE** (gudang cabang → custody teknisi), **bukan** di titik submit Laporan Pemasangan. `task_materials` (kind=terpakai) cuma catat konsumsi aktual — jangan sampai stok kepotong dua kali (sekali di ISSUE, sekali lagi saat laporan disubmit).
3. **`task_materials.unit_price_snapshot` wajib diisi** begitu modul ini jalan — harga **saat dipakai**, bukan harga master saat laporan dibuka nanti. Tanpa ini laporan biaya lama berubah nilai tiap harga master naik (sudah diperingatkan di migration `task_materials`, kolomnya sudah ada, masih kosong).

### 29.5 Skala pipeline kebesaran untuk ISP lokal

§9–11 (Stock Request 9-status: DRAFT→SUBMITTED→REVIEWED→APPROVED→REJECTED→PICKING→SHIPPED→RECEIVED→CANCELLED + scan-per-SN picking) adalah pola 3PL/enterprise. ISP lokal dengan sedikit cabang + volume rendah tidak butuh approval berlapis segitu.

**Koreksi (MVP fase 1):** satu alur simpel — `TRANSFER` (Pusat kirim, catat SN kalau serialized) → `RECEIVE` (Cabang konfirmasi, mismatch di-flag) — tanpa status Request/Review/Picking terpisah. Approval berlapis + stock request formal boleh menyusul di fase 2 kalau kebutuhan riil muncul (prinsip repo: jangan otomatisasi/formalisasi sebelum flow manual stabil).

### 29.6 Penomoran harus nyambung ke `PopSequence`

Prefix `TRF-`/`REQ-`/`ISS-` di §9–10 sudah konsisten pola `TKT-`/`TFOP-`/`TASK-`, tapi belum diputuskan: sequence global atau per-cabang (seperti CID pelanggan pakai `PopSequence` per POP)? Putuskan dulu, lalu daftarkan ke `docs/ID_NUMBERING_RULES.md` — jangan generate nomor tanpa didaftarkan, risiko tabrakan dengan seri lain.

### 29.7 Repair/RMA vendor — cukup catatan, jangan modul penuh

ISP lokal biasanya kirim modem rusak balik ke distributor untuk retur/garansi, bukan diperbaiki sendiri. Cukup field `repair_reference`/catatan RMA di record quarantine. **Jangan** bikin modul vendor/RMA penuh — kompleksitas tidak sepadan skala ISP lokal.

### 29.8 Valuasi harga — last-cost, jangan FIFO/average

Draf tidak menyebut metode costing. Untuk skala ISP lokal: cukup **last-cost** (harga pembelian terakhir jadi default `unit_price_snapshot`). FIFO/weighted-average kompleksitasnya tidak sepadan manfaat di skala ini.

### 29.9 Struktur & tabel minimum fase 1 (revisi §22–23)

```text
Gudang Pusat (Pop type=pusat)
   │ TRANSFER (+SN kalau serialized)
   ▼
Gudang Cabang (Pop type=cabang)   ← BUKAN mini_pop
   │ ISSUE (decrement stock di sini, bukan saat instalasi)
   ▼
Teknisi (custody)
   │ dipakai → task_materials (kind=terpakai) / SN masuk customer_technical_details
   ▼
Pelanggan (asset — sumber kebenaran TETAP customer_technical_details, bukan tabel baru)
```

Tabel minimum fase 1 (bukan semua daftar §23):

- `warehouses` — row per Pop `type` pusat/cabang, **reuse `pops.id` sebagai FK**, jangan bikin master lokasi terpisah kalau POP sudah cukup.
- `inventory_balances` — `warehouse_id` + `item_id` (atau `serial_id`).
- `inventory_transactions` — ledger RECEIVE/TRANSFER/ISSUE/RETURN/ADJUSTMENT, referensi `item_id` existing + `fop_task_id` kalau terkait task.
- `inventory_serials` — untuk item serialized: status + custodian/lokasi saat ini, menunjuk `customer_id`/`customer_technical_details` saat `INSTALLED` (bukan menyalin field device).
- Permission baru `warehouse.*` di matrix existing, scope lewat `EffectiveAccessService`.

**Fase 2 (tunda sampai ada kebutuhan riil):** stock request/approval formal, alert minimum/maximum stock, quarantine/repair RMA reference terstruktur, reporting/control tower dashboard (§20, §27 tetap relevan sebagai referensi ke depan, bukan syarat rilis pertama).
