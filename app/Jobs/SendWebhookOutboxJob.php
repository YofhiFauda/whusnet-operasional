<?php

namespace App\Jobs;

use App\Models\WebhookOutbox;
use App\Services\Webhooks\InstallationWebhookPresenter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Kirim satu baris `webhook_outbox` — dua transport (`http_json` ke Website B,
 * `telegram` ke Telegram Eksternal), satu job yang sama, dibedakan lewat
 * kolom `destination`. Backoff 1m/5m/30m/2j/6j, maks 8 percobaan (angka sama
 * dengan §6.6.6 portal, docs/api/business-logic.md — satu kebijakan retry di
 * seluruh sistem).
 *
 * Payload TIDAK dirakit ulang di sini — dibaca apa adanya dari `$row->payload`
 * yang sudah tersimpan sejak listener menulisnya. Merakit ulang tiap percobaan
 * berisiko mengirim data yang sudah berubah, lalu dibuang penerima sebagai
 * duplikat `event_id` — perubahan hilang tanpa jejak.
 */
class SendWebhookOutboxJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /** 1 menit, 5 menit, 30 menit, 2 jam, 6 jam — nilai terakhir berulang untuk sisa percobaan. */
    public array $backoff = [60, 300, 1800, 7200, 21600];

    // Sengaja TIDAK dideklarasikan sebagai properti $afterCommit di sini —
    // trait Queueable sudah mendeklarasikannya dengan tipe berbeda (persis
    // masalah $queue yang diperingatkan MatchPaymentReceipt), menimpanya
    // bikin fatal error "incompatible property definition" saat class
    // dikomposisi. `->afterCommit()` dipanggil di titik dispatch
    // (SendInstallationActivatedWebhooks), bukan lewat properti di sini.
    public function __construct(public readonly int $outboxId)
    {
        // Antrean SENDIRI, bukan `default` — biar retry webhook (bisa 8x,
        // sampai 6 jam) tidak menahan antrean broadcast realtime dashboard
        // FOP. Lewat onQueue(), bukan properti $queue (lihat alasan sama di
        // MatchPaymentReceipt: trait Queueable sudah mendeklarasikan properti
        // itu, menimpanya bikin job gagal dikomposisi).
        $this->onQueue('webhooks');
    }

    public function handle(InstallationWebhookPresenter $presenter): void
    {
        $row = WebhookOutbox::find($this->outboxId);

        // Idempoten: baris hilang atau sudah final (delivered/skipped) —
        // tidak ada yang perlu dikerjakan. Bisa terjadi kalau job kedispatch
        // dobel (afterCommit + retry queue yang tumpang tindih).
        if (! $row || in_array($row->status, ['delivered', 'skipped'], true)) {
            return;
        }

        // Guard urutan: kalau event yang SUDAH delivered untuk pelanggan+event
        // ini punya nomor aktivasi lebih tinggi dari baris ini, baris ini
        // basi — datang belakangan gara-gara backoff, bukan berarti lebih
        // baru. Dibuang, bukan menimpa. Belum pernah dicoba kirim, jadi
        // attempts TIDAK dinaikkan.
        $maxDelivered = WebhookOutbox::maxDeliveredActivationNumber($row->customer_id, $row->event, $row->destination);
        $thisNumber = WebhookOutbox::activationNumberFromKey($row->idempotency_key) ?? 0;

        if ($maxDelivered > 0 && $thisNumber > 0 && $thisNumber < $maxDelivered) {
            $row->update([
                'status' => 'skipped',
                'last_error' => "superseded: idempotency #{$thisNumber} lebih rendah dari #{$maxDelivered} yang sudah delivered",
            ]);

            return;
        }

        // Guard konfigurasi — HANYA untuk website_b. Endpoint wajib HTTPS
        // (docs/api/business-logic.md "Keamanan") dan secret wajib terisi,
        // kalau tidak HMAC tidak bisa dihitung. Ditolak LANGSUNG jadi
        // `failed`, TANPA masuk siklus retry 8x/backoff 6 jam — url http://
        // atau secret kosong bukan kegagalan jaringan sesaat yang bisa
        // sembuh sendiri lewat retry, itu salah konfigurasi yang cuma
        // sembuh kalau manusia membetulkan `.env`. Menghabiskan 8 percobaan
        // untuk kesalahan yang pasti gagal lagi cuma menunda orang sadar
        // ada yang salah setup.
        //
        // Telegram dapat guard yang setara (bot_token + chat_id wajib terisi):
        // token kosong bikin URL jadi `https://api.telegram.org/bot/sendMessage`
        // yang dijawab 404 "Not Found" — kelihatan seperti galat jaringan
        // padahal tidak akan pernah sembuh sendiri. Tanpa guard ini, satu
        // salah setup `.env` membakar 8 percobaan selama 6 jam sebelum
        // siapa pun sadar (kejadian 2026-08-20).
        if ($configError = $this->configError($row->destination)) {
            $row->update([
                'status' => 'failed',
                'last_error' => $configError,
            ]);

            Log::error('SendWebhookOutboxJob: konfigurasi tujuan tidak valid, baris ditandai failed tanpa retry.', [
                'outbox_id' => $row->id,
                'destination' => $row->destination,
            ]);

            return;
        }

        $row->increment('attempts');

        try {
            $result = match ($row->destination) {
                'website_b' => $this->sendHttpJson($row),
                'telegram_external' => $this->sendTelegram($row, $presenter),
                default => throw new RuntimeException("Tujuan webhook tidak dikenal: {$row->destination}"),
            };
        } catch (Throwable $e) {
            $row->update(['last_error' => Str::limit($e->getMessage(), 1000)]);

            throw $e;
        }

        if (! $result['success']) {
            // Kegagalan permanen (mis. Telegram menjawab 401/403/404: token
            // salah, bot ditendang dari channel, chat_id tidak ada) TIDAK
            // dilempar — mengulangnya 8x sampai 6 jam mustahil berhasil dan
            // cuma menunda orang sadar setup-nya salah. Baris tetap tinggal
            // sebagai `failed` supaya masuk daftar rekonsiliasi, sesuai
            // "kegagalan tidak boleh hilang diam-diam"
            // (docs/api/business-logic.md).
            if ($result['permanent'] ?? false) {
                $row->update([
                    'status' => 'failed',
                    'response_status' => $result['status'],
                    'last_error' => Str::limit((string) $result['error'], 1000),
                ]);

                Log::error('SendWebhookOutboxJob: kegagalan permanen dari tujuan, baris ditandai failed tanpa retry.', [
                    'outbox_id' => $row->id,
                    'destination' => $row->destination,
                    'response_status' => $result['status'],
                ]);

                return;
            }

            $row->update([
                'response_status' => $result['status'],
                'last_error' => Str::limit((string) $result['error'], 1000),
            ]);

            // Lempar supaya mekanisme retry/backoff Laravel jalan — sukses
            // HTTP (2xx / ok:true) adalah satu-satunya jalan keluar normal.
            throw new RuntimeException((string) $result['error']);
        }

        $row->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'response_status' => $result['status'],
            'last_error' => null,
        ]);
    }

    /**
     * Dipanggil Laravel otomatis setelah 8 percobaan habis. Kegagalan TIDAK
     * boleh hilang diam-diam — baris tetap ada, status `failed`, jadi daftar
     * rekonsiliasi "event mana yang belum sampai".
     */
    public function failed(Throwable $e): void
    {
        WebhookOutbox::find($this->outboxId)?->update(['status' => 'failed']);
    }

    /**
     * Pesan galat konfigurasi untuk tujuan ini, atau null kalau konfigurasinya
     * sah. Dikembalikan sebagai pesan (bukan bool) supaya `last_error`
     * menyebut tujuan mana yang salah — waktu ada dua tujuan, "konfigurasi
     * tidak valid" saja tidak cukup untuk tahu file `.env` baris mana yang
     * harus dibetulkan.
     */
    private function configError(string $destination): ?string
    {
        if ($destination === 'website_b') {
            $config = config('webhooks.website_b');
            $url = (string) ($config['url'] ?? '');
            $secret = (string) ($config['secret'] ?? '');

            if (! str_starts_with($url, 'https://') || $secret === '') {
                return 'Konfigurasi webhooks.website_b tidak valid: url wajib https:// dan secret wajib diisi.';
            }

            return null;
        }

        if ($destination === 'telegram_external') {
            $config = config('webhooks.telegram_external');
            $token = trim((string) ($config['bot_token'] ?? ''));
            $chatId = trim((string) ($config['chat_id'] ?? ''));

            if ($token === '' || $chatId === '') {
                return 'Konfigurasi webhooks.telegram_external tidak valid: bot_token dan chat_id wajib diisi.';
            }

            return null;
        }

        return null;
    }

    /**
     * Body yang di-HMAC dan yang dikirim WAJIB string yang sama persis.
     * json_encode() sekali di sini, dipakai buat menandatangani DAN sebagai
     * raw body kiriman — Http::post($url, $array) tidak dipakai karena ia
     * meng-encode array-nya sendiri, berisiko beda string dari yang
     * ditandatangani (urutan kunci bisa berubah, signature gagal tanpa sebab
     * yang kelihatan di sisi penerima).
     */
    private function buildSignedBody(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{success: bool, status: int, error: string|null, permanent: bool}
     */
    private function sendHttpJson(WebhookOutbox $row): array
    {
        $config = config('webhooks.website_b');
        $rawBody = $this->buildSignedBody($row->payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", (string) $config['secret']);

        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Whusnet-Signature' => "t={$timestamp},v1={$signature}",
            ])
            ->withBody($rawBody, 'application/json')
            ->post((string) $config['url']);

        // Website B SENGAJA tidak punya klasifikasi permanen seperti Telegram:
        // 4xx dari endpoint mitra bisa saja sementara (deploy setengah jalan,
        // rotasi secret yang belum kelar di sisi sana), dan kesalahan setup
        // yang benar-benar mustahil sembuh sudah dijaring lebih awal oleh
        // configError(). Sisanya masuk retry normal.
        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'error' => $response->successful() ? null : Str::limit($response->body(), 500),
            'permanent' => false,
        ];
    }

    /**
     * @return array{success: bool, status: int, error: string|null, permanent: bool}
     */
    private function sendTelegram(WebhookOutbox $row, InstallationWebhookPresenter $presenter): array
    {
        $config = config('webhooks.telegram_external');
        $activationNumber = WebhookOutbox::activationNumberFromKey($row->idempotency_key) ?? 1;
        $text = $presenter->toTelegramText($row->payload, $activationNumber);

        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->post("https://api.telegram.org/bot{$config['bot_token']}/sendMessage", [
                'chat_id' => $config['chat_id'],
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

        $ok = $response->successful() && $response->json('ok') === true;

        // 400/401/403/404 dari Bot API = salah token, chat_id tidak ada, atau
        // bot ditendang dari channel — semuanya cuma sembuh kalau manusia
        // membetulkan konfigurasi/keanggotaan bot, tidak pernah lewat retry.
        // 429 (rate limit) dan 5xx SENGAJA tidak masuk daftar ini: itu justru
        // kegagalan sesaat yang backoff-nya memang berguna.
        $permanent = ! $ok && in_array($response->status(), [400, 401, 403, 404], true);

        return [
            'success' => $ok,
            'status' => $response->status(),
            'error' => $ok ? null : Str::limit($response->body(), 500),
            'permanent' => $permanent,
        ];
    }
}
