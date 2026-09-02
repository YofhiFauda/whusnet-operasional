<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ADHOC-20 langkah 3 — target aksi yang MENGUBAH DATA harus dirender server-side.
 *
 * Latar: bug assign kolektor (2026-08-08). Panel Worksheet Admin menyusun target
 * POST-nya lewat Alpine (`:action`). Waktu Alpine tidak termuat, atribut itu tak
 * pernah dievaluasi, `form.action` jatuh ke URL halaman sendiri, dan assign gagal
 * TANPA pesan apa pun — dialog konfirmasi tetap muncul (skrip lokal), user klik
 * "Ya", lalu tidak terjadi apa-apa. Alpine sekarang sudah dibundel lokal, tapi
 * polanya tetap salah: URL yang dirakit di klien menduplikasi definisi route, dan
 * kalau perakitannya meleset, POST-nya nyasar diam-diam.
 *
 * Dua jenis pemeriksaan di sini:
 *  1. HTTP — URL memang benar-benar ikut ter-render di markup.
 *  2. Pindai sumber Blade — pola literal yang sudah dicabut tidak boleh balik lagi.
 */
class PostTargetRenderedServerSideTest extends TestCase
{
    use RefreshDatabase;

    private function seedPop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    public function test_customer_list_renders_network_assignment_urls_from_routes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->seedPop();

        $customer = Customer::factory()->create([
            'status' => 'active',
            'pop_id' => $pop->id,
        ]);

        $response = $this->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee(route('customers.network-assignment.update', $customer->id), false);
        $response->assertSee(route('customers.network-assignment.data', $customer->id), false);
    }

    public function test_payment_info_endpoint_exposes_server_rendered_payment_target(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->seedPop();

        $customer = Customer::factory()->create([
            'status' => 'active',
            'pop_id' => $pop->id,
        ]);

        $response = $this->getJson(route('customers.payment-info', $customer->id));

        $response->assertOk();
        // Kuncinya WAJIB ada walau nilainya null (pelanggan tanpa tagihan) — modal
        // Quick Hub memakainya sebagai satu-satunya sumber action form pembayaran,
        // dan sengaja membiarkan form mati kalau nilainya kosong.
        $response->assertJsonStructure(['payment_store_url']);
    }

    public function test_ticket_create_form_has_server_rendered_action(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get(route('tickets.create'));

        $response->assertOk();
        // Tanpa action eksplisit, kegagalan Alpine bikin browser POST ke
        // /tickets/new (GET-only) dan tiketnya hilang tanpa jejak.
        $response->assertSee('action="'.route('tickets.store').'" method="POST"', false);
    }

    /**
     * Pola yang sudah dicabut — jangan dihidupkan lagi. Kalau butuh URL baru di
     * klien, kirim dari server (atribut `data-*` di tombol, atau field di respons
     * JSON), jangan tulis ulang path route-nya di JavaScript.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function forbiddenClientBuiltUrlProvider(): array
    {
        return [
            // Daftar pelanggan dipecah jadi partial (2026-08-13): tabel, modal, dan
            // skripnya tidak lagi inline di index.blade.php. Yang dipindai partialnya,
            // karena di situlah URL-nya pernah dirakit.
            'customers list — payment store' => ['resources/views/customers/partials/_list_scripts.blade.php', '/invoices/${'],
            'customers list — network assignment' => ['resources/views/customers/partials/_list_scripts.blade.php', '/customers/${'],
            'customers list — tabel baris' => ['resources/views/customers/partials/_list_table.blade.php', '/customers/${'],
            'customers list — modal quick hub' => ['resources/views/customers/partials/_quick_hub_modal.blade.php', '/invoices/${'],
            'quick payment modal — payment store' => ['resources/views/payments/partials/quick-payment-modal.blade.php', '${qpInvoiceId}/payments'],
            'verification queue — reject' => ['resources/views/verifications/queue.blade.php', '/reject`'],
            'notification dropdown — mark read' => ['resources/views/components/notification-dropdown.blade.php', '/notifications/${'],
            'notification list — toggle read' => ['resources/views/notifications/index.blade.php', '/notifications/${'],
        ];
    }

    #[DataProvider('forbiddenClientBuiltUrlProvider')]
    public function test_view_does_not_rebuild_mutating_urls_in_javascript(string $relativePath, string $forbidden): void
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertIsString($contents, "Gagal membaca {$relativePath}");
        $this->assertStringNotContainsString(
            $forbidden,
            $contents,
            "{$relativePath} merakit URL aksi di klien ({$forbidden}). Kirim URL-nya dari server: route() di atribut data-* atau field di respons JSON. Lihat ADHOC-20 langkah 3 di docs/TASKS.md."
        );
    }
}
