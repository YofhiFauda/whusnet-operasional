<?php

namespace Tests\Feature;

use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_pop_with_valid_data(): void
    {
        $pop = Pop::create([
            'code' => 'POP-PST-001',
            'name' => 'POP Pusat Jakarta',
            'type' => 'pusat',
            'address' => 'Jl. Merdeka No. 1',
            'village' => 'Gambir',
            'district' => 'Gambir',
            'city' => 'Jakarta Pusat',
            'latitude' => -6.1753924,
            'longitude' => 106.8271528,
            'pic_name' => 'John Doe',
            'pic_phone' => '08123456789',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('pops', [
            'code' => 'POP-PST-001',
            'name' => 'POP Pusat Jakarta',
            'type' => 'pusat',
            'status' => 'active',
        ]);

        $this->assertEquals('POP Pusat Jakarta', $pop->name);
        $this->assertEquals(-6.1753924, (float) $pop->latitude);
        $this->assertEquals(106.8271528, (float) $pop->longitude);
    }

    public function test_pop_has_default_active_status(): void
    {
        $pop = Pop::create([
            'code' => 'POP-SMN-001',
            'name' => 'POP Sleman',
            'type' => 'cabang',
        ]);

        $pop->refresh();
        $this->assertEquals('active', $pop->status);
        $this->assertNull($pop->address);
    }

    public function test_pop_parent_child_relationships(): void
    {
        // 1. Create central POP
        $pusat = Pop::create([
            'code' => 'PST',
            'name' => 'POP Pusat',
            'type' => 'pusat',
        ]);

        // 2. Create branch POP under central
        $cabang = Pop::create([
            'code' => 'CBG-YOG',
            'name' => 'POP Cabang Yogyakarta',
            'type' => 'cabang',
            'parent_id' => $pusat->id,
        ]);

        // 3. Create mini POP under branch
        $mini = Pop::create([
            'code' => 'MINI-SLM',
            'name' => 'Mini POP Sleman',
            'type' => 'mini_pop',
            'parent_id' => $cabang->id,
        ]);

        // Verify relationships
        $this->assertCount(1, $pusat->children);
        $this->assertEquals($cabang->id, $pusat->children->first()->id);

        $this->assertEquals($pusat->id, $cabang->parent->id);
        $this->assertCount(1, $cabang->children);
        $this->assertEquals($mini->id, $cabang->children->first()->id);

        $this->assertEquals($cabang->id, $mini->parent->id);
        $this->assertNull($pusat->parent);
        $this->assertCount(0, $mini->children);
    }
}
