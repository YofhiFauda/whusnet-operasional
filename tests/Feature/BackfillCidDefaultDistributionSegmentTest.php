<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Distribution;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Betulkan CID lama yang segmen distribusinya "XX" (bug default lama di
 * Pop::generateComplexCid()) jadi "0" sesuai skema resmi ID_NUMBERING_RULES.md.
 */
class BackfillCidDefaultDistributionSegmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_candidates_without_writing(): void
    {
        $pop = Pop::factory()->create(['cid_prefix' => 'C', 'registration_prefix' => 'RQ', 'pop_code' => 'C']);

        $customer = Customer::factory()->create([
            'pop_id' => $pop->id,
            'distribution_id' => null,
            'customer_code' => 'C00RQ000004',
            'cid' => 'C1XXRQ000004',
        ]);

        $this->artisan('customers:backfill-cid-xx-segment')
            ->assertSuccessful();

        $this->assertSame('C1XXRQ000004', $customer->fresh()->cid);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'backfill_cid_xx_segment']);
    }

    public function test_force_rewrites_xx_segment_to_zero_and_logs_audit(): void
    {
        $pop = Pop::factory()->create(['cid_prefix' => 'C', 'registration_prefix' => 'RQ', 'pop_code' => 'C']);

        $customer = Customer::factory()->create([
            'pop_id' => $pop->id,
            'distribution_id' => null,
            'customer_code' => 'C00RQ000004',
            'cid' => 'C1XXRQ000004',
        ]);

        $this->artisan('customers:backfill-cid-xx-segment', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame('C10RQ000004', $customer->fresh()->cid);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'backfill_cid_xx_segment',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
        ]);
    }

    public function test_ignores_customers_already_assigned_to_a_distribution(): void
    {
        $pop = Pop::factory()->create(['cid_prefix' => 'C', 'registration_prefix' => 'RQ', 'pop_code' => 'C']);

        // distribution_id terisi → bukan kandidat, walau kebetulan cid-nya mengandung "XXRQ".
        $distribution = Distribution::create([
            'pop_id' => $pop->id,
            'code' => 'X1A',
            'name' => 'Dist Test',
        ]);

        $customer = Customer::factory()->create([
            'pop_id' => $pop->id,
            'distribution_id' => $distribution->id,
            'customer_code' => 'C00RQ000005',
            'cid' => 'C1XXRQ000005',
        ]);

        $this->artisan('customers:backfill-cid-xx-segment', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame('C1XXRQ000005', $customer->fresh()->cid);
    }

    public function test_ignores_customers_without_xx_segment(): void
    {
        $pop = Pop::factory()->create(['cid_prefix' => 'C', 'registration_prefix' => 'RQ', 'pop_code' => 'C']);

        // "1" di sini kode mini POP sungguhan, bukan hasil bug — jangan disentuh.
        $customer = Customer::factory()->create([
            'pop_id' => $pop->id,
            'distribution_id' => null,
            'customer_code' => 'C00RQ000006',
            'cid' => 'C1X1ARQ000006',
        ]);

        $this->artisan('customers:backfill-cid-xx-segment', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame('C1X1ARQ000006', $customer->fresh()->cid);
    }
}
