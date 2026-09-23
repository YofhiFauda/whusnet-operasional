# Analisa & Rancangan: Upgrade/Downgrade Paket Internet

**Status:** Terbuka — analisa selesai 2026-09-15, framing diperjelas 2026-09-22 (dikonfirmasi user — isi/keputusan **tidak berubah**, cuma cara penjelasan §1 diperbaiki karena bikin salah paham "2 rumus terpisah"). Di luar Sprint 8.10 (aktif), dicatat sebagai ADHOC-68 di `docs/TASKS.md`.

**Sumber ide awal:** `docs/plan/billing/upgrade-downgrade/Skema-downgrade-dan-upgrade.md` (skema matematis dari user). Dokumen ini adalah hasil review terhadap skema tsb + gap analysis terhadap kode nyata + rancangan implementasi.

---

## 1. Ringkasan Putusan

**Rumusnya CUMA SATU** — bukan dua rumus terpisah ("Postpaid" vs "Prepaid" seperti judul di dokumen sumber). Berlaku sama persis untuk upgrade maupun downgrade:

```
total_tagihan_periode_ini = prorate_paket_lama + prorate_paket_baru
sisa_yang_harus_dibayar   = total_tagihan_periode_ini − yang_sudah_dibayar
```

Hasilnya otomatis menyesuaikan tergantung berapa yang **sudah dibayar duluan** untuk periode ini — bukan tergantung "tipe pelanggan":
- **Belum bayar sama sekali** → `sisa` = total penuh (ini yang di dokumen sumber disebut "Postpaid").
- **Sudah bayar lunas** paket lama duluan → `sisa` otomatis jadi lebih kecil, cuma kekurangannya aja (ini yang di dokumen sumber disebut "Prepaid" — rumus "selisih").
- **Kalau hasil `sisa` malah minus** (dibayar lebih besar dari total baru, kasus umum di downgrade) → jadi **Deposit** untuk bulan depan, bukan ditagih.
- **Kalau baru dibayar sebagian** (cicilan) → `sisa` dikurangi sebesar yang sudah dicicil, sama seperti dua kasus di atas — satu formula yang sama, tidak perlu cabang tambahan.

Dikonfirmasi user (2026-09-22): pemahaman ini benar, cuma penjelasan `Postpaid`/`Prepaid` di dokumen sumber sempat disangka butuh 2 rumus/kolom "tipe pelanggan" terpisah — sistem ini memang **tidak** dan **tidak perlu** punya kolom/konsep semacam itu (dicek: nihil di `app/`, `database/migrations/`). Satu formula di atas sudah otomatis mencakup semua kasus.

Piutang: **upgrade diblok** kalau pelanggan masih punya piutang periode sebelumnya (termasuk yang sudah dicicil sebagian, status `sebagian` — bukan cuma yang sama sekali belum dibayar); **downgrade tetap boleh jalan** meski ada piutang (dikonfirmasi ulang user 2026-09-22, lihat §2.9).

---

## 2. Gap Terhadap Kode Nyata

### 2.1 `CustomerPackageService::change()` tidak menyentuh invoice sama sekali

`app/Services/CustomerPackageService.php:18-26` eksplisit bilang: ganti paket cuma menimpa kolom `customer_services` (paket, snapshot kecepatan, harga, `total_monthly_bill`). Invoice periode berjalan **tidak disentuh** — efek harga baru baru kepakai mulai `GenerateMonthlyInvoicesCommand` jalan periode berikutnya. Ini kontradiksi total dengan skema prorate: sekarang ganti paket tengah bulan **gratis**, tidak ada prorate apa pun. Servis ini harus di-rewrite total untuk skema baru, bukan ditambal.

### 2.2 Tidak ada tabel riwayat ganti paket

Tidak ada `customer_package_changes` atau sejenisnya. Dibutuhkan untuk:
- Audit: paket lama, paket baru, tanggal efektif, hari pakai tiap sisi, breakdown prorate, siapa yang eksekusi.
- Basis hitung kalau ganti paket **terjadi lebih dari sekali** dalam satu periode (skema sumber cuma punya 2 variabel/2 segmen — lihat §3.5).
- Idempotency guard (satu perubahan gak diproses dobel).

### 2.3 Kredit/deposit belum punya jalur non-payment

`CustomerBalanceService::credit()` (`app/Services/CustomerBalanceService.php:74-89`) **wajib** `Payment $sourcePayment` — dirancang supaya kreditnya bisa dibalik kalau payment sumber overpay-nya direject (`reverseCreditForPayment()`). Deposit dari downgrade **bukan** turunan dari payment — perlu jalur baru: kolom `payment_id` nullable + referensi ke record ganti-paket (§2.2), atau method kredit terpisah dengan semantik reversal yang beda (reversal-nya bukan "payment ditolak", tapi "ganti paket dibatalkan/salah input" — kasus lebih jarang tapi harus ada jalurnya).

### 2.4 Perlakuan diskon/PPN/other_fee belum didefinisikan

`CustomerPackageService::change()` sekarang menghitung `total_monthly_bill` dari `(monthly_price - discount) * (1 + ppn%) + other_fee`. Skema prorate di dokumen sumber cuma pakai `monthly_price` polos. Belum diputuskan:
- Diskon (nominal tetap) & PPN (persen) — ikut diprorate per segmen (proporsional ke hari) atau dihitung dari total bulan penuh dulu baru dipotong prorate-nya?
- `other_fee` (kalau ada biaya lain flat bulanan) — dikenakan sekali penuh, atau ikut dibagi hari juga?

**Keputusan (2026-09-15):** yang diprorate **cuma `monthly_price` paket internet**. `discount`, `ppn`, `other_fee` **tidak** dipecah per segmen — tetap dihitung dari nilai `customer_service` yang berlaku saat invoice diproses (snapshot terbaru, dikenakan sekali penuh di level total invoice), sama seperti perilaku `total_monthly_bill` sekarang. Lihat §3 (rumus final sudah disesuaikan).

### 2.5 Aturan cutover hari belum eksplisit

Contoh dokumen sumber: "Perubahan Terjadi Pada Hari ke-11 (paket lama dipakai 10 hari, paket baru 20 hari)" — artinya hari ke-11 **sudah** masuk hitungan paket baru. Ini harus dikunci sebagai aturan resmi: **hari efektif = mulai paket baru**, hari sebelumnya (1..hari efektif−1) = paket lama. Tanpa dikunci eksplisit di kode, gampang kena off-by-one (dobel hitung atau bolong 1 hari).

### 2.6 Jumlah hari pakai konstanta, bukan hari riil

Dokumen sumber pakai `30 hari` tetap untuk semua contoh. Implementasi wajib pakai **jumlah hari riil periode berjalan** (`billing_period`'s actual calendar days — 28/29/30/31), bukan konstanta. Kasus tambahan: **invoice `awal`** (aktivasi, prorate custom sejak tanggal aktivasi sampai akhir bulan) punya panjang periode yang **tidak** 1 bulan penuh — kalau ganti paket terjadi masih dalam periode invoice `awal` ini, "jumlah hari dalam periode" harus dihitung dari panjang periode invoice `awal` itu, bukan disamakan dengan bulan kalender.

### 2.7 Kasus invoice `sebagian` (partial paid) belum eksplisit di dokumen sumber

Dokumen sumber cuma bagi 2 kondisi biner (belum bayar sama sekali / sudah lunas). Perlu digeneralisasi:

- Invoice **belum lunas** (baik `belum_dibayar` maupun `sebagian`) → total invoice di-**recompute** jadi `prorate_lama + prorate_baru`; sisa tagih = `total_baru - sudah_dibayar`.
- Kalau hasil `sisa tagih` **negatif** (karena yang sudah dibayar > total baru — bisa terjadi kalau downgrade & pelanggan sudah bayar penuh sebagian besar tagihan lama) → itu otomatis jadi kasus "lunas": sisa tagihan invoice = 0, dan **selisih negatifnya jadi deposit** bulan depan (sama seperti kasus lunas penuh).

Jadi aturan yang benar bukan dua cabang terpisah, tapi **satu formula** (`total_baru = prorate_lama + prorate_baru`, `sisa = total_baru - dibayar`) dengan **clamp di 0** dan surplus jadi deposit. Ini generalisasi yang lebih bersih daripada percabangan "Postpaid vs Prepaid" di dokumen sumber — sekaligus otomatis benar untuk kasus lunas (dibayar = harga lama penuh, sisa pasti ≤ 0 kalau downgrade, atau > 0 kalau upgrade).

### 2.8 Ganti paket berkali-kali dalam satu periode

Formula dokumen sumber cuma 2 variabel (prorate lama + prorate baru). Kalau pelanggan ganti paket 2x dalam sebulan (A→B→C), butuh generalisasi jadi **jumlah prorate per segmen** (n segmen, n paket, n rentang hari), diturunkan dari riwayat ganti paket (§2.2), bukan cuma "paket sekarang" dan "paket sebelumnya".

### 2.9 Upgrade wajib lunas dulu kalau ada tunggakan; downgrade tidak (keputusan 2026-09-15)

- **Upgrade** → diblok kalau pelanggan punya tunggakan: invoice periode **sebelumnya** (bukan periode berjalan yang sedang diproses) berstatus `belum_dibayar` atau `sebagian`. Pelanggan harus lunasi dulu baru boleh upgrade. Pesan error jelas, bukan silent-fail.
- **Downgrade** → tetap boleh jalan meski ada tunggakan (masuk akal secara bisnis: downgrade justru mengurangi beban tagihan pelanggan ke depan, tidak ada alasan diblok).
- Tunggakan periode **berjalan** (invoice bulan ini sendiri, yang justru sedang diproses ulang oleh prorate) **tidak dihitung** sebagai blocker — itu bagian dari alur normal (§2.7), bukan tunggakan.

---

## 3. Rumus Final (setelah generalisasi §2.7, §2.8, keputusan §2.4 & §2.9)

Untuk periode invoice dengan **n segmen paket internet** (n ≥ 1, n=1 kalau tidak ada perubahan):

```
subtotal_paket = Σ (harga_harian_paket_i × hari_segmen_i)   untuk i = 1..n
harga_harian_paket_i = monthly_price_i / hari_dalam_periode
```

Cuma `monthly_price` yang diprorate per segmen (§2.4). Baru di level total invoice, diskon/PPN/other_fee dikenakan **sekali** dari nilai `customer_service` terbaru:

```
total_invoice_periode = max(0, subtotal_paket - discount) × (1 + ppn%) + other_fee
```

Lalu:

```
sisa_tagih = max(0, total_invoice_periode - sudah_dibayar)
deposit_baru = max(0, sudah_dibayar - total_invoice_periode)
```

- `sisa_tagih > 0` → invoice di-update nominalnya, pelanggan bayar kekurangan.
- `deposit_baru > 0` → dicatat sebagai kredit saldo pelanggan (lihat §2.3), otomatis mengurangi tagihan periode berikutnya lewat `CustomerBalanceService` yang sudah ada.
- **Sebelum hitung apa pun untuk upgrade** → cek §2.9 (tunggakan periode sebelumnya). Kalau ada tunggakan, tolak di awal (`InvalidArgumentException`/`RuntimeException`), jangan lanjut ke prorate.

Ini backward-compatible: kalau `n=1` dan tidak ada ganti paket, `total_invoice_periode` = tagihan flat biasa seperti sekarang.

---

## 4. Rancangan Implementasi

### 4.1 Skema DB baru

**Tabel `customer_package_changes`:**

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | | |
| `customer_id` | FK | |
| `customer_service_id` | FK | layanan yang diganti |
| `old_internet_package_id` | FK | |
| `new_internet_package_id` | FK | |
| `billing_period` | string `Y-m` | periode invoice yang kena dampak |
| `effective_date` | date | hari mulai paket baru berlaku (lihat §2.5) |
| `days_in_period` | int | hari riil periode ini (§2.6) |
| `days_old_used` | int | |
| `days_new_used` | int | |
| `prorate_old_amount` | decimal | |
| `prorate_new_amount` | decimal | |
| `total_recomputed` | decimal | `prorate_old + prorate_new` |
| `previously_paid` | decimal | snapshot `sudah_dibayar` invoice periode ini saat diproses |
| `resulting_invoice_id` | FK nullable | invoice yang di-update nominalnya |
| `deposit_mutation_id` | FK nullable | ke `customer_balance_mutations` kalau menghasilkan deposit |
| `created_by` | FK user | |
| `created_at` | | |

**`customer_balance_mutations`:** cek apakah `payment_id` sudah nullable — kalau belum, migration untuk nullable-kan + tambah kolom referensi opsional (`source_type`/`source_id` polymorphic, atau kolom `package_change_id` FK langsung — pilih yang lebih konsisten dengan pola existing di tabel itu, cek dulu kolom `pop_id`/`payment_id` FK style yang dipakai).

### 4.2 Service

`CustomerPackageService::change()` di-rewrite (breaking change terhadap signature sekarang — perlu cek semua caller, `CustomerPackageController` minimal):

1. Tentukan arah (upgrade/downgrade) dari perbandingan `monthly_price` lama vs baru.
2. **Kalau arahnya upgrade** → cek tunggakan periode sebelumnya (§2.9): ada invoice `belum_dibayar`/`sebagian` di `billing_period` < periode berjalan untuk customer ini → tolak (`RuntimeException`, pesan sebut invoice mana yang nunggak), berhenti di sini. Downgrade lewati langkah ini.
3. Ambil invoice periode berjalan (`billing_period` = periode saat ini) milik `customer_service` ini.
4. Kalau invoice periode ini **belum ada** (baru lewat tanggal generate, atau baru aktivasi) → tidak ada yang perlu di-prorate, langsung ganti paket seperti sekarang (efek murni ke periode depan).
5. Kalau **ada** → hitung segmen (ambil riwayat `customer_package_changes` periode ini + segmen baru), hitung `total_invoice_periode`, `sisa_tagih`, `deposit_baru` (§3).
6. Simpan `customer_package_changes` (audit).
7. Update invoice existing (`total_amount`, lalu panggil `recalculateFromPayments()` supaya status ikut update) — **bukan bikin invoice baru**, biar tidak kena/tidak melanggar guard anti-dobel per periode (`InvoiceObserver::creating()`, unique index).
8. Kalau `deposit_baru > 0` → catat kredit lewat jalur baru (§2.3), bukan `CustomerBalanceService::credit()` yang sekarang (butuh `Payment`).
9. Update `customer_services` + `customers.internet_package_id` seperti sekarang.
10. Semua dalam satu `DB::transaction()`.

Tidak perlu langkah approval — ganti paket tetap **langsung efektif** begitu request lolos validasi (keputusan §4.4 poin 4), sama seperti perilaku sekarang.

### 4.3 Test yang wajib ada

- Upgrade, invoice belum dibayar → total invoice ter-update jadi jumlah 2 prorate.
- Upgrade, invoice lunas → invoice lama tetap, muncul invoice/tagihan tambahan sebesar selisih.
- Downgrade, invoice belum dibayar → total invoice ter-update jadi jumlah 2 prorate (lebih kecil dari harga lama).
- Downgrade, invoice lunas → invoice tetap Rp0 tambahan, muncul kredit saldo pelanggan sebesar selisih, dan kredit itu otomatis mengurangi invoice periode berikutnya.
- Invoice `sebagian` (partial paid), upgrade & downgrade — verifikasi formula generalisasi §2.7 (termasuk kasus `sisa_tagih` clamp ke 0 dan sisanya jadi deposit).
- Ganti paket 2x dalam periode yang sama (3 segmen) — verifikasi jumlah hari & prorate benar.
- Ganti paket di hari pertama periode (0 hari paket lama) dan hari terakhir (0 hari paket baru) — edge case tidak boleh division by zero / hari negatif.
- Ganti paket saat invoice periode berjalan masih tipe `awal` (aktivasi, periode custom bukan 1 bulan penuh) — `days_in_period` harus ambil dari panjang periode invoice `awal`, bukan hari kalender bulan itu.
- Guard: ganti paket ke paket yang sama tetap ditolak (perilaku existing, `InvalidArgumentException` — jangan regresi).
- POP scope: `CustomerPackageController` tetap tunduk scope existing (regresi check, bukan test baru).
- **Upgrade diblok kalau ada tunggakan periode sebelumnya** (§2.9) — invoice `belum_dibayar` dan invoice `sebagian` di periode lalu, dua-duanya harus nolak.
- **Upgrade tetap jalan** kalau yang belum lunas cuma invoice periode **berjalan** (bukan tunggakan, itu justru target prorate-nya sendiri).
- **Downgrade tetap jalan** meski ada tunggakan periode sebelumnya (regresi check — jangan ikut kena blok upgrade).

### 4.4 Keputusan (2026-09-15)

1. **`other_fee`/diskon/PPN** — tidak diprorate, dikenakan sekali dari nilai `customer_service` terbaru (§2.4, §3).
2. **Tunggakan** — downgrade tetap boleh jalan; upgrade wajib lunasi dulu tunggakan periode sebelumnya (§2.9).
3. **Siapa yang boleh eksekusi** — diatur lewat RBAC (permission existing `customers.detail.packages.change`, lihat `routes/web.php:848`), bukan hardcode role. Kalau nanti perlu beda hak antara upgrade vs downgrade, pisahkan jadi 2 permission (`...packages.upgrade` / `...packages.downgrade`) — belum perlu sekarang, pakai yang ada dulu.
4. **Approval** — tidak perlu. Ganti paket tetap langsung efektif begitu lolos validasi (termasuk cek tunggakan poin 2), sama seperti alur sekarang.

---

## 5. Dampak ke Modul Lain (perlu dicek saat implementasi, belum dianalisa detail di sini)

- `InvoiceObserver::creating()` — guard anti-dobel per periode, pastikan **update** invoice existing tidak ke-trigger guard ini (guard itu untuk `creating`, bukan `updating`, kemungkinan aman tapi wajib diverifikasi test).
- `GenerateMonthlyInvoicesCommand` — pastikan tidak generate invoice `bulanan` kedua untuk periode yang invoice-nya sudah di-recompute oleh proses ganti paket (`hasActiveSubscriptionInvoiceForPeriod()` harus tetap true).
- Laporan (`InvoiceReportController`, `PaymentReportController`) — kalau ada laporan yang mengasumsikan 1 invoice = 1 harga paket flat sepanjang periode, breakdown per-segmen prorate perlu tampil biar tidak membingungkan admin/finance saat rekonsiliasi.
- `docs/billing-pembayaran/` — begitu diimplementasi, dokumentasi modul ini (README, business-logic, database-schema) wajib diupdate sesuai `docs/DEFINITION_OF_DONE.md` / alur `docs/TASKS.md`.
