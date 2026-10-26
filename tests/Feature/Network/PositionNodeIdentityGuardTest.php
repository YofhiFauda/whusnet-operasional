<?php

namespace Tests\Feature\Network;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi final review ADHOC-213 (I4, I5): identitas Slot/PON/Port (nomor +
 * kode turunan `{induk}-S{n}-P{m}`) hanya boleh lahir dari
 * InfrastructurePositionService dan tidak boleh diubah lewat form node umum.
 */
class PositionNodeIdentityGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $cabang;

    private NetworkNode $olt;

    private NetworkNode $slot;

    private NetworkNode $pon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->olt = NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $this->cabang->id, 'code' => 'OLT-LAMA', 'name' => 'OLT Lama', 'status' => 'active']);
        $positions = app(InfrastructurePositionService::class);
        $this->slot = $positions->createSlot($this->olt, 3);
        $this->pon = $positions->createPort($this->slot, 5);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function positionTypes(): array
    {
        return ['slot OLT' => ['olt_slot'], 'PON' => ['olt_pon'], 'slot Switch' => ['switch_slot'], 'port Switch' => ['switch_port']];
    }

    #[Test]
    #[DataProvider('positionTypes')]
    public function store_umum_menolak_tipe_posisi(string $typeCode): void
    {
        $parent = in_array($typeCode, ['olt_pon', 'switch_port'], true) ? $this->slot : $this->olt;

        $this->actingAs($this->owner)->from(route('network.nodes.index'))->post(route('network.nodes.store'), [
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->value('id'),
            'parent_id' => $parent->id,
            'code' => 'POSISI-PALSU',
            'name' => 'Posisi Palsu',
        ])->assertSessionHasErrors(['node_type_id' => 'Slot/PON/Port dibuat lewat Generate struktur atau form Tambah Slot/PON pada perangkat, bukan form node umum.']);

        $this->assertFalse(NetworkNode::where('code', 'POSISI-PALSU')->exists());
    }

    #[Test]
    public function update_pon_mengabaikan_kode_dan_tipe_kiriman(): void
    {
        $longCode = $this->pon->code;

        $this->actingAs($this->owner)->patch(route('network.nodes.update', $this->pon), [
            'code' => 'KODE-BEBAS',
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'name' => 'PON Ganti Nama',
        ])->assertRedirect(route('network.nodes.show', $this->pon))->assertSessionHasNoErrors();

        $fresh = $this->pon->fresh();
        $this->assertSame($longCode, $fresh->code);
        $this->assertSame('PON Ganti Nama', $fresh->name);
        $this->assertSame('olt_pon', $fresh->nodeType->code);
    }

    #[Test]
    public function update_pon_berkode_lebih_dari_50_karakter_tetap_bisa_disimpan(): void
    {
        // Kode turunan bisa sampai 120 karakter; aturan `max:50` milik kode
        // node biasa tidak boleh menghalangi simpan nama PON.
        $longOlt = NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $this->cabang->id, 'code' => str_repeat('O', 48), 'name' => 'OLT Panjang', 'status' => 'active']);
        $positions = app(InfrastructurePositionService::class);
        $pon = $positions->createPort($positions->createSlot($longOlt, 12), 15);
        $this->assertGreaterThan(50, strlen($pon->code));

        $this->actingAs($this->owner)->patch(route('network.nodes.update', $pon), [
            'code' => $pon->code,
            'name' => 'PON Panjang',
        ])->assertSessionHasNoErrors();

        $this->assertSame('PON Panjang', $pon->fresh()->name);
    }

    #[Test]
    public function detail_pon_menampilkan_kode_hanya_baca(): void
    {
        $this->actingAs($this->owner)->get(route('network.nodes.show', $this->pon))->assertOk()
            ->assertSee('data-derived-code', false)
            ->assertDontSee('name="code"', false);
    }

    #[Test]
    public function rename_kode_perangkat_menurunkan_ulang_kode_slot_dan_pon(): void
    {
        $this->assertSame('OLT-LAMA-S3', $this->slot->code);
        $this->assertSame('OLT-LAMA-S3-P5', $this->pon->code);

        $this->actingAs($this->owner)->patch(route('network.nodes.update', $this->olt), [
            'code' => 'olt-baru',
            'name' => 'OLT Baru',
        ])->assertSessionHasNoErrors();

        $this->assertSame('OLT-BARU', $this->olt->fresh()->code);
        $this->assertSame('OLT-BARU-S3', $this->slot->fresh()->code);
        $this->assertSame('OLT-BARU-S3-P5', $this->pon->fresh()->code);
    }

    #[Test]
    public function rename_yang_bentrok_dengan_kode_turunan_lain_dibatalkan_utuh(): void
    {
        // Kode turunan baru (`BENTROK-S3`) sudah dipakai node lain → seluruh
        // rename batal: kode perangkat maupun Slot/PON tidak berubah sebagian.
        NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $this->cabang->id, 'code' => 'BENTROK-S3', 'name' => 'Penghalang', 'status' => 'active']);

        $this->actingAs($this->owner)->from(route('network.nodes.show', $this->olt))->patch(route('network.nodes.update', $this->olt), [
            'code' => 'BENTROK',
            'name' => 'OLT Lama',
        ])->assertSessionHasErrors('code');

        $this->assertSame('OLT-LAMA', $this->olt->fresh()->code);
        $this->assertSame('OLT-LAMA-S3', $this->slot->fresh()->code);
        $this->assertSame('OLT-LAMA-S3-P5', $this->pon->fresh()->code);
    }
}
