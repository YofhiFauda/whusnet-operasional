# Modul Kolektor (Kolektor 2.0)

Penagihan **door-to-door**: kolektor mendatangi pelanggan, menerima tunai, menyerahkan uangnya ke admin, dan admin menghitung ulang sebelum menutup buku. Modul ini menutup jarak antara *uang berpindah tangan di teras rumah* dan *angka yang tercatat di sistem*.

Bukan pengganti halaman Tagihan. Tagihan tetap master universal semua tagihan; kolektor adalah **salah satu jalur bayar**, bukan tempat tagihan berpindah.

## Konsep Inti

Tiga hal yang harus dipahami sebelum menyentuh kode di modul ini.

### 1. Dua halaman, dua audiens — dipisah menurut SIAPA, bukan menurut data

```
/collector-worksheet          ADMIN di kantor
  ├─ index : daftar kolektor + pelanggan belum ber-kolektor (assign)
  └─ show  : per-kolektor, 4 tab
              · Pembayaran  — tunggakan + bayar mewakili (cross check)
              · Setoran     — hitung uang fisik, verifikasi, selisih, hapus buku
              · Kunjungan   — laporan aging + riwayat kunjungan
              · Atur Pelanggan — assign / lepas rute permanen

/collector-worklist           KOLEKTOR di lapangan
  ├─ daftar pelanggan yang sudah waktunya ditagih
  ├─ catat pembayaran (1-by-1 / massal)
  ├─ catat kunjungan tanpa hasil
  └─ setor seluruh saldo ke admin
```

Konsekuensinya: **kolektor tidak pernah membuka halaman admin**, jadi role `kolektor` tetap tanpa `payments.create` maupun `customers.update`. Ini yang membuat kewenangannya bisa sempit tanpa melumpuhkan pekerjaannya.

### 2. Dua angka uang, tidak pernah dijumlahkan

```
Saldo Belum Disetor  = Σ payment VALID (collected_by = X, collector_deposit_id NULL)
                       → WAJIB kembali 0 tiap kali setor
Kurang Setor         = Σ sisa kewajiban dari setoran berstatus `selisih`
                       → TIDAK ikut nol; terus terlihat sampai dilunasi/dihapus buku
```

Keduanya **angka turunan**, tidak ada kolom saldo. Kolom yang di-`+=`/`-=` berhenti benar begitu satu payment di-reject, dan angka uang yang bohong tidak punya alarm — ketahuannya berbulan-bulan kemudian dan tak bisa direkonstruksi.

Kalau digabung, "saldo 0" jadi ambigu: beres, atau nombok yang tak tercatat. Justru angka kedua itulah yang paling penting dilacak.

### 3. Setoran cuma menangkap setengah kecurangan — sisanya ditutup Visit Log

```
Setoran menangkap : "laporan jujur, kas tidak jujur"
                    (kolektor melapor benar tapi menyetor kurang)

Setoran BUTA pada : "laporan tidak jujur"
                    kolektor tagih Ani 100rb → tidak dilaporkan sama sekali →
                    uang dikantongi. Setoran cocok sempurna. Invoice Ani tetap
                    belum_dibayar. Ani merasa sudah bayar. Sistem diam.
```

Yang menangkap kasus kedua adalah **`collector_visits`** — catatan kunjungan termasuk yang **tidak** menghasilkan uang. Tanpa itu, "tidak ada baris" ambigu antara *belum didatangi* dan *didatangi lalu uangnya raib*. Dengan itu, muncul pola yang layak diaudit: "5× tidak ada orang, tunggakan menua".

Karena itu hasil `bayar` **tidak bisa diketik manual** — hanya lahir dari payment yang benar-benar tersimpan. Kalau boleh diketik, tabel ini berubah dari alat pengungkap jadi alat penutup.

## Prinsip yang Dijaga

- **Rute aksi kolektor tanpa parameter `{collector}`.** Pelakunya `auth()->user()`, tak pernah dari klien. Rute admin (`/payment-batches/{collector}`) boleh ber-parameter karena digerbang `payments.create`. Kalau disatukan, kolektor A bisa mencatat pembayaran atas nama kolektor B.
- **Target POST aksi yang mengubah data di-render server-side.** Tidak boleh dihitung di klien — Alpine dimuat dari CDN, dan waktu CDN gagal, form assign pernah mem-POST ke URL halaman sendiri lalu gagal tanpa pesan apa pun.
- **Setor = SELURUH saldo.** Tidak ada setoran parsial; ini membuang seluruh logika alokasi.
- **`difference` dihitung sekali saat verifikasi lalu disimpan; saldo tidak.** Aman beku karena payment tak bisa keluar dari setoran terverifikasi.
- **Verifikator ≠ penyetor**, berlaku juga untuk Owner.
- **Semua transisi setoran masuk `audit_logs`** (module `kolektor`).
- **Notifikasi tak pernah membatalkan uang.** Dikirim sesudah commit, kegagalannya ditelan + `report()`.
- **Idempotency key mengidentifikasi ISI KIRIMAN, bukan tab.** Key global yang dipakai bersama membuat pembayaran kedua dijawab `already_processed` — uang hilang dengan gejala sukses.
- **Kwitansi tak pernah menyandera uang.** Setoran terverifikasi tanpa menunggu berkas apa pun.

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | Saldo turunan, siklus setoran & selisih, jendela tagih, Visit Log, RBAC, seluruh guard |
| [user-flow.md](user-flow.md) | Langkah kolektor & admin per halaman, skenario harian, kasus selisih & pelunasan |
| [flowchart.md](flowchart.md) | State machine setoran, alur bayar/setor/verifikasi, pohon keputusan guard |
| [database-schema.md](database-schema.md) | `collector_deposits`, `collector_visits`, `payment_receipts`, kolom tambahan di `payments`/`customers`, migrasi |
| [uat-checklist.md](uat-checklist.md) | Checklist pengujian manual per fase + uji temuan review + cara generate QR uji |

Rancangan asal & alasan tiap keputusan: [`docs/plan/kolektor/analisa-alur-kolektor-2.0.md`](../plan/kolektor/analisa-alur-kolektor-2.0%20.md).
Hasil review + perbaikannya: [`docs/plan/kolektor/review-fase-1-3.md`](../plan/kolektor/review-fase-1-3.md).

## Halaman & RBAC

| Halaman | Route | Permission | Buat siapa |
|---|---|---|---|
| **Worksheet Admin** | `/collector-worksheet` | `collector_worksheet.view` | admin, pop_admin, owner |
| **Detail Kolektor** (4 tab) | `/collector-worksheet/{collector}` | `collector_worksheet.view` + gerbang POP | idem |
| **Worklist Kolektor** | `/collector-worklist` | `kolektor.view` | kolektor |

### Permission aksi

| Permission | Role default | Dipakai untuk |
|---|---|---|
| `kolektor.view` | kolektor | Lihat worklist sendiri |
| `kolektor.pay` | kolektor | Catat pembayaran dari worklist sendiri (`POST /collector-worklist/pay`) |
| `kolektor.deposit` | kolektor | Setor seluruh saldo (`POST /collector-worklist/deposit`) |
| `kolektor.visit` | kolektor | Catat kunjungan tanpa hasil (`POST /collector-worklist/visits`) |
| `collector_worksheet.view` | admin, pop_admin | Buka Worksheet Admin |
| `collector_worksheet.assign` | admin, pop_admin | Assign / lepas pelanggan |
| `collector_worksheet.validate` | admin, pop_admin | Cross check & verifikasi setoran |
| `collector_worksheet.approve` | **owner saja** (lewat `*`) | Hapus buku selisih |
| `collector_worksheet.print` | admin, pop_admin | Cetak kwitansi ber-QR |
| `collector_worksheet.upload` | admin, pop_admin | Upload & cocokkan kwitansi |
| `payments.create` | admin, pop_admin | Bayar mewakili kolektor (`POST /payment-batches/{collector}`) |

`collector_worksheet.approve` **sengaja tidak diberikan ke `admin`** — matrix admin memakai daftar eksplisit, bukan wildcard `collector_worksheet.*`. Admin yang menemukan selisih tidak boleh sekaligus menutup kerugian temuannya sendiri.

### Realtime & endpoint pendukung

| Kanal / Route | Dipakai | Otorisasi |
|---|---|---|
| `collector-activity.{popId}` (broadcast) | Worksheet Admin — seluruh aktivitas kas kolektor di POP itu | `collector_worksheet.view` + POP scope |
| `App.Models.User.{id}` (broadcast) | Worklist Kolektor — aktivitas kas miliknya sendiri | pemilik akun |
| `GET /payment-receipts/progress/{collector}` | Panel progres pembacaan kwitansi | `collector_worksheet.view` |

Dua event berjalan di kanal yang sama, keduanya **`ShouldBroadcastNow`** (tidak lewat queue — lihat alasannya di `business-logic.md` §9):

| Event | Isi |
|---|---|
| `CollectorDepositUpdated` | siklus setoran: `diajukan` → `diverifikasi` → `dilunasi` → `dihapus_buku` |
| `CollectorActivityUpdated` | `pembayaran_dicatat`, `pembayaran_ditolak`, `pelanggan_diassign`, `pelanggan_dilepas` |

> Kanalnya sempat bernama `collector-deposits.{popId}`. Diganti 2026-08-11 begitu isinya melampaui setoran — nama yang berbohong tentang isinya adalah utang yang menyesatkan orang berikutnya. Nama route `collector-deposits.verify` / `.write-off` **tidak** ikut berubah; itu route, bukan kanal.

## Riwayat Perubahan

| Fase | Isi | Tanggal |
|---|---|---|
| **Fase 1** | Pisah Worksheet Admin vs Worklist Kolektor; kolektor mencatat pembayarannya sendiri; jendela tagih 7 hari | 2026-08-08 |
| **Fase 2** | Saldo turunan, Setoran, cross check, selisih & pelunasan lintas setoran, hapus buku Owner | 2026-08-08 |
| **Fase 3** | Visit Log + laporan aging per kolektor | 2026-08-08 |
| **Perbaikan review Fase 1–3** | 10 temuan (#1–#9 + kebersihan) & 2 sisa (R1, R2) | 2026-08-08 |
| **Perbaikan review Fase 4** | 11 temuan — termasuk satu **HIGH**: idempotency key sempat dipakai bersama antar-permintaan yang sedang jalan, sehingga pembayaran kedua hilang dengan gejala toast hijau | 2026-08-08 |
| **Fase 4** | Kwitansi ber-QR, upload bulk, pencocokan otomatis (QR) + OCR cadangan + override manual | 2026-08-08 |
| **Fase 5** | Pencocokan kwitansi lewat **lapisan teks PDF** (jalur utama, 100% akurat vs QR halaman-penuh 0%), lembar borongan 8 kwitansi/berkas, panel progres pembacaan | 2026-08-11 |
| **Fase 6** | **Realtime setoran** (`CollectorDepositUpdated`, dua kanal) + notifikasi hapus buku ke kolektor | 2026-08-11 |
| **Fase 7** | **Realtime aktivitas kas** (`CollectorActivityUpdated`) — pembayaran dicatat/ditolak & perubahan rute; notifikasi assign/lepas yang sebelumnya nol; kanal diganti nama jadi `collector-activity.{popId}` | 2026-08-11 |
| **Fase 8** | **Satu pembayaran = satu kwitansi** — kwitansi satuan dirender ulang dari data lewat halaman cetak (`payment_ids[]` satu id); berkas unggahan berperan sebagai arsip LEMBAR. Pemecahan lembar jadi PNG sempat dicoba lalu dibatalkan — lihat `business-logic.md` §12 | 2026-08-11 |

| **Fase 9** | **Isi kwitansi disatukan** — kartu kolektor, struk thermal, dan lembar A4 detail pembayaran kini membaca `ReceiptPresenter` yang sama; header/footer bawaan browser dimatikan di semua halaman cetak. Detail: `docs/billing-pembayaran/README.md` §Cetak Kwitansi | 2026-08-12 |

### Dua keputusan lama yang DIREVISI modul ini

`docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md` masih memuat dua aturan yang **tidak berlaku lagi**:

1. **§B-8 no. 4 & 5** — "kolektor tidak boleh input pembayaran", "UI kolektor read-only". Direvisi: kolektor mencatat pembayarannya sendiri lewat `kolektor.pay`. Yang tetap: tanpa `payments.create`, tak bisa buka halaman admin, tak bisa menagih di luar `collector_id`-nya.
2. **§B-11 ⛔ "DILUAR SCOPE"** — Setoran/rekonsiliasi kas. Direvisi: dihidupkan di Fase 2 dengan bentuk lebih ringan (`collector_deposits`, tanpa ledger).

Yang **tetap berlaku** dari dokumen lama: model mental Tagihan/Pembayaran (§B-2), dua kolom kolektor (§B-3), kelebihan bayar dikembalikan fisik (§B-8 no. 6), `payment_date` vs `collected_date` (§B-8 no. 8).
