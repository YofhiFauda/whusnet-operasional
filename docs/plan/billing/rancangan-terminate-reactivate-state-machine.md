# Rancangan: Terminasi & "Langganan Lagi" Lewat State Machine (ADHOC-85)

**Status:** Selesai diimplementasi 2026-09-19. Di luar sprint aktif (Sprint 8.10), dikerjakan atas persetujuan eksplisit user.

**Terkait:**
- [`../../customer-lifecycle/business-logic.md`](../../customer-lifecycle/business-logic.md) §1 (tabel transisi) dan §8 (Terminasi Layanan)
- [`../../api/api-portal-pelanggan/business-logic.md`](../../api/api-portal-pelanggan/business-logic.md) §Token — akun portal & QR
- [`analisa-rancangan-putus-langganan.md`](analisa-rancangan-putus-langganan.md) — ADHOC-69, **belum diimplementasi**, akan mengganti isi `CustomerTerminationController`. Lihat §7 dokumen ini.

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
