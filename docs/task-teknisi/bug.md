# Bug — Dua Jalur Aktivasi Pelanggan yang Tidak Konsisten

**Status:** ✅ Fixed 2026-07-07. Approve Pemasangan sekarang cuma bisa lewat `/verifications/{customer}/admin` — lihat [Perbaikan](#perbaikan-diterapkan) di bawah.
**Severity:** Tinggi — bisa bikin pelanggan "aktif" tanpa tagihan & tanpa CID, gak kelihatan lagi di antrean manapun.

## Ringkasan

Ada **2 tombol berbeda** yang sama-sama bisa meng-aktifkan pelanggan (`customer.status = active`) untuk task tipe Pemasangan, tapi cuma **1 dari 2** yang benar-benar menyelesaikan proses aktivasi dengan lengkap. Yang satu lagi cuma ubah status doang, tanpa efek ikutan yang wajib ada.

## Lokasi

| | Jalur A (BENAR, lengkap) | Jalur B (BUG, tidak lengkap) |
|---|---|---|
| Halaman | `/verifications/{customer}/admin` | `/tasks/{task}` (halaman detail Task Teknisi mana pun) |
| Tombol | "Approve" di form Verifikasi Admin | "Approve Task" di panel "Review Hasil & Tandai Selesai" |
| Kode | `CustomerVerificationController::finalVerify()` | `TaskController::review()`, cabang `action=approve` |
| View | `resources/views/verifications/admin.blade.php` | `resources/views/tasks/show.blade.php:1004` |

## Apa Bedanya — Efek Samping Tiap Jalur

| Efek | Jalur A `finalVerify()` | Jalur B `review()` approve |
|------|---|---|
| `customer.status` | → `active` | → `active` |
| `customer.cid` | **Di-generate** (`Pop::generateComplexCid()`) | ❌ Tetap kosong |
| Invoice AWAL (biaya pasang, prorate, dll) | **Dibuat** | ❌ Tidak dibuat |
| `customer_service.service_status` | → `aktif` | ❌ Tetap seperti sebelumnya |
| `customer_service.billing_status` | → `active` | ❌ Tetap `pending` |
| `activated_by_name`/`activated_by_user_id` | Dicatat | ❌ Tidak dicatat |
| Muncul di antrean `/verifications/queue`? | Otomatis hilang (karena sudah beres, memang seharusnya) | Juga hilang (query filter status), **padahal belum beres** |

## Kenapa Ini Bisa Kepencet — Alur Kejadian

1. Teknisi selesai pasang, submit laporan pemasangan → status Customer masuk `verification_admin`, Task terkait jadi `selesai` + `fop_review_status=pending`.
2. FOP dapat 2 notifikasi berbeda yang nunjuk ke 2 tempat:
   - Notifikasi umum "Laporan Task Selesai, butuh review Anda" → link ke `/tasks/{task}` (halaman Task).
   - Antrean `/verifications/queue` → link ke `/verifications/{customer}/admin` (halaman Verifikasi).
3. Kalau FOP klik notifikasi pertama dan langsung pencet "Approve Task" di halaman Task **tanpa mampir ke halaman Verifikasi dulu** → jalur B kepakai. Tidak ada validasi/larangan sistem yang mencegah ini — tombolnya memang sengaja ada di sana untuk semua tipe task (Survey, Pemasangan, Maintenance, dll), gak ada pengecualian untuk Pemasangan.

## Dampak Konkret (Contoh Kasus)

Pelanggan **Budi**, sudah selesai dipasang, Task Pemasangan-nya `selesai`, nunggu review.

**Kalau FOP approve lewat halaman Task (`/tasks/{task}`, jalur B):**
- Budi jadi `status=active` di sistem.
- CID Budi masih kosong (`null`) — padahal CID dipakai buat identifikasi jaringan/PPPoE session.
- Gak ada tagihan pemasangan yang terbit — Budi pakai internet gratis, perusahaan rugi biaya instalasi yang gak pernah tertagih.
- `customer_service.billing_status` masih `pending` — sistem billing bulanan berikutnya (`billing:generate-monthly-invoices`) mungkin bingung/skip karena status service belum `aktif`.
- Budi udah gak nongol lagi di `/verifications/queue` (karena status-nya udah `active`, keluar dari filter status queue) — **gak ada tempat manapun di UI yang bisa "menangkap" bahwa Budi masih butuh Invoice+CID.** Harus ketauan manual (misal Budi komplain gak bisa connect karena CID kosong, atau admin ngecek laporan keuangan nemu ada pelanggan aktif tanpa invoice awal).

## Root Cause

`TaskController::review()` didesain generik untuk semua tipe Task (approve laporan Survey → transisi ke `waiting_installation`, approve laporan Pemasangan → transisi ke `active`). Tapi transisi ke `active` untuk Pemasangan **seharusnya bukan aksi sederhana** — itu titik bisnis kritikal yang wajib disertai penerbitan invoice & CID. Developer yang bikin `finalVerify()` sepertinya menganggap itu satu-satunya jalur, tapi lupa `review()` juga punya cabang yang menyentuh transisi `ACTIVE` yang sama tanpa lewat proses lengkap itu.

## Solusi yang Disarankan (belum dieksekusi, tunggu keputusan)

**Opsi 1 — Block di controller (paling aman, disarankan):**
```php
// TaskController::review(), cabang action=approve
if ($action === 'approve' && $task->task_type === TaskType::PEMASANGAN) {
    return back()->with('error', 'Approve pemasangan wajib lewat halaman Verifikasi Admin (generate invoice & CID).');
}
```

**Opsi 2 — Sembunyikan tombol di view** (`tasks/show.blade.php`, sekitar baris 990):
```blade
@if($task->status->value === 'selesai' && $task->fop_review_status === 'pending' && $task->task_type->value !== 'PSB')
@can('review', $task)
    {{-- tombol approve/reject --}}
@endcan
@endif
```
Untuk task Pemasangan, ganti panel ini jadi info teks: "Approve pemasangan dilakukan di halaman Verifikasi Admin" + link ke `/verifications/{customer}/admin`.

**Opsi 3 — Satukan logic** (lebih besar, opsional): extract logic aktivasi (`generateComplexCid` + buat Invoice + update `customer_service`) dari `finalVerify()` jadi 1 service method (`CustomerActivationService::activate()`), dipanggil dari kedua tempat biar hasilnya selalu konsisten gimana pun jalurnya diakses.

**Rekomendasi:** Opsi 1 + 2 sekaligus — murah, cepat, langsung nutup celahnya tanpa refactor besar.

## Perbaikan Diterapkan

Opsi 1 + 2 dieksekusi 2026-07-07 (disepakati: `/verifications/{customer}/admin` tetap satu-satunya pintu resmi aktivasi):

1. **`TaskController::review()`** — cabang `action=approve` sekarang menolak duluan kalau `task_type === PEMASANGAN`, balik dengan pesan error "wajib lewat Verifikasi Admin", sebelum sempat transisi status apapun. Approve Survey & tipe lain gak kepengaruh, tetap jalan seperti biasa.
2. **`resources/views/tasks/show.blade.php`** — panel review untuk Task tipe Pemasangan (`PSB`) diganti: gak ada lagi tombol "Approve Task", cuma info + tombol "Buka Verifikasi Admin" yang link langsung ke `/verifications/{customer}/admin`. Panel approve/reject asli tetap tampil apa adanya untuk tipe task lain.

**Yang gak berubah:** Reject dari halaman Task tetap boleh untuk semua tipe (termasuk Pemasangan) — reject cuma balikin ke `in_progress`, gak ada efek CID/Invoice yang bisa nyasar, jadi aman dipertahankan sebagai jalur cepat.
