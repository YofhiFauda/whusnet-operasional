<?php

namespace App\Listeners;

use App\Events\InstallationActivated;
use App\Jobs\SendWebhookOutboxJob;
use App\Models\WebhookOutbox;
use App\Services\Webhooks\InstallationWebhookPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Satu-satunya listener API 1 (webhook pemasangan). Sengaja BUKAN
 * ShouldQueue — ia jalan sinkron di dalam transaksi
 * CustomerInstallationController::storePemasangan(), cuma tulis baris
 * `webhook_outbox` di sini. HTTP baru terjadi di SendWebhookOutboxJob,
 * dikirim afterCommit(). Tidak ada try/catch: exception naik ke controller
 * yang sudah punya DB::rollBack(), otomatis memenuhi "rollback transaksi →
 * nol baris outbox".
 */
class SendInstallationActivatedWebhooks
{
    public function __construct(private InstallationWebhookPresenter $presenter) {}

    public function handle(InstallationActivated $event): void
    {
        $customer = $event->customer;
        $eventName = 'installation.activated';

        $eventId = (string) Str::uuid();
        $activationNumber = WebhookOutbox::nextActivationNumber($customer->id, $eventName);
        $idempotencyKey = "installation:{$customer->id}:activation:{$activationNumber}";

        $data = $this->presenter->for($customer, $eventId, $idempotencyKey);

        // Website B (http_json) — selalu dikirim, tidak ada aturan skip.
        $websiteBRow = WebhookOutbox::create([
            'destination' => 'website_b',
            'event' => $eventName,
            'event_id' => $eventId,
            'idempotency_key' => $idempotencyKey,
            'customer_id' => $customer->id,
            'payload' => $data,
            'status' => 'pending',
        ]);
        $this->dispatchAfterCommitSafely($websiteBRow->id);

        // Telegram Eksternal — lewati kalau data intinya identik dengan
        // kiriman terakhir yang berhasil untuk pelanggan ini. Dibandingkan
        // dari DATA MENTAH (bukan teks ter-render), karena teks selalu ikut
        // berubah gara-gara nomor aktivasi disebut di dalamnya.
        $lastDeliveredTelegram = WebhookOutbox::where('customer_id', $customer->id)
            ->where('destination', 'telegram_external')
            ->where('event', $eventName)
            ->where('status', 'delivered')
            ->latest('delivered_at')
            ->first();

        $unchanged = $lastDeliveredTelegram
            && $lastDeliveredTelegram->payload['data'] === $data['data'];

        $telegramRow = WebhookOutbox::create([
            'destination' => 'telegram_external',
            'event' => $eventName,
            'event_id' => $eventId,
            'idempotency_key' => $idempotencyKey,
            'customer_id' => $customer->id,
            'payload' => $data,
            'status' => $unchanged ? 'skipped' : 'pending',
            'last_error' => $unchanged ? 'unchanged: teks identik dgn kiriman terakhir yang delivered' : null,
        ]);

        if (! $unchanged) {
            $this->dispatchAfterCommitSafely($telegramRow->id);
        }
    }

    /**
     * Dispatch job kirim SETELAH transaksi commit — TAPI dibungkus try/catch
     * di titik ini, bukan cuma diserahkan ke `->afterCommit()` Laravel.
     *
     * Alasannya bukan gaya: di queue `sync` (dipaksa phpunit.xml untuk semua
     * test repo ini, dan bisa saja jadi konfigurasi environment tertentu),
     * `->afterCommit()` mengeksekusi job SECARA INLINE saat `DB::commit()`
     * dipanggil — kalau job melempar (mis. Website B mati, config kosong),
     * exception itu naik lewat DB::commit() balik ke
     * storePemasangan()'s try/catch, men-trigger DB::rollBack() PADAHAL
     * commit sudah sukses, dan teknisi melihat pemasangannya "gagal
     * tersimpan" walau datanya sudah aman. Kegagalan kirim webhook TIDAK
     * BOLEH pernah menggagalkan alur yang memicunya — itu justru inti
     * kenapa pola outbox ini ada. Di queue redis/database (produksi),
     * dispatch() di dalam closure ini cuma insert baris/entri, nyaris tidak
     * pernah melempar — try/catch di sini jaring pengaman, bukan jalur
     * normal.
     */
    private function dispatchAfterCommitSafely(int $outboxId): void
    {
        DB::afterCommit(function () use ($outboxId) {
            try {
                SendWebhookOutboxJob::dispatch($outboxId);
            } catch (Throwable $e) {
                Log::error('Gagal dispatch SendWebhookOutboxJob', [
                    'outbox_id' => $outboxId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
