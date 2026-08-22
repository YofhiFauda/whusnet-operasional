# Business Logic — Modul Kolektor

Semua logika uang ada di service, bukan controller. Controller tipis: validasi request, resolusi aktor, delegasi, redirect.

| Service | Tanggung jawab |
|---|---|
| `CollectorWorklistService` | Satu-satunya sumber "tagihan mana yang boleh ditagih" — `dueInvoices()` (kolektor, ber-jendela) & `outstandingInvoices()` (admin, tanpa jendela) |
| `CollectorPaymentService` | Pencatatan batch pembayaran: validasi baris, transaksi all-or-nothing, idempotency, notifikasi pop_admin |
| `CollectorBalanceService` | Dua angka uang (saldo & kurang setor) + jejak POP kolektor untuk gerbang visibilitas |
| `CollectorDepositService` | Siklus hidup setoran: setor → cross check → terverifikasi / kurang setor / lebih setor → lunas / hapus buku |
| `CollectorVisitService` | Catatan kunjungan (termasuk yang gagal) + laporan aging |
| `App\Traits\RecordsCollectorBatch` | Bentuk I/O batch pembayaran, dipakai bersama controller admin & kolektor |

---

## 1. Jendela Tagih — "sudah waktunya ditagih"

`config('billing.collector_due_window_days')`, default **7**. Disimpan di config karena tiap POP bisa beda ritme keliling dan penyetelannya tak boleh butuh deploy.

Dua aturan yang gampang tertukar, keduanya disengaja:

1. **Seleksi per PELANGGAN, tampilan per INVOICE.** Pelanggan masuk daftar kalau punya **minimal satu** tagihan `due_date <= hari ini + N`. Begitu masuk, **seluruh** tagihan tertunggaknya ikut tampil — termasuk yang belum jatuh tempo. Kalau tidak begitu, tunggakan lama dan tagihan berjalan pecah ke dua kunjungan, padahal kolektor cuma lewat sebulan sekali.
2. **Jendela, bukan `due_date <= hari ini`.** Jatuh tempo tanggal 20, kolektor lewat tanggal 18 — pelanggan siap bayar tapi tak muncul di layar. Itu kegagalan yang mahal.

**Jendela ini BUKAN pencegah "nagih 2× ke pelanggan sama".** Dobel tagih sudah tertutup struktural: bayar → `remaining_amount` turun → lunas → invoice keluar dari daftar; ditambah penolakan di `CollectorPaymentService::validateRows()` untuk invoice `lunas`/`batal` dan nominal melebihi sisa. Yang dicegah jendela adalah **nagih terlalu awal**.

**Worksheet Admin sengaja TANPA jendela** — admin bukan pengetuk pintu, dia butuh gambaran penuh untuk cross check.

---

## 2. Pencatatan Pembayaran

Satu endpoint logika, dua jalur masuk:

| Jalur | Rute | Sumber `$collector` | Digerbang |
|---|---|---|---|
| Admin bayar mewakili | `POST /payment-batches/{collector}` | route parameter | `payments.create` |
| Kolektor bayar sendiri | `POST /collector-worklist/pay` | `auth()->user()` | `kolektor.pay` |

> **Kenapa rute kolektor tanpa parameter.** Begitu kolektor boleh mengirim id kolektor, kolektor A bisa mencatat pembayaran atas nama kolektor B — dan saldo/setoran keduanya langsung tak bisa dipercaya.

### Jaminan per batch

- **Idempotensi DULU, baru validasi.** Kebalikannya membuat submit ulang dijawab `422 "invoice sudah lunas"` padahal pembayarannya justru berhasil.
- **Satu transaksi, all-or-nothing.** Gagal satu baris ⇒ seluruh batch ditolak, dengan alasan per baris.
- **Validasi dua fase.** Fase cepat tanpa lock (untuk pesan gagal yang enak dibaca) + re-validasi otoritatif di bawah `lockForUpdate` (untuk race dengan jalur bayar lain).
  → Re-validasi itu memeriksa **status DAN nominal**, bukan nominal saja. Alasannya spesifik: `Invoice::recalculateFromPayments()` early-return untuk invoice `batal`, sehingga `remaining_amount`-nya tetap utuh dan pemeriksaan nominal saja lolos. Kalau admin membatalkan invoice di antara dua fase, payment tetap tersimpan, invoice tetap `batal`, dan uangnya masuk saldo kolektor menempel pada tagihan yang sudah mati.
- **1 invoice = 1 payment.** "Bayar semua tunggakan" = banyak payment sekaligus, bukan satu payment dipecah.
- **Notifikasi di LUAR wilayah `try`.** Batas transaksi dan batas penanganan error harus sejajar — lihat §12 R1.

### Batas nominal & tanggal

| Aturan | Alasan |
|---|---|
| `amount` ≤ sisa tagihan | Kelebihan bayar dikembalikan fisik, tidak jadi kredit (§B-8 no. 6 dokumen lama, masih berlaku) |
| `amount` > 0 | `PaymentObserver::creating()` menolak ≤ 0 dari semua jalur |
| `collected_date` ≤ hari ini | Tanggal masa depan merusak pemotongan pendapatan per periode dan melahirkan kunjungan bertanggal besok |

`payment_date` = tanggal posting kantor. `collected_date` = tanggal uang diterima di lapangan. Dua kolom terpisah supaya kolektor yang telat setor tidak menggeser pendapatan ke bulan berikutnya.

### Cicilan

Nominal boleh di bawah sisa. Invoice jadi `sebagian`, sisanya tetap muncul di worklist sampai lunas, dan saldo kolektor bertambah **sebesar uang yang diterima** — bukan sebesar nilai tagihan.

---

## 3. Saldo — angka TURUNAN

```
saldo(X) = Σ payment (collected_by = X, status VALID, collector_deposit_id IS NULL)
```

**Dilarang** membuat kolom `users.saldo` yang di-`+=`/`-=`. Begitu ada payment di-reject/void/dikoreksi, kolom itu tidak ikut berubah dan mulai bohong.

Konsekuensi yang membuktikan pilihan ini benar: **payment ditolak ⇒ saldo turun sendiri**, tanpa kompensasi manual di mana pun.

Pola yang diikuti sudah ada di repo: `Invoice::recalculateFromPayments()` — nilai boleh disimpan, tapi hanya satu fungsi yang boleh menulisnya, dan selalu hitung ulang dari payment.

---

## 4. Setoran — siklus hidup

```
menunggu_verifikasi ──┬─ difference = 0 ─→ terverifikasi        (terminal)
                      ├─ difference < 0 ─→ selisih (kurang setor)
                      └─ difference > 0 ─→ lebih_setor           (terminal)

selisih ──┬─ dilunasi setoran berikutnya ─→ selisih_lunas        (terminal)
          └─ hapus buku Owner ───────────→ dihapus_buku          (terminal)
```

### Setor

Kolektor menyetorkan **seluruh** saldonya — tidak ada setoran parsial ("tidak boleh ada saldo mengendap"). Setelah setor, Saldo Belum Disetor = 0.

Yang disimpan adalah **relasi ke payment**, bukan totalnya. Kolektor boleh terus menagih selagi setoran menunggu verifikasi; penagihan sesudah submit masuk saldo **baru** dan tidak menggeser angka yang sedang dihitung admin.

### Cross check

```
difference = declared_amount − (Σ payment setoran ini + settlement_amount)
```

- `declared_amount` = uang fisik yang **dihitung admin di meja**. Bukan angka yang diketik kolektor: kalau kolektor mendeklarasikan sendiri, dia mengontrol kedua sisi persamaan dan cross check kehilangan artinya.
- `difference ≠ 0` ⇒ **`note` WAJIB**. Selisih tak boleh lewat sebagai angka tanpa penjelasan.
- Pencocokan **invoice** otomatis (tiap payment sudah terikat `invoice_id`). Yang dicocokkan manusia hanya uang fisik vs total sistem — menyuruh admin mencocokkan 1000 invoice/hari mengembalikan masalah yang mau dipecahkan modul ini.

### Pelunasan selisih — field terpisah, WAJIB

```
Total pembayaran hari ini (sistem)  : 280.000
Pelunasan selisih Setoran #12       :  30.000
──────────────────────────────────────────────
Diharapkan                          : 310.000
Uang fisik (declared)               : 310.000
Selisih                             :       0
```

Kalau uang pelunasan cuma dilebur ke `declared_amount`, hasilnya `difference = +30.000` alias **lebih setor** — selisih baru yang menggantung, dan laporan selisih tak pernah nol.

Mendukung **cicilan**: `settled_amount` mengakumulasi pelunasan; sisa kewajiban = kurang setor − `settled_amount`. Status pindah ke `selisih_lunas` hanya setelah sisanya habis.

### Kurang setor vs lebih setor — kenapa status berbeda

Arah uangnya berbeda, jadi konsekuensinya berbeda:

| | Kurang setor (`selisih`) | Lebih setor (`lebih_setor`) |
|---|---|---|
| Artinya | uang perusahaan di tangan kolektor | uang lebih yang diserahkan kolektor |
| Tindak lanjut | ditagih pulang | dikembalikan fisik saat itu juga |
| Status | **bukan terminal** — wajib punya jalan pulang | **terminal** |
| Masuk "Kurang Setor" | ya | tidak |
| Bisa dilunasi setoran berikutnya | ya | tidak relevan |
| Bisa dihapus buku | ya (Owner) | tidak |

Waktu keduanya berbagi status `selisih`, lebih setor nyangkut: badge merah permanen "Kurang setor Rp0", tak bisa dipilih untuk pelunasan, dan satu-satunya jalan keluar adalah hapus buku bernilai nol. **Status yang artinya berbeda tidak boleh berbagi nama.**

### Hapus buku

Titik di mana kerugian diakui. Owner saja, wajib beralasan, dan tetap tunduk pada guard "bukan penyetor sendiri" + guard POP yang sama dengan verifikasi.

---

## 5. Batas koreksi — reject payment

| Kondisi payment | Reject boleh? | Efek |
|---|---|---|
| Belum masuk setoran | ya | saldo turun sendiri |
| Setoran `menunggu_verifikasi` | ya | total setoran ikut berubah — makanya yang disimpan relasi, bukan angka |
| Setoran **sudah terverifikasi** (termasuk `lebih_setor`, `selisih`, `selisih_lunas`, `dihapus_buku`) | **tidak** | ditolak di `PaymentController::reject()` |

Alasannya bukan teknis: setoran terverifikasi adalah dokumen serah-terima uang yang disepakati dua pihak. Mengubahnya belakangan berarti jejak uang bisa dihapus diam-diam. Koreksi yang benar = pembayaran pembalik yang menerbitkan Kurang/Lebih Setor baru; setoran lama tak pernah disentuh.

Implementasinya bersandar pada `DepositStatus::isVerified()` (`!== MENUNGGU_VERIFIKASI`), jadi status baru apa pun otomatis ikut terkunci.

---

## 6. Visit Log — satu-satunya kontrol anti-fraud

Setoran hanya menangkap "laporan jujur, kas tidak jujur". Skenario "laporan tidak jujur" lolos 100% tanpa tabel ini — lihat [README § Konsep Inti](README.md#3-setoran-cuma-menangkap-setengah-kecurangan--sisanya-ditutup-visit-log).

### Dua jalur pengisian, beda kewenangan

| Hasil | Sumber | Catatan |
|---|---|---|
| `bayar` | **otomatis** dari payment yang tersimpan | ditulis di dalam transaksi pembayaran yang sama ⇒ mustahil ada payment tanpa jejak kunjungan |
| `tidak_ada_orang`, `menolak`, `janji_bayar` | diinput kolektor | `VisitResult::manualValues()` |

**`bayar` tidak bisa diketik manual** — kalau bisa, kolektor yang mengantongi uang tinggal mencatat "bayar" tanpa payment.

**`bayar` juga tidak bisa DIHAPUS lewat input manual.** Larangan itu sempat hanya separuh: kolektor tak bisa membuatnya, tapi bisa menimpanya dengan "tidak ada orang" sore hari — dan jejak pembayaran lenyap dari laporan. Sekarang jalur manual menolak menimpa baris `bayar`; koreksi harus lewat pembatalan payment.

### Satu kunjungan = satu pintu, satu hari

Unique `(collector_id, customer_id, visited_at)`. Pelanggan yang melunasi 3 tagihan sekaligus tetap **satu** kunjungan; kalau tidak, "total kunjungan" membengkak dan pola aslinya tertutup.

Bayar sore **menimpa** "tidak ada orang" pagi — yang berlaku hasil akhir hari itu. Atribut basi dibersihkan dua arah: `payment_id` dinolkan saat hasil jadi non-bayar, `note` manual dinolkan saat baris menjadi `bayar`.

### Aturan input

- Pilihan pelanggan dibatasi ke **worklist hari itu** — tak bisa mencatat kunjungan ke pintu yang tak didatangi.
- `visited_at` boleh mundur, **tidak boleh maju**. Mencatat menyusul itu normal (sinyal mati di lapangan); mencatat untuk besok bukan laporan, itu rencana.
- `promised_date` **wajib** untuk `janji_bayar`, dan **dibuang** untuk hasil lain — kalau ikut disimpan, laporan "janji jatuh tempo" memungut baris yang bukan janji.

### Laporan aging

Per pelanggan tertunggak: jumlah kunjungan gagal, total kunjungan, terakhir dikunjungi, nilai tunggakan. Diurutkan dari yang paling sering gagal; ≥3 kunjungan gagal ditandai.

Satu baris belum tentu berarti apa-apa — **pengulangannya** yang layak diaudit.

---

## 7. RBAC & POP Scope

### Gerbang halaman kas kolektor — all-or-nothing

`CollectorWorksheetController::show()` menolak penonton yang tidak membawahi **seluruh** POP jejak uang kolektor. Jejak POP dihitung `CollectorBalanceService::popFootprint()` dari gabungan: pelanggan yang dipegang + payment yang ditagih + setoran yang diserahkan.

Sengaja **bukan** "saring yang boleh": halaman ini menyajikan **angka total** (saldo, kurang setor, nilai setoran). Total yang diam-diam disaring bukan menyembunyikan baris — ia **berbohong**, dan admin menghitung uang fisik dengan patokan yang salah.

Sejalan dengan syarat verifikasi: kalau seorang admin tak berwenang menutup setoran karena ada POP di luar jangkauannya, dia juga tak berkepentingan membaca posisi kasnya.

Kolektor tanpa jejak uang (baru dibuat) boleh dibuka — halaman kosong tak membocorkan apa pun.

### Guard uang lain

| Guard | Lokasi |
|---|---|
| Verifikator ≠ penyetor (berlaku juga untuk Owner) | `CollectorDepositService::assertVerifierIsNotDepositor()` |
| Verifikator/approver harus bisa melihat SELURUH payment setoran | `assertVerifierCanSeeAllPayments()` — dipakai `verify()`, `writeOff()`, **dan setoran target pelunasan** |
| Pelunasan divalidasi ulang terhadap baris terkunci | di dalam `DB::transaction()` `verify()` |
| Nomor setoran digenerate di bawah lock | `lockForUpdate()` saat membaca nomor terakhir — tanpa itu dua setoran bersamaan menabrak unique index, dan kolektor menerima error tepat saat menyerahkan uang |
| Kolektor tak bisa dinonaktifkan saat pegang saldo / punya kurang setor | `UserController::update()` |
| Pelanggan milik kolektor itu **dan** dalam POP scope-nya | `validateRows()`, `dueInvoices()`, `assertCustomerBelongsToCollector()` |

> Guard POP `writeOff()` terasa mubazir hari ini (hanya Owner memegang `collector_worksheet.approve`), tapi permission itu digenerate dan bisa diberikan ke role ber-scope lewat Role Matrix kapan saja. **Menutup kerugian adalah kewenangan yang lebih besar dari memverifikasi, jadi guard-nya tak boleh lebih longgar.**

### Dua lapis scope di worklist kolektor

`collector_id = auth()->id()` **dan** `applyUserScope()`. Assign yang benar menjamin keduanya sejalan, tapi scope POP bisa dipersempit belakangan (kolektor dipindah cabang) tanpa assign lamanya dibersihkan — tanpa lapis kedua, pelanggan cabang lama tetap muncul dan bisa ditagih.

---

## 8. Assign Pelanggan

Dua jalur masuk, **satu method dan satu blok guard**:

| Jalur | Rute | Sumber kolektor |
|---|---|---|
| Panel index | `POST /collector-worksheet/assign` | `collector_id` di body |
| Tab Atur Pelanggan | `POST /collector-worksheet/{collector}/assign` | route parameter |

Guard yang berlaku di kedua jalur: target wajib ber-role `kolektor`, dan **POP tiap pelanggan wajib masuk scope kolektor** — gagal satu pelanggan ⇒ seluruh batch dibatalkan.

> Menyalin guard ke method kedua dilarang. Dua jalur tulis dengan dua salinan guard adalah cara tercepat salah satunya ketinggalan.

---

## 9. Notifikasi

| Peristiwa | Penerima | Sifat |
|---|---|---|
| Batch pembayaran tercatat | `pop_admin` di POP terkait | informatif |
| Setoran menunggu verifikasi | `admin` + `pop_admin` di POP setoran | menuntut tindakan (uang sudah pindah tangan, belum dihitung) |
| Hasil verifikasi setoran | kolektor penyetor | judul mengikuti `status->label()` |
| Hapus buku | kolektor penyetor | menutup kewajibannya — wajib tahu (2026-08-11) |
| Pembayaran ditolak | pencatatnya (kolektor kalau ada) | — |
| Pelanggan di-assign / dilepas | kolektor bersangkutan | rute berubah — wajib tahu sebelum berangkat (2026-08-11) |

**Semua notifikasi dikirim SESUDAH commit dan kegagalannya ditelan** (`report()`). Kabar yang tak terkirim adalah masalah operasional, bukan alasan menganulir uang yang sudah diterima dari pelanggan.

**Hapus buku sebelumnya tidak memberi tahu siapa pun.** Kewajiban kolektor dihapus dan satu-satunya cara dia tahu adalah kebetulan membuka Worklist lalu melihat angkanya berubah sendiri — persis pola "berubah diam-diam" yang modul ini justru ada untuk mencegah.

### Realtime — dua event, satu pasang kanal

Ditambahkan 2026-08-11.

| Event | Aksi yang dibawa | Dipicu dari |
|---|---|---|
| `CollectorDepositUpdated` | `diajukan`, `diverifikasi`, `dilunasi`, `dihapus_buku` | `CollectorDepositService` |
| `CollectorActivityUpdated` | `pembayaran_dicatat`, `pembayaran_ditolak`, `pelanggan_diassign`, `pelanggan_dilepas` | `CollectorPaymentService`, `PaymentController::reject`, `CollectorWorksheetController::assign/release` |

Sebelum ini dua sisi saling menunggu tanpa saling tahu: admin tidak tahu kolektor mana yang baru menyetor atau baru mencatat pembayaran sampai dia memuat ulang Worksheet, dan kolektor tidak tahu setorannya sudah diperiksa atau belum — sementara saldonya bisa naik (mencatat pembayaran) maupun turun (verifikasi, penolakan) kapan saja.

Perubahan **rute** paling berbahaya di lapangan: sebelum ini assign/lepas tidak memberi tahu siapa pun (`grep -c notify` = 0 di controllernya). Pelanggan yang dilepas setelah kolektor berangkat berarti dia menagih orang yang bukan lagi tanggungannya — kunjungan sia-sia, dan kalau uangnya terlanjur diterima, uang yang tak punya tempat mendarat di sistem.

Disiarkan ke **dua kanal sekaligus**, satu per audiens:

| Kanal | Audiens | Otorisasi |
|---|---|---|
| `collector-activity.{popId}` | admin (Worksheet index & detail) | `collector_worksheet.view` + POP scope lewat `EffectiveAccessService` |
| `App.Models.User.{collectorId}` | kolektor (Worklist) | kanal yang sudah dipakai notifikasi — penerimanya persis satu orang |
| `cash-deposits` (2026-08-21) | Owner/atasan (Setoran Kas) — `App\Events\CashDepositUpdated` | `cash_deposit.view`. Global, BUKAN per-POP: pemeriksanya sudah bypass scope POP |
| `App.Models.User.{adminId}` (2026-08-21) | admin penyetor sendiri (Worksheet, setoran kasnya SENDIRI ke Owner) — `App\Events\CashDepositUpdated` | kanal generik yang sama, ditambahkan ke `activityChannels()` |

> Kanalnya sempat bernama `collector-deposits.{popId}`, diganti begitu isinya melampaui setoran. Nama yang berbohong tentang isinya adalah utang yang menyesatkan orang berikutnya — sama kelasnya dengan `firstPageToPng()` yang cuma membaca halaman 1.

**Pembayaran yang TIDAK ditagih kolektor tidak menyiarkan apa pun.** Pembayaran yang diterima langsung di kantor tak punya `collected_by`, jadi tak ada saldo kolektor yang berubah; menyiarkannya cuma bikin bising di layar orang yang tidak berkepentingan. Dijaga test `AktivitasKasKolektorRealtimeTest::test_pembayaran_non_kolektor_tidak_menyiarkan_apa_pun`.

**`ShouldBroadcastNow`, bukan `ShouldBroadcast`/queue.** Alasannya sama dengan `AppNotification` (§6.3/§8) dan `NotificationsMarkedRead`: ini kabar tentang uang yang sedang dihitung dua orang di dua layar. Menggantungnya pada worker berarti menambah satu cara lagi untuk gagal diam-diam. Volumenya pun kecil — beberapa setoran per kolektor per hari — jadi tak ada alasan biaya untuk mengantre.

**Payload SENGAJA tidak membawa saldo.** Saldo adalah angka turunan (§3) — menyiarkannya lewat payload melahirkan sumber kebenaran kedua yang gampang menyimpang dari `CollectorBalanceService`. Dijaga test `SetoranKolektorRealtimeTest::test_payload_tidak_membawa_saldo`.

**Perubahan 2026-08-21 — auto-tambal DOM, bukan cuma aba-aba lagi.** Sebelumnya event ini murni ABA-ABA: klien cuma nampilin toast + bilah "Muat ulang", penyegaran tetap keputusan manusia — alasannya, halaman ini menghitung **uang fisik**, dan angka yang berubah diam-diam saat admin sedang menghitung uang di meja berisiko dia meneruskan hitungan dengan patokan yang salah tanpa sadar.

User (pemilik produk) diberi tahu risiko itu secara eksplisit, lalu **memilih mencabutnya** — minta SPA-like penuh, nol refresh manual/polling, "termasuk pas form kebuka" (ADHOC-45). Sekarang `partials/collector-realtime.blade.php` fetch-ulang halaman & tambal elemen `#live-content` otomatis tiap event masuk (pola sama `refreshFopTaskRow`/`refreshTaskCard`, ADHOC-44) — TANPA syarat "skip kalau modal/form lagi kebuka". Konsekuensinya: kalau admin lagi ngetik nominal di form yang ada di dalam `#live-content` pas event lain masuk (mis. kolektor lain baru setor), ketikannya bisa ketiban data fresh. Ini keputusan sadar yang diambil user, bukan regresi — kalau mau dibalik, ubah di `collector-realtime.blade.php` (titik tunggal, komentar di sana menjelaskan hal yang sama).

Payload TETAP tidak membawa saldo (alasan §1 di atas gak berubah) — client menambal dengan fetch-ulang halaman, bukan menghitung dari payload event.

Dispatch-nya menumpang `safelyNotify()` yang sama dengan notifikasi — kegagalan menyiarkan tidak boleh membatalkan setoran yang uangnya sudah pindah tangan.

---

## 10. Penomoran

| Entitas | Format |
|---|---|
| Setoran | `SETOR-{tahun}-{4 digit}` |
| Payment | `PAY-{YYYYMM}-{4 digit}` (mekanisme lama, tak berubah) |

`deposit_number` dijaga unique index; generator memakai `max + 1` per tahun.

---

## 11. Idempotensi

| Aksi | Kunci | Catatan |
|---|---|---|
| Batch pembayaran | `payment_batches.idempotency_key` | klien menurunkan key dari **tanda tangan baris** yang dikirim |
| Setoran | `collector_deposits.idempotency_key` | key mengandung komponen acak; dua tab tidak lagi berbagi key |

### Key mengidentifikasi ISI KIRIMAN, bukan sesi

Aturan ini lahir dari dua kesalahan berturut-turut, dan keduanya layak diingat karena arah gejalanya berlawanan:

| Bentuk key | Bug yang muncul | Gejala |
|---|---|---|
| Baru tiap panggilan | Retry sesudah kegagalan pasca-commit = batch baru ⇒ **pelanggan terkredit dua kali** | uang lebih |
| Satu key dipakai ulang sampai sukses | Bayar baris A lalu baris B sebelum jawaban A tiba ⇒ B dijawab `already_processed`, **uang baris B tak pernah tercatat** | uang hilang, **toast hijau** |
| **Turunan tanda tangan baris** (sekarang) | — | — |

Tanda tangan = `invoice_id:amount:method:collected_date` tiap baris, disortir dan digabung. Disimpan di `Map` klien, dibuang begitu sukses.

Aman di tiga arah sekaligus:

- **retry kiriman yang sama** → tanda tangan sama → key sama → server menjawab `already_processed`, tak ada pembayaran kedua;
- **kiriman baris lain yang tumpang tindih** → tanda tangan beda → key beda → dua-duanya tercatat;
- **cicilan 50rb kedua di hari yang sama** → tanda tangan lama sudah dibuang setelah sukses → key baru → dihitung sebagai kiriman baru, bukan pengulangan.

Yang berbahaya dari bentuk kedua bukan besarnya kerugian, tapi **gejalanya yang menyerupai keberhasilan**. Bug yang menampilkan toast hijau tidak akan dilaporkan siapa pun.

---

## 12. Kwitansi — sumbu DOKUMEN

Dua sumbu berjalan sendiri-sendiri dan **tidak boleh saling menyandera**:

```
Sumbu KAS     : bayar → setor → cross check → terverifikasi   ← selesai hari itu
Sumbu DOKUMEN : cetak → upload → cocokkan → matched            ← status sendiri
```

Rancangan awal menggantung "Status Verifikasi Kolektor: Berhasil" pada selesainya OCR. Itu salah urut: verifikasi adalah penghitungan **uang fisik** oleh dua orang di meja; menundanya karena berkas gambar gagal dibaca berarti kas perusahaan menunggu mesin.

### Kapan kwitansi boleh terbit — dan siapa yang menerbitkannya

**Kwitansi adalah dokumen KANTOR, bukan dokumen lapangan.** Dua konsekuensi yang dua-duanya ditegakkan kode:

| Aturan | Cara ditegakkan |
|---|---|
| Kolektor **tidak bisa** menerbitkan kwitansi | `collector_worksheet.print` tak diberikan ke role `kolektor` |
| Kwitansi baru boleh dicetak **setelah setorannya diperiksa kantor** | kandidat cetak & endpoint cetak menyaring `collectorDeposit.status != menunggu_verifikasi` |

Selama uangnya masih di tas kolektor — belum disetor, atau sudah disetor tapi belum dihitung admin — kantor belum punya dasar menerbitkan bukti apa pun.

Dua penyaring lain yang menyertainya:

- **Sumbernya `payments`, bukan `invoices`.** Satu baris kandidat berarti uangnya sudah diterima dan tercatat. Pelanggan yang belum bayar tak punya baris `payment`, jadi mustahil ikut tercetak.
- **`payment_status` wajib `valid`.** Pembayaran yang **ditolak** (uang tak pernah sampai kantor) tak boleh dicetak — kertas resmi yang menyatakan pelanggan sudah bayar untuk uang yang kantor sendiri sudah tolak adalah dokumen yang melawan catatannya sendiri.

> **Yang dipakai `status != menunggu_verifikasi`, bukan "harus `terverifikasi`".** Setoran yang berakhir **Kurang Setor** pun sudah selesai diperiksa kantor. Pelanggan yang membayar penuh tidak boleh kehilangan kwitansinya cuma karena kolektor kurang menyetor — itu urusan kantor dengan kolektornya, bukan urusan pelanggan.

### Apa yang kwitansi ini SEBENARNYA lakukan

Kwitansi dicetak **setelah** pembayaran tersimpan. Karena itu ia **bukan kontrol anti-fraud**: kolektor yang menerima uang lalu tak melaporkannya tidak pernah mencetak kwitansi — tak ada berkas yang hilang, tak ada alarm. Yang menangkap kasus itu tetap Visit Log (§6).

Nilai kwitansi = **bukti bagi pelanggan** saat sengketa "saya sudah bayar", dan arsip bagi CS supaya tak perlu bertanya ke kolektor.

### Urutan baca — empat jalur, dari yang paling pasti

Diperbarui 2026-08-11. Urutannya ditentukan di **satu tempat**: `ReceiptNumberExtractor::extractAll()`.

```
1. TEKS   lapisan teks PDF        pasti, gratis, seluruh halaman sekaligus
2. QR     raster halaman + decode untuk berkas yang isinya cuma piksel
3. OCR    Gemini                  cuma kalau QR rusak; mati tanpa GEMINI_API_KEY
4. MANUAL admin memilih sendiri   selalu tersedia, tak boleh disandera mesin
```

**Kenapa TEKS di depan.** Kwitansi mencetak nomornya **dua kali** — sebagai QR *dan* sebagai teks di sampingnya. Dokumen hasil "Print → Save as PDF" membawa teks itu apa adanya, jadi nomornya bisa diambil tanpa render, tanpa DPI, tanpa blur.

**Kenapa QR tetap ada.** Untuk berkas yang memang tidak punya lapisan teks: foto/scan kertas. Di situ nomor tercetak hanyalah gambar tinta, dan QR unggul dibanding mengenali teks — error correction High (~30% modul boleh rusak), tahan miring, dan punya checksum internal sehingga **rusak = gagal baca, bukan salah baca**. Untuk urusan uang, gagal jujur lebih murah daripada benar-tapi-salah.

**Kenapa QR TIDAK pernah diserahkan ke OCR.** Model bahasa buruk membaca matriks QR dan akan mengarang nomor yang formatnya benar — kegagalan paling berbahaya karena lolos gerbang pola. Pembagiannya tetap: QR → decoder khusus, teks tercetak → OCR.

`ReceiptMatchMethod` punya empat nilai (`teks`, `qr`, `ocr`, `manual`) justru supaya kolom itu jujur waktu ada kwitansi salah tempel: metode menentukan seberapa jauh harus ditelusuri.

### Satu LEMBAR memuat banyak kwitansi — tapi satu kwitansi tetap satu halaman

Diperbarui 2026-08-14: tata letak diganti dari grid 2 kolom (8 kwitansi/lembar, digunting) jadi
satu kolom bergaya struk — field lengkap (alamat, invoice, total/sisa tagihan, catatan), **satu
pembayaran = satu halaman A4** (`page-break-after` per kartu). QR + `payment_number` sebagai teks
tetap dicetak di tiap halaman — dua penanda itu tidak boleh hilang, itulah yang dibaca ulang jalur
TEKS/QR/OCR di atas. Konsekuensi paling nyata: mencetak 50 pembayaran sekarang menghasilkan 50
halaman, bukan ~7 lembar gunting — trade-off sadar demi keterbacaan dan format kwitansi yang
konsisten dengan struk (`payments/receipt.blade.php`) dan lembar A4 (`payments/show.blade.php`),
bukan lagi bentuk keempat yang menyimpang sendiri.

Admin yang menekan Print lalu "Save as PDF" tetap menghasilkan **satu berkas untuk banyak
pembayaran** (satu PDF multi-halaman) — itulah bentuk yang dipakai verifikasi massal, cuma
jumlah halamannya sekarang mengikuti jumlah kwitansi, bukan dibagi 8.

Konsekuensinya di data **tidak berubah**: satu baris `payment_receipts` **per nomor**. Model "satu
baris = satu pembayaran" tetap utuh, sehingga UI, POP scope, pencocokan manual, dan `detach`
bekerja tanpa perubahan. Berkas satuan lewat jalur yang sama persis — daftarnya cuma berisi satu.

### Berkas unggahan = arsip LEMBAR, bukan dokumen kwitansi

Seluruh baris dari satu lembar menunjuk **berkas yang sama**. Berkas itu arsip bahwa kertasnya benar tercetak dan diserahkan — bukan "kwitansi pelanggan X".

**Kwitansi satuan tidak disimpan sebagai berkas.** Ia dirender ulang dari data lewat halaman cetak dengan satu `payment_id` — rute yang memang sudah ada:

```
GET /collector-worksheet/{collector}/receipts/print?payment_ids[]=X
GET /payments/{payment}/kwitansi
```

Daftar Berkas Kwitansi menyediakan dua tautan per baris: **Kwitansi** (satuan, dari data) dan **Lembar asal** (unggahan apa adanya).

> **Sempat dicoba memotong lembar jadi PNG per kwitansi (2026-08-11), lalu DIBATALKAN hari itu juga.** Dua alasan, dan keduanya layak diingat sebelum ada yang mencobanya lagi:
>
> 1. **Menebak geometri cetakan sendiri.** Empat pendekatan dicoba — titik tengah antar nomor, jarak kata, celah kosong, grid dari rentang isi — dan tiap kali muncul kasus tepi baru: potongan menelan kartu tetangga, kehilangan sisi kiri/kanannya, atau menyeret URL kaki halaman. Kwitansi ini dicetak oleh sistem sendiri; menebak-nebak posisi hasil cetakan sendiri memang pendekatan yang salah sejak awal.
> 2. **Gambar beku menyimpan klaim yang bisa jadi bohong.** Kwitansi menampilkan `payment_status`. Pembayaran yang kelak **ditolak** akan terus terpampang "Lunas Rp 138.000" di PNG selamanya, sementara halaman yang dirender dari data menampilkan "Ditolak". Untuk audit, arsip yang melawan catatannya sendiri lebih berbahaya daripada tidak ada arsip.
>
> Yang membuktikan pelanggan sudah bayar tetap baris `payments` + `audit_logs` + jejak `payment_batches`/`collector_deposits` — bukan kwitansi. Kwitansi dicetak SESUDAH pembayaran tersimpan; ia turunan, bukan sumber (§12).

Nomor yang payment-nya tidak ada tetap dibuatkan barisnya sendiri berstatus `MISMATCH`: pekerjaan yang tertinggal harus kelihatan, dan **satu nomor bermasalah tidak boleh menggagalkan tujuh lainnya**.

### Angka nyata (lembar 200 kwitansi, 26 halaman, 2,97 MB)

| Jalur | Waktu | Ditemukan | Akurasi |
|---|---|---|---|
| TEKS | **0,64 dtk** | 200 | **200/200 = 100%** |
| QR (halaman penuh) | 37,2 dtk | 0 | **0%** |
| QR (per ubin, tak diterapkan) | ±51 dtk | 8/8 di halaman 1 | ~100% |

Pemrosesan penuh (baca + tulis 200 baris + audit): **1,14 dtk**.

QR gagal total bukan karena QR-nya rusak — pemindaian per ubin menemukan kedelapan QR di halaman 1. `Zxing` menyerah waktu disodori satu halaman A4 berisi 8 QR kecil; decoder itu mengharapkan satu QR yang menonjol.

> ⚠️ **Jangan pernah men-scan ulang hasil cetakan.** Lembar yang di-scan jadi gambar kehilangan lapisan teks, dan yang tersisa cuma QR halaman-penuh — terbukti nol. Ditambah batas `MAX_PAGES = 10` pada raster dan eskalasi 400 DPI yang dilewati untuk dokumen >3 halaman, hasil akhirnya **200 pencocokan manual** (35–70 menit kerja orang). Simpan PDF hasil Print apa adanya.

Batas praktis satu unggahan: ±114 KB/halaman terhadap batas 8 MB/berkas ⇒ ±70 halaman ≈ 560 kwitansi. Di atas itu dipecah.

Yang dicetak di kertas ada **dua**, dan keduanya perlu:

| Penanda | Dibaca oleh | Saat |
|---|---|---|
| QR (SVG, error correction **High**) | mesin | jalur utama |
| `payment_number` sebagai teks polos | OCR, lalu manusia | saat QR sobek/buram/fotokopi |

Error correction High dipilih sadar: kertas kwitansi terlipat, kena air, difotokopi. Level H menoleransi ~30% modul rusak — itu selisih antara pencocokan otomatis dan kerja manual admin.

Isi QR **bukan URL**: kertas yang sudah dicetak tak boleh terikat domain yang bisa berubah, dan pembacanya adalah sistem ini sendiri.

### Satu pembaca gagal tidak menghentikan rantai

Tiap pembaca dibungkus `try/catch`; yang meledak dicatat lalu **dilewati**, bukan menghentikan yang berikutnya. `Zxing\QrReader` melempar untuk gambar yang GD-nya tak bisa buka (mis. WEBP di build tanpa dukungan WEBP) — dan `getimagesize()` tetap mengenali berkas itu, jadi penjaga "ini gambar?" pun lolos. Waktu exception-nya merambat keluar, **OCR yang justru ada untuk kasus "QR tak terbaca" tak pernah dicoba sama sekali**.

Ketersediaan pembaca QR dicek `gd || imagick` — decoder memakai Imagick bila ada dan baru jatuh ke GD. Memeriksa GD saja membuat server ber-imagick-tanpa-gd melewatkan jalur gratis itu diam-diam, dan setiap kwitansi jatuh ke OCR berbayar atau kerja manual tanpa satu pun pesan yang menjelaskan kenapa.

### Kegagalan teknis vs "tidak terbaca"

Dua hal berbeda, dan hanya satu yang layak diulang:

| Keadaan | Hasil | Perlakuan |
|---|---|---|
| Semua pembaca jalan normal, nomornya memang tak ada | `null` | langsung `FAILED` — diulang berapa kali pun hasilnya sama |
| Pembaca meledak (decoder error, API OCR mati) | `ReceiptReadFailure` | **dilempar ulang** selama jatah percobaan tersisa, supaya queue benar-benar mengulang |

Jatah percobaan = `PaymentReceiptService::MAX_ATTEMPTS`, dan `MatchPaymentReceipt::$tries` mengambil angka dari konstanta yang sama — dua angka yang menggambarkan satu aturan tidak boleh ditulis dua kali.

> Sebelumnya service menelan semua exception, jadi `$tries` pada job cuma konfigurasi mati: Gemini 503 sesaat langsung menandai kwitansi `FAILED` pada percobaan pertama.

Satu konsekuensi khusus koneksi queue **`sync`**: job berjalan seketika di dalam request upload, jadi exception-nya akan merambat jadi 500 padahal unggahannya sendiri sudah berhasil. Karena itu `dispatch()` dibungkus `try/catch` + `report()`. Pada Horizon (async) blok itu tak pernah kena.

### Urutan pembacaan & gerbang ganda

```
upload → queue → QR (khanamiryan) ─ gagal ─→ OCR Gemini ─ gagal ─→ FAILED (manusia)
                       │                          │
                       └──── nomor terbaca ───────┘
                                   ▼
                    ada payment dengan nomor itu?
                       ya → MATCHED        tidak → MISMATCH
```

**Dua gerbang, bukan satu.** Gerbang pertama pola `PAY-YYYYMM-NNNN`; gerbang kedua keberadaan payment-nya di database. Nomor yang lolos pola tapi tak menunjuk pembayaran mana pun berakhir `MISMATCH` — **tidak pernah** dicocokkan asal. Inilah yang menahan halusinasi OCR maupun QR salah cetak.

> ⚠️ **Sebelum mengisi `GEMINI_API_KEY`, baca
> [`analisa-risiko-ocr-kwitansi.md`](../plan/kolektor/analisa-risiko-ocr-kwitansi.md).**
> Hasil OCR saat ini diperlakukan sama persis dengan hasil QR — langsung ditempelkan. OCR bisa salah
> baca satu digit, dan kalau nomor hasil salah-baca itu kebetulan ada di DB, berkasnya menempel
> diam-diam ke pembayaran pelanggan lain dengan status hijau "Cocok". Lubang itu **dorman** selama
> OCR mati dan **hidup pada hari key diisi**.

### OCR mati secara default

Tanpa `GEMINI_API_KEY`, `GeminiOcrReceiptNumberReader::isAvailable()` false dan jalur itu dilewati diam-diam. Itu **keadaan normal**, bukan error: modul harus jalan penuh tanpa layanan berbayar, dan tak boleh ada biaya keluar sebelum diputuskan.

Saat aktif, permintaan ke model sengaja **sempit** — satu nomor dengan format tertentu, `temperature: 0`, jawaban `NONE` bila tak terbaca. Semakin sempit pertanyaannya, semakin kecil ruang mengarang; dan hasilnya tetap lewat dua gerbang di atas.

### Override manual wajib ada

Status dokumen tak boleh disandera keberhasilan mesin. QR sobek dan OCR mati adalah kejadian normal, dan kwitansinya tetap harus sampai ke pelanggan yang benar. Admin bisa mencocokkan berkas `MISMATCH`/`FAILED` ke pembayaran mana pun **dalam POP scope-nya**, dan melepas kaitan yang keliru (dicatat di audit log).

### Aturan berkas

| Aturan | Alasan |
|---|---|
| Disk **`local`** (privat), tak pernah `public` | kwitansi memuat nama & nominal pelanggan; akses hanya lewat controller bercek permission + POP scope |
| Checksum SHA-256 **unique** | memilih folder scan dua kali tidak melahirkan dua pekerjaan untuk dokumen identik |
| Maks 8 MB/berkas, 100 berkas/upload | batas praktis; sisanya dibagi beberapa kali |
| Pembacaan di **queue**, `tries = 3` | admin tak menunggu; berkas yang tak terbaca berhenti membebani queue dan segera jadi urusan manusia |
| `pop_id` disalin dari payment saat cocok | berkas ikut POP scope begitu diketahui pemiliknya |

### Siapa boleh melihat berkas mana

| Keadaan berkas | Gerbang |
|---|---|
| Punya `pop_id` (pernah tercocokkan) | POP scope penuh |
| Belum pernah tercocokkan (`pop_id` null) | **hanya pengunggahnya**, atau pemegang akses seluruh POP |

Dua koreksi yang lahir dari review dan penting dipahami:

1. **`detach()` TIDAK menolkan `pop_id`.** Melepas kaitan tidak membuat dokumen kembali "tak diketahui miliknya" — POP-nya sudah pernah diketahui. Menolkannya justru **melebarkan** akses, karena gerbang melewatkan berkas ber-`pop_id` null.
2. **Berkas yatim dibatasi ke pengunggahnya**, bukan ke semua pemegang permission halaman. Tanpa itu, daftar kwitansi membeberkan seluruh berkas yatim di sistem — nama berkas, pengunggah, nomor yang terbaca — ke tiap admin, lintas cabang.

### Batas yang belum tertutup

`payment_number` baru ada **setelah** pembayaran tersimpan, jadi kwitansi dicetak sesudah kolektor submit — **pelanggan tidak menerima apa pun di tempat**. Ini arsip internal, bukan bukti yang dipegang pelanggan saat itu juga. Kalau kelak pelanggan harus pegang bukti seketika, butuh langkah tambahan (kwitansi dibawa kunjungan berikutnya, atau notifikasi WA saat payment tercatat).

---

## 13. Perbaikan Hasil Review — kenapa aturannya begitu

Review 2026-08-08 atas Fase 1–3 menemukan 9 temuan fungsional + 2 sisa dari perbaikannya. Semua sudah ditutup. Yang perlu diingat sebagai **aturan**, bukan sekadar riwayat:

| # | Aturan yang lahir |
|---|---|
| 1 | Halaman yang menyajikan angka total wajib bergerbang **all-or-nothing**, bukan disaring diam-diam |
| 2 | **Batas transaksi dan batas penanganan error harus sejajar** — sesudah commit, tak ada yang boleh mengubah jawaban jadi "gagal" |
| 3 | Data turunan-sistem tak boleh bisa **dibuat maupun dihapus** oleh input manual |
| 4 | Aturan yang sama harus ditegakkan di **semua** jalur masuk (`collected_date` di jalur bayar & kunjungan) |
| 5 | Pemeriksaan di luar transaksi hanya UX; yang otoritatif adalah pemeriksaan ulang **di bawah lock** |
| 6 | Status yang artinya berbeda tidak boleh berbagi nama |
| 7 | Kewenangan yang lebih besar tidak boleh punya guard yang lebih longgar |
| 8 | Paginator di halaman ber-tab wajib `withQueryString()` |
| 9 | Idempotency key tidak boleh bergantung pada waktu render saja |
| R1 | Memperbaiki satu contoh dari sebuah pola **bukan berarti polanya hilang** — sisir semua tempat sejenis |
| R2 | Teks yang menggambarkan status harus **diturunkan dari status**, bukan di-hardcode |

### Tambahan dari review Fase 4 (11 temuan)

| Aturan yang lahir | Dari |
|---|---|
| **Idempotency key mengidentifikasi ISI KIRIMAN**, bukan sesi/tab. Perbaikan yang menukar "uang dobel" jadi "uang hilang" bukan perbaikan | key dipakai bersama antar-permintaan yang sedang jalan |
| Bug yang **gejalanya menyerupai keberhasilan** paling berbahaya — tak akan dilaporkan siapa pun | toast hijau padahal uang tak tercatat |
| Re-validasi di bawah lock harus memeriksa **semua** syarat yang diperiksa fase cepat, bukan sebagiannya | status invoice terlewat, hanya nominal yang dicek ulang |
| Satu komponen yang gagal tidak boleh menghentikan **rantai fallback** — justru fallback itu alasan rantainya ada | pembaca QR meledak ⇒ OCR tak pernah dicoba |
| Pemeriksaan ketersediaan harus mencerminkan **semua** jalur yang dipakai library, bukan satu yang kita ingat | cek GD saja padahal decoder pakai Imagick bila ada |
| Mencabut kaitan tak boleh **melebarkan** akses; informasi yang sudah diketahui jangan dibuang | `detach()` menolkan `pop_id` |
| Data tanpa pemilik tetap butuh gerbang — "belum bisa di-scope" bukan berarti "boleh dilihat semua orang" | daftar berkas yatim bocor lintas cabang |
| Kewenangan menulis ke sebuah catatan tak boleh lebih longgar daripada kewenangan membacanya | setoran target pelunasan tak diotorisasi POP |
| Nomor berurut yang dibaca tanpa lock akan tabrakan tepat di saat tersibuk | `deposit_number` `max + 1` |
| Konfigurasi yang menjanjikan perilaku (`$tries`) harus benar-benar terjadi, atau dihapus | service menelan semua exception |

Rincian mekanisme tiap temuan: [`docs/plan/kolektor/review-fase-1-3.md`](../plan/kolektor/review-fase-1-3.md).
