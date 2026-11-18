<?php

namespace Tests\Feature;

use App\Models\FopTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Q4/Q8: centang "Langsung jadwalkan" → ke form assign /fop-tasks yang sudah
 * ada (difilter ke TFOP baru); tanpa centang → kembali ke halaman review.
 */
class DeviceRetrievalReviewRejectRedirectsToFopAssignTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function centang_langsung_jadwalkan_mengarah_ke_task_fop_terfilter(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);

        $response = $this->actingAs($this->fop)->post(route('device-retrieval-reviews.reject', $attempt), [
            'review_note' => 'Ulangi besok pagi.',
            'schedule_now' => '1',
        ]);

        $fopTask = FopTask::where('customer_id', $this->customer->id)->sole();
        $response->assertRedirect(route('fop-tasks.index', ['search' => $fopTask->task_number]));
    }

    #[Test]
    public function tanpa_centang_kembali_ke_detail_kalau_dikirim_dari_detail(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->fop)->post(route('device-retrieval-reviews.reject', $attempt), [
            'review_note' => 'Ulangi besok pagi.',
            'return_to' => 'detail',
        ])->assertRedirect(route('device-retrieval-reviews.show', $this->customer));
    }
}
