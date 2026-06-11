<?php

namespace Tests\Feature;

use App\Models\SubscriptionStatus;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStatusMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_status_seed_has_expected_workflow_statuses(): void
    {
        $this->seed(SubscriptionStatusSeeder::class);

        $this->assertSame(9, SubscriptionStatus::query()->count());

        $expectedCodes = [
            'registered',
            'waiting_survey',
            'surveyed',
            'waiting_installation',
            'installed',
            'active',
            'suspended',
            'terminated',
            'rejected',
        ];

        $this->assertSame(
            $expectedCodes,
            SubscriptionStatus::query()->orderBy('workflow_order')->pluck('code')->all(),
        );

        $this->assertTrue(SubscriptionStatus::query()->where('code', 'terminated')->firstOrFail()->is_terminal);
        $this->assertTrue(SubscriptionStatus::query()->where('code', 'rejected')->firstOrFail()->is_terminal);
    }

    public function test_subscription_status_seed_is_idempotent(): void
    {
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);

        $this->assertSame(9, SubscriptionStatus::query()->count());
    }

    public function test_subscription_status_master_page_is_available(): void
    {
        $this->seed(SubscriptionStatusSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get('/master/status-langganan');

        $response
            ->assertOk()
            ->assertSee('Master Status Pelanggan')
            ->assertSee('Waiting Survey')
            ->assertSee('waiting_installation');
    }
}
