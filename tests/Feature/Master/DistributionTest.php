<?php

namespace Tests\Feature\Master;

use App\Models\Distribution;
use App\Models\Pop;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class DistributionTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_it_disallows_same_code_globally()
    {
        $this->loginAsAdmin();

        $pop1 = Pop::factory()->create(['code' => 'P1', 'pop_code' => 'P1', 'name' => 'POP 1']);
        $pop2 = Pop::factory()->create(['code' => 'P2', 'pop_code' => 'P2', 'name' => 'POP 2']);

        // Create first distribution in POP 1
        Distribution::create([
            'pop_id' => $pop1->id,
            'code' => 'D1',
            'name' => 'Dist 1',
        ]);

        // Attempt to create same code 'D1' in POP 2
        $response = $this->post(route('master.distribusi.store'), [
            'pop_id' => $pop2->id,
            'code' => 'D1',
            'name' => 'Dist 2',
        ]);

        $response->assertStatus(302);
        $this->assertEquals(1, Distribution::where('code', 'D1')->count());
    }
}
