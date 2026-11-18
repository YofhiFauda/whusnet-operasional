<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Q6/D4: reject laporan generik (TaskController::review) dulu mengembalikan
 * task DEAC yang SAMA ke teknisi — percobaannya tidak terhitung. Untuk DEAC
 * gagal yang sudah masuk Review Penarikan Alat, jalur ini ditutup.
 */
class DeviceRetrievalGenericRejectDisabledForFailedOutcomeTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function reject_generik_tidak_mengembalikan_task_ke_in_progress(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);
        $task = $attempt->task;

        $this->actingAs($this->owner)
            ->from(route('tasks.show', $task))
            ->post(route('tasks.review', $task), ['action' => 'reject', 'reason' => 'Ulangi'])
            ->assertRedirect(route('tasks.show', $task))
            ->assertSessionHas('error');

        $this->assertEquals(TaskStatus::SELESAI, $task->refresh()->status);
    }

    #[Test]
    public function detail_task_mengarahkan_ke_halaman_review(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->fop)->get(route('tasks.show', $attempt->task))
            ->assertOk()
            ->assertSee('Buka Review Penarikan')
            ->assertSee(route('device-retrieval-reviews.show', $this->customer), false);
    }
}
