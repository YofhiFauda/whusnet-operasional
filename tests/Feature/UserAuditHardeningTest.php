<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAuditHardeningTest extends TestCase
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

    public function test_create_update_and_pop_assignment_are_logged(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = $this->loginAsAdmin();

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $popA = Pop::create([
            'code' => 'POP-AUD-01',
            'pop_code' => 'AD1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Audit 01',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $popB = Pop::create([
            'code' => 'POP-AUD-02',
            'pop_code' => 'AD2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Audit 02',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $createResponse = $this->post(route('users.store'), [
            'name' => 'User Audit',
            'email' => 'user.audit@example.com',
            'phone' => '081200000010',
            'status' => 'active',
            'role_id' => $teknisiRole->id,
            'pop_ids' => [$popA->id],
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $createResponse->assertRedirect(route('users.index'));

        $user = User::where('email', 'user.audit@example.com')->firstOrFail();

        $createLog = AuditLog::query()
            ->where('module', 'User Management')
            ->where('action', 'create')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($owner->id, $createLog->user_id);
        $this->assertSame('User Audit', $createLog->new_values['name']);
        $this->assertSame([$popA->id], $createLog->new_values['pop_ids']);

        $updateResponse = $this->put(route('users.update', $user), [
            'name' => 'User Audit Updated',
            'email' => 'user.audit.updated@example.com',
            'phone' => '081200000011',
            'status' => 'inactive',
            'role_id' => $adminRole->id,
            'pop_ids' => [$popB->id],
            'password' => 'password456',
            'password_confirmation' => 'password456',
        ]);

        $updateResponse->assertRedirect(route('users.index'));

        $user->refresh();

        $updateLog = AuditLog::query()
            ->where('module', 'User Management')
            ->where('action', 'update')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('User Audit', $updateLog->old_values['name']);
        $this->assertSame('User Audit Updated', $updateLog->new_values['name']);
        $this->assertSame([$popA->id], $updateLog->old_values['pop_ids']);
        $this->assertSame([$popB->id], $updateLog->new_values['pop_ids']);

        $popResponse = $this->put(route('users.pops.update', $user), [
            'pop_ids' => [$popA->id, $popB->id],
        ]);

        $popResponse->assertRedirect(route('users.index'));

        $assignLog = AuditLog::query()
            ->where('module', 'User Management')
            ->where('action', 'assign_pop')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame([$popB->id], $assignLog->old_values['pop_ids']);
        $this->assertSame([$popA->id, $popB->id], $assignLog->new_values['pop_ids']);
    }

    public function test_user_form_validation_messages_are_clear(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->post(route('users.store'), [
            'email' => 'invalid-email',
            'status' => 'pending',
            'role_id' => 999999,
            'password' => 'short',
            'password_confirmation' => 'different',
            'pop_ids' => 'not-array',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'status', 'role_id', 'password', 'pop_ids']);

        $errors = session('errors')->getBag('default');

        $this->assertSame('Nama user wajib diisi.', $errors->first('name'));
        $this->assertSame('Format email user tidak valid.', $errors->first('email'));
        $this->assertSame('Status user tidak valid. Pilih aktif atau nonaktif.', $errors->first('status'));
        $this->assertSame('Role user yang dipilih tidak valid.', $errors->first('role_id'));
        $this->assertSame('Password user minimal 8 karakter.', $errors->first('password'));
        $this->assertSame('Format POP yang dipilih tidak valid.', $errors->first('pop_ids'));
    }
}
