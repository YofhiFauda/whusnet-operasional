# SELESAI — hasil review Kolektor 2.0 Fase 1–3

> **Update 2026-08-08:** **seluruh temuan #1–#10 sudah diperbaiki** beserta test regresinya
> (1095 test lulus). Dokumen ini dipertahankan sebagai catatan: apa yang salah, kenapa, dan
> keputusan apa yang diambil untuk memperbaikinya.

**Tanggal review:** 2026-08-08
**Cakupan:** seluruh diff modul kolektor yang belum di-commit — service setoran/kunjungan/pembayaran,
dua controller hasil rename, penambahan RBAC, migration, dan lapisan Blade/JS batch-pay.
**Status test saat review:** 1080 test lulus (91 di antaranya khusus kolektor), Pint bersih.

> **Semua temuan di bawah sudah diverifikasi ulang terhadap kode, bukan diterima mentah.**
> Tidak ada yang gugur.
>
> Catatan yang layak direnungkan: **kesepuluh temuan berada di luar jangkauan test yang ditulis
> bersama fiturnya.** Itu sinyal tersendiri — test yang ada menguji alur yang dibayangkan penulisnya,
> bukan alur yang menyimpang. Tiap perbaikan di bawah wajib membawa test regresi yang dinamai sesuai
> gejalanya, bukan sesuai kelasnya.

**Rekomendasi urutan:** P1 (#1–#3) memblokir Fase 4 — ketiganya melanggar jaminan yang sudah ditulis
eksplisit di `analisa-alur-kolektor-2.0.md`, jadi bukan polesan. Lanjut P2 (#4–#6), lalu P3 (#7–#9)
yang murah dan bisa disekalian.

---

## P1 — ✅ SELESAI 2026-08-08

**Ringkas perbaikan:**

| # | Perbaikan | Berkas |
|---|---|---|
| 1 | Gerbang POP all-or-nothing di `show()` + `CollectorBalanceService::isVisibleTo()` / `popFootprint()` | `CollectorWorksheetController`, `CollectorBalanceService` |
| 2 | `notifyPopAdmins()` keluar dari wilayah `try`, dan kegagalannya ditelan + `report()`; klien memakai ulang idempotency key sampai sukses | `CollectorPaymentService`, `RecordsCollectorBatch`, `partials/collector-pay-script.blade.php` |
| 3 | Baris `bayar` tak bisa ditimpa input manual; `payment_id`/`note` basi dibersihkan saat hasil berubah | `CollectorVisitService` |

Test regresi baru: 4 di `CollectorWorksheetTest` (#1), 2 di `CollectorSelfPaymentTest` (#2),
3 di `CollectorVisitLogTest` (#3). Satu test lama Fase 3 diubah ekspektasinya —
`test_admin_from_other_pop_cannot_open_visit_tab_at_all` sekarang menuntut **403 di gerbang**,
bukan 200 dengan data tersaring.

### #1 Kebocoran lintas POP di Worksheet Admin

**Lokasi:** `app/Http/Controllers/CollectorWorksheetController.php` — `show()`, blok `$balance` &
`$deposits`.

**Mekanisme.** `show()` hanya memanggil `authorizeCollector()` (cek target ber-role kolektor). Tidak
ada satu pun pemeriksaan bahwa kolektor itu masuk POP scope penontonnya. Selain itu:

- `CollectorBalanceService::balance()` / `outstandingShortfall()` menyaring `payments` **hanya** lewat
  `collected_by` — tanpa `applyUserScope()`;
- query `$deposits` memakai `CollectorDeposit::where('collector_id', …)` — juga tanpa scope, padahal
  model itu **sudah** `use HasPopScope`.

**Dampak.** `RolePermissionSeeder` memberi `pop_admin` permission `collector_worksheet.view`. Admin
POP A tinggal membuka `/collector-worksheet/{id-kolektor-POP-B}` dan membaca posisi kas kolektor
cabang lain, seluruh riwayat setorannya, catatan verifikasi, sampai alasan hapus buku.

**Kenapa ini serius, bukan sekadar inkonsistensi.** Semua bagian lain di halaman yang sama sudah
di-scope dengan benar (`outstandingInvoices`, `assignedCustomers`, `agingFor`, `historyFor`) — jadi
ini bukan keputusan desain, ini kelupaan. Dan CLAUDE.md menyebutnya larangan keras: *"Setiap query
pelanggan/task/invoice/laporan wajib lewat POP scope."*

**Usulan perbaikan.**
1. Di `show()`: tolak (404/403) kalau `$collector` tak punya irisan POP dengan scope penonton.
2. `CollectorBalanceService` menerima `?User $viewer` dan menerapkan `applyUserScope($viewer)` pada
   query `payments` & `collector_deposits` — dengan catatan: **saldo yang dilihat kolektor sendiri
   tetap harus utuh**, jadi viewer default = pemilik saldo, bukan selalu `auth()`.
3. Query `$deposits` di controller ikut `applyUserScope()`.

**Hati-hati saat memperbaiki:** jangan sampai penyempitan scope membuat *saldo kolektor sendiri*
ikut terpotong — itu mengubah angka uang, bukan sekadar menyembunyikan baris.

---

### #2 Pembayaran dobel setelah kegagalan notifikasi

**Lokasi:** `app/Traits/RecordsCollectorBatch.php` (blok `try` seputar `record()`) +
`app/Services/CollectorPaymentService.php` (`notifyPopAdmins()` dipanggil **setelah**
`DB::transaction()` ditutup).

**Mekanisme.**
1. Transaksi commit — payment sudah tersimpan permanen.
2. `notifyPopAdmins()` jalan **di luar** transaksi tapi **masih di dalam** `try/catch` pemanggilnya.
3. Satu exception dari dispatch notifikasi (broadcast, queue, relasi user) → response
   `422 {"success": false, "message": "Batch ditolak: …"}`.
4. Front-end menampilkan "Pembayaran gagal dicatat", baris tetap di layar.
5. `cbGenerateKey()` membuat **idempotency key baru setiap panggilan**, jadi retry kolektor bukan
   submit ulang yang sama — ia batch baru.

**Dampak.** Payment kedua tersimpan. Untuk pelunasan penuh, validasi menolak (invoice sudah lunas) —
jadi gejalanya cuma membingungkan. Untuk **cicilan** (sisa masih ≥ nominal), duplikatnya lolos dan
pelanggan terkredit dua kali. Saldo kolektor ikut menggelembung.

**Catatan pola.** Ini kelas kesalahan yang sama dengan bug idempotency di Fase 1 (cek idempotency
sempat jatuh sesudah validasi), muncul dari sisi berbeda: **batas transaksi dan batas penanganan
error tidak sejajar.**

**Usulan perbaikan.** Keluarkan `notifyPopAdmins()` dari wilayah yang dijaga `try`, ATAU tangkap
hanya `RuntimeException` tingkat-baris. Pertimbangkan juga membungkus notifikasi dengan
`report($e)`-and-continue: kegagalan mengirim kabar tak boleh pernah membatalkan uang yang sudah
tercatat.

---

### #3 Jejak kunjungan `bayar` bisa dihapus lewat input manual

**Lokasi:** `app/Services/CollectorVisitService.php` — `logManualVisit()` dan `recordPaid()` berbagi
`upsertVisit()`.

**Mekanisme.** `upsertVisit()` memakai ulang baris (kolektor, pelanggan, hari) — memang disengaja,
supaya satu kunjungan tidak tercatat berkali-kali. Tapi atribut yang ditimpa berbeda per jalur:

| Jalur | Atribut yang ditulis | Yang tertinggal basi |
|---|---|---|
| `logManualVisit()` | `result`, `promised_date`, `note` | **`payment_id` tidak dibersihkan** |
| `recordPaid()` | `result`, `promised_date`, `payment_id` | `note` manual tidak dibersihkan |

Jadi: kolektor menagih X pagi (`result=bayar`, `payment_id=P`), lalu sore mengirim kunjungan manual
untuk X → baris berubah jadi `tidak_ada_orang` **dengan `payment_id=P` masih menempel**.

**Dampak.** Penanda `bayar` yang dijanjikan tiga tempat sekaligus — `VisitResult::manualValues()`,
docblock enum, dan komentar migration — sebagai "tidak bisa dibuat maupun dihapus lewat input
manual", ternyata **bisa dihapus**. Laporan aging menghitung hari itu sebagai kunjungan gagal, dan
riwayat admin menampilkan baris yang saling bertentangan: "Tidak Ada Orang • PAY-000123".

Ini menyerang tepat di jantung satu-satunya kontrol anti-fraud modul ini (§12).

**Usulan perbaikan.** Jalur manual menolak menimpa baris yang sudah `bayar` (hasil sah = uang sudah
masuk; kalau memang ada koreksi, itu urusan pembatalan payment, bukan pengetikan ulang kunjungan).
Minimal: bersihkan `payment_id` saat hasil berubah dari `bayar`, dan bersihkan `note` manual saat
baris menjadi `bayar`.

---

## Review Fase 4 (2026-08-08, putaran ketiga) — 11 temuan, semua ditutup

Diverifikasi ulang terhadap kode; tak ada yang gugur. **1119 test lulus** setelah perbaikan.

| # | Temuan | Tingkat | Perbaikan |
|---|---|---|---|
| 1 | **Idempotency key dipakai bersama antar-permintaan yang sedang jalan** — kolektor tekan Bayar baris A lalu baris B sebelum jawaban A tiba; keduanya memakai key sama, B dijawab `already_processed`, toast hijau muncul, **uang baris B tak pernah tercatat** | **HIGH** | Key diturunkan dari **tanda tangan baris**, disimpan di `Map`. Retry kiriman sama → key sama; kiriman baris lain → key sendiri |
| 2 | Re-validasi di bawah lock tak memeriksa `invoice_status`; `recalculateFromPayments()` early-return untuk `batal`, jadi payment mendarat di tagihan mati dan masuk saldo kolektor | medium | Status diperiksa ulang di dalam transaksi |
| 3 | Satu pembaca yang melempar (WEBP di GD tanpa dukungan WEBP) menghentikan rantai — **OCR yang justru ada untuk kasus itu tak pernah dicoba** | medium | `try/catch` per pembaca lalu lanjut; kegagalan teknis diagregasi jadi `ReceiptReadFailure` |
| 4 | `isAvailable()` cuma cek GD padahal decoder memakai Imagick bila ada — server imagick-tanpa-gd melewatkan jalur QR gratis, diam-diam | medium | `gd \|\| imagick` |
| 5 | `detach()` menolkan `pop_id`, dan gerbang akses melewatkan berkas ber-`pop_id` null — melepas kaitan justru **melebarkan** akses | medium | `pop_id` dipertahankan; gerbang berkas yatim diperketat ke pengunggahnya |
| 6 | Daftar kwitansi menampilkan **seluruh berkas yatim di sistem** ke tiap admin, lintas cabang | low | Tercocokkan → POP scope; belum tercocokkan → hanya milik pengunggah |
| 7 | Setoran target pelunasan tak pernah diotorisasi POP — admin cabang B bisa menulis ke setoran cabang A | low | `assertVerifierCanSeeAllPayments()` juga untuk target |
| 8 | `deposit_number` dibuat `max + 1` tanpa lock — dua setoran bersamaan tabrakan unique index, 500 tepat saat uang diserahkan | low | `lockForUpdate` saat membaca nomor terakhir |
| 9 | `$tries`/`$backoff` konfigurasi mati — service menelan semua exception, job tak pernah retry | low | Kegagalan teknis dilempar ulang selama jatah percobaan tersisa; `MAX_ATTEMPTS` jadi satu sumber angka |
| 10 | File `NUL` nyasar | low | Dihapus |
| 11 | Judul ADHOC-22 di `TASKS.md` bilang "TERBUKA" padahal tabelnya "Done" | low | Diselaraskan |

**Temuan #1 adalah akibat langsung dari perbaikan #2 putaran sebelumnya.** Memperbaiki "retry menyimpan pembayaran dua kali" dengan cara berbagi satu key ternyata melahirkan kebalikannya: pembayaran kedua **hilang tanpa jejak**, dan lebih berbahaya karena gejalanya adalah toast hijau. Pelajarannya: idempotency key harus mengidentifikasi **isi kiriman**, bukan sesi atau tab.

**Temuan tambahan saat memperbaiki #3/#9:** pada koneksi queue `sync`, exception job merambat ke request upload sehingga endpoint 500 padahal berkasnya sudah tersimpan. Dispatch dibungkus `try/catch` + `report()` — pada Horizon (async) blok itu tak pernah kena.

---

## Review atas perbaikan (2026-08-08, putaran kedua)

Perbaikan #1–#9 diperiksa ulang. Sembilan-sembilannya terbukti memegang janjinya, **tapi review
ini menemukan dua sisa yang lahir justru dari cara memperbaikinya** — keduanya sudah ditutup:

### R1 — Notifikasi masih di DALAM transaksi pada jalur setoran (medium)

Perbaikan #2 memindahkan notifikasi keluar dari wilayah `try` **di jalur pembayaran saja**. Pola
identiknya tertinggal di `CollectorDepositService`: `notifyVerifiers()` dipanggil di dalam
`DB::transaction()` milik `submit()`, dan `notifyCollectorOnVerification()` di dalam transaksi
`verify()`.

Dua akibatnya sama buruk:
- broadcast/queue mati ⇒ **kolektor tak bisa menyerahkan uangnya sama sekali**, karena exception
  dispatch me-rollback seluruh setoran;
- kalau dispatch sempat berhasil lalu transaksi rollback karena hal lain ⇒ admin menerima kabar
  setoran yang **tak pernah ada**.

Ditutup: notifikasi pindah ke sesudah commit, dibungkus helper `safelyNotify()` (satu tempat, dipakai
semua jalur — kalau tiap pemanggil menulis `try/catch` sendiri, cepat atau lambat ada yang lupa,
persis yang barusan terjadi). Test regresi:
`test_notification_failure_blocks_neither_deposit_nor_verification`.

**Pelajaran yang layak dicatat:** memperbaiki satu contoh dari sebuah pola bukan berarti polanya
hilang. Waktu #2 ditutup, seharusnya langsung disisir semua tempat yang mengirim notifikasi di
sekitar transaksi uang, bukan hanya yang dilaporkan.

### R2 — Judul notifikasi masih menyebut "SELISIH" (low)

Sisa dari #6: `notifyCollectorOnVerification()` menyusun judul dari string hardcode, jadi kolektor
yang menyerahkan uang **lebih** menerima kabar berjudul "SELISIH" dan mengira dirinya kurang.
Ditutup: judul diambil dari `$deposit->status->label()`, ikut statusnya.

### Yang diperiksa dan terbukti aman

- `match` atas `DepositStatus` sudah exhaustive di enum, service, dan view — penambahan
  `LEBIH_SETOR` tidak meninggalkan `UnhandledMatchError`.
- `isVerified()` memperlakukan `LEBIH_SETOR` sebagai terverifikasi ⇒ guard reject payment (#3 Fase 2)
  ikut berlaku tanpa perubahan.
- `outstandingShortfall()`, `openShortfallDeposits()`, guard nonaktifkan kolektor, dan daftar pending
  di worklist semuanya menyaring `SELISIH` saja ⇒ lebih setor tidak lagi memunculkan kewajiban semu.
- Deny-by-default `isVisibleTo()`: penonton tanpa scope ⇒ `getAllowedPopIds()` kosong ⇒ ditolak.
- Pemakaian ulang idempotency key di klien aman untuk kegagalan validasi (key tak pernah terpakai)
  maupun kegagalan pasca-commit (server menjawab `already_processed`).

### Sisa yang SENGAJA tidak diubah

- **`index()` masih menampilkan seluruh kolektor lintas POP** (nama + jumlah pelanggan yang sudah
  ber-scope). Klik ke kolektor luar scope kini 403. Yang bocor tinggal nama kolektor cabang lain —
  bukan uang. Mengubahnya menyentuh keputusan produk ("apakah admin cabang boleh tahu daftar kolektor
  perusahaan"), jadi tidak diputuskan sepihak di dalam perbaikan bug.
- **Urutan penguncian di `verify()`** (deposit lalu target pelunasan) secara teori bisa deadlock kalau
  dua setoran saling melunasi bersamaan. Tidak mungkin terjadi: target pelunasan wajib berstatus
  `SELISIH` sementara yang diverifikasi wajib `MENUNGGU_VERIFIKASI`, jadi tak ada siklus.

---

## P2 / P3 — ✅ SELESAI 2026-08-08

| # | Perbaikan | Berkas |
|---|---|---|
| 4 | `rows.*.collected_date` diberi `before_or_equal:today` — menyamakan aturan dengan jalur kunjungan | `RecordsCollectorBatch` |
| 5 | Pelunasan divalidasi ulang **terhadap baris terkunci** di dalam transaksi; `applySettlement()` tak lagi mengunci ulang salinan kedua | `CollectorDepositService` |
| 6 | Status baru `DepositStatus::LEBIH_SETOR`, terminal. Verify jadi tiga cabang: `= 0` terverifikasi, `< 0` kurang setor, `> 0` lebih setor | `DepositStatus`, `CollectorDepositService`, kedua view |
| 7 | `writeOff()` memakai `assertVerifierCanSeeAllPayments()` — guard POP sama dengan `verify()` | `CollectorDepositService` |
| 8 | `withQueryString()` pada `$invoices` & `$assignedCustomers` | `CollectorWorksheetController` |
| 9 | Idempotency key setoran ditambah `Str::random(8)` | `collector-worklist/index.blade.php` |

**Keputusan #6 — dipilih opsi (a), status sendiri.** Alasannya arah uangnya berbeda, jadi
konsekuensinya berbeda: kurang setor adalah kewajiban yang harus ditagih pulang, lebih setor adalah
uang yang dikembalikan fisik saat itu juga. Karena `LEBIH_SETOR` terminal, ia otomatis keluar dari
daftar pending kolektor, tak bisa dipilih untuk pelunasan, dan hapus buku menolaknya —
tiga gejala buntu itu hilang sekaligus tanpa penanganan khusus di tiap tempat.

Efek samping yang disengaja: label `SELISIH` diubah dari "Selisih" jadi **"Kurang Setor"**. Setelah
lebih setor punya nama sendiri, "Selisih" jadi ambigu.

Test regresi baru: 3 di `CollectorDepositTest` (#5, #6, #7), 1 di `CollectorSelfPaymentTest` (#4),
1 di `CollectorWorksheetTest` (#8). #9 tak diberi test otomatis — nilainya dirender Blade dan
keacakannya tak bisa diuji bermakna tanpa menguji `Str::random()` itu sendiri.

---

## P2 — Penting (rincian asli)

### #4 `collected_date` tanpa batas atas

**Lokasi:** `app/Traits/RecordsCollectorBatch.php` — `'rows.*.collected_date' => 'required|date'`.

Kolektor bisa mengirim `2030-01-01`. Nilai itu mendarat di `payments.collected_date` (merusak
pemotongan pendapatan per periode — padahal justru itu alasan kolom ini dibuat, §B-8 no. 8) dan
diteruskan ke `CollectorVisitService::recordPaid()`, melahirkan baris `collector_visits` bertanggal
masa depan.

**Yang bikin ini jelas cacat, bukan pilihan:** jalur kunjungan **melarang persis hal ini** —
`CollectorVisitController` memakai `before_or_equal:today` dengan alasan tertulis *"itu bukan laporan
kunjungan lagi, itu rencana"*. Dua jalur, satu aturan, cuma satu yang menegakkan.

**Perbaikan:** tambahkan `before_or_equal:today`.

---

### #5 Race pada pelunasan selisih

**Lokasi:** `app/Services/CollectorDepositService.php` — `assertSettlementIsValid()` (di luar
transaksi) vs `applySettlement()` (di dalam).

`assertSettlementIsValid()` memeriksa status & sisa kewajiban pada baris **tak terkunci**, sebelum
`DB::transaction()` dibuka. `applySettlement()` memang mengunci target, tapi langsung
`settled_amount += $amount` **tanpa memeriksa ulang** apa pun.

**Dua akibat:**
1. Dua verifikasi bersamaan yang melunasi selisih yang sama bisa over-credit.
2. Kalau target dihapus-buku di sela pemeriksaan dan transaksi, uang pelunasan masuk ke baris
   `DIHAPUS_BUKU` — dan `outstandingShortfall()` mengembalikan `0.0` tanpa syarat untuk status itu.
   **Uangnya lenyap dari semua laporan.**

**Perbaikan:** jalankan ulang seluruh assertion terhadap baris yang sudah dikunci, di dalam
transaksi. Pemeriksaan di luar tetap boleh ada sebagai UX cepat — tapi bukan yang otoritatif. Pola
ini sudah dipakai benar di `CollectorPaymentService` (validasi cepat + re-validasi di bawah
`lockForUpdate`); di sini belum.

---

### #6 "Lebih setor" nyangkut tanpa jalan keluar

**Lokasi:** `app/Models/CollectorDeposit.php::outstandingShortfall()` +
`CollectorWorklistController` + `resources/views/collector-worklist/index.blade.php`.

`difference` positif (uang fisik melebihi catatan) tetap berstatus `SELISIH`, tapi
`outstandingShortfall()` mengembalikan 0 — memang sengaja, karena lebih setor bukan utang kolektor.
Konsekuensinya tidak dituntaskan:

- `$pendingDeposits` memasukkan semua `SELISIH`, dan view merender cabang non-pending sebagai badge
  merah permanen **"Kurang setor Rp0"** di worklist kolektor;
- setoran itu tak bisa dipilih untuk pelunasan (`openShortfallDeposits()` memfilter `> 0`);
- satu-satunya jalan keluar: hapus buku Owner atas nilai nol — yang secara semantik omong kosong.

**Perbaikan (pilih satu, keputusan produk):**
- (a) status sendiri untuk lebih setor, mis. `LEBIH_SETOR`, yang langsung terminal karena uangnya
  dikembalikan fisik saat itu juga; atau
- (b) tetap `SELISIH` tapi keluarkan dari daftar "pending" kolektor dan beri label yang benar.

Rekomendasi: **(a)** — status yang artinya berbeda sebaiknya tidak berbagi nama.

---

## P3 — Kecil, murah, sekalian saja

### #7 `writeOff()` tanpa guard POP
`CollectorDepositService::writeOff()` hanya memeriksa status + `assertVerifierIsNotDepositor()`,
sementara `verify()` juga memanggil `assertVerifierCanSeeAllPayments()`. Aman **hari ini** karena
hanya Owner memegang `collector_worksheet.approve` (lewat wildcard `*`) — tapi permission-nya
digenerate dan bisa diberikan lewat Role Matrix kapan saja. Begitu diberikan ke role ber-scope, role
itu bisa menghapus-buku kerugian atas setoran yang isinya pembayaran cabang yang tak boleh dia lihat.

### #8 Paginasi menendang user pindah tab
`$assignedCustomers` dan `$invoices` di `CollectorWorksheetController::show()` tidak memanggil
`withQueryString()` (`$aging`, `$visitHistory`, `$deposits` sudah). Klik halaman 2 di tab
"Atur Pelanggan" menjatuhkan `tab=assign`, `show()` jatuh ke default `pembayaran`, dan user terlempar
ke tab lain di tengah paginasi.

### #9 Idempotency key setoran bergantung detik render
`resources/views/collector-worklist/index.blade.php`:
`'deposit-'.auth()->id().'-'.now()->timestamp`. Dua tab worklist yang dibuka pada detik yang sama
berbagi key. Setelah tab A menyetor, submit tab B kena early-return
`CollectorDepositService::submit()` dan controller melaporkan *"Setoran … terkirim, saldo Anda kembali
nol"* — padahal tak ada yang disetor dan pembayaran yang lebih baru tetap menggantung. Jalur JS sudah
memakai komponen acak; jalur ini belum.

---

## Kebersihan repo (di luar temuan fungsional)

- File `NUL` nyasar di root — artefak redirect Windows dari sesi kerja. **Sudah dihapus 2026-08-08.**
- `storage/framework/testing/views/*.php` (hasil kompilasi Blade saat test) **ter-track di git** dan
  ikut muncul di tiap diff. Mestinya masuk `.gitignore`, bukan ikut di-review. Belum diubah — di luar
  scope modul kolektor, butuh persetujuan karena menyentuh berkas bersama.

---

## Ringkasan status

| # | Temuan | Prioritas | Status |
|---|---|---|---|
| 1 | Kebocoran lintas POP di Worksheet Admin | P1 | **Selesai 2026-08-08** |
| 2 | Pembayaran dobel setelah notifikasi gagal | P1 | **Selesai 2026-08-08** |
| 3 | Jejak kunjungan `bayar` bisa dihapus manual | P1 | **Selesai 2026-08-08** |
| 4 | `collected_date` tanpa batas atas | P2 | **Selesai 2026-08-08** |
| 5 | Race pelunasan selisih | P2 | **Selesai 2026-08-08** |
| 6 | "Lebih setor" nyangkut | P2 | **Selesai 2026-08-08** — status `LEBIH_SETOR` |
| 7 | `writeOff()` tanpa guard POP | P3 | **Selesai 2026-08-08** |
| 8 | Paginasi menendang pindah tab | P3 | **Selesai 2026-08-08** |
| 9 | Idempotency key setoran per-detik | P3 | **Selesai 2026-08-08** |
| R1 | Notifikasi di dalam transaksi setor & verifikasi (sisa pola #2) | medium | **Selesai 2026-08-08** |
| R2 | Judul notifikasi hardcode "SELISIH" (sisa #6) | low | **Selesai 2026-08-08** |
| 10 | File `NUL` nyasar | — | Selesai |

**Tidak ditemukan masalah** pada: pemetaan RBAC (triplet feature/action/permission konsisten,
`ActionSeeder` memakai bentuk enum yang sama dengan entri lama), pemisahan rute admin `{collector}`
vs rute self-service, dan binding `?User $collector = null` pada dua rute assign (resolusi `null`-nya
benar karena parameter punya nilai default).
