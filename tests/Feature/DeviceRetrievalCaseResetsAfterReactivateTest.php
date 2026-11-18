<?php

namespace Tests\Feature;

use App\Enums\WorkflowTransition;
use App\Services\CustomerWorkflowService;
use App\Services\DeviceRetrievalReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Q5: Langganan Lagi menghentikan kasus — keluar antrian, status dibebaskan
 * dicabut. Putus lagi → hitungan percobaan mulai dari 1 (batas kasus =
 * `terminated_at` terakhir).
 */
class DeviceRetrievalCaseResetsAfterReactivateTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function langganan_lagi_mengeluarkan_dari_antrian_dan_mencabut_dibebaskan(): void
    {
        $pending = $this->makeAttempts($this->customer, 5);
        $this->customer->customerDevice->update(['retrieval_waived_at' => now(), 'retrieval_waived_by' => $this->fop->id]);

        $this->actingAs($this->owner)
            ->post(route('customers.reactivate', $this->customer))
            ->assertSessionHasNoErrors();

        $this->assertNotSame('terminated', $this->customer->refresh()->status);
        $this->assertNull($this->customer->customerDevice->refresh()->retrieval_waived_at);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index'))
            ->assertOk()
            ->assertDontSee($this->customer->full_name);

        // Laporan lama tidak bisa diputuskan lagi setelah kasus berhenti.
        $this->actingAs($this->fop)
            ->post(route('device-retrieval-reviews.approve', $pending))
            ->assertSessionHasErrors('review_note');
    }

    #[Test]
    public function putus_lagi_menghitung_percobaan_dari_satu(): void
    {
        $service = app(DeviceRetrievalReviewService::class);
        $this->makeAttempts($this->customer, 4);

        $this->actingAs($this->owner)->post(route('customers.reactivate', $this->customer))->assertSessionHasNoErrors();
        $this->travel(1)->minutes();
        app(CustomerWorkflowService::class)->transition($this->customer->refresh(), WorkflowTransition::TERMINATED, 'Putus lagi');
        $this->travel(1)->minutes();

        $this->assertSame('terminated', $this->customer->refresh()->status);
        $newAttempt = $this->makeAttempt($this->customer, createdAt: now());

        $this->assertSame(1, $service->attemptNumber($newAttempt));
        $this->assertCount(1, $service->caseAttempts($this->customer));
    }
}
