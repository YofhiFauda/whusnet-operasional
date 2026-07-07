# Business Logic — Master Distribusi

## 1. Model Sederhana — Bukan Inventaris Kapasitas

`Distribution` cuma py 4 kolom (`pop_id`, `code`, `name`, `description`) — **tidak ada** `type` (OLT/ODC/ODP), **tidak ada** `capacity`/`used_ports`, **tidak ada** `status`. Kalau butuh info kapasitas port/tipe perangkat, itu belum ada di model ini — jangan asumsikan sistem tracking kapasitas jaringan otomatis, itu murni tanggung jawab operasional di luar sistem (dicatat manual di `description` kalau perlu).

## 2. Keunikan Kode — Global, Bukan Per-POP

**Keputusan eksplisit** (dari [archive/spesifikasi-pop-distribusi-cid.md](../pop/archive/spesifikasi-pop-distribusi-cid.md) §2, dikonfirmasi user pemilik produk): kode Distribusi **harus unik secara global** di seluruh sistem, bukan cuma unik dalam 1 POP. Ditegakkan di 2 lapis:

- Migrasi `2026_06_19_140908_make_distribution_code_globally_unique` — unique index DB di kolom `code` (bukan composite dengan `pop_id`).
- `DistributionController::store()`/`update()` — `Rule::unique('distributions', 'code')`, komentar eksplisit nyebut alasan spesifikasi ini.

**Implikasi:** 2 POP beda gak bisa punya Distribusi dengan kode yang sama persis, walau secara fisik itu perangkat yang beda-beda di lokasi beda. Kode harus didesain global-aware sejak awal (e.g. sisipkan kode POP di kode Distribusi, seperti contoh `X4A` di spesifikasi awal — bukan aturan sistem, cuma konvensi penamaan yang disarankan).

## 3. Relasi ke POP — Wajib, Many-to-One (Sebenarnya ke Mini POP)

Setiap Distribusi **wajib** terhubung ke 1 POP (`pop_id` NOT NULL, cascade delete — hapus POP ikut hapus semua Distribusi di bawahnya). Model & form gak enforce `type` tertentu di `pop_id`, tapi **struktur data yang benar** (sesuai `MasterPopSeeder`) adalah Distribusi terhubung ke **Mini POP** (`type=mini_pop`), bukan Cabang langsung — hierarki penuhnya `Cabang → Mini POP → Distribusi`.

Form create/edit Master Distribusi (`Pop::where('status','active')`) nawarin **semua level POP** tanpa filter type — jadi admin bisa aja gak sengaja assign Distribusi ke row Cabang langsung (melanggar konvensi hierarki, walau gak ditolak sistem). Disiplin operasional: selalu pilih Mini POP, bukan Cabang.

## 4. Peran dalam Pembentukan CID

Distribusi gak berdiri sendiri — fungsinya jadi **segmen ke-3** di CID pelanggan:

```
CID = {cid_prefix POP} + {segmen Mini POP} + {Distribution.code} + {REQ ID}
```

Lihat [docs/master/pop/business-logic.md §4](../pop/business-logic.md#4-generate-cid-popgeneratecomplexcid) untuk detail lengkap kalkulasi CID. Kalau pelanggan belum di-assign Distribusi (`customer.distribution_id` kosong), sistem pakai placeholder `'XX'` di posisi ini — bukan error, cuma bagian dari format default (`C00RQ######`, lihat [docs/master/pop/business-logic.md §5](../pop/business-logic.md#5-resolve-display-id-per-status-popresolvedisplayid)).

## 5. Kapan Distribusi Di-assign ke Pelanggan (✅ Fixed 2026-07-07)

**Bukan** di form laporan pemasangan — `CustomerInstallationController::store()` gak pernah menyentuh `distribution_id` (cuma isi `CustomerTechnicalDetail`/`CustomerDevice`). Distribusi (bareng Mini POP) di-assign lewat modal **"Atur Mini POP & Distribusi"** yang muncul saat klik CID/REQ ID di halaman detail pelanggan (`CustomerNetworkAssignmentController@update`) — **pasca pemasangan**, kapan pun sebelum atau sesudah aktivasi, dan bisa diganti-ganti belakangan (nyusul konfigurasi Mikrotik manual). Dropdown Distribusi di modal ini otomatis ke-filter ikut Mini POP yang dipilih (`Distribution.pop_id = mini_pop.id`).

Sebelum di-assign, sistem pakai placeholder `'XX'` (§4). Kalau Distribusi diganti setelah pelanggan `active`/`suspended`, CID di-regenerate otomatis. Riwayat gap sebelum fix ini: [../pop/bug.md](../pop/bug.md).

## 6. Tidak Ada Guard Penghapusan

`DistributionController::destroy()` **langsung hapus** tanpa cek apakah masih ada `Customer` yang pakai `distribution_id` itu (ada komentar "Optional: check..." di kode tapi belum diimplementasi). Karena `customers.distribution_id` kemungkinan `nullOnDelete` (cek [database-schema.md](database-schema.md)), hapus Distribusi yang masih dipakai bakal bikin `customer.distribution_id` jadi `null` diam-diam — CID pelanggan yang udah di-generate **tidak** ikut berubah (CID tersimpan sebagai string tetap di `customer.cid`, bukan dihitung ulang tiap saat), tapi assignment distribusi pelanggan itu hilang untuk keperluan lain (misal laporan per-distribusi).
