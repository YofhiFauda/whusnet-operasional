# Rancangan: Terminasi & "Langganan Lagi" Lewat State Machine (ADHOC-85)

**Status:** Selesai diimplementasi 2026-09-19. Di luar sprint aktif (Sprint 8.10), dikerjakan atas persetujuan eksplisit user.

**Terkait:**
- [`../../customer-lifecycle/business-logic.md`](../../customer-lifecycle/business-logic.md) §1 (tabel transisi) dan §8 (Terminasi Layanan)
- [`../../api/api-portal-pelanggan/business-logic.md`](../../api/api-portal-pelanggan/business-logic.md) §Token — akun portal & QR
- [`analisa-rancangan-putus-langganan.md`](analisa-rancangan-putus-langganan.md) — ADHOC-69, **belum diimplementasi**, akan mengganti isi `CustomerTerminationController`. Lihat §7 dokumen ini.
- [`rancangan-piutang-tak-tertagih-otomatis-pelanggan-putus.md`](rancangan-piutang-tak-tertagih-otomatis-pelanggan-putus.md) — ADHOC-105, **RANCANGAN, belum diimplementasi**. Akan menambah guard piutang/denda ke `CustomerController::reactivate()` (§13 dokumen ini) — Langganan Lagi bakal ditolak kalau pelanggan masih punya invoice belum lunas/tak tertagih. Belum berlaku sampai diimplementasi.

---

## 1. Masalah

Dua aksi mengubah `customers.status` dengan `$customer->update()` langsung, melewati `CustomerWorkflowService::transition()`:

| Aksi | Controller | Akibat sebelum perbaikan |
|---|---|---|
| Putus Langganan | `CustomerTerminationController` | Tidak ada validasi status asal. `AuditLog.old_values` di-hardcode `'active'`. Tidak masuk `customer_status_logs`. |
| Langganan Lagi | `CustomerController::reactivate()` | Enum bilang `TERMINATED => []` (terminal), kodenya bilang sebaliknya. Tidak masuk `customer_status_logs`. Akun portal & QR tidak dipulihkan. |

Rincian gejala:

1. **Terminasi tanpa guard status.** Endpoint hanya mengecek permission `customers.deactivate` dan alasan. POST manual bisa memutus pelanggan `waiting_survey` / `rejected`, atau memutus ulang yang sudah `terminated` (menimpa `terminated_at`, menambah baris audit).
2. **Riwayat status bolong.** `customer_status_logs` berhenti di status terakhir sebelum putus; timeline yang membacanya tidak tahu pelanggan sudah putus atau sudah kembali.
3. **`old_values` audit salah.** Pelanggan `suspended` yang diputus tercatat "dari active".
4. **Pelanggan yang Langganan Lagi terkunci dari portal.** `CustomerObserver` men-`disabled` akun portal dan mencabut token API + QR saat terminate, tapi tidak ada yang membalikkannya. `PortalAuthService::claim()` menolak akun `disabled` sebagai `invalid`, dan token QR sudah dicabut sehingga PIN pun tidak ada.
5. **Temuan tambahan dari penelusuran portal — celah keamanan:** `PortalAuthService::login()` tidak pernah membaca `customer_portal_accounts.status`. Terminate hanya mencabut token yang sudah ada; pelanggan putus tetap bisa `POST /auth/login` dengan password lama dan mendapat pasangan token baru, karena `EnsurePortalCustomerToken` hanya memeriksa token, bukan status akun. Test lama (`PortalCustomerObserverDisablesPortalOnTerminatedTest`) hanya membuktikan token *lama* mati, bukan bahwa login ulang ditolak.

## 2. Keputusan

| # | Keputusan | Alasan |
|---|---|---|
| 1 | Terminasi lewat `transition()` | Guard status, `customer_status_logs`, dan `terminated_at` didapat gratis. `terminated` sudah ada di daftar transisi `active` dan `suspended`. |
| 2 | Audit `customers`/`terminate` **tetap ditulis**, dengan `old_values` status asli | `RendersCustomerList` (kolom Alasan di List Putus Langganan) dan import legacy membaca baris ini. Menghapusnya mengosongkan kolom Alasan. Konsekuensi: dua baris audit per terminasi (`Customer Workflow`/`status_transition` dan `customers`/`terminate`). Ini kompromi sadar sampai ADHOC-69 memindahkan alasan ke kolom master. |
| 3 | Enum: `TERMINATED => [ACTIVE]`, hanya itu | Meresmikan jalur yang sudah ada. Tidak boleh lompat ke survey/pemasangan/isolir. |
| 4 | Langganan Lagi lewat `transition()`; audit khusus `reactivate` dibuang | Tidak ada pembacanya di kode. Jejak ada di `customer_status_logs` + audit `status_transition` (note `Langganan Lagi`). Baris `reactivate` lama tetap utuh. |
| 5 | **`terminated_at` TIDAK dikosongkan** saat Langganan Lagi | Lihat §4. Ini **membalik** usulan awal ("kosongkan supaya tidak ada timestamp basi"). |
| 6 | Pemulihan akun portal & QR di `CustomerObserver`, logikanya di `PortalAuthService::restoreAfterReactivation()` | Sama seperti penonaktifannya: invariant "akun portal & QR mengikuti status pelanggan" harus jalan dari semua jalur (`transition()`, tinker, import), bukan cuma tombol. Logic di Service sesuai pembagian layer repo. |
| 7 | Akun portal pulih ke **`pending_claim`**, bukan `active` | Lihat §3. |
| 8 | `login()` menolak akun `disabled` | Menutup celah §1 poin 5. Tanpa ini `disabled` tidak berarti apa-apa. |

## 3. Alur Pemulihan Portal & QR

Terminate (tidak berubah, `CustomerObserver`):
```
status → terminated
  ├─ customer_portal_accounts.status = disabled
  ├─ semua customer_portal_tokens dicabut
  └─ token QR aktif dicabut (revoke_reason 'Pelanggan terminated')
```

Langganan Lagi (baru):
```
transition(TERMINATED → ACTIVE, note 'Langganan Lagi')
  └─ CustomerObserver::updated  (getOriginal('status') = terminated, status baru = active)
       └─ PortalAuthService::restoreAfterReactivation()
            ├─ akun disabled → pending_claim
            │    password_hash ditimpa placeholder acak
            │    failed_attempts/locked_until di-reset, token dicabut ulang (sabuk pengaman)
            │    AuditLog 'Portal Pelanggan' / account_restored_after_reactivation
            ├─ ensureAccountExists()      ← pelanggan legacy tanpa akun portal
            └─ QR: issue() token baru (yang lama sudah dicabut) + issuePin()
                 gagal RuntimeException (customer_code/pop_id kosong) → Log::warning,
                 TIDAK menggagalkan Langganan Lagi; admin terbitkan manual dari halaman QR.
```

**Kenapa `pending_claim`, bukan `active` + password lama.** Pemulihan ke `active` menghidupkan kembali kredensial pelanggan yang sudah putus tanpa ada yang membuktikan identitas ulang. `pending_claim` memaksa klaim ulang lewat PIN dari kartu QR baru — pola yang sama dengan `resetToPendingClaim()` ("Lupa Password"). Konsekuensi operasional: **kartu QR lama tidak berlaku**; staf perlu mencetak kartu baru dari `/qr/cetak` (PIN dapat ditampilkan ulang karena `pin_hash` reversible).

`claimed_at` tidak dikosongkan (jejak klaim pertama), sama seperti `resetToPendingClaim()`.

## 4. Kenapa `terminated_at` Tidak Dikosongkan

Usulan awal: kosongkan `terminated_at` saat Langganan Lagi supaya tidak ada timestamp basi pada pelanggan aktif. Dibatalkan setelah dicek pemakainya:

- `DashboardController::growthStats()` menghitung **churn per periode** dengan `whereBetween('terminated_at', …)`. Dikosongkan = churn bulan lalu ikut terhapus retroaktif tiap ada Langganan Lagi.
- `NocDashboardController::buildPerPopAnalytics()` memakai `terminated_at` untuk memperkirakan jumlah pelanggan per akhir bulan.

Dipertahankan. Terminasi berikutnya menimpanya dengan tanggal yang baru (perilaku wajar).

**Batasan yang diterima:**
- Card performa per POP (NOC) menganggap pelanggan yang pernah putus *keluar dari basis selamanya* setelah `terminated_at`, walau sudah Langganan Lagi → jumlah pelanggan bulan-bulan sesudahnya sedikit terlalu rendah. Kode itu sudah mendokumentasikan dirinya sebagai proksi tren, bukan snapshot presisi. Perbaikan proper (turunkan dari `customer_status_logs`) di luar scope.
- `growthStats()` menghitung `new_active_customers` dari `customer_status_logs` `to_status = active`. Karena Langganan Lagi sekarang menulis log itu, pelanggan yang kembali terhitung sebagai "aktif baru" di periode kembalinya, sementara churn-nya tetap di periode putus. Net growth konsisten (−1 lalu +1), tapi angka "aktif baru" bercampur pelanggan kembali. `toggleSuspend()` sudah berperilaku sama untuk aktivasi kembali dari isolir.

## 5. Perubahan Kode

| File | Perubahan |
|---|---|
| `app/Enums/WorkflowTransition.php` | `TERMINATED => [ACTIVE]` (sebelumnya `[]`) |
| `app/Http/Controllers/CustomerTerminationController.php` | Pre-check `canTransitionTo(TERMINATED)` → redirect back + `error` kalau tidak valid. `transition()` menggantikan `update()`. Audit `terminate` dipertahankan dengan `old_values` status asli. |
| `app/Http/Controllers/CustomerController.php` (`reactivate`) | `transition(ACTIVE, 'Langganan Lagi')` menggantikan `update()`; audit `reactivate` dibuang. `service_status = aktif` tetap di controller. |
| `app/Observers/CustomerObserver.php` | Blok TERMINATED → ACTIVE memanggil `restoreAfterReactivation()`. |
| `app/Services/CustomerPortal/PortalAuthService.php` | Method baru `restoreAfterReactivation()`; guard `disabled` di `login()`. |

Pre-check di controller (bukan menangkap `Exception` dari `transition()`) disengaja: `transition()` melempar `Exception` generik, dan menangkapnya akan menelan error DB sebagai "status tidak valid".

Tidak ada migrasi, kolom, atau route baru.

## 6. Efek Samping yang Perlu Diketahui

- **Pelanggan yang Langganan Lagi menerima notifikasi aktivasi lagi.** `transition()` ke `active` selalu dispatch `SendCustomerActivationNotification`. Dinilai wajar; kalau tidak diinginkan, kecualikan asal `terminated` di `transition()`.
- **Observer berjalan di dalam transaksi `transition()`.** Exception non-`RuntimeException` dari `restoreAfterReactivation()` membatalkan seluruh Langganan Lagi (status kembali `terminated`). Sengaja: lebih baik gagal utuh daripada pelanggan aktif dengan akun portal setengah pulih.
- **`CustomerAcquisition` tidak dibuat ulang** (`firstOrCreate`, sekali seumur hidup) — tidak berubah.

## 7. Hubungan dengan ADHOC-69 (Skema Putus Langganan)

ADHOC-69 (belum diimplementasi) akan memindahkan logika terminasi ke `CustomerTerminationService` dan menambah invoice denda (**tanpa prorate**, keputusan user 2026-09-19; tipe invoice & bentuk barisnya belum diputuskan, lihat §2.1 dokumen itu), serta master alasan (`customers.termination_reason_id`). Rancangan ini adalah **prasyarat yang searah**, bukan konflik:

- §5 ADHOC-69 sendiri mensyaratkan "transisi ke `terminated` tetap konsisten dengan state machine" — sekarang terpenuhi. Service baru cukup memanggil `transition()` seperti controller ini.
- Audit `customers`/`terminate` (keputusan §2 #2) adalah jembatan sementara. Begitu ADHOC-69 memindahkan alasan ke `termination_reason_id` dan `RendersCustomerList` membaca relasi, audit itu bisa dihentikan. Import legacy (`CustomerController` ~baris 2803) dan `BackfillStatusTimestamps` masih memakainya — periksa dulu saat itu.
- Pre-check status di controller ini harus ikut pindah ke Service baru, bukan hilang.

## 8. Test

| File | Yang dibuktikan |
|---|---|
| `CustomerTerminationRejectsInvalidStatusTest` | Terminasi ditolak dari `waiting_survey` / `rejected` / `terminated` (status, `terminated_at`, audit, status log tidak berubah). Dari `suspended`: `from_status` dan `old_values` = `suspended`. Audit `terminate` tetap terbaca. Enum: `TERMINATED` hanya ke `ACTIVE`. |
| `CustomerReactivateWritesStatusLogAndRestoresPortalTest` | Status log `terminated → active` + note; `terminated_at` utuh; ditolak kalau bukan `terminated`; akun `disabled` → `pending_claim`, password lama tidak lagi cocok, token QR baru + PIN, audit portal; pelanggan legacy tanpa akun dibuatkan akun `pending_claim`. |
| `PortalDisabledAccountCannotLoginTest` | Akun `disabled` tidak bisa login dengan password lama; responsnya identik dengan password salah. |

Diverifikasi dengan uji mutasi: menonaktifkan guard `login()` dan blok observer membuat 4 dari 6 test di dua file terakhir gagal.

Test lama yang tetap hijau: `CustomerTerminationTest`, `PortalCustomerObserverDisablesPortalOnTerminatedTest`.

Regresi `--filter='Customer|Portal|Qr|Workflow|Dashboard|Terminat|Suspend|Verification'`: 679 lulus, 5 gagal — semuanya test isi Blade (`PostTargetRenderedServerSideTest`, `QrStaffPageSmokeTest`, `CustomerHubModalFooterResponsiveTest` ×2, `PaymentReceiptPrintTest`) yang tidak bersinggungan dengan file yang diubah. Belum dibuktikan gagal juga di kondisi sebelum perubahan (working tree berisi banyak perubahan lain yang belum di-commit).

## 9. Belum Dikerjakan / Tindak Lanjut

- `toggleSuspend()` juga menulis status langsung (dengan `CustomerStatusLog` manual dan guard sendiri) — di luar scope, tidak diubah.
- Halaman QR staf belum menampilkan petunjuk "cetak kartu baru" setelah Langganan Lagi; saat ini staf harus tahu sendiri.
- Data terminated lama: tidak ada backfill `customer_status_logs` untuk terminasi masa lalu.
- Tidak ada kode yang mematikan PPPoE/router saat terminate atau isolir (sudah dilaporkan di sesi analisis; belum ditelusuri di luar `app/`).

## 10. Rollback

Murni perubahan kode, tanpa migrasi. Revert file di §5. Data yang tercipta selama berlaku (baris `customer_status_logs`, audit `account_restored_after_reactivation`, akun portal `pending_claim`) aman dibiarkan.

## 11. Temuan Lanjutan (2026-09-26, belum diperbaiki): `InvoiceType::REAKTIVASI` tidak sinkron dengan definisinya sendiri

Ditemukan saat diskusi terpisah soal makna "reaktivasi" secara umum di industri ISP (isolir/suspend beda konsep dari terminate/putus — lihat §12 untuk definisi acuan).

**Definisi yang sudah dikonfirmasi user (2026-09-19), tertulis di `analisa-rancangan-tagihan-manual.md` §3.2:**

> `Reaktivasi (invoice_type reaktivasi, sudah ada — pelanggan **putus** lalu berlangganan lagi)`

Yaitu `REAKTIVASI` seharusnya berpasangan dengan status **`terminated → active`** ("Langganan Lagi", alur dokumen ini).

**Implementasi aktual** (`app/Services/ManualInvoiceService.php:305-308`, `resolveTypeFromLines()`):

```php
return $customer->status === 'suspended'
    ? InvoiceType::REAKTIVASI
    : InvoiceType::BULANAN;
```

Mengecek status **`suspended`** (isolir), bukan `terminated`. Ini kebalikan dari definisi §3.2.

**Kenapa ini bug, bukan cuma beda istilah:**

1. `reactivate()` (`CustomerController.php:208-241`, alur dokumen ini) mengubah status pelanggan `terminated → active` **langsung dalam satu transaksi** (via `transition()`). Status `suspended` **tidak pernah tersentuh** di jalur ini. Akibatnya kasus asli yang dimaksud §3.2 ("pelanggan putus lalu berlangganan lagi") **tidak mungkin** menghasilkan `InvoiceType::REAKTIVASI` — begitu admin buka `/invoices/create` setelah "Langganan Lagi", status pelanggan sudah `active`, jadi kena cabang `BULANAN`.
2. Yang justru kena cabang `REAKTIVASI` adalah pelanggan **isolir** (`suspended`, nunggak tagihan) yang masih berstatus itu saat dibuatkan Tagihan Manual — kasus yang secara bisnis ISP umum **tidak butuh invoice jenis baru** (layanan isolir otomatis nyala lagi begitu tunggakan lama lunas, bukan "berlangganan lagi").
3. `Invoice::SUBSCRIPTION_TYPES` sengaja mengecualikan `REAKTIVASI` supaya pelanggan yang suspend→aktif di bulan sama boleh punya 2 record tagihan periode — desain ini konsisten dengan kondisi `suspended` di kode, TAPI jadi tidak relevan untuk kasus `terminated→active` yang sebenarnya dituju §3.2 (pelanggan putus biasanya sudah lewat >1 periode, bukan cuma dalam bulan yang sama).

**Asal-usul nilai enum:** `REAKTIVASI` sudah ada sejak commit awal modul (`9548cf1`), kemungkinan ikut ter-*carry* dari pemetaan data legacy (`docs/billing-pembayaran/perbandingan-tagihan-awal-vs-bulanan-legacy.md` — legacy punya baris histori bertanda "reaktivasi" per pelanggan, mis. `IN001619`, `MigrateLegacyDataCommand.php:1108`), bukan dari rancangan fitur baru manapun.

**Pengecekan data (2026-09-26, DB dev/lokal `whusnet_operasional`, BUKAN data produksi):**

```
Invoice::where('invoice_type', 'reaktivasi')->count()  →  0
```

Tidak ada satu pun baris `invoice_type = reaktivasi` di database ini — baik dari migrasi legacy maupun terbentuk manual lewat kode baru. DB ini kemungkinan belum diisi data migrasi/produksi asli; hitungan ini **belum membuktikan** kondisi di DB produksi, cuma membuktikan bahwa di lingkungan dev saat ini fitur belum pernah dipakai/diuji nyata — aman diubah tanpa migrasi data.

**Opsi perbaikan (belum diputuskan, tunggu konfirmasi user):**

| # | Opsi | Konsekuensi |
|---|---|---|
| A | Ganti kondisi `resolveTypeFromLines()` jadi cek `terminated` (dibaca **sebelum** `reactivate()` mengubahnya jadi `active`) | `REAKTIVASI` jadi sesuai definisi §3.2. Perlu cari titik baca status yang tepat — begitu `reactivate()` selesai, status sudah `active`, jadi kalau invoice reaktivasi mau dibuat otomatis saat "Langganan Lagi" (bukan manual belakangan), harus dipicu dari dalam `reactivate()`/`transition()` itu sendiri, bukan `ManualInvoiceService`. |
| B | Hapus `InvoiceType::REAKTIVASI` sepenuhnya | Kasus isolir→aktif: tidak perlu invoice baru (tunggakan lama cukup dilunasi). Kasus putus→langganan lagi: kalau modem sudah diambil → masuk Antrean Survey → instalasi baru → sudah tercover `InvoiceType::AWAL`; kalau modem belum diambil → langsung ke List Pelanggan → cukup lanjut tagihan `BULANAN` biasa, tidak ada tagihan "reaktivasi" tersendiri secara bisnis. Lebih sederhana, sejalan §3.2 kalau ditinjau ulang: definisi itu sendiri mungkin premis yang keliru (mengasumsikan proses ISP butuh jenis tagihan baru padahal cukup pakai AWAL/BULANAN yang sudah ada). |

Opsi B lebih sejalan dengan cara kerja ISP secara umum (tidak ada "invoice reaktivasi" berdiri sendiri di luar aplikasi ini) dan menghilangkan cabang kode yang sampai sekarang tidak pernah tercapai sesuai maksud aslinya. Opsi A mempertahankan taksonomi §3.2 tapi butuh pekerjaan tambahan memindah titik pemicu.

**Dampak kalau dihapus (opsi B) — perlu disentuh:**
- `app/Enums/InvoiceType.php` — hapus case.
- `app/Services/ManualInvoiceService.php:305-308` — sederhanakan jadi selalu `BULANAN`.
- `app/Models/Invoice.php` (docblock `SUBSCRIPTION_TYPES`) — hapus catatan pengecualian REAKTIVASI.
- `app/Console/Commands/GenerateMonthlyInvoicesCommand.php`, `AuditDuplicateInvoicesCommand.php` — hapus pengecualian REAKTIVASI dari query (jadi tidak relevan lagi, bukan berarti guard-nya salah).
- `app/Services/CustomerVerificationDetailService.php:94`, `CustomerBalanceService.php` docblock — hapus referensi.
- `docs/plan/billing/analisa-rancangan-tagihan-manual.md` §3.2 — revisi taksonomi.
- Test terkait: `SatuTagihanLanggananPerPeriodeTest.php`, `AuditTagihanDobelTest.php` — sesuaikan skenario REAKTIVASI.
- **Tidak ada migrasi data diperlukan** (dev DB kosong untuk tipe ini) — tapi cek ulang di DB produksi sebelum eksekusi, karena kemungkinan ada baris hasil migrasi legacy yang belum masuk dev DB ini.

## 12. Definisi Acuan Umum (di luar konteks kode project ini)

Untuk industri ISP secara umum (bukan istilah khusus project ini):

- **Isolir/Suspend** — pemblokiran fungsi layanan karena masalah administratif (paling sering telat bayar). Efek: internet/panggilan mati, perangkat tetap terhubung ke jaringan. Solusi: otomatis aktif lagi begitu tunggakan lunas — **tidak butuh invoice baru**, cukup pelunasan invoice yang sudah ada.
- **Terminate/Putus** — pengakhiran resmi layanan (permintaan pelanggan atau kebijakan ISP). Berlangganan kembali setelah putus **juga tidak otomatis punya invoice jenis baru** — yang ada cuma tagihan tunggakan (kalau ada) ditambah proses ulang seperti pelanggan baru (survey/instalasi) kalau modemnya sudah ditarik.

Istilah "Reaktivasi" dalam artian umum lebih dekat ke **suspend → aktif lagi**; sedangkan "putus lalu berlangganan lagi" secara umum lebih tepat disebut **"berlangganan ulang" / "re-subscribe"**, bukan reaktivasi — beda dari definisi §3.2 dokumen `analisa-rancangan-tagihan-manual.md` yang memakai "Reaktivasi" untuk kasus putus. Ketidaksamaan istilah ini salah satu akar kebingungan yang melahirkan mismatch di §11.

## 13. Implementasi: Langganan Lagi Bercabang Berdasarkan Status Alat (ADHOC-102, 2026-09-26)

**Status:** Selesai diimplementasi. Melengkapi §11/§12 — sekaligus mengoreksi keputusan §2 poin 3 (`TERMINATED => [ACTIVE]`, sekarang `[ACTIVE, WAITING_SURVEY]`) berdasarkan konfirmasi user:

> Pelanggan Putus yang modemnya **belum** diambil ketika berlangganan kembali akan langsung bisa masuk List Pelanggan.
> Pelanggan Putus yang modemnya **sudah** diambil ketika berlangganan kembali maka akan masuk ke Antrean Survey.

**Konteks penting:** ini soal *status/label pelanggan* ("Langganan Lagi" pasca terminate/putus), **bukan** `InvoiceType::REAKTIVASI` (§11) — dua hal berbeda yang kebetulan sama-sama dibahas dalam sesi yang sama. `InvoiceType::REAKTIVASI` tetap dihapus terpisah (lihat commit/diff `InvoiceType.php`), tidak ada keterkaitan implementasi.

**Kenapa dulu selalu `active` langsung (§2 poin 3 lama, "tidak boleh lompat ke survey"):** keputusan awal ADHOC-85 mengasumsikan infrastruktur pelanggan masih terpasang penuh saat Langganan Lagi. Asumsi itu tidak berlaku begitu modemnya sudah ditarik (`device_retrieved_at` terisi, lewat alur Ambil Alat/DEAC ADHOC-86) — tidak ada modem untuk langsung dipakai lagi, jadi harus diproses ulang seperti pemasangan baru.

**Perubahan:**

| File | Perubahan |
|---|---|
| `app/Enums/WorkflowTransition.php` | `TERMINATED => [ACTIVE, WAITING_SURVEY]` (sebelumnya `[ACTIVE]` saja). Komentar diperbarui menjelaskan pemicu cabang. |
| `app/Http/Controllers/CustomerController.php` (`reactivate()`) | Baca `customer->customerDevice?->device_retrieved_at` SEBELUM transaksi (nilainya di-null-kan di dalam transaksi). `false` → `transition(ACTIVE, ...)` seperti sebelumnya + `service_status = 'aktif'`. `true` → `transition(WAITING_SURVEY, ...)`, TANPA set `service_status` (masih menunggu instalasi ulang). `device_retrieved_at` di-null-kan di KEDUA cabang (ADHOC-88 tetap berlaku). |
| `app/Observers/CustomerObserver.php` | Kondisi pemulihan portal (`restoreAfterReactivation`) diperluas dari `status === ACTIVE` jadi `status IN (ACTIVE, WAITING_SURVEY)` — tetap dipicu dari `getOriginal('status') === TERMINATED`. Portal harus pulih begitu pelanggan MULAI Langganan Lagi, bukan menunggu instalasi ulang kelar. |
| `resources/views/customers/terminated.blade.php` | Pesan konfirmasi tombol "Langganan Lagi" (2 lokasi — tabel & card) dibuat kondisional: menyebut "masuk Antrean Survey" kalau `$isDeviceRetrieved`, supaya admin tidak kaget hasil akhirnya bukan `active`. |
| `docs/customer-lifecycle/business-logic.md` §1, §8 | Tabel transisi + narasi "Langganan Lagi" diperbarui menjelaskan dua cabang. |
| `tests/Feature/CustomerReactivateWritesStatusLogAndRestoresPortalTest.php` | +2 test: `langganan_lagi_masuk_antrean_survey_kalau_alat_sudah_diambil` (status jadi `waiting_survey`, Task SURVEY otomatis terbentuk, flag ter-null-kan, portal ikut pulih) dan `langganan_lagi_tetap_langsung_active_kalau_alat_belum_diambil` (regresi perilaku lama). |
| `tests/Feature/CustomerTerminationRejectsInvalidStatusTest.php` | Test `terminated_hanya_boleh_kembali_ke_active` diperbarui jadi `terminated_hanya_boleh_kembali_ke_active_atau_waiting_survey`, assert dua transisi yang diizinkan. |

**Apa yang TIDAK diubah (sengaja, di luar scope §13):**
- Antrean Survey yang dituju sama persis dengan Task SURVEY biasa (dibuat otomatis oleh `CustomerWorkflowService::transition()` sendiri, mekanisme sudah ada sejak sebelumnya) — **tidak ada Task/tabel baru**.
- Notifikasi aktivasi (`SendCustomerActivationNotification`) tetap hanya terpicu saat status BENAR-BENAR `active` — cabang `waiting_survey` baru mengirimnya nanti setelah instalasi ulang selesai dan pelanggan sampai `active` lagi lewat alur normal (survey → ACC → instalasi → verifikasi).
- `CustomerAcquisition::firstOrCreate` (modul akuisisi Busdev) tetap hanya tercatat saat status jadi `active` — pelanggan yang masih `waiting_survey` belum dianggap "aktif lagi" oleh modul itu.
- Tidak ada migrasi data — perubahan murni enum + logic, tidak menyentuh baris `customers`/`customer_devices` yang sudah ada.

**Rollback:** murni perubahan kode (Enum + Controller + Observer + view + test), tanpa migrasi. Revert file di tabel atas untuk balik ke perilaku lama (selalu `active` langsung, tanpa cabang survey).
