# Analisa Operasional OCR Gemini — rate limit, cooldown, dan antisipasi galat

**Status:** rancangan, **belum diimplementasikan**. Dicatat 2026-08-12 atas permintaan user.
**Terkait:** ADHOC-25 (`analisa-risiko-ocr-kwitansi.md`) — **dokumen itu soal KEBENARAN, dokumen ini soal OPERASIONAL.** Keduanya wajib selesai sebelum `GEMINI_API_KEY` diisi.

---

> ## ⚠️ Pertanyaan yang harus dijawab SEBELUM key dibuat
>
> Bukan "berapa rate limit-nya", melainkan: **boleh atau tidak berkas kwitansi — berisi nama,
> CID, alamat, dan nominal pelanggan — dikirim ke API pihak ketiga?** Satu panggilan atas lembar
> borongan membawa data **200 pelanggan** sekaligus (§3A.1). Kalau jawabannya tidak, seluruh
> jalur OCR ditutup dan sisa dokumen ini tidak relevan.

## 0. Ringkasan satu paragraf

OCR Gemini saat ini dipanggil tanpa rem, tanpa pembedaan jenis galat, dan tanpa cooldown. Selama key kosong semuanya dorman. Begitu key diisi, empat masalah operasional hidup sekaligus: kuota habis tampil sebagai "berkas tidak terbaca" (salah diagnosis), header `Retry-After` diabaikan, galat permanen diulang tiga kali per berkas, dan unggahan borongan menembak API tanpa jeda. Rancangan di bawah menutup keempatnya dengan lima lapis, memakai ulang perilaku "OCR mati = normal" yang sudah ada — bukan membangun jalur baru.

---

## 1. Kondisi kode saat ini (per 2026-08-12)

| Aspek | Nilai | Berkas |
|---|---|---|
| Timeout HTTP | 30 dtk (`GEMINI_TIMEOUT`) | `config/services.php:70` |
| Retry HTTP | **tidak ada** | `GeminiOcrReceiptNumberReader::read()` |
| Penanganan gagal | semua `failed()` → `RuntimeException` | idem |
| Percobaan job | `tries = 3` (`MAX_ATTEMPTS`) | `MatchPaymentReceipt`, `PaymentReceiptService:33` |
| Backoff | **30 dtk tetap** | `MatchPaymentReceipt::$backoff` |
| Konkurensi | 1 lokal, **4 produksi** | `config/horizon.php` `supervisor-kwitansi` |
| Rate limit | **tidak ada** | — |
| Cooldown / pemutus arus | **tidak ada** | — |
| Pagu harian / biaya | **tidak ada** | — |
| Gerbang aktif | `isAvailable()` = ada tidaknya `GEMINI_API_KEY` | `GeminiOcrReceiptNumberReader` |

**Yang sudah benar dan tidak boleh dirusak:**

- OCR adalah jalur **ketiga**, sesudah lapisan teks PDF dan QR (`ReceiptNumberExtractor`). Kwitansi hasil cetak sistem terbaca dari lapisan teks — terukur **200/200** — dan **tidak pernah** menyentuh OCR. OCR hanya kena berkas hasil scan/foto tanpa lapisan teks yang QR-nya juga gagal.
- Reader yang `isAvailable()` false **dilewati diam-diam**. "OCR mati" sudah diperlakukan sebagai keadaan normal di seluruh rantai: berkas jatuh ke penanganan manusia, tak ada error, tak ada yang otomatis terjadi. Rancangan di bawah menumpang pada semantik ini.
- Kegagalan teknis dilempar (bukan `null`) supaya bisa dibedakan dari "tidak terbaca" — `ReceiptNumberExtractor::extract()` sengaja membedakan keduanya.

---

## 2. Empat lubang

### 2.1 Kuota habis tampil sebagai "berkas tidak terbaca"

Jalurnya hari ini: HTTP 429 → `RuntimeException('Gemini OCR gagal: HTTP 429')` → job retry 3× jeda 30 dtk → `PaymentReceiptService::match()` menandai `FAILED` dengan `last_error` berisi teks itu.

Di layar admin, `FAILED` berarti satu hal: **berkas ini perlu dikerjakan manusia**. Padahal berkasnya baik-baik saja — yang habis kuotanya, dan lima menit lagi kemungkinan besar terbaca. Dua akibat:

1. Admin mengerjakan manual sesuatu yang sebenarnya otomatis.
2. Kwitansi itu **tidak pernah dicoba ulang** — jatah percobaan sudah habis dalam 90 detik.

Kelas kegagalan yang sama dengan yang berulang di modul ini: **status yang salah menggambarkan keadaan**.

### 2.2 `Retry-After` diabaikan

Google mengirim header `Retry-After` pada 429. Kita memakai `backoff` tetap 30 detik dan menghantam lagi — kemungkinan besar kena 429 lagi, menghabiskan jatah percobaan tanpa satu pun panggilan yang berpeluang berhasil.

### 2.3 Galat permanen diulang

401 (key salah), 403 (API belum diaktifkan), 404 (nama model keliru) **tidak akan pernah** berhasil kalau diulang. Hari ini masing-masing diulang 3×.

Unggahan 100 berkas dengan key salah ketik = **300 panggilan sia-sia**, lalu 100 kwitansi merah yang penyebabnya satu baris `.env`. Tidak ada satu pun pesan yang menyebut "API key ditolak" secara menonjol.

### 2.4 Tidak ada rem

`maxProcesses: 4` di produksi. Unggahan 100 scan menghasilkan 100 job; empat worker menembak Gemini serentak tanpa jeda. Kuota free tier untuk model flash berkisar **belasan permintaan per menit** (angka pastinya wajib diperiksa ulang di dashboard Google saat key dibuat — jangan dipatok dari ingatan). Artinya 429 dalam hitungan detik, lalu §2.1 terjadi berantai.

---

## 3. Rancangan — lima lapis

Prinsip: **rem sebelum menembak, bukan menambal setelah gagal.** Panggilan yang tidak jadi dilakukan tidak pernah menghasilkan galat, tidak membakar kuota, dan tidak menyentuh status kwitansi.

### Lapis 1 — Rate limiter di depan job

Middleware antrean `RateLimited` (varian Redis) pada `MatchPaymentReceipt`, dengan limiter bernama `gemini-ocr`:

```
GEMINI_RATE_LIMIT=10      # panggilan per menit, DI BAWAH kuota tier
GEMINI_RATE_DECAY=60      # detik
```

Job yang kena limit **dilepas kembali ke antrean dengan delay**, bukan digagalkan — `attempts` kwitansi tidak naik, status tidak berubah, admin tidak melihat apa pun. Ini yang membuat "kuota habis → tunggu & coba lagi otomatis" (keputusan D7) bekerja tanpa jalur khusus.

> **Batas ini melindungi kuota, bukan sekadar menghitung.** Setel di bawah kuota nyata, bukan pas: worker lain, percobaan ulang, dan panggilan dari lingkungan lain berbagi kuota yang sama.

### Lapis 2 — Klasifikasi galat

Dua kelas exception menggantikan satu `RuntimeException` generik:

| HTTP / kondisi | Kelas | Tindakan |
|---|---|---|
| 429 | `GeminiRateLimited` | `release(Retry-After ?? backoff eksponensial)`, **tidak** menghitung percobaan |
| 500, 502, 503, 504 | `GeminiTemporarilyUnavailable` | idem, backoff eksponensial |
| timeout koneksi | `GeminiTemporarilyUnavailable` | idem |
| 400 | `GeminiRequestRejected` | **tidak** retry — permintaan kita yang salah bentuk; catat & laporkan |
| 401, 403 | `GeminiNotAuthorized` | **tidak** retry, **aktifkan cooldown panjang** (§Lapis 3) |
| 404 | `GeminiModelNotFound` | idem 401/403 |

Yang penting bukan nama kelasnya, melainkan **pemisahan "coba lagi nanti" dari "percuma diulang"**. Hari ini keduanya sama.

### Lapis 3 — Cooldown / pemutus arus

Satu kunci cache: `gemini:cooldown_until`.

| Pemicu | Durasi | Alasan |
|---|---|---|
| 401 / 403 / 404 | **sampai dinyalakan manual** | konfigurasi salah; menunggu tidak memperbaikinya |
| Pagu harian tercapai | sampai tengah malam | kuota harian memang berbasis hari |
| 429 berulang N kali beruntun | 15 menit | menghindari badai permintaan |

**Cara memasangnya sengaja minimal:** `isAvailable()` mengembalikan `false` selama cooldown aktif. Tidak ada jalur baru sama sekali — `ReceiptNumberExtractor` sudah melewati reader yang tidak tersedia, dan seluruh rantai sudah memperlakukan "OCR mati" sebagai keadaan normal. Berkas jatuh ke penanganan manusia persis seperti hari ini tanpa key.

Cooldown wajib punya **jalan keluar yang terlihat**: perintah artisan (mis. `receipts:ocr-status`) yang menampilkan status, sisa pagu, dan alasan cooldown, plus opsi mengangkatnya. Cooldown yang tak bisa dilihat = OCR mati diam-diam, dan itu mengulang kelas bug yang sedang kita berantas.

### Lapis 4 — Pagu harian

```
GEMINI_DAILY_LIMIT=500    # 0 = tanpa batas
```

Penghitung `gemini:calls:YYYY-MM-DD`, dinaikkan tiap panggilan **terkirim** (bukan tiap job). Tercapai → cooldown sampai tengah malam.

Ini pengaman biaya, bukan pengaman teknis: melindungi dari satu unggahan 5.000 berkas scan yang tak seorang pun berniat mengunggah.

### Lapis 5 — Pesan yang membedakan keadaan

`last_error` hari ini bercampur. Yang perlu dibedakan di layar:

| Keadaan | Pesan | Tindakan admin |
|---|---|---|
| Nomor memang tak terbaca | "Nomor pembayaran tidak terbaca dari berkas." | cocokkan manual |
| Kuota/koneksi | "Pembacaan otomatis tertunda — dicoba lagi otomatis." | **tidak ada** |
| Konfigurasi (401/403/404) | "OCR nonaktif: kredensial ditolak." | hubungi admin sistem |

Aturannya: **pesan yang menyuruh admin bertindak hanya boleh muncul kalau memang ada yang perlu dia lakukan.**

---

## 3A. Empat temuan yang lebih berat dari rate limit

Ditemukan saat memeriksa ulang kode untuk menyusun §3. Ketiganya **tidak** tertutup oleh lima lapis di atas, dan dua di antaranya bukan soal biaya melainkan soal data pelanggan.

### 3A.1 Yang dikirim ke Google adalah SELURUH berkas, bukan satu kwitansi

`GeminiOcrReceiptNumberReader::read($absolutePath)` menerima **berkas unggahan apa adanya** — sama persis dengan yang disimpan di disk privat. Untuk lembar borongan, itu berarti satu panggilan API membawa **seluruh isi lembar**.

Terukur pada berkas nyata di storage:

```
lembar borongan (200 kwitansi) : 3,0 MB
setelah base64                 : 4,0 MB per panggilan
```

Isi tiap kwitansi di lembar itu, sesudah penyatuan `ReceiptPresenter`: **nama pelanggan, CID, alamat, nomor tagihan, periode, paket, nominal dibayar, sisa tagihan, nama kolektor**.

Jadi satu panggilan OCR atas satu lembar = **data 200 pelanggan dikirim ke pihak ketiga**, untuk mendapatkan satu string `PAY-YYYYMM-NNNN`.

> Repo ini sudah memutuskan lampiran kwitansi disimpan di disk **`local` (privat)**, bukan `public`, dengan alasan eksplisit "isinya bisa memuat data pelanggan" (CLAUDE.md § File & lampiran). Mengirim berkas yang sama ke API luar adalah keputusan yang berlawanan arah, dan **belum pernah diputuskan siapa pun**.

### 3A.2 Prompt meminta SATU nomor, berkas bisa memuat 200

Prompt-nya: *"kembalikan HANYA nomor pembayarannya"* — tunggal. `ReceiptNumberExtractor::extract()` juga mengembalikan satu nomor, dan `extractAll()` membungkusnya jadi daftar berisi satu.

Konsekuensinya kalau OCR sampai dipakai pada lembar borongan: **199 kwitansi lain hilang tanpa jejak**. Bukan error, bukan `MISMATCH` — cuma tidak pernah ada.

Hari ini tidak meledak karena lembar cetak sistem selalu punya lapisan teks dan berhenti di jalur pertama. Yang berbahaya adalah lembar borongan **hasil scan kertas** (dicetak, ditandatangani, difoto) — persis kasus yang OCR ada untuk menolongnya.

### 3A.3 Ketentuan penggunaan data berbeda antar tier

Layanan model gratis pada umumnya memakai masukan pengguna untuk pengembangan produk, sementara tier berbayar tidak. **Angka dan ketentuan wajib diperiksa langsung di dokumen resmi Google saat key dibuat — jangan dipatok dari dokumen ini.**

Yang perlu diputuskan sebelum key dibuat, bukan sesudah: apakah data pelanggan boleh menjadi masukan pelatihan pihak ketiga. Kalau jawabannya tidak, maka tier gratis **tidak boleh dipakai sama sekali**, termasuk untuk uji coba.

> **Dijawab 2026-08-12 (D11/D12):** boleh, dan **Tier 1 berbayar**. Tier gratis tidak dipakai sama sekali, termasuk untuk uji coba.

### 3A.4 Cadangan bekerja per BERKAS, bukan per kwitansi — kehilangan senyap yang SUDAH terjadi hari ini

**Ditemukan 2026-08-12 dari pertanyaan user:** *"bukankah OCR itu untuk mencocokkan kwitansi yang gagal di jalur teks dan QR? Kalau ada 20 halaman berisi 160 kwitansi dan 20 di antaranya gagal, bukankah OCR seharusnya mengerjakan 20 itu?"*

Itu **arsitektur yang benar**. Yang ada sekarang bukan itu.

Rantai pembacaan menciutkan hasil di empat tempat, semuanya per berkas:

| Tempat | Perilaku sekarang |
|---|---|
| `ReceiptNumberExtractor::extractAll()` | lapisan teks dapat ≥1 nomor → **langsung `return`**. QR & OCR tak pernah dijalankan |
| `PdfTextNumberReader::numbers()` | `pdftotext` atas SELURUH dokumen; halaman hasil scan tak menyumbang apa pun |
| `QrReceiptNumberReader::scanPages()` | mengembalikan nomor **pertama** yang ketemu lalu **`return`** — maksimal 1 nomor untuk seluruh berkas |
| `ReceiptNumberExtractor::extract()` | mengembalikan satu nomor dari jalur gambar |

**Skenario 20 halaman × 8 kwitansi = 160, dengan 20 gagal terbaca:**

- *Sebagian halaman punya lapisan teks* → 140 nomor dikembalikan, rantai berhenti. **OCR tidak pernah dipanggil.** 20 sisanya tidak punya baris sama sekali.
- *Seluruhnya hasil scan* → QR mengembalikan **1 nomor**, berhenti. **159 hilang.**

Dalam kedua kasus berkasnya berstatus **"Cocok"** di layar. Tidak ada error, tidak ada `MISMATCH`, tidak ada yang melaporkan bahwa 20 (atau 159) pembayaran tidak punya kwitansi.

> **Ini bukan risiko OCR di masa depan — jalur QR sudah berperilaku begini sekarang.** Berkas scan berisi banyak kwitansi hari ini hanya menghasilkan satu baris. Yang menutupinya: lembar cetak sistem selalu punya lapisan teks, jadi kasus ini baru muncul pada berkas hasil scan/foto — yang justru makin sering begitu kwitansi dipakai sebagai bukti fisik.

**Kelas bug yang sama, ketiga kalinya di modul ini: gejalanya menyerupai keberhasilan.**

Konsekuensi untuk D13: pertanyaannya bukan lagi "kirim utuh atau dipotong", melainkan **apa satuan cadangannya**. Satuan terkecil yang bisa diisolasi tanpa menebak adalah **halaman** — `PdfPageRasterizer` sudah merender per halaman. Rancangan lengkap di §3D.

---

## 3B. Cara mengukur akurasi TANPA risiko: mode bayangan

Pertanyaan yang belum bisa dijawab siapa pun hari ini: **seberapa sering OCR salah baca?** Tanpa angka itu, mengaktifkan OCR adalah taruhan.

Jawabannya tidak perlu taruhan, karena kita punya **kunci jawaban**: berkas yang lapisan teks/QR-nya berhasil dibaca sudah diketahui nomornya secara deterministik.

**Mode bayangan** (`GEMINI_SHADOW_MODE=true`):

1. Berkas dibaca seperti biasa lewat teks/QR → nomor **ini** yang dipakai sistem.
2. Kalau OCR aktif, berkas yang sama juga dikirim ke Gemini — hasilnya **tidak dipakai untuk apa pun**.
3. Kedua hasil dicatat berdampingan.

Yang didapat sesudah beberapa hari:

| Metrik | Artinya |
|---|---|
| cocok | OCR membaca hal yang sama dengan kunci jawaban |
| meleset | OCR membaca nomor **lain** — inilah angka yang menentukan segalanya |
| kosong | OCR menyerah (aman: fail-closed) |

**Meleset adalah metrik yang menentukan.** Kalau di atas nol pada sampel yang layak, §5.1 ADHOC-25 (OCR jadi usulan, bukan keputusan) berhenti jadi teori dan jadi kebutuhan terbukti.

Mode bayangan juga **mengukur biaya nyata** sebelum ada satu keputusan pun yang bergantung padanya.

> Catatan jujur: mode bayangan tetap **mengirim data pelanggan** ke Google. §3A.1 dan §3A.3 harus diputuskan lebih dulu — mode bayangan bukan jalan pintas untuk melewatinya.

---

## 3C. Rencana gelar & jalan mundur

Urutan yang tidak bisa ditukar:

| Tahap | Syarat masuk | Yang boleh terjadi |
|---|---|---|
| 0. Sekarang | — | OCR mati. Semua kwitansi tak terbaca → manusia |
| 1. Bayangan | §3A.1/§3A.3 diputuskan, lima lapis §3 terpasang | OCR dipanggil, hasilnya **tak pernah** dipakai |
| 2. Usulan | angka "meleset" diketahui, ADHOC-25 §5.1 selesai | OCR mengisi usulan, **admin** yang memutuskan |
| 3. Otomatis penuh | **belum diputuskan — mungkin tidak pernah** | — |

**Tahap 3 sengaja tidak dijadwalkan.** Kalau tahap 2 sudah cukup, tidak ada alasan menaikkannya: yang dihemat cuma satu klik, yang dipertaruhkan adalah kwitansi menempel ke pelanggan yang salah.

**Jalan mundur harus ada sebelum tahap 1, bukan sesudah:**

1. **Sakelar mati seketika** — mengosongkan `GEMINI_API_KEY` sudah cukup (`isAvailable()` false), tapi wajib diuji sekali supaya bukan asumsi.
2. **Menemukan kembali** — semua kwitansi yang tertempel lewat OCR harus bisa dicari dalam satu query. `payment_receipts.match_method` sudah menyimpannya (`ReceiptMatchMethod::OCR`); pastikan tidak ada jalur yang menimpanya.
3. **Melepas massal** — perintah untuk melepas seluruh tempelan hasil OCR dalam rentang tanggal, mengembalikannya ke antrean manual. Tanpa ini, "salah tempel" berarti menelusuri satu per satu.
4. **Jejak audit** — tiap penempelan otomatis wajib punya baris audit dengan metodenya, supaya pertanyaan "kenapa kwitansi ini menempel ke pelanggan itu" punya jawaban enam bulan kemudian.

---

## 3D. Rancangan: pembacaan per HALAMAN

Menutup §3A.4, sekaligus menjawab D13. Berdiri sendiri — tahap pertamanya tidak menyentuh Gemini sama sekali.

### 3D.1 Prinsip: satuan cadangan adalah halaman

Satu **kwitansi** bukan unit yang bisa dipakai. Memisahkannya butuh koordinat, dan berkas yang sampai ke jalur cadangan justru yang **tidak punya lapisan teks** — jadi koordinatnya memang tidak ada. Itu pemotongan yang sudah gagal presisi empat kali di ADHOC-27; mengulanginya di atas data yang lebih miskin adalah cara cepat menempelkan kwitansi ke pelanggan yang salah.

Satu **halaman** bisa diisolasi tanpa menebak apa pun:

- `PdfPageRasterizer::pageToPng($path, $page, $dpi)` sudah ada dan sudah dipakai jalur QR.
- `pdftotext` menerima `-f`/`-l` untuk membatasi halaman.

Karena itu: **teks, QR, dan OCR dijalankan per halaman; hasil seluruh halaman digabungkan.**

### 3D.2 Alur

```
untuk tiap halaman h (1..min(jumlahHalaman, MAX_PAGES)):
    nomor[h] ← teksHalaman(h)          # pdftotext -f h -l h
    jika kosong: nomor[h] ← semuaQr(h) # SEMUA QR di halaman itu, tidak berhenti di yang pertama
    jika kosong: nomor[h] ← ocr(h)     # HANYA halaman ini yang dikirim keluar

hasil = gabungan(nomor[1..n]) → dedup → urut kemunculan
```

Tiga sifat yang membedakannya dari sekarang:

1. **Tidak ada `return` dini.** Halaman yang berhasil tidak lagi menutup jalur cadangan bagi halaman yang gagal.
2. **QR mengumpulkan, bukan mengambil yang pertama.**
3. **OCR hanya menyentuh halaman yang benar-benar gagal** — bukan seluruh berkas.

### 3D.3 Perubahan per berkas

| Berkas | Sekarang | Menjadi |
|---|---|---|
| `PdfTextNumberReader` | `numbers($path)` untuk seluruh dokumen | `numbersOnPage($path, $page)`; `numbers()` jadi pembungkus yang menjumlahkan halaman |
| `QrReceiptNumberReader` | `scanPages()` `return` di nomor pertama | `numbersOnPage($path, $page)` → **semua** QR di halaman itu |
| `GeminiOcrReceiptNumberReader` | menerima berkas utuh, prompt minta 1 nomor | menerima **satu PNG halaman**, prompt minta **semua** nomor (daftar) |
| `ReceiptNumberExtractor` | teks → QR → OCR per berkas, ciut jadi 1 nomor | loop per halaman, tiga jalur di dalamnya, lalu gabungkan |

`extractAll()` tetap mengembalikan `{numbers, method}`, jadi `PaymentReceiptService::attachNumbers()` **tidak tersentuh**. Yang berubah cuma isinya menjadi lengkap.

### 3D.4 Satu keputusan turunan: metode per NOMOR, bukan per berkas

Sesudah per halaman, satu berkas bisa memuat nomor dari jalur berbeda: halaman 1 dari teks, halaman 7 dari OCR. `match_method` hari ini satu nilai per berkas.

**Metode harus disimpan per nomor.** Label "via Teks / QR / OCR" di daftar Berkas Kwitansi adalah dasar admin memutuskan seberapa jauh ia percaya baris itu — dan sesudah ADHOC-25, hasil OCR punya perlakuan berbeda (usulan, bukan keputusan). Nomor hasil OCR yang menyamar sebagai hasil QR menghapus pembedaan itu diam-diam.

Konsekuensi: kolom metode pindah ke baris `payment_receipts` per nomor (sudah per-nomor sejak lembar borongan) — perlu diperiksa saat implementasi, bukan diasumsikan.

### 3D.5 Yang ikut membaik

Contoh nyata: scan 20 halaman, 8 kwitansi per halaman, 3 halaman gagal terbaca.

| | Sekarang | Per halaman |
|---|---|---|
| Nomor tercatat | 1 (jalur QR) atau parsial senyap | seluruhnya |
| Halaman yang dikirim ke Gemini | 20 halaman (160 pelanggan) | **3 halaman** (24 pelanggan) |
| Panggilan OCR | 1 × berkas 4 MB | 3 × ±200 KB |
| Kehilangan pada jalur QR | 7 dari 8 per halaman | tertutup, **tanpa menunggu Gemini** |

Perhatikan baris ketiga: per halaman **menurunkan** paparan data pelanggan sekaligus biaya. Ia bukan sekadar perbaikan kebenaran.

### 3D.6 Batas & konsekuensi yang harus disengaja

- **`MAX_PAGES` (10) tetap pagar waktu job.** Berkas lebih tebal ditolak ke jalur manual — **bukan dibaca separuh diam-diam seperti sekarang.** Ini perubahan perilaku yang harus disadari: hari ini berkas 20 halaman dibaca 10 halaman pertama tanpa memberi tahu siapa pun.
- **Timeout job 240 dtk** tetap batas atas. OCR per halaman ikut masuk hitungan; halaman gagal yang banyak berarti banyak panggilan berurutan.
- **Beban teks & QR tidak bertambah** — keduanya sudah berjalan per halaman hari ini, cuma hasilnya yang dibuang.
- **Tetap tidak ada cara mengetahui berapa kwitansi *seharusnya* ada** di halaman scan. Kehilangan hanya dicegah dengan mengerjakan tiap halaman, bukan dengan menghitung selisih.

### 3D.7 Penargetan halaman gagal & himpunan yang diharapkan

**Asal usul (user, 2026-08-12):** *"Sistem terlebih dahulu menganalisa ada berapa halaman berkas yang diunggah, OCR diberi tahu ada error di halaman berapa saja dan di kwitansi yang mana saja — dengan metode seperti ini OCR akan jauh lebih cepat."*

Arahnya benar. Dipisah jadi dua bagian karena yang satu bisa dan yang satu tidak.

#### A. Penargetan per HALAMAN — bisa, dan inilah jawaban D13

Sistem tahu jumlah halaman (`PdfPageRasterizer::pageCount()`) dan, sesudah §3D, tahu halaman mana yang belum tuntas. OCR hanya menerima halaman itu.

Contoh 20 halaman × 8 kwitansi, gagal di 3 halaman: OCR menerima **3 gambar halaman**, bukan berkas 20 halaman. Dugaan user bahwa ini "jauh lebih cepat" benar — dan sekaligus memangkas biaya serta paparan data pelanggan (§3D.5).

#### B. Penargetan per KWITANSI di dalam halaman — TIDAK bisa

Untuk memberi tahu "yang gagal slot ke-3 dan ke-5", sistem harus tahu (a) halaman itu berisi berapa kwitansi dan (b) posisi masing-masing.

Berkas yang sampai ke OCR justru **yang tidak punya lapisan teks** — jadi koordinatnya memang tidak ada. Yang tersisa hanya menebak geometri grid dari piksel: persis yang gagal presisi empat kali di ADHOC-27, di atas data yang lebih miskin. Menebak posisi pada dokumen uang adalah cara cepat menempelkan kwitansi ke pelanggan yang salah.

**Tidak perlu juga.** Yang diinginkan sebenarnya bukan "OCR tahu slot mana", melainkan "OCR mengerjakan 20 yang gagal, bukan 160". Itu tercapai tanpa posisi:

```
halaman 7 → QR dapat 6 dari 8 → halaman ditandai belum tuntas
          → OCR menerima gambar halaman 7, diminta SEMUA nomor
          → hasil 8 nomor → 6 yang sudah ada dibuang → 2 dipakai
```

Beban OCR = **jumlah halaman gagal**, bukan jumlah kwitansi. Hasil akhirnya identik.

> **Prasyarat:** QR wajib mengumpulkan semua nomor per halaman (§3D.2). Tanpa itu sistem tak pernah tahu sebuah halaman baru terbaca sebagian — itulah bug ADHOC-32.

> **Sengaja TIDAK dilakukan: memberi tahu model nomor yang sudah diketahui.** Prompt semacam *"sudah ada PAY-202608-0041, cari sisanya"* terdengar membantu, tapi menyodorkan contoh berformat benar kepada pembaca probabilistik menaikkan peluang ia **mengarang** nomor serupa — dan nomor karangan yang kebetulan ada di database lolos kedua gerbang (pola + payment ada). Minta semua, lalu kurangi di sisi kita.

#### C. Himpunan yang diharapkan — menjawab "kwitansi mana" secara pasti

Pertanyaan "kwitansi mana yang belum ketemu" **bisa** dijawab pasti, bukan dari gambar melainkan dari **konteks unggahan**.

Kwitansi dicetak sistem untuk sekumpulan pembayaran kolektor tertentu (`payment-receipts.print` menerima `payment_ids[]`). Sistem tahu persis nomor apa saja yang dicetak:

```
diharapkan (dari konteks cetak/kolektor) : 160 nomor
ditemukan (teks + QR + OCR)              :  140
belum ketemu                             :   20  → DAFTAR NOMORNYA
```

Nilainya: hari ini kalau 20 kwitansi hilang, **tak ada yang tahu**. Dengan ini admin melihat daftar nomor yang spesifik dan bisa mengejarnya.

**Kendala yang harus dibereskan dulu:** `PaymentReceiptController::store()` menerima berkas **tanpa konteks apa pun** — tidak terikat kolektor maupun setoran (`files[]` + `files_count`, itu saja). "Yang diharapkan" belum punya arti sampai unggahan diikat ke konteksnya.

**Kelebihan terbesar: bekerja walau OCR mati.** Tanpa Gemini sama sekali, sistem berhenti kehilangan kwitansi secara senyap — ia berubah dari "diam" menjadi "menyebut apa yang kurang". Karena itu bagian C **tidak bergantung pada keputusan Gemini mana pun**.

### 3D.8 Urutan pengerjaan

| Tahap | Isi | Bergantung pada |
|---|---|---|
| **1** | Teks & QR per halaman; QR mengumpulkan semua nomor. Menutup ADHOC-32 | **tidak ada** — bisa dikerjakan sekarang |
| **1b** | Himpunan yang diharapkan (§3D.7 bagian C): unggahan diikat ke konteks kolektor, sistem menyebut nomor yang belum ketemu | **tidak ada** — bekerja walau OCR mati selamanya |
| **2** | Lima lapis operasional §3 | keputusan D8 |
| **3** | OCR per halaman (§3D.7 bagian A) + mode bayangan §3B | ADHOC-25 selesai |

**Tahap 1 & 1b tidak menunggu satu pun keputusan soal Gemini**, dan justru yang paling mendesak: kehilangan kwitansi terjadi hari ini, dengan berkas berstatus "Cocok".

Urutannya juga bukan kebetulan — **1b memberi alat ukur untuk tahap 3.** Begitu sistem bisa menyebut "20 nomor belum ketemu", barulah ada angka untuk menilai apakah OCR benar-benar menolong, dan seberapa banyak. Mengaktifkan OCR sebelum itu berarti menambah jalur probabilistik tanpa cara mengukur hasilnya.

---

## 4. Yang TIDAK dilakukan

- **Tidak menaikkan `tries`.** Berkas yang tak terbaca tetap tak terbaca di percobaan kelima. Yang diperbaiki adalah *kelas galat*, bukan jumlah pengulangan.
- **Tidak mengecilkan `maxProcesses` produksi.** Konkurensi berguna untuk jalur teks & QR yang tidak memanggil API sama sekali; yang direm cukup jalur Gemini.
- **Tidak membuat status kwitansi baru untuk kuota habis.** Job yang dilepas kembali ke antrean tidak mengubah status — kwitansinya tetap `PENDING`/`PROCESSING` seperti yang memang benar.
- **Tidak menyentuh urutan pembacaan.** Teks → QR → OCR tetap.

---

## 5. Batas dokumen ini — TIDAK membuka blokir ADHOC-25

Seluruh isi dokumen ini **operasional**: jangan boros, jangan salah diagnosis, jangan membakar kuota.

ADHOC-25 adalah soal **kebenaran**: OCR salah baca satu digit, nomor hasil salah-baca itu kebetulan ada di database, lalu kwitansi menempel diam-diam ke pembayaran **pelanggan lain** — status hijau "Cocok", `last_error` kosong, tak ada yang melaporkannya.

Rate limit tidak menyentuh itu sedikit pun. Mengisi `GEMINI_API_KEY` setelah pekerjaan ini saja berarti lubang tersebut terbuka dengan rapi.

> **Syarat mengisi key: ADHOC-25 selesai DAN dokumen ini diimplementasikan.** Urutan yang disarankan: kebenaran dulu, operasional menyusul.

---

## 6. Keputusan yang sudah diambil (2026-08-12)

| # | Pertanyaan | Keputusan |
|---|---|---|
| D5 | Urutan kerja | **Analisa & catat dulu** — dokumen ini. Implementasi menunggu perintah terpisah |
| D6 | Kedalaman | **Lengkap** — kelima lapis (rem, klasifikasi, cooldown, pagu harian, pesan) |
| D7 | Kuota habis di tengah unggahan | **Tunggu & coba lagi otomatis** — job dilepas kembali ke antrean, admin tidak melihatnya sebagai kegagalan |
| D11 | Data kwitansi boleh dikirim ke pihak ketiga? | **Boleh** — proyek ini memang akan memakai Gemini AI |
| D13 | Satuan yang dikirim ke OCR | **Per halaman**, bukan per berkas dan bukan per kwitansi (§3D.7). Ditambah himpunan-yang-diharapkan dari konteks unggahan |
| D12 | Tier mana? | **Tier 1 berbayar.** Tier gratis **tidak dipakai sama sekali**, termasuk untuk uji coba — konsekuensinya keputusan §3A.3 tertutup, dan pemakaian tier gratis di lingkungan mana pun harus dianggap pelanggaran, bukan penghematan |

## 7. Yang masih perlu diputuskan sebelum implementasi

**Kelompok A — data pelanggan (harus dijawab paling awal; menentukan apakah sisanya relevan)**

- [ ] **D11** — Boleh atau tidak berkas kwitansi (nama, CID, alamat, nominal) dikirim ke API pihak ketiga? Kalau **tidak**, seluruh jalur OCR ditutup permanen dan dokumen ini selesai di sini.
- [ ] **D12** — Kalau boleh: tier gratis (data berpotensi dipakai melatih model) atau **hanya** tier berbayar? *(rekomendasi: berbayar saja, termasuk untuk uji coba)*
- [x] **D13** — Satuan yang dikirim ke OCR. **Diputuskan 2026-08-12: per HALAMAN** (§3D, §3D.7). Sistem menghitung halaman, menandai halaman yang belum tuntas, dan hanya halaman itu yang dikirim — OCR diminta **semua** nomor di halaman itu, hasil yang sudah diketahui dibuang di sisi kita. Penargetan sampai ke **kwitansi individual di dalam halaman TIDAK dilakukan**: berkas yang sampai ke OCR tak punya koordinat teks, jadi itu berarti menebak geometri grid — kegagalan ADHOC-27 yang diulang di atas data lebih miskin. Dilengkapi **himpunan yang diharapkan** (§3D.7 bagian C) supaya pertanyaan "kwitansi mana yang belum ketemu" dijawab dari konteks unggahan, bukan dari gambar.

**Jawaban dari Kelompok A**

**D11:** — Boleh karena project ini nanti menggunakan pihak ketiga yaitu Gemini AI.

**D12:** — Tier Berbayar, yaitu Tier 1.

**D13:** — Jelaskan Bagaimana Maksutnya. Apakah Maksudnya adalah Potongan dari kwitansi tersebut yang akan dikirim ke OCR. Misal dalam kwitansi ada 2 atau 4 potongan kwitansi, maka akan di crop bagian kwitansi tersebut menjadi 2 atau 4 lalu akan dikirim ke OCR?


**Kelompok B — operasional**

- [ ] **D8** — Angka `GEMINI_RATE_LIMIT` dan `GEMINI_DAILY_LIMIT`. Wajib diambil dari kuota tier yang benar-benar dipakai (dashboard Google saat key dibuat), bukan dari perkiraan.
- [ ] **D9** — Cooldown akibat 401/403 diangkat lewat perintah artisan saja, atau perlu tombol di layar? *(rekomendasi: artisan saja — ini kejadian langka dan bersifat konfigurasi)*
- [ ] **D10** — Perlukah notifikasi ke admin saat cooldown konfigurasi aktif? *(rekomendasi: perlu — OCR mati diam-diam adalah gejala yang sama dengan yang sedang diberantas modul ini)*
- [ ] **D14** — Mode bayangan (§3B) dijalankan berapa lama / berapa berkas sebelum dinilai? *(rekomendasi: sampai ada minimal beberapa puluh berkas scan nyata, bukan patokan hari)*


**Jawaban dari Kelompok B — Operasional**

**D8** — Saya menggunakan gemini tier 1 paid

**D9** — Artisan saja, karena ini kejadian langka dan bersifat konfigurasi

**D10** — perlu — OCR mati diam-diam adalah gejala yang sama dengan yang sedang diberantas modul ini

**D14** — sampai ada minimal beberapa puluh berkas scan nyata, bukan patokan hari


## 8. Kriteria selesai

**Operasional (§3):**

1. Unggahan 100 berkas scan dengan `GEMINI_RATE_LIMIT=10` tidak menghasilkan satu pun 429.
2. 429 buatan tidak pernah menandai kwitansi `FAILED`; job dilepas dan berhasil pada percobaan berikutnya.
3. 401 buatan menghentikan panggilan berikutnya seketika (bukan 3× per berkas) dan mengaktifkan cooldown.
4. Pagu harian tercapai → panggilan berhenti, kwitansi jatuh ke jalur manusia tanpa error.
5. Ada satu perintah yang menampilkan status OCR: aktif/cooldown, alasan, sisa pagu.
6. Test menutup keempat kelas galat (429, 503, 401, timeout) tanpa memanggil API sungguhan (`Http::fake`).

**Data & jalan mundur (§3A, §3C) — syarat masuk tahap bayangan:**

7. Keputusan D11–D13 tercatat.
8. OCR menolak berkas yang memuat lebih dari satu kwitansi (kalau D13 = batasi), sehingga §3A.2 mustahil terjadi.
9. Mengosongkan `GEMINI_API_KEY` terbukti menghentikan seluruh panggilan — diuji, bukan diasumsikan.
10. Ada perintah untuk melepas massal seluruh tempelan hasil OCR dalam rentang tanggal.
11. Tiap penempelan otomatis punya baris audit yang menyebut metodenya.

**Sebelum naik dari bayangan ke usulan (§3B, §3C):**

12. Angka "meleset" diketahui dari data nyata, bukan diperkirakan.
13. ADHOC-25 §5.1 selesai — `payment_id` tidak pernah diisi mesin dari hasil OCR.
