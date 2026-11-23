# Bypass DEAC lewat Command (Data Rekonsiliasi) — Design

Tanggal: 2026-11-23 · Ukuran: perubahan kecil (1 command + test)

## Kenapa

Rekonsiliasi Excel (`app:customer-package-reconcile-excel --terminate-missing` /
`manual_terminate_cids`) memutus banyak pelanggan sekaligus. Semuanya lalu muncul
di List Putus Langganan dengan tombol **Ambil Alat** → tiap pelanggan butuh Task
DEAC + laporan teknisi. Untuk data rekonsiliasi itu tidak relevan: alat sudah lama
kembali, atau memang tidak akan kembali. Butuh jalan pintas untuk menandai status
alat langsung, tanpa DEAC.

## Goals / Non-goals

- Goal: tandai status alat pelanggan `terminated` secara massal per POP, termasuk
  pelanggan yang diputus di run rekonsiliasi sebelumnya.
- Non-goal: UI/tombol di List Putus Langganan (keputusan user: lewat command saja).
- Non-goal: gerakan stok gudang. "Kembali ke gudang" cuma tanda status, karena SN
  data rekonsiliasi umumnya tidak diketahui. Kalau stok harus bertambah, pakai
  jalur walk-in (`warehouse.returns.from-customer`) yang mewajibkan SN.
- Anti-goal: menimpa status alat yang sudah final, atau mendahului task DEAC /
  review yang masih berjalan (bisa bikin modem tercatat dua kali).

## Approaches

1. Opsi `--device-status` di command rekonsiliasi. Ditolak: hanya berlaku untuk
   pelanggan yang diputus di run itu, dan command itu sudah gemuk.
2. **Command terpisah (dipilih user).** Bisa dipakai untuk data lama maupun baru,
   dan bisa diulang kapan saja.

## Desain

`php artisan app:device-retrieval-bypass`

| Opsi | Arti |
|---|---|
| `--pop-id=` / `--pop-name=` | POP sasaran (wajib salah satu) |
| `--status=returned\|not_returned` | wajib |
| `--cids=CID1,CID2` | sasaran per CID (opsional) |
| `--reason=` | sasaran per nama alasan putus, mis. `"Rekonsiliasi Data Excel"` (opsional) |
| `--actor-id=` | wajib, user pelaku (audit + `retrieval_waived_by`) |
| `--note=` | catatan opsional, masuk audit |
| `--apply` | tanpa flag ini cuma dry-run |

- Sasaran: pelanggan `status=terminated` di POP itu, difilter `--cids` dan/atau
  `--reason`. **Minimal salah satu filter wajib**, supaya gak sengaja kena satu POP penuh.
- Dilewati (dilaporkan alasannya): CID tidak ditemukan / bukan terminated / beda POP;
  `device_retrieved_at` atau `retrieval_waived_at` sudah terisi; masih ada FopTask
  AMBIL_MODEM yang belum selesai/batal; laporan DEAC masih menunggu review
  (`DeviceRetrievalReviewService::hasPendingReview()`).
- `returned` → `device_retrieved_at = now()`.
  `not_returned` → `retrieval_waived_at = now()`, `retrieval_waived_by = actor`.
- Pelanggan tanpa baris `customer_devices` dibuatkan placeholder
  `LEGACY_DEVICE_TYPE` (pola `CustomerController::retrieveDevice()`).
- Tiap pelanggan diproses dalam transaksi. `AuditLog::log()` dengan action
  `device_retrieval_bypassed` (status + note + sumber command).
- Output: tabel sasaran + tabel yang dilewati + ringkasan jumlah.
- Lokasi: `app/Console/Commands/Operational/DeviceRetrievalBypassCommand.php`.
  Test: `tests/Feature/DeviceRetrievalBypassCommandTest.php`.

## Keputusan builder (koreksi kalau keliru)

- Timestamp pakai `now()` (waktu penandaan), bukan `terminated_at`. Waktu alat
  kembali yang sebenarnya tidak diketahui.
- Tidak menulis `device_retrieval_logs`. Itu riwayat inventori per SN, sedangkan
  bypass ini tidak punya SN. Jejaknya cukup di AuditLog.
- Efek turunan otomatis lewat flag yang sudah ada: badge List Putus Langganan,
  tombol Ambil Alat hilang, Langganan Lagi (`returned` → Antrean Survey).
