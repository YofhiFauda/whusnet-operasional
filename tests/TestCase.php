<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Helper to authenticate as an active admin/owner user in tests.
     */
    protected function loginAsAdmin(?\App\Models\User $user = null): \App\Models\User
    {
        if (!$user) {
            $ownerRole = \App\Models\Role::where('name', 'Owner')->first();
            if (!$ownerRole) {
                $this->seed(\Database\Seeders\RoleSeeder::class);
                $ownerRole = \App\Models\Role::where('name', 'Owner')->first();
            }

            $user = \App\Models\User::factory()->create([
                'status' => 'active',
                'role_id' => $ownerRole ? $ownerRole->id : null,
            ]);
        }

        $this->actingAs($user);
        return $user;
    }
}
