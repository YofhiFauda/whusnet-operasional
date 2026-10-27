<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalSource;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DeviceRetrievalLog;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackfillRetrievalLogWarehousePopCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function default_hanya_preview_dan_apply_mencatat_audit_tanpa_menebak_pop_yang_tidak_jelas(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $category = ItemCategory::create(['code' => 'AUDIT-BACKFILL', 'name' => 'Audit Backfill', 'default_unit' => 'pcs']);
        $item = Item::create([
            'code' => 'AUDIT-BACKFILL',
            'name' => 'Audit Backfill',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'tracking_type' => 'serialized',
        ]);
        $known = InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => 'AUDIT-KNOWN',
            'issued_from_pop_id' => $pop->id,
        ]);
        $unknown = InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => 'AUDIT-UNKNOWN',
        ]);

        foreach ([$known, $unknown] as $serial) {
            DeviceRetrievalLog::create([
                'customer_id' => $customer->id,
                'serial_id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'source' => DeviceRetrievalSource::CREQ_SWAP,
                'retrieved_at' => now(),
            ]);
        }

        $this->artisan('warehouse:backfill-retrieval-log-pop')->assertExitCode(0);
        $this->assertSame(0, AuditLog::where('action', 'backfill_warehouse_pop')->count());
        $this->assertSame(2, DeviceRetrievalLog::whereNull('warehouse_pop_id')->count());

        $this->artisan('warehouse:backfill-retrieval-log-pop', ['--apply' => true])->assertExitCode(0);
        $this->assertSame($pop->id, (int) DeviceRetrievalLog::where('serial_id', $known->id)->value('warehouse_pop_id'));
        $this->assertNull(DeviceRetrievalLog::where('serial_id', $unknown->id)->value('warehouse_pop_id'));
        $this->assertSame(1, AuditLog::where('action', 'backfill_warehouse_pop')->count());

        $this->artisan('warehouse:backfill-retrieval-log-pop', ['--apply' => true])->assertExitCode(0);
        $this->assertSame(1, AuditLog::where('action', 'backfill_warehouse_pop')->count());
    }
}
