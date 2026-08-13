<?php

namespace App\Support;

/**
 * Menormalkan nominal rupiah yang diketik manusia jadi angka yang dimengerti
 * validator (`numeric`) dan kolom desimal DB.
 *
 * Kasir mengetik `150.000` — titik sebagai pemisah ribuan, persis seperti yang
 * dicetak di kwitansi dan tampil di seluruh layar. Sebelum ini `amount` masuk
 * apa adanya: `150.000` lolos `numeric` sebagai **seratus lima puluh rupiah**
 * (titik dibaca desimal Inggris). Tidak ada error, tidak ada peringatan —
 * pembayaran tercatat 1.000 kali lebih kecil dan invoice tetap "belum lunas".
 * Karena itu normalisasi ini duduk di SISI SERVER, bukan cuma di masking JS:
 * form bisa dikirim dari mana saja, dan salah baca nominal uang tidak boleh
 * bergantung pada JavaScript yang jalan.
 *
 * Aturan (sengaja konservatif — apa yang tidak dikenali TIDAK ditebak, biar
 * ditolak validator daripada diam-diam berubah nilainya):
 *
 * | Ketikan       | Hasil      | Alasan                                  |
 * |---------------|------------|-----------------------------------------|
 * | `150.000`     | `150000`   | grup 3 digit = ribuan Indonesia         |
 * | `1.500.000`   | `1500000`  | idem, banyak grup                       |
 * | `Rp 150.000`  | `150000`   | prefiks & spasi dibuang                 |
 * | `150.000,50`  | `150000.50`| koma = desimal Indonesia                |
 * | `150000,50`   | `150000.50`| idem, tanpa pemisah ribuan              |
 * | `150000`      | `150000`   | apa adanya                              |
 * | `150000.50`   | `150000.50`| format mesin, jangan diutak-atik        |
 * | `1.50`        | `1.50`     | BUKAN grup 3 digit → dibiarkan          |
 * | `12.34.56`    | `12.34.56` | tak dikenali → biar validator menolak   |
 */
class RupiahInput
{
    /**
     * Nilai non-string (angka dari JSON, null) dikembalikan apa adanya —
     * yang perlu ditebak cuma teks ketikan.
     */
    public static function parse(mixed $nilai): mixed
    {
        if (! is_string($nilai)) {
            return $nilai;
        }

        // NBSP ikut dibuang: hasil salin-tempel dari tabel/PDF sering membawanya.
        $bersih = trim(str_replace(["\u{00A0}", ' ', 'Rp', 'rp', 'RP'], '', $nilai));

        if ($bersih === '') {
            return $nilai;
        }

        // Ribuan Indonesia, dengan/tanpa desimal koma: 150.000 / 1.500.000,50
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $bersih)) {
            return str_replace(',', '.', str_replace('.', '', $bersih));
        }

        // Tanpa pemisah ribuan, desimal koma: 150000,50
        if (preg_match('/^-?\d+,\d+$/', $bersih)) {
            return str_replace(',', '.', $bersih);
        }

        // Angka polos atau desimal titik (format mesin) — biarkan.
        if (preg_match('/^-?\d+(\.\d+)?$/', $bersih)) {
            return $bersih;
        }

        // Bentuk lain tidak ditebak. Nilai asli dikembalikan supaya pesan
        // error validasi menunjuk ke apa yang benar-benar diketik pengguna.
        return $nilai;
    }

    /**
     * Menormalkan beberapa field sekaligus di dalam array (mis. payload batch).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function parseKeys(array $data, string ...$keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = static::parse($data[$key]);
            }
        }

        return $data;
    }
}
