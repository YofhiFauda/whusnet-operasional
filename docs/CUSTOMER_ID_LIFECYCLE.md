# Siklus Hidup ID Pelanggan (Customer ID Lifecycle)

> Dokumen ini menjelaskan format, aturan, dan perilaku sistem untuk identitas pelanggan
> mulai dari registrasi, aktivasi, hingga terminasi.

---

## 1. Ringkasan Siklus

```
[REGISTRASI]
Admin pilih POP/Cabang → Sistem generate customer_code
customer_code = C00RQ000001
Bersifat PERMANEN — tidak berubah seumur hidup pelanggan

        ↓

[PROSES (Survey, Pemasangan, dll)]
Tampilkan: customer_code = C00RQ000001
Teknisi mengisi olt_number di data teknis

        ↓

[AKTIVASI]
Sistem generate CID dari: POP + OLT + Distribusi + customer_code + Desa + Nama
CID = C1X1AC00RQ000001_MANGKUJAYAN_DYAHPURBA
Tampilkan: CID

        ↓

[SUSPENDED]
Tampilkan: CID (masih aktif secara billing)

        ↓

[TERMINATED / PUTUS]
Tampilkan: RQ000001  (hanya nomor urut bare — tanpa prefix cabang)
CID tetap tersimpan di DB sebagai histori
```

---

## 2. Tabel Format ID Per Status

| Status Pelanggan | ID yang Ditampilkan | Contoh |
|---|---|---|
| `registered` | `customer_code` | `C00RQ000001` |
| `waiting_survey` | `customer_code` | `C00RQ000001` |
| `surveyed` | `customer_code` | `C00RQ000001` |
| `waiting_installation` | `customer_code` | `C00RQ000001` |
| `installed` | `customer_code` | `C00RQ000001` |
| `active` | `cid` | `C1X1AC00RQ000001_MANGKUJAYAN_DYAHPURBA` |
| `suspended` | `cid` | `C1X1AC00RQ000001_MANGKUJAYAN_DYAHPURBA` |
| `terminated` | bare registration ID | `RQ000001` |

---

## 3. Decoding Format `customer_code`

Format: `{cid_prefix}00{registration_prefix}{######}`

| Segmen | Nilai | Sumber | Makna |
|---|---|---|---|
| `C` | Huruf | `pops.cid_prefix` | Kode prefix cabang POP |
| `00` | Dua digit | Hardcoded | Placeholder OLT saat registrasi (OLT belum diketahui) |
| `RQ` | Huruf | `pops.registration_prefix` | Prefix tipe registrasi |
| `000001` | 6 digit | `pop_sequences` counter | Nomor urut per POP, sequential, tidak berulang |

**Contoh:** Cabang Jetis (`cid_prefix='C'`, `registration_prefix='RQ'`):
```
C  00  RQ  000001
│  │   │   └── Nomor urut ke-1 di cabang Jetis
│  │   └────── Registration prefix cabang Jetis
│  └────────── OLT placeholder default (belum diisi)
└───────────── Kode prefix cabang Jetis
```

### Kenapa `00`?

Saat registrasi, admin belum mengetahui OLT mana yang akan melayani pelanggan tersebut.
OLT baru diketahui saat teknisi melakukan survei dan mengisi `customer_technical_details.olt_number`.
Nilai `00` adalah placeholder yang jelas secara visual bahwa "belum di-assign ke OLT".

---

## 4. Decoding Format CID (setelah aktivasi)

Format: `{cid_prefix}{olt_number}{dist_code}{customer_code}_{DESA}_{NAMA}`

| Segmen | Nilai | Sumber | Makna |
|---|---|---|---|
| `C` | Huruf | `pops.cid_prefix` | Kode prefix cabang |
| `1` | Angka | `customer_technical_details.olt_number` | Nomor OLT yang melayani pelanggan |
| `X1A` | Kode | `distributions.code` | Kode area distribusi |
| `C00RQ000001` | Mixed | `customers.customer_code` | customer_code permanen (includes prefix) |
| `MANGKUJAYAN` | CAPS | `villages.name` | Nama desa tanpa spasi, huruf kapital |
| `DYAHPURBA` | CAPS | `customers.full_name` | Nama pelanggan tanpa spasi, huruf kapital |

**Contoh:**
```
C  1  X1A  C00RQ000001  _  MANGKUJAYAN  _  DYAHPURBA
│  │  │    │               │               └── Nama pelanggan
│  │  │    │               └────────────────── Nama desa
│  │  │    └────────────────────────────────── customer_code permanen
│  │  └─────────────────────────────────────── Kode distribusi
│  └────────────────────────────────────────── Nomor OLT (diisi teknisi)
└───────────────────────────────────────────── Kode prefix cabang
```

---

## 5. Decoding Format Bare Registration ID (setelah terminate)

Format: `{registration_prefix}{######}`

**Cara menghasilkan:** Strip `cid_prefix` + `00` dari `customer_code`.

| `customer_code` | Bare ID |
|---|---|
| `C00RQ000001` | `RQ000001` |
| `D00RQ000474` | `RQ000474` |

Bare ID ini adalah identitas yang dikembalikan saat pelanggan berhenti berlangganan.
Ini berguna untuk referensi historis: admin bisa menelusuri data lama dari nomor `RQ######`.

---

## 6. Aturan Sistem

### `customer_code` bersifat PERMANEN
- Di-generate satu kali saat registrasi menggunakan `Pop::generateRegistrationNumber()`
- Tidak boleh diubah setelah tersimpan
- Menjadi kunci relasi ke tabel lain (invoice, payment, audit log)
- Selalu mengikut ke manapun status pelanggan berubah

### Counter per POP
- Setiap POP memiliki counter sequential tersendiri di tabel `pop_sequences`
- Dua pelanggan di POP berbeda bisa memiliki nomor yang sama (misal: `C00RQ000001` dan `D00RQ000001`)
- Dalam satu POP, nomor tidak pernah berulang (dijaga dengan `lockForUpdate` dalam DB transaction)

### CID hanya di-generate sekali saat aktivasi
- Dipanggil di `CustomerController::activate()`
- Tersimpan permanen di `customers.cid`
- Jika distribusi belum dipilih → dist_code default `'XX'`
- Jika olt_number belum diisi teknisi → olt default `'0'`

### Tampilan ID di UI
- Logika `displayId` ada di `CustomerController::show()` dan `customers/index.blade.php`
- Keduanya menggunakan logika yang identik untuk konsistensi

---

## 7. Implementasi Teknis

### Method: `Pop::generateRegistrationNumber()`
```php
// File: app/Models/Pop.php
// Output: C00RQ000001
public function generateRegistrationNumber(): string
{
    return sprintf('%s00%s%06d', $this->cid_prefix, $this->registration_prefix, $nextNumber);
}
```

### Method: `Pop::generateComplexCid()`
```php
// File: app/Models/Pop.php
// Output: C1X1AC00RQ000001_MANGKUJAYAN_DYAHPURBA
public function generateComplexCid(Customer $customer, ?Distribution $distribution = null): string
{
    return sprintf('%s%s%s%s_%s_%s', $prefix, $oltNumber, $distCode, $customer->customer_code, $villageName, $customerName);
}
```

### Method: `Pop::extractBareRegistrationId()`
```php
// File: app/Models/Pop.php
// Input: C00RQ000001 → Output: RQ000001
public function extractBareRegistrationId(string $customerCode): string
{
    $stripped = ltrim($customerCode, $this->cid_prefix);
    if (str_starts_with($stripped, '00')) {
        return substr($stripped, 2);
    }
    return $customerCode;
}
```

### Logika displayId (Controller & View)
```php
if (in_array($status, ['active', 'suspended']) && $customer->cid) {
    $displayId = $customer->cid;                                    // CID penuh
} elseif ($status === 'terminated') {
    $displayId = $pop->extractBareRegistrationId($customer->customer_code); // RQ######
} else {
    $displayId = $customer->customer_code;                          // C00RQ######
}
```

---

## 8. File yang Terlibat

| File | Perubahan |
|---|---|
| `app/Models/Pop.php` | `generateRegistrationNumber()`, `generateComplexCid()`, `extractBareRegistrationId()` |
| `app/Http/Controllers/CustomerController.php` | `store()` memanggil `generateRegistrationNumber()`, `show()` dan logika `displayId` |
| `resources/views/customers/index.blade.php` | Logika `displayId` per status |
| `resources/views/customers/show.blade.php` | Variabel `$displayId` dari controller |
| `tests/Unit/PopCidGenerationTest.php` | Unit test format CID dan `extractBareRegistrationId` |
| `tests/Feature/PopIdentifierSettingTest.php` | Feature test format customer_code baru |
| `tests/Feature/CustomerCreateTest.php` | Assertion format `D00C######` |
| `tests/Feature/CustomerActivationTest.php` | Assertion CID berisi `customer_code` |

---

## 9. Contoh Skenario Lengkap

### Skenario: Pelanggan Cabang Jetis

```
1. Admin memilih POP: Cabang Jetis (cid_prefix='C', registration_prefix='RQ')
2. Sistem generate: customer_code = C00RQ000001
3. Di list pelanggan tampil: C00RQ000001  (status: registered)

4. Teknisi survey → mengisi olt_number = 1
5. Admin pilih distribusi: X1A
6. Di list pelanggan tampil: C00RQ000001  (status: surveyed → installed)

7. Admin aktivasi:
   - Sistem load: cid_prefix='C', olt_number='1', dist_code='X1A'
   - Sistem load: customer_code='C00RQ000001', desa='MANGKUJAYAN', nama='DYAHPURBA'
   - Generate CID: C1X1AC00RQ000001_MANGKUJAYAN_DYAHPURBA
   - Simpan ke customers.cid
8. Di list pelanggan tampil: C1X1AC00RQ000001_MANGKUJAYAN_DYAHPURBA  (status: active)

9. Pelanggan terminate:
   - Status berubah ke: terminated
   - CID tetap di DB: C1X1AC00RQ000001_MANGKUJAYAN_DYAHPURBA  (histori)
10. Di list pelanggan tampil: RQ000001  (bare registration ID)
```

---

*Dokumen ini dibuat 18 Juni 2026. Update test suite: 188 passed, 0 failed.*
