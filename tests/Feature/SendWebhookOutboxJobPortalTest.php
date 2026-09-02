<?php

namespace Tests\Feature;

use App\Jobs\SendWebhookOutboxJob;
use App\Models\WebhookOutbox;
use App\Services\Webhooks\InstallationWebhookPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Perluasan `SendWebhookOutboxJob` untuk destination `customer_portal`
 * (docs/api/api-portal-pelanggan/, Fase 3, §6.6.6). Skema signature
 * PORTAL beda dari `website_b` — 3 header TERPISAH, bukan digabung.
 */
class SendWebhookOutboxJobPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webhooks.customer_portal.url' => 'https://portal.test/webhooks/whusnet',
            'webhooks.customer_portal.secret' => 'portal-unit-test-secret',
        ]);
    }

    private function runJob(WebhookOutbox $row): void
    {
        (new SendWebhookOutboxJob($row->id))->handle(app(InstallationWebhookPresenter::class));
    }

    private function makeOutboxRow(array $overrides = []): WebhookOutbox
    {
        return WebhookOutbox::create(array_merge([
            'destination' => 'customer_portal',
            'event' => 'invoice.updated',
            'event_id' => (string) Str::uuid(),
            'idempotency_key' => 'invoice:1:updated:1',
            'payload' => [
                'event_id' => (string) Str::uuid(),
                'event' => 'invoice.updated',
                'occurred_at' => now()->toIso8601String(),
                'customer' => ['login_id' => 'PNG-RQ000631'],
                'invoice' => [
                    'invoice_number' => 'INV-2026-08-000123',
                    'invoice_status' => 'lunas',
                    'total_amount' => '150000.00',
                    'paid_amount' => '150000.00',
                    'remaining_amount' => '0.00',
                ],
            ],
            'status' => 'pending',
        ], $overrides));
    }

    public function test_job_kirim_ke_customer_portal_dengan_tiga_header_terpisah(): void
    {
        Http::preventStrayRequests();
        Http::fake(['portal.test/*' => Http::response(['received' => true], 200)]);

        $row = $this->makeOutboxRow();
        $this->runJob($row);

        $row->refresh();
        $this->assertSame('delivered', $row->status);

        Http::assertSent(function ($request) use ($row) {
            $eventIdHeader = $request->header('X-Whusnet-Event-Id')[0] ?? null;
            $timestampHeader = $request->header('X-Whusnet-Timestamp')[0] ?? null;
            $signatureHeader = $request->header('X-Whusnet-Signature')[0] ?? null;

            // Header TERPISAH, bukan format gabungan "t=...,v1=..." (website_b).
            return $eventIdHeader === $row->event_id
                && $timestampHeader !== null
                && is_numeric($timestampHeader)
                && $signatureHeader !== null
                && ! str_contains((string) $signatureHeader, 't=')
                && ! str_contains((string) $signatureHeader, 'v1=');
        });
    }

    public function test_signature_hmac_sha256_atas_timestamp_titik_body(): void
    {
        Http::preventStrayRequests();
        Http::fake(['portal.test/*' => Http::response(['received' => true], 200)]);

        $row = $this->makeOutboxRow();
        $this->runJob($row);

        Http::assertSent(function ($request) {
            $timestamp = $request->header('X-Whusnet-Timestamp')[0] ?? '';
            $signature = $request->header('X-Whusnet-Signature')[0] ?? '';
            $expected = hash_hmac('sha256', "{$timestamp}.{$request->body()}", 'portal-unit-test-secret');

            return hash_equals($expected, $signature);
        });
    }

    public function test_config_kosong_menghasilkan_failed_tanpa_retry(): void
    {
        config(['webhooks.customer_portal.secret' => '']);
        Http::preventStrayRequests();
        Http::fake();

        $row = $this->makeOutboxRow();
        $this->runJob($row);

        $row->refresh();
        $this->assertSame('failed', $row->status);
        $this->assertSame(0, $row->attempts);
        Http::assertNothingSent();
    }

    public function test_url_non_https_menghasilkan_failed_tanpa_retry(): void
    {
        config(['webhooks.customer_portal.url' => 'http://portal.test/webhooks/whusnet']);
        Http::preventStrayRequests();
        Http::fake();

        $row = $this->makeOutboxRow();
        $this->runJob($row);

        $row->refresh();
        $this->assertSame('failed', $row->status);
        Http::assertNothingSent();
    }

    public function test_gagal_kirim_masuk_retry_backoff_normal(): void
    {
        Http::preventStrayRequests();
        Http::fake(['portal.test/*' => Http::response(['error' => 'down'], 500)]);

        $row = $this->makeOutboxRow();

        $this->expectException(RuntimeException::class);

        try {
            $this->runJob($row);
        } finally {
            $row->refresh();
            $this->assertSame('pending', $row->status);
            $this->assertSame(1, $row->attempts);
        }
    }

    public function test_delivered_menyimpan_response_status(): void
    {
        Http::preventStrayRequests();
        Http::fake(['portal.test/*' => Http::response(['received' => true], 200)]);

        $row = $this->makeOutboxRow();
        $this->runJob($row);

        $row->refresh();
        $this->assertSame(200, $row->response_status);
        $this->assertNotNull($row->delivered_at);
    }
}
