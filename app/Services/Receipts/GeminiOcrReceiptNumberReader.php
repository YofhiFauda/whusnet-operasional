<?php

namespace App\Services\Receipts;

use App\Enums\ReceiptMatchMethod;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Jalur CADANGAN: baca nomor pembayaran lewat Gemini, dipakai hanya kalau QR
 * gagal (sobek, buram, hasil fotokopi berkali-kali).
 *
 * **Mati secara default.** Tanpa `GEMINI_API_KEY`, `isAvailable()` false dan
 * berkas langsung jatuh ke penanganan manusia. Itu keadaan normal, bukan
 * error: modul ini harus tetap jalan penuh tanpa layanan berbayar, dan tak
 * boleh ada biaya yang keluar sebelum diputuskan.
 *
 * Yang diminta ke model sengaja SEMPIT — satu nomor dengan format yang sudah
 * ditentukan, bukan "pahami kwitansi ini". Semakin sempit pertanyaannya,
 * semakin kecil ruang model mengarang, dan hasilnya tetap divalidasi ulang
 * terhadap pola nomor + keberadaan payment di database.
 */
class GeminiOcrReceiptNumberReader implements ReceiptNumberReader
{
    public function read(string $absolutePath): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        if (! is_readable($absolutePath)) {
            return null;
        }

        $model = (string) config('services.gemini.model');
        $endpoint = rtrim((string) config('services.gemini.endpoint'), '/')
            ."/models/{$model}:generateContent";

        $response = Http::timeout((int) config('services.gemini.timeout', 30))
            ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->post($endpoint, [
                'contents' => [[
                    'parts' => [
                        ['text' => $this->prompt()],
                        ['inline_data' => [
                            'mime_type' => mime_content_type($absolutePath) ?: 'image/jpeg',
                            'data' => base64_encode((string) file_get_contents($absolutePath)),
                        ]],
                    ],
                ]],
                // Deterministik semaksimal mungkin: ini pekerjaan transkripsi,
                // bukan pekerjaan kreatif.
                'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 32],
            ]);

        if ($response->failed()) {
            // Kegagalan teknis DILEMPAR, bukan dikembalikan null — supaya
            // pemanggil bisa membedakan "tidak terbaca" (hasil sah) dari
            // "layanannya rusak" (perlu dicatat & bisa dicoba ulang).
            throw new RuntimeException('Gemini OCR gagal: HTTP '.$response->status());
        }

        $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));

        return $text !== '' && strtoupper($text) !== 'NONE' ? $text : null;
    }

    public function method(): ReceiptMatchMethod
    {
        return ReceiptMatchMethod::OCR;
    }

    public function isAvailable(): bool
    {
        return filled(config('services.gemini.key'));
    }

    private function prompt(): string
    {
        return implode(' ', [
            'Baca gambar kwitansi ini dan kembalikan HANYA nomor pembayarannya.',
            'Formatnya PAY-YYYYMM-NNNN, contoh PAY-202608-0042.',
            'Jangan tambahkan penjelasan, tanda baca, atau teks lain.',
            'Kalau nomor itu tidak terlihat jelas, jawab persis: NONE',
        ]);
    }
}
