<?php

namespace Tests\Feature;

use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\Role;
use App\Models\User;
use App\Services\ModemSaleReconciliationService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ModemSaleReconciliationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Seeder demo Rekonsiliasi Modem Terjual harus benar-benar mengisi laporan
 * dengan keempat status (bukan cuma bikin baris yang lalu tersaring habis),
 * dan SN-nya melewati alur gudang asli sampai INSTALLED milik pelanggan.
 */
class ModemSaleReconciliationDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);
    }

    #[Test]
    public function seeder_mengisi_laporan_dengan_semua_status(): void
    {
        $this->seed(ModemSaleReconciliationDemoSeeder::class);

        $response = $this->actingAs($this->owner)->get(route('reports.modem-sale-reconciliation.index'));
        $response->assertOk();

        $rows = collect($response->viewData('rows')->items());
        $summary = $response->viewData('summary');
        $this->assertSame(9, $summary['total']);
        $this->assertSame(3, $summary['anomaly']);

        $perStatus = $rows->countBy('status')->all();
        $this->assertSame(2, $perStatus[ModemSaleReconciliationService::STATUS_WAITING_BD]);
        $this->assertSame(2, $perStatus[ModemSaleReconciliationService::STATUS_WAITING_CS_VERIFICATION]);
        $this->assertSame(2, $perStatus[ModemSaleReconciliationService::STATUS_WAITING_CREQ_APPROVAL]);
        $this->assertSame(3, $perStatus[ModemSaleReconciliationService::STATUS_ANOMALY]);

        $response->assertSee('REKDEMO-ONT-0001');
        $response->assertSee('Cabang Jetis (Demo Rekonsiliasi)');
    }

    #[Test]
    public function sn_demo_terpasang_ke_pelanggan_lewat_alur_gudang(): void
    {
        $this->seed(ModemSaleReconciliationDemoSeeder::class);

        $serials = InventorySerial::where('serial_number', 'like', 'REKDEMO-ONT-%')->get();

        $this->assertCount(9, $serials);
        foreach ($serials as $serial) {
            $this->assertSame('installed', $serial->status->value);
            $this->assertNotNull($serial->customer_id);
        }
    }

    #[Test]
    public function seeder_aman_dijalankan_dua_kali(): void
    {
        $this->seed(ModemSaleReconciliationDemoSeeder::class);
        $this->seed(ModemSaleReconciliationDemoSeeder::class);

        $this->assertSame(9, InventorySerialSale::count());
    }
}
