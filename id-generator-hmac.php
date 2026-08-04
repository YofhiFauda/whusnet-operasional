<?php

/**
 * Enterprise ID Generator — Hybrid HMAC Version (PHP)
 * ==================================================================
 * Skema:
 *   TCK-[BRANCH]-[HASH12]
 *   INV-REG-[BRANCH]-[HASH12]
 *   INV-BLN-[BRANCH]-[HASH12]
 *   PAY-REG-[BRANCH]-[HASH12]
 *   PAY-BLN-[BRANCH]-[HASH12]
 *
 * Pakai hash_hmac (BUKAN md5/sha1 biasa) supaya ID tidak bisa dipalsukan
 * tanpa tahu secret key. Hash bersifat satu arah — untuk identifikasi,
 * simpan sebagai kolom UNIQUE + INDEX di database (lookup, bukan decode).
 * ==================================================================
 */
class IdGenerator
{
    private const HASH_LENGTH = 12;

    /**
     * Ambil secret dari environment. Jangan hardcode di source code,
     * dan jangan commit ke repository.
     */
    private static function getSecret(): string
    {
        $secret = getenv('ID_HMAC_SECRET');
        if (! $secret) {
            throw new RuntimeException('ID_HMAC_SECRET belum di-set di environment.');
        }

        return $secret;
    }

    private static function validateBranch(string $branch): void
    {
        if (! preg_match('/^[A-Z]{3}$/', $branch)) {
            throw new InvalidArgumentException("Kode branch harus 3 huruf kapital, diterima: \"{$branch}\"");
        }
    }

    private static function computeHash(string $payload, int $nonce = 0): string
    {
        $secret = self::getSecret();
        $fullPayload = $nonce > 0 ? "{$payload}|nonce={$nonce}" : $payload;
        $hmac = hash_hmac('sha256', $fullPayload, $secret);

        return strtoupper(substr($hmac, 0, self::HASH_LENGTH));
    }

    /**
     * Generate satu ID hash (tanpa cek collision ke DB).
     *
     * @param  string  $domain  "TCK" | "INV" | "PAY"
     * @param  string|null  $subType  "REG" | "BLN" (wajib untuk INV & PAY, null untuk TCK)
     * @param  string  $branch  kode cabang 3 huruf, mis. "JTS"
     * @param  string  $cid  customer id / entity id sumber
     * @param  string  $date  tanggal/periode transaksi, mis. "25-07-2026"
     * @param  string  $refId  nomor referensi internal (billId, ticketId, dst)
     * @param  int  $nonce  dipakai untuk retry saat collision
     */
    public static function generate(
        string $domain,
        ?string $subType,
        string $branch,
        string $cid,
        string $date,
        string $refId,
        int $nonce = 0
    ): string {
        self::validateBranch($branch);

        if ($domain !== 'TCK' && ! $subType) {
            throw new InvalidArgumentException("subType (REG/BLN) wajib diisi untuk domain {$domain}");
        }

        $prefix = $domain === 'TCK' ? 'TCK' : "{$domain}-{$subType}";
        $branchUpper = strtoupper($branch);
        $payload = "{$domain}|".($subType ?? '')."|{$branchUpper}|{$cid}|{$date}|{$refId}";
        $hash = self::computeHash($payload, $nonce);

        return "{$prefix}-{$branchUpper}-{$hash}";
    }

    /**
     * Generate ID dengan otomatis retry kalau collision terdeteksi di database.
     * $checkNotExists menerima kandidat ID, return true kalau BELUM dipakai (aman).
     */
    public static function generateUnique(
        string $domain,
        ?string $subType,
        string $branch,
        string $cid,
        string $date,
        string $refId,
        callable $checkNotExists,
        int $maxRetries = 5
    ): string {
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $candidate = self::generate($domain, $subType, $branch, $cid, $date, $refId, $attempt);
            if ($checkNotExists($candidate)) {
                return $candidate;
            }
        }
        throw new RuntimeException("Gagal generate ID unik setelah {$maxRetries} percobaan.");
    }

    /**
     * Validasi format ID (bentuk saja, bukan validasi keberadaan data di DB).
     */
    public static function isValidFormat(string $id): bool
    {
        $tckPattern = '/^TCK-[A-Z]{3}-[0-9A-F]{12}$/';
        $invPayPattern = '/^(INV|PAY)-(REG|BLN)-[A-Z]{3}-[0-9A-F]{12}$/';

        return (bool) (preg_match($tckPattern, $id) || preg_match($invPayPattern, $id));
    }
}

// ==================================================================
// Contoh pemakaian — mengikuti kasus yang kamu kasih
// ==================================================================
//
// putenv('ID_HMAC_SECRET=ganti-dengan-secret-kuat-minimal-32-char');
//
// $cid = 'C1X4ARQ000004';
// $date = '25-07-2026';
// $billId = '0001';
//
// $ticketId = IdGenerator::generate('TCK', null, 'JTS', $cid, $date, $billId);
// // => "TCK-JTS-<hash12>"  (nilai aktual tergantung secret yang dipakai)
//
// $invoiceId = IdGenerator::generate('INV', 'BLN', 'JTS', $cid, '2026-08', '0105');
// // => "INV-BLN-JTS-<hash12>"
//
// // Dengan cek collision ke DB (contoh pakai PDO):
// $paymentId = IdGenerator::generateUnique(
//     'PAY', 'REG', 'JTS', $cid, $date, '0012',
//     function (string $candidate) use ($pdo): bool {
//         $stmt = $pdo->prepare('SELECT 1 FROM payments WHERE public_id = ?');
//         $stmt->execute([$candidate]);
//         return $stmt->fetch() === false; // true = belum dipakai, aman
//     }
// );
//
// var_dump(IdGenerator::isValidFormat('TCK-JTS-A51F686EC0A8')); // true
