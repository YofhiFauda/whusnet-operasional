<?php

namespace Tests\Feature;

use App\Enums\CustodyStatus;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryReassignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReturnCustodyLintasCabangDitolakTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $trackingType): array
    {
        $asal = Pop::factory()->create(['type' => 'cabang']);
        $tujuan = Pop::factory()->create(['type' => 'cabang']);
        $user = User::factory()->create();
        $category = ItemCategory::create(['code' => 'AUDIT-'.$trackingType, 'name' => 'Audit '.$trackingType, 'default_unit' => 'pcs']);
        $item = Item::create([
            'code' => 'AUDIT-'.$trackingType,
            'name' => 'Audit '.$trackingType,
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'tracking_type' => $trackingType,
        ]);

        return [$asal, $tujuan, $user, $item];
    }

    #[Test]
    public function serial_tidak_boleh_kembali_ke_cabang_lain(): void
    {
        [$asal, $tujuan, $user, $item] = $this->fixture('serialized');
        $serial = InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => 'AUDIT-RETURN-SN',
            'status' => SerialStatus::ISSUED,
            'issued_from_pop_id' => $asal->id,
            'current_technician_id' => $user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(InventoryReassignService::class)->returnSerialToWarehouse($serial, $tujuan, 'Audit', $user);
    }

    #[Test]
    public function quantity_tidak_boleh_kembali_ke_cabang_lain(): void
    {
        [$asal, $tujuan, $user, $item] = $this->fixture('quantity');
        $custody = TechnicianCustody::create([
            'item_id' => $item->id,
            'technician_id' => $user->id,
            'issued_from_pop_id' => $asal->id,
            'qty_remaining' => 5,
            'issued_at' => now(),
            'status' => CustodyStatus::ISSUED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(InventoryReassignService::class)->returnToWarehouse($custody, $tujuan, 'Audit', $user);
    }

    #[Test]
    public function roll_tidak_boleh_kembali_ke_cabang_lain(): void
    {
        [$asal, $tujuan, $user, $item] = $this->fixture('roll');
        $roll = InventoryRoll::create([
            'item_id' => $item->id,
            'roll_code' => 'AUDIT-RETURN-ROLL',
            'length_total' => 10,
            'length_remaining' => 5,
            'status' => RollStatus::ISSUED,
            'issued_from_pop_id' => $asal->id,
            'current_technician_id' => $user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(InventoryReassignService::class)->returnRollToWarehouse($roll, $tujuan, 'Audit', $user);
    }
}
