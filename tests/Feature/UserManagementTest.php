<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_user_management_index_page_loads(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $role = Role::firstOrFail();
        $user = User::factory()->create([
            'name' => 'User Operasional',
            'email' => 'user.operasional@example.com',
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $pop = Pop::create([
            'code' => 'POP-USER-01',
            'pop_code' => 'USR',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP User Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $user->pops()->sync([$pop->id]);

        $response = $this->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Manajemen User & POP');
        $response->assertSee('User Operasional', false);
        $response->assertSee('Atur Cabang', false);
        $response->assertSee($pop->name, false);
    }

    public function test_user_management_index_supports_search_and_filters(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();

        $popAlpha = Pop::create([
            'code' => 'POP-USER-03',
            'pop_code' => 'UA3',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP User Alpha',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $popBeta = Pop::create([
            'code' => 'POP-USER-04',
            'pop_code' => 'UB4',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP User Beta',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $targetUser = User::factory()->create([
            'name' => 'Filter User',
            'email' => 'filter.user@example.com',
            'phone' => '081200000001',
            'status' => 'active',
            'role_id' => $teknisiRole->id,
        ]);
        $targetUser->pops()->sync([$popAlpha->id]);

        $otherUser = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other.user@example.com',
            'phone' => '081200000002',
            'status' => 'inactive',
            'role_id' => $adminRole->id,
        ]);
        $otherUser->pops()->sync([$popBeta->id]);

        $response = $this->get(route('users.index', [
            'search' => 'Filter User',
            'role_id' => $teknisiRole->id,
            'status' => 'active',
            'pop_id' => $popAlpha->id,
        ]));

        $response->assertOk();
        $response->assertSee('Filter User', false);
        $response->assertSee('POP User Alpha', false);
        $response->assertDontSee('Other User', false);
    }

    public function test_user_pop_assignment_page_loads(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $user = User::factory()->create([
            'name' => 'Petugas POP',
            'email' => 'petugas.pop@example.com',
            'status' => 'active',
            'role_id' => Role::firstOrFail()->id,
        ]);

        $user->pops()->sync([
            Pop::create([
                'code' => 'POP-USER-02',
                'pop_code' => 'UP2',
                'registration_prefix' => 'C',
                'cid_prefix' => 'D',
                'name' => 'POP User Test 2',
                'type' => 'cabang',
                'status' => 'active',
            ])->id,
        ]);

        $response = $this->get(route('users.pops.edit', $user));

        $response->assertOk();
        $response->assertSee('Assign POP');
        $response->assertSee('Petugas POP', false);
    }
}
