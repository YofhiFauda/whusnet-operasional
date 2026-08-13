# Rancangan QR Code Pelanggan

**Status:** Rancangan (belum masuk sprint). Sprint aktif saat dokumen ini ditulis: **Sprint 8.10**.
Dokumen ini **tidak** mengubah kode apa pun — murni desain untuk direview sebelum dijadwalkan.

**Tanggal:** 2026-08-07
**Revisi:** 2026-08-08 — semua temuan review (R1–R15) dan keputusan lanjutan telah diterapkan ke badan dokumen. Dokumen ini adalah versi bersih yang siap dijadikan acuan implementasi.
**Revisi:** 2026-08-08 (lanjutan) — hasil review teknis kedua: checklist Fase 1/2 dilengkapi (registrasi rate limiter, seeder permission). Lihat §12 untuk detail.

> **Catatan referensi baris:** nomor baris kode (`Pop.php:264`, `Customer.php:379`, dst) di seluruh dokumen ini adalah rujukan **saat dokumen ditulis**, bukan acuan permanen — kode berubah, nomor baris bergeser. Saat implementasi, berpatokan pada **nama metode/migration/perilaku** (`Pop::resolveDisplayId()`, `Customer::getDisplayIdAttribute()`, migration `scope_customer_code_unique_to_pop`), bukan nomor barisnya.

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

**Keempat fungsi layak,** dengan urutan implementasi yang disesuaikan:

| Fungsi | Layak? | Catatan |
|---|---|---|
| Ticketing | Ya, langsung | Tidak menyentuh alur sync Ticket↔FopTask↔Task sama sekali |
| Pembayaran + PIN | Ya, bertahap | QR = pintu ke halaman tagihan, bukan instrumen bayar. QRIS menyusul saat gateway diputuskan |
| Login pelanggan | Mekanisme siap, portal ditunda | Faktor pembuktian = **PIN 6 digit** (§6.5). Yang belum ada tinggal portal & tabel sesi pelanggan |
| Absen teknisi | Ya, dengan syarat | QR sendirian **tidak** membuktikan kehadiran — butuh geolocation + cek penugasan + cek jadwal. **Dijadwalkan di Fase 3 (terakhir)** setelah fondasi dan tagihan terbukti stabil |

**Tambahan PIN (2026-08-07):** PIN di-generate otomatis bersamaan QR sebagai faktor login pelanggan — layak, dengan satu koreksi penting: **PIN dicetak di kartu pelanggan terpisah, bukan di stiker ONT.** Rincian di §6.5.

Rincian dan pembuktiannya di §2 dan seterusnya. Seluruh keputusan beserta alasan terkumpul di §12.

---

## 1. Tujuan

Satu QR Code per pelanggan, dicetak sekali (stiker di ONT / kartu pelanggan), melayani 4 fungsi:

| # | Fungsi | Aktor pemindai | Fase |
|---|---|---|---|
| A | **Pembayaran** — buka halaman tagihan pelanggan | Pelanggan (publik) | Fase 2 |
| B | **Ticketing** — buat tiket dengan pelanggan ter-prefill | Helpdesk/NOC/FOP (login) | Fase 1 |
| C | **Absen teknisi** — mulai Task di lokasi pelanggan | Teknisi (login) | Fase 3 |
| D | **Login pelanggan** ke portal masing-masing | Pelanggan | Fase 4 — **belum diimplementasi**, hanya disiapkan slot-nya |

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

Ini justru mengonfirmasi triple asli: **POP ID memang wajib ada di dalam hash.** Kunci identitas = `(pop_id, customer_code)` — persis composite unique-nya.

**Catatan `pop_id`:** `pop_id` bisa berubah saat re-homing pelanggan antar cabang, yang akan mematikan QR lama. Dikonfirmasi ke pemilik produk: **re-homing sangat jarang terjadi.**

Keputusan: **`pop_id` tetap masuk bahan HMAC.**

| | Kalau `pop_id` masuk hash | Kalau tidak |
|---|---|---|
| Tabrakan REQ ID antar cabang | Tidak mungkin | **Terjadi** — data nyata sudah punya kasusnya (Winda/Endah, `RQ000042`) |
| Biaya | Cetak ulang 1 stiker saat re-homing | — |
| Frekuensi biaya | Sangat jarang | — |

Penanganan saat re-homing wajib ada supaya kegagalannya tidak diam-diam: `CustomerObserver::updated()` mendeteksi perubahan `pop_id` → cabut token → notifikasi admin POP untuk cetak ulang. Scan QR lama ditolak eksplisit sebagai `pop_mismatch` (§5).

**`full_name` dikeluarkan dari hash.** Alasannya:
- Tidak menambah keunikan — `(pop_id, customer_code)` sudah unique by constraint
- Mutable — koreksi typo nama akan mematikan seluruh QR pelanggan itu
- PII — tidak ada gunanya memasukkan nama ke bahan kriptografi yang tidak butuh

Nama tetap ditampilkan di **stiker cetak** (agar admin/teknisi bisa membaca stiker mana milik siapa), hanya tidak masuk perhitungan hash.

### 2.2 Hash field publik bisa dipalsukan

POP ID, CID, dan nama pelanggan semuanya **bukan rahasia** — tercetak di invoice, terlihat di stiker rumah tetangga, muncul di WhatsApp broadcast. Kalau QR = `sha256(pop_id + cid + nama)`, penyerang yang tahu ketiganya bisa menghitung sendiri QR valid untuk pelanggan mana pun.

Konsekuensi nyata di sistem ini: **teknisi bisa absen fiktif** — generate QR pelanggan dari rumah, `TaskService::start()` jalan tanpa pernah ke lokasi.

Dua mekanisme, dan **keduanya dipakai bersamaan** — bukan pilih salah satu:

| Mekanisme | Cara | Melindungi dari |
|---|---|---|
| **Opaque token** | 128-bit acak (`random_bytes(16)`) disimpan di DB | Tebakan/enumerasi; memungkinkan **pencabutan** per pelanggan |
| **HMAC-SHA256** | `hmac(pop_id\|customer_code\|token, QR_HMAC_SECRET)` | Pemalsuan QR; penyisipan baris token lewat jalur non-aplikasi oleh orang dalam dengan akses DB terbatas |

Keduanya harus lolos agar scan diterima. Ini defense in depth, bukan redundansi:

- **Token tanpa HMAC** — kalau ada jalur write ke DB di luar aplikasi (akses DB langsung oleh orang dalam, restore backup yang dimodifikasi), penyerang bisa menyisipkan baris token buatan sendiri dan QR-nya langsung sah. Dengan HMAC, baris sisipan tidak akan pernah punya signature valid karena secret tidak ada di database.
- **HMAC tanpa token** — signature valid selamanya dan tidak bisa dicabut. Pelanggan lapor stiker hilang, teknisi resign menyimpan foto QR → satu-satunya jalan adalah rotasi secret, yang berarti **cetak ulang seluruh stiker di semua cabang**.

**Urutan verifikasi:** karena bahan HMAC memuat `pop_id` dan `customer_code`, keduanya harus dibaca dari DB dulu, jadi signature **tidak bisa** diverifikasi sebelum query. Yang bisa dilakukan tanpa DB hanyalah validasi format (panjang + charset base32), dan itu sudah cukup menyaring mayoritas request sampah ke endpoint publik. Sisanya = 1 query indexed pada kolom unique + 1 `hash_hmac` — murah, dan tetap di belakang rate limiter.

> **Batas yang harus jelas:** HMAC **tidak** melindungi dari QR asli yang difoto orang. Stiker sah punya signature sah; siapa pun yang memotretnya memegang QR yang valid secara kriptografis. Untuk absen teknisi, pembuktian kehadiran datang dari geolocation + cek penugasan + cek jadwal (§6.3), bukan dari HMAC.

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
https://portal.whusnet.id/q1/4K7M2QX9P4T8W3NR6FDC2HJP5TV.K7M2QX9P4T
                         ├┘ └──────────────────────────┘ └────┬───┘
                       versi     token (128-bit acak,       HMAC sig
                                  26 char base32)           (10 char base32)
```

URL menggunakan prefix versi `/q1/` — berbiaya nol sekarang, tidak ternilai nanti. Kalau format payload perlu berubah (ganti algoritma, tambah field, pindah domain), stiker lama yang memakai `/q1/` masih bisa di-redirect atau ditangani dengan handler versi lama, tanpa harus menarik seluruh stiker fisik yang sudah tertempel.

Alasan URL, bukan payload JSON/teks:
- Kamera HP bawaan (Android/iOS) langsung menawarkan "buka link" — pelanggan tidak perlu instal aplikasi apa pun untuk fungsi A
- Server yang memutuskan routing → satu QR melayani 4 fungsi tanpa cetak ulang
- Tidak ada PII di dalam QR — stiker yang difoto orang lewat tidak membocorkan nama/alamat

Panjang total ~62 karakter → QR **versi 4, ECC level M**, 33×33 modul. Tetap terbaca stabil dicetak 2×2 cm di stiker vinyl.

> Kalau nanti ukuran cetak jadi kendala, naikkan ECC ke level Q dan perbesar stiker — **jangan potong panjang signature.**

### 3.2 Token: 128-bit acak penuh, bukan ULID

Token menggunakan `random_bytes(16)` yang di-encode ke base32, **bukan ULID**.

ULID = 48-bit timestamp + 80-bit random, dan timestamp-nya terbaca dari token. Akibatnya: entropi hanya 80 bit, waktu penerbitan bocor dari QR-nya sendiri, dan token yang diterbitkan berurutan punya prefix mirip. Untuk token identitas, memilih format yang sengaja membocorkan waktu dan sengaja sortable adalah pilihan yang salah tanpa alasan.

```php
// Benar — 128-bit acak penuh, panjang tetap 26 char
$token = substr(strtoupper(rtrim(base32_encode(random_bytes(16)), '=')), 0, 26);

// Salah — jangan pakai Str::ulid() untuk token identitas
```

### 3.3 Perhitungan HMAC

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
 * customer_code (alnum), maupun token base32. Tanpa pemisah, (pop=1, code=RQ12)
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

**Kenapa 10 karakter (50 bit) dan bukan lebih pendek:** signature dipotong demi ukuran QR, tapi memotong terlalu agresif membuatnya bisa di-brute-force. 50 bit berarti ~10¹⁵ percobaan — mustahil lewat HTTP bahkan tanpa rate limit. Di bawah ~40 bit mulai masuk wilayah yang layak diserang oleh penyerang dengan botnet.

**Config** — `config/qr.php`:

```php
return [
    // WAJIB di .env, minimal 32 byte acak: `openssl rand -base64 32`
    // Terpisah dari APP_KEY: rotasi APP_KEY (mis. saat insiden) tidak boleh
    // mematikan seluruh stiker QR yang sudah tercetak dan tertempel.
    'secret' => env('QR_HMAC_SECRET'),

    'base_url' => env('QR_BASE_URL', env('APP_URL')),

    // Secret lama diterima selama masa transisi rotasi.
    // Lihat §7.5 untuk strategi rotasi.
    'previous_secrets' => [],   // array string, max transisi 30 hari
];
```

Tambahkan guard di `AppServiceProvider::boot()` yang menolak boot kalau `QR_HMAC_SECRET` kosong di production — secret kosong membuat `hash_hmac` tetap menghasilkan nilai (dengan key string kosong), jadi kegagalannya diam-diam: semua QR ter-generate dan tervalidasi normal, tapi signature-nya bisa dihitung siapa saja.

### 3.4 Fallback saat QR rusak/pudar

Tidak ada fallback ketik-manual di stiker. Alasannya: 26 + 10 = 36 karakter diketik di HP sambil berdiri di lokasi — tidak ada teknisi yang akan melakukannya. Fallback yang tidak dipakai membuat kita *merasa* punya penanganan padahal tidak.

**Pengganti:** teknisi memilih pelanggan dari daftar task hari ini (dia sudah tahu mau ke siapa), tombol mulai dengan `started_via='manual'` + geolocation tetap dicatat. Konsekuensinya **signature tidak perlu dicetak di stiker** — menghemat ruang cetak.

Stiker cukup memuat: QR + nama + REQ ID + POP.

### 3.5 Yang TIDAK boleh masuk payload

- Nama pelanggan, alamat, nomor HP → stiker tertempel di luar rumah, bisa difoto siapa saja
- CID/REQ ID mentah → memudahkan enumerasi pelanggan
- Nominal tagihan → berubah tiap bulan, QR statis tidak boleh memuatnya
- Signature terpisah (teks) → tidak perlu, karena fallback ketik-manual ditiadakan

---

## 4. Skema database

### 4.1 Tabel baru: `customer_qr_tokens`

Tabel terpisah, bukan kolom di `customers`, karena satu pelanggan bisa punya beberapa token seumur hidup (stiker hilang → terbitkan baru, yang lama dicabut) dan riwayat pencabutan perlu disimpan untuk audit.

```php
Schema::create('customer_qr_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

    // 128-bit acak, base32 26 char. Indexed unique — ini kunci lookup tiap scan.
    // BUKAN ULID — ULID membocorkan timestamp penerbitan dan hanya 80-bit random.
    $table->string('token', 26)->unique();

    // Bahan HMAC dibekukan saat penerbitan.
    //
    // WAJIB disimpan, tidak boleh dibaca ulang dari relasi `customers` saat
    // verifikasi. Alasannya: kalau pelanggan dipindah POP, `customers.pop_id`
    // berubah, signature yang dihitung ulang jadi beda dari yang TERCETAK di
    // stiker, dan seluruh QR pelanggan itu mati diam-diam tanpa ada yang tahu.
    // Dengan disimpan, mismatch pop_id terdeteksi eksplisit sebagai kondisi
    // "token perlu diterbitkan ulang" — bukan sebagai QR rusak misterius.
    $table->foreignId('signed_pop_id')->constrained('pops');
    $table->string('signed_customer_code', 30);

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

    // PIN kedaluwarsa otomatis jika tidak pernah dipakai.
    // Setelah 90 hari tanpa login, pin_hash di-null-kan.
    // Pelanggan yang mau login minta PIN baru — satu panggilan.
    // Ini menggantikan dashboard "PIN belum diaktivasi" yang hanya jadi kebisingan
    // karena mayoritas pelanggan memang tidak pernah login (bayar lewat kolektor).
    $table->timestamp('pin_expires_at')->nullable();

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

    $table->index(['customer_id', 'revoked_at']);
});
```

**Invariant:** maksimal satu token dengan `revoked_at IS NULL` per `customer_id`.
Ditegakkan di `CustomerQrTokenObserver::creating()` — bukan hanya di service — supaya jalur artisan/tinker/import ikut terkunci.

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

    // Geolocation dari browser saat scan (fungsi C — absen). Nullable.
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->unsignedInteger('accuracy_meters')->nullable();
    $table->unsignedInteger('distance_meters')->nullable();

    $table->foreignId('task_id')->nullable()->constrained();
    $table->foreignId('ticket_id')->nullable()->constrained();

    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent', 255)->nullable();

    $table->timestamp('scanned_at');
    $table->timestamps();

    $table->index(['customer_id', 'scanned_at']);
    $table->index(['user_id', 'scanned_at']);
    $table->index(['result', 'scanned_at']);
});
```

**Kebijakan retensi:** detail log disimpan 90 hari, setelah itu di-aggregate (jumlah scan per pelanggan per bulan) dan baris detail dihapus. Tanpa pruning, tabel ini tumbuh tak terbatas.

### 4.3 Kolom tambahan di `tasks`

```php
$table->string('started_via', 20)->nullable()->after('started_at');  // manual | qr_scan
$table->decimal('start_latitude', 10, 7)->nullable();
$table->decimal('start_longitude', 10, 7)->nullable();
$table->unsignedInteger('start_distance_meters')->nullable();
```

Alasan disimpan di `tasks`, bukan hanya di `qr_scan_logs`: laporan SLA & audit membaca `tasks` langsung, dan pertanyaan "task ini dimulai lewat QR di lokasi atau diklik manual dari kantor?" harus terjawab tanpa join ke tabel log.

### 4.4 Koordinat pelanggan — sumber otoritatif dan strategi fallback

Fungsi C (absen) butuh koordinat pelanggan untuk validasi radius. Ada dua kolom di database:

- `customers.latitude` / `customers.longitude` — kolom utama (lihat `#[Fillable]` di `Customer.php:42-43`)
- `customer_addresses.latitude` / `customer_addresses.longitude` — kolom di tabel alamat

**Keputusan (2026-08-08):** gunakan keduanya dengan urutan prioritas: cek `customers.latitude` dulu, jika null fallback ke `customer_addresses.latitude` untuk pelanggan yang bersangkutan. Ini menghindari silent bug di mana guard geolocation tampak aktif tapi selalu menghasilkan `distance_meters = null`.

```php
// CustomerQrTokenService — resolusi koordinat
private function resolveCoordinates(Customer $customer): ?array
{
    if ($customer->latitude !== null && $customer->longitude !== null) {
        return ['lat' => $customer->latitude, 'lng' => $customer->longitude];
    }

    $address = $customer->addresses()->whereNotNull('latitude')->first();
    if ($address) {
        return ['lat' => $address->latitude, 'lng' => $address->longitude];
    }

    return null;  // koordinat tidak tersedia → lolos dengan flag tanpa_koordinat
}
```

**Sebelum Fase 3 (absen) dimulai**, wajib audit coverage kedua kolom:

```sql
SELECT
  COUNT(*) AS total_aktif,
  COUNT(CASE WHEN c.latitude IS NOT NULL AND c.longitude IS NOT NULL THEN 1 END) AS ada_di_customers,
  COUNT(CASE WHEN ca.latitude IS NOT NULL AND ca.longitude IS NOT NULL THEN 1 END) AS ada_di_customer_addresses,
  COUNT(CASE WHEN
    (c.latitude IS NOT NULL AND c.longitude IS NOT NULL)
    OR (ca.latitude IS NOT NULL AND ca.longitude IS NOT NULL)
  THEN 1 END) AS total_dengan_koordinat
FROM customers c
LEFT JOIN customer_addresses ca ON ca.customer_id = c.id
WHERE c.status = 'active';
```

Kalau `total_dengan_koordinat` rendah, validasi radius harus **soft** (dicatat sebagai `distance_meters = null`, tidak memblokir) sampai backfill koordinat selesai.

---

## 5. Routing: satu URL, empat perilaku

Endpoint tunggal `GET /q1/{code}` bertindak sebagai dispatcher.

```
GET /q1/{token}.{sig}
  │
  ├─ [1] Format token/sig salah      → 404, TANPA query DB
  │       Regex: [A-Z2-7]{26}\.[A-Z2-7]{10}
  │       (base32 penuh, bukan ULID charset)
  │
  ├─ [2] Lookup token di DB          → tidak ketemu: 404
  │
  ├─ [3] hash_equals(sig, hitung ulang dari signed_pop_id
  │                        + signed_customer_code + token)
  │        gagal → 404, log result=bad_signature
  │        (SINYAL SERIUS: harus memicu alert, bukan sekadar log —
  │         beda dari token_not_found yang wajar terjadi.)
  │
  ├─ [4] token dicabut               → 404, log result=token_revoked
  │
  ├─ [5] customer.pop_id ≠ signed_pop_id → 404, log result=pop_mismatch
  │        Pelanggan dipindah cabang tanpa token diterbitkan ulang.
  │        Bukan serangan — kegagalan proses. Notifikasi ke admin POP.
  │
  │  Semua kegagalan di atas mengembalikan 404 yang IDENTIK.
  │  Detail hanya masuk qr_scan_logs.
  │
  ├─ TAMU (belum login)
  │     └─ redirect → /q1/{token}/tagihan          [fungsi A — Fase 2]
  │
  └─ SUDAH LOGIN
        └─ cek POP scope pelanggan via EffectiveAccessService
              │  gagal → 403 + log result=out_of_scope
              │
              ├─ punya task 'terjadwal' hari ini utk pelanggan ini
              │  DAN user ∈ team task tsb
              │     └─ tampilkan konfirmasi "Mulai Task TASK-2026-0123?"  [fungsi C — Fase 3]
              │
              ├─ punya permission tickets.create
              │     └─ tampilkan pilihan aksi: Buat Tiket / Lihat Detail   [fungsi B — Fase 1]
              │
              └─ selain itu
                    └─ redirect → customers.show                            [fallback]
```

> **Catatan routing:** staf yang login (helpdesk, admin) tidak bisa mengakses halaman tagihan publik lewat QR — selalu diarahkan ke form tiket atau detail pelanggan. Kalau suatu saat staf perlu melihat halaman tagihan sebagai pelanggan, tambahkan tombol eksplisit di `customers.show`, jangan modifikasi dispatcher.

### Rute

```php
// routes/web.php — STATIC dulu, DYNAMIC belakangan (konvensi repo)

Route::middleware('throttle:qr-public')->group(function () {
    Route::get('/q1/{code}', [QrScanController::class, 'dispatch'])
        ->where('code', '[A-Z2-7]{26}\.[A-Z2-7]{10}')
        ->name('qr.dispatch');

    Route::get('/q1/{code}/tagihan',  [QrBillingController::class, 'show'])->name('qr.billing');
    Route::post('/q1/{code}/tagihan/verifikasi', [QrBillingController::class, 'verify'])->name('qr.billing.verify');
    Route::post('/q1/{code}/masuk', [QrBillingController::class, 'login'])->name('qr.login');
});

Route::middleware('auth')->group(function () {
    Route::post('/q1/{code}/absen', [QrAttendanceController::class, 'store'])->name('qr.attendance');
    Route::get('/q1/{code}/tiket',  [QrTicketController::class, 'create'])->name('qr.ticket.create');
});

Route::middleware('permission:customers.qr.view')->group(function () {
    Route::get('/customers/{customer}/qr',       [CustomerQrController::class, 'show'])->name('customers.qr.show');
    Route::get('/customers/{customer}/qr/cetak', [CustomerQrController::class, 'print'])->name('customers.qr.print');
});
Route::middleware('permission:customers.qr.create')->group(function () {
    Route::post('/customers/{customer}/qr/terbitkan', [CustomerQrController::class, 'issue'])->name('customers.qr.issue');
});
Route::middleware('permission:customers.qr.cancel')->group(function () {
    Route::post('/customers/{customer}/qr/cabut', [CustomerQrController::class, 'revoke'])->name('customers.qr.revoke');
});
```

Permission baru lewat `features` × `actions` (`PermissionGeneratorService`):

| Permission | Pemegang | Keterangan |
|---|---|---|
| `customers.qr.view` | admin, pop_admin, fop, helpdesk | Lihat status token |
| `customers.qr.create` | admin, owner | Terbitkan token baru |
| `customers.qr.cancel` | admin, owner | Cabut token — lebih destruktif, permission terpisah |
| `customers.qr.print` | admin, pop_admin | Cetak stiker/kartu |
| `tasks.qr_attendance.create` | teknisi, fop | Absen via QR |
| `qr_scan_logs.view` | owner, atasan, admin | Dashboard anomali |

> **Penting:** `manage` bukan `ActionCode` yang valid di repo ini. `cancel` adalah pengganti yang benar untuk "cabut". `attendance` bukan action — gunakan `tasks.qr_attendance.create`.

---

## 6. Rancangan per fungsi

### 6.1 Fungsi A — Pembayaran (Fase 2)

Satu-satunya halaman **tanpa auth**. Posisi ONT (dalam/luar rumah) mempengaruhi takaran mitigasi — konfirmasi ke FOP sebelum implementasi. Rancangan ini mengasumsikan ONT bisa diakses orang yang tidak tinggal di rumah (tamu, teknisi, atau memang di luar).

**Two-step disclosure:**

*Langkah 1 — halaman minimal (langsung tampil):*
```
Whusnet — Tagihan Pelanggan
Pelanggan  : MASUDAH Y****** F*****
ID         : C1X4ARQ000631
POP        : Ponorogo

Untuk melihat tagihan, masukkan PIN Anda:  [ _______ ]
Belum punya PIN? Masukkan 4 digit terakhir nomor HP terdaftar.
```

*Langkah 2 — setelah verifikasi benar:*
Nama lengkap, alamat, rincian tagihan per periode, status, tombol Bayar.

**Dua jalur gerbang yang berjalan berdampingan:**

| Kondisi pelanggan | Gerbang |
|---|---|
| Sudah punya PIN aktif (`pin_hash` terisi, belum kedaluwarsa) | PIN 6 digit |
| Belum punya PIN / PIN kedaluwarsa | 4 digit terakhir HP (jalur legacy) |

Jalur legacy dihapus setelah seluruh pelanggan aktif punya PIN.

Guard yang wajib ada:
- Rate limit `5 percobaan / 15 menit / IP+token`, lalu blokir 1 jam
- Percobaan gagal masuk `qr_scan_logs` dengan `result=verify_failed`
- Session verifikasi 30 menit, di-scope ke token
- Tidak ada nomor HP lengkap, NIK, atau foto KTP di halaman ini

**Fase 2 tanpa gateway:** tombol Bayar diganti tampilan nomor rekening + nominal + tombol "Salin", plus tombol WhatsApp ke admin POP. Pencatatan pembayaran tetap manual lewat `/payments`.

### 6.2 Fungsi B — Ticketing (Fase 1)

Paling sederhana, paling cepat memberi nilai fondasi.

Helpdesk/FOP memindai QR → `GET /q1/{token}/tiket` → form `tickets.create` dengan `customer_id` terkunci dan snapshot pelanggan ter-prefill.

Setelahnya masuk `TicketService::create()` apa adanya. **Tidak ada perubahan pada alur sinkronisasi Ticket ↔ FopTask ↔ Task.** QR hanya cara mengisi field pelanggan.

Guard: POP scope + permission `tickets.create`.

### 6.3 Fungsi C — Absen teknisi (Fase 3)

Fungsi paling sensitif, dijadwalkan terakhir setelah fondasi dan tagihan terbukti stabil di lapangan.

QR statis **bisa difoto** — teknisi bisa menyimpan foto QR semua pelanggannya lalu absen dari rumah. QR sendirian tidak membuktikan kehadiran; yang membuktikan adalah **gabungan** QR + GPS + jadwal + penugasan.

**Rantai guard, semuanya wajib lewat:**

```php
// QrAttendanceController::store()

1. Token valid & belum dicabut                    → else 404
2. Pelanggan dalam POP scope user                 → else 403
3. Cari Task: customer_id = X
              AND status = TERJADWAL
              AND scheduled_at <= hari ini
              AND user ∈ task.teamMembers
   Tidak ketemu → 422 "Tidak ada task terjadwal untuk pelanggan ini"
   Lebih dari satu → tampilkan pilihan (jangan tebak)

4. Geolocation (browser Geolocation API):
   - resolveCoordinates($customer) → §4.4
     ├─ koordinat tersedia → hitung haversine
     │        ├─ ≤ 150 m           → lolos
     │        ├─ 150–500 m         → lolos + flag perlu_review
     │        └─ > 500 m           → TOLAK (422), log out_of_radius
     └─ tidak ada koordinat → lolos, distance_meters = null,
                               flag tanpa_koordinat

   Radius 150 m: akurasi GPS HP di area padat/dalam rumah rutin meleset
   50–100 m. Radius lebih ketat menghasilkan false-negative yang membuat
   teknisi kembali ke tombol manual — guard yang di-bypass = guard yang mati.

5. Delegasi ke TaskService::start($task, $actor)
   — SEMUA guard existing tetap berlaku, TIDAK di-bypass

6. Isi tasks.started_via='qr_scan' + koordinat
7. Tulis qr_scan_logs
```

**Penanganan offline:** absen QR butuh round-trip ke server dan tidak bekerja tanpa sinyal. Ini keterbatasan yang diterima secara sadar — tombol "Mulai Task" manual tetap ada sebagai fallback (`started_via='manual'`). Antrean lokal offline tidak diimplementasi di Fase 3; evaluasi ulang jika data lapangan menunjukkan wilayah tanpa sinyal adalah mayoritas kasus.

**Yang eksplisit TIDAK dilakukan:**
- Tidak menduplikasi logika start di controller. `TaskService::start()` tetap satu-satunya penulis transisi.
- Tidak menghapus tombol "Mulai Task" manual.

**Deteksi anomali (halaman `qr_scan_logs.view`):**
- Teknisi dengan rasio `started_via='manual'` tinggi
- Scan sukses dengan `distance_meters > 150`
- Beberapa scan dari koordinat yang hampir identik untuk pelanggan berbeda-beda
- Scan token yang sudah dicabut

### 6.4 Fungsi D — Login pelanggan (disiapkan, belum diimplementasi)

Belum ada portal pelanggan. Yang disiapkan sekarang hanya agar nanti tidak perlu cetak ulang stiker:
- Field `purpose` di `qr_scan_logs` sudah menerima nilai `login`
- Dispatcher sudah punya titik cabang untuk itu
- Token sudah bersifat opaque & revocable — prasyarat untuk auth

Saat diimplementasi, **scan QR tidak boleh langsung mengautentikasi** — QR berperan sebagai identifier, faktor pembuktiannya adalah PIN (§6.5).

---

## 6.5 PIN pelanggan (Fase 2)

PIN di-generate otomatis bersamaan QR, dipakai sebagai faktor login pelanggan.

**Layak, dan melengkapi celah yang tidak ditutup HMAC** — HMAC membuktikan QR itu asli, bukan membuktikan siapa yang memindainya. PIN menutup yang kedua.

### 6.5.1 PIN tidak boleh dicetak di stiker yang sama dengan QR

Stiker QR ditempel di ONT. Kalau PIN tercetak di stiker yang sama:

```
Siapa pun yang memotret stiker  →  dapat QR DAN PIN sekaligus
                                →  dua faktor jadi satu faktor
                                →  PIN tidak menambah keamanan apa pun
```

**Dua media, dua jalur pengiriman:**

| Media | Isi | Ditempel/diberikan | Sifat |
|---|---|---|---|
| **Stiker ONT** | QR + nama + REQ ID + POP | Ditempel di ONT | Publik |
| **Kartu Pelanggan** | QR + **PIN** + petunjuk pakai | Diserahkan ke tangan pelanggan saat instalasi | Privat |

Keduanya membawa QR yang sama. Yang berbeda hanya: kartu memuat PIN, stiker tidak.

Jalur cadangan: kirim PIN via WhatsApp/SMS ke `primary_phone` terdaftar.

### 6.5.2 Spesifikasi PIN

| Aspek | Nilai | Alasan |
|---|---|---|
| Panjang | **6 digit** | 10⁶ kombinasi — cukup kuat dengan rate limit |
| Sumber acak | `random_int()` | CSPRNG. **Bukan** `rand()`/`mt_rand()` |
| Penyimpanan | `Hash::make()` (bcrypt) | Kolom `pin_hash`; PIN plaintext tidak pernah masuk DB |
| Ditampilkan | **Sekali saja**, saat diterbitkan | Tidak bisa dilihat ulang siapa pun |
| Rotasi | Independen dari token QR | Reset PIN **tidak** mematikan stiker |
| Kedaluwarsa | 90 hari tanpa dipakai → `pin_hash` di-null-kan | Risiko "teknisi masih tahu PIN" hilang sendiri |
| Ditolak | `000000`, `123456`, 6 digit sama, tanggal lahir | Generate ulang kalau kena |

**PIN tidak boleh masuk bahan HMAC.** Kalau `pin_hash` ikut ditandatangani, setiap reset PIN mengubah signature → seluruh stiker QR pelanggan itu mati. Token QR dan PIN adalah **dua sumbu rotasi yang sepenuhnya independen:**

| Kejadian | Tindakan | Cetak ulang stiker? |
|---|---|---|
| Pelanggan lupa PIN | Terbitkan PIN baru | Tidak |
| PIN kedaluwarsa | Terbitkan PIN baru saat pelanggan minta | Tidak |
| Stiker hilang/rusak | Cetak ulang (token sama) | Ya |
| Pelanggan pindah POP | Terbitkan token baru | Ya |

### 6.5.3 Alur penerbitan

Dipicu saat pelanggan masuk `WAITING_INSTALLATION`, bukan aksi admin lepas.

```
Pelanggan → WAITING_INSTALLATION
      │
      ├─ sudah punya token aktif? → BERHENTI, pakai yang lama
      │
      ├─ token 128-bit acak + signature   → disimpan
      ├─ PIN 6 digit (random_int)         → HANYA hash yang disimpan
      │                                     pin_must_change = true
      │                                     pin_expires_at = now() + 90 hari
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
```

Guard di halaman ini:
- PIN plaintext hidup di **response saja**, tidak pernah masuk session, cache, flash, atau log
- Halaman `no-store` dan `noindex`
- Audit log mencatat "PIN diterbitkan oleh X untuk pelanggan Y", bukan PIN-nya
- Butuh permission `customers.qr.create`

### 6.5.4 Alur verifikasi

```
POST /q1/{code}/masuk   { pin }
  │
  ├─ [1] Token + signature valid           → else 404
  ├─ [2] pin_expires_at sudah lewat        → 422 "PIN kedaluwarsa, hubungi helpdesk"
  ├─ [3] pin_locked_until masih berlaku    → 429 + sisa waktu
  ├─ [4] Hash::check(pin, pin_hash)
  │        gagal → pin_failed_attempts++
  │                ├─ ≥ 5  → pin_locked_until = now()+15 menit + notifikasi
  │                → 422 "PIN salah" (TANPA menyebut sisa percobaan)
  │
  └─ berhasil → pin_failed_attempts = 0
                sesi pelanggan dibuat, di-scope ke customer_id
                pin_expires_at di-refresh (aktif dipakai = tidak kedaluwarsa)
                log result='success', purpose='login'
```

### 6.5.5 Lupa PIN / PIN kedaluwarsa

**Jalur A — via admin (utama):** Pelanggan menghubungi helpdesk → **verifikasi identitas minimal 2 faktor** (NIK, alamat, pembayaran terakhir — nomor HP saja tidak cukup) → "Terbitkan Ulang PIN" → PIN baru tampil sekali. **Token QR tidak berubah, stiker tetap sah.**

**Jalur B — mandiri via OTP** (kalau nanti ada gateway SMS/WA): Scan QR → "Lupa PIN" → OTP ke `primary_phone` → PIN baru tampil sekali.

### 6.5.5b Wajib ganti PIN saat login pertama

```
Login pertama dengan PIN cetak
   │
   ├─ pin_must_change = true → paksa halaman "Buat PIN Baru"
   │      (tidak bisa dilewati)
   │
   ├─ PIN baru: 6 digit, tidak boleh sama dengan PIN cetak,
   │            tolak pola lemah
   │
   └─ pin_must_change = false
      pin_first_used_at = now()
      pin_version++
      pin_expires_at = null  (PIN yang sudah diganti tidak kedaluwarsa)
```

### 6.5.6 Yang PIN tidak lakukan

- **PIN tidak dipakai untuk absen teknisi** — menghindari melatih pelanggan menyebutkan PIN ke petugas lapangan (modus penipuan).
- **PIN tidak menggantikan HMAC** — dua pertanyaan berbeda.
- **PIN tidak dipakai staf internal** — staf login lewat `users` + RBAC.

---

## 7. Siklus hidup: dua media, satu QR

### 7.1 Dua media — apa persisnya yang berbeda

```
                    SATU TOKEN
         4K7M2QX9P4T8W3NR6FDC2HJP5TV.K7M2QX9P4T
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
    │                │           │  PIN: 4 8 2 9 1 7  │
    │  TANPA PIN     │           │  (ganti saat login │
    │  TANPA SIG     │           │   pertama)         │
    └───────┬────────┘           └─────────┬──────────┘
            │                              │
    Ditempel di ONT              Diserahkan ke tangan
            │                    pelanggan, disimpan
    Dipakai TEKNISI              Dipakai PELANGGAN
    untuk absen                  untuk login & tagihan
```

### 7.2 Penerbitan — terikat ke workflow pemasangan

```
registered → waiting_survey → surveyed → waiting_acc
                                              │
                                    WAITING_INSTALLATION  ◄── TOKEN + PIN TERBIT
                                              │
                            Admin cetak: stiker + kartu (PIN)
                            Teknisi ambil sebelum berangkat
                                              │
                                    INSTALLATION_IN_PROGRESS
                                              │
                            Teknisi di lokasi:
                              ├─ tempel STIKER di ONT
                              ├─ serahkan KARTU ke pelanggan (amplop tertutup)
                              └─ jelaskan: ganti PIN saat login pertama
                                              │
                                        INSTALLED → VERIFICATION_ADMIN → ACTIVE
```

Penerbitan harus **idempoten** — sudah ada token aktif → kembalikan yang lama, tidak menerbitkan baru. Instalasi bisa diulang (`WorkflowTransition.php:37-40`).

### 7.3 Skenario kehilangan

Kunci membedakan **cetak ulang** dari **terbitkan ulang**:

| | Cetak ulang (reprint) | Terbitkan ulang (reissue) |
|---|---|---|
| Token | **Tetap** | Dicabut, diganti baru |
| Media lain | **Tidak terpengaruh** | **Ikut mati, wajib ikut dicetak ulang** |
| Kapan | Media rusak/hilang biasa | Ada dugaan penyalahgunaan |

> **Jebakan:** kalau stiker hilang lalu *terbitkan ulang* token, **kartu pelanggan ikut mati**. Untuk kehilangan biasa, **cetak ulang, jangan terbitkan ulang.**

#### Kasus 1 — Stiker ONT hilang/rusak/ONT diganti

**Dampak: kecil.** Kartu pelanggan tetap jalan. Yang terganggu hanya absen QR — punya fallback manual.

- Admin: "Cetak Ulang Stiker" → token sama, kartu tidak terpengaruh
- Penempelan: titipkan ke kunjungan terjadwal berikutnya

#### Kasus 2 — Kartu pelanggan hilang / lupa PIN

**Dampak: pelanggan kehilangan PIN.** QR masih di ONT. Yang perlu diganti **hanya PIN**, tidak perlu kunjungan.

- Verifikasi identitas minimal 2 faktor (NIK, alamat, pembayaran terakhir)
- "Terbitkan Ulang PIN" → token tidak berubah, stiker tetap sah
- Penyerahan: WA ke `primary_phone` terdaftar / ambil di kantor

#### Kasus 3 — Keduanya hilang

Cetak ulang stiker (token sama) + terbitkan ulang PIN. Bukan reissue token.

#### Kasus 4 — Dugaan penyalahgunaan

Pemicunya: `bad_signature` berulang di `qr_scan_logs`, scan dari lokasi tidak masuk akal, atau laporan pelanggan.

- "Cabut & Terbitkan Ulang" (reason wajib diisi)
- Token lama: `revoked_at` diisi, tidak dihapus
- Token baru + PIN baru → wajib cetak ulang KEDUA media

#### Kasus 5 — Re-homing POP

Otomatis via `CustomerObserver::updated()`: deteksi `pop_id` berubah → cabut token → notifikasi admin POP. QR lama ditolak sebagai `pop_mismatch`. Kedua media wajib dicetak ulang.

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

Kalau secret bocor → rotasi bertahap: `config('qr.secret')` menandatangani token baru, `config('qr.previous_secrets')` (array string) masih diterima selama masa transisi maksimal 30 hari. Setelah transisi, hapus secret lama dari array.

Rotasi diikuti audit token mana yang masih memakai secret lama (bandingkan `customer_qr_tokens.signature` dengan hasil `signature()` menggunakan secret baru — yang berbeda = masih pakai secret lama, perlu cetak ulang).

### 7.6 Halaman cetak

`customers.qr.print` merender dua layout dari token yang sama:

| Layout | Isi | Pemakaian |
|---|---|---|
| **Stiker** | QR + nama + REQ ID + POP, **tanpa PIN, tanpa signature teks** | Grid siap potong, cetak massal per POP |
| **Kartu** | QR + PIN + petunjuk pakai | Per pelanggan, hanya saat PIN diterbitkan (`no-store`) |

**Label manusia di stiker pakai REQ ID, bukan CID.** REQ ID permanen; CID baru terisi setelah pelanggan aktif, sehingga label CID di stiker yang dicetak saat `WAITING_INSTALLATION` langsung basi.

**Program logistik cetak fisik** — ini bukan fitur perangkat lunak semata. Sebelum Fase 2 ship, harus dijawab:
- Printer apa, media vinyl apa, di POP mana saja tersedia?
- Siapa yang mencetak dan siapa yang bertanggung jawab stok?
- Apa prosedur kalau teknisi kehilangan kartu PIN sebelum sampai ke pelanggan?
- Amplop apa yang dipakai agar PIN tidak terbaca sebelum diserahkan?

Tanpa menjawab ini, fitur yang secara teknis sempurna akan tersendat di logistik.

---

## 8. Ringkasan keamanan

| Ancaman | Mitigasi |
|---|---|
| Tebak/enumerasi token | 128-bit acak (`random_bytes(16)`) + HMAC 50 bit; 404 generik; rate limit |
| QR dipalsukan/dihitung sendiri | HMAC-SHA256 — secret tidak ada di DB |
| Baris token disisipkan oleh orang dalam | Baris sisipan tidak punya HMAC valid; ditolak di dispatcher |
| Tabrakan REQ ID antar cabang | `pop_id` masuk bahan HMAC + composite unique |
| QR difoto → absen fiktif | Geolocation + radius + cek penugasan + cek jadwal + log anomali |
| QR difoto → intip data pelanggan | Halaman publik hanya nama tersamar; data penuh butuh PIN/4-digit HP |
| Brute-force PIN | 6 digit + lockout 15 menit setelah 5 gagal + hitungan di DB + rate limit per IP |
| PIN bocor lewat dump/backup DB | bcrypt hash; PIN plaintext tidak pernah masuk DB/log/session |
| Orang dalam melihat PIN pelanggan | PIN tampil sekali saat diterbitkan; tidak ada layar "lihat PIN" |
| Rekayasa sosial "petugas minta PIN" | PIN tidak pernah diminta teknisi — absen tidak memakai PIN |
| Stiker lama beredar setelah ganti | Token revocable; scan token dicabut → log + notifikasi admin |
| Kebocoran lintas cabang | Semua endpoint terautentikasi lewat `EffectiveAccessService` |
| Timing attack pada perbandingan signature | `hash_equals()`, bukan `===` |
| Format URL basi karena perubahan domain/algoritma | Prefix versi `/q1/` di URL — migrasi ke `/q2/` tanpa cetak ulang stiker |

**Catatan konfigurasi wajib:**
- Halaman publik **harus HTTPS** — Geolocation API diblokir di HTTP.
- `QR_HMAC_SECRET` di `.env`, minimal 32 byte acak, tidak masuk repo, berbeda dari `APP_KEY`.
- Guard di `AppServiceProvider::boot()`: tolak boot kalau `QR_HMAC_SECRET` kosong di production.
- `QR_HMAC_SECRET` **tidak boleh** masuk `qr_scan_logs`, pesan exception, atau payload Telegram.

---

## 9. Dependensi

```bash
composer require endroid/qr-code
# Alternatif: bacon/bacon-qr-code (lebih ringan, renderer SVG tanpa GD)
```

SVG lebih baik untuk cetak — tajam di ukuran apa pun.

> Penambahan dependensi butuh persetujuan sebelum dieksekusi.

**Sisi klien:** tidak perlu library baru. Fungsi A dipindai kamera bawaan. Fungsi B & C butuh pemindai browser — pakai `BarcodeDetector` API (native Chrome/Edge Android) dengan fallback pilih-dari-daftar-task untuk browser yang belum mendukung.

**Base32:** PHP tidak punya `base32_encode()` bawaan. Tulis helper kecil di `app/Helpers/` (~20 baris, tidak perlu dependensi). Base32 dipilih karena aman di URL tanpa escaping, dan alfabetnya (RFC 4648, tanpa `0`/`1`/`8`) mengurangi salah baca.

**Kripto:** `hash_hmac()` dan `hash_equals()` ada di PHP core.

---

## 10. Rencana implementasi

Bukan satu sprint. Tiap fase punya nilai sendiri dan bisa dihentikan tanpa menyisakan setengah jadi.

### Fase 1 — Fondasi + Ticketing (risiko terendah)

Label "risiko terendah" sengaja — prefill tiket menghemat ~10 detik dibanding kolom cari yang sudah ada. Nilai sesungguhnya ada di Fase 2 & 3. Urutan ini benar (fondasi dulu) tapi jangan ekspektasi "nilai langsung terasa" di Fase 1.

- `config/qr.php` + `QR_HMAC_SECRET` + guard boot production
- Helper base32
- Migration `customer_qr_tokens`, `qr_scan_logs`
- **Daftarkan `RateLimiter::for('qr-public', ...)` di `AppServiceProvider::boot()`** — belum ada di codebase (dicek: grep `RateLimiter::for`/`throttle:` ke seluruh `app/` & `bootstrap/app.php` = nol hasil), ini limiter baru, bukan alias yang sudah tersedia. Baseline per-IP: `Limit::perMinute(60)->by($request->ip())`. Tanpa langkah ini, middleware `throttle:qr-public` di route (§5) error saat pertama kali diakses (limiter tak terdaftar)
- `CustomerQrTokenService` — `issue()`, `revoke()`, `signature()`, `verify()`, `resolve()`. `issue()` **menolak** pelanggan dengan `customer_code`/`pop_id` kosong (§11.2) — jalankan query legacy (§11 poin 5) untuk tahu skala data yang perlu dibersihkan dulu
- `CustomerQrTokenObserver` + hook `CustomerObserver` untuk `pop_id` berubah & `terminated`
- `QrScanController::dispatch()` + resolusi POP scope
- Halaman lihat/cetak QR + permission baru
- **Seeder permission** — `FeatureSeeder`/`ActionSeeder`/`RolePermissionSeeder`: `customers.qr.view`, `customers.qr.create`, `customers.qr.cancel`, `customers.qr.print`, `tasks.qr_attendance.create`, `qr_scan_logs.view` (§5 tabel permission). Lewat matrix role, bukan hardcode — ikuti pola `PermissionGeneratorService` yang sudah ada
- Fungsi B (ticketing prefill)

**Test wajib fase ini:**
- `signature()` deterministik; berubah kalau `pop_id`, `customer_code`, atau `token` berubah
- **Dua pelanggan beda POP dengan `customer_code` identik → signature BERBEDA**
- Signature salah → 404 + `result='bad_signature'`
- Token dicabut → 404 + `result='token_revoked'`
- `pop_id` pelanggan diubah → token lama ditolak `result='pop_mismatch'`, token otomatis tercabut
- Semua kegagalan mengembalikan body 404 identik
- Pelanggan luar POP scope → 403
- Invariant satu token aktif ditegakkan dari jalur non-HTTP (artisan/tinker)

### Fase 2 — PIN + Halaman tagihan publik

- Kolom PIN di `customer_qr_tokens`
- `CustomerQrTokenService::issuePin()` / `verifyPin()` + lockout + TTL kedaluwarsa 90 hari
- **Limiter lebih ketat untuk verifikasi PIN** (§6.1) — 5 percobaan/15 menit per kombinasi IP+token, terpisah dari `qr-public` baseline di Fase 1. `pin_failed_attempts`/`pin_locked_until` di DB (§4.1) menangani lockout per-token; tambahan per-IP mencegah 1 IP mencoba banyak token berbeda secara paralel
- Halaman terbitkan PIN (tampil sekali, `no-store`) + layout cetak kartu pelanggan
- Halaman publik + gerbang PIN (fallback 4 digit HP)
- Tampilan tagihan (tanpa gateway: rekening + salin + WhatsApp admin)

**Test wajib fase ini:**
- PIN plaintext **tidak pernah** muncul di DB, log, session, audit trail, atau response kedua
- 5 gagal → `pin_locked_until` terisi, percobaan ke-6 ditolak 429 **walau cache di-flush**
- Reset PIN **tidak** mengubah token/signature
- Cetak ulang stiker tidak mencabut token
- Penerbitan idempoten: transisi berulang ke `WAITING_INSTALLATION` tidak membuat token/PIN kedua
- `pin_must_change`: login pertama dipaksa ke halaman ganti PIN
- PIN kedaluwarsa (>90 hari tanpa dipakai) → 422 + arahkan ke helpdesk
- PIN yang sudah diganti sendiri tidak ikut kedaluwarsa
- Pelanggan tanpa `pin_hash` bisa lewat jalur 4 digit HP

### Fase 3 — Absen teknisi (nilai tertinggi, risiko tertinggi)

**Prasyarat wajib sebelum sprint dimulai:**
1. Audit coverage koordinat — query di §4.4. Kalau rendah, backfill dulu atau jalankan radius soft
2. Konfirmasi radius toleransi ke FOP yang tahu kondisi lapangan

Deliverable:
- Kolom `started_via` + koordinat di `tasks`
- `QrAttendanceController` + pemindai browser + resolusi koordinat dual-source (§4.4)
- Dashboard anomali

**Test wajib fase ini:**
- Happy path: scan sukses dalam radius → `started_via='qr_scan'`
- Di luar radius → 422 + log `out_of_radius`
- Task bukan milik tim → 422
- Tidak ada task terjadwal → 422
- Task sudah `IN_PROGRESS` → ditangani oleh `TaskService::start()` yang sudah ada
- Tanpa koordinat pelanggan (kedua kolom null) → lolos + flag `tanpa_koordinat`
- Koordinat dari `customer_addresses` dipakai kalau `customers.latitude` null
- Double-submit → ditolak oleh guard status `TERJADWAL` (by design, bukan kebetulan)

### Fase 4 — Payment Gateway

Belum diputuskan vendornya. Tidak memblokir Fase 1–3. Saat nanti diimplementasi, yang berubah hanya isi tombol "Bayar" — token, signature, dan stiker tidak tersentuh.

### Fase 5 — Login pelanggan

Blocked sampai portal pelanggan ada. PIN sudah siap dari Fase 2.

---

## 11. Yang perlu diputuskan sebelum mulai

1. **ONT dipasang di dalam atau di luar rumah?** Konfirmasi ke FOP sebelum Fase 2 dimulai. Jika di dalam rumah, §6.1 perlu ditinjau ulang — threat model bergeser ke insider/tamu, bukan orang lewat.
2. **Coverage koordinat pelanggan aktif** — jalankan query §4.4. Menentukan apakah Fase 3 bisa jalan dengan radius keras atau harus soft.
3. **Radius toleransi absen** — usulan 150 m; konfirmasi ke FOP.
4. **Media & ukuran stiker** — QR versi 4 (33×33 modul); memengaruhi ECC dan ukuran cetak minimum.
5. **Pelanggan legacy dengan `customer_code` kosong/bermasalah:**
   ```sql
   SELECT COUNT(*) FROM customers
   WHERE customer_code IS NULL OR customer_code = '' OR pop_id IS NULL;
   ```
6. **Program logistik cetak fisik** — printer, media, stok, prosedur (lihat §7.6). Harus direncanakan sebelum Fase 2, bukan setelah.
7. **Payment gateway** — tidak memblokir Fase 1–3, dibahas terpisah.

---

### 11.1 Status mengunci fase — mana yang benar-benar blocking

7 poin di atas **tidak semuanya menahan seluruh rencana**. Dipetakan per fase supaya sprint bisa mulai dari yang tidak terkunci, sementara sisanya jalan paralel.

| # | Isu | Mengunci Fase | Kenapa |
|---|---|---|---|
| 1 | ONT dalam/luar rumah | **2** (halaman tagihan publik) | Fase 1 tak menyentuh halaman publik sama sekali |
| 2 | Coverage koordinat pelanggan | **3** (absen) | Fallback soft-block sudah didesain (§4.4, §6.3) — Fase 3 tetap bisa mulai, akurasi guard-nya yang bergantung hasil query |
| 3 | Radius absen 150m | **3** (absen) | Dipakai di guard `TaskService::start()` lewat `QrAttendanceController` |
| 4 | Stiker/ECC — print test fisik | **2** (cetak kartu PIN) | Cetak QR **tanpa PIN** di Fase 1 tetap bisa jalan untuk validasi digital sebelum print test fisik selesai |
| 5 | Legacy `customer_code`/`pop_id` kosong | **Tidak mengunci fase mana pun** | Asal `issue()` menolak dengan jelas — lihat §11.2 |
| 6 | Logistik cetak fisik | **2** | Sudah dinyatakan eksplisit "wajib sebelum Fase 2" di §7.6 |
| 7 | Vendor payment gateway | **4** saja | Non-blocking terhadap Fase 1–3 sejak awal |

**Konsekuensi:** Fase 1 (Fondasi + Ticketing prefill) tidak terkunci satu pun dari 7 poin ini — sprint bisa mulai dari situ sekarang. Q1/Q3/Q6 (konfirmasi FOP/Operasional) dan Q2/Q5 (jalankan query) berjalan paralel, bukan prasyarat sebelum baris kode pertama.

### 11.2 Guard wajib — `issue()` menolak pelanggan legacy tanpa `customer_code`/`pop_id`

Sebelumnya cuma tersirat dari "keduanya bahan wajib HMAC" (§3.3). Dieksplisitkan sebagai kontrak service:

```php
// CustomerQrTokenService::issue()

public function issue(Customer $customer, ?User $actor = null): CustomerQrToken
{
    if (blank($customer->customer_code) || blank($customer->pop_id)) {
        throw ValidationException::withMessages([
            'customer' => 'Pelanggan ini belum punya REQ ID atau POP — QR tidak bisa diterbitkan sebelum data lengkap.',
        ]);
    }

    // ... generate token, hitung signature, simpan
}
```

Ditolak di titik penerbitan, bukan diam-diam menghasilkan token dengan bahan HMAC kosong/parsial — konsisten dengan prinsip "kegagalan proses harus terlihat, bukan QR yang diam-diam salah" yang sudah dipakai di alur `pop_mismatch` (§5).

## 12. Log keputusan

| Tanggal | Keputusan | Alasan |
|---|---|---|
| 2026-08-07 | Anchor identitas = `customers.customer_code` (REQ ID), **bukan** `display_id` | REQ ID permanen seumur hidup pelanggan. `display_id` hanya bungkus yang berubah RQ↔CID |
| 2026-08-07 | `pop_id` **wajib** ikut jadi bahan HMAC | `customer_code` cuma unik per POP. Tanpa `pop_id`, 2 pelanggan beda cabang dengan RQ sama → QR identik (kasus Winda/Endah `RQ000042`) |
| 2026-08-07 | `full_name` **tidak** ikut jadi bahan HMAC | Mutable, tidak menambah keunikan, PII |
| 2026-08-07 | Opaque token **dan** HMAC — keduanya wajib lolos | Token bisa dicabut per pelanggan; HMAC tidak bisa dipalsukan orang dalam |
| 2026-08-07 | Signature 10 char base32 (50 bit) | 20 bit hanya cukup sebagai checksum, bukan kontrol keamanan |
| 2026-08-07 | `QR_HMAC_SECRET` terpisah dari `APP_KEY` | Rotasi `APP_KEY` saat insiden tidak boleh mematikan seluruh stiker |
| 2026-08-07 | Re-homing POP → cabut token + cetak ulang stiker | Re-homing sangat jarang; cacat identitas permanen lebih buruk dari cetak ulang sesekali |
| 2026-08-07 | QR ≠ QRIS; payment gateway di fase terpisah | QR = identifier pelanggan, bukan instrumen bayar |
| 2026-08-07 | Token **tidak** auto-generate saat pelanggan dibuat | Pelanggan bisa batal sebelum instalasi |
| 2026-08-07 | Tombol "Mulai Task" manual **tidak** dihapus | Sinyal/kamera/koordinat bisa gagal. `started_via` membuat jalur manual terlihat di dashboard |
| 2026-08-07 | Radius absen 150 m (usulan, belum final) | GPS HP di area padat rutin meleset 50–100 m |
| 2026-08-07 | **PIN 6 digit di-generate bersamaan QR** | HMAC membuktikan QR asli, PIN membuktikan siapa yang memindai |
| 2026-08-07 | **PIN dicetak di kartu pelanggan, BUKAN di stiker ONT** | PIN di stiker = dua faktor jadi satu |
| 2026-08-07 | **PIN disimpan sebagai bcrypt hash, tampil sekali** | Dump DB tidak membocorkan PIN |
| 2026-08-07 | **`pin_hash` TIDAK masuk bahan HMAC** | Reset PIN mengubah signature → stiker mati |
| 2026-08-07 | **PIN menggantikan gerbang 4 digit HP** | 4 digit HP bukan rahasia. Jalur lama tetap jalan sampai PIN tergelar merata |
| 2026-08-07 | **PIN tidak dipakai untuk absen teknisi** | Melatih pelanggan menyebutkan PIN ke petugas = modus penipuan |
| 2026-08-07 | **QR ada di KEDUA media, PIN hanya di kartu** | Pelanggan tak perlu keluar rumah; teknisi tak perlu minta kartu |
| 2026-08-07 | **Token terbit saat `WAITING_INSTALLATION`** | Kartu harus ikut berangkat bersama teknisi |
| 2026-08-07 | **Penerbitan idempoten** | Instalasi bisa diulang; tanpa guard, PIN menumpuk |
| 2026-08-07 | **`pin_must_change=true` — wajib ganti PIN saat login pertama** | Teknisi memegang PIN sebelum diserahkan; PIN cetak = aktivasi sekali pakai |
| 2026-08-07 | **Stiker hilang → CETAK ULANG, bukan terbitkan ulang** | Reissue mematikan kartu pelanggan yang tidak kehilangan apa pun |
| 2026-08-07 | **Reissue token hanya untuk dugaan penyalahgunaan, pindah POP, terminated** | Kehilangan biasa tidak menandakan token bocor |
| 2026-08-07 | **Stiker hilang tidak dibuatkan task khusus** | Biaya kunjungan > manfaat; fungsi terganggu punya fallback |
| 2026-08-07 | **Reset PIN butuh verifikasi identitas ≥2 faktor** | Nomor HP bisa diketahui tetangga; titik masuk rekayasa sosial |
| 2026-08-07 | **Label stiker pakai REQ ID, bukan `display_id`/CID** | Token terbit saat `cid` belum ada; `display_id` basi setelah aktif |
| 2026-08-08 | **Token pakai `random_bytes(16)` base32, bukan ULID** | ULID hanya 80-bit random dan membocorkan timestamp penerbitan |
| 2026-08-08 | **URL memakai prefix versi `/q1/`** | Perubahan format payload di masa depan tidak memaksa cetak ulang stiker fisik |
| 2026-08-08 | **Permission `customers.qr.manage` dipisah jadi `.create` dan `.cancel`** | `manage` bukan `ActionCode` yang valid; mencabut token lebih destruktif dari menerbitkan |
| 2026-08-08 | **Permission absen = `tasks.qr_attendance.create`** | `qr_scan.attendance` tidak valid; `attendance` bukan action di `ActionCode` |
| 2026-08-08 | **Fallback ketik-manual di stiker ditiadakan** | 36 karakter di HP sambil berdiri di lokasi — tidak akan dipakai. Fallback = pilih dari daftar task |
| 2026-08-08 | **Koordinat: cek `customers.latitude` dulu, fallback ke `customer_addresses.latitude`** | Menghindari silent bug guard geolocation yang tampak aktif tapi selalu null |
| 2026-08-08 | **PIN kedaluwarsa otomatis 90 hari tanpa dipakai** | Menggantikan dashboard `pin_must_change > 30 hari` yang jadi kebisingan karena mayoritas pelanggan tidak login |
| 2026-08-08 | **`qr_scan_logs` dipruning: detail 90 hari, agregat seterusnya** | Tanpa retensi, tabel tumbuh tak terbatas |
| 2026-08-08 | **Absen dipindah ke Fase 3 (terakhir)** | Fase 1–2 dibangun dulu agar fondasi terbukti stabil sebelum fungsi paling sensitif diimplementasi. Konsekuensi: masalah absen fiktif tetap terbuka 2–3 sprint lebih lama — diterima |
| 2026-08-08 | **`previous_secrets` dirancang untuk transisi rotasi, maksimal 30 hari** | Tanpa strategi rotasi yang jelas, secret bocor = cetak ulang semua stiker sekaligus |
| 2026-08-08 | **`RateLimiter::for('qr-public', ...)` masuk checklist eksplisit Fase 1** | Review teknis: `throttle:qr-public` dipakai di route (§5) tapi limiter itu tidak ada di codebase (dicek langsung, nol hasil) — tanpa didaftarkan, route error saat diakses |
| 2026-08-08 | **Limiter IP+token 5/15 menit untuk verifikasi PIN masuk checklist eksplisit Fase 2** | Terpisah dari baseline `qr-public` per-IP; mencegah 1 IP mencoba banyak token berbeda secara paralel, melengkapi lockout per-token di DB (§4.1) |
| 2026-08-08 | **Seeder permission (`customers.qr.*`, `tasks.qr_attendance.create`, `qr_scan_logs.view`) masuk checklist eksplisit Fase 1** | Sebelumnya cuma tersirat dari "permission baru" — eksplisit supaya tidak terlewat |
| 2026-08-08 | **Nomor baris di dokumen ini diperlakukan sebagai rujukan saat-ditulis, bukan acuan permanen** | Kode berubah di luar siklus revisi dokumen (`Customer.php` sudah bergeser ~13 baris sejak draf ini ditulis); implementasi berpatokan ke nama metode/migration, bukan baris |
| 2026-08-08 | **7 poin §11 dipetakan ke fase yang dikunci masing-masing (§11.1)** — Fase 1 tidak terkunci satu pun | Business/operational open questions tidak boleh menahan seluruh sprint kalau yang dibutuhkan cuma sebagian fase |
| 2026-08-08 | **`issue()` menolak eksplisit pelanggan `customer_code`/`pop_id` kosong (§11.2)** | Sebelumnya cuma tersirat dari "bahan wajib HMAC" — kegagalan proses harus terlihat, bukan diam-diam menghasilkan token dari bahan tidak lengkap |

### Alternatif yang dipertimbangkan dan ditolak

| Alternatif | Alasan ditolak |
|---|---|
| Hash langsung dari `display_id` | `display_id` berubah RQ→CID seiring lifecycle; stiker tertempel mati |
| Hash dari REQ ID saja (tanpa POP) | REQ ID tidak unik global — QR bisa bentrok antar cabang |
| SHA256 polos tanpa secret | Semua bahan publik → siapa pun bisa hitung QR valid |
| Hanya HMAC, tanpa token DB | Tidak bisa dicabut; stiker hilang = rotasi secret = cetak ulang semua cabang |
| Hanya token DB, tanpa HMAC | Orang dalam bisa menyisipkan token yang langsung sah |
| ULID sebagai token | 80-bit random (bukan 128), membocorkan timestamp, sortable — salah untuk token identitas |
| URL tanpa versi `/q/{token}` | Perubahan format memaksa cetak ulang seluruh stiker fisik |
| Payload QR berisi data pelanggan | Stiker bisa difoto → kebocoran PII |
| QR internal sebagai instrumen bayar | QR non-QRIS tidak bisa dipindai m-banking |
| 4 QR berbeda untuk 4 fungsi | Pelanggan hanya punya satu stiker; routing lebih baik di server |
| PIN di stiker yang sama dengan QR | Foto stiker = QR + PIN sekaligus; dua faktor runtuh jadi satu |
| PIN 4 digit | Ruang tebakan 10⁴; terlalu tipis kalau rate limiter gagal |
| PIN disimpan plaintext | Dump/backup/log membocorkan PIN; admin yang bisa lihat PIN = PIN bukan bukti identitas |
| `pin_hash` ikut ditandatangani HMAC | Reset PIN mematikan stiker |
| PIN dipakai teknisi untuk absen | Melatih pelanggan menyebutkan PIN ke petugas — modus penipuan |
| Cabut token setiap stiker hilang | Kartu pelanggan ikut mati; tidak menambah keamanan |
| QR hanya di stiker | Pelanggan harus keluar rumah untuk cek tagihan |
| QR hanya di kartu | Teknisi harus minta kartu tiap kunjungan |
| Terbitkan token saat `registered` | Pelanggan bisa batal sebelum pemasangan |
| PIN cetak permanen tanpa wajib ganti | Teknisi yang mengantar kartu tahu PIN selamanya |
| Task khusus untuk menempel stiker pengganti | Biaya kunjungan > manfaat; ada fallback manual |
| `customers.qr.manage` sebagai satu permission | `manage` tidak ada di `ActionCode`; terbit dan cabut harus dipisah |
| Dashboard `pin_must_change > 30 hari` | Mayoritas pelanggan tidak pernah login → ribuan baris noise permanen |
| Fallback ketik-manual signature di stiker | 36 karakter di HP standing — tidak ada yang akan pakai; dead code yang memberi false sense of safety |
| Absen di Fase 2 (lebih awal) | Fondasi belum terbukti; risiko tertinggi seharusnya dibangun di atas yang sudah stabil |

### Koreksi selama perancangan

1. **Draf awal menyatakan "REQ ID tidak stabil".** Salah — yang berubah `display_id`, REQ ID permanen.
2. **Draf awal mengklaim HMAC diverifikasi sebelum query DB.** Salah — bahan HMAC memuat `pop_id` yang harus dibaca dari DB dulu.
3. **Draf awal mengusulkan checksum 4 karakter.** Terlalu pendek; dinaikkan ke 10 karakter.
4. **Draf awal menggunakan ULID sebagai token.** Salah — ULID hanya 80-bit random dan membocorkan timestamp. Diganti `random_bytes(16)`.
5. **Draf awal tidak punya versi di URL (`/q/{token}`).** Dikoreksi ke `/q1/{token}`.
6. **Draf awal memakai `customers.qr.manage`.** `manage` tidak valid di `ActionCode` — dipisah jadi `.create` dan `.cancel`.
7. **Draf awal hanya membaca `customers.latitude`.** Ada dua sumber koordinat; keduanya harus dicek dengan urutan prioritas.

---

## 13. Catatan review (diarsipkan)

Review teknis dilakukan 2026-08-07 setelah dokumen dianggap selesai. Temuan R1–R15 telah diterapkan seluruhnya ke badan dokumen pada revisi 2026-08-08. Bagian ini diarsipkan sebagai rekam jejak.

### Ringkasan temuan dan status

| # | Tingkat | Temuan | Status |
|---|---|---|---|
| R1 | 🔴 | Asumsi threat model (ONT dalam/luar rumah) belum diverifikasi | **Tindakan:** masuk §11 sebagai prasyarat wajib sebelum Fase 2 |
| R2 | 🔴 | ULID membocorkan timestamp, bukan 128-bit random | **Diterapkan:** token diganti ke `random_bytes(16)` base32 |
| R3 | 🔴 | HMAC: biaya nyata (kopling `pop_id`, cetak ulang saat re-homing), manfaatnya melindungi dari insider dengan akses DB terbatas | **Dipertahankan** dengan catatan: ini tempat pemangkasan pertama jika ruang lingkup harus dikurangi |
| R4 | 🔴 | Nama permission tidak valid di RBAC repo | **Diterapkan:** `manage` → `.create` + `.cancel`; `qr_scan.attendance` → `tasks.qr_attendance.create` |
| R5 | 🔴 | Fallback ketik-manual 36 karakter tidak akan pernah dipakai | **Diterapkan:** dihapus; diganti pilih-dari-daftar-task; signature tidak perlu dicetak di stiker |
| R6 | 🟡 | PIN dibangun untuk portal yang belum ada | **Diterima dengan sadar:** PIN tetap dibangun di Fase 2 karena menggantikan gerbang 4-digit HP yang lemah |
| R7 | 🟡 | Dashboard `pin_must_change > 30 hari` jadi kebisingan | **Diterapkan:** diganti TTL kedaluwarsa otomatis 90 hari |
| R8 | 🟡 | Tidak ada versi di URL | **Diterapkan:** `/q/{token}` → `/q1/{token}` |
| R9 | 🟡 | Tidak ada penanganan offline | **Diterima dengan sadar:** keterbatasan diakui; fallback manual ada; antrean offline tidak diimplementasi Fase 3 |
| R10 | 🟡 | Dua sumber koordinat, belum dipilih yang otoritatif | **Diterapkan:** prioritas `customers.latitude` → fallback `customer_addresses.latitude`; keduanya diaudit di §4.4 |
| R11 | 🟡 | Label fase "nilai tercepat" menyesatkan | **Diterapkan:** label diubah ke "risiko terendah" |
| R12 | 🟡 | Logistik cetak fisik tidak dianalisis | **Ditambahkan:** §7.6 dan §11 memuat pertanyaan yang harus dijawab sebelum Fase 2 |
| R13a | 🟢 | `qr_scan_logs` tanpa kebijakan retensi | **Diterapkan:** pruning 90 hari ditambahkan ke skema dan §4.2 |
| R13b | 🟢 | Kolom `signature` tersimpan bisa divergen setelah rotasi | **Diterima:** kolom tetap ada untuk audit media; verifikasi tetap hitung ulang HMAC |
| R13c | 🟢 | `previous_secrets` disebut tapi tidak dirancang | **Diterapkan:** strategi rotasi dirancang di §7.5 dengan batas transisi 30 hari |
| R13d | 🟢 | Absen tanpa idempotensi eksplisit | **Dicatat:** guard status `TERJADWAL` sudah melindungi by design, ditambahkan keterangan eksplisit di test Fase 3 |
| R13e | 🟢 | Staf login tidak bisa lihat halaman tagihan lewat QR | **Diterima dan dicatat:** routing implisit ini disengaja; akses tagihan lewat `customers.show` |
| R14 | — | Yang bertahan setelah review (tetap valid) | Dipertahankan seluruhnya di badan dokumen |
| R15 | — | Penilaian ruang lingkup: absen teknisi sebagai minimal viable | **Diterapkan sebagian:** absen dipindah ke Fase 3 (bukan dipangkas); urutan disesuaikan |



