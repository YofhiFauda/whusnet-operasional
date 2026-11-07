<?php

namespace Tests\Feature\Warehouse;

use App\Enums\CustodyStatus;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Artisan command wrapper `inventory:expire-overdue-custody` (dijadwalkan `routes/console.php` dailyAt('00:07')). */
class ExpireOverdueTechnicianCustodyCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_menandai_custody_kemarin_expired(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $technician = User::factory()->create();
        $category = ItemCategory::create([
            'code' => 'cmd_expire_cat', 'name' => 'Kategori Command Expire', 'default_unit' => 'pcs',
            'equipment_class' => 'pasif', 'is_system' => false, 'is_active' => true,
        ]);
        $item = Item::create([
            'code' => 'CMD-EXPIRE-QTY', 'name' => 'Barang Command Expire', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'quantity',
        ]);
        $custody = TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $cabang->id,
            'item_id' => $item->id,
            'qty_remaining' => 3,
            'status' => CustodyStatus::ISSUED,
            'issued_at' => now()->subDay(),
        ]);

        $this->artisan('inventory:expire-overdue-custody')->assertSuccessful();

        $this->assertNotNull($custody->refresh()->expired_at);
    }

    #[Test]
    public function command_tidak_error_kalau_tidak_ada_yang_perlu_di_expire(): void
    {
        $this->artisan('inventory:expire-overdue-custody')->assertSuccessful();
    }
}
