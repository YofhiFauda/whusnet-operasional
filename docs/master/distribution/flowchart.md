# Flowchart — Master Distribusi

## 1. Create/Edit Distribusi

```
Admin isi form (pop_id, code, name, description)
        │
        ▼
normalizeInput() — uppercase + trim code, trim name
        │
        ▼
Validasi: pop_id exists, code unik GLOBAL (bukan per-POP)
        │
        ▼
   code sudah dipakai Distribusi lain (POP manapun)? ──ya──▶ TOLAK
        │ tidak
        ▼
Distribution::create()/update()
```

## 2. Hapus Distribusi

```
Admin klik hapus (DELETE /master/distribusi/{distribusi})
        │
        ▼
Distribution::delete()  — LANGSUNG, tanpa cek pemakaian
        │
        ▼
customers yang punya distribution_id = ini → otomatis jadi NULL (FK nullOnDelete)
        │
        ▼
CID pelanggan yang SUDAH di-generate TIDAK berubah (string statis di customer.cid)
tapi assignment distribution_id pelanggan itu hilang untuk kebutuhan lain
```

## 3. Assignment ke Pelanggan (dari modul lain)

```
Teknisi submit laporan pemasangan (CustomerInstallationController::store())
        │
        ▼
Pilih Distribution dari dropdown (berdasar lokasi fisik ODP/OLT)
        │
        ▼
customer.distribution_id = distribution.id
        │
        ▼
(nanti) Verifikasi Admin approve → Pop::generateComplexCid(customer, distribution)
        → distribution.code jadi segmen CID
```

Lihat [docs/customer-lifecycle/flowchart.md §4](../../customer-lifecycle/flowchart.md#4-alur-pemasangan) untuk konteks penuh form pemasangan.
