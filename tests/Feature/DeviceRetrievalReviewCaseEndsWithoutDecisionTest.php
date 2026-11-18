<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalReviewStatus;
use App\Enums\ItemCondition;
use App\Models\FopTask;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Services\InventoryReassignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Temuan code review 2026-11-18 (#1–#3): laporan gagal yang masih MENUNGGU
 * saat kasusnya berhenti tanpa keputusan FOP.
 *
 *  - Modem diantar pelanggan ke gudang → dulu laporan tetap di antrian, dan
 *    Tolak membuat task DEAC untuk modem yang sudah di gudang.
 *  - Laporan dari masa putus langganan SEBELUMNYA → dulu bisa di-Approve
 *    dari tab lama sebagai "percobaan ke-0", membebaskan kasus baru.
 *  - Langganan Lagi → dulu laporan menunggu jadi yatim.
 */
class DeviceRetrievalReviewCaseEndsWithoutDecisionTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function modem_diantar_ke_gudang_menutup_laporan_yang_menunggu(): void
    {
        $attempt = $this->makeAttempts($this->customer, 2);
        $category = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $modem = Item::create(['code' => 'RV-MODEM', 'name' => 'Modem Review Test', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        app(InventoryReassignService::class)->receiveSerialFromCustomerAtWarehouse(
            'RV-SN-WALKIN', $modem, $this->customer, $this->popA, ItemCondition::USED_GOOD, null, $this->owner,
        );

        $attempt->refresh();
        $this->assertSame(DeviceRetrievalReviewStatus::DITUTUP, $attempt->review_status);
        $this->assertStringContainsString('diantar pelanggan', (string) $attempt->review_note);
        // Task tidak tertahan "pending" (keputusan #5-A).
        $this->assertSame('approved', $attempt->task->refresh()->fop_review_status);

        $this->actingAs($this->fop)
            ->from(route('device-retrieval-reviews.index'))
            ->post(route('device-retrieval-reviews.reject', $attempt), ['review_note' => 'Ulangi'])
            ->assertSessionHasErrors('review_note');
        $this->assertFalse(FopTask::where('customer_id', $this->customer->id)->exists());

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index'))
            ->assertOk()
            ->assertDontSee($this->customer->full_name);
    }

    #[Test]
    public function modem_sudah_kembali_tidak_bisa_ditolak_walau_laporan_masih_menunggu(): void
    {
        // Penjaga lapis kedua: device_retrieved_at terisi lewat jalur yang
        // tidak memanggil closeOpenCase().
        $attempt = $this->makeAttempts($this->customer, 1);
        $this->customer->customerDevice->update(['device_retrieved_at' => now()]);

        $this->actingAs($this->fop)
            ->from(route('device-retrieval-reviews.index'))
            ->post(route('device-retrieval-reviews.reject', $attempt), ['review_note' => 'Ulangi'])
            ->assertSessionHasErrors('review_note');
        $this->actingAs($this->fop)
            ->from(route('device-retrieval-reviews.index'))
            ->post(route('device-retrieval-reviews.approve', $attempt), ['review_note' => 'Bebaskan'])
            ->assertSessionHasErrors('review_note');

        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $attempt->refresh()->review_status);
        $this->assertNull($this->customer->customerDevice->refresh()->retrieval_waived_at);
        $this->assertFalse(FopTask::where('customer_id', $this->customer->id)->exists());
    }

    #[Test]
    public function laporan_dari_masa_putus_sebelumnya_tidak_bisa_membebaskan_kasus_baru(): void
    {
        // terminated_at pelanggan = 60 hari lalu; laporan ini dibuat sebelum itu.
        $oldCycle = $this->makeAttempt($this->customer, createdAt: now()->subDays(70));

        $this->actingAs($this->fop)
            ->from(route('device-retrieval-reviews.index'))
            ->post(route('device-retrieval-reviews.approve', $oldCycle), ['review_note' => 'Bebaskan'])
            ->assertSessionHasErrors('review_note');

        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $oldCycle->refresh()->review_status);
        $this->assertNull($this->customer->customerDevice->refresh()->retrieval_waived_at);
    }

    #[Test]
    public function langganan_lagi_menutup_laporan_menunggu_supaya_tidak_yatim(): void
    {
        $attempt = $this->makeAttempts($this->customer, 2);

        $this->actingAs($this->owner)
            ->post(route('customers.reactivate', $this->customer))
            ->assertSessionHasNoErrors();

        $attempt->refresh();
        $this->assertSame(DeviceRetrievalReviewStatus::DITUTUP, $attempt->review_status);
        $this->assertSame($this->owner->id, $attempt->reviewed_by);
        $this->assertSame('approved', $attempt->task->refresh()->fop_review_status);

        // Detail Task tidak lagi menampilkan panel "menunggu review".
        $this->actingAs($this->fop)->get(route('tasks.show', $attempt->task))
            ->assertOk()
            ->assertDontSee('Buka Review Penarikan');
    }
}
