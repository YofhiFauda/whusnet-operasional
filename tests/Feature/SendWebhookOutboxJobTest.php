<?php

namespace Tests\Feature;

use App\Jobs\SendWebhookOutboxJob;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\WebhookOutbox;
use App\Services\Webhooks\InstallationWebhookPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * SendWebhookOutboxJob — pengiriman, HMAC, retry, guard urutan (superseded),
 * dan purge. Job dipanggil langsung (`->handle()`), bukan lewat queue
 * worker: repo ini QUEUE_CONNECTION=sync di test, jadi memanggil handle()
 * langsung persis merepresentasikan satu "percobaan" job — cara paling
 * eksplisit untuk menguji `attempts`/backoff tanpa bergantung ke jadwal
 * queue worker sungguhan.
 */
class SendWebhookOutboxJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webhooks.website_b.url' => 'https://website-b.test/webhooks/installation',
            'webhooks.website_b.secret' => 'unit-test-secret',
            'webhooks.telegram_external.bot_token' => 'UNIT-TEST-BOT',
            'webhooks.telegram_external.chat_id' => '-100111',
        ]);
    }

    public function test_website_b_success_marks_delivered_with_valid_hmac_signature(): void
    {
        Http::preventStrayRequests();
        Http::fake(['website-b.test/*' => Http::response(['received' => true], 200)]);

        $row = $this->makeOutboxRow('website_b');

        $this->runJob($row);

        $row->refresh();
        $this->assertSame('delivered', $row->status);
        $this->assertSame(200, $row->response_status);
        $this->assertNotNull($row->delivered_at);
        $this->assertSame(1, $row->attempts);

        Http::assertSent(function ($request) {
            $header = $request->header('X-Whusnet-Signature')[0] ?? '';
            if (! preg_match('/^t=(\d+),v1=([0-9a-f]+)$/', $header, $m)) {
                return false;
            }

            $expected = hash_hmac('sha256', "{$m[1]}.{$request->body()}", 'unit-test-secret');

            return hash_equals($expected, $m[2]);
        });
    }

    public function test_telegram_success_reads_ok_field_and_uses_external_credentials(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $row = $this->makeOutboxRow('telegram_external');

        $this->runJob($row);

        $row->refresh();
        $this->assertSame('delivered', $row->status);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'botUNIT-TEST-BOT/sendMessage'));
    }

    /**
     * Teks Telegram dibaca manusia — "2026-08-20T14:29:54+07:00" bukan format
     * yang bisa dibaca sekilas. `occurred_at` di payload TETAP ISO-8601:
     * konsumen mesin (Website B) memakainya untuk menentukan urutan event.
     */
    public function test_telegram_text_renders_indonesian_datetime_while_payload_stays_iso(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $payload = $this->samplePayload();
        $payload['occurred_at'] = '2026-08-20T14:29:54+07:00';

        $row = $this->makeOutboxRow('telegram_external', ['payload' => $payload]);

        $this->runJob($row);

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'Waktu: 20 Agustus 2026, 14.29 WIB')
                && ! str_contains($request['text'], '2026-08-20T14:29:54');
        });

        $row->refresh();
        $this->assertSame('2026-08-20T14:29:54+07:00', $row->payload['occurred_at']);
    }

    public function test_failure_records_error_then_throws_for_retry(): void
    {
        Http::preventStrayRequests();
        Http::fake(['website-b.test/*' => Http::response(['error' => 'server down'], 500)]);

        $row = $this->makeOutboxRow('website_b');

        $this->expectException(RuntimeException::class);

        try {
            $this->runJob($row);
        } finally {
            $row->refresh();
            $this->assertSame('pending', $row->status);
            $this->assertSame(1, $row->attempts);
            $this->assertSame(500, $row->response_status);
            $this->assertNotNull($row->last_error);
        }
    }

    public function test_non_https_website_b_url_marks_failed_without_retry(): void
    {
        config(['webhooks.website_b.url' => 'http://website-b.test/webhooks/installation']);
        Http::preventStrayRequests();
        Http::fake(); // apa pun yang lolos ke sini bikin test gagal

        $row = $this->makeOutboxRow('website_b');

        $this->runJob($row);

        $row->refresh();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('https://', $row->last_error);
        $this->assertSame(0, $row->attempts, 'Salah konfigurasi bukan kegagalan jaringan — jangan dihitung sebagai percobaan.');

        Http::assertNothingSent();
    }

    public function test_missing_website_b_secret_marks_failed_without_retry(): void
    {
        config(['webhooks.website_b.secret' => '']);
        Http::preventStrayRequests();
        Http::fake();

        $row = $this->makeOutboxRow('website_b');

        $this->runJob($row);

        $row->refresh();
        $this->assertSame('failed', $row->status);
        Http::assertNothingSent();
    }

    public function test_max_attempts_exhausted_marks_row_failed(): void
    {
        Http::preventStrayRequests();
        Http::fake(['website-b.test/*' => Http::response(['error' => 'down'], 500)]);

        $row = $this->makeOutboxRow('website_b');
        $job = new SendWebhookOutboxJob($row->id);

        for ($i = 1; $i <= 8; $i++) {
            try {
                $job->handle(app(InstallationWebhookPresenter::class));
            } catch (RuntimeException) {
                // diharapkan — job melempar tiap kali gagal supaya retry Laravel jalan
            }
        }

        $row->refresh();
        $this->assertSame(8, $row->attempts);
        $this->assertSame('pending', $row->status, 'Job sendiri tidak menandai failed — itu tugas failed() setelah tries habis.');

        $job->failed(new RuntimeException('percobaan habis'));

        $row->refresh();
        $this->assertSame('failed', $row->status);
    }

    public function test_superseded_row_skipped_without_http_call(): void
    {
        Http::preventStrayRequests();
        Http::fake(); // apa pun yang lolos ke sini bikin test gagal (stray request)

        $pop = Pop::create([
            'code' => 'SS', 'pop_code' => 'SS',
            'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Superseded', 'type' => 'cabang', 'status' => 'active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'TEST-SS-001',
            'full_name' => 'Superseded Customer',
            'primary_phone' => '0812340002',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        WebhookOutbox::create([
            'destination' => 'website_b',
            'event' => 'installation.activated',
            'event_id' => Str::uuid(),
            'idempotency_key' => "installation:{$customer->id}:activation:2",
            'customer_id' => $customer->id,
            'payload' => $this->samplePayload(),
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $staleRow = WebhookOutbox::create([
            'destination' => 'website_b',
            'event' => 'installation.activated',
            'event_id' => Str::uuid(),
            'idempotency_key' => "installation:{$customer->id}:activation:1",
            'customer_id' => $customer->id,
            'payload' => $this->samplePayload(),
            'status' => 'pending',
        ]);

        $this->runJob($staleRow);

        $staleRow->refresh();
        $this->assertSame('skipped', $staleRow->status);
        $this->assertStringContainsString('superseded', $staleRow->last_error);
        $this->assertSame(0, $staleRow->attempts, 'Belum pernah dicoba kirim — jangan dihitung sebagai percobaan.');

        Http::assertNothingSent();
    }

    /**
     * Regresi 2026-08-20: guard urutan tidak memfilter `destination`, jadi
     * Website B yang delivered #7 membuat baris Telegram #4/#5/#6 di-`skipped`
     * padahal Telegram belum pernah menerima apa pun. Satu penekanan Aktivasi
     * menulis dua baris ber-`idempotency_key` sama — urutan cuma bermakna per
     * tujuan.
     */
    public function test_delivered_website_b_row_does_not_supersede_telegram_row(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $customer = $this->makeCustomer('XD');

        WebhookOutbox::create([
            'destination' => 'website_b',
            'event' => 'installation.activated',
            'event_id' => Str::uuid(),
            'idempotency_key' => "installation:{$customer->id}:activation:7",
            'customer_id' => $customer->id,
            'payload' => $this->samplePayload(),
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $telegramRow = WebhookOutbox::create([
            'destination' => 'telegram_external',
            'event' => 'installation.activated',
            'event_id' => Str::uuid(),
            'idempotency_key' => "installation:{$customer->id}:activation:4",
            'customer_id' => $customer->id,
            'payload' => $this->samplePayload(),
            'status' => 'pending',
        ]);

        $this->runJob($telegramRow);

        $telegramRow->refresh();
        $this->assertSame('delivered', $telegramRow->status, 'Keberhasilan Website B tidak boleh menyensor antrean Telegram.');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org'));
    }

    public function test_telegram_row_still_superseded_by_delivered_telegram_row(): void
    {
        Http::preventStrayRequests();
        Http::fake(); // apa pun yang lolos ke sini bikin test gagal

        $customer = $this->makeCustomer('XT');

        WebhookOutbox::create([
            'destination' => 'telegram_external',
            'event' => 'installation.activated',
            'event_id' => Str::uuid(),
            'idempotency_key' => "installation:{$customer->id}:activation:3",
            'customer_id' => $customer->id,
            'payload' => $this->samplePayload(),
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $staleRow = WebhookOutbox::create([
            'destination' => 'telegram_external',
            'event' => 'installation.activated',
            'event_id' => Str::uuid(),
            'idempotency_key' => "installation:{$customer->id}:activation:2",
            'customer_id' => $customer->id,
            'payload' => $this->samplePayload(),
            'status' => 'pending',
        ]);

        $this->runJob($staleRow);

        $staleRow->refresh();
        $this->assertSame('skipped', $staleRow->status);
        $this->assertStringContainsString('superseded', $staleRow->last_error);
        Http::assertNothingSent();
    }

    public function test_missing_telegram_credentials_marks_failed_without_retry(): void
    {
        config(['webhooks.telegram_external.bot_token' => '']);
        Http::preventStrayRequests();
        Http::fake();

        $row = $this->makeOutboxRow('telegram_external');

        $this->runJob($row);

        $row->refresh();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('telegram_external', $row->last_error);
        $this->assertSame(0, $row->attempts, 'Salah konfigurasi bukan kegagalan jaringan — jangan dihitung sebagai percobaan.');

        Http::assertNothingSent();
    }

    /**
     * Regresi 2026-08-20: token Telegram salah dijawab 404 "Not Found" dan
     * baris ikut siklus retry 8x/6 jam padahal mustahil sembuh sendiri.
     */
    public function test_telegram_permanent_error_marks_failed_without_retry(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(
            ['ok' => false, 'error_code' => 404, 'description' => 'Not Found'], 404
        )]);

        $row = $this->makeOutboxRow('telegram_external');

        $this->runJob($row); // tidak melempar — kegagalan permanen tidak di-retry

        $row->refresh();
        $this->assertSame('failed', $row->status);
        $this->assertSame(404, $row->response_status);
        $this->assertStringContainsString('Not Found', $row->last_error);
        $this->assertSame(1, $row->attempts, 'Sudah benar-benar dicoba kirim — beda dari galat konfigurasi.');
    }

    public function test_telegram_server_error_still_retries(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 502)]);

        $row = $this->makeOutboxRow('telegram_external');

        $this->expectException(RuntimeException::class);

        try {
            $this->runJob($row);
        } finally {
            $row->refresh();
            $this->assertSame('pending', $row->status, '5xx itu kegagalan sesaat — backoff-nya memang berguna.');
            $this->assertSame(502, $row->response_status);
        }
    }

    public function test_retry_sends_same_stored_payload_not_rebuilt(): void
    {
        Http::preventStrayRequests();
        Http::fake(['website-b.test/*' => Http::sequence()
            ->push(['error' => 'down'], 500)
            ->push(['received' => true], 200),
        ]);

        $row = $this->makeOutboxRow('website_b');
        $originalPayload = $row->payload;

        try {
            $this->runJob($row);
        } catch (RuntimeException) {
        }

        $row->refresh();
        $this->assertSame($originalPayload, $row->payload, 'Percobaan gagal tidak boleh mengubah payload tersimpan.');

        $this->runJob($row);
        $row->refresh();
        $this->assertSame('delivered', $row->status);
        $this->assertSame($originalPayload, $row->payload, 'Percobaan sukses pun memakai payload yang sama, bukan dirakit ulang.');
    }

    public function test_purge_deletes_delivered_older_than_retention_keeps_failed(): void
    {
        $oldDelivered = $this->makeOutboxRow('website_b', ['status' => 'delivered']);
        $oldDelivered->forceFill(['delivered_at' => Carbon::now()->subDays(100)])->save();

        $oldFailed = $this->makeOutboxRow('website_b', ['status' => 'failed']);
        $oldFailed->forceFill(['created_at' => Carbon::now()->subDays(100)])->save();

        Artisan::call('webhook-outbox:prune');

        $this->assertDatabaseMissing('webhook_outbox', ['id' => $oldDelivered->id]);
        $this->assertDatabaseHas('webhook_outbox', ['id' => $oldFailed->id]);
    }

    private function makeCustomer(string $popCode): Customer
    {
        $pop = Pop::create([
            'code' => $popCode, 'pop_code' => $popCode,
            'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => "POP {$popCode}", 'type' => 'cabang', 'status' => 'active',
        ]);

        return Customer::create([
            'customer_code' => "TEST-{$popCode}-001",
            'full_name' => "Pelanggan {$popCode}",
            'primary_phone' => '0812340003',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function runJob(WebhookOutbox $row): void
    {
        (new SendWebhookOutboxJob($row->id))->handle(app(InstallationWebhookPresenter::class));
    }

    private function makeOutboxRow(string $destination, array $overrides = []): WebhookOutbox
    {
        return WebhookOutbox::create(array_merge([
            'destination' => $destination,
            'event' => 'installation.activated',
            'event_id' => Str::uuid(),
            'idempotency_key' => 'installation:1:activation:1',
            'payload' => $this->samplePayload(),
            'status' => 'pending',
        ], $overrides));
    }

    private function samplePayload(): array
    {
        return [
            'event' => 'installation.activated',
            'event_id' => (string) Str::uuid(),
            'idempotency_key' => 'installation:1:activation:1',
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'customer' => ['cid' => 'C1X4ARQ000631', 'nama' => 'Uji Job'],
                'pop' => ['code' => 'PNR-JTS', 'name' => 'Jetis', 'type' => 'cabang'],
                'desa' => ['id' => 1, 'name' => 'Joresan', 'kecamatan' => 'Mlarak', 'kota' => 'Kab. Ponorogo'],
                'paket' => ['code' => 'PKT-20M', 'name' => 'Home 20 Mbps', 'bandwidth' => '20 Mbps', 'harga_bulanan' => '150000.00'],
                'perangkat' => ['sn' => 'SN1', 'odp' => 'ODP1', 'odp_port' => '1', 'olt' => 'OLT1', 'vlan' => '10'],
                'task' => ['number' => 'TASK-2026-0001', 'started_at' => now()->toIso8601String()],
            ],
        ];
    }
}
