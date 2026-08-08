# Analisa: Pencegahan Tagihan & Pembayaran Dobel

Ditulis 2026-07-21, dipicu temuan bug `activation_date` (BILLING-B0).

Inti masalahnya bukan satu bug, tapi satu **titik tunggal kegagalan**: seluruh
pencegahan tagihan dobel bergantung pada satu kolom yang bisa salah isi.
Dokumen ini memetakan lapisan pertahanan yang ada, mana yang bolong, dan urutan
perbaikannya.

---

## 1. Gejala

Pelanggan menerima dua tagihan untuk periode yang sama:

```
1 Juni    pelanggan daftar         → activation_date = 2026-06-01 (tanggal DAFTAR)
21 Juli   admin verifikasi & aktif → invoice AWAL, periode 2026-07, prorata 35.484
1 Agustus cron menagih Juli?       → activation_date Juni ≠ Juli → TIDAK di-skip
                                    → terbit invoice BULANAN Juli Rp 110.000
```

Dua invoice sah menurut sistem, periode sama, pelanggan sama.

Penyebab langsung: `customer_services.activation_date` diisi `registration_date`
saat pendaftaran (`CustomerController::store`) dan dulu tidak pernah ditimpa saat
aktivasi.

---

## 2. Kenapa tidak ada yang menahan

`GenerateMonthlyInvoicesCommand` mengklaim punya tiga lapis penjaga. Yang
benar-benar relevan cuma satu.

| Lapis | Isi | Menangkap kasus ini? |
|---|---|---|
| 1 | Lewati bulan aktivasi via `activation_date` | **Ya — dan ini satu-satunya** |
| 2 | `alreadyExists`: cek invoice periode sama | Tidak. Di-scope `invoice_type`, jadi AWAL dan BULANAN dianggap bukan duplikat |
| 3 | `InvoiceObserver::creating()` | Tidak. Di-scope `invoice_type` juga |

Tabel `invoices` tidak punya unique index sebagai jaring terakhir — migration
`add_duplicate_guard_indexes_to_invoices_and_payments` sengaja hanya memasangnya
di `payments`, karena data invoice hasil migrasi legacy masih menyimpan
pelanggaran historis (alasannya ada di docblock migration itu).

**Kesimpulan: lapis 1 jebol = tidak ada cadangan sama sekali.**

---

## 3. Solusi berlapis

Prinsipnya: pencegahan dobel tidak boleh bergantung pada satu kolom yang bisa
salah isi.

### Lapis 1 — Perbaiki sumber ✅ SELESAI (BILLING-B0 + B0b)

`CustomerVerificationController::finalVerify()` menimpa `activation_date` dengan
`issue_date` (tanggal yang sama dengan basis prorata). Dikunci
`tests/Feature/AktivasiTertagihDobelKarenaActivationDateStaleTest.php`.

Untuk data lama ada `billing:backfill-activation-date` (BILLING-B0b) — default
hanya mencetak daftar usulan, `--force` baru menulis, tiap perubahan masuk audit
log. Urutan sumber: invoice `AWAL` → `customer_installations.finished_date` →
lapor manual (tidak menebak).

Baris hasil migrasi legacy dilewati seluruhnya: `activation_date`-nya diisi dari
`finished_at` sistem lama waktu import, jadi memang sudah tanggal aktivasi.
**Eksekusi di produksi belum dilakukan** — menunggu owner membaca dry-run.

### Lapis 2 — Penjaga lintas-jenis ✅ SELESAI (BILLING-B0c)

Sebelumnya:

```php
$alreadyExists = Invoice::where('customer_id', $customer->id)
    ->where('billing_period', $billingPeriod)
    ->where('invoice_type', InvoiceType::BULANAN->value)   // ← filter ini bikin buta
    ->exists();
```

Sekarang:

```php
$alreadyExists = Invoice::where('customer_id', $customer->id)
    ->where('billing_period', $billingPeriod)
    ->whereIn('invoice_type', [InvoiceType::AWAL->value, InvoiceType::BULANAN->value])
    ->whereNot('invoice_status', InvoiceStatus::BATAL->value)
    ->exists();
```

Pertanyaannya berubah dari "sudah ada tagihan BULANAN?" jadi "sudah ada tagihan
**langganan** untuk periode ini?".

Efeknya: **meski `activation_date` salah, invoice AWAL Juli tetap menghalangi
terbitnya BULANAN Juli.** Lapis 1 tidak lagi jadi titik tunggal kegagalan.

`REAKTIVASI` sengaja dikecualikan — pelanggan yang disuspend lalu aktif lagi di
bulan yang sama memang boleh punya dua record; itu bukan dobel.

Invoice berstatus `BATAL` juga dikecualikan, kalau tidak tagihan yang sudah
dibatalkan akan memblokir penerbitan penggantinya.

### Lapis 3 — Observer ikut lintas-jenis ✅ SELESAI (BILLING-B0c)

`InvoiceObserver::creating()` punya masalah yang persis sama, dan aturan barunya
ada di `rejectSecondSubscriptionInvoice()`. Ditegakkan di observer supaya jalur
non-cron ikut tertutup: input manual admin, import, tinker.

Aturan lama (burst dedup — jenis, periode, dan nominal sama dalam 300 detik)
**tetap dipertahankan**; keduanya menangkap gejala berbeda. Burst dedup menahan
double-submit; aturan baru menahan tagihan langganan kedua di periode yang sama
walau nominalnya beda dan jaraknya berbulan-bulan.

Satu pengecualian penting: invoice ber-`old_invoice_id` dilewati. Data legacy
memang menyimpan pelanggaran historis (ada pelanggan dengan AWAL dan BULANAN di
periode sama), dan `MigrateLegacyDataCommand` harus tetap bisa memuatnya apa
adanya. Membersihkannya ranah BILLING-B0e, bukan tugas guard ini.

Dikunci `tests/Feature/SatuTagihanLanggananPerPeriodeTest.php` — 6 kasus,
termasuk yang sengaja merusak `activation_date` untuk membuktikan lapis 2
berdiri sendiri tanpa bergantung lapis 1.

### Lapis 4 — Jaring database ⬜ BELUM (BILLING-B0e)

Unique index `(customer_id, billing_period)` untuk jenis langganan. Belum bisa
dipasang karena data legacy masih menyimpan pelanggaran historis, jadi urutannya
wajib:

1. bereskan grup dobel legacy dulu
2. baru pasang unique index

Catatan teknis: index parsial (`WHERE ...`) jalan di SQLite tapi **tidak** di
MySQL. Kalau produksi MySQL, tidak ada jalan pintas — data harus bersih dulu.

### Lapis 5 — Deteksi, bukan cuma pencegahan ✅ SELESAI (BILLING-B0d)

```
php artisan billing:audit-duplicate-invoices
php artisan billing:audit-duplicate-invoices --period=2026-07
php artisan billing:audit-duplicate-invoices --strict     # exit 1 kalau ada temuan
```

Read-only, melaporkan pelanggan dengan >1 tagihan langganan pada periode sama.
Gunanya: kalau suatu hari lapis di atas jebol lewat jalur yang belum terpikir,
ketahuan dalam hitungan hari — bukan waktu pelanggan menelepon marah.

Temuan dipisah dua kelompok. Grup yang **semua** barisnya bertanda
`old_invoice_id` ditandai `legacy` (warisan migrasi, ranah BILLING-B0e);
sisanya `PERLU CEK` — itu berarti ada jalur pembuatan invoice yang lolos dari
dua lapis guard, dan harus diselidiki. Tanpa pemisahan ini, temuan baru
tenggelam di antara data lama yang memang sudah diketahui kotor.

Command juga menghitung nominal yang **sudah terbayar** di grup dobel, dan
memperingatkan kalau ada — itu uang pelanggan yang harus dijadikan kredit atau
dikembalikan, bukan sekadar tagihan yang dibatalkan.

**Tidak ada `--fix`, dan sengaja.** Memutuskan tagihan mana yang dibatalkan dan
bagaimana memperlakukan uang yang terlanjur dibayar adalah keputusan bisnis per
kasus, bukan sesuatu yang boleh diputuskan massal oleh command.

Catatan scope: command CLI berjalan tanpa user login, jadi laporannya
lintas-POP. Itu disengaja — auditnya memang untuk owner/pusat. Kalau suatu saat
hasil ini diekspos lewat HTTP, wajib dibatasi `EffectiveAccessService::getAllowedPopIds()`
dulu.

Dikunci `tests/Feature/AuditTagihanDobelTest.php` (8 kasus).

---

## 4. Pembayaran dobel — kasus berbeda

Kondisinya sudah lebih baik dan **tidak** perlu perbaikan:

| Penjaga | Status |
|---|---|
| Unique index `payments (invoice_id, payment_date, amount)` | Terpasang |
| `PaymentObserver::creating()` menolak nominal ≤ 0 | Ada |
| `Payment` selalu menempel ke invoice yang jenisnya sudah pasti | Sejak awal |

Pembayaran dobel akibat retry/double-submit sudah tertutup.

**Tapi akar masalah "pelanggan bayar dua kali" di kasus kita bukan pembayaran
yang dobel, melainkan tagihannya.** Pelanggan membayar dua invoice berbeda yang
dua-duanya sah menurut sistem. Unique index di `payments` tidak akan pernah
menangkap itu — `invoice_id`-nya memang beda. Perbaikannya harus di sisi invoice.

Efek samping yang perlu diketahui: unique index `(invoice_id, payment_date,
amount)` juga memblokir pembayaran sah yang kebetulan identik — pelanggan
mencicil 50.000 dua kali di hari yang sama. Jarang, tapi kalau kasir melapor
"gagal simpan", inilah penyebabnya.

---

## 5. Menangani tagihan dobel yang sudah terbit

- **Jangan dihapus.** Invoice lunas dan riwayat pembayaran tidak boleh hilang.
- Yang salah di-set `InvoiceStatus::BATAL` + alasan + audit log.
- Kalau yang dobel sudah **dibayar**, uangnya tidak boleh menguap — harus jadi
  kredit ke periode berikutnya atau dikembalikan. Ini keputusan bisnis, dan
  sistem belum punya konsep kredit/saldo pelanggan.

---

## 6. Urutan pengerjaan

| Urutan | Task | Isi | Risiko | Perlu keputusan bisnis? |
|---|---|---|---|---|
| 1 | BILLING-B0c ✅ | Lapis 2 + 3, guard lintas-jenis | Rendah — murni kode + test | Selesai 2026-07-21 |
| 2 | BILLING-B0d ✅ | Command audit (read-only) | Nol | Selesai 2026-07-21 |
| 3 | BILLING-B0b ✅ | Backfill `activation_date` (dry-run default) | Nol sampai `--force` | Command selesai 2026-07-21; eksekusi produksi menunggu owner |
| 4 | BILLING-B0e | Bersihkan dobel legacy + unique index | Sedang — sentuh data | Ya — per kasus |

**Kerjakan 1 dan 2 lebih dulu.** Keduanya tidak menyentuh data sama sekali tapi
langsung menghentikan pendarahan: begitu lapis 2 lintas-jenis masuk,
`activation_date` yang salah tidak lagi bisa memproduksi tagihan dobel baru.
Backfill turun status dari "darurat" jadi "rapikan data".

---

## 7. Kondisi data saat dokumen ini ditulis

DB development:

- `customer_services` dengan `service_status = aktif`: **0** → dampak backfill
  belum bisa diukur di sini, angka sebenarnya harus diambil dari produksi lewat
  dry-run.
- Baris legacy: **1.956**, di antaranya **146** punya `activation_date` NULL
  karena kelima sumber tanggal di sistem lama kosong/`0000-00-00`.
  **Keputusan owner 2026-07-21: dibiarkan kosong.** Lebih baik kosong dan jujur
  daripada diisi perkiraan dari `biaya_tagihan.TGLINSERT` (itu tanggal tagihan
  dibuat, bukan tanggal layanan menyala). Aman karena penjaga dobel sudah tidak
  bergantung pada kolom ini sejak lapis 2. Jangan tambahkan fallback baru di
  `MigrateLegacyDataCommand` tanpa keputusan bisnis baru — catatannya ada di
  docblock rantai fallback `$actDate`.
- Grup `customer_id` + `billing_period` dengan >1 invoice: **5**, semuanya
  bertanda legacy. Hasil `billing:audit-duplicate-invoices` per 2026-07-21:

  | Kode | Nama | Periode | Jenis | Total | Terbayar |
  |---|---|---|---|---|---|
  | RQ000289 | Djarijanto | 2022-12 | awal, awal | 208.065 | 208.065 |
  | RQ000306 | Haryanto | 2022-12 | awal, awal | 166.774 | 166.774 |
  | RQ000311 | Wakhid Fandra Bahtiar | 2022-12 | bulanan, bulanan | 170.323 | 170.323 |
  | RQ000308 | Sugianto | 2023-01 | awal, awal | 351.032 | 351.032 |
  | RQ001660 | Tesdata | 2025-07 | bulanan, awal | 374.194 | 257.097 |

  Total terbayar di grup dobel: **Rp 1.153.291**. Artinya pembersihan B0e bukan
  sekadar membatalkan tagihan — ada uang yang sudah masuk dan harus diputuskan
  nasibnya. `perlu dicek: 0`, jadi tidak ada tagihan dobel dari jalur berjalan.

---

## Referensi

- `app/Console/Commands/GenerateMonthlyInvoicesCommand.php`
- `app/Observers/InvoiceObserver.php`
- `app/Http/Controllers/CustomerVerificationController.php` (`finalVerify`)
- `database/migrations/2026_07_04_091731_add_duplicate_guard_indexes_to_invoices_and_payments.php`
- [perbandingan-tagihan-awal-vs-bulanan-legacy.md](perbandingan-tagihan-awal-vs-bulanan-legacy.md) bagian 3.3
- `docs/TASKS.md` — BILLING-B0b/B0c/B0d/B0e
