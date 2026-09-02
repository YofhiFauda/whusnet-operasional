# Inventory Tracking

## Lanjutan dari ./warehouse_inventory_asset_traceability_analysis.md
## Gambar pendukung ./image.png dan ./image-2.png

## Jenis Barang dan Cara Tracking

| Jenis Barang   | Tracking                       | Contoh                          | Cara Stok       |
| -------------- | ------------------------------ | ------------------------------- | --------------- |
| **Serialized** | Per unit + SN                  | Modem, ONT, Router, OLT module  | Per unit        |
| **Batch/Lot**  | Batch/Lot number               | Kabel fiber, material tertentu  | Per batch + qty |
| **Quantity**   | Jumlah                         | RJ45, cable tie, baut           | Qty             |
| **Asset**      | ID aset + identitas unit       | Laptop, HP, alat teknisi        | Per unit        |
| **Consumable** | Jumlah pemakaian               | Isolasi, sleeve, connector      | Qty             |
| **Sparepart**  | Qty / serial tergantung barang | Power supply, adaptor           | Qty atau unit   |
| **Returnable** | Per unit / SN                  | Tangga, alat ukur, alat teknisi | Per unit        |

---

### 1. Konsep yang Lebih Tepat
Saya akan ubah konsepnya menjadi:

```
                         PRODUCT
                            │
             ┌──────────────┼──────────────┐
             │              │              │
             ▼              ▼              ▼
        SERIALIZED       QUANTITY       BATCH/LOT
             │              │              │
             ▼              ▼              ▼
          Per Unit          Per Qty        Per Batch

```

Contoh:
```
**Modem**
Product:
ZTE F670L

Tracking:
SERIALIZED

SN:
ZTE001
ZTE002
ZTE003


**RJ45**
Product:
RJ45 CAT6

Tracking:
QUANTITY

Qty:
500

**Kabel Fiber**
Product:
Kabel Optik SC-SC

Tracking:
BATCH/LOT

Batch:
LOT-2026-001

Qty:
100

**Kemudian digunakan**
POP-PON
Used:
150 meter

Remaining:
4850 meter
```
---

### 2. Bahkan Satu Kategori Barang Bisa Punya Karakteristik Berbeda
Ini yang perlu kita desain dengan benar.

Misalnya:
```
**Adaptor**
Adaptor biasa
→ QUANTITY

Tetapi jika perusahaan menganggap setiap adaptor sebagai asset yang harus dilacak:
Adaptor
→ SERIALIZED / ASSET

Jadi jangan menentukan tracking berdasarkan nama barang.
Lebih baik Product mempunyai konfigurasi:

tracking_type
Misalnya:

SERIAL
QUANTITY
BATCH
ASSET
```
---

### 3. Barang Teknisi Juga Bisa Bermacam-macam
Misalnya Ahmad mengambil:
```
Teknisi Ahmad
```

```
Inventory-nya:
┌──────────────────────────────┐
│ TECHNICIAN INVENTORY         │
├──────────────────────────────┤
│                              │
│ Modem ZTE       3 unit       │
│   ├─ SN001                   │
│   ├─ SN002                   │
│   └─ SN003                   │
│                              │
│ RJ45            50 pcs       │
│                              │
│ Cable Tie       100 pcs      │
│                              │
│ Fiber           200 meter    │
│                              │
│ Power Adapter   2 unit       │
│                              │
│ Optical Power   1 unit       │
│   Asset ID: AST-00031        │
│                              │
└──────────────────────────────┘

Ini jauh lebih realistis untuk operasional teknisi ISP.
```

---

### 4. Contoh Ketika Teknisi Melakukan Instalasi
Misalnya teknisi melakukan pemasangan internet.
Dia menggunakan:
```
1x Modem
20x RJ45
30m Fiber
10x Cable Tie
1x Power Adapter
```

**Modem**
Karena serialized:
```
SN:
ZTE001

Usage:
INSTALLATION

```

**RJ45**

Karena quantity:
```
Before:
50

Used:
20

After:
30
```

**Fiber**

Jika menggunakan meter:
```
Before:
200m

Used:
30m

After:
170m
```

**Cable Tie**

```
Before:
100

Used:
10

After:
90
```


**Power Adapter**

Jika serialized:
```
Asset/SN:
PA001

Status:
INSTALLED
```
---

### 5. Ini Membuka Konsep "Material Usage"
Dan menurut saya ini justru sangat penting untuk sistem Anda.
Laporan pemasangan jangan hanya:
```
Customer
Technician
Modem SN
```

Tetapi:
```
INSTALLATION
│
├── Customer
├── Technician
├── Service
├── Date
│
└── MATERIAL USAGE
    │
    ├── Modem
    │    └── SN: ZTE001
    │
    ├── RJ45
    │    └── Qty: 20
    │
    ├── Fiber
    │    └── Qty: 30 meter
    │
    ├── Cable Tie
    │    └── Qty: 10
    │
    └── Adapter
         └── SN: PA001
```

Sehingga sistem bisa menghitung actual material consumption per installation.

---

### 6. Ini Juga Berguna untuk Costing
Misalnya:

```
Installation #001

Modem          Rp 350.000
RJ45 20 pcs    Rp 20.000
Fiber 30m      Rp 45.000
Cable Tie      Rp 5.000
Adaptor        Rp 50.000
────────────────────────
Material Cost  Rp 470.000
```

Kemudian perusahaan bisa mengetahui:

```
Berapa rata-rata biaya material untuk pemasangan pelanggan?
```

Bahkan bisa dianalisis berdasarkan:

- POP
- Teknisi
- Paket internet
- Wilayah
- Jenis instalasi
- Periode
- Produk

--- 

### 7. Pengelolaan Barang di Teknisi Juga Bermacam-macam

Teknisi bisa membawa barang dengan status:
```
On Custody
```

Contoh:
```
Teknisi: Budi

Modem ZTE         3 unit
  ├─ SN001
  ├─ SN002
  └─ SN003

Power Adapter     1 unit
  Asset ID: PA007

Router Mikrotik   1 unit
  Asset ID: RB-1100AX-05

Cable Tie         50 pcs
```

Barang-barang ini adalah inventory perusahaan yang sedang dipegang oleh teknisi.

---

### 8. Barang di Teknisi Bisa Berubah Status

Contoh alur:
```
Teknisi menerima barang dari POP:
ISSUED_TO_TECHNICIAN
   ↓
Teknisi melakukan instalasi:
INSTALLATION
   ↓
Modem terpasang di pelanggan.

Teknisi mengembalikan barang rusak:
RETURN_TO_POP
   ↓
Di POP bisa menjadi:
- DAMAGED
- REPAIR
- AVAILABLE
```

Atau:
```
Teknisi menjual barang ke pelanggan:
SOLD_TO_CUSTOMER
   ↓
Status modem berubah menjadi:
OWNED_BY_CUSTOMER
```

---

### 9. Ini Membuka Konsep Asset Management yang Sebenarnya

Karena barang seperti:
```
Router Mikrotik
OLT module
Power supply
Server
Laptop
Optical power meter
Fiber identifier
FTB-1
```

Adalah **asset** yang harus dilacak kepemilikannya, bukan hanya stok biasa.

---

### 10. Ini Juga Memungkinkan Analisis:

```
Berapa biaya material per installation?
Berapa biaya material per bulan?
Berapa biaya material per teknisi?
Berapa banyak barang yang hilang/rusak?
Berapa lama rata-rata umur modem di pelanggan?
Berapa persen barang yang dikembalikan?
```

---

### 11. Gudang Pusat Juga Tidak Hanya Menyimpan Modem
Misalnya:
```
CENTRAL WAREHOUSE

NETWORK DEVICE
├── Modem
├── ONT
├── Router
├── Access Point
└── OLT Module

CABLE & FIBER
├── Fiber Optic
├── UTP Cable
├── Patch Cord
└── Drop Cable

CONNECTOR
├── RJ45
├── SC Connector
├── LC Connector
└── Fiber Sleeve

ELECTRICAL
├── Power Adapter
├── Power Supply
└── Extension

INSTALLATION MATERIAL
├── Cable Tie
├── Isolasi
├── Clamp
├── Baut
└── Bracket

TOOLS / ASSETS
├── Optical Power Meter
├── OTDR
├── Crimping Tool
├── Tangga
└── Laptop
```
Semuanya dapat berada dalam satu sistem inventory, tetapi aturan tracking-nya berbeda.

---

### 12. Bahkan Bisa Dibuat Hierarki Product
Saya sarankan Master Barang dibuat seperti:

```
CATEGORY
   │
   └── PRODUCT
          │
          ├── Tracking Type
          ├── Unit
          ├── Minimum Stock
          ├── Maximum Stock
          ├── Reorder Point
          ├── Cost
          └── Active
```
Contoh:
```
Category:
Network Device

Product:
ZTE F670L

Tracking:
SERIALIZED

Unit:
PCS

Minimum:
10

Maximum:
50
```

Sedangkan:
```
Category:
Connector

Product:
RJ45 CAT6

Tracking:
QUANTITY

Unit:
PCS

Minimum:
100

Maximum:
500
```

Dan:

```
Category:
Fiber

Product:
Drop Cable 1 Core

Tracking:
QUANTITY

Unit:
METER

Minimum:
500

Maximum:
5000
```

---

### 13. Jadi Sistemnya Bisa Menjawab Banyak Pertanyaan
Bukan hanya:

```
"Berapa modem yang tersisa?"
```
Tetapi:

**Inventory**
```
Berapa stok RJ45 di seluruh POP?
```

**Asset**
```
OTDR sekarang dibawa siapa?
```

**Serial**
```
Modem SN001 sekarang berada di mana?
```


**Material Usage**
```
Berapa meter fiber digunakan bulan ini?
```


**Technician**

```
Berapa material yang dibawa Ahmad?
```

**Customer**
```
Modem apa yang terpasang di pelanggan X?
```

**Installation**
```
Material apa saja yang digunakan pada pemasangan X?
```


**POP**
```
Barang apa yang hampir habis di POP-PON?
```

**Audit**
```
Kenapa stok RJ45 berkurang 500 pcs?
```


**Traceability**
```
Dari mana modem SN001 berasal dan siapa yang terakhir memegangnya?
```

---

### 14. Maka Arsitektur Besarnya Menjadi

```
                         INVENTORY SYSTEM
                                │
        ┌───────────────────────┼────────────────────────┐
        │                       │                        │
        ▼                       ▼                        ▼
     PRODUCT                 INVENTORY                 ASSET
        │                       │                        │
        │                ┌──────┼──────┐                 │
        │                │      │      │                 │
        ▼                ▼      ▼      ▼                 ▼
   Tracking Type      SERIAL   QTY    BATCH          ASSET ID
        │                │      │      │                 │
        └────────────────┴──────┴──────┴─────────────────┘
                                │
                                ▼
                     INVENTORY TRANSACTION
                                │
             ┌──────────────────┼──────────────────┐
             ▼                  ▼                  ▼
         WAREHOUSE          TECHNICIAN          CUSTOMER
             │                  │                  │
             └──────────────────┼──────────────────┘
                                ▼
                         MATERIAL USAGE
                                │
                                ▼
                          INSTALLATION
                                │
                                ▼
                         CUSTOMER ASSET

```

Struktur yang lebih matang pada Analsisa kali ini :
```
Product → Tracking Type → Inventory → Transaction → Custody/Location → Usage → Asset/Customer
```

---

### 15. STATUS BARANG
---
AVAILABLE → Ada di gudang, siap ambil
RESERVED → Sudah diambil teknisi, belum dipasang
INSTALLED → Sudah terpasang di pelanggan
RETURNED → Dikembalikan (rusak/ganti)
LOST → Hilang (butuh BAP)
DAMAGED → Rusak, perlu klaim garansi
---

## 16. Koreksi untuk Konteks ISP Lokal

Fakta diverifikasi ulang di kode: kolom `items.type` (varchar) **sudah dihapus total** oleh migration `2026_08_01_000002_link_items_and_task_materials_to_item_categories.php`, diganti `item_category_id`. `Item` model sekarang cuma `code, name, item_category_id, unit, is_active`. Jadi nama kolom `tracking_type` yang diusulkan dokumen ini AMAN dipakai — tidak bentrok legacy apa pun.

### 16.1 7 jenis barang di §Jenis Barang → cukup 3 `tracking_type`

Consumable/Sparepart/Returnable itu properti PEMAKAIAN (habis vs balik), bukan cara hitung stok. **Koreksi:** `tracking_type` di `items` cukup **3 nilai** — `SERIALIZED`, `QUANTITY`, `BATCH`. "Asset" (§9: OTDR, laptop, Optical Power Meter, Router Mikrotik infra) **bukan** tracking_type keempat — itu `SERIALIZED` + flag kepemilikan/lifecycle berbeda (lihat 16.2). Tracking_type 4 macam bikin query "semua barang per-unit" harus selalu union 2 tipe — redundan.

### 16.2 Boundary "Asset" vs "Installable Device" wajib eksplisit

Keduanya sama-sama `SERIALIZED` tapi lifecycle beda total. Modem/ONT (installable) berakhir di `INSTALLED` (custody pindah ke pelanggan, gak balik). OTDR/laptop/Optical Power Meter (company asset/tools) **tidak pernah** `INSTALLED` — cuma looping `ISSUED ⇄ RETURNED`.

**Koreksi:** tambah kolom `ownership_mode` (`installable` | `company_asset`) di `items`, bukan tracking_type baru. State machine cabang berdasarkan ini: `installable` boleh transisi ke `INSTALLED`, `company_asset` dilarang keras transisi ke situ (guard di level Service/Observer — biar OTDR gak nyasar ketag "terpasang di pelanggan"). Ini nyambung ke `work_tools`/`task_work_tools` yang **sudah ada** (sengaja tanpa qty/kepemilikan) — kalau `company_asset` mau dilacak per-unit (OTDR punya SN, bukan cuma checklist "dibawa/tidak"), itu perluasan `work_tools` (tambah `serial_number` opsional) di fase Inventory, **bukan** entity custody baru yang duplikat `work_tools`.

### 16.3 BATCH/LOT — gap nyata, tapi jangan bangun genealogy penuh

`task_materials` sekarang cuma `qty` desimal polos, gak ada dimensi "drum/lot mana". Buat skala ISP lokal, `BATCH` cukup jadi **QUANTITY + tag `lot_no` opsional** di baris `inventory_transactions`/receiving (satu baris = satu drum, `qty_remaining` didecrement biasa) — bukan tabel `batches` terpisah dengan split/merge/expiry ala farmasi/food. Cukup jawab "drum LOT-2026-001 sisa berapa meter".

### 16.4 Minimum/Maximum/Reorder/Cost (§12) — jangan masuk `items`, dan per-gudang bukan global

Batch C (`docs/TASKS.md:1043-1056`) tegas: `items` tetap minimum, stok/harga wilayah Inventory yang MENAMBAH tabel di atasnya, bukan mengubah `items`. Lebih jauh: minimum/maximum stok itu **per-gudang**, bukan per-produk global — POP-PON butuh minimum modem beda dari POP-MADIUN. **Koreksi:** taruh `minimum_stock`/`maximum_stock`/`reorder_point` di `inventory_balances` (per `warehouse_id` + `item_id`), bukan di `items`. Harga cukup di ledger (`unit_price_snapshot`, sudah disiapkan di `task_materials`) sebagai harga-saat-pakai — bukan kolom "cost" statis di master produk yang gampang basi.

### 16.5 `SOLD_TO_CUSTOMER`/`OWNED_BY_CUSTOMER` (§8) — didrop

Dikonfirmasi user: CPE company-owned semua, ditarik balik saat pelanggan berhenti — gak ada skema jual putus. State machine cukup `INSTALLED → REMOVED → RETURNED` (konsisten dengan flow "Customer Termination dan Device Return" di dokumen pertama). Status terminal `OWNED_BY_CUSTOMER`/transisi `SOLD_TO_CUSTOMER` **tidak diadopsi** — jangan dibuatkan kolom/status untuk ini.

### 16.6 Status §15 vs dokumen pertama — satu acuan saja

Daftar status di §15 (dokumen ini) lebih pendek dari lifecycle penuh di dokumen pertama (`RECEIVED/AVAILABLE/RESERVED/ISSUED/IN_USE/INSTALLED/TRANSFERRED/RETURNED/DAMAGED/LOST/SCRAPPED/QUARANTINE`). **Koreksi:** jangan implementasi dua state machine. Daftar dokumen pertama jadi **satu-satunya** acuan status resmi (punya `QUARANTINE`/`IN_USE`/`TRANSFERRED` yang lebih lengkap); daftar §15 di sini cukup dianggap subset ilustratif.

### 16.7 Costing (§6) — menegaskan ulang `unit_price_snapshot`

Tanpa kolom itu terisi konsisten di tiap `ISSUE`/pemakaian, laporan "rata-rata biaya material per pemasangan" salah begitu harga master naik. Tetap prasyarat wajib sebelum modul jalan (sudah diflag di analisa pertama §29.4).

### 16.8 Pentahapan (biar gak overengineer)

- **Fase 1 (MVP):** cuma `tracking_type` SERIALIZED + QUANTITY (fiber-per-meter masuk QUANTITY, unit=meter, TANPA lot dulu). Cukup buat modem/ONT/router (serial) + material habis pakai (qty). `ownership_mode` installable vs company_asset **wajib dari awal** — murah bikinnya, mahal dikoreksi belakangan.
- **Fase 2 (nunggu kebutuhan riil):** `BATCH`/`lot_no` tagging, `company_asset` per-serial (extend `work_tools`), min/max per-warehouse + reorder alert.
- **Didrop:** `SOLD_TO_CUSTOMER`/`OWNED_BY_CUSTOMER` — poin 16.5.