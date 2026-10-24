<?php

namespace Tests\Feature\Network;

use App\Models\AuditLog;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Generate massal Slot + PON/Port Switch (ADHOC-213 §6): idempoten, satu
 * transaksi, satu baris audit ringkasan (bukan satu per node).
 */
class GenerateInfrastructureStructureTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $miniA;

    private Pop $miniB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->sole()->id]);
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniA = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $this->miniB = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
    }

    private function device(string $type, ?Pop $pop = null, string $code = ''): NetworkNode
    {
        return NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', $type)->sole()->id,
            'anchor_pop_id' => ($pop ?? $this->miniA)->id, 'parent_id' => null,
            'code' => $code !== '' ? $code : strtoupper($type).'-'.fake()->unique()->numerify('###'),
        ]);
    }

    private function typeCount(string $code): int
    {
        return NetworkNode::where('node_type_id', NetworkNodeType::where('code', $code)->sole()->id)->count();
    }

    private function service(): InfrastructurePositionService
    {
        return app(InfrastructurePositionService::class);
    }

    #[Test]
    public function olt_16_slot_8_pon_membuat_16_slot_dan_128_pon(): void
    {
        $olt = $this->device('olt_chassis');

        $result = $this->service()->generateStructure($olt, 16, 8, 128, $this->owner);

        $this->assertSame(['slots_created' => 16, 'ports_created' => 128], $result);
        $this->assertSame(16, $this->typeCount('olt_slot'));
        $this->assertSame(128, $this->typeCount('olt_pon'));
        $this->assertSame(16, $olt->fresh()->capacity);
        $slot = $olt->slots()->where('number', 3)->sole();
        $this->assertSame(8, $slot->capacity);
        $this->assertSame(128, $slot->positionPorts()->where('number', 5)->sole()->capacity);
    }

    #[Test]
    public function generate_ulang_hanya_menambah_nomor_yang_belum_ada(): void
    {
        $olt = $this->device('olt_chassis');
        $this->service()->generateStructure($olt, 4, 8, 100, $this->owner);
        $existingPon = $olt->slots()->where('number', 1)->sole()->positionPorts()->where('number', 1)->sole();
        $existingPon->forceFill(['capacity' => 77])->saveQuietly();

        $result = $this->service()->generateStructure($olt->fresh(), 16, 8, 999, $this->owner);

        $this->assertSame(['slots_created' => 12, 'ports_created' => 96], $result);
        $this->assertSame(16, $this->typeCount('olt_slot'));
        $this->assertSame(128, $this->typeCount('olt_pon'));
        $this->assertSame(77, $existingPon->fresh()->capacity);
        $this->assertSame(100, $olt->slots()->where('number', 4)->sole()->positionPorts()->where('number', 8)->sole()->capacity);
        $this->assertSame(999, $olt->slots()->where('number', 5)->sole()->positionPorts()->where('number', 1)->sole()->capacity);
    }

    #[Test]
    public function satu_entri_audit_per_aksi(): void
    {
        $olt = $this->device('olt_chassis');
        AuditLog::query()->delete();

        $this->service()->generateStructure($olt, 2, 4, null, $this->owner);

        $this->assertSame(1, AuditLog::count());
        $log = AuditLog::where('module', 'Struktur Infrastruktur')->sole();
        $this->assertSame(2, $log->new_values['slots_created']);
        $this->assertSame(8, $log->new_values['ports_created']);
        $this->assertSame($olt->code, $log->new_values['device_code']);
    }

    #[Test]
    public function switch_membuat_satu_slot_logis_dan_n_port(): void
    {
        $switch = $this->device('switch_mini_pop');

        $result = $this->service()->generateStructure($switch, 9, 24, null, $this->owner);

        $this->assertSame(['slots_created' => 1, 'ports_created' => 24], $result);
        $slot = $switch->slots()->sole();
        $this->assertTrue($slot->is_logical);
        $this->assertSame(24, $this->typeCount('switch_port'));
        $this->assertNull($slot->positionPorts()->first()->capacity);
    }

    #[Test]
    public function gagal_di_tengah_membatalkan_semua(): void
    {
        $olt = $this->device('olt_chassis', null, 'OLT-ROLLBACK');
        // Kode PON slot 2 sengaja bentrok (unique code) supaya gagal di tengah jalan.
        $this->device('odp', null, 'OLT-ROLLBACK-S2-P2');

        try {
            $this->service()->generateStructure($olt, 3, 4, null, $this->owner);
            $this->fail('Harus gagal.');
        } catch (InvalidArgumentException) {
            // diharapkan
        }

        $this->assertSame(0, $this->typeCount('olt_slot'));
        $this->assertSame(0, $this->typeCount('olt_pon'));
        $this->assertNull($olt->fresh()->capacity);
        $this->assertSame(0, AuditLog::where('module', 'Struktur Infrastruktur')->count());
    }

    #[Test]
    public function controller_membuat_struktur_lalu_redirect_ke_detail_node(): void
    {
        $olt = $this->device('olt_chassis');

        $this->actingAs($this->owner)->get(route('network.nodes.generate-structure.create', $olt))
            ->assertOk()->assertSee(route('network.nodes.generate-structure.store', $olt), false);
        $this->actingAs($this->owner)->post(route('network.nodes.generate-structure.store', $olt),
            ['slot_count' => 2, 'ports_per_slot' => 3])
            ->assertRedirect(route('network.nodes.show', $olt))->assertSessionHas('success');

        $this->assertSame(6, $this->typeCount('olt_pon'));
    }

    #[Test]
    public function tipe_selain_olt_dan_switch_ditolak_404(): void
    {
        $odp = $this->device('odp');

        $this->actingAs($this->owner)->get(route('network.nodes.generate-structure.create', $odp))->assertNotFound();
        $this->actingAs($this->owner)->post(route('network.nodes.generate-structure.store', $odp),
            ['slot_count' => 1, 'ports_per_slot' => 1])->assertNotFound();
    }

    #[Test]
    public function user_cabang_lain_ditolak_403(): void
    {
        $actor = User::factory()->create(['role_id' => Role::where('code', 'fop')->sole()->id]);
        $scope = UserRoleScope::create(['user_id' => $actor->id, 'role_id' => $actor->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->miniA->id]);
        app(EffectiveAccessService::class)->clearCache($actor);
        $outside = $this->device('olt_chassis', $this->miniB);
        $inside = $this->device('olt_chassis', $this->miniA);

        $this->actingAs($actor)->get(route('network.nodes.generate-structure.create', $outside))->assertForbidden();
        $this->actingAs($actor)->post(route('network.nodes.generate-structure.store', $outside),
            ['slot_count' => 1, 'ports_per_slot' => 1])->assertForbidden();
        $this->assertSame(0, $this->typeCount('olt_slot'));

        $this->actingAs($actor)->post(route('network.nodes.generate-structure.store', $inside),
            ['slot_count' => 1, 'ports_per_slot' => 1])->assertRedirect();
        $this->assertSame(1, $this->typeCount('olt_slot'));
    }

    #[Test]
    public function jumlah_di_atas_batas_ditolak(): void
    {
        $olt = $this->device('olt_chassis');
        $url = route('network.nodes.generate-structure.store', $olt);

        $this->actingAs($this->owner)->post($url, ['slot_count' => 65, 'ports_per_slot' => 8])
            ->assertSessionHasErrors('slot_count');
        $this->actingAs($this->owner)->post($url, ['slot_count' => 64, 'ports_per_slot' => 129])
            ->assertSessionHasErrors('ports_per_slot');
        $this->actingAs($this->owner)->post($url, ['slot_count' => 1, 'ports_per_slot' => 1, 'pon_capacity' => 0])
            ->assertSessionHasErrors('pon_capacity');
        $this->assertSame(0, $this->typeCount('olt_slot'));
    }
}
