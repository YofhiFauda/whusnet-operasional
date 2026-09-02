<?php

namespace App\Services;

use App\Helpers\Base32Helper;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerQrToken;
use App\Models\QrScanLog;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md — Fase 1 (fondasi) +
 * Fase 2 (PIN + halaman tagihan publik).
 *
 * Satu-satunya tempat token QR diterbitkan, dicabut, ditandatangani, dan
 * diverifikasi. Jangan hitung HMAC di tempat lain — signature() adalah
 * satu-satunya rumus yang boleh ada di codebase ini. Sama halnya PIN:
 * issuePin()/verifyPin() satu-satunya jalur baca/tulis pin_hash.
 */
class CustomerQrTokenService
{
    private const HMAC_ALGO = 'sha256';

    // 10 char base32 = 50 bit. Dipotong demi ukuran QR, tapi 50 bit tetap
    // ~10^15 percobaan — mustahil di-brute-force lewat HTTP walau tanpa
    // rate limit. Jangan dipotong lebih pendek (§3.3).
    private const SIG_LENGTH = 10;

    // 128-bit acak (random_bytes(16)) di-base32 pas jadi 26 karakter tanpa
    // padding — BUKAN ULID, lihat §3.2 kenapa ULID ditolak untuk token
    // identitas (membocorkan waktu penerbitan, cuma 80-bit random).
    private const TOKEN_LENGTH = 26;

    /**
     * Terbitkan token baru untuk pelanggan. IDEMPOTEN — kalau sudah ada
     * token aktif (belum dicabut), kembalikan yang lama, bukan bikin baru.
     * Instalasi bisa diulang (WorkflowTransition), penerbitan berulang
     * tidak boleh menghasilkan token kedua (§7.2).
     *
     * Menolak pelanggan dengan customer_code/pop_id kosong (§10 Fase 1) —
     * tanpa keduanya, bahan HMAC tidak lengkap dan QR tidak bisa ditandatangani.
     *
     * **Kenapa `lockForUpdate()` pada baris `customers`, bukan cuma cek
     * `CustomerQrTokenObserver`:** Observer menjaga invariant dari sisi
     * `creating()`, tapi cek-lalu-insert di sini (dan di Observer sendiri)
     * sama-sama query biasa — dua request `issue()` nyaris bersamaan (dua
     * admin klik "Terbitkan" untuk pelanggan yang sama) bisa dua-duanya
     * lolos cek "belum ada token aktif" SEBELUM salah satu commit
     * (TOCTOU race), lahir dua token aktif sekaligus. `SELECT ... FOR
     * UPDATE` pada baris pelanggan itu sendiri menyerialkan SEMUA
     * `issue()` untuk pelanggan yang sama — transaksi kedua nunggu sampai
     * transaksi pertama commit, baru baca ulang & nemu token yang baru
     * dibuat (return yang lama, bukan bikin token kedua). Pola sama persis
     * `TicketService::close()`/`cancel()`. Baris `customer_qr_tokens`
     * sendiri belum ada saat pelanggan belum pernah dapat token — gak ada
     * yang bisa dikunci di situ, makanya yang dikunci baris pelanggannya.
     */
    public function issue(Customer $customer, ?User $actor = null): CustomerQrToken
    {
        if (blank($customer->customer_code) || blank($customer->pop_id)) {
            throw new RuntimeException(
                "Pelanggan #{$customer->id} belum punya customer_code/pop_id lengkap — QR tidak bisa diterbitkan."
            );
        }

        return DB::transaction(function () use ($customer, $actor) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $existing = CustomerQrToken::where('customer_id', $customer->id)->whereNull('revoked_at')->first();
            if ($existing) {
                return $existing;
            }

            return CustomerQrToken::create([
                'customer_id' => $customer->id,
                'token' => $this->generateToken(),
                'signed_pop_id' => $customer->pop_id,
                'signed_customer_code' => $customer->customer_code,
                'issued_display_id' => $customer->display_id,
                'issued_at' => now(),
                'issued_by' => $actor?->id,
            ]);
        });
    }

    /**
     * Cabut token. Baris TIDAK dihapus — jejak audit (§4.1). Idempoten:
     * mencabut token yang sudah dicabut tidak menimpa reason/waktu lama.
     *
     * `lockForUpdate()` pada baris token itu sendiri — dua pencabutan
     * nyaris bersamaan (mis. admin klik "Cabut" dobel-klik) tidak boleh
     * menimpa `revoke_reason`/`revoked_by` yang tercatat duluan.
     */
    public function revoke(CustomerQrToken $qrToken, string $reason, ?User $actor = null): void
    {
        DB::transaction(function () use ($qrToken, $reason, $actor) {
            $locked = CustomerQrToken::whereKey($qrToken->id)->lockForUpdate()->firstOrFail();

            if ($locked->isRevoked()) {
                return;
            }

            $locked->update([
                'revoked_at' => now(),
                'revoked_by' => $actor?->id,
                'revoke_reason' => $reason,
            ]);

            // Sinkronkan instance yang caller pegang — caller sering masih
            // pakai $qrToken setelah revoke() balik (mis. flash message),
            // dan idempotency check pemanggil lain (isRevoked()) mesti
            // ikut lihat status barunya tanpa fresh() manual.
            $qrToken->setRawAttributes($locked->getAttributes());
        });
    }

    /**
     * Bahan tanda tangan: pop_id | customer_code | token (§3.3).
     *
     * - pop_id     WAJIB — customer_code cuma unik per POP (composite unique
     *              (pop_id, customer_code), migration
     *              scope_customer_code_unique_to_pop). Tanpa pop_id, 2
     *              pelanggan beda cabang dengan RQ sama menghasilkan
     *              signature identik.
     * - token      mengikat signature ke SATU token — tanpa ini, token yang
     *              sudah dicabut masih membawa signature sah, dan
     *              terbitkan-ulang menghasilkan QR identik dengan yang lama.
     * - full_name  SENGAJA TIDAK IKUT — mutable, tidak menambah keunikan, PII.
     *
     * Pemisah "|" tidak pernah muncul di pop_id (integer), customer_code
     * (alnum), maupun token (base32) — tanpa pemisah, (pop=1, code=RQ12)
     * dan (pop=11, code=Q12) menghasilkan bahan hash yang sama.
     */
    public function signature(int $popId, string $customerCode, string $token, ?string $secret = null): string
    {
        $secret ??= (string) config('qr.secret');
        $payload = $popId.'|'.$customerCode.'|'.$token;
        $raw = hash_hmac(self::HMAC_ALGO, $payload, $secret, binary: true);

        return substr(Base32Helper::encode($raw), 0, self::SIG_LENGTH);
    }

    /**
     * Verifikasi signature terhadap token yang SUDAH ditemukan di DB — bahan
     * HMAC (signed_pop_id, signed_customer_code) dibaca dari baris token itu
     * sendiri, BUKAN dari relasi `customer` (lihat kenapa di §4.1: kalau
     * pelanggan pindah POP, membaca ulang dari customer bikin signature
     * mismatch dengan yang tercetak di stiker).
     *
     * Cuma satu secret aktif — dukungan secret lama (rotasi §7.5, "stiker
     * lama tetap valid selama masa transisi") DICABUT (2026-08-27, perintah
     * eksplisit user): `QR_HMAC_SECRET` rotasi = semua QR lama wajib cetak
     * ulang, bukan ditolerir sementara. Simplifikasi sadar (CLAUDE.md
     * "sederhana, tidak overengineered") — fitur itu belum pernah dipakai
     * beneran di produksi.
     */
    public function verify(string $token, string $signature, CustomerQrToken $qrToken): bool
    {
        $expected = $this->signature(
            (int) $qrToken->signed_pop_id,
            $qrToken->signed_customer_code,
            $token
        );

        // hash_equals: perbandingan constant-time. Perbandingan biasa (===)
        // bocor lewat timing — penyerang bisa menebak signature karakter
        // demi karakter.
        return hash_equals($expected, strtoupper($signature));
    }

    /**
     * Resolusi satu scan `{token}.{sig}` ke status + baris token (kalau
     * ketemu). Dipakai QrScanController::dispatch() — urutan pengecekan
     * PERSIS §5:
     *   token tidak ketemu → bad_signature (kalau ketemu tapi sig salah)
     *   → token_revoked → pop_mismatch → success.
     *
     * Format token/sig sendiri (regex base32) sudah disaring di layer route
     * (§5) SEBELUM method ini dipanggil — jadi tidak perlu query DB sama
     * sekali untuk sampah yang jelas bukan base32.
     *
     * @return array{status: string, qrToken: ?CustomerQrToken}
     */
    public function resolve(string $token, string $signature): array
    {
        $qrToken = CustomerQrToken::where('token', $token)->first();

        if (! $qrToken) {
            return ['status' => 'token_not_found', 'qrToken' => null];
        }

        if (! $this->verify($token, $signature, $qrToken)) {
            return ['status' => 'bad_signature', 'qrToken' => $qrToken];
        }

        if ($qrToken->isRevoked()) {
            return ['status' => 'token_revoked', 'qrToken' => $qrToken];
        }

        $customer = $qrToken->customer;

        if (! $customer || (int) $customer->pop_id !== (int) $qrToken->signed_pop_id) {
            return ['status' => 'pop_mismatch', 'qrToken' => $qrToken];
        }

        return ['status' => 'success', 'qrToken' => $qrToken];
    }

    /**
     * URL lengkap `/q1/{token}.{sig}` — dipakai halaman lihat/cetak QR
     * (CustomerQrController) untuk render QR image & payload teks.
     */
    public function dispatchUrl(CustomerQrToken $qrToken): string
    {
        $signature = $this->signature(
            (int) $qrToken->signed_pop_id,
            $qrToken->signed_customer_code,
            $qrToken->token
        );

        return rtrim((string) config('qr.base_url'), '/')."/q1/{$qrToken->token}.{$signature}";
    }

    /**
     * Catat SATU baris scan (sukses maupun gagal) + update
     * last_scanned_at/scan_count kalau sukses. Semua kegagalan tetap
     * dicatat — scan gagal adalah sinyal, bukan sampah (§4.2).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordScan(array $attributes): QrScanLog
    {
        $log = QrScanLog::create($attributes + ['scanned_at' => now()]);

        if (($attributes['result'] ?? null) === 'success' && ! empty($attributes['customer_qr_token_id'])) {
            CustomerQrToken::whereKey($attributes['customer_qr_token_id'])->update([
                'last_scanned_at' => now(),
                'scan_count' => DB::raw('scan_count + 1'),
            ]);
        }

        return $log;
    }

    // 90 hari tanpa login → pin_hash di-null-kan otomatis (§6.5.2). PIN yang
    // sudah diganti sendiri (pasca wajib-ganti login pertama) TIDAK ikut
    // aturan ini — changePin() men-set pin_expires_at = null.
    private const PIN_TTL_DAYS = 90;

    // 5 gagal berturut-turut → kunci 15 menit. Ini lockout PER-TOKEN di DB,
    // bertahan lintas cache flush/restart — beda dari rate limiter
    // qr-billing-verify (per IP+kode, lihat AppServiceProvider) yang
    // menutup celah 1 IP mencoba banyak token berbeda paralel (§6.5.4).
    private const PIN_MAX_ATTEMPTS = 5;

    private const PIN_LOCKOUT_MINUTES = 15;

    /**
     * Terbitkan PIN baru (6 digit) untuk token yang SUDAH ada — dipanggil
     * staf lewat CustomerQrController::issuePin(), atau otomatis saat
     * pelanggan masuk WAITING_INSTALLATION (CustomerWorkflowService).
     *
     * TIDAK idempoten seperti issue() token — reset PIN memang harus selalu
     * menghasilkan PIN baru (§6.5.5 "Terbitkan Ulang PIN"), rotasi PIN
     * SENGAJA independen dari token/signature (§6.5.2): mengubah pin_hash
     * TIDAK BOLEH ikut masuk bahan HMAC, karena tiap reset PIN akan mematikan
     * seluruh stiker QR pelanggan itu kalau sampai ikut.
     *
     * Menulis SATU baris `AuditLog` per panggilan ("PIN diterbitkan"/"PIN
     * direset oleh X pada Y") — di Service, BUKAN di controller, supaya
     * riwayat PIN lengkap dari SEMUA jalur pemicu (staf klik tombol, ATAU
     * hook otomatis `CustomerWorkflowService` saat WAITING_INSTALLATION,
     * `$actor` null). PIN plaintext-nya sendiri TIDAK PERNAH ikut ditulis
     * ke sana (§6.5.3) — cuma jejak siapa/kapan.
     *
     * @return string PIN plaintext. Koreksi 2026-08-26 (perintah eksplisit
     *                user, membalik keputusan "Opsi A" sebelumnya):
     *                `pin_hash` sekarang REVERSIBLE (Crypt::encryptString,
     *                AES-256 pakai APP_KEY), bukan bcrypt — supaya
     *                `/qr/cetak` (kartu reprintable) bisa nampilin PIN
     *                kapan pun, bukan cuma sekali pas modal terbuka.
     *                Trade-off SADAR: siapa pun akses DB + APP_KEY bisa
     *                baca PIN semua pelanggan (beda dari hash yang buta
     *                permanen). Baris lama (dibuat sebelum revisi ini)
     *                masih bcrypt — revealPin() gagal decrypt & balikin
     *                null buat baris itu, BUKAN error (lihat revealPin()).
     */
    public function issuePin(CustomerQrToken $qrToken, ?User $actor = null): string
    {
        $plainPin = $this->generateStrongPin();

        DB::transaction(function () use ($qrToken, $actor, $plainPin) {
            $locked = CustomerQrToken::whereKey($qrToken->id)->lockForUpdate()->firstOrFail();
            $isReissue = $locked->pin_hash !== null;

            $locked->update([
                'pin_hash' => Crypt::encryptString($plainPin),
                'pin_issued_at' => now(),
                'pin_issued_by' => $actor?->id,
                'pin_version' => $locked->pin_version + 1,
                'pin_must_change' => true,
                'pin_first_used_at' => null,
                'pin_expires_at' => now()->addDays(self::PIN_TTL_DAYS),
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
            ]);

            $qrToken->setRawAttributes($locked->getAttributes());

            AuditLog::create([
                'user_id' => $actor?->id,
                'module' => 'QR Pelanggan',
                'action' => $isReissue ? 'pin_reissued' : 'pin_issued',
                'auditable_type' => CustomerQrToken::class,
                'auditable_id' => $locked->id,
                'old_values' => null,
                'new_values' => null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        });

        return $plainPin;
    }

    /**
     * Verifikasi PIN — urutan PERSIS §6.5.4:
     * kedaluwarsa → terkunci → cocok/tidak (lockout naik kalau gagal,
     * direset kalau berhasil).
     *
     * `lockForUpdate()` — tanpa ini, dua tebakan PIN nyaris bersamaan
     * (skrip brute-force paralel) bisa dua-duanya baca
     * `pin_failed_attempts` yang sama SEBELUM salah satu commit
     * increment-nya, satu hitungan ke-skip, dan lockout 5x jadi bisa
     * ditembus lebih dari 5 tebakan efektif (TOCTOU, pola sama seperti
     * `issue()`/`revoke()`).
     *
     * @return array{outcome: string, retry_after_minutes?: int} outcome:
     *                                                           expired | locked | invalid | success
     */
    public function verifyPin(CustomerQrToken $qrToken, string $pin): array
    {
        return DB::transaction(function () use ($qrToken, $pin) {
            $locked = CustomerQrToken::whereKey($qrToken->id)->lockForUpdate()->firstOrFail();
            $qrToken->setRawAttributes($locked->getAttributes());

            if (! $locked->hasActivePin()) {
                return ['outcome' => 'expired'];
            }

            if ($locked->isPinLocked()) {
                return [
                    'outcome' => 'locked',
                    'retry_after_minutes' => max(1, now()->diffInMinutes($locked->pin_locked_until, false)),
                ];
            }

            if (! hash_equals((string) $this->revealPin($locked), $pin)) {
                $attempts = $locked->pin_failed_attempts + 1;
                $update = ['pin_failed_attempts' => $attempts];

                if ($attempts >= self::PIN_MAX_ATTEMPTS) {
                    $update['pin_locked_until'] = now()->addMinutes(self::PIN_LOCKOUT_MINUTES);
                }

                $locked->update($update);
                $qrToken->setRawAttributes($locked->getAttributes());

                return ['outcome' => 'invalid'];
            }

            $locked->update([
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
                // Aktif dipakai = tidak kedaluwarsa (§6.5.2) — login yang
                // berhasil me-refresh jam TTL 90 hari.
                'pin_expires_at' => now()->addDays(self::PIN_TTL_DAYS),
            ]);
            $qrToken->setRawAttributes($locked->getAttributes());

            return ['outcome' => 'success'];
        });
    }

    /**
     * Jalur legacy — pelanggan belum punya PIN aktif (§6.1 "Belum punya PIN?
     * Masukkan 4 digit terakhir nomor HP terdaftar"). Dihapus total setelah
     * seluruh pelanggan aktif punya PIN; sengaja TIDAK ada percobaan/lockout
     * per-token di sini (4 digit HP jauh lebih tebak-able, jadi satu-satunya
     * penjaganya rate limiter qr-billing-verify per IP+kode — cukup untuk
     * fitur transisi yang memang akan dicabut).
     */
    public function verifyLegacyPhoneSuffix(CustomerQrToken $qrToken, string $last4): bool
    {
        $phone = (string) ($qrToken->customer?->primary_phone ?? '');

        return $phone !== '' && substr($phone, -4) === $last4;
    }

    /**
     * Wajib ganti PIN saat login pertama (§6.5.5b) — PIN baru TIDAK BOLEH
     * sama dengan PIN cetak (dicek lewat hash lama, satu-satunya cara karena
     * plaintext lama tidak pernah disimpan) dan tidak boleh pola lemah.
     *
     * @throws RuntimeException kalau PIN baru sama dengan lama atau lemah
     */
    public function changePin(CustomerQrToken $qrToken, string $newPin): void
    {
        DB::transaction(function () use ($qrToken, $newPin) {
            $locked = CustomerQrToken::whereKey($qrToken->id)->lockForUpdate()->firstOrFail();

            if ($newPin === $this->revealPin($locked)) {
                throw new RuntimeException('PIN baru tidak boleh sama dengan PIN sebelumnya.');
            }

            if ($this->isWeakPin($newPin)) {
                throw new RuntimeException('PIN terlalu mudah ditebak — hindari angka berurutan atau berulang.');
            }

            $locked->update([
                'pin_hash' => Crypt::encryptString($newPin),
                'pin_must_change' => false,
                'pin_first_used_at' => $locked->pin_first_used_at ?? now(),
                'pin_version' => $locked->pin_version + 1,
                // PIN yang sudah diganti sendiri tidak ikut kedaluwarsa (§6.5.2).
                'pin_expires_at' => null,
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
            ]);

            $qrToken->setRawAttributes($locked->getAttributes());
        });
    }

    /**
     * Buka PIN plaintext dari `pin_hash` (sekarang reversible, lihat
     * docblock issuePin()) — dipakai `/qr/cetak` biar kartu reprintable
     * bisa nunjukin PIN kapan pun, dan verifyPin()/changePin() internal.
     *
     * Balikin null (BUKAN exception) kalau: belum ada PIN sama sekali, ATAU
     * baris ini masih format bcrypt lama (dibuat sebelum revisi 2026-08-26
     * ke enkripsi reversible) — baris begitu buta permanen, satu-satunya
     * jalan keluar reset PIN (issuePin() lagi, menimpa dengan format baru).
     */
    public function revealPin(CustomerQrToken $qrToken): ?string
    {
        if ($qrToken->pin_hash === null) {
            return null;
        }

        try {
            return Crypt::decryptString($qrToken->pin_hash);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * random_int (CSPRNG, BUKAN rand()/mt_rand()) diulang sampai lolos
     * isWeakPin() — §6.5.2: 000000/123456/6 digit sama ditolak. Cek
     * "tanggal lahir pelanggan" di dokumen SENGAJA tidak diimplementasikan —
     * `customers` tidak punya kolom tanggal lahir sama sekali di skema ini,
     * jadi tidak ada bahan untuk dicek (bukan kelalaian, tidak ada datanya).
     */
    private function generateStrongPin(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while ($this->isWeakPin($pin));

        return $pin;
    }

    private function isWeakPin(string $pin): bool
    {
        if (preg_match('/^(\d)\1{5}$/', $pin)) {
            return true; // 6 digit sama (termasuk 000000)
        }

        $ascending = '0123456789';
        $descending = '9876543210';
        if (str_contains($ascending, $pin) || str_contains($descending, $pin)) {
            return true; // berurutan naik/turun, termasuk 123456
        }

        return false;
    }

    private function generateToken(): string
    {
        return substr(Base32Helper::encode(random_bytes(16)), 0, self::TOKEN_LENGTH);
    }
}
