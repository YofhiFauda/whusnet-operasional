# Business Logic — Master Distribusi

## 1. Model Sederhana — Bukan Inventaris Kapasitas

`Distribution` cuma py 4 kolom (`pop_id`, `code`, `name`, `description`) — **tidak ada** `type` (OLT/ODC/ODP), **tidak ada** `capacity`/`used_ports`, **tidak ada** `status`. Kalau butuh info kapasitas port/tipe perangkat, itu belum ada di model ini — jangan asumsikan sistem tracking kapasitas jaringan otomatis, itu murni tanggung jawab operasional di luar sistem (dicatat manual di `description` kalau perlu).

## 2. Keunikan Kode — Global, Bukan Per-POP

**Keputusan eksplisit** (dari [archive/spesifikasi-pop-distribusi-cid.md](../pop/archive/spesifikasi-pop-distribusi-cid.md) §2, dikonfirmasi user pemilik produk): kode Distribusi **harus unik secara global** di seluruh sistem, bukan cuma unik dalam 1 POP. Ditegakkan di 2 lapis:

- Migrasi `2026_06_19_140908_make_distribution_code_globally_unique` — unique index DB di kolom `code` (bukan composite dengan `pop_id`).
- `DistributionController::store()`/`update()` — `Rule::unique('distributions', 'code')`, komentar eksplisit nyebut alasan spesifikasi ini.

**Implikasi:** 2 POP beda gak bisa punya Distribusi dengan kode yang sama persis, walau secara fisik itu perangkat yang beda-beda di lokasi beda. Kode harus didesain global-aware sejak awal (e.g. sisipkan kode POP di kode Distribusi, seperti contoh `X4A` di spesifikasi awal — bukan aturan sistem, cuma konvensi penamaan yang disarankan).

## 3. Relasi ke POP — Wajib, Many-to-One, ke CABANG (ADHOC-312, 2026-11-23)

Setiap Distribusi **wajib** terhubung ke 1 POP (`pop_id` NOT NULL, cascade delete — hapus POP ikut hapus semua Distribusi di bawahnya). `pop_id` menunjuk **Cabang** (`type=cabang`), **bukan** Mini POP — hierarkinya `Cabang → Mini POP` dan `Cabang → Distribusi` dua cabang paralel, bukan berantai lewat Mini POP. Satu Distribusi (ODP) lazim dipakai bareng lebih dari satu Mini POP dalam Cabang yang sama (laporan user: Cabang Siman punya D1/D2 yang dipakai lintas Mini POP).

**Sebelum ADHOC-312** aturannya kebalik: `pop_id` divalidasi harus Mini POP spesifik (`NetworkAssignmentService::distributionBelongsToMiniPop()`, ADHOC-107 R6) — satu Distribusi cuma sah nempel ke SATU Mini POP. Itu salah baca kenyataan lapangan. Sudah dikoreksi jadi `distributionBelongsToPop()` (cek `pop_id` Cabang). Data lama yang `pop_id`-nya masih menunjuk Mini POP dibetulkan lewat `php artisan distributions:backfill-pop-to-cabang --force` (`app/Console/Commands/OneOff/BackfillDistributionPopToCabangCommand.php`).

Form create/edit Master Distribusi (`Pop::forUser()->where('type','cabang')`) cuma nawarin level Cabang — dropdown Mini POP dilepas dari form ini sejak ADHOC-312.

## 4. Peran dalam Pembentukan CID

Distribusi gak berdiri sendiri — fungsinya jadi **segmen ke-3** di CID pelanggan:

```
CID = {cid_prefix POP} + {segmen Mini POP} + {Distribution.code} + {REQ ID}
```

Lihat [docs/master/pop/business-logic.md §4](../pop/business-logic.md#4-generate-cid-popgeneratecomplexcid) untuk detail lengkap kalkulasi CID. Kalau pelanggan belum di-assign Distribusi (`customer.distribution_id` kosong), sistem pakai placeholder `'XX'` di posisi ini — bukan error, cuma bagian dari format default (`C00RQ######`, lihat [docs/master/pop/business-logic.md §5](../pop/business-logic.md#5-resolve-display-id-per-status-popresolvedisplayid)).

## 5. Kapan Distribusi Di-assign ke Pelanggan (✅ Fixed 2026-07-07)

**Bukan** di form laporan pemasangan — `CustomerInstallationController::store()` gak pernah menyentuh `distribution_id` (cuma isi `CustomerTechnicalDetail`/`CustomerDevice`). Distribusi (bareng Mini POP) di-assign lewat modal **"Atur Mini POP & Distribusi"** yang muncul saat klik CID/REQ ID di halaman detail pelanggan (`CustomerNetworkAssignmentController@update`) — **pasca pemasangan**, kapan pun sebelum atau sesudah aktivasi, dan bisa diganti-ganti belakangan (nyusul konfigurasi Mikrotik manual). Dropdown Distribusi di modal ini di-scope ke Cabang pelanggan (ADHOC-312), **bukan** ikut Mini POP yang dipilih — pilih Mini POP tidak mempengaruhi daftar Distribusi yang tersedia.

Jalur kedua (ADHOC-104/109/107, direvisi ADHOC-312): form **Edit Pelanggan** punya dropdown POP → Mini POP dan POP → Distribusi (dua cabang paralel, bukan berantai) dengan aturan satu sumber (`NetworkAssignmentService::miniPopBelongsToPop()`/`distributionBelongsToPop()`), tapi Mini POP & Distribusi **cuma ditulis kalau Cabang ikut dipindah** dan terkunci kalau pelanggan pra-pemasangan atau user tanpa `customers.detail.installation.validate`. `CustomerObserver::updating()` menegakkan hierarki dari semua jalur: Mini POP yang bukan anak Cabang pelanggan, atau Distribusi yang bukan anak Cabang pelanggan, dilepas **saat kolom jaringannya berubah** — keduanya dicek independen (Distribusi TIDAK ikut dilepas hanya karena Mini POP-nya dilepas).

Sebelum di-assign, segmen Distribusi di CID = `0` (dulu `'XX'`; lihat `../pop/business-logic.md` §4). Kalau Distribusi diganti setelah pelanggan `active`/`suspended`, CID dibuat ulang otomatis lewat `CustomerCidService`. Riwayat gap sebelum fix ini: [../pop/bug.md](../pop/bug.md).

## 6. Tidak Ada Guard Penghapusan

`DistributionController::destroy()` **langsung hapus** tanpa cek apakah masih ada `Customer` yang pakai `distribution_id` itu (ada komentar "Optional: check..." di kode tapi belum diimplementasi). Karena `customers.distribution_id` kemungkinan `nullOnDelete` (cek [database-schema.md](database-schema.md)), hapus Distribusi yang masih dipakai bakal bikin `customer.distribution_id` jadi `null` diam-diam — CID pelanggan yang udah di-generate **tidak** ikut berubah (CID tersimpan sebagai string tetap di `customer.cid`, bukan dihitung ulang tiap saat), tapi assignment distribusi pelanggan itu hilang untuk keperluan lain (misal laporan per-distribusi).
