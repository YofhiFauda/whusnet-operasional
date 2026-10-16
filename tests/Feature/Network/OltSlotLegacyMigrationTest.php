<?php

namespace Tests\Feature\Network;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class OltSlotLegacyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_without_chassis_stays_intact_when_migration_stops(): void
    {
        $pop = Pop::factory()->create(['type' => 'mini_pop']);
        $this->createLegacySlot($pop);

        $this->assertMigrationStopsWithoutChangingLegacySlot();
    }

    public function test_slot_with_two_possible_chassis_stays_intact_when_migration_stops(): void
    {
        $pop = Pop::factory()->create(['type' => 'mini_pop']);
        $this->createChassis($pop);
        $this->createChassis($pop);
        $this->createLegacySlot($pop);

        $this->assertMigrationStopsWithoutChangingLegacySlot();
    }

    public function test_slot_with_one_chassis_is_moved_without_losing_its_number(): void
    {
        $pop = Pop::factory()->create(['type' => 'mini_pop']);
        $chassis = $this->createChassis($pop);
        $this->createLegacySlot($pop);

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('olt_slots', 'pop_id'));
        $this->assertTrue(Schema::hasColumn('olt_slots', 'chassis_node_id'));
        $this->assertSame(1, DB::table('olt_slots')->count());
        $this->assertDatabaseHas('olt_slots', ['chassis_node_id' => $chassis->id, 'number' => 3]);

        $this->migration()->down();

        $this->assertTrue(Schema::hasColumn('olt_slots', 'pop_id'));
        $this->assertFalse(Schema::hasColumn('olt_slots', 'chassis_node_id'));
        $this->assertDatabaseHas('olt_slots', ['pop_id' => $pop->id, 'number' => 3]);
    }

    public function test_rollback_stops_before_changing_schema_if_slot_numbers_would_collide(): void
    {
        $pop = Pop::factory()->create(['type' => 'mini_pop']);
        $firstChassis = $this->createChassis($pop);
        $secondChassis = $this->createChassis($pop);

        foreach ([$firstChassis, $secondChassis] as $chassis) {
            DB::table('olt_slots')->insert([
                'chassis_node_id' => $chassis->id,
                'number' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $stopped = false;

        try {
            $this->migration()->down();
        } catch (RuntimeException) {
            $stopped = true;
        }

        $this->assertTrue($stopped, 'Rollback must stop when legacy POP slot numbers would collide.');
        $this->assertTrue(Schema::hasColumn('olt_slots', 'chassis_node_id'));
        $this->assertFalse(Schema::hasColumn('olt_slots', 'pop_id'));
        $this->assertSame(2, DB::table('olt_slots')->count());
    }

    private function assertMigrationStopsWithoutChangingLegacySlot(): void
    {
        $stopped = false;

        try {
            $this->migration()->up();
        } catch (RuntimeException) {
            $stopped = true;
        }

        $this->assertTrue($stopped, 'Migration must stop when the slot owner cannot be determined.');
        $this->assertTrue(Schema::hasColumn('olt_slots', 'pop_id'));
        $this->assertFalse(Schema::hasColumn('olt_slots', 'chassis_node_id'));
        $this->assertSame(1, DB::table('olt_slots')->count());
        $this->assertSame(3, (int) DB::table('olt_slots')->value('number'));
    }

    private function createLegacySlot(Pop $pop): void
    {
        Schema::dropIfExists('olt_slots');
        Schema::create('olt_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->foreignId('card_node_id')->nullable()->unique()->constrained('network_nodes')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['pop_id', 'number']);
        });

        DB::table('olt_slots')->insert([
            'pop_id' => $pop->id,
            'number' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createChassis(Pop $pop): NetworkNode
    {
        $type = NetworkNodeType::firstOrCreate(
            ['code' => 'olt_chassis'],
            ['name' => 'Chassis OLT', 'has_ports' => false],
        );

        return NetworkNode::factory()->create([
            'node_type_id' => $type->id,
            'anchor_pop_id' => $pop->id,
            'parent_id' => null,
        ]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_14_120000_reconcile_olt_slots_chassis_node_id.php');
    }
}
