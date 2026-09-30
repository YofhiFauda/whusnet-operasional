# Rancangan: Edit Pembayaran Penuh (setara form Bayar)

> Status: **Diimplementasikan** (2026-09-29). Di luar sprint aktif, permintaan eksplisit user.
> Task: ADHOC-108 (`docs/TASKS.md`).
> K1–K7 dijawab user (bagian 2 & 9), lalu diimplementasikan sesuai urutan bagian 5. Penyimpangan dari rancangan yang ditemukan saat coding dicatat di §10 di bawah.

---

## 1. Masalah

Edit Pembayaran hanya bisa mengubah tanggal, metode, rekening, pengirim, kolektor, catatan, dan bukti.
**Nominal** dan **saldo pelanggan yang dipakai** terkunci (nominal read-only di modal), padahal form Bayar (`payments/create`) bisa mengisi keduanya.
Salah ketik nominal hari ini hanya bisa dikoreksi lewat Kembalikan + catat ulang.

Permintaan: edit harus bisa mengubah **semua yang bisa diisi saat bayar**.

## 2. Keputusan user (final)

| # | Keputusan | Konsekuensi desain |
|---|---|---|
| K1 | **Tidak perlu tabel audit baru — sudah ada catatan.** | Terverifikasi (F1): `Payment::booted()->updated` sudah menulis `AuditLog` (old/new per kolom). Tidak ada tabel `payment_revisions`. |
| K2 | **Alasan koreksi opsional.** | Field `reason` nullable. Bila diisi, ditulis sebagai entri `AuditLog` tambahan (F1). |
| K3 | **Ketat: hanya payment bulan berjalan yang boleh diedit.** | Sama dengan `BookPeriod` (F4). `payment_date` lama **dan** baru wajib di bulan berjalan. Payment bulan lalu → hanya jalur Kembalikan. |
| K4 | **Payment dalam setoran boleh diubah; setoran dihitung ulang otomatis.** | `computedAmount()` sudah turunan (F3) → "hitung ulang" gratis selama setoran `MENUNGGU_VERIFIKASI`. Yang perlu dikerjakan: lock + guard, bukan hitung ulang. |
| K5 | **Payment metode Saldo tidak boleh diedit.** | Guard di service + tombol Edit disembunyikan + opsi "Saldo" dibuang dari dropdown edit. Koreksi lewat Kembalikan. |
| K6 | **Jangan beri `payments.update` ke role lain untuk sementara; RBAC disiapkan agar bisa disesuaikan nanti.** | Seeder tidak diubah, tanpa migrasi data. Route dipindah ke grup `payments.update`, label matrix diganti, test penjaga default (F6, 4.6). `pop_admin` kehilangan edit saat deploy — pulih lewat Role Matrix. |
| K7 | **Ya** — kolom `revision` di ledger saldo. | Migrasi unique index `(payment_id, type, source, revision)` + `KOREKSI` (F2, bagian 9). |

## 3. Temuan analisa

### F1 — Audit sudah otomatis (K1)
`Payment::booted()` (`app/Models/Payment.php:76`): tiap `$payment->update()` yang mengubah kolom menulis `AuditLog`
(`module=Pembayaran`, `action=update`, `old_values`/`new_values` hanya kolom yang berubah, user, IP, user-agent).
`revise()` yang memakai `$payment->update()` otomatis tercatat: nominal, saldo dipakai, overpay, metode, tanggal, rekening, dst.

Celah kecil: **alasan** bukan kolom `payments`, jadi tidak ikut diff. Solusi: bila `reason` terisi, tulis satu `AuditLog::create([... 'action' => 'koreksi', 'new_values' => ['alasan' => …]])`
pada `auditable` payment yang sama. Karena `writeAuditLog()` privat, tambah method publik kecil di `Payment` (mis. `logCorrectionReason(string $reason)`), jangan duplikasi struktur `AuditLog::create` di service.
Perubahan saldo tercatat terpisah di ledger `customer_balance_mutations` (F2).

### F2 — Ledger saldo TIDAK bisa dipakai ulang mentah-mentah (temuan paling rawan)
`CustomerBalanceService::reverseCreditForPayment()` / `reverseDebitForPayment()` dibuat untuk **Kembalikan**, bukan untuk koreksi:
- Sifatnya *sekali jalan*: begitu ada baris pembalik (`note like 'Pembalikan …%'`), panggilan berikutnya `return` diam-diam. Edit kedua pada payment yang sama tidak membalik apa pun.
- Membaca satu baris via `->first()` dan membalik **nominal ledger-nya**, bukan `payments.overpay_amount` yang baru.
- Catatan barisnya berbunyi "payment … ditolak" — salah untuk koreksi.
- Unique index `(payment_id, type, source)` (`customer_balance_mutations_payment_type_source_unique`) → `credit()` sumber `BAYAR_DI_MUKA` kedua kali untuk payment yang sama melempar `UniqueConstraintViolationException`.

**Kesimpulan:** `revise()` memakai pendekatan **selisih (delta) satu baris per revisi**, bukan balik-lalu-terapkan-ulang:

```
Δoverpay = overpay_baru − overpay_lama
Δpakai   = saldo_dipakai_baru − saldo_dipakai_lama
net      = Δoverpay − Δpakai        // efek bersih ke saldo pelanggan
net > 0  → 1 baris CREDIT  source=KOREKSI  amount=net
net < 0  → 1 baris DEBIT   source=KOREKSI  amount=|net|
net = 0  → tidak ada baris
```

Kebenaran nilai lama diambil dari kolom `payments.overpay_amount` / `balance_used_amount` (bukan dari ledger), sehingga tetap benar untuk data lama/backfill.

Perubahan skema yang dibutuhkan (lihat K7):
- `BalanceMutationSource::KOREKSI = 'koreksi'` (+ `label()`).
- Kolom `customer_balance_mutations.revision` (`unsignedSmallInteger`, default 0) dan unique index diganti ke `(payment_id, type, source, revision)`. Revisi ke-n memakai `revision = n` (n = jumlah baris `KOREKSI` payment tsb + 1). Tanpa ini, koreksi kedua arah yang sama bentrok dengan unique index.
- Baris `KOREKSI` **bukan** pemakaian saldo/pembalikan yang sudah dihitung `physicalAmount()`; cek `Payment::physicalAmount()` memakai kolom payment (bukan ledger) sehingga otomatis benar setelah kolom diperbarui — verifikasi lewat test kas.

Cek kecukupan saldo (mirror form Bayar): `saldo_dipakai_baru ≤ balance(customer) + saldo_dipakai_lama`.
Pembalikan overpay yang sudah keburu terpakai pelanggan **boleh** membuat saldo negatif (piutang terlihat) — sama dengan filosofi `reverseCreditForPayment()`; jangan lewat `debit()` yang menolak saldo kurang.

### F3 — Setoran dihitung ulang otomatis (K4)
- `CollectorDeposit::computedAmount()` = Σ `physicalAmount()` payment VALID tertaut — **dihitung setiap dipanggil, tidak disimpan**.
- `declared_amount` / `difference` hanya ditulis saat `verify()`.
- Jadi selama status `MENUNGGU_VERIFIKASI`, mengubah nominal payment otomatis mengubah total setoran. Tidak ada kode "hitung ulang" yang perlu ditulis.
- `CashDeposit::computedAmount()` (setoran kas admin) berperilaku sama.
- `DepositStatus::isVerified()` = semua status selain `MENUNGGU_VERIFIKASI` (termasuk `SELISIH`, `LEBIH_SETOR`, `SELISIH_LUNAS`, `DIHAPUS_BUKU`). `CashDepositStatus::isVerified()` = selain `MENUNGGU_VERIFIKASI`/`SALDO_AWAL`.

Yang **wajib** dikerjakan:
1. **Lock setoran sebelum membaca statusnya.** `verify()` melakukan `CollectorDeposit … lockForUpdate()` lalu `computedAmount()`. `revise()` harus lock baris setoran (collector maupun cash) **lebih dulu**, baru cek status. Urutan lock global: **setoran → invoice → payment**. Tanpa ini, edit dan verifikasi yang bersamaan bisa menghasilkan `declared − computed` dari angka campur.
2. **Baca ulang `collector_deposit_id` / `cash_deposit_id` setelah lock payment** — payment bisa baru saja dimasukkan ke setoran oleh `submit()` (yang mengunci baris payment).
3. **Setoran terverifikasi → blokir semua perubahan** (bukan hanya nominal). Selaras `reject()`.
4. **Payment yang tertaut setoran apa pun (pending sekalipun): `payment_method` dan `collected_by` dibekukan.** Alasan: setoran kolektor memuat payment dengan `collected_by` = kolektor tsb; mengubah metode kolektor→cash atau mengganti kolektor membuat payment tersangkut di setoran orang yang salah, sementara `CollectorBalanceService`/`AdminCashBalanceService` (turunan dari `collected_by`, `payment_method='cash'`, `collector_deposit_id`, `cash_deposit_id`) tidak lagi konsisten. Field lain (nominal, saldo, tanggal, catatan, bukti, pengirim) tetap boleh. Payment yang **belum** tertaut setoran boleh ganti metode/kolektor bebas — kas admin & saldo kolektor turunan, ikut menyesuaikan sendiri.
5. Nominal/kolektor berubah → `CollectorActivityUpdated::dispatch` (pola `reject()`), supaya Worklist yang terbuka tidak memajang saldo lama. Cek apakah `CollectorDepositUpdated` (dipakai `verify()`) perlu dipicu untuk setoran pending yang totalnya berubah; jangan asumsi nilai `$action`-nya — baca event-nya dulu.
6. Notifikasi `submit()` ke verifikator memuat total saat submit; total itu jadi basi setelah koreksi. Dampak: pesan lama saja (verifikator melihat angka baru di layar verifikasi). Dicatat sebagai keterbatasan, bukan blocker.

### F4 — "Bulan berjalan" = `BookPeriod` (K3)
`BookPeriod::isLocked($ym)` = `$ym < now()->format('Y-m')`, terkunci permanen tanpa buka ulang; laporan kas & Blok 4 laporan bulanan memakai `payment_date`.
Aturan edit:
- Tolak bila `BookPeriod::isLocked(payment_date_lama->format('Y-m'))` → "Pembayaran periode … sudah tutup buku. Gunakan Kembalikan lalu catat ulang di periode berjalan."
- Tanggal baru: `>= BookPeriod::firstOpenDate()` dan `<= today` (validasi server + `min`/`max` di input date).
- Catatan: pada tanggal 1, seluruh payment bulan sebelumnya otomatis tak bisa diedit — konsisten dengan tutup buku otomatis (ADHOC-96).
- Kembalikan (reject) tetap boleh untuk bulan terkunci (aturan ADHOC-96) — tidak diubah.

### F5 — Payment metode Saldo (K5)
`PaymentMethod::SALDO` dibuat sistem oleh `CustomerBalanceService::applyToOpenInvoices()` dengan `idempotency_key = auto-saldo:{invoice_id}:{urutan}`.
Guard: tolak bila `payment_method === SALDO` **atau** `idempotency_key` berawalan `auto-saldo:` (jaring pengaman bila metodenya pernah berbeda).
Dropdown metode di halaman edit **tidak** memuat "Saldo" (metode itu hanya milik sistem; "pakai saldo" manual = field `use_balance_amount`, bukan metode). Tombol Edit di `payments/index` & `payments/show` disembunyikan untuk payment Saldo.

### F6 — RBAC: `payments.update` SUDAH ada; K6 = tidak diberikan ke role lain, tapi bisa diatur dari Role Matrix
Fakta:
- Action `update` sudah ada di `config/rbac.php` (`'payments'`) → permission `payments.update` sudah digenerate `PermissionGeneratorService` dan **sudah tampil di Role Matrix** sebagai checkbox. Tidak ada permission/feature/action baru.
- `owner` (`*`) dan `admin` (`payments.*`, `RolePermissionSeeder.php:191`) sudah memilikinya.
- `pop_admin` (`RolePermissionSeeder.php:501-505`) punya `create/validate/reject/print` tapi **tidak** `update`. Route `payments.update` sekarang ada di grup `payments.create`, jadi **pop_admin bisa edit hari ini lewat celah itu**. Role lain ber-`payments.create` (seeder ~`:337`) juga.
- Label `roles/matrix.blade.php:308` masih "[Belum aktif] Belum ada route ubah pembayaran" — salah sejak route itu ada dan akan makin salah; admin yang membaca label itu mengira mencentang tidak berdampak.
- `RoleManagementService::syncPermissions()` (dipakai matrix): memakai `sync()` dalam transaksi + `lockForUpdate` role, menulis `AuditLog` ("Role Management"), memanggil `EffectiveAccessService::clearCache` untuk semua user role itu, dan **otomatis menambahkan `payments.view`** bila `payments.update` dicentang. Jadi penyesuaian lewat matrix sudah aman tanpa kode tambahan.

Keputusan K6 (user): **jangan beri `payments.update` ke role lain dulu; siapkan RBAC agar bisa disesuaikan nanti.** Artinya:
1. **Tidak ada perubahan seeder dan tidak ada migrasi data** — `RolePermissionSeeder` tidak diubah. Hanya `owner` dan `admin` yang punya izin edit.
2. Route `payments.edit` dan `payments.update` dipindah ke `permission:payments.update` (grup baru, terpisah dari `payments.create`) supaya izin ini **benar-benar menggerbangi** fitur, dan bisa diberikan per role dari Role Matrix kapan saja tanpa deploy.
3. **Dampak yang harus disadari & dicatat:** `pop_admin` (dan role ber-`payments.create` lain) **kehilangan kemampuan edit** begitu deploy, karena selama ini lolos lewat grup `payments.create`. Bukan bug — konsekuensi K6. Bila operasional butuh, admin tinggal mencentang `payments.update` untuk `pop_admin` di Role Matrix (scope POP tetap membatasi datanya; `Payment::applyUserScope()` tidak berubah).
4. Label matrix `payments.update` diganti, dan harus **menjelaskan sensitivitasnya** karena kini izin ini mengubah uang (nominal, saldo), bukan cuma metode:
   > `'payments.update' => 'Ubah pembayaran bulan berjalan: tanggal, metode, nominal, saldo pelanggan yang dipakai, bukti, catatan — setoran/tagihan ikut terhitung ulang. Tidak berlaku untuk pembayaran bulan lalu, pembayaran Saldo, atau yang sudah masuk setoran terverifikasi. Berikan hanya ke peran yang memegang kas.'`
   Label deskripsi `payments.reject` tidak berubah (koreksi bulan lalu tetap lewat Kembalikan).
5. Larangan keras CLAUDE.md tetap berlaku bila matrix dipakai kelak: **teknisi tidak boleh catat pembayaran**, **sales tidak boleh akses keuangan**, dan `kolektor` sengaja tanpa `payments.create` (dikunci `CollectorRoleCannotCreatePaymentsTest`). Rancangan menambah test penjaga default (lihat bagian 6, #28–#31) supaya izin ini tidak bocor ke role lain lewat perubahan seeder di masa depan.
6. Tidak dibuat mekanisme "permission terkunci per role" baru (over-engineering); Role Matrix yang ada sudah cukup.

### F7 — Turunan kas otomatis menyesuaikan
`AdminCashBalanceService::unsettledManualPaymentsQuery()` = payment `cash`, `collected_by` null, belum tertaut setoran, VALID. `CollectorBalanceService` serupa. Keduanya query turunan → mengubah nominal/metode payment yang **belum** tertaut setoran otomatis menggeser saldo tunai admin/kolektor; tidak ada penyesuaian manual. (Aturan #4 F3 mencegah inkonsistensi bagi yang sudah tertaut.)

### F8 — Rumus sisa tagihan tanpa payment ini
`Invoice::recalculateFromPayments()` menjumlah `amount` payment VALID (`amount` = bagian yang menutup tagihan, bukan tunai mentah).
`remaining_tanpa_payment_ini = total_amount − Σ amount payment VALID lain`. Pakai ini untuk auto-split; setelah update panggil `recalculateFromPayments()` (satu-satunya sumber `paid/remaining/status`).
`total_amount` invoice tidak disentuh. Invoice `BATAL`/`TAK_TERTAGIH` ditolak.

### F9 — Test lama yang terdampak
`tests/Feature/PaymentEditUpdateTest.php::test_authorized_user_can_update_payment` memakai tanggal hard-code `2026-09-25` (akan gagal K3 mulai Oktober) dan payload lama. Perbaiki: pakai `now()`, sesuaikan payload/harapan; **jangan dihapus** (aturan repo). Test render (`payment_index_renders_*`) tetap.

## 4. Rancangan

### 4.1 Route (static dulu, dynamic belakangan)
```php
// grup permission:payments.update  (BARU — lepas dari payments.create)
Route::get('/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');   // SEBELUM /payments/{payment} (web.php:344)
Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
```
`payments.edit` didaftarkan sebelum `payments.show`, sejajar `payments.receipt` (`:343`).

### 4.2 Halaman edit (bukan modal)
Aturan CLAUDE.md "mutasi data → halaman tersendiri": `back()->withErrors()->withInput()` pada modal di List menutup modal dan menghilangkan error.
- Ekstrak field `payments/create.blade.php` (499 baris) ke `payments/partials/_form.blade.php`; `create` dan `edit` `@include` bersama (toggle metode, checklist saldo, modal konfirmasi overpay identik).
- Field: tanggal (`min` = `firstOpenDate`, `max` = hari ini), metode (tanpa Saldo), rekening, pengirim, kolektor (beku bila tertaut setoran), nominal tunai, pakai saldo, bukti, catatan, **alasan koreksi (opsional)**.
- Kartu read-only: No. Transaksi, No. Tagihan, pelanggan, **sisa tagihan tanpa payment ini**, saldo pelanggan tersedia, status setoran tertaut (bila ada) + peringatan bila metode/kolektor beku.
- Nilai awal: `amount` tunai = `amount + overpay_amount − balance_used_amount`; `use_balance_amount` = `balance_used_amount`.
- `action` form dirender `route('payments.update', $payment)` (server-side).
- `payments/index.blade.php`: tombol Edit → link `route('payments.edit', …)` (hanya bila `Payment::isEditable()` benar); hapus modal + `paymentManager()`; buang `$collectors` dari `index()` **hanya jika** tak dipakai bagian lain (cek dulu, `PaymentController.php:116-126`).
- Redirect PRG sukses → `payments.show`.

### 4.3 `Payment::editBlockedReason(): ?string` (satu sumber "boleh diedit?")
Dipakai bersama tombol (view), `edit()`, dan `revise()` — jangan duplikasi daftar syarat di tiga tempat (pola `TaskStatus::acceptsReport()`):
1. `payment_status === DITOLAK`
2. `payment_method === SALDO` atau key `auto-saldo:` (K5)
3. `BookPeriod::isLocked(payment_date)` (K3)
4. setoran tertaut terverifikasi (collector/cash) (F3.3)
5. invoice `BATAL`/`TAK_TERTAGIH` (dicek di service dengan lock)
Mengembalikan pesan Indonesia atau `null`.

### 4.4 `PaymentService::revise(Payment $payment, array $validated, ?string $proofPath): Payment`
Satu `DB::transaction`, urutan lock: **setoran → invoice → payment**.
1. Lock setoran tertaut (collector/cash) bila ada; lock invoice; lock + muat ulang payment.
2. Guard ulang di bawah lock: `editBlockedReason()`; `payment_date` baru ≥ `firstOpenDate` & ≤ hari ini; bila tertaut setoran, `payment_method`/`collected_by` tidak berubah.
3. Rekening: `resolveActiveBankAccount()` **hanya bila** rekening berubah; rekening lama yang kini nonaktif boleh dipertahankan (snapshot).
4. Hitung ulang split dengan helper bersama `splitAmount($tunai, $saldo, $remainingTanpaPaymentIni)` — **ekstrak dari `record()`** (`PaymentService.php:105-107`) agar satu rumus. Tolak `applied ≤ 0`.
5. Cek saldo: `saldo_baru ≤ balance + saldo_lama`.
6. `$payment->update([...])` (kolom sama dengan `record()`; audit otomatis F1).
7. Ledger: satu baris delta `KOREKSI` (F2).
8. `$lockedInvoice->recalculateFromPayments()`.
9. Bila `reason` terisi → catat entri audit `koreksi` (F1).
10. Kembalikan payment segar.
Method `PaymentObserver::creating()` (nominal ≤ 0) tidak jalan pada update — guard `applied > 0` di langkah 4 wajib.

### 4.5 Controller
- `edit(Payment)`: scope `applyUserScope()` (403), `editBlockedReason()` → redirect `payments.show` dengan error, siapkan data view.
- `update()`: scope → validasi → `revise()` → redirect. Hapus logika inline.
- Validasi: ekstrak aturan `store()` ke method privat `paymentRules()` (dipakai bersama), `RupiahInput::parse` untuk nominal, tambah `reason` nullable `ReasonValidationRule`/`string|max:1000` (opsional), batas tanggal K3, metode ≠ saldo.
- Tangkap `ValidationException` dari service (tetap di halaman edit). `UniqueConstraintViolationException` tidak diharapkan (ledger diberi `revision`); jangan ditelan diam-diam.
- Dispatch `CollectorActivityUpdated` bila nominal/kolektor berubah dan `collected_by` terisi.

### 4.6 RBAC (K6)
- Route `payments.edit`/`payments.update` → grup baru `permission:payments.update` di `routes/web.php` (dekat grup `payments.reject`, `:364`), **bukan** `payments.create`.
- `RolePermissionSeeder` **tidak diubah**; tidak ada migrasi data; tidak ada permission baru. Yang berubah hanya: (a) grup route, (b) label `roles/matrix.blade.php:308` (teks di F6 no. 4), (c) test penjaga.
- Tombol Edit di `payments/index` & `payments/show` dibungkus `@can`/pengecekan izin yang sama (`payments.update` lewat `EffectiveAccessService::userCan`, pola tombol lain di halaman itu) **selain** `Payment::editBlockedReason()`. Tanpa izin → tombol tidak dirender.
- Catat di `docs/rbac/` (atau README billing-pembayaran) satu paragraf: "`payments.update` hanya owner/admin secara default; role lain diatur dari Role Matrix."

## 5. Urutan implementasi
1. Migrasi: `customer_balance_mutations.revision` + ganti unique index; enum `KOREKSI` (+label).
2. `Payment`: `editBlockedReason()`, `isEditable()`, method catat alasan koreksi.
3. `PaymentService`: ekstrak `splitAmount()`; tambah `revise()`; ledger delta `KOREKSI` (di `CustomerBalanceService`, mis. `adjustForRevision(Payment, oldOverpay, oldUsed)`).
4. `PaymentController`: `paymentRules()`, `edit()`, tipiskan `update()`.
5. Route + RBAC (K6) + label matrix.
6. View: partial `_form`, `payments/edit.blade.php`, ganti tombol, hapus modal.
7. Test (bagian 6); perbaiki `PaymentEditUpdateTest`.
8. Dokumen: `docs/billing-pembayaran/{README,user-flow,flowchart}.md`, `docs/TASKS.md` → Done, catatan implementasi di file ini.
9. `npm run build`, `vendor/bin/pint --dirty --format agent`. Jalankan **hanya** file test terdampak (jangan full suite).

## 6. Test — `tests/Feature/PaymentEditFullRevisionTest.php`
Tanggal memakai `now()`/`Carbon`, **bukan** string hard-code.

| # | Skenario | Harapan |
|---|---|---|
| 1 | Nominal turun (lunas→sebagian) | `paid/remaining/status` invoice ikut berubah |
| 2 | Nominal naik sampai pas | Invoice `lunas` |
| 3 | Nominal > sisa | `overpay_amount` terbentuk; baris ledger delta positif; saldo naik |
| 4 | Payment ber-overpay dikoreksi ke pas | Baris delta negatif; saldo turun; boleh negatif bila sudah terpakai |
| 5 | Tambah / kurangi / hapus `use_balance_amount` | Delta benar; saldo tak cukup → error field `use_balance_amount`, tak ada perubahan tersimpan |
| 6 | **Edit dua kali berturut-turut pada payment yang sama** | Tidak ada `UniqueConstraintViolationException`; `revision` 1 dan 2; saldo akhir benar |
| 7 | Overpay AWAL vs non-AWAL | Sumber saldo awal tak berubah; koreksi selalu `KOREKSI` |
| 8 | Guard: payment `DITOLAK` | Ditolak |
| 9 | Guard K3: `payment_date` bulan lalu | Ditolak (edit & update), pesan menyarankan Kembalikan |
| 10 | Guard K3: tanggal baru bulan lalu / masa depan | Ditolak |
| 11 | Guard: setoran terverifikasi (semua status non-pending) | Ditolak |
| 12 | K4: payment dalam setoran `MENUNGGU_VERIFIKASI`, nominal diubah | Sukses; `computedAmount()` setoran = angka baru; `verify()` sesudahnya memakai angka baru |
| 13 | K4: payment tertaut setoran, ganti metode / kolektor | Ditolak; field lain boleh |
| 14 | Payment belum tertaut setoran, ganti kolektor→cash | Sukses; saldo kolektor & tunai admin turunan ikut bergeser |
| 15 | K5: payment metode Saldo / key `auto-saldo:` | Ditolak; opsi Saldo tidak ada di dropdown edit; tombol Edit tersembunyi |
| 16 | Invoice `BATAL` / `TAK_TERTAGIH` | Ditolak |
| 17 | Rekening nonaktif dipilih baru vs rekening lama yang kini nonaktif dipertahankan | Ditolak vs lolos |
| 18 | Metode Transfer→Cash | `bank_*`, `sender_name` dikosongkan |
| 19 | Scope POP | Payment POP lain → 403 di `edit` & `update` |
| 20 | Permission | Tanpa `payments.update` → 403 (termasuk yang hanya `payments.create`) |
| 21 | Gagal validasi | Redirect ke halaman edit dengan error + input lama |
| 22 | Format `150.000` | Terbaca 150000 |
| 23 | `applied ≤ 0` | Ditolak |
| 24 | Atomik | Gagal di tengah → payment/invoice/ledger tidak berubah |
| 25 | Audit | `AuditLog` `update` (old/new); dengan alasan → entri `koreksi`; tanpa alasan → tidak ada entri `koreksi` |
| 26 | Kas | `physicalAmount()` & saldo kas admin/kolektor benar setelah koreksi (overpay & saldo dipakai) |
| 27 | Penjaga statis | `PostTargetRenderedServerSideTest` hijau; tak ada modal edit tersisa |
| 28 | **RBAC default (K6)** — setelah `DatabaseSeeder`: hanya `owner` & `admin` yang lolos `userCan('payments.update')`; `pop_admin`, `helpdesk`, `noc`, `fop`, `teknisi`, `sales`, `kolektor`, `atasan` **tidak** | Penjaga agar izin tak bocor lewat perubahan seeder. Tidak memakai daftar role hard-code yang menyembunyikan role baru: iterasi semua role seeded, kecualikan owner/admin |
| 29 | **RBAC dinamis (K6)** — `RoleManagementService::syncPermissions()` mencentang `payments.update` untuk `pop_admin` | `pop_admin` (dalam scope POP-nya) lolos `edit`/`update`; `payments.view` ikut otomatis; di luar scope → 403; cache di-clear (tanpa restart); mencabutnya lagi → 403 |
| 30 | **RBAC gerbang tombol** — user tanpa `payments.update` membuka `payments.index` / `payments.show` | Tombol/link Edit tidak dirender; user ber-`payments.create` saja pun tidak melihatnya dan `PUT` ke `payments.update` → 403 |
| 31 | **Matrix** — halaman Role Matrix | Baris `payments.update` tidak lagi berlabel "[Belum aktif]"; deskripsi memuat batas (bulan berjalan, bukan Saldo, bukan setoran terverifikasi). Update `RolePermissionMatrixTest` bila ada assertion label lama |
| 32 | **Migrasi K7** — ledger | Baris lama `revision = 0`; unique baru menolak duplikat `(payment_id,type,source,revision)` tetapi mengizinkan `KOREKSI` revisi 1 lalu 2; `down()` memulihkan index lama |

## 7. Risiko
- **Ledger saldo** (F2) — risiko terbesar. Salah pakai `reverse*`/`credit` = saldo dobel atau exception unique. Test #6 wajib.
- **Race edit vs verifikasi setoran** (F3.1) — tanpa urutan lock setoran→invoice→payment, angka verifikasi bisa campur.
- **Dua rumus auto-split** — wajib ekstrak `splitAmount()`.
- **Pencabutan hak edit pop_admin** (F6, konsekuensi K6): terjadi otomatis saat deploy. Beri tahu pengguna sebelum rilis; pemulihannya cukup mencentang `payments.update` di Role Matrix (tanpa deploy).
- **Uang tercatat berubah** — guard `editBlockedReason()` tidak boleh dilonggarkan.

## 8. Batasan (tidak dikerjakan)
- Pembalik untuk setoran terverifikasi (`reject()` menyebut jalur ini) — tetap terpisah.
- Edit massal / batch kolektor (`PaymentBatchController`, `CollectorPaymentController`).
- Mengubah `total_amount` invoice.
- Notifikasi ke pencatat pembayaran (opsional, bisa menyusul memakai `notifyPaymentRecorderIfDifferentActor`).

## 9. Keputusan (semua terjawab — siap implementasi setelah user memberi aba-aba)

| # | Keputusan user (2026-09-28) | Ditulis di |
|---|---|---|
| K6 | **Jangan beri `payments.update` ke role lain untuk sementara; siapkan RBAC agar bisa disesuaikan nanti.** Hanya owner/admin default; role lain diatur lewat Role Matrix. | F6, 4.6 |
| K7 | **Ya**: tambah kolom `customer_balance_mutations.revision` + unique index `(payment_id, type, source, revision)` + `BalanceMutationSource::KOREKSI`. | F2, langkah 1 bagian 5 |

Catatan migrasi K7 (untuk implementasi):
- Migrasi baru: tambah `revision` `unsignedSmallInteger` `default(0)`, drop `customer_balance_mutations_payment_type_source_unique`, buat ulang termasuk `revision`. Semua baris lama otomatis `revision = 0` → perilaku lama tidak berubah.
- Pola drop/create index ikuti `2026_09_24_103221_add_source_to_customer_balance_mutations_table.php` (termasuk `down()`); uji di sqlite `:memory:` (test) dan DB dev.
- Revisi ke-n = (jumlah baris `KOREKSI` payment tsb, semua tipe, dibagi per revisi) + 1; hitung di dalam transaksi yang sudah mengunci payment supaya dua koreksi tak berebut nomor.
- `CustomerBalanceMutationObserver` bersifat append-only — baris koreksi hanya `create`, tidak pernah update/hapus.

## 10. Catatan Implementasi (2026-09-29)

Diimplementasikan sesuai bagian 4–9. Penyimpangan dari rancangan tertulis:

1. **Urutan lock jadi PAYMENT → SETORAN → INVOICE**, bukan "setoran → invoice → payment" seperti tertulis di §4.4/F3.1. Alasan: setoran mana yang menautkan payment ini baru diketahui SETELAH baris payment dibaca (`collector_deposit_id`/`cash_deposit_id` ada di tabel `payments`), jadi urutan tekstual tak bisa dieksekusi apa adanya. Mengunci payment PALING AWAL justru memperkuat maksud rancangan: itu mencegah `CollectorDepositService::submit()` (yang juga `lockForUpdate()` baris payment saat menyusun setoran baru) menautkan payment ini ke setoran lain di tengah proses edit. Lihat `PaymentService::revise()` docblock.
2. **`CustomerBalanceService::lockedBalance()` diubah dari `private` ke `public`** — dibutuhkan `revise()` untuk mengecek kecukupan saldo NILAI BARU dengan lock yang sama (mencegah dua edit simultan pada pelanggan yang sama lolos berbarengan), pola yang sama dengan pemakaian internalnya di `debit()`.
3. **`store()` (form Bayar) ikut dipindah ke `paymentRules()`** bersama `update()`, sesuai §4.5, dan sebagai efek sampingnya `payment_method=saldo` kini eksplisit DITOLAK oleh validasi `store()` juga (sebelumnya cuma tidak pernah dipilih dari UI, bukan ditolak server) — pengerasan yang selaras K5, bukan pengetatan yang tidak diminta.
4. **`use_balance_amount` di `update()` ikut dinormalkan `RupiahInput::parse()`** sebelum divalidasi — `store()` TIDAK melakukan ini (celah lama, di luar scope ADHOC-108, dilaporkan di temuan tambahan bagian ini, bukan diperbaiki di `store()`).
5. **Partial `_form.blade.php` TIDAK dibuat** (berbeda dari §4.2) — `payments/edit.blade.php` ditulis berdiri sendiri karena field yang tersedia berbeda dari `create` (Edit punya opsi Kolektor & field Alasan Koreksi, tidak punya Saldo sebagai metode). Konsekuensinya markup toggle metode terduplikasi antar dua file; diterima demi kecepatan, dicatat sebagai utang teknis kecil kalau nanti field-nya makin banyak menyimpang.
6. **Dispatch `CollectorDepositUpdated` untuk setoran pending yang totalnya berubah TIDAK dilakukan** (F3.5 sengaja dilewati, bukan bug) — event itu string `$aksi`-nya (`diajukan|diverifikasi|dilunasi|dihapus_buku`) tidak punya nilai yang tepat untuk "nominal diedit sambil pending". `CollectorActivityUpdated` dengan `$aksi='pembayaran_dikoreksi'` (nilai baru, event ini bukan enum) tetap di-dispatch ke kolektor terkait supaya Worklist yang terbuka tak memajang saldo lama — frontend yang belum mengenali aksi ini akan mengabaikannya (fallback aman, bukan error).
7. **Migrasi `2026_09_29_000001_add_revision_to_customer_balance_mutations_table` ditulis ulang jadi idempotent** setelah gagal dua kali berturut-turut saat dijalankan user di DB dev MySQL (`docker exec whusnet-app php artisan migrate`, 2026-09-29) — dua bug berbeda, keduanya cuma muncul di MySQL (sqlite test tidak pernah menyentuhnya):
   - **Bug A — kolom `revision` sudah ada tapi migrasi tercatat "Pending".** MySQL meng-commit tiap statement DDL sendiri-sendiri (tak ada rollback sebagian); proses migrate awal berhenti di tengah (`ADD COLUMN` sukses, langkah berikutnya belum sempat jalan) sebelum baris `migrations` ditulis. Run ulang mengulang `up()` dari awal → `ADD COLUMN revision` gagal "Duplicate column name". **Fix:** tiap langkah dibungkus `Schema::hasColumn()`/`Schema::hasIndex()` — migrasi sekarang aman dijalankan ulang dari state manapun.
   - **Bug B — index unik lama tak bisa di-drop.** `customer_balance_mutations_payment_type_source_unique` (diawali `payment_id`) ternyata satu-satunya index yang menutupi kolom `payment_id` (foreign key ke `payments`) — tak ada index `payment_id_foreign` terpisah. MySQL/InnoDB menolak `DROP INDEX` index semacam ini selama belum ada index pengganti yang menutupi kolom FK yang sama ("Cannot drop index ...: needed in a foreign key constraint"). **Fix:** urutan dibalik — index BARU (`..._revision_unique`, sama-sama diawali `payment_id`) dibuat LEBIH DULU, index LAMA baru dihapus SETELAHNYA (bukan drop-dulu-baru-add). `down()` disimetriskan (tambah index lama dulu, baru hapus index baru, baru drop kolom).

   Diverifikasi ulang: sqlite `RefreshDatabase` (21 test `PaymentEditFullRevisionTest`+`PaymentEditUpdateTest` tetap hijau) DAN langsung di MySQL dev (`php artisan migrate` → `DONE`, `SHOW INDEX` mengonfirmasi index akhir 4 kolom, index lama sudah hilang, kolom FK `payment_id` tetap tertutupi index sepanjang waktu).

### Temuan tambahan (dilaporkan, TIDAK diperbaiki — di luar scope ADHOC-108)

- **`PaymentController::store()` tidak menormalkan `use_balance_amount` lewat `RupiahInput::parse()`** (hanya `amount` yang dinormalkan). Kalau JS mask gagal/nonaktif, input "50.000" tervalidasi `numeric` tapi dibaca 50.0 (seribu kali lebih kecil) — kelas bug yang sama yang `RupiahInput` dibuat untuk cegah, tapi belum ditambal di jalur ini. Sudah ditambal di `update()` (poin 4 di atas) karena itu kode baru; `store()` dibiarkan sesuai instruksi "jangan ubah di luar scope".
- **`CashDeposit::computedAmount()` menjumlah `manualPayments()->sum('amount')` mentah**, bukan `Payment::physicalAmount()` seperti `CollectorDeposit::computedAmount()`/`AdminCashBalanceService`/`CollectorBalanceService` (ADHOC-92 G4). Payment manual tunai yang overpay/pakai-saldo tidak terhitung benar di setoran kas admin. Pre-existing, tidak disentuh — tapi relevan karena payment yang diedit di sini bisa saja payment manual dalam `cash_deposit_id` yang menunggu verifikasi.
- **`MiddlewarePermissionTest::admin_has_access_to_all…` gagal (403) di `/users`** di working tree ini — ditelusuri BUKAN akibat perubahan ADHOC-108 (dikonfirmasi lewat `git stash` ke commit terakhir: test itu hijau di HEAD). Penyebabnya perubahan lain yang SUDAH ada di working tree sebelum sesi ini dimulai (`config/rbac.php` `view_autogrant_exempt`/`view_autogrant_chain_boundary` untuk `customers.*`, tertanggal komentar "bug 2026-09-29", di luar scope ADHOC-108 — kemungkinan pekerjaan ADHOC-107 yang belum selesai). Dilaporkan, tidak diperbaiki.
