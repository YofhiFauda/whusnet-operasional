<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Helper to authenticate as an active admin/owner user in tests.
     */
    protected function loginAsAdmin(?User $user = null): User
    {
        if (! $user) {
            $ownerRole = Role::where('name', 'Owner')->first();
            if (! $ownerRole) {
                $this->seed(RoleSeeder::class);
                $ownerRole = Role::where('name', 'Owner')->first();
            }

            $user = User::factory()->create([
                'status' => 'active',
                'role_id' => $ownerRole ? $ownerRole->id : null,
            ]);
        }

        $this->actingAs($user);

        return $user;
    }
}
