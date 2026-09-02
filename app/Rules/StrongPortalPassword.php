<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Password portal pelanggan tidak boleh mengandung identitas yang gampang
 * ditebak orang lain (login_id, nomor HP) dan tidak boleh masuk daftar
 * password paling umum (docs/api/api-portal-pelanggan/business-logic.md
 * §Aktivasi akun, keputusan.md §6.6.5). Panjang minimum (>=10 karakter)
 * DITEGAKKAN DI FORMREQUEST lewat rule `min:10`, bukan di sini — biar pesan
 * error bawaan Laravel yang dipakai untuk kasus itu.
 *
 * PENYIMPANGAN TERDOKUMENTASI: dokumen juga mensyaratkan tolak password yang
 * memuat tanggal lahir pelanggan. `Customer` TIDAK PUNYA kolom tanggal lahir
 * sama sekali (dikonfirmasi grep nihil saat eksplorasi Fase 2) — aturan ini
 * TIDAK diimplementasikan sampai kolom itu ada atau pemilik produk
 * memutuskan lain. Lihat docs/api/api-portal-pelanggan/rencana-implementasi.md
 * Fase 2.
 *
 * Daftar password umum: array statis di bawah, BUKAN dependency composer
 * baru (perubahan dependency butuh persetujuan terpisah, CLAUDE.md). Ini
 * PLACEHOLDER dikonfirmasi pemilik produk 2026-08-24 — boleh ditambah/ditinjau
 * ulang kapan saja tanpa mengubah cara kerja rule ini.
 */
class StrongPortalPassword implements ValidationRule
{
    /**
     * @var list<string>
     */
    private const COMMON_PASSWORDS = [
        'password', 'password1', 'password123', '12345678', '123456789',
        '1234567890', 'qwerty123', 'qwertyuiop', 'admin123', 'indonesia',
        'indonesia1', 'sayang123', 'jakarta123', 'bismillah', 'assalamualaikum',
        'aku sayang kamu', 'jancok123', 'wifigratis', 'internetgratis',
        'passw0rd', 'p@ssw0rd', '87654321', '11111111', '00000000',
        'iloveyou', 'welcome123', 'letmein123', 'changeme123', 'selamatpagi',
        'terserah123',
    ];

    public function __construct(
        private readonly ?string $loginId,
        private readonly ?string $primaryPhone,
        private readonly ?string $alternativePhone,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $needle = Str::lower($value);

        foreach ($this->personalTokens() as $token) {
            if ($token !== '' && Str::contains($needle, Str::lower($token))) {
                $fail('Password tidak boleh mengandung login ID atau nomor HP.');

                return;
            }
        }

        if (in_array($needle, self::COMMON_PASSWORDS, true)) {
            $fail('Password ini terlalu umum dan mudah ditebak, gunakan yang lain.');
        }
    }

    /**
     * @return list<string>
     */
    private function personalTokens(): array
    {
        return array_values(array_filter([
            $this->loginId,
            $this->primaryPhone,
            $this->alternativePhone,
        ]));
    }
}
