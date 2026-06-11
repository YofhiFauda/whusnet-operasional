<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopCRUDTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Test guests are redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/master/pop');
        $response->assertRedirect('/login');

        $response = $this->get('/master/pop/create');
        $response->assertRedirect('/login');
    }

    /**
     * Test role without view_pop permission gets 403.
     */
    public function test_user_without_permission_gets_403(): void
    {
        // Create a custom role with no permissions
        $restrictedRole = Role::create([
            'name' => 'Restricted Role',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create([
            'role_id' => $restrictedRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->get('/master/pop');
        $response->assertStatus(403);

        $response = $this->get('/master/pop/create');
        $response->assertStatus(403);
    }

    /**
     * Test role with view_pop but without manage_pop gets 403 on write operations.
     */
    public function test_user_with_view_only_gets_403_on_manage_actions(): void
    {
        // Customer Service has view_pop but not manage_pop
        $csRole = Role::where('name', 'Customer Service')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        // Can view list
        $response = $this->get('/master/pop');
        $response->assertStatus(200);

        // Cannot view create form
        $response = $this->get('/master/pop/create');
        $response->assertStatus(403);

        // Create a POP for testing
        $pop = Pop::create([
            'code' => 'POP-TEST-01',
            'name' => 'POP Test 1',
            'type' => 'cabang',
        ]);

        // Cannot view edit form
        $response = $this->get("/master/pop/{$pop->id}/edit");
        $response->assertStatus(403);

        // Cannot update
        $response = $this->put("/master/pop/{$pop->id}", [
            'code' => 'POP-TEST-01',
            'name' => 'POP Test 1 Updated',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $response->assertStatus(403);

        // Cannot toggle status
        $response = $this->post("/master/pop/{$pop->id}/toggle");
        $response->assertStatus(403);

        // But can view detail
        $response = $this->get("/master/pop/{$pop->id}");
        $response->assertStatus(200);
    }

    /**
     * Test full CRUD workflow for authorized user (Owner).
     */
    public function test_authorized_user_can_perform_full_crud(): void
    {
        $this->loginAsAdmin(); // Authenticates as Owner

        // 1. View index
        $response = $this->get('/master/pop');
        $response->assertStatus(200);
        $response->assertSee('Manajemen Wilayah Operasional POP');

        // 2. View create page
        $response = $this->get('/master/pop/create');
        $response->assertStatus(200);

        // 3. Store POP Pusat
        $popPusatData = [
            'code' => 'PST-JKT',
            'pop_code' => 'JKT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Pusat Jakarta',
            'type' => 'pusat',
            'address' => 'Jl. Kebon Sirih No. 1',
            'city' => 'Jakarta Pusat',
            'pic_name' => 'Budi',
            'pic_phone' => '08111222333',
        ];

        $response = $this->post('/master/pop', $popPusatData);
        $response->assertRedirect('/master/pop');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('pops', [
            'code' => 'PST-JKT',
            'name' => 'POP Pusat Jakarta',
            'type' => 'pusat',
            'status' => 'active',
        ]);

        $pusat = Pop::where('code', 'PST-JKT')->firstOrFail();

        // 4. Store child POP Cabang
        $popCabangData = [
            'code' => 'CBG-SLM',
            'pop_code' => 'SLM',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Cabang Sleman',
            'type' => 'cabang',
            'parent_id' => $pusat->id,
            'address' => 'Jl. Kaliurang KM 5',
            'city' => 'Sleman',
            'pic_name' => 'Andi',
            'pic_phone' => '08222333444',
        ];

        $response = $this->post('/master/pop', $popCabangData);
        $response->assertRedirect('/master/pop');
        $this->assertDatabaseHas('pops', [
            'code' => 'CBG-SLM',
            'parent_id' => $pusat->id,
        ]);

        $cabang = Pop::where('code', 'CBG-SLM')->firstOrFail();

        // 5. View detail
        $response = $this->get("/master/pop/{$pusat->id}");
        $response->assertStatus(200);
        $response->assertSee('POP Pusat Jakarta');
        $response->assertSee('POP Cabang Sleman'); // Should display children list

        // 6. View edit page
        $response = $this->get("/master/pop/{$cabang->id}/edit");
        $response->assertStatus(200);
        $response->assertSee('POP Cabang Sleman');

        // 7. Update POP
        $updateData = [
            'code' => 'CBG-SLM-REV',
            'pop_code' => 'SLM',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Cabang Sleman Updated',
            'type' => 'cabang',
            'parent_id' => $pusat->id,
            'address' => 'Jl. Kaliurang KM 6',
            'city' => 'Sleman',
            'pic_name' => 'Andi Santoso',
            'pic_phone' => '08222333444',
            'status' => 'active',
        ];

        $response = $this->put("/master/pop/{$cabang->id}", $updateData);
        $response->assertRedirect('/master/pop');
        
        $this->assertDatabaseHas('pops', [
            'id' => $cabang->id,
            'code' => 'CBG-SLM-REV',
            'name' => 'POP Cabang Sleman Updated',
            'pic_name' => 'Andi Santoso',
        ]);

        // 8. Toggle status to inactive
        $response = $this->post("/master/pop/{$cabang->id}/toggle");
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('pops', [
            'id' => $cabang->id,
            'status' => 'inactive',
        ]);

        // 9. Toggle status back to active
        $response = $this->post("/master/pop/{$cabang->id}/toggle");
        $this->assertDatabaseHas('pops', [
            'id' => $cabang->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test mandatory field validation.
     */
    public function test_mandatory_fields_validation(): void
    {
        $this->loginAsAdmin();

        $response = $this->post('/master/pop', [
            'code' => '',
            'pop_code' => '',
            'registration_prefix' => '',
            'cid_prefix' => '',
            'name' => '',
            'type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors(['code', 'pop_code', 'registration_prefix', 'cid_prefix', 'name', 'type']);
    }

    /**
     * Test code uniqueness validation.
     */
    public function test_code_uniqueness_validation(): void
    {
        $this->loginAsAdmin();

        Pop::create([
            'code' => 'POP-UNIQUE',
            'name' => 'POP Existing',
            'type' => 'pusat',
        ]);

        $response = $this->post('/master/pop', [
            'code' => 'POP-UNIQUE',
            'pop_code' => 'UNQ2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP New',
            'type' => 'cabang',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    /**
     * Test prevention of circular parent-child reference.
     */
    public function test_prevention_of_circular_reference(): void
    {
        $this->loginAsAdmin();

        // Create level 1: Pusat
        $pusat = Pop::create([
            'code' => 'LV1',
            'name' => 'Level 1 Pusat',
            'type' => 'pusat',
        ]);

        // Create level 2: Cabang under Pusat
        $cabang = Pop::create([
            'code' => 'LV2',
            'name' => 'Level 2 Cabang',
            'type' => 'cabang',
            'parent_id' => $pusat->id,
        ]);

        // Create level 3: Mini POP under Cabang
        $mini = Pop::create([
            'code' => 'LV3',
            'name' => 'Level 3 Mini',
            'type' => 'mini_pop',
            'parent_id' => $cabang->id,
        ]);

        // Skenario A: Mengedit Pusat (LV1) dan memilih dirinya sendiri (LV1) sebagai parent
        $response = $this->put("/master/pop/{$pusat->id}", [
            'code' => 'LV1',
            'pop_code' => 'LV1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Level 1 Pusat',
            'type' => 'pusat',
            'parent_id' => $pusat->id, // self
            'status' => 'active',
        ]);
        $response->assertSessionHasErrors(['parent_id']);

        // Skenario B: Mengedit Pusat (LV1) dan memilih keturunannya (LV2) sebagai parent
        $response = $this->put("/master/pop/{$pusat->id}", [
            'code' => 'LV1',
            'pop_code' => 'LV1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Level 1 Pusat',
            'type' => 'pusat',
            'parent_id' => $cabang->id, // descendant level 1
            'status' => 'active',
        ]);
        $response->assertSessionHasErrors(['parent_id']);

        // Skenario C: Mengedit Pusat (LV1) dan memilih keturunannya yang lebih dalam (LV3) sebagai parent
        $response = $this->put("/master/pop/{$pusat->id}", [
            'code' => 'LV1',
            'pop_code' => 'LV1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Level 1 Pusat',
            'type' => 'pusat',
            'parent_id' => $mini->id, // descendant level 2
            'status' => 'active',
        ]);
        $response->assertSessionHasErrors(['parent_id']);

        // Pastikan parent_id Pusat tidak berubah menjadi circular reference
        $pusat->refresh();
        $this->assertNull($pusat->parent_id);
    }
}
