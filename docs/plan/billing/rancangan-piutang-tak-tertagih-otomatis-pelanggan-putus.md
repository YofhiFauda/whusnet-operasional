# Rancangan: Piutang/Denda Pelanggan Putus — Grace Period Otomatis → Tak Tertagih (ADHOC-105)

**Status:** RANCANGAN, belum diimplementasi — **jangan dikerjakan sebelum ada perintah eksplisit dari user**. Di luar sprint aktif, permintaan eksplisit user (2026-09-26). Revisi 2026-09-28: 5 pertanyaan awal dan 2 pertanyaan lanjutan (A: tombol Kembalikan vs periode terkunci, B: penerima notifikasi) sudah dijawab user dan dimasukkan ke dokumen. Tidak ada pertanyaan terbuka tersisa; detail teknis opsi A2 (§3.4a) difinalisasi saat implementasi.

**Terkait:**
- [`rancangan-terminate-reactivate-state-machine.md`](rancangan-terminate-reactivate-state-machine.md) §13 (Langganan Lagi bercabang berdasarkan status alat, ADHOC-102 — **sudah diimplementasi**, akan disentuh lagi oleh rancangan ini)
- [`../../customer-lifecycle/business-logic.md`](../../customer-lifecycle/business-logic.md) §8 (Terminasi Layanan, List Putus Langganan)
- Fitur yang SUDAH ADA dan akan dipakai ulang, bukan dibangun dari nol: `InvoiceStatus::TAK_TERTAGIH`, `InvoiceWriteOffService::writeOff()`/`reverse()` (ADHOC-90), scheduler `billing:close-period` (ADHOC-96)

---

## 1. Latar Belakang

Ditemukan lewat pertanyaan user soal alur "Langganan Lagi" (ADHOC-102): apa yang terjadi ke piutang/denda pelanggan yang sudah putus langganan? Sebelum rancangan ini, tidak ada jawaban — invoice lama pelanggan `terminated` dibiarkan begitu saja selamanya (tetap `belum_dibayar`/`sebagian`, ikut terhitung piutang tiap bulan lewat `Invoice::scopePiutang()` yang murni berbasis tanggal, tanpa batas waktu), dan `reactivate()` tidak mengecek utang sama sekali.

User mengusulkan dua skema (istilah akuntansi: **piutang tak tertagih** / *bad debt*):

**Skema 1 — dibayar dalam grace period → case close.**
Deac/putus 15 September, belum bayar sampai akhir September → masuk periode Oktober (masih dianggap piutang normal, tampil di tab Tagihan) → dibayar sebelum akhir Oktober → lunas, selesai, tidak ada tindak lanjut.

**Skema 2 — tidak dibayar sampai grace period habis → dipindah ke Putus Langganan sebagai "piutang tak tertagih".**
Sama sampai akhir Oktober masih belum dibayar → begitu masuk November, tagihan (denda + piutang) **hilang dari tab Tagihan** dan **muncul sebagai kolom baru di halaman Putus Langganan** (tercatat, bukan dihapus). Kolom itu bisa diklik untuk detail + tombol **"Kembalikan"** yang memasukkan tagihan itu lagi ke tab Tagihan kalau pelanggan akhirnya mau bayar.

**Reaktivasi dengan utang:** kalau pelanggan yang statusnya sudah "piutang tak tertagih" ini mau Langganan Lagi, utangnya **wajib muncul kembali & lunas dulu** sebelum reaktivasi diproses — bukan otomatis terhapus/diabaikan begitu aktif lagi.

## 2. Keputusan Terkonfirmasi (user, 2026-09-26)

| # | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Kapan tepatnya auto-write-off jalan? | **Ngikut kalender bulan.** Grace = sisa bulan pemutusan + satu bulan penuh berikutnya. Auto write-off jalan tanggal 1 dua bulan setelah bulan pemutusan (bareng scheduler `billing:close-period` yang sudah ada, ADHOC-96). |
| 2 | Reactivate kalau masih ada piutang **normal** (belum sampai grace habis, masih di tab Tagihan)? | **Block total.** `reactivate()` ditolak selama ada invoice `belum_dibayar`/`sebagian` milik pelanggan itu — gak peduli tipe (denda/piutang bulanan) atau cabang reaktivasi (langsung `active` maupun lewat `waiting_survey`). |
| 3 | Reactivate kalau piutang sudah **Tak Tertagih**? | **Tombol terpisah, manual, 2 langkah.** Kolom Tagihan baru di Putus Langganan punya tombol "Kembalikan" sendiri (independen dari tombol Langganan Lagi). Tombol **Langganan Lagi ditolak** selama masih ada invoice `tak_tertagih` ATAU `belum_dibayar`/`sebagian` milik pelanggan — admin harus klik Kembalikan dulu, tagihan lunas dulu, baru Langganan Lagi bisa diproses. |
| 4 | Scope eksekusi? | **Rancangan dulu** (dokumen ini) — review dulu sebelum coding. |
| 5 | Tombol Kembalikan: satu-satu atau borongan? | **Keduanya.** Per invoice (satu-satu) dan satu tombol untuk semua invoice tak tertagih milik pelanggan itu (borongan). Lihat §3.4. (jawaban user 2026-09-28) |
| 6 | Notifikasi in-app saat invoice otomatis jadi tak tertagih? | **Perlu.** Lihat §3.5. Penerima usulan disetujui user 2026-09-28 (pendaftar asli + user ber-permission `invoices.approve` dalam POP scope pelanggan). |
| 9 | Tombol Kembalikan setelah periode write-off terkunci tutup buku? | **Tombol Kembalikan di halaman Putus Langganan harus SELALU aktif**, tidak boleh mati setelah ±1 bulan, supaya pelanggan tetap bisa membayar tagihannya kapan pun. Konsekuensinya opsi A1 (biarkan mati) ditolak; dipakai **opsi A2**: `reverse()` boleh untuk write-off yang periodenya terkunci, pemulihannya dicatat di **bulan berjalan**, laporan bulan lama tidak bergeser. Lihat §3.4a. (jawaban user 2026-09-28) |
| 7 | Nomor ADHOC | **Tetap ADHOC-105.** Sudah dicek 2026-09-28: `docs/TASKS.md` hanya punya satu entri ADHOC-105 (milik rancangan ini); nomor tertinggi sekarang ADHOC-110, jadi tidak ada tabrakan. |
| 8 | Guard `writeOff()` untuk invoice denda (poin 1 & "setuju" user) | **Tidak perlu diubah.** Hasil pengecekan kode: invoice denda punya `billing_period` terisi (§3.1). `writeOff()` dipakai apa adanya. |

## 3. Aturan Bisnis Detail

### 3.1 Definisi Grace Period

Dihitung dari `customers.terminated_at`, BUKAN dari `invoice.billing_period` per-invoice — semua utang pelanggan itu (denda + piutang lama, berapa pun bulan menunggaknya sebelum putus) diperlakukan sebagai **satu bundel**, dapat **satu** jendela grace yang sama.

```
terminated_at jatuh di bulan M (kalender)
  → Grace window: sisa bulan M + seluruh bulan M+1
  → Batas akhir: 23:59:59 hari terakhir bulan M+1
  → Auto write-off dieksekusi: tanggal 1 bulan M+2, 00:10
    (bareng job billing:close-period — scheduler yang sama, hemat satu job baru)
```

Contoh (`terminated_at` = 15 September):
- M = September, M+1 = Oktober, M+2 = November.
- Belum bayar sampai 31 Oktober 23:59:59 → tanggal 1 November job jalan → seluruh invoice `belum_dibayar`/`sebagian` milik pelanggan itu di-write-off jadi `tak_tertagih`.
- Kalau bayar (lunas semua) kapan saja sebelum 1 November job jalan → tidak ada yang di-write-off (case close, skema 1).

**Kenapa jendela grace dihitung dari `terminated_at`, bukan per-invoice `billing_period`:** supaya denda + piutang lama pelanggan itu satu bundel, satu jendela. Berapa bulan pun pelanggan sudah menunggak sebelum putus, semuanya jatuh tempo write-off di tanggal yang sama.

**Terverifikasi 2026-09-28 (koreksi asumsi awal rancangan):** invoice Denda Putus Langganan **punya `billing_period` terisi** — `CustomerTerminationService.php:122` mengisinya `now()->format('Y-m')` (bulan pemutusan) dan dipakai di `Invoice::create` baris 134. Jadi denda terbit di bulan M, otomatis berstatus piutang mulai M+1 (`scopePiutang()`), dan sudah piutang saat job jalan di M+2. Konsekuensinya `InvoiceWriteOffService::writeOff()` yang ada (guard `isPiutang()`) **lolos untuk denda tanpa perubahan apa pun**. Query job tetap eksplisit `invoice_status IN (belum_dibayar, sebagian)` per `customer_id`, tapi tidak butuh method/guard baru di service.

**Satu edge case yang harus ditangani job:** invoice manual yang sengaja dibuat admin untuk pelanggan `terminated` dengan `billing_period` ≥ M+2 (bulan berjalan) bukan piutang, jadi `writeOff()` akan menolaknya (`ValidationException`). Job harus **menangkap penolakan itu per invoice, mencatat log warning, lalu lanjut** ke invoice berikutnya — satu invoice yang ditolak tidak boleh menghentikan seluruh job. Invoice tersebut baru ikut terproses pada run bulan berikutnya, saat sudah berstatus piutang.

### 3.2 Syarat Eksekusi Job Auto Write-off

Per pelanggan `status = terminated` dengan `terminated_at` yang grace-nya sudah lewat (§3.1):

1. Pelanggan **masih** `status = terminated` di saat job jalan (kalau sudah Langganan Lagi sebelum tanggal eksekusi, dilewati — tapi lihat §3.3, reactivate sendiri sudah di-block kalau masih ada utang, jadi kasus ini seharusnya cuma terjadi kalau utangnya sempat lunas dulu baru grace habis berikutnya — edge case kecil, aman dilewati).
2. Ambil SEMUA invoice milik pelanggan dengan `invoice_status IN (belum_dibayar, sebagian)`, apa pun tipenya (denda, bulanan, awal, manual).
3. Tiap invoice di-`InvoiceWriteOffService::writeOff()` **apa adanya** (tanpa method baru, lihat §3.1 hasil verifikasi) dengan `reason` baku, mis. `"Auto write-off: pelanggan putus, grace period habis (ADHOC-105)"`, actor = user sistem (pola sama `CustomerWorkflowService::transition()` fallback `Auth::id() ?? 1`).
4. Penolakan `writeOff()` per invoice (edge case §3.1) ditangkap, dilog, dan dilewati — job lanjut.
5. Idempoten: invoice yang sudah `tak_tertagih` dilewati (filter status di poin 2 menjamin ini). Job aman dijalankan ulang.
6. Kirim notifikasi in-app sekali per pelanggan (bukan per invoice) setelah semua invoice pelanggan itu diproses — lihat §3.5.

### 3.3 Gate di `CustomerController::reactivate()`

Ditambah SEBELUM percabangan device (ADHOC-102), berlaku ke KEDUA cabang:

```
$hasOutstanding = Invoice::where('customer_id', $customer->id)
    ->whereIn('invoice_status', [BELUM_DIBAYAR, SEBAGIAN, TAK_TERTAGIH])
    ->exists();

if ($hasOutstanding) {
    return back()->with('error', 'Pelanggan ini masih punya tagihan/piutang belum lunas. Lunasi dulu sebelum Langganan Lagi.');
}
```

`TAK_TERTAGIH` ikut di-cek di sini juga (bukan cuma `belum_dibayar`/`sebagian`) — supaya keputusan §2 poin 3 ("Langganan Lagi ditolak selama masih ada invoice tak_tertagih") tegak tanpa perlu logic terpisah.

### 3.4 Tombol "Kembalikan" di Halaman Putus Langganan

**Backend SUDAH ADA sepenuhnya** — `InvoiceWriteOffService::reverse()` + route `invoices.write-off.reverse` (permission `invoices.approve`, sudah dipasang ADHOC-90). Yang perlu ditambah cuma UI:

- Kolom baru **"Tagihan"** di `customers/terminated.blade.php` (tabel & card, dua lokasi sama seperti kolom Status Alat) — badge jumlah invoice `tak_tertagih` + total nominal, cuma tampil kalau ada (0 = kolom kosong/dash).
- Klik kolom → modal detail (daftar invoice `tak_tertagih` milik pelanggan itu: nomor, tipe, nominal, tanggal write-off, alasan). Modal ini **view-only** (pola 1 di CLAUDE.md "Aksi baru"), tombol aksinya yang mengirim POST.
- **Dua tombol Kembalikan (keputusan user 2026-09-28):**
  - **Satu-satu** — tombol per baris invoice, POST ke `invoices.write-off.reverse` yang sudah ada.
  - **Borongan** — satu tombol "Kembalikan Semua" untuk seluruh invoice `tak_tertagih` milik pelanggan itu. Butuh endpoint baru (mis. `customers.write-off.reverse-all`, permission tetap `invoices.approve`) yang memanggil `InvoiceWriteOffService::reverse()` per invoice dalam **satu transaksi**: kalau ada satu invoice yang ditolak (mis. periode terkunci, lihat §3.4a), seluruhnya dibatalkan dan pesan error menyebut invoice mana yang menghalangi — jangan sampai separuh terkembalikan diam-diam.
  - Target URL kedua tombol dirender server-side lewat `route()` (aturan CLAUDE.md "Target aksi yang mengubah data").
- Redirect setelah Kembalikan: saat ini `InvoiceController::reverseWriteOff()` redirect ke `invoices.show` — dari konteks halaman Putus Langganan lebih pas balik ke `customers.terminated` (perlu parameter redirect atau query balik, bukan ubah redirect default supaya jalur lama dari `/invoices/{id}` tidak rusak). Endpoint borongan langsung redirect ke `customers.terminated`.

### 3.4a Kembalikan harus selalu aktif walau periode write-off terkunci (keputusan user 2026-09-28: opsi A2)

**Masalah yang ditemukan:** `InvoiceWriteOffService::reverse()` (baris 66-73) menolak pembatalan hapus buku kalau `written_off_at` jatuh di periode tutup buku (`BookPeriod::isLocked()`, permanen sejak ADHOC-96). Auto write-off jatuh tanggal 1 bulan M+2, jadi mulai tanggal 1 bulan M+3 tombol Kembalikan otomatis ditolak. `PaymentService.php:58` juga menolak bayar ke invoice `tak_tertagih`, sehingga utang itu tidak bisa dibayar sama sekali setelah periodenya terkunci.

**Keputusan user:** tombol Kembalikan di halaman Putus Langganan harus **selalu aktif**, agar pelanggan tetap bisa membayar tagihannya kapan pun. Dipilih **opsi A2** (dari tiga opsi yang diajukan; A1 "biarkan mati" ditolak, A3 "tagihan manual baru" tidak dipilih karena user mau tagihan asli yang kembali ke tab Tagihan).

**Aturan A2:**
1. Untuk write-off yang periodenya **belum** terkunci → `reverse()` berjalan seperti sekarang (kolom `written_off_*` dikosongkan, status dihitung ulang dari payment).
2. Untuk write-off yang periodenya **sudah** terkunci → `reverse()` **diizinkan** (guard periode terkunci di baris 66-73 tidak lagi menolak), tapi **jejak write-off lama tidak dihapus**. Pemulihannya dicatat di **bulan berjalan** sebagai pengurang, persis pola pembayaran yang di-Kembalikan (ADHOC-96: `payments.rejected_at` + `CollectorMonthlyReportService::countedAsOf()` + kolom `dikembalikan`).
3. Laporan bulan lama **tidak boleh bergeser**. Angka "Piutang tak Tertagih" bulan M+2 tetap memuat invoice itu walau sekarang sudah dipulihkan, karena pada tanggal laporan itu write-off memang berlaku.

**Sketsa teknis (final saat implementasi):**
- Kolom baru di `invoices`: `write_off_reversed_at` (nullable timestamp) + `write_off_reversed_by`. Untuk write-off terkunci, `written_off_at`/`written_off_amount`/`write_off_reason` **dipertahankan** sebagai riwayat dan hanya `write_off_reversed_at` yang diisi. Perlu migrasi.
- Pembacaan laporan "sah per tanggal" (analog `countedAsOf()`): write-off dianggap berlaku pada tanggal T bila `written_off_at < T` dan (`write_off_reversed_at` null atau `write_off_reversed_at >= T`). Dipakai di `CollectorMonthlyReportService` pada query piutang pembuka (baris ±300) dan agregat tak tertagih (±321-332), plus drill-down (±646-654, ±669).
- Bulan pemulihan menampilkan angka pemulihan sebagai pengurang di Blok 2 (mis. kolom/baris "Tak Tertagih Dipulihkan"). Snapshot bulan lama belum punya kunci ini, jadi pembaca wajib `?? 0` (pola yang sama dengan `dikembalikan`, `CollectorMonthlyReportService.php:137-139`).
- Setelah dipulihkan, invoice kembali `belum_dibayar`/`sebagian` (dihitung ulang dari payment) dan muncul lagi di tab Tagihan serta terhitung piutang seperti biasa, jadi `PaymentService.php:58` tidak lagi menolaknya.
- Invoice yang sama boleh di-write-off lagi kemudian (mis. grace baru setelah putus lagi). Riwayat lebih dari satu siklus write-off per invoice **belum dirancang**: kolom tunggal hanya menyimpan siklus terakhir. Untuk versi awal cukup audit log (`RecordsAuditLogs` di model Invoice sudah mencatat perubahan kolom) sebagai jejak siklus lama. Perlu dikonfirmasi saat implementasi apakah itu cukup.

**Yang tidak berubah:** aturan tutup buku untuk pembayaran dan jalur write-off/reverse manual lain tetap sama. Perubahan guard di `reverse()` **hanya** melonggarkan penolakan periode terkunci, bukan menghapus konsep kunci periode.

### 3.5 Notifikasi In-App (keputusan user 2026-09-28: perlu)

Pola yang sudah ada dan akan diikuti: `CustomerTerminationController.php:55-69` — `$user->notify(new AppNotification(title:, message:, actionUrl:, type: NotificationType::...))`. Enum `NotificationType` punya `INFO`/`ERROR`/`WARNING`/`SUCCESS`.

**Disetujui user 2026-09-28 (penerima: pendaftar asli + user ber-permission `invoices.approve` dalam POP scope pelanggan):**

| Kejadian | Pesan | Tipe | Penerima usulan |
|---|---|---|---|
| Job auto write-off memproses pelanggan (sekali per pelanggan, bukan per invoice) | "Piutang Tak Tertagih: {nama}" — "{n} tagihan senilai {total} otomatis dihapus buku karena masa tenggang setelah putus langganan habis." | `WARNING` | Pendaftar asli (`customers.created_by`, sama seperti notifikasi terminate) + user yang punya `invoices.approve` dalam POP scope pelanggan itu |
| Admin menekan Kembalikan (satu-satu/borongan) | "Tagihan dikembalikan: {nama}" — detail jumlah & nominal | `INFO` | Tidak wajib; default **tidak dikirim** (aksinya dilakukan sendiri oleh user yang login, cukup flash message). Ubah kalau user minta. |

- `actionUrl` = `route('customers.terminated')` dengan filter/anchor ke pelanggan itu kalau memungkinkan, atau `customers.show`.
- Job berjalan tanpa user login, jadi jangan bergantung ke `auth()->id()` (pola "skip kalau penerima = pelaku" di controller terminate tidak berlaku di sini).
- Penerima wajib difilter POP scope lewat `EffectiveAccessService` (larangan keras #3 di CLAUDE.md) — jangan kirim ke semua admin lintas cabang.
- Tidak ada perubahan skema DB: `AppNotification` memakai tabel notifikasi Laravel yang sudah ada.

### 3.6 Yang TIDAK Berubah / Di Luar Scope

- `InvoiceWriteOffService::writeOff()`/`reverse()` manual oleh admin (tombol di `/invoices/{id}`, ADHOC-90) tetap ada apa adanya, dipakai ulang bukan diganti.
- Piutang pelanggan **aktif** (bukan `terminated`) sama sekali tidak tersentuh rancangan ini — grace period & auto write-off cuma berlaku selama status `terminated`.
- Tidak ada invoice baru yang dibuat oleh job ini — cuma mengubah `invoice_status` invoice yang sudah ada.
- Denda yang **belum** melewati grace tapi pelanggan Langganan Lagi lebih dulu — tetap jadi invoice normal (di-block reactivate-nya, lihat §3.3), tidak pernah masuk siklus tak_tertagih kalau keburu lunas atau kalau (secara teori) grace belum habis saat itu.

## 4. Alur Ringkas (State)

```
Pelanggan ACTIVE/SUSPENDED
  │ terminate() (ADHOC-85)
  ▼
TERMINATED, invoice lama tetap belum_dibayar/sebagian
  │
  ├─ Lunas sebelum grace habis ──────────────► Case Close (Skema 1)
  │
  └─ Grace habis (§3.1), masih belum lunas
        │ job tanggal 1 bulan M+2
        ▼
     Invoice → TAK_TERTAGIH (InvoiceWriteOffService::writeOff, otomatis)
        │  tampil sebagai kolom "Tagihan" di halaman Putus Langganan (§3.4)
        │
        ├─ Admin klik "Kembalikan" (manual, §3.4) ─► Invoice balik belum_dibayar
        │                                              │ dibayar lunas
        │                                              ▼
        └─ Selama masih ada invoice belum_dibayar/sebagian/tak_tertagih ──► Langganan Lagi DITOLAK (§3.3)
                                                          │ semua lunas
                                                          ▼
                                                    Langganan Lagi diproses (ADHOC-102: active/waiting_survey)
```

## 5. Perubahan Teknis (Rencana, Belum Dieksekusi)

| # | File | Perubahan |
|---|---|---|
| 1 | `app/Console/Commands/AutoWriteOffTerminatedCustomersCommand.php` (baru) | Command baru, dijadwalkan `console.php`/`Kernel` bareng `billing:close-period` (tanggal 1, 00:10 atau sesaat setelahnya). Query §3.2, panggil write-off per invoice. `--dry-run` (pola sama command billing lain di repo) wajib ada untuk verifikasi sebelum aktif di produksi. |
| 2 | `app/Services/InvoiceWriteOffService.php` | `writeOff()` **tidak berubah** untuk jalur otomatis (denda punya `billing_period`, §3.1). `reverse()` **diubah** sesuai opsi A2 (§3.4a): guard periode terkunci (baris 66-73) tidak lagi menolak, dan untuk write-off terkunci hanya `write_off_reversed_at`/`_by` yang diisi (riwayat `written_off_*` dipertahankan). Logika pemilihan pelanggan/invoice grace (query §3.2) ditaruh di Service — mis. `TerminatedCustomerWriteOffService` — bukan di command, sesuai aturan layer CLAUDE.md. |
| 2a | Migrasi baru + `app/Models/Invoice.php` | Kolom `write_off_reversed_at`, `write_off_reversed_by` di `invoices` (§3.4a), masuk `$fillable`/casts. Ikuti pola migrasi `2026_09_22_100000_add_write_off_columns_to_invoices_table`. |
| 2b | `app/Services/CollectorMonthlyReportService.php` (+ `CollectorMonthlyReportController` ekspor CSV, view laporan) | Write-off dibaca "sah per tanggal" (§3.4a) di query piutang pembuka, agregat tak tertagih, dan drill-down; tambah tampilan pemulihan sebagai pengurang di bulan berjalan; pembaca snapshot lama wajib `?? 0`. |
| 3 | `app/Http/Controllers/CustomerController.php` (`reactivate()`) | Tambah guard §3.3 SEBELUM baca `device_retrieved_at` — kalau ada outstanding, `return back()->with('error', ...)` duluan, gak lanjut ke transaksi/percabangan ADHOC-102. |
| 4 | `resources/views/customers/terminated.blade.php` | Kolom baru "Tagihan" (2 lokasi: tabel + card) + modal detail + tombol Kembalikan. Perlu query tambahan di controller list (`CustomerTerminatedController`?) untuk eager-load invoice `tak_tertagih` per pelanggan (hindari N+1). |
| 5 | `app/Http/Controllers/InvoiceController.php` (`reverseWriteOff()`) + route/endpoint borongan baru | Redirect balik ke halaman asal (Putus Langganan vs Detail Invoice) — perlu parameter `redirect_to` atau baca `url()->previous()` terbatas ke dua rute yang valid (jangan open redirect). Endpoint borongan "Kembalikan Semua" (§3.4) didaftarkan di grup `permission:invoices.approve`, statis sebelum dinamis. |
| 5a | Notifikasi (§3.5) | `AppNotification` dikirim dari service/command, penerima difilter POP scope lewat `EffectiveAccessService`. Tanpa migrasi. |
| 6 | Scheduler (`routes/console.php` atau `app/Console/Kernel.php`, cek yang dipakai repo untuk `billing:close-period`) | Daftarkan command baru. |
| 7 | Test baru | `AutoWriteOffTerminatedCustomersCommandTest` (skema 1 lunas sebelum grace = tidak ke-writeoff, skema 2 lewat grace = ter-writeoff, **denda (billing_period bulan putus) ikut ke-writeoff**, pelanggan sudah reaktif dilewati, idempoten dijalankan 2x, satu invoice ditolak `writeOff()` tidak menghentikan invoice lain, batas tepat 31 Okt 23:59 vs 1 Nov 00:10, notifikasi terkirim sekali per pelanggan & hanya ke penerima dalam POP scope), `CustomerReactivateBlockedByOutstandingInvoiceTest` (block reactivate ada piutang normal DAN ada tak_tertagih, lolos begitu lunas semua, berlaku di cabang active maupun waiting_survey), test UI kolom Tagihan + Kembalikan satu-satu + Kembalikan borongan (atomik: satu gagal = semua batal), **Kembalikan tetap berhasil untuk write-off periode terkunci** (invoice kembali ke tab Tagihan, bisa dibayar), **laporan bulan lama tidak bergeser setelah pemulihan** (angka tak tertagih bulan M+2 tetap sama, bulan pemulihan menampilkan pengurang), dan pembayaran ke invoice yang sudah dipulihkan diterima. |
| 8 | Dokumentasi | `docs/customer-lifecycle/business-logic.md` §8 (tambah aturan grace+block), `docs/billing-pembayaran/README.md` (kalau ada bagian piutang/write-off yang perlu disebut), `docs/TASKS.md` (entry ADHOC-105 begitu diimplementasi). |

## 6. Status Pertanyaan

### 6.1 Sudah terjawab (user 2026-09-28)

| # | Pertanyaan awal | Hasil |
|---|---|---|
| 1 | `billing_period` invoice denda null atau terisi? | **Terisi** (`CustomerTerminationService.php:122,134`). Tidak butuh guard/method baru. Masuk §3.1, §3.2. |
| 2 | Kembalikan satu-satu atau borongan? | **Keduanya.** Masuk §3.4. |
| 3 | Notifikasi in-app? | **Perlu.** Masuk §3.5 (penerima masih usulan → poin B). |
| 4 | Apa maksud "tampil beda di laporan"? | Dijelaskan di §6.2. Default dipakai: **digabung dengan write-off manual**, dibedakan lewat `write_off_reason`. |
| 5 | Nomor ADHOC | **ADHOC-105 tetap**, sudah dicek tidak bentrok (§2 baris 7). |

### 6.2 Penjelasan pertanyaan #4: tak tertagih masuk laporan apa?

Ya, tagihan berstatus `tak_tertagih` **sudah muncul di satu laporan yang ada**, bukan laporan baru: **Laporan Bulanan Admin Collector** (`CollectorMonthlyReportService`, `CollectorMonthlyReportController`), di **Blok 2 "Piutang Bulan Lalu"**, kolom **"Piutang tak Tertagih"** per OLT/POP (ADHOC-90, `CollectorMonthlyReportService.php:321-332`).

Cara hitungnya: semua invoice yang `written_off_at` jatuh **di bulan laporan** dijumlahkan `written_off_amount`-nya. Artinya:
- Auto write-off tanggal 1 November → angkanya masuk laporan **November**, kolom Piutang tak Tertagih (bukan laporan Oktober).
- Invoice yang sudah dihapus buku **sebelum** awal bulan laporan tidak lagi dihitung sebagai piutang pembuka bulan itu (`CollectorMonthlyReportService.php:299-300`), jadi tidak terhitung dobel.
- Detail per baris (nama pelanggan, nomor invoice, tanggal, nominal, alasan) bisa dibuka dari kolom itu (`CollectorMonthlyReportService.php:646-654`), dan tercantum di ekspor.

Yang ditanyakan di rancangan: apakah angka dari auto write-off pelanggan putus perlu **dipisah** dari write-off manual admin di laporan itu (mis. dua baris/kolom terpisah). **Default rancangan: digabung** — tidak ada perubahan laporan, dan pembedaan cukup lewat teks `write_off_reason` yang tampil di detail per baris. Kalau mau dipisah, itu tambahan pekerjaan di `CollectorMonthlyReportService` + tampilan + ekspor CSV, dan belum masuk scope rancangan ini.

Catatan waktu: job jalan tanggal 1 pukul 00:10, sama dengan `billing:close-period` yang membekukan bulan sebelumnya. Write-off distempel `written_off_at` bulan berjalan (M+2), jadi **tidak menggeser laporan bulan M+1 yang sedang dibekukan**, dan urutan kedua job tidak menimbulkan balapan.

### 6.3 Pertanyaan lanjutan (terjawab user 2026-09-28)

| # | Pertanyaan | Jawaban |
|---|---|---|
| A | Tombol Kembalikan diblokir periode terkunci setelah ±1 bulan. Pilih A1 (biarkan mati), A2 (izinkan, catat pemulihan di bulan berjalan), atau A3 (tagihan manual baru)? | Tombol **harus selalu aktif** supaya pelanggan tetap bisa membayar. **A2 dipakai** (A1 ditolak; A3 tidak dipilih karena yang kembali harus tagihan aslinya). Desain di §3.4a. |
| B | Penerima notifikasi? | **Disetujui usulan:** pendaftar asli + user ber-permission `invoices.approve` dalam POP scope pelanggan (§3.5). |

Tidak ada pertanyaan terbuka. Yang masih "difinalisasi saat implementasi" hanya detail teknis di §3.4a (bentuk kolom pemulihan, tampilan pengurang di laporan, dan apakah audit log cukup sebagai jejak bila satu invoice di-write-off lebih dari sekali).

## 7. Rollback

Rancangan ini penambahan (command baru + guard baru + kolom UI baru + dua kolom baru di `invoices` via migrasi, poin 5.2a), dan mengubah perilaku `reverse()` serta pembacaan laporan bulanan (poin 5.2/5.2b). Data lama tidak diubah. Kalau perlu dibatalkan pasca-implementasi: nonaktifkan scheduler command baru (poin 5.1/5.6), hapus guard di `reactivate()` (poin 5.3), sembunyikan kolom UI (poin 5.4), kembalikan guard periode terkunci di `reverse()` (poin 5.2). Kolom `write_off_reversed_*` boleh dibiarkan (nullable, tidak mengganggu). Catatan: pemulihan yang sudah terjadi di periode terkunci tidak bisa dibalik otomatis, cukup di-write-off ulang manual — invoice yang sudah kadung `tak_tertagih` oleh job ini bisa di-`reverse()` manual satu-satu lewat mekanisme ADHOC-90 yang sudah ada (tidak hilang, cuma butuh kerja manual kalau mau dibalikin massal).
