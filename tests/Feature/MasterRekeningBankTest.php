<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\BankAccountFeatureSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Rekening Bank (ADHOC-95): CRUD + toggle, tanpa hapus, dan gerbang
 * permission `master_rekening.*` lewat matrix role (bukan hardcode role).
 */
class MasterRekeningBankTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rantai seeding asli — membuktikan BankAccountFeatureSeeder ikut
        // terpanggil dari DatabaseSeeder, bukan cuma bisa jalan sendirian.
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeder_feature_idempotent(): void
    {
        $this->seed(BankAccountFeatureSeeder::class);

        $this->assertSame(3, Permission::where('code', 'like', 'master_rekening.%')->count());
    }

    public function test_permission_master_rekening_digenerate_tanpa_delete(): void
    {
        $codes = Permission::where('code', 'like', 'master_rekening.%')->pluck('code')->sort()->values()->all();

        $this->assertSame(['master_rekening.create', 'master_rekening.update', 'master_rekening.view'], $codes);
    }

    public function test_owner_bisa_tambah_ubah_dan_nonaktifkan_rekening(): void
    {
        $owner = $this->loginAsAdmin();

        $this->actingAs($owner)->get(route('master.rekening.index'))->assertOk();
        $this->actingAs($owner)->get(route('master.rekening.create'))->assertOk();

        $this->actingAs($owner)->post(route('master.rekening.store'), [
            'bank_name' => 'BCA',
            'account_number' => '123 456-7890',
            'account_holder_name' => 'PT Whusnet',
            'label' => 'BCA Utama',
            'is_active' => '1',
        ])->assertRedirect(route('master.rekening.index'));

        $account = BankAccount::firstOrFail();
        // Spasi/strip dirapikan supaya duplikat tak lolos lewat format beda.
        $this->assertSame('1234567890', $account->account_number);
        $this->assertTrue($account->is_active);

        $this->actingAs($owner)->get(route('master.rekening.edit', $account))->assertOk();

        $this->actingAs($owner)->put(route('master.rekening.update', $account), [
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'CV Whusnet',
            'is_active' => '1',
        ])->assertRedirect(route('master.rekening.index'));

        $this->assertSame('CV Whusnet', $account->fresh()->account_holder_name);

        $this->actingAs($owner)->post(route('master.rekening.toggle', $account))->assertRedirect();
        $this->assertFalse($account->fresh()->is_active);
    }

    public function test_rekening_kembar_di_bank_yang_sama_ditolak(): void
    {
        $owner = $this->loginAsAdmin();
        BankAccount::factory()->create(['bank_name' => 'BRI', 'account_number' => '5550001']);

        $this->actingAs($owner)->post(route('master.rekening.store'), [
            'bank_name' => 'BRI',
            'account_number' => '555-0001',
            'account_holder_name' => 'PT Whusnet',
            'is_active' => '1',
        ])->assertSessionHasErrors('account_number');

        $this->assertSame(1, BankAccount::count());
    }

    public function test_nomor_rekening_non_angka_ditolak(): void
    {
        $owner = $this->loginAsAdmin();

        $this->actingAs($owner)->post(route('master.rekening.store'), [
            'bank_name' => 'BNI',
            'account_number' => 'ABC123',
            'account_holder_name' => 'PT Whusnet',
            'is_active' => '1',
        ])->assertSessionHasErrors('account_number');
    }

    public function test_menu_master_rekening_muncul_di_sidebar_master_data(): void
    {
        $owner = $this->loginAsAdmin();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Master Rekening Bank')
            ->assertSee(route('master.rekening.index'), false);
    }

    public function test_menu_master_rekening_tersembunyi_tanpa_permission(): void
    {
        $teknisi = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($teknisi)->get(route('dashboard'))
            ->assertDontSee('Master Rekening Bank');
    }

    public function test_tidak_ada_route_hapus_rekening(): void
    {
        $this->assertFalse(app('router')->has('master.rekening.destroy'));
    }

    public function test_role_tanpa_permission_ditolak(): void
    {
        $teknisi = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->value('id'),
            'status' => 'active',
        ]);
        $account = BankAccount::factory()->create();

        $this->actingAs($teknisi)->get(route('master.rekening.index'))->assertForbidden();
        $this->actingAs($teknisi)->post(route('master.rekening.store'), [
            'bank_name' => 'BCA',
            'account_number' => '1',
            'account_holder_name' => 'X',
            'is_active' => '1',
        ])->assertForbidden();
        $this->actingAs($teknisi)->post(route('master.rekening.toggle', $account))->assertForbidden();

        $this->assertTrue($account->fresh()->is_active);
        $this->assertSame(1, BankAccount::count());
    }

    public function test_role_hanya_view_tidak_bisa_mengubah(): void
    {
        $role = Role::where('code', 'helpdesk')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([
            Permission::where('code', 'master_rekening.view')->firstOrFail()->id,
        ]);
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        app(EffectiveAccessService::class)->clearCache($user);
        $account = BankAccount::factory()->create();

        $this->actingAs($user)->get(route('master.rekening.index'))->assertOk()->assertSee($account->account_number);
        $this->actingAs($user)->get(route('master.rekening.edit', $account))->assertForbidden();
        $this->actingAs($user)->post(route('master.rekening.toggle', $account))->assertForbidden();
    }
}
