<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Pop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'whusnet-test-views';
        if (! is_dir($compiledPath)) {
            @mkdir($compiledPath, 0777, true);
        }

        config()->set('view.compiled', $compiledPath);
    }

    public function test_create_user_page_loads(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get(route('users.create'));

        $response->assertOk();
        $response->assertSee('Tambah User');
        $response->assertSee('Konfirmasi & Simpan', false);
    }

    public function test_admin_can_create_basic_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $role = Role::where('name', 'Teknisi')->firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-CRUD-01',
            'pop_code' => 'CR1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP CRUD 01',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'User Baru',
            'email' => 'user.baru@example.com',
            'phone' => '081234567890',
            'status' => 'active',
            'role_id' => $role->id,
            'scope_type' => 'selected_pop',
            'pop_ids' => [$pop->id],
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post(route('users.store'), $payload);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'user.baru@example.com')->firstOrFail();

        $this->assertSame('User Baru', $user->name);
        $this->assertSame('081234567890', $user->phone);
        $this->assertSame('active', $user->status);
        $this->assertSame($role->id, $user->role_id);
        $this->assertTrue($user->pops->contains($pop));
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_admin_can_update_basic_user_fields(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $popA = Pop::create([
            'code' => 'POP-CRUD-02',
            'pop_code' => 'CR2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP CRUD 02',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $popB = Pop::create([
            'code' => 'POP-CRUD-03',
            'pop_code' => 'CR3',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP CRUD 03',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'name' => 'User Lama',
            'email' => 'user.lama@example.com',
            'phone' => '0800000000',
            'status' => 'inactive',
            'role_id' => $teknisiRole->id,
            'password' => bcrypt('old-password123'),
        ]);
        $user->pops()->sync([$popA->id]);

        $response = $this->put(route('users.update', $user), [
            'name' => 'User Baru Diedit',
            'email' => 'user.baru.diedit@example.com',
            'phone' => '081111111111',
            'status' => 'active',
            'role_id' => $adminRole->id,
            'scope_type' => 'selected_pop',
            'pop_ids' => [$popB->id],
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $user->refresh();

        $this->assertSame('User Baru Diedit', $user->name);
        $this->assertSame('user.baru.diedit@example.com', $user->email);
        $this->assertSame('081111111111', $user->phone);
        $this->assertSame('active', $user->status);
        $this->assertSame($adminRole->id, $user->role_id);
        $this->assertTrue($user->pops->contains($popB));
        $this->assertFalse($user->pops->contains($popA));
        $this->assertTrue(Hash::check('new-password123', $user->password));
    }
}
