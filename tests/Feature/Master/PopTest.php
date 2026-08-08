<?php

namespace Tests\Feature\Master;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class PopTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_it_normalizes_pop_fields_before_validation()
    {
        $this->loginAsAdmin();

        $response = $this->post(route('master.pop.store'), [
            'code' => ' pop-01 ',
            'pop_code' => ' p01 ',
            'registration_prefix' => ' c ',
            'cid_prefix' => ' d ',
            'name' => ' POP Name ',
            'type' => 'cabang',
        ]);

        $response->assertRedirect(route('master.pop.index'));

        $this->assertDatabaseHas('pops', [
            'code' => 'POP-01',
            'pop_code' => 'P01',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Name',
        ]);
    }
}
