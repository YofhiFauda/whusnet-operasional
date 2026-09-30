<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Distribution;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Field Distribusi di Edit Pelanggan.
 *
 * Disesuaikan 2026-09-29 ke aturan terkunci pindah POP (ADHOC-104/109/107,
 * docs/plan/rancangan-pindah-pop-lanjutan.md): Distribusi wajib anak Mini POP,
 * dan Edit cuma menulis Mini POP/Distribusi kalau Cabang ikut dipindah —
 * penyesuaian jaringan tanpa pindah Cabang lewat modal "Atur Mini POP &
 * Distribusi". Versi lama test ini mengunci perilaku sebelum aturan itu
 * (Distribusi menempel ke Cabang, diubah dari Edit tanpa pindah Cabang, untuk
 * pelanggan pra-pemasangan) dan sudah gagal sejak commit bb15742.
 */
class CustomerDistributionEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class]);
    }

    /**
     * @return array{0: Pop, 1: Pop, 2: Distribution}
     */
    private function cabangDenganDistribusi(): array
    {
        $cabang = Pop::create([
            'code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active',
        ]);
        $miniPop = Pop::create([
            'code' => 'SMN1', 'pop_code' => 'D1', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'Mini Sooko', 'type' => 'mini_pop', 'status' => 'active', 'parent_id' => $cabang->id,
        ]);
        $distribution = Distribution::create(['pop_id' => $miniPop->id, 'code' => 'DIST-SOOKO', 'name' => 'Distribution Sooko']);

        return [$cabang, $miniPop, $distribution];
    }

    public function test_customer_edit_view_shows_distribution_field(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        [$cabang, , $distribution] = $this->cabangDenganDistribusi();

        $customer = Customer::create([
            'customer_code' => 'C-TST-000001',
            'full_name' => 'Budi Santoso',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $cabang->id,
            'status' => 'registered',
        ]);

        $response = $this->get("/customers/{$customer->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('KODE DISTRIBUSI');
        $response->assertSee('distribution_id');
        $response->assertSee($distribution->code);
    }

    public function test_edit_tanpa_pindah_cabang_tidak_mengubah_distribusi(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        [$cabang, $miniPop, $distribution] = $this->cabangDenganDistribusi();

        $customer = Customer::create([
            'customer_code' => 'C-SMN-000001',
            'full_name' => 'Original Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $cabang->id,
            'status' => 'active',
        ]);

        $response = $this->put("/customers/{$customer->id}", [
            'full_name' => 'Updated Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $cabang->id,
            'mini_pop_id' => $miniPop->id,
            'distribution_id' => $distribution->id,
            'status' => 'active',
        ]);

        $response->assertRedirect("/customers/{$customer->id}");
        $customer->refresh();
        $this->assertSame('Updated Name', $customer->full_name);
        // Diabaikan (rule `exclude`): atur lewat modal "Atur Mini POP & Distribusi".
        $this->assertNull($customer->distribution_id);
        $this->assertNull($customer->mini_pop_id);
    }
}
