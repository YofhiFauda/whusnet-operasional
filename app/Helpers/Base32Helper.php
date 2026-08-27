<?php

namespace App\Helpers;

/**
 * Base32 (RFC 4648) tanpa padding — PHP tidak punya base32_encode() bawaan.
 *
 * Dipakai buat token & signature QR pelanggan (docs/plan/qr-code/
 * rancangan-qr-pelanggan-final.md §3.2, §9), BUKAN base64: base32 aman
 * ditaruh di URL tanpa escaping, dan alfabetnya (tanpa 0/1/8) mengurangi
 * salah baca kalau suatu saat perlu dibaca manusia.
 */
class Base32Helper
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Encode biner mentah ke string base32 huruf besar, TANPA padding '='.
     */
    public static function encode(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $output .= self::ALPHABET[bindec($chunk)];
        }

        return $output;
    }
}
