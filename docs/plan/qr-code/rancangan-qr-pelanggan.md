# Rancangan QR Code Pelanggan

**Status:** Rancangan (belum masuk sprint). Sprint aktif saat dokumen ini ditulis: **Sprint 8.10**.
Dokumen ini **tidak** mengubah kode apa pun — murni desain untuk direview sebelum dijadwalkan.

**Tanggal:** 2026-08-07

> ⚠ **Sudah direview — baca [§13 Catatan Review](#13-catatan-review) sebelum mengimplementasi apa pun.**
> Review menemukan 5 masalah serius, termasuk **asumsi threat model yang belum diverifikasi** (R1), **pilihan format token yang salah** (R2), dan **nama permission yang tidak akan ter-generate** (R4). Temuan sengaja **belum** diperbaiki di badan dokumen karena sebagian membatalkan keputusan di §12. Bagian terdampak ditandai `⚠ R#`.

---

## 0. Kesimpulan kelayakan

**Pertanyaan awal:** bisakah satu QR Code per pelanggan — dibentuk dari POP ID + REQ ID/CID + Nama Pelanggan yang di-hash — melayani pembayaran, login pelanggan, ticketing, dan absen teknisi?

**Jawaban: bisa,** dengan tiga koreksi pada bahan hash-nya:

| Ide awal | Hasil verifikasi ke kode | Keputusan |
|---|---|---|
| Pakai REQ ID/CID | REQ ID **memang permanen** (benar). Tapi `display_id` yang dirender berubah RQ↔CID | Hash `customer_code`, bukan `display_id` |
| Pakai POP ID | **Wajib** — REQ ID tidak unik lintas cabang sejak migration `scope_customer_code_unique_to_pop` | Dipertahankan |
| Pakai Nama Pelanggan | Mutable + PII + tidak menambah keunikan | Dikeluarkan dari hash, tetap dicetak di stiker |
| "hashing" | Hash polos bisa dihitung siapa saja (semua bahannya publik) | HMAC-SHA256 dengan secret + opaque token, keduanya wajib lolos |

**Keempat fungsi layak,** dengan catatan berbeda-beda:

| Fungsi | Layak? | Catatan |
|---|---|---|
| Ticketing | Ya, langsung | Tidak menyentuh alur sync Ticket↔FopTask↔Task sama sekali |
| Absen teknisi | Ya, dengan syarat | QR sendirian **tidak** membuktikan kehadiran — butuh geolocation + cek penugasan + cek jadwal. Bergantung pada kelengkapan koordinat pelanggan (§4.4) |
| Pembayaran | Ya, bertahap | QR = pintu ke halaman tagihan, bukan instrumen bayar. QRIS menyusul saat gateway diputuskan |
| Login pelanggan | Mekanisme siap, portal ditunda | Faktor pembuktian = **PIN 6 digit** (§6.5). Yang belum ada tinggal portal & tabel sesi pelanggan |

**Tambahan PIN (2026-08-07):** PIN di-generate otomatis bersamaan QR sebagai faktor login pelanggan — layak, dengan satu koreksi penting: **PIN dicetak di kartu pelanggan terpisah, bukan di stiker ONT.** Stiker tertempel di luar rumah; PIN di stiker yang sama membuat dua faktor runtuh jadi satu. Rincian di §6.5.

Rincian dan pembuktiannya di §2 dan seterusnya. Seluruh keputusan beserta alasan terkumpul di §12.

---

## 1. Tujuan

Satu QR Code per pelanggan, dicetak sekali (stiker di ONT / kartu pelanggan), melayani 4 fungsi:

| # | Fungsi | Aktor pemindai | Prioritas |
|---|---|---|---|
| A | **Pembayaran** — buka halaman tagihan pelanggan | Pelanggan (publik) | 1 |
| B | **Absen teknisi** — mulai Task di lokasi pelanggan | Teknisi (login) | 1 |
| C | **Ticketing** — buat tiket dengan pelanggan ter-prefill | Helpdesk/NOC/FOP (login) | 2 |
| D | **Login pelanggan** ke portal masing-masing | Pelanggan | 3 — **belum diimplementasi**, hanya disiapkan slot-nya |

Prinsip: **satu QR, satu URL, routing ditentukan server berdasarkan siapa yang memindai.**
Bukan 4 QR berbeda — pelanggan cuma punya satu stiker.

---

## 2. Koreksi terhadap ide awal

Ide awal: `POP ID + REQ ID/CID + Nama Pelanggan → hashing`. Tiga masalah:

### 2.1 REQ ID permanen — tapi `display_id` bukan REQ ID, dan REQ ID tidak unik global

Dua hal yang harus dipisahkan:

**REQ ID (`customers.customer_code`) memang permanen.** Melekat seumur hidup pelanggan apa pun statusnya — gagal, putus, suspend, reject. Ini anchor yang benar. Konfirmasi di kode: `Pop::resolveDisplayId()` (`app/Models/Pop.php:264`) selalu menurunkan REQ ID dari `customer_code` lewat `extractBareRegistrationId()`, dan status `terminated`/`failed`/`rejected`/`putus`/`gagal` justru menampilkannya murni.

**Yang berubah adalah `display_id`, bukan REQ ID.** `Customer::getDisplayIdAttribute()` (`Customer.php:379`) merender REQ ID yang sama ke tiga bentuk berbeda tergantung status:

| Kondisi | `display_id` |
|---|---|
| `terminated`/`failed`/`rejected`/`putus`/`gagal` | `RQ000631` (REQ ID murni) |
| `cid` sudah terisi | `C1X4ARQ000631` |
| selain itu | `C00RQ000631` |

Jadi koreksinya bukan "REQ ID tidak stabil" — melainkan **jangan hash `display_id`, hash `customer_code`.** REQ ID di baliknya sama; hanya bungkusnya yang berubah.

**Tapi REQ ID sendirian tidak cukup — wajib berpasangan dengan POP.**

Migration `2026_07_20_141841_scope_customer_code_unique_to_pop.php` **mencabut unique global** pada `customer_code` dan menggantinya dengan composite:

```php
$table->dropUnique('customers_customer_code_unique');
$table->unique(['pop_id', 'customer_code']);
```

Alasannya (dari komentar migration): tiap cabang legacy punya counter RQ sendiri mulai dari 1. Kasus nyata di `docs/ID_NUMBERING_RULES.md:389` — `Winda Ari Sulfia` (POP Jetis, `RQ000042`) dan `Endah Puji Rahayu` (POP Sandya, `RQ000042`): dua pelanggan berbeda, REQ ID identik, keduanya sah.

> **Konsekuensi:** hash dari REQ ID saja akan **menghasilkan QR yang sama untuk dua pelanggan berbeda.** Teknisi memindai stiker Winda, sistem membuka task Endah.

Ini justru mengonfirmasi triple asli Anda: **POP ID memang wajib ada di dalam hash.** Kunci identitas = `(pop_id, customer_code)` — persis composite unique-nya.

> ⚠ **R3: seluruh analisis tabrakan ini hanya relevan KALAU HMAC dipakai.** Token acak 128-bit di DB sudah unik global — tanpa HMAC, masalah tabrakan REQ ID tidak pernah ada, dan kopling `pop_id` (beserta cetak ulang saat re-homing) hilang seluruhnya. Lihat §13 R3.

**Catatan `pop_id` — sudah diputuskan (2026-08-07):** `pop_id` bisa berubah saat re-homing pelanggan antar cabang, yang akan mematikan QR lama. Dikonfirmasi ke pemilik produk: **re-homing sangat jarang terjadi.**

Keputusan: **`pop_id` tetap masuk bahan HMAC.**

Perbandingannya tidak seimbang, jadi keputusannya mudah:

| | Kalau `pop_id` masuk hash | Kalau tidak |
|---|---|---|
| Tabrakan REQ ID antar cabang | Tidak mungkin | **Terjadi** — data nyata sudah punya kasusnya (Winda/Endah, `RQ000042`) |
| Biaya | Cetak ulang 1 stiker saat re-homing | — |
| Frekuensi biaya | Sangat jarang | — |

Menghilangkan `pop_id` berarti menerima cacat identitas permanen pada data yang sudah ada, demi menghindari cetak ulang stiker yang hampir tidak pernah terjadi.

Penanganan saat re-homing tetap wajib ada supaya kegagalannya tidak diam-diam: `CustomerObserver::updated()` mendeteksi perubahan `pop_id` → cabut token → notifikasi admin POP untuk cetak ulang. Scan QR lama ditolak eksplisit sebagai `pop_mismatch` (§5), bukan sebagai "QR rusak" yang membingungkan teknisi di lapangan.

**`full_name` dikeluarkan dari hash.** Alasannya:
- Tidak menambah keunikan — `(pop_id, customer_code)` sudah unique by constraint
- Mutable — koreksi typo nama akan mematikan seluruh QR pelanggan itu
- PII — tidak ada gunanya memasukkan nama ke bahan kriptografi yang tidak butuh

Nama tetap ditampilkan di **stiker cetak** (agar admin/teknisi bisa membaca stiker mana milik siapa), hanya tidak masuk perhitungan hash.

### 2.2 Hash field publik bisa dipalsukan

POP ID, CID, dan nama pelanggan semuanya **bukan rahasia** — tercetak di invoice, terlihat di stiker rumah tetangga, muncul di WhatsApp broadcast. Kalau QR = `sha256(pop_id + cid + nama)`, penyerang yang tahu ketiganya bisa menghitung sendiri QR valid untuk pelanggan mana pun.

Konsekuensi nyata di sistem ini: **teknisi bisa absen fiktif** — generate QR pelanggan dari rumah, `TaskService::start()` jalan tanpa pernah ke lokasi.

Dua mekanisme, dan sesuai permintaan **keduanya dipakai bersamaan** — bukan pilih salah satu:

| Mekanisme | Cara | Melindungi dari |
|---|---|---|
| **Opaque token** | ULID acak disimpan di DB | Tebakan/enumerasi; memungkinkan **pencabutan** per pelanggan |
| **HMAC-SHA256** | `hmac(pop_id\|customer_code\|token, QR_HMAC_SECRET)` | Pemalsuan QR; penyisipan baris token lewat jalur non-aplikasi |

Keduanya harus lolos agar scan diterima. Ini defense in depth, bukan redundansi:

- **Token tanpa HMAC** — kalau ada jalur write ke DB di luar aplikasi (SQL injection, akses DB langsung oleh orang dalam, restore backup yang dimodifikasi), penyerang bisa menyisipkan baris token buatan sendiri dan QR-nya langsung sah. Dengan HMAC, baris sisipan tidak akan pernah punya signature valid karena secret tidak ada di database.
- **HMAC tanpa token** — signature valid selamanya dan tidak bisa dicabut. Pelanggan lapor stiker hilang, teknisi resign menyimpan foto QR → satu-satunya jalan adalah rotasi secret, yang berarti **cetak ulang seluruh stiker di semua cabang**.

**Urutan verifikasi — catatan jujur soal biaya:** karena bahan HMAC memuat `pop_id` dan `customer_code`, keduanya harus dibaca dari DB dulu, jadi signature **tidak bisa** diverifikasi sebelum query. Yang bisa dilakukan tanpa DB hanyalah validasi format (panjang + charset ULID/base32), dan itu sudah cukup menyaring mayoritas request sampah ke endpoint publik. Sisanya = 1 query indexed pada kolom unique + 1 `hash_hmac` — murah, dan tetap di belakang rate limiter.

Alternatif `hmac(token)` saja memang bisa diverifikasi sebelum DB, tapi membuang pengikatan ke POP — dan justru POP-lah yang mencegah tabrakan REQ ID antar cabang (§2.1). Pengikatan lebih bernilai daripada satu query yang dihemat.

> **Batas yang harus jelas:** HMAC **tidak** melindungi dari QR asli yang difoto orang. Stiker sah punya signature sah; siapa pun yang memotretnya memegang QR yang valid secara kriptografis. Untuk absen teknisi, pembuktian kehadiran datang dari geolocation + cek penugasan + cek jadwal (§6.2), bukan dari HMAC.

### 2.3 QR internal ≠ QRIS

QR buatan sendiri **tidak bisa dipindai** aplikasi m-banking, GoPay, OVO, DANA. QRIS punya format terstandar (EMVCo + aturan BI) dan wajib diterbitkan lewat PJSP berizin.

Alur yang benar:

```
QR internal (stiker) → dipindai kamera HP biasa → buka URL
      → halaman tagihan publik → tombol "Bayar"
            → server minta QRIS dinamis ke payment gateway (nominal = sisa tagihan)
            → QRIS tampil di layar → pelanggan pindai dengan m-banking
```

Jadi QR pelanggan adalah **pintu masuk ke halaman tagihan**, bukan instrumen pembayaran itu sendiri. Ini juga yang membuat satu QR statis bisa melayani tagihan yang nominalnya berubah tiap bulan.

---

## 3. Desain payload

### 3.1 Isi QR

```
https://portal.whusnet.id/q/01JZ8K3M9XQ7VN4T2P6R8W5FDC.K7M2QX9P4T
                        └┬┘└──────────┬─────────────┘ └────┬───┘
                      route      token (ULID 26 char)   HMAC sig
                                                        (10 char base32)
```

Alasan URL, bukan payload JSON/teks:

- Kamera HP bawaan (Android/iOS) langsung menawarkan "buka link" — pelanggan tidak perlu instal aplikasi apa pun untuk fungsi A
- Server yang memutuskan routing → satu QR melayani 4 fungsi tanpa cetak ulang
- Tidak ada PII di dalam QR — stiker yang difoto orang lewat tidak membocorkan nama/alamat

Panjang total ~59 karakter → QR **versi 4, ECC level M**, 33×33 modul. Tetap terbaca stabil dicetak 2×2 cm di stiker vinyl.

> Kalau nanti ukuran cetak jadi kendala, naikkan ECC ke level Q dan perbesar stiker — **jangan potong panjang signature.** Detail alasannya di §3.2.

### 3.2 Perhitungan HMAC

```php
// app/Services/CustomerQrTokenService.php

private const HMAC_ALGO = 'sha256';
private const SIG_LENGTH = 10;   // 10 char base32 = 50 bit

/**
 * Bahan tanda tangan: pop_id | customer_code | token
 *
 * - pop_id     WAJIB — customer_code cuma unik per POP (composite unique
 *              `(pop_id, customer_code)`, lihat migration
 *              scope_customer_code_unique_to_pop). Tanpa pop_id, 2 pelanggan
 *              beda cabang dengan RQ sama menghasilkan signature identik.
 * - token      mengikat signature ke SATU token. Tanpa ini, token yang sudah
 *              dicabut masih membawa signature yang sah untuk pelanggan itu,
 *              dan terbitkan-ulang menghasilkan QR yang identik dengan yang
 *              lama — pencabutan jadi tidak ada artinya.
 * - full_name  SENGAJA TIDAK IKUT — mutable (koreksi typo mematikan QR),
 *              tidak menambah keunikan, dan PII.
 *
 * Pemisah "|" dipakai karena tidak pernah muncul di pop_id (integer),
 * customer_code (alnum), maupun ULID. Tanpa pemisah, (pop=1, code=RQ12)
 * dan (pop=11, code=Q12) menghasilkan bahan hash yang sama.
 */
public function signature(int $popId, string $customerCode, string $token): string
{
    $payload = $popId.'|'.$customerCode.'|'.$token;
    $raw = hash_hmac(self::HMAC_ALGO, $payload, config('qr.secret'), binary: true);

    return substr(
        strtoupper(rtrim(base32_encode($raw), '=')),
        0,
        self::SIG_LENGTH
    );
}

public function verify(string $token, string $signature, Customer $customer): bool
{
    // hash_equals: perbandingan constant-time. Perbandingan biasa (===) bocor
    // lewat timing — penyerang bisa menebak signature karakter demi karakter.
    return hash_equals(
        $this->signature($customer->pop_id, $customer->customer_code, $token),
        strtoupper($signature)
    );
}
```

**Kenapa 10 karakter (50 bit) dan bukan lebih pendek:** signature dipotong demi ukuran QR, tapi memotong terlalu agresif membuatnya bisa di-brute-force. 50 bit berarti ~10¹⁵ percobaan — mustahil lewat HTTP bahkan tanpa rate limit. Di bawah ~40 bit mulai masuk wilayah yang layak diserang oleh penyerang dengan botnet. 4 karakter (20 bit) yang sempat saya usulkan di draf sebelumnya hanya cukup sebagai checksum salah-ketik, **tidak** sebagai kontrol keamanan.

**Config** — `config/qr.php`:

```php
return [
    // WAJIB di .env, minimal 32 byte acak: `openssl rand -base64 32`
    // Terpisah dari APP_KEY: rotasi APP_KEY (mis. saat insiden) tidak boleh
    // mematikan seluruh stiker QR yang sudah tercetak dan tertempel.
    'secret' => env('QR_HMAC_SECRET'),

    'base_url' => env('QR_BASE_URL', env('APP_URL')),
];
```

Tambahkan guard di `AppServiceProvider::boot()` yang menolak boot kalau `QR_HMAC_SECRET` kosong di production — secret kosong membuat `hash_hmac` tetap menghasilkan nilai (dengan key string kosong), jadi kegagalannya diam-diam: semua QR ter-generate dan tervalidasi normal, tapi signature-nya bisa dihitung siapa saja.

### 3.3 Fallback manual tercetak

Di bawah gambar QR, cetak teks yang bisa dibaca manusia:

```
MASUDAH YUNI FITRI
C1X4ARQ000631  ·  Ponorogo
K7M2QX9P4T
```

Kalau QR tergores/pudar, teknisi mengetik `customer_code` + signature secara manual. Server memverifikasi dengan cara yang persis sama. Jalur manual ini **tidak melewati satu pun guard** — tetap butuh login, POP scope, cek penugasan, dan geolocation seperti jalur QR.

> ⚠ **R5: fallback ini tidak akan pernah dipakai** — 36 karakter diketik di HP sambil berdiri di lokasi. Ganti dengan pilih-pelanggan-dari-daftar-task. Konsekuensinya signature tidak perlu dicetak di stiker. Lihat §13 R5.

Nama dan POP dicetak agar stiker bisa disortir sebelum ditempel; keduanya tidak masuk perhitungan hash.

### 3.4 Yang TIDAK boleh masuk payload

- Nama pelanggan, alamat, nomor HP → stiker tertempel di luar rumah, bisa difoto siapa saja
- CID/REQ ID mentah → memudahkan enumerasi pelanggan
- Nominal tagihan → berubah tiap bulan, QR statis tidak boleh memuatnya

---

## 4. Skema database

### 4.1 Tabel baru: `customer_qr_tokens`

Tabel terpisah, bukan kolom di `customers`, karena satu pelanggan bisa punya beberapa token seumur hidup (stiker hilang → terbitkan baru, yang lama dicabut) dan riwayat pencabutan perlu disimpan untuk audit.

```php
Schema::create('customer_qr_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

    // ULID 26 char. Indexed unique — ini kunci lookup tiap scan.
    $table->string('token', 32)->unique();

    // Bahan HMAC dibekukan saat penerbitan.
    //
    // WAJIB disimpan, tidak boleh dibaca ulang dari relasi `customers` saat
    // verifikasi. Alasannya: kalau pelanggan dipindah POP, `customers.pop_id`
    // berubah, signature yang dihitung ulang jadi beda dari yang TERCETAK di
    // stiker, dan seluruh QP pelanggan itu mati diam-diam tanpa ada yang tahu.
    // Dengan disimpan, mismatch pop_id terdeteksi eksplisit sebagai kondisi
    // "token perlu diterbitkan ulang" — bukan sebagai QR rusak misterius.
    $table->foreignId('signed_pop_id')->constrained('pops');
    $table->string('signed_customer_code', 30);

    // Signature disimpan HANYA untuk cetak ulang stiker tanpa akses secret
    // (mis. halaman cetak massal). Verifikasi TETAP menghitung ulang HMAC —
    // membandingkan signature masuk dengan kolom DB akan menghapus seluruh
    // manfaat HMAC terhadap penyisipan baris lewat jalur non-aplikasi.
    $table->string('signature', 16);

    // Snapshot display_id saat diterbitkan. HANYA untuk audit & label stiker.
    // JANGAN dipakai untuk verifikasi — display_id berubah seiring lifecycle
    // (RQ murni → CID) walaupun REQ ID di baliknya tetap.
    $table->string('issued_display_id', 40)->nullable();

    // --- PIN pelanggan (§6.5) ---
    //
    // HASH, bukan PIN itu sendiri. Ruang PIN cuma 10^6 — kalau kolom ini bocor
    // lewat dump/backup/log, PIN plaintext langsung terbaca; hash bcrypt bikin
    // penyerang harus brute-force per baris.
    //
    // Konsekuensi yang harus diterima: PIN TIDAK BISA ditampilkan ulang.
    // Lupa PIN = terbitkan ulang, bukan "lihat PIN". Ini disengaja — kalau
    // admin bisa melihat PIN pelanggan, PIN berhenti jadi faktor "hanya
    // pelanggan yang tahu" dan tidak lagi bisa dipakai sebagai bukti identitas.
    $table->string('pin_hash')->nullable();

    // PIN dirotasi TANPA menyentuh token/signature — pelanggan lupa PIN cukup
    // dapat PIN baru, stiker QR yang tertempel tetap berlaku. Inilah alasan
    // pin_hash TIDAK boleh masuk bahan HMAC (§6.5).
    $table->timestamp('pin_issued_at')->nullable();
    $table->foreignId('pin_issued_by')->nullable()->constrained('users');
    $table->unsignedTinyInteger('pin_version')->default(1);

    // PIN awal dicetak di kantor lalu DIBAWA TEKNISI ke rumah pelanggan
    // (§7.1) — ada jendela waktu di mana orang selain pelanggan memegang
    // PIN secara fisik. Wajib-ganti saat login pertama membuat pengetahuan
    // itu kedaluwarsa: PIN cetak = PIN AKTIVASI sekali pakai, bukan PIN
    // permanen. Tanpa ini, seluruh model "PIN hanya diketahui pelanggan"
    // bocor di titik serah-terima.
    $table->boolean('pin_must_change')->default(true);
    $table->timestamp('pin_first_used_at')->nullable();

    // Anti brute-force yang bertahan lintas request & restart cache.
    // Rate limiter Laravel berbasis cache saja tidak cukup: cache flush
    // menghapus hitungan percobaan, dan itu jalur bypass yang gampang.
    $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
    $table->timestamp('pin_locked_until')->nullable();

    $table->timestamp('issued_at');
    $table->foreignId('issued_by')->nullable()->constrained('users');

    // Pencabutan. Token dicabut tidak dihapus — jejak audit.
    $table->timestamp('revoked_at')->nullable();
    $table->foreignId('revoked_by')->nullable()->constrained('users');
    $table->string('revoke_reason', 255)->nullable();

    $table->timestamp('last_scanned_at')->nullable();
    $table->unsignedInteger('scan_count')->default(0);

    $table->timestamps();

    // Satu token aktif per pelanggan. Partial unique index (Postgres);
    // di SQLite pakai guard di Observer.
    $table->index(['customer_id', 'revoked_at']);
});
```

**Invariant:** maksimal satu token dengan `revoked_at IS NULL` per `customer_id`.
Ditegakkan di `CustomerQrTokenObserver::creating()` — bukan hanya di service — supaya jalur artisan/tinker/import ikut terkunci. Ini pola yang sama dengan `PaymentObserver::creating()` yang menolak nominal ≤ 0 dari semua jalur masuk.

### 4.2 Tabel baru: `qr_scan_logs`

Semua scan dicatat, termasuk yang **gagal**. Scan gagal justru sinyal paling berharga: token dicabut yang masih dipindai = stiker lama beredar; scan di luar radius berulang = indikasi absen fiktif.

```php
Schema::create('qr_scan_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_qr_token_id')->nullable()->constrained();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->foreignId('user_id')->nullable()->constrained();   // null = pemindai publik

    $table->string('purpose', 20);   // payment | attendance | ticketing | login
    $table->string('result', 30);    // success | bad_signature | token_not_found
                                     // | token_revoked | pop_mismatch | out_of_scope
                                     // | no_eligible_task | out_of_radius | verify_failed
    $table->string('reason', 255)->nullable();

    // Geolocation dari browser saat scan (fungsi B). Nullable — HTTP non-TLS
    // atau izin lokasi ditolak menghasilkan null, dan itu bukan error fatal,
    // hanya menurunkan tingkat kepercayaan absensi.
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->unsignedInteger('accuracy_meters')->nullable();
    $table->unsignedInteger('distance_meters')->nullable();  // jarak ke koordinat pelanggan

    $table->foreignId('task_id')->nullable()->constrained();
    $table->foreignId('ticket_id')->nullable()->constrained();

    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent', 255)->nullable();

    $table->timestamp('scanned_at');
    $table->timestamps();

    $table->index(['customer_id', 'scanned_at']);
    $table->index(['user_id', 'scanned_at']);
    $table->index(['result', 'scanned_at']);   // untuk dashboard anomali
});
```

### 4.3 Kolom tambahan di `tasks`

```php
$table->string('started_via', 20)->nullable()->after('started_at');  // manual | qr_scan
$table->decimal('start_latitude', 10, 7)->nullable();
$table->decimal('start_longitude', 10, 7)->nullable();
$table->unsignedInteger('start_distance_meters')->nullable();
```

Alasan disimpan di `tasks`, bukan hanya di `qr_scan_logs`: laporan SLA & audit membaca `tasks` langsung, dan pertanyaan "task ini dimulai lewat QR di lokasi atau diklik manual dari kantor?" harus terjawab tanpa join ke tabel log.

### 4.4 Prasyarat data

Fungsi B (absen) butuh koordinat pelanggan. Kolom sudah ada: `customers.latitude`, `customers.longitude` (lihat `#[Fillable]` di `Customer.php:42-43`).

> **Blocker yang harus dicek sebelum sprint dimulai:** berapa persen pelanggan aktif punya lat/lng terisi? Data migrasi legacy (`jetis_db`, `sand_db`) kemungkinan besar kosong. Kalau coverage rendah, validasi radius harus **soft** (dicatat sebagai `distance_meters = null`, tidak memblokir) sampai backfill koordinat selesai. Query pengecekan:
>
> ```sql
> SELECT COUNT(*) total,
>        SUM(CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END) tanpa_koordinat
> FROM customers WHERE status = 'active';
> ```

---

## 5. Routing: satu URL, empat perilaku

Endpoint tunggal `GET /q/{token}` bertindak sebagai dispatcher.

```
GET /q/{token}.{sig}
  │
  ├─ [1] Format token/sig salah      → 404, TANPA query DB
  │
  ├─ [2] Lookup token di DB          → tidak ketemu: 404
  │
  ├─ [3] hash_equals(sig, hitung ulang dari signed_pop_id
  │                        + signed_customer_code + token)
  │        gagal → 404, log result=bad_signature
  │        (SINYAL SERIUS: signature gagal = QR palsu atau baris token
  │         disisipkan lewat jalur non-aplikasi. Harus memicu alert,
  │         bukan sekadar baris log — beda dari token_not_found yang
  │         wajar terjadi karena stiker lama/salah ketik.)
  │
  ├─ [4] token dicabut               → 404, log result=token_revoked
  │
  ├─ [5] customer.pop_id ≠ signed_pop_id → 404, log result=pop_mismatch
  │        Pelanggan dipindah cabang tanpa token diterbitkan ulang.
  │        Bukan serangan — kegagalan proses. Notifikasi ke admin POP
  │        agar stiker dicetak ulang.
  │
  │  Semua kegagalan di atas mengembalikan 404 yang IDENTIK. Pesan
  │  spesifik ("token dicabut", "signature salah") membocorkan apakah
  │  suatu customer_code valid dan memberi umpan balik untuk penyerang
  │  yang menebak. Detail hanya masuk qr_scan_logs.
  │
  ├─ TAMU (belum login)
  │     └─ redirect → /q/{token}/tagihan          [fungsi A]
  │
  └─ SUDAH LOGIN
        └─ cek POP scope pelanggan via EffectiveAccessService
              │  gagal → 403 + log result=out_of_scope
              │
              ├─ punya task 'terjadwal' hari ini utk pelanggan ini
              │  DAN user ∈ team task tsb
              │     └─ tampilkan konfirmasi "Mulai Task TASK-2026-0123?"  [fungsi B]
              │
              ├─ punya permission tickets.create
              │     └─ tampilkan pilihan aksi: Buat Tiket / Lihat Detail   [fungsi C]
              │
              └─ selain itu
                    └─ redirect → customers.show                            [fallback]
```

Urutan cek disengaja: **absen didahulukan** karena teknisi di lapangan adalah pemindai paling sering dan paling terburu-buru — satu tap lebih sedikit berarti nyata di sana.

### Rute

```php
// routes/web.php — STATIC dulu, DYNAMIC belakangan (konvensi repo)

// Publik, tanpa auth. Rate-limited ketat.
Route::middleware('throttle:qr-public')->group(function () {
    // {code} = "{token}.{sig}". Constraint regex menolak format salah di
    // level router — tidak pernah menyentuh controller apalagi DB.
    Route::get('/q/{code}', [QrScanController::class, 'dispatch'])
        ->where('code', '[0-9A-HJKMNP-TV-Z]{26}\.[A-Z2-7]{10}')
        ->name('qr.dispatch');

    Route::get('/q/{code}/tagihan',  [QrBillingController::class, 'show'])->name('qr.billing');
    Route::post('/q/{code}/tagihan/verifikasi', [QrBillingController::class, 'verify'])->name('qr.billing.verify');
});

// Butuh auth
Route::middleware('auth')->group(function () {
    Route::post('/q/{code}/absen', [QrAttendanceController::class, 'store'])->name('qr.attendance');
    Route::get('/q/{code}/tiket',  [QrTicketController::class, 'create'])->name('qr.ticket.create');
});

// Manajemen token — permission sendiri, bukan numpang customers.view
Route::middleware('permission:customers.qr.view')->group(function () {
    Route::get('/customers/{customer}/qr',       [CustomerQrController::class, 'show'])->name('customers.qr.show');
    Route::get('/customers/{customer}/qr/cetak', [CustomerQrController::class, 'print'])->name('customers.qr.print');
});
Route::middleware('permission:customers.qr.manage')->group(function () {
    Route::post('/customers/{customer}/qr/terbitkan', [CustomerQrController::class, 'issue'])->name('customers.qr.issue');
    Route::post('/customers/{customer}/qr/cabut',     [CustomerQrController::class, 'revoke'])->name('customers.qr.revoke');
});
```

Permission baru yang perlu ditambah lewat `features` × `actions` (`PermissionGeneratorService`), **bukan hardcode**:

> ⚠ **R4: dua nama di tabel bawah TIDAK VALID** — `manage` bukan `ActionCode`, `attendance` bukan action. Tidak akan ter-generate. Perbaikan di §13 R4.

| Permission | Pemegang |
|---|---|
| `customers.qr.view` | admin, pop_admin, fop, helpdesk |
| `customers.qr.manage` | admin, owner (terbitkan/cabut token) |
| `qr_scan.attendance` | teknisi, fop |
| `qr_scan_logs.view` | owner, atasan, admin (dashboard anomali) |

---

## 6. Rancangan per fungsi

### 6.1 Fungsi A — Pembayaran

Ini satu-satunya halaman **tanpa auth**, jadi paling rawan. Stiker QR tertempel di luar rumah pelanggan; siapa pun yang lewat bisa memindainya.

> ⚠ **R1: asumsi "di luar rumah" BELUM DIVERIFIKASI.** ONT FTTH umumnya dipasang **di dalam** rumah. Kalau begitu kenyataannya, seluruh takaran mitigasi di sub-bab ini perlu ditinjau ulang — bukan ditambal. Lihat §13 R1.

**Two-step disclosure:**

*Langkah 1 — halaman minimal (langsung tampil):*
```
Whusnet — Tagihan Pelanggan
Pelanggan  : MASUDAH Y****** F*****
ID         : C1X4ARQ000631
POP        : Ponorogo

Untuk melihat tagihan, masukkan 4 digit terakhir
nomor HP terdaftar:  [ ____ ]
```

*Langkah 2 — setelah verifikasi benar:*
Nama lengkap, alamat, rincian tagihan per periode, status, tombol Bayar.

Alasan gerbang 4 digit: orang lewat yang memindai stiker hanya melihat nama tersamar. Yang tahu nomor HP pelanggan (pelanggan sendiri, keluarga, kolektor) bisa lanjut. Ini bukan autentikasi kuat — dan tidak perlu, karena **halaman ini tidak punya aksi destruktif**: hanya baca tagihan + bayar (membayar tagihan orang lain tidak merugikan pemiliknya).

Guard yang tetap wajib:
- Rate limit `5 percobaan / 15 menit / IP+token`, lalu blokir 1 jam — mencegah brute-force 10.000 kombinasi 4 digit
- Percobaan gagal masuk `qr_scan_logs` dengan `result=verify_failed`
- Session verifikasi disimpan 30 menit, di-scope ke token (verifikasi satu pelanggan tidak membuka pelanggan lain)
- Tidak ada nomor HP lengkap, NIK, atau foto KTP di halaman ini — apa pun kondisinya

**Pembayaran QRIS** — tombol Bayar memanggil gateway untuk QRIS dinamis dengan nominal = sisa tagihan. Belum ada integrasi payment gateway di repo saat ini, jadi:

> **Fase 1 (tanpa gateway):** tombol Bayar diganti tampilan nomor rekening + nominal + tombol "Salin", plus tombol WhatsApp ke admin POP. Pencatatan pembayaran tetap manual lewat `/payments` seperti sekarang, melalui `Payment` + `PaymentObserver`. Fungsi QR tetap bernilai (pelanggan tahu tagihannya tanpa telepon admin) tanpa menunggu integrasi gateway.

### 6.1b PIN — lihat §6.5

Gerbang 4 digit HP di atas **digantikan PIN** begitu §6.5 diimplementasi. Alasannya di §6.5; ringkasnya: 4 digit terakhir nomor HP bukan rahasia (tetangga, kolektor, dan grup WhatsApp RT tahu), sementara PIN dirancang untuk hanya diketahui pelanggan.

### 6.2 Fungsi B — Absen teknisi

Fungsi paling sensitif. QR statis **bisa difoto** — teknisi bisa menyimpan foto QR semua pelanggannya lalu absen dari rumah. QR sendirian tidak membuktikan kehadiran; yang membuktikan adalah **gabungan** QR + GPS + jadwal + penugasan.

**Rantai guard, semuanya wajib lewat:**

```php
// QrAttendanceController::store()

1. Token valid & belum dicabut                    → else 404
2. Pelanggan dalam POP scope user                 → else 403  (EffectiveAccessService)
3. Cari Task: customer_id = X
              AND status = TERJADWAL
              AND scheduled_at <= hari ini
              AND user ∈ task.teamMembers
   Tidak ketemu → 422 "Tidak ada task terjadwal untuk pelanggan ini"
   Lebih dari satu → tampilkan pilihan (jangan tebak)

4. Geolocation (browser Geolocation API):
   - koordinat pelanggan ada?
     ├─ ya  → hitung haversine
     │        ├─ ≤ 150 m           → lolos
     │        ├─ 150–500 m         → lolos + flag `perlu_review`
     │        └─ > 500 m           → TOLAK (422), log out_of_radius
     └─ tidak → lolos, distance_meters = null, flag `tanpa_koordinat`

   Radius 150 m: akurasi GPS HP di area padat/dalam rumah rutin meleset
   50–100 m. Radius lebih ketat menghasilkan false-negative yang membuat
   teknisi kembali ke tombol manual — guard yang di-bypass = guard yang mati.

5. Delegasi ke TaskService::start($task, $actor)
   — SEMUA guard existing tetap berlaku, TIDAK di-bypass:
     • status harus TERJADWAL           (TaskService.php:158)
     • scheduled_at tidak boleh future  (TaskService.php:164)
     • tidak boleh ada task IN_PROGRESS lain di tim yang sama
                                        (TaskService.php:175-185)

6. Isi tasks.started_via='qr_scan' + koordinat
7. Tulis qr_scan_logs
```

**Yang eksplisit TIDAK dilakukan:**
- Tidak menduplikasi logika start di controller. `TaskService::start()` tetap satu-satunya penulis transisi `TERJADWAL → IN_PROGRESS`, konsisten dengan aturan repo bahwa business logic ada di Service. QR hanya jalur masuk baru, bukan alur paralel.
- Tidak menghapus tombol "Mulai Task" manual. Sinyal HP bisa mati, kamera bisa rusak, koordinat pelanggan bisa kosong. Manual tetap ada, tapi `started_via='manual'` → muncul di dashboard anomali untuk direview atasan.

**Deteksi anomali (halaman `qr_scan_logs.view`):**
- Teknisi dengan rasio `started_via='manual'` tinggi
- Scan sukses dengan `distance_meters > 150`
- Beberapa scan dari koordinat yang hampir identik untuk pelanggan berbeda-beda (indikasi absen borongan dari satu tempat)
- Scan token yang sudah dicabut

### 6.3 Fungsi C — Ticketing

Paling sederhana, paling cepat memberi nilai.

Helpdesk/FOP memindai QR → `GET /q/{token}/tiket` → form `tickets.create` dengan `customer_id` terkunci dan snapshot pelanggan ter-prefill.

Setelahnya masuk `TicketService::create()` apa adanya. **Tidak ada perubahan pada alur sinkronisasi Ticket ↔ FopTask ↔ Task.** QR hanya cara mengisi field pelanggan — `create()` tetap tidak membuat FopTask, dan FopTask tetap hanya lahir di `escalateToFop()`. Area itu tidak disentuh sama sekali.

Guard: POP scope + permission `tickets.create`.

### 6.4 Fungsi D — Login pelanggan (disiapkan, belum diimplementasi)

Belum ada portal pelanggan dan belum ada tabel autentikasi untuk pelanggan (`users` adalah tabel staf internal). Yang disiapkan sekarang hanya agar nanti tidak perlu cetak ulang stiker:

- Field `purpose` di `qr_scan_logs` sudah menerima nilai `login`
- Dispatcher `/q/{token}` sudah punya titik cabang untuk itu
- Token sudah bersifat opaque & revocable — prasyarat untuk auth

Saat nanti diimplementasi, **scan QR tidak boleh langsung mengautentikasi**. Stiker tertempel di luar rumah; siapa pun bisa memindainya. QR berperan sebagai identifier, dan faktor pembuktiannya adalah **PIN** — dirancang di §6.5.

---

## 6.5 PIN pelanggan

Ditambahkan 2026-08-07 atas permintaan: PIN di-generate otomatis bersamaan dengan QR, dipakai sebagai faktor login pelanggan.

**Layak, dan memang melengkapi celah yang tidak ditutup HMAC** — HMAC membuktikan QR itu asli, bukan membuktikan siapa yang memindainya. PIN menutup yang kedua.

Satu koreksi pada rumusan awal, dan ini menentukan seluruh nilai fiturnya.

### 6.5.1 PIN tidak boleh dicetak di stiker yang sama dengan QR

Permintaan awal: *"ketika QR di-generate, di bawahnya terdapat sebuah PIN."*

Masalahnya: stiker QR **tertempel di ONT / luar rumah pelanggan** — itu memang tujuannya, supaya teknisi bisa memindai tanpa masuk rumah. Kalau PIN tercetak di stiker yang sama:

```
Siapa pun yang memotret stiker  →  dapat QR DAN PIN sekaligus
                                →  dua faktor jadi satu faktor
                                →  PIN tidak menambah keamanan apa pun
```

Ini setara menempelkan password di samping username. Faktor "sesuatu yang Anda **tahu**" hanya bernilai kalau tidak tergeletak di tempat yang sama dengan "sesuatu yang Anda **punya**".

**Perbaikannya kecil: dua media, dua jalur pengiriman.**

| Media | Isi | Ditempel/diberikan | Sifat |
|---|---|---|---|
| **Stiker ONT** | QR + nama + CID + signature | Ditempel di ONT/luar rumah | Publik — memang harus bisa dipindai teknisi |
| **Kartu Pelanggan** | QR + **PIN** + petunjuk pakai | Diserahkan ke tangan pelanggan saat instalasi | Privat — disimpan pelanggan, seperti kartu ATM |

Keduanya membawa QR yang sama. Yang berbeda hanya: kartu memuat PIN, stiker tidak.

Halaman cetak (`customers.qr.print`) merender dua layout dari satu token — **stiker per POP untuk cetak massal**, dan **kartu per pelanggan** yang dicetak saat penerbitan PIN. PIN hanya bisa dicetak sekali (§6.5.3).

Jalur cadangan kalau kartu tidak praktis: kirim PIN via WhatsApp/SMS ke `primary_phone` terdaftar. Tetap terpisah dari stiker, dan mengikat PIN ke nomor pelanggan.

### 6.5.2 Spesifikasi PIN

| Aspek | Nilai | Alasan |
|---|---|---|
| Panjang | **6 digit** | 10⁶ kombinasi. 4 digit (10⁴) terlalu tipis walau ada rate limit — 10.000 tebakan masih terjangkau kalau limiter pernah bocor |
| Sumber acak | `random_int()` | CSPRNG. **Bukan** `rand()`/`mt_rand()` — keduanya bisa diprediksi dari beberapa keluaran |
| Penyimpanan | `Hash::make()` (bcrypt) | Kolom `pin_hash`; PIN plaintext tidak pernah masuk DB, log, atau audit trail |
| Ditampilkan | **Sekali saja**, saat diterbitkan | Setelah itu tidak bisa dilihat siapa pun, termasuk owner |
| Rotasi | Independen dari token QR | Reset PIN **tidak** mematikan stiker |
| Ditolak | `000000`, `123456`, 6 digit sama, tanggal lahir pelanggan | Generate ulang kalau kena — PIN otomatis pun bisa apes menghasilkan pola tebakan pertama |

**PIN tidak boleh masuk bahan HMAC.** Kalau `pin_hash` ikut ditandatangani, setiap reset PIN mengubah signature → seluruh stiker QR pelanggan itu mati → pelanggan lupa PIN berubah jadi kunjungan teknisi untuk menempel stiker baru. Token QR dan PIN sengaja dibuat **dua sumbu yang bisa dirotasi sendiri-sendiri**:

| Kejadian | Tindakan | Cetak ulang stiker? |
|---|---|---|
| Pelanggan lupa PIN | Terbitkan PIN baru | Tidak |
| PIN diduga bocor | Terbitkan PIN baru | Tidak |
| Stiker hilang/rusak | Terbitkan token baru | Ya |
| Pelanggan pindah POP | Terbitkan token baru | Ya |

### 6.5.3 Alur penerbitan

Dipicu saat pelanggan masuk `WAITING_INSTALLATION` (§7.2), bukan aksi admin lepas.

```
Pelanggan → WAITING_INSTALLATION  (Task PSB dijadwalkan)
      │
      ├─ sudah punya token aktif? → BERHENTI, pakai yang lama
      │     (instalasi bisa diulang — WorkflowTransition.php:37-40 —
      │      tanpa guard ini satu pelanggan mengumpulkan banyak PIN
      │      dan tidak ada yang tahu kartu mana yang dipegang)
      │
      ├─ token ULID + signature   → disimpan
      ├─ PIN 6 digit (random_int) → HANYA hash yang disimpan
      │                             pin_must_change = true
      │
      └─ Halaman hasil, SEKALI TAMPIL:
            ┌──────────────────────────────┐
            │  [QR]                        │
            │  MASUDAH YUNI FITRI          │
            │  RQ000631 · Ponorogo         │
            │  PIN: 4 8 2 9 1 7            │
            │  Ganti PIN saat login pertama│
            └──────────────────────────────┘
            [Cetak Kartu]  [Cetak Stiker]  [Selesai]
                    │
                    └─ setelah "Selesai" ditekan atau halaman ditinggalkan,
                       PIN TIDAK BISA dilihat lagi — hanya bisa diterbitkan ulang

Teknisi membawa: STIKER (tanpa PIN) + KARTU (dengan PIN, amplop tertutup)
```

Guard di halaman ini:
- PIN plaintext hidup di **response saja**, tidak pernah masuk session, cache, flash message, atau log
- Halaman `no-store` (cegah tersimpan di cache browser/proxy) dan `noindex`
- Penerbitan tercatat di audit log — **isinya "PIN diterbitkan oleh X untuk pelanggan Y", bukan PIN-nya**
- Butuh permission `customers.qr.manage`
- Kirim WhatsApp hanya ke `primary_phone` terdaftar; nomor tidak boleh diketik bebas saat itu juga (kalau salah ketik, PIN terkirim ke orang lain)

### 6.5.4 Alur verifikasi

```
POST /q/{code}/masuk   { pin }
  │
  ├─ [1] Token + signature valid           → else 404  (§5)
  ├─ [2] pin_locked_until masih berlaku    → 429 + sisa waktu
  ├─ [3] Hash::check(pin, pin_hash)
  │        gagal → pin_failed_attempts++
  │                ├─ ≥ 5  → pin_locked_until = now()+15 menit
  │                │          + notifikasi ke pelanggan & admin POP
  │                └─ log result='pin_failed'
  │                → 422 "PIN salah" (TANPA menyebut sisa percobaan —
  │                  informasi itu membantu penyerang mengatur laju)
  │
  └─ berhasil → pin_failed_attempts = 0
                sesi pelanggan dibuat, di-scope ke customer_id ini saja
                log result='success', purpose='login'
```

Tiga lapis anti brute-force, karena masing-masing punya titik gagal sendiri:

1. **`pin_failed_attempts` di DB** — bertahan menembus cache flush dan restart
2. **Rate limiter per IP** — mencegah satu IP menyerang banyak pelanggan sekaligus (limiter per-token saja tidak melihat pola ini)
3. **Lockout 15 menit setelah 5 gagal** — memaksa 10⁶ tebakan butuh ~5.700 tahun

Notifikasi ke pelanggan saat lockout memberi tahu **pemiliknya** kalau ada yang mencoba menebak — sesuatu yang tidak akan pernah muncul di log yang tidak dibaca siapa pun.

### 6.5.5 Lupa PIN

Tidak ada "lihat PIN" — `pin_hash` searah. Yang ada hanya penerbitan ulang. **Alur operasional lengkapnya di §7.3 Kasus 2**; ringkasnya:

**Jalur A — via admin (utama).** Pelanggan menghubungi helpdesk/admin POP → **verifikasi identitas minimal 2 faktor** (NIK, alamat, pembayaran terakhir — nomor HP saja tidak cukup) → "Terbitkan Ulang PIN" → `pin_version++`, `pin_failed_attempts` di-reset, `pin_must_change=true`. **Token QR tidak berubah, stiker tetap sah, tidak perlu kunjungan teknisi.**

**Jalur B — mandiri via OTP (kalau nanti ada gateway SMS/WA).** Scan QR → "Lupa PIN" → OTP ke `primary_phone` → verifikasi → PIN baru tampil sekali. Tanpa perlu admin.

> OTP di rancangan sebelumnya (§6.4 lama) **tidak dibuang** — hanya berpindah peran: dari faktor login utama menjadi mekanisme pemulihan. PIN lebih cocok sebagai faktor harian karena tidak memerlukan biaya SMS per login dan tetap bekerja saat pelanggan tidak memegang HP terdaftar.

### 6.5.5b Wajib ganti PIN saat login pertama

PIN awal dicetak di kantor dan **dibawa teknisi** ke rumah pelanggan (§7.2). Selama jendela itu, PIN diketahui orang selain pelanggan. `pin_must_change = true` membuat pengetahuan itu kedaluwarsa sendiri:

```
Login pertama dengan PIN cetak
   │
   ├─ pin_must_change = true → paksa halaman "Buat PIN Baru"
   │      (tidak bisa dilewati; tidak ada akses ke halaman lain
   │       sebelum PIN diganti)
   │
   ├─ PIN baru: 6 digit, tidak boleh sama dengan PIN cetak,
   │            tolak pola lemah (000000, 123456, 6 digit sama)
   │
   └─ pin_must_change = false
      pin_first_used_at = now()
      pin_version++
```

**Pemantauan:** pelanggan dengan `pin_must_change = true` selama >30 hari berarti PIN cetaknya belum pernah dipakai dan masih diketahui pihak lain. Muncul di dashboard sebagai "PIN belum diaktivasi" untuk ditindaklanjuti admin POP — bukan sebagai kesalahan, tapi sebagai risiko yang menua diam-diam kalau tidak dilihat.

### 6.5.6 Dampak ke halaman tagihan (§6.1)

PIN **menggantikan gerbang 4 digit nomor HP**. Peningkatan yang jelas: 4 digit terakhir nomor HP bukan rahasia — tetangga tahu, kolektor tahu, grup WhatsApp RT tahu. PIN dirancang hanya diketahui pelanggan.

Sampai PIN tergelar merata, dua jalur berjalan berdampingan:

| Kondisi pelanggan | Gerbang halaman tagihan |
|---|---|
| Sudah punya PIN (`pin_hash` terisi) | PIN 6 digit |
| Belum punya PIN | 4 digit terakhir HP (jalur lama) |

Jalur lama dihapus setelah seluruh pelanggan aktif punya PIN. Menghapusnya lebih awal membuat pelanggan yang belum dapat kartu kehilangan akses ke tagihannya sendiri.

### 6.5.7 Yang PIN tidak lakukan

Batas ini perlu eksplisit supaya PIN tidak dianggap menyelesaikan masalah yang bukan bagiannya:

- **PIN tidak dipakai untuk absen teknisi.** Teknisi tidak tahu — dan tidak boleh tahu — PIN pelanggan. Meminta PIN saat teknisi datang berarti melatih pelanggan menyebutkan PIN-nya ke petugas lapangan, persis kebiasaan yang dieksploitasi penipuan bermodus "petugas". Absen tetap mengandalkan geolocation + penugasan + jadwal (§6.2).
- **PIN tidak menggantikan HMAC.** HMAC membuktikan QR asli; PIN membuktikan pemindainya pelanggan. Dua pertanyaan berbeda.
- **PIN tidak dipakai staf internal.** Staf tetap login lewat `users` + RBAC seperti biasa.

---

## 7. Siklus hidup: dua media, satu QR

### 7.1 Dua media — apa persisnya yang berbeda

Keduanya membawa **token yang sama persis**. Satu token, satu signature, satu URL. Yang berbeda hanya **apa yang tercetak di sekitarnya** dan **ke mana benda itu pergi.**

```
                    SATU TOKEN
         01JZ8K3M9XQ7VN4T2P6R8W5FDC.K7M2QX9P4T
                         │
          ┌──────────────┴──────────────┐
          │                             │
    ┌─────▼──────────┐           ┌──────▼─────────────┐
    │  STIKER ONT    │           │  KARTU PELANGGAN   │
    ├────────────────┤           ├────────────────────┤
    │  [QR]          │           │  [QR]  (sama)      │
    │  MASUDAH Y. F. │           │  MASUDAH YUNI F.   │
    │  RQ000631      │           │  RQ000631·Ponorogo │
    │  Ponorogo      │           │                    │
    │  K7M2QX9P4T    │           │  PIN: 4 8 2 9 1 7  │
    │                │           │  (ganti saat login │
    │  TANPA PIN     │           │   pertama)         │
    └───────┬────────┘           └─────────┬──────────┘
            │                              │
    Ditempel di ONT              Diserahkan ke tangan
    (luar rumah, publik)         pelanggan, disimpan
            │                              │
    Dipakai TEKNISI              Dipakai PELANGGAN
    untuk absen                  untuk login & tagihan
```

**Kenapa QR ada di dua-duanya, bukan cuma stiker?**

Kalau QR hanya di stiker, pelanggan harus keluar rumah dan memindai ONT setiap kali mau cek tagihan. Kalau QR hanya di kartu, teknisi harus meminta kartu ke pelanggan setiap kunjungan — padahal sering pelanggan tidak di rumah. Menaruh QR di dua-duanya menghilangkan kedua gesekan itu, dan **tidak menambah risiko** karena QR memang bukan rahasia: keamanannya ada di HMAC (anti-palsu), PIN (login), dan geolocation (absen) — bukan pada kerahasiaan token.

Konsekuensi yang berguna dan tidak langsung terlihat: **kehilangan salah satu media tidak melumpuhkan semua fungsi**, karena masing-masing masih memegang QR. Ini yang membentuk seluruh alur di §7.3.

### 7.2 Penerbitan — terikat ke workflow pemasangan

Keputusan: **kartu dicetak dan diserahkan saat instalasi**, jadi token wajib sudah ada **sebelum teknisi berangkat.**

Titik penerbitan: saat pelanggan masuk `WAITING_INSTALLATION` (`app/Enums/WorkflowTransition.php:12`) — yaitu ketika Task PSB dijadwalkan.

```
registered → waiting_survey → surveyed → waiting_acc
                                              │
                                    WAITING_INSTALLATION  ◄── TOKEN + PIN TERBIT
                                              │             di sini
                                              │
                            Admin cetak: stiker + kartu (PIN)
                            Teknisi ambil sebelum berangkat
                                              │
                                    INSTALLATION_IN_PROGRESS
                                              │
                            Teknisi di lokasi:
                              ├─ tempel STIKER di ONT
                              ├─ serahkan KARTU ke pelanggan
                              └─ jelaskan: ganti PIN saat login pertama
                                              │
                                        INSTALLED
                                              │
                                    VERIFICATION_ADMIN → ACTIVE
```

**Kenapa `WAITING_INSTALLATION`, bukan `INSTALLED`?** Kalau menunggu `INSTALLED`, kartu baru bisa dicetak setelah teknisi pulang — berarti butuh kunjungan kedua hanya untuk menyerahkan kartu. Menerbitkan di `WAITING_INSTALLATION` membuat kartu ikut berangkat bersama teknisi.

**Kenapa tidak lebih awal (`registered`/`surveyed`)?** Pelanggan bisa batal atau ditolak sebelum pemasangan. Menerbitkan lebih awal menghasilkan token untuk pelanggan yang tidak pernah terpasang.

**Penerbitan harus idempoten.** `WorkflowTransition` mengizinkan kembali ke `WAITING_INSTALLATION` dari `INSTALLATION_IN_PROGRESS`, `INSTALLED`, `VERIFICATION_ADMIN`, dan `REVISION_INSTALLATION` (`WorkflowTransition.php:37-40`) — instalasi bisa diulang beberapa kali. Tanpa guard, satu pelanggan bisa mengumpulkan beberapa token dan beberapa PIN, dan tidak ada yang tahu kartu mana yang dipegang pelanggan.

> **Aturan:** `issueForInstallation()` mengecek token aktif lebih dulu. Sudah ada → **tidak menerbitkan apa pun**, kembalikan yang lama. Penerbitan ulang hanya lewat aksi eksplisit admin (§7.3), tidak pernah sebagai efek samping transisi status.

**Titik lemah yang harus diakui: teknisi memegang PIN.** Kartu dicetak di kantor lalu dibawa teknisi — ada jendela waktu di mana orang selain pelanggan memegang PIN secara fisik. Tiga mitigasi:

1. **`pin_must_change = true`** — login pertama **wajib** ganti PIN. PIN cetak adalah PIN **aktivasi sekali pakai**, bukan PIN permanen. Ini yang membuat pengetahuan teknisi kedaluwarsa dengan sendirinya.
2. **Kartu diserahkan dalam amplop tertutup** — mengurangi PIN terbaca sambil lalu.
3. **`pin_first_used_at` dipantau** — pelanggan yang tidak pernah login berarti PIN awalnya masih berlaku dan masih diketahui orang lain. Muncul di dashboard sebagai "PIN belum diaktivasi > 30 hari" untuk ditindaklanjuti.

### 7.3 Skenario kehilangan

Bagian yang paling mudah salah rancang. Kuncinya membedakan **cetak ulang** dari **terbitkan ulang** — dua hal yang sering dianggap sama padahal akibatnya berlawanan.

| | Cetak ulang (reprint) | Terbitkan ulang (reissue) |
|---|---|---|
| Token | **Tetap** | Dicabut, diganti baru |
| Media lain | **Tidak terpengaruh** | **Ikut mati, wajib ikut dicetak ulang** |
| Kapan | Media rusak/hilang biasa | Ada dugaan penyalahgunaan |

> **Jebakan:** kalau stiker hilang lalu Anda *terbitkan ulang* token, **kartu pelanggan ikut mati** — padahal pelanggan tidak kehilangan apa pun. Pelanggan tiba-tiba tidak bisa login karena stikernya yang hilang. Untuk kehilangan biasa, **cetak ulang, jangan terbitkan ulang.**

Ini bisa dilakukan karena token bukan rahasia: absen dilindungi geolocation + penugasan + jadwal, bukan oleh kerahasiaan QR. Mencabut token saat stiker hilang tidak menambah keamanan, hanya menambah kerusakan.

#### Kasus 1 — Stiker ONT hilang/rusak/ONT diganti

**Dampak: kecil.** Pelanggan tidak terpengaruh sama sekali — kartunya masih memuat QR yang sama, login dan cek tagihan tetap jalan. Yang terganggu hanya absen teknisi jalur QR, dan itu punya fallback manual (`started_via='manual'`).

```
Lapor: pelanggan / teknisi saat kunjungan
   │
   ├─ Admin buka detail pelanggan → "Cetak Ulang Stiker"
   │     ├─ TOKEN SAMA — tidak dicabut
   │     ├─ signature sama, kartu pelanggan TIDAK terpengaruh
   │     └─ log: sticker_reprinted
   │
   └─ Penempelan: TIDAK dibuatkan task khusus.
         Stiker dititipkan ke kunjungan terjadwal berikutnya
         (MTN/gangguan). Kalau tidak ada dalam ~30 hari,
         muncul di daftar "Stiker menunggu tempel" untuk
         digabung ke kunjungan area yang sama.
```

**Kenapa tidak bikin task sendiri?** Mengirim teknisi hanya untuk menempel stiker lebih mahal daripada manfaatnya, sementara satu-satunya fungsi yang terganggu punya fallback yang bekerja. Menumpang kunjungan yang memang sudah terjadwal menghilangkan biaya itu.

#### Kasus 2 — Kartu pelanggan hilang (dan/atau lupa PIN)

**Dampak: pelanggan kehilangan PIN.** QR masih terjangkau — tertempel di ONT. Jadi yang perlu diganti **hanya PIN**, tidak perlu kunjungan sama sekali.

```
Pelanggan lapor ke Helpdesk / admin POP
   │
   ├─ [1] VERIFIKASI IDENTITAS  ← langkah paling penting
   │        Cocokkan minimal 2 dari:
   │          • NIK (customers.identity_number)
   │          • alamat lengkap
   │          • nominal/tanggal pembayaran terakhir
   │        Nomor HP saja TIDAK CUKUP — nomor bisa diketahui
   │        tetangga, dan inilah titik masuk favorit
   │        rekayasa sosial untuk membajak akun pelanggan.
   │
   ├─ [2] "Terbitkan Ulang PIN"   (permission customers.qr.manage)
   │        ├─ pin_version++
   │        ├─ pin_failed_attempts = 0, pin_locked_until = null
   │        ├─ pin_must_change = true
   │        └─ TOKEN & SIGNATURE TIDAK BERUBAH
   │             → stiker ONT tetap sah
   │             → tidak ada kunjungan teknisi
   │
   └─ [3] PENYERAHAN — pilih satu:
            a. WhatsApp/SMS ke primary_phone terdaftar
               (nomor TIDAK boleh diketik bebas saat itu)
            b. Diambil di kantor POP dengan identitas
            c. Kartu baru dititipkan ke kunjungan berikutnya

            TIDAK PERNAH: didiktekan lewat telepon ke nomor
            yang menelepon masuk — penelepon belum tentu pelanggan.
```

Notifikasi ke `primary_phone` dikirim **setiap** PIN diterbitkan ulang, bahkan kalau penyerahannya lewat jalur lain. Kalau bukan pelanggan yang meminta, dialah yang pertama tahu.

#### Kasus 3 — Keduanya hilang

Stiker hilang **dan** kartu hilang: cetak ulang stiker (token sama) + terbitkan ulang PIN. Tetap **bukan** reissue token — tidak ada indikasi penyalahgunaan, hanya dua benda hilang.

#### Kasus 4 — Dugaan penyalahgunaan → satu-satunya reissue token

Pemicunya bukan "hilang", tapi **jejak di `qr_scan_logs`**: `result='bad_signature'` berulang, scan dari lokasi yang tidak masuk akal, atau laporan pelanggan bahwa ada yang mengaku petugas memindai QR-nya.

```
Admin/owner → "Cabut & Terbitkan Ulang" (reason wajib diisi)
   │
   ├─ token lama: revoked_at diisi — TIDAK dihapus (jejak audit)
   ├─ token baru + signature baru + PIN baru
   │
   └─ WAJIB cetak ulang KEDUA media:
        ├─ stiker (perlu kunjungan — di sini task MTN memang wajar dibuat)
        └─ kartu
      Scan QR lama → 404 + log result='token_revoked'
```

#### Kasus 5 — Re-homing POP

Otomatis, bukan permintaan siapa pun: `CustomerObserver::updated()` mendeteksi `pop_id` berubah → token dicabut (`reason='pop_changed'`) → notifikasi admin POP. Signature terikat `pop_id` lama, jadi QR lama ditolak eksplisit sebagai `pop_mismatch` (§5) — bukan gagal diam-diam. Kedua media wajib dicetak ulang. Sangat jarang (§2.1).

#### Kasus 6 — Pelanggan terminated

Token dicabut otomatis via `CustomerObserver`. Tidak ada cetak apa pun.

### 7.4 Ringkasan tindakan

| Kejadian | Token | PIN | Cetak stiker | Cetak kartu | Perlu kunjungan? |
|---|---|---|---|---|---|
| Pemasangan baru | Terbit | Terbit | Ya | Ya | Sudah terjadwal (PSB) |
| Stiker hilang/rusak | **Tetap** | Tetap | Ya | Tidak | Titip kunjungan berikutnya |
| Kartu hilang / lupa PIN | **Tetap** | Baru | Tidak | Ya | **Tidak** |
| Keduanya hilang | **Tetap** | Baru | Ya | Ya | Titip kunjungan berikutnya |
| Dugaan penyalahgunaan | **Dicabut** | Baru | Ya | Ya | Ya (task MTN) |
| Pindah POP | **Dicabut** | Baru | Ya | Ya | Ya |
| Terminated | Dicabut | — | — | — | — |

### 7.5 Rotasi `QR_HMAC_SECRET`

Kalau secret bocor, seluruh QR yang tercetak harus dianggap dapat dipalsukan. Rotasi berarti cetak ulang semua stiker **dan** semua kartu, jadi harus didukung transisi bertahap: `config('qr.secret')` menandatangani token baru, `config('qr.previous_secrets')` (array) masih diterima saat verifikasi selama masa transisi. Tanpa ini, rotasi = seluruh pelanggan di semua cabang kehilangan QR pada detik yang sama. Kolom `signature` yang tersimpan memudahkan audit media mana yang masih memakai secret lama.

### 7.6 Halaman cetak

`customers.qr.print` merender dua layout dari token yang sama:

| Layout | Isi | Pemakaian |
|---|---|---|
| **Stiker** | QR + nama + REQ ID + POP + signature, **tanpa PIN** | Grid siap potong (mis. 12/A4), cetak massal per POP, filter status + POP scope |
| **Kartu** | QR + PIN + petunjuk pakai | Per pelanggan, hanya saat PIN diterbitkan (sekali tampil, `no-store`) |

**Label manusia di stiker pakai REQ ID, bukan CID.** Token terbit saat `WAITING_INSTALLATION` — pelanggan belum aktif, `cid` masih kosong, jadi `display_id` saat itu bernilai `C00RQ######`. Setelah aktif dan dapat distribusi, `display_id` berubah jadi CID penuh. Kalau stiker mencetak `display_id`, labelnya jadi basi begitu pelanggan aktif. REQ ID permanen (§2.1), jadi tidak pernah basi.

> Ini murni kosmetik — label tercetak tidak ikut dalam verifikasi apa pun. Tapi label yang tidak cocok dengan layar membuat admin ragu apakah stikernya benar, dan keraguan itu berujung cetak ulang yang tidak perlu.

---

## 8. Ringkasan keamanan

| Ancaman | Mitigasi |
|---|---|
| Tebak/enumerasi token | ~~ULID acak 26 char (~128 bit)~~ **⚠ R2: klaim ini SALAH — ULID = 48-bit timestamp + 80-bit random, dan sortable. Ganti ke `random_bytes(16)`** + HMAC 50 bit; 404 generik; rate limit |
| **QR dipalsukan/dihitung sendiri** | **HMAC-SHA256 dengan `QR_HMAC_SECRET` — secret tidak ada di DB, jadi akses baca DB saja tidak cukup untuk menerbitkan QR baru** |
| **Baris token disisipkan lewat SQL injection / akses DB langsung** | **Baris sisipan tidak punya signature valid; ditolak di langkah [3] dispatcher** |
| **Tabrakan REQ ID antar cabang** | **`pop_id` masuk bahan HMAC + composite unique `(pop_id, customer_code)`** |
| QR difoto → absen fiktif | Geolocation + radius + cek penugasan tim + cek jadwal + log anomali. **HMAC tidak membantu di sini** — QR asli punya signature asli |
| QR difoto → intip data pelanggan | Halaman publik hanya nama tersamar; data penuh butuh verifikasi 4 digit HP |
| Brute-force 4 digit HP | 5 percobaan/15 menit per IP+token, lalu blokir 1 jam; semua percobaan dicatat |
| Timing attack pada perbandingan signature | `hash_equals()`, bukan `===` |
| **Stiker difoto → login sebagai pelanggan** | **PIN dicetak di kartu terpisah, TIDAK di stiker. Foto stiker tidak memuat PIN** |
| **Brute-force PIN** | **6 digit + lockout 15 menit setelah 5 gagal + hitungan di DB (tahan cache flush) + rate limit per IP** |
| **PIN bocor lewat dump/backup DB** | **Disimpan sebagai bcrypt hash; PIN plaintext tidak pernah masuk DB, log, session, atau audit trail** |
| **Orang dalam melihat PIN pelanggan** | **PIN hanya tampil sekali saat diterbitkan; tidak ada layar "lihat PIN" untuk role mana pun** |
| **Rekayasa sosial "petugas minta PIN"** | **PIN tidak pernah diminta teknisi — absen tidak memakai PIN (§6.5.7)** |
| Stiker lama beredar setelah ganti | Token revocable; scan token dicabut → log + notifikasi admin |
| Kebocoran lintas cabang | Semua endpoint terautentikasi lewat `EffectiveAccessService::getAllowedPopIds()` |
| Scraping halaman tagihan | Rate limit publik + tidak ada endpoint listing (hanya akses per-token) |

**Catatan konfigurasi wajib:**
- Halaman publik **harus HTTPS**. Geolocation API browser diblokir di HTTP non-localhost, dan verifikasi 4 digit HP lewat HTTP = terkirim polos.
- `QR_HMAC_SECRET` di `.env`, minimal 32 byte acak (`openssl rand -base64 32`), tidak masuk repo, **berbeda dari `APP_KEY`**.
- Guard di `AppServiceProvider::boot()`: tolak boot kalau `QR_HMAC_SECRET` kosong di production. Secret kosong gagal secara diam-diam — `hash_hmac` tetap menghasilkan nilai dan semua QR tampak normal, padahal signature-nya bisa dihitung siapa saja.
- `QR_HMAC_SECRET` **tidak boleh** masuk `qr_scan_logs`, pesan exception, atau payload Telegram (`TelegramBotService`).

---

## 9. Dependensi

Belum ada library QR di `composer.json`. Yang dibutuhkan:

```bash
composer require endroid/qr-code
```

Alternatif `bacon/bacon-qr-code` (lebih ringan, tanpa dependensi GD kalau pakai renderer SVG). SVG lebih baik untuk cetak — tajam di ukuran apa pun, tidak butuh ekstensi GD/Imagick.

> Penambahan dependensi butuh persetujuan sebelum dieksekusi.

Sisi klien: **tidak perlu library baru.** Fungsi A dipindai kamera HP bawaan. Fungsi B & C butuh pemindai di dalam browser — pakai `BarcodeDetector` API (native Chrome/Edge Android, tanpa library) dengan fallback input manual `customer_code` + signature untuk browser yang belum mendukung. Ini menghindari menambah beban JS ke `layouts/app.blade.php` yang saat ini hanya memuat Alpine via CDN.

**Base32:** PHP tidak punya `base32_encode()` bawaan. Tulis helper kecil di `app/Helpers/` (mengikuti pola `FormatHelper`) — ~20 baris, tidak perlu dependensi. Base32 dipilih ketimbang base64 karena aman di URL tanpa escaping, dan alfabetnya (RFC 4648, tanpa `0`/`1`/`8`) mengurangi salah baca saat teknisi mengetik signature manual dari stiker.

**Kripto:** `hash_hmac()` dan `hash_equals()` ada di PHP core — tidak ada dependensi tambahan.

---

## 10. Rencana implementasi

Bukan satu sprint. Dipecah supaya tiap fase punya nilai sendiri dan bisa dihentikan tanpa menyisakan setengah jadi.

### Fase 1 — Fondasi + Ticketing (risiko terendah) ⚠ R11

> ⚠ **R11:** label lama "nilai tercepat" menyesatkan — prefill tiket hanya menghemat ~10 detik dibanding kolom cari yang sudah ada. Nilai sesungguhnya ada di Fase 2 & 3. Urutannya tetap benar (fondasi dulu), labelnya yang dikoreksi.
> ⚠ **R15:** kalau ruang lingkup harus dipangkas, lihat §13 R15 — versi minimal hanya butuh absen teknisi, tanpa PIN/kartu/HMAC/halaman tagihan.
- `config/qr.php` + `QR_HMAC_SECRET` + guard boot production
- Helper base32
- Migration `customer_qr_tokens`, `qr_scan_logs`
- `CustomerQrTokenService` — `issue()`, `revoke()`, `signature()`, `verify()`, `resolve()`
- `CustomerQrTokenObserver` (invariant satu token aktif) + hook `CustomerObserver` untuk `pop_id` berubah & `terminated`
- `QrScanController::dispatch()` + resolusi POP scope
- Halaman lihat/cetak QR + permission baru
- Fungsi C (ticketing prefill)

**Test wajib fase ini** (unit untuk kripto, feature untuk sisanya):
- `signature()` deterministik untuk input sama; berubah kalau `pop_id`, `customer_code`, atau `token` berubah
- **Dua pelanggan beda POP dengan `customer_code` identik → signature BERBEDA** (regresi langsung terhadap kasus Winda/Endah di `ID_NUMBERING_RULES.md:389`)
- Signature salah → 404 + `qr_scan_logs.result='bad_signature'`
- Token dicabut → 404 + `result='token_revoked'`
- `pop_id` pelanggan diubah → token lama ditolak `result='pop_mismatch'`, token otomatis tercabut
- Semua kegagalan mengembalikan body 404 yang identik (tidak membocorkan penyebab)
- Pelanggan luar POP scope → 403
- Invariant satu token aktif ditegakkan dari jalur non-HTTP (langsung `Model::create()`, meniru artisan/tinker)

### Fase 2 — Absen teknisi (nilai tertinggi, risiko tertinggi)
- **Prasyarat:** audit coverage `customers.latitude/longitude`; kalau rendah, backfill dulu atau jalankan radius soft
- Kolom `started_via` + koordinat di `tasks`
- `QrAttendanceController` + pemindai browser
- Dashboard anomali
- Test: happy path, di luar radius, task bukan milik tim, tidak ada task terjadwal, task sudah in_progress, tanpa koordinat pelanggan

### Fase 3 — PIN + Halaman tagihan publik
- Kolom PIN di `customer_qr_tokens` (`pin_hash`, `pin_issued_at`, `pin_version`, `pin_failed_attempts`, `pin_locked_until`)
- `CustomerQrTokenService::issuePin()` / `verifyPin()` + lockout
- Halaman terbitkan PIN (tampil sekali, `no-store`) + layout cetak kartu pelanggan
- Halaman publik + gerbang PIN (fallback 4 digit HP untuk pelanggan yang belum punya PIN)
- Tampilan tagihan (fase tanpa gateway: rekening + salin + WhatsApp admin)

**Test wajib fase ini:**
- PIN plaintext **tidak pernah** muncul di DB, log, session, audit trail, atau response kedua
- `Hash::check()` benar/salah; PIN salah → `pin_failed_attempts` naik
- 5 gagal → `pin_locked_until` terisi, percobaan ke-6 ditolak 429 **walau cache di-flush**
- Reset PIN **tidak** mengubah token/signature — QR lama tetap valid (regresi langsung terhadap risiko "reset PIN mematikan stiker")
- **Cetak ulang stiker tidak mencabut token** — kartu pelanggan tetap berfungsi (regresi terhadap jebakan §7.3)
- **Penerbitan idempoten**: transisi berulang ke `WAITING_INSTALLATION` tidak membuat token/PIN kedua
- **`pin_must_change`**: login pertama dipaksa ke halaman ganti PIN, halaman lain tidak bisa diakses sebelum diganti; PIN baru ditolak kalau sama dengan PIN cetak
- Terbitkan ulang token **tidak** ikut me-reset PIN kecuali diminta
- Pesan "PIN salah" tidak membocorkan sisa percobaan
- Halaman tagihan tidak membocorkan PII sebelum gerbang dilewati
- Pelanggan tanpa `pin_hash` masih bisa lewat jalur 4 digit HP

### Fase 4 — Payment Gateway (masih tahap diskusi & perencanaan)
Integrasi payment gateway direncanakan tapi **belum diputuskan** — vendor, model integrasi, dan alur rekonsiliasi masih dibahas terpisah. Fase ini tidak dijadwalkan sampai keputusan itu keluar.

Yang penting: **Fase 1–3 tidak menunggu ini.** Rancangan sudah memisahkan QR (identifier pelanggan) dari instrumen pembayaran, jadi saat gateway nanti dipilih, yang berubah hanya isi tombol "Bayar" di halaman tagihan — token, signature, stiker tercetak, dan ketiga fungsi lain tidak tersentuh. Tidak ada stiker yang perlu dicetak ulang karena keputusan gateway.

Yang perlu disiapkan saat pembahasan gateway nanti:
- Rekonsiliasi ke `Payment` + `Invoice` — `PaymentObserver::creating()` menolak nominal ≤ 0 dari semua jalur, callback gateway tidak dikecualikan
- Idempotensi callback — unique index anti-dobel per periode sudah ada (`add_duplicate_guard_indexes_to_invoices_and_payments`), callback gateway harus menghormatinya
- Verifikasi signature callback dari gateway (mekanisme terpisah dari HMAC QR, jangan pakai secret yang sama)

### Fase 5 — Login pelanggan
Blocked sampai portal pelanggan ada. **PIN sudah siap dari Fase 3** — yang kurang tinggal tabel sesi/auth pelanggan dan halaman portalnya, bukan mekanisme pembuktian identitasnya.

---

## 11. Yang perlu diputuskan sebelum mulai

0. **⚠ R1 — ONT dipasang di dalam atau di luar rumah?** Belum diverifikasi, padahal seluruh threat model berdiri di atasnya. Tanya FOP sebelum apa pun dibangun
1. **Coverage `latitude`/`longitude` pelanggan aktif** — menentukan apakah Fase 2 bisa jalan dengan radius keras atau harus soft. **⚠ R10:** cek **dua** kolom — `customers.latitude` dan `customer_addresses.latitude` — sebelum menyimpulkan coverage
2. **Radius toleransi** — usulan 150 m; perlu konfirmasi dari FOP yang tahu kondisi lapangan
3. **Media & ukuran stiker** — QR sekarang versi 4 (33×33 modul); memengaruhi ECC level dan ukuran cetak minimum
4. **Pelanggan legacy dengan `customer_code` kosong/bermasalah** — token butuh `(pop_id, customer_code)` yang valid. Cek:
   ```sql
   SELECT COUNT(*) FROM customers
   WHERE customer_code IS NULL OR customer_code = '' OR pop_id IS NULL;
   ```
5. **Payment gateway** — tidak memblokir apa pun di Fase 1–3, dibahas terpisah

---

## 12. Log keputusan

Semua keputusan desain beserta alasannya. Ditulis supaya pertanyaan "kenapa dulu diputuskan begini" tidak perlu digali ulang dari percakapan.

| Tanggal | Keputusan | Alasan |
|---|---|---|
| 2026-08-07 | Anchor identitas = `customers.customer_code` (REQ ID), **bukan** `display_id` | REQ ID permanen seumur hidup pelanggan apa pun statusnya (gagal/putus/suspend/reject). `display_id` hanya bungkus yang berubah RQ↔CID — lihat §2.1 |
| 2026-08-07 | `pop_id` **wajib** ikut jadi bahan HMAC | `customer_code` cuma unik per POP (composite unique `(pop_id, customer_code)`). Tanpa `pop_id`, 2 pelanggan beda cabang dengan RQ sama menghasilkan QR identik — kasus nyata Winda/Endah `RQ000042` |
| 2026-08-07 | `full_name` **tidak** ikut jadi bahan HMAC | Mutable (koreksi typo mematikan QR), tidak menambah keunikan, dan PII. Tetap dicetak di stiker untuk dibaca manusia |
| 2026-08-07 | Opaque token **dan** HMAC — keduanya wajib lolos | Token bisa dicabut per pelanggan; HMAC tidak bisa dipalsukan. Masing-masing menutup celah yang tidak ditutup yang lain — §2.2 |
| 2026-08-07 | Signature 10 char base32 (50 bit), bukan 4 char | 20 bit hanya cukup sebagai checksum salah-ketik, bukan kontrol keamanan |
| 2026-08-07 | `QR_HMAC_SECRET` terpisah dari `APP_KEY` | Rotasi `APP_KEY` saat insiden tidak boleh mematikan seluruh stiker yang sudah tercetak dan tertempel |
| 2026-08-07 | Re-homing POP → cabut token + cetak ulang stiker | Dikonfirmasi pemilik produk: re-homing sangat jarang. Menerima biaya jarang ini lebih murah daripada cacat identitas permanen pada data yang sudah ada |
| 2026-08-07 | QR ≠ QRIS; payment gateway di fase terpisah | QR = identifier pelanggan, bukan instrumen bayar. Keputusan gateway tidak memaksa cetak ulang stiker |
| 2026-08-07 | Token **tidak** auto-generate saat pelanggan dibuat | Pelanggan `pending`/`draft` bisa batal sebelum instalasi. Token hanya berguna kalau stikernya benar-benar dicetak |
| 2026-08-07 | Tombol "Mulai Task" manual **tidak** dihapus | Sinyal/kamera/koordinat bisa gagal. Guard yang di-bypass = guard yang mati. `started_via` membuat jalur manual terlihat di dashboard anomali |
| 2026-08-07 | Halaman tagihan publik pakai gerbang 4 digit HP | Stiker tertempel di luar rumah. Bukan auth kuat — dan tidak perlu, karena halaman ini tidak punya aksi destruktif |
| 2026-08-07 | Radius absen 150 m (usulan, belum final) | Akurasi GPS HP di area padat rutin meleset 50–100 m. Radius terlalu ketat → teknisi kembali ke tombol manual |
| 2026-08-07 | **PIN 6 digit di-generate bersamaan QR** | Faktor pembuktian untuk login pelanggan; HMAC membuktikan QR asli, PIN membuktikan siapa yang memindai |
| 2026-08-07 | **PIN dicetak di kartu pelanggan, BUKAN di stiker ONT** | Stiker tertempel di luar rumah. PIN di stiker yang sama = dua faktor jadi satu faktor; foto stiker langsung memberi akses penuh |
| 2026-08-07 | **PIN 6 digit, bukan 4** | 10⁴ masih terjangkau kalau rate limiter pernah bocor; 10⁶ + lockout membuat brute-force butuh ribuan tahun |
| 2026-08-07 | **PIN disimpan sebagai bcrypt hash, tampil sekali** | Dump DB tidak membocorkan PIN. Konsekuensi diterima: tidak ada "lihat PIN" untuk role mana pun — kalau admin bisa melihatnya, PIN berhenti jadi bukti identitas |
| 2026-08-07 | **`pin_hash` TIDAK masuk bahan HMAC** | Kalau ikut, reset PIN mengubah signature → stiker mati → pelanggan lupa PIN butuh kunjungan teknisi. Token & PIN dua sumbu rotasi terpisah |
| 2026-08-07 | **PIN menggantikan gerbang 4 digit HP di halaman tagihan** | 4 digit terakhir HP bukan rahasia (tetangga/kolektor/grup RT tahu). Jalur lama tetap jalan sampai PIN tergelar merata |
| 2026-08-07 | **PIN tidak dipakai untuk absen teknisi** | Meminta PIN ke pelanggan saat teknisi datang melatih kebiasaan yang dieksploitasi penipuan bermodus "petugas" |
| 2026-08-07 | **OTP turun peran: dari faktor login jadi jalur pemulihan PIN** | PIN tidak butuh biaya SMS per login dan tetap bekerja saat pelanggan tidak memegang HP terdaftar |
| 2026-08-07 | **QR ada di KEDUA media (stiker & kartu), PIN hanya di kartu** | Pelanggan tak perlu keluar rumah untuk cek tagihan; teknisi tak perlu meminta kartu. Token bukan rahasia — keamanan ada di HMAC/PIN/geolocation |
| 2026-08-07 | **Token terbit saat `WAITING_INSTALLATION`, bukan `INSTALLED`** | Kartu harus ikut berangkat bersama teknisi. Menunggu `INSTALLED` berarti kunjungan kedua hanya untuk menyerahkan kartu |
| 2026-08-07 | **Penerbitan idempoten — sudah ada token aktif = tidak menerbitkan apa pun** | Instalasi bisa diulang (`WorkflowTransition.php:37-40`). Tanpa guard, satu pelanggan mengumpulkan banyak PIN dan tidak ada yang tahu kartu mana yang dipegang |
| 2026-08-07 | **`pin_must_change=true` — wajib ganti PIN saat login pertama** | Teknisi memegang kartu ber-PIN sebelum diserahkan. PIN cetak = PIN aktivasi sekali pakai, sehingga pengetahuan teknisi kedaluwarsa sendiri |
| 2026-08-07 | **Stiker hilang → CETAK ULANG (token sama), bukan terbitkan ulang** | Mencabut token akan ikut mematikan kartu pelanggan — pelanggan kehilangan akses gara-gara stiker yang bukan miliknya hilang. Tidak menambah keamanan, hanya menambah kerusakan |
| 2026-08-07 | **Reissue token hanya untuk dugaan penyalahgunaan, pindah POP, dan terminated** | Kehilangan biasa tidak menandakan token bocor; absen dilindungi geolocation, bukan kerahasiaan QR |
| 2026-08-07 | **Stiker hilang tidak dibuatkan task khusus** | Satu-satunya fungsi terganggu (absen QR) punya fallback manual. Dititipkan ke kunjungan terjadwal berikutnya |
| 2026-08-07 | **Reset PIN butuh verifikasi identitas ≥2 faktor, HP saja tidak cukup** | Nomor HP diketahui tetangga/kolektor; ini titik masuk favorit rekayasa sosial untuk membajak akun |
| 2026-08-07 | **Label manusia di stiker pakai REQ ID, bukan `display_id`/CID** | Token terbit saat pelanggan belum aktif (`cid` kosong). `display_id` berubah jadi CID setelah aktif → label tercetak jadi basi. REQ ID permanen |

### Alternatif yang dipertimbangkan dan ditolak

| Alternatif | Alasan ditolak |
|---|---|
| Hash langsung dari `display_id` | `display_id` berubah RQ→CID seiring lifecycle; stiker yang sudah tertempel akan mati |
| Hash dari REQ ID saja (tanpa POP) | REQ ID tidak unik global sejak migration `scope_customer_code_unique_to_pop` — QR bisa bentrok antar cabang |
| SHA256 polos tanpa secret | Semua bahan (POP, CID, nama) publik → siapa pun bisa menghitung QR valid → absen fiktif |
| Hanya HMAC, tanpa token DB | Tidak bisa dicabut per pelanggan; stiker hilang = rotasi secret = cetak ulang semua cabang |
| Hanya token DB, tanpa HMAC | Jalur write ke DB di luar aplikasi bisa menyisipkan token yang langsung sah |
| `hmac(token)` saja agar bisa diverifikasi sebelum query DB | Membuang pengikatan ke POP; menghemat 1 query indexed tapi membuka tabrakan antar cabang |
| Payload QR berisi data pelanggan (nama/CID/alamat) | Stiker di luar rumah bisa difoto siapa saja → kebocoran PII |
| QR internal langsung sebagai instrumen pembayaran | QR non-QRIS tidak bisa dipindai m-banking; QRIS wajib lewat PJSP berizin |
| 4 QR berbeda untuk 4 fungsi | Pelanggan hanya punya satu stiker; routing lebih baik ditentukan server |
| PIN dicetak di bawah QR pada stiker yang sama | Stiker tertempel di luar rumah — memotretnya memberi QR + PIN sekaligus, dua faktor runtuh jadi satu |
| PIN 4 digit | Ruang tebakan 10⁴; terlalu tipis kalau rate limiter pernah gagal |
| PIN disimpan plaintext agar admin bisa membantu pelanggan | Dump/backup/log langsung membocorkan seluruh PIN; dan PIN yang bisa dilihat admin berhenti membuktikan identitas pelanggan |
| `pin_hash` ikut ditandatangani HMAC | Setiap reset PIN mematikan stiker QR yang sudah tertempel |
| PIN dipakai teknisi untuk konfirmasi absen | Melatih pelanggan menyebutkan PIN ke petugas lapangan — modus penipuan paling umum di sektor ini |
| Cabut + terbitkan token baru setiap stiker hilang | Kartu pelanggan ikut mati; pelanggan kehilangan login gara-gara stiker hilang. Cetak ulang token yang sama sudah cukup |
| QR hanya di stiker (kartu cuma berisi PIN) | Pelanggan harus keluar rumah memindai ONT tiap kali cek tagihan |
| QR hanya di kartu (stiker cuma penanda) | Teknisi harus meminta kartu ke pelanggan tiap kunjungan — sering pelanggan tidak di rumah |
| Terbitkan token saat pelanggan `registered` | Pelanggan bisa batal/ditolak sebelum pemasangan; menghasilkan token untuk yang tidak pernah terpasang |
| PIN cetak dipakai permanen tanpa wajib ganti | Teknisi yang mengantar kartu tetap tahu PIN pelanggan selamanya |
| Buat task khusus untuk menempel stiker pengganti | Biaya kunjungan lebih besar dari manfaatnya; fungsi yang terganggu punya fallback |

### Koreksi selama perancangan

Dicatat karena keduanya sempat masuk draf dan bisa terlanjur ikut ke implementasi kalau tidak ditandai:

1. **Draf awal menyatakan "REQ ID tidak stabil".** Salah — yang berubah `display_id`, REQ ID di baliknya permanen. Dikoreksi di §2.1.
2. **Draf awal mengklaim HMAC diverifikasi sebelum query DB.** Salah — bahan HMAC memuat `pop_id` yang harus dibaca dari DB dulu. Yang bisa pre-DB hanya validasi format, sudah dipasang sebagai route constraint regex (§5). Dikoreksi di §2.2.
3. **Draf awal mengusulkan checksum 4 karakter.** Terlalu pendek untuk kontrol keamanan; dinaikkan ke 10 karakter (§3.2).

---

## 13. Catatan review

Review teknis atas rancangan ini, 2026-08-07, setelah dokumen dianggap selesai. Ditulis sebagai reviewer, bukan sebagai penulis rancangan.

**Temuan belum diperbaiki di badan dokumen** — sengaja. Sebagian membatalkan keputusan yang sudah diambil, jadi butuh persetujuan dulu. Bagian yang terdampak diberi penanda `⚠ Lihat §13`.

Ringkasan: rancangannya matang secara teknis tapi **kelebihan bobot untuk masalah yang dipecahkan**, dan berdiri di atas satu asumsi lapangan yang belum diverifikasi.

---

### R1 🔴 Asumsi threat model belum diverifikasi: ONT di dalam atau di luar rumah?

Seluruh rancangan keamanan berdiri di atas satu kalimat di §6.1 yang **tidak pernah diverifikasi ke lapangan**:

> *"Stiker QR tertempel di luar rumah pelanggan; siapa pun yang lewat bisa memindainya."*

Pada ISP FTTH, **ONT umumnya dipasang di dalam rumah pelanggan.** Kalau itu kenyataannya di Whusnet:

| Yang dirancang | Kalau ONT di dalam rumah |
|---|---|
| Penyamaran nama `MASUDAH Y****** F*****` | Jadi teater — tidak ada "orang lewat" |
| Gerbang PIN/4-digit di halaman tagihan | Bisa lebih longgar dari yang dirancang |
| Pemisahan dua media | **Tetap benar**, tapi alasannya berubah: bukan orang lewat, melainkan teknisi & tamu yang masuk rumah |

Threat model yang salah menghasilkan mitigasi yang salah takaran — sebagian berlebihan, sebagian kurang.

**Tindakan:** konfirmasi ke FOP di mana ONT dipasang, sebelum kode apa pun ditulis. Kalau di dalam rumah, §6.1 perlu ditulis ulang, bukan ditambal.

---

### R2 🔴 ULID pilihan yang salah untuk token

§8 menyatakan *"ULID acak 26 char (~128 bit)"*. **Klaim itu keliru.**

ULID = **48-bit timestamp + 80-bit random**, dan timestamp-nya terbaca dari token. Akibatnya:

- Entropi 80 bit, bukan 128
- ULID **sortable by design** — waktu penerbitan bocor dari QR-nya sendiri
- Token yang diterbitkan berurutan punya prefix mirip

80 bit secara praktis masih tidak bisa ditebak, jadi ini bukan lubang menganga. Tapi memilih format yang **sengaja** membocorkan waktu dan **sengaja** sortable untuk token identitas adalah pilihan yang salah tanpa alasan.

**Perbaikan, tanpa biaya dan tanpa trade-off:**

```php
// Ganti ULID dengan 128 bit acak penuh. Panjang tetap 26 char.
$token = substr(strtoupper(rtrim(base32_encode(random_bytes(16)), '=')), 0, 26);
```

Regex route di §5 ikut berubah jadi charset base32 penuh: `[A-Z2-7]{26}\.[A-Z2-7]{10}`.

---

### R3 🔴 HMAC: biayanya nyata, manfaatnya tipis — dan dialah sumber kopling `pop_id`

Kritik terhadap keputusan yang diminta secara sadar dan didukung penulis. Hasil akhirnya tetap harus dicatat jujur.

Ancaman yang HMAC lindungi menurut §2.2: *penyerang menyisipkan baris token lewat SQL injection / akses DB langsung.*

**Masalahnya: penyerang yang bisa menulis ke DB tidak akan repot memalsukan QR.** Dia bisa mengubah status pelanggan, menghapus invoice, membuat user owner baru, atau membaca seluruh tabel pelanggan. Melindungi token QR dari lawan yang sudah memiliki database adalah melindungi hal yang salah.

Biayanya konkret:

| Biaya | Akibat |
|---|---|
| Helper base32, manajemen secret, `previous_secrets`, strategi rotasi | Kode + beban operasional |
| QR v3 → v4 | Stiker harus lebih besar |
| Signature tercetak di stiker | Ruang cetak + kebingungan pengguna |
| **Kopling ke `pop_id`** | **Re-homing POP → cetak ulang fisik dua media** |
| State `pop_mismatch` | Cabang penanganan tambahan |

Yang paling mahal poin keempat, dan ini konsekuensi yang tidak terlihat saat memutuskan: **kopling `pop_id` murni akibat HMAC.** Token acak 128-bit di DB sudah tidak bisa dipalsukan **dan sudah unik global** — tidak butuh `pop_id`, jadi tabrakan REQ ID antar cabang tidak pernah jadi masalah, dan tidak ada cetak ulang saat re-homing.

> **Konsekuensi terhadap §2.1:** analisis tabrakan Winda/Endah — bagian yang paling meyakinkan di dokumen ini — **hanya relevan kalau HMAC dipakai.** Tanpa HMAC, masalah itu tidak pernah ada. Ini perlu diketahui siapa pun yang nanti mempertimbangkan memangkas ruang lingkup.

**Rekomendasi:** pertahankan HMAC kalau lapisan ekstra itu memang diinginkan — keputusan sah, dan alasannya sudah dicatat di §12. Tapi kalau di tengah jalan terasa berat, **di sinilah tempat memangkas pertama**, bukan di guard absen atau di PIN.

---

### R4 🔴 Nama permission tidak valid di RBAC repo ini

Kesalahan konkret. `ActionCode` (`app/Enums/ActionCode.php:7-25`) tidak punya `manage`, dan `attendance` bukan action. Permission di §5 **tidak akan ter-generate** oleh `PermissionGeneratorService`.

| Di §5 | Status | Perbaikan |
|---|---|---|
| `customers.qr.manage` | ❌ `manage` tidak ada | `customers.qr.create` (terbit) + `customers.qr.cancel` (cabut) |
| `qr_scan.attendance` | ❌ bukan action | `tasks.qr_attendance.create` |
| `customers.qr.view` | ✅ | — |
| `customers.qr.print` | ✅ (`print` ada) | — |
| `qr_scan_logs.view` | ✅ | — |

Memisahkan terbit dan cabut jadi dua permission juga lebih tepat secara substansi — mencabut token lebih destruktif daripada menerbitkan, dan tidak seharusnya satu paket.

---

### R5 🔴 Fallback manual tidak akan pernah dipakai

§3.3 meminta teknisi mengetik `customer_code` + signature saat QR rusak. Realitanya **26 + 10 = 36 karakter di HP sambil berdiri di lokasi.** Tidak ada yang akan melakukannya; semua akan menekan tombol manual biasa.

Fallback yang tidak dipakai bukan sekadar mubazir — dia membuat kita **merasa** punya penanganan padahal tidak.

**Ganti dengan:** teknisi memilih pelanggan dari daftar task hari ini (dia sudah tahu mau ke siapa), tombol mulai dengan `started_via='manual'` + geolocation tetap dicatat. Konsekuensinya **signature tidak perlu dicetak di stiker sama sekali** — menghemat ruang cetak sekaligus.

---

### R6 🟡 PIN dibangun untuk portal yang belum ada

Satu-satunya konsumen PIN saat ini adalah gerbang halaman tagihan. Portal pelanggan — alasan utama PIN — ada di Fase 5 dan **blocked**.

CLAUDE.md repo ini menyatakan: *"Hindari abstraksi sebelum dibutuhkan, otomatisasi sebelum flow manual stabil."* Membangun PIN lengkap (hash, lockout, rotasi, wajib-ganti, kartu fisik, alur reset ber-verifikasi identitas) di Fase 3 untuk fitur Fase 5 adalah persis pola yang dilarang itu.

PIN memang lebih baik dari 4 digit HP. Pertanyaan yang harus dijawab: sepadankah dengan **beban helpdesk permanen** (tiap lupa PIN = telepon + verifikasi identitas + pengiriman) untuk halaman yang isinya "tagihan Anda sekian"?

---

### R7 🟡 `pin_must_change` menghasilkan dashboard yang tidak berguna

§7.2 mengusulkan memantau pelanggan dengan `pin_must_change=true` >30 hari. Tapi **mayoritas pelanggan tidak akan pernah login** — mereka bayar lewat kolektor seperti biasa. Hasilnya ribuan baris "PIN belum diaktivasi" permanen: bukan monitoring, melainkan kebisingan yang melatih orang mengabaikan dashboard.

**Ganti mekanismenya:** PIN cetak **kedaluwarsa otomatis** (mis. 90 hari tanpa dipakai → `pin_hash` di-null-kan). Pelanggan yang baru mau login setahun kemudian minta PIN baru — satu panggilan, dan risiko "teknisi masih tahu PIN" hilang sendiri tanpa dashboard apa pun.

---

### R8 🟡 Tidak ada versi di URL — dan stiker itu benda fisik

`/q/{token}` tidak punya penanda versi. Kalau format payload perlu berubah (ganti algoritma, tambah field, pindah domain), **semua stiker yang sudah tertempel harus ditarik secara fisik.**

Menambahkan `/q1/` sekarang berbiaya nol.

Kelalaian ini menonjol justru karena seluruh dokumen berpusat pada "jangan sampai cetak ulang" — prinsip itu diterapkan ke `pop_id` dan ke PIN, tapi terlewat di lapisan URL-nya sendiri.

---

### R9 🟡 Tidak ada penanganan offline

Absen butuh round-trip ke server. Wilayah layanan ISP mencakup daerah bersinyal buruk — justru di sanalah kunjungan teknisi sering terjadi. Rancangan tidak punya antrean offline, jadi di lokasi tanpa sinyal fitur ini mati total, dan tombol manual pun butuh server.

Perlu diputuskan: terima keterbatasan ini secara sadar, atau rancang antrean lokal yang disinkronkan saat sinyal kembali (dengan konsekuensi: `started_at` jadi klaim klien, bukan waktu server — butuh penanganan tersendiri).

---

### R10 🟡 Dua sumber koordinat, belum dipilih mana yang otoritatif

`customers.latitude` (`add_extended_fields_to_customers_table.php:20`) **dan** `customer_addresses.latitude` (`create_customer_addresses_table.php:29`) sama-sama ada. §4.4 hanya menyebut yang pertama.

Kalau yang terisi ternyata yang kedua, validasi radius diam-diam selalu `null` dan guard absen **tidak pernah aktif** — gagal dalam mode paling berbahaya: tampak bekerja padahal tidak. Harus diputuskan sebelum Fase 2, dan ikut masuk audit coverage di §11 butir 1.

---

### R11 🟡 Urutan fase dioptimasi untuk risiko, tapi dilabeli "nilai"

Fase 1 diberi label *"nilai tercepat"*. Kenyataannya prefill tiket menghemat ~10 detik dibanding helpdesk mengetik nama di kolom cari yang sudah ada. Nilai sesungguhnya ada di absen (Fase 2) dan tagihan (Fase 3).

Urutannya sendiri masuk akal — fondasi memang harus dulu, dan mendahulukan yang berisiko rendah itu benar. Tapi labelnya menyesatkan pengambil keputusan: orang bisa menyetujui Fase 1 mengira manfaatnya langsung terasa, lalu kecewa. **Ganti label jadi "risiko terendah", bukan "nilai tercepat".**

---

### R12 🟡 Logistik cetak fisik diperlakukan seperti halaman web

§7.6 menyebut "cetak stiker" dan "cetak kartu" seolah itu tombol. Yang sebenarnya dibutuhkan: printer di tiap POP atau cetak terpusat lalu distribusi, stiker vinyl tahan panas ONT, amplop, manajemen stok, siapa yang mencetak, apa yang terjadi kalau teknisi kehilangan kartu sebelum sampai ke pelanggan.

**Ini program logistik, bukan fitur perangkat lunak.** Bagian itu belum dianalisis sama sekali, dan besar kemungkinan justru di sinilah rencana ini tersendat kalau dipaksakan jalan.

---

### R13 🟢 Temuan kecil

| # | Temuan |
|---|---|
| a | `qr_scan_logs` tanpa kebijakan retensi — perlu pruning (mis. detail 90 hari, agregat seterusnya) |
| b | Kolom `signature` tersimpan bisa divergen dari hasil hitung ulang setelah rotasi secret; manfaatnya kecil |
| c | `previous_secrets` disebut tapi tidak dirancang — verifikasi coba semua? berapa lama transisi? |
| d | Absen tanpa idempotensi eksplisit; double-submit terlindung **kebetulan** oleh guard status `TERJADWAL`, bukan by design |
| e | Routing implisit dispatcher membuat staf yang login tidak bisa melihat halaman tagihan lewat QR — selalu dibelokkan ke form tiket |

---

### R14 Yang bertahan setelah review

Bukan basa-basi penyeimbang — bagian ini tetap valid apa pun keputusan atas R1–R13:

- **Pemisahan cetak ulang vs terbitkan ulang (§7.3)** — bagian terkuat. Jebakan "stiker hilang → cabut token → kartu pelanggan ikut mati" nyata dan tidak terlihat sampai dipikirkan tuntas
- **QR di kedua media** — menghilangkan dua gesekan operasional tanpa menambah risiko, dan menghasilkan sifat berguna: kehilangan satu media tidak melumpuhkan semuanya
- **Penerbitan terikat `WAITING_INSTALLATION` + idempoten** — sesuai kenyataan bahwa instalasi bisa diulang
- **Absen tidak bergantung pada kerahasiaan QR** — geolocation + penugasan + jadwal. Benar, dan tidak berubah oleh temuan mana pun di atas
- **PIN tidak dipakai teknisi** — menghindari melatih pelanggan menyebutkan PIN ke petugas lapangan

---

### R15 Penilaian ruang lingkup

Perkiraan jujur: 2 tabel, ~20 kolom, 5 controller, permission baru, dua layout cetak, siklus hidup PIN, dashboard anomali, ditambah program logistik fisik — realistis **3–4 sprint**. Sprint 8.10 (Audit Trail + Notification) masih berjalan, dan CLAUDE.md melarang loncat sprint.

**Kalau harus dipangkas jadi satu hal yang layak dikerjakan lebih dulu: absen teknisi saja.**

Itu satu-satunya fungsi yang memecahkan masalah yang benar-benar mahal — absen fiktif yang tidak terdeteksi. Kebutuhannya:

```
✓ token acak 128-bit (R2)
✓ satu tabel (customer_qr_tokens, tanpa kolom PIN)
✓ satu endpoint absen + qr_scan_logs
✓ stiker

✗ tanpa PIN          (R6)
✗ tanpa kartu        (R12)
✗ tanpa halaman tagihan publik
✗ tanpa HMAC         (R3)
```

Kalau itu terbukti dipakai teknisi di lapangan, sisanya menyusul di atas fondasi yang **sudah terbukti** — bukan di atas asumsi.

Prasyaratnya tetap satu yang sama dan belum terjawab sejak §11: **berapa persen pelanggan aktif punya koordinat?** Kalau rendah, fungsi paling bernilai itu tidak bisa dibangun, dan urutan seluruh rencana harus disusun ulang — bukan sekadar digeser.
