<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerTerminationReason;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Master Alasan Putus Langganan (ADHOC-69 §3.4/§4.2) — hard-delete diblok
 * kalau masih dipakai minimal 1 pelanggan, permission terpisah dari
 * `customers.deactivate`.
 */
class CustomerTerminationReasonMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function alasan_yang_masih_dipakai_tidak_bisa_dihapus(): void
    {
        $this->loginAsAdmin();

        $reason = CustomerTerminationReason::create(['name' => 'Kompetitor']);
        $pop = Pop::create([
            'code' => 'POP-MST',
            'pop_code' => 'MST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Master Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        Customer::create([
            'customer_code' => 'C-MST-000001',
            'full_name' => 'Pelanggan Terpakai',
            'primary_phone' => '081200000001',
            'registration_date' => now(),
            'pop_id' => $pop->id,
            'status' => 'terminated',
            'termination_reason_id' => $reason->id,
        ]);

        $response = $this->delete(route('master.termination-reasons.destroy', $reason));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('customer_termination_reasons', ['id' => $reason->id]);
    }

    #[Test]
    public function alasan_yang_tidak_dipakai_bisa_dihapus(): void
    {
        $this->loginAsAdmin();

        $reason = CustomerTerminationReason::create(['name' => 'Alasan Kosong']);

        $response = $this->delete(route('master.termination-reasons.destroy', $reason));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('customer_termination_reasons', ['id' => $reason->id]);
    }

    #[Test]
    public function crud_dasar_dan_toggle_status(): void
    {
        $this->loginAsAdmin();

        $this->post(route('master.termination-reasons.store'), [
            'name' => 'Pindah Rumah',
            'default_penalty_amount' => 50000,
            'is_active' => 1,
        ])->assertSessionHas('success');

        $reason = CustomerTerminationReason::where('name', 'Pindah Rumah')->firstOrFail();
        $this->assertEquals(50000, (float) $reason->default_penalty_amount);
        $this->assertTrue($reason->is_active);

        $this->put(route('master.termination-reasons.update', $reason), [
            'name' => 'Pindah Rumah Jauh',
            'default_penalty_amount' => 75000,
            'is_active' => 1,
        ])->assertSessionHas('success');

        $reason->refresh();
        $this->assertSame('Pindah Rumah Jauh', $reason->name);

        $this->post(route('master.termination-reasons.toggle', $reason))->assertSessionHas('success');
        $this->assertFalse($reason->fresh()->is_active);
    }

    #[Test]
    public function unique_name_ditolak(): void
    {
        $this->loginAsAdmin();
        CustomerTerminationReason::create(['name' => 'Duplikat']);

        $this->post(route('master.termination-reasons.store'), [
            'name' => 'Duplikat',
            'is_active' => 1,
        ])->assertSessionHasErrors('name');
    }

    #[Test]
    public function role_dengan_customers_deactivate_saja_bisa_lihat_dropdown_tapi_tidak_bisa_akses_master(): void
    {
        // pop_admin: punya `customers.deactivate` + `termination_reasons.view`,
        // TAPI bukan `.create`/`.update`/`.delete` (§4.2 rancangan — dua
        // permission terpisah, jangan digabung satu gate).
        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('master.termination-reasons.index'))->assertOk();

        $this->actingAs($user)->get(route('master.termination-reasons.create'))->assertForbidden();

        $reason = CustomerTerminationReason::create(['name' => 'Test']);
        $this->actingAs($user)->delete(route('master.termination-reasons.destroy', $reason))->assertForbidden();
    }
}
