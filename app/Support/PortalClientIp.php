<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * IP pelanggan ASLI untuk request yang datang lewat Portal Pelanggan (BFF).
 *
 * Portal memanggil Laravel dari SERVER Next.js, jadi `$request->ip()` selalu IP
 * server portal — sama untuk seluruh pelanggan. Tanpa ini, limiter per-IP
 * (customer-portal-auth-ip, customer-portal-api) menghitung 30.000 pelanggan
 * sebagai SATU klien dan menutup login semua orang begitu satu jatah habis.
 *
 * Portal mengirim IP pelanggan di header `X-Portal-Client-IP`. Header itu
 * dipercaya HANYA bila request juga membawa `X-Portal-Client` yang cocok dengan
 * secret server — kalau tidak, siapa pun bisa memalsukan IP untuk lolos dari
 * limiter. Header kosong/tidak valid/tanpa secret → jatuh ke `$request->ip()`,
 * jadi portal lama yang belum mengirim header tetap berfungsi seperti dulu.
 */
class PortalClientIp
{
    public const HEADER = 'X-Portal-Client-IP';

    public static function resolve(Request $request): string
    {
        $fallback = (string) $request->ip();

        $secret = config('webhooks.portal_client_secret');
        $given = $request->header('X-Portal-Client');

        if (! $secret || ! is_string($given) || ! hash_equals((string) $secret, $given)) {
            return $fallback;
        }

        $candidate = trim((string) $request->header(self::HEADER));

        return filter_var($candidate, FILTER_VALIDATE_IP) !== false ? $candidate : $fallback;
    }
}
