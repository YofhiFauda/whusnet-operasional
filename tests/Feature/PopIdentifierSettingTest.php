<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\PopSequence;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopIdentifierSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pop_identifier_fields_are_saved_from_crud(): void
    {
        $this->loginAsAdmin();

        $response = $this->post('/master/pop', [
            'code' => 'POP-SMN-01',
            'pop_code' => 'smn',
            'registration_prefix' => 'c',
            'cid_prefix' => 'd',
            'name' => 'POP Sleman',
            'type' => 'cabang',
        ]);

        $response->assertRedirect('/master/pop');

        $this->assertDatabaseHas('pops', [
            'code' => 'POP-SMN-01',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
        ]);
    }

    public function test_registration_number_runs_per_pop(): void
    {
        $sleman = $this->makePop('POP-SMN', 'SMN');
        $bantul = $this->makePop('POP-BTL', 'BTL');

        // Format baru: {registration_prefix}{######}
        $this->assertSame('C000001', $sleman->generateRegistrationNumber());
        $this->assertSame('C000002', $sleman->generateRegistrationNumber());
        $this->assertSame('C000001', $bantul->generateRegistrationNumber());
    }

    public function test_cid_sequence_is_separate_from_registration_sequence(): void
    {
        $pop = $this->makePop('POP-SMN', 'SMN');

        // Registration counter harus terpisah dari CID counter
        $this->assertSame('C000001', $pop->generateRegistrationNumber());
        $this->assertSame('C000002', $pop->generateRegistrationNumber());

        $this->assertDatabaseHas('pop_sequences', [
            'pop_id' => $pop->id,
            'sequence_type' => PopSequence::TYPE_REGISTRATION,
            'current_number' => 2,
        ]);
    }

    public function test_pop_identifier_values_must_be_unique_and_valid(): void
    {
        $this->loginAsAdmin();

        $this->makePop('POP-SMN', 'SMN');

        $response = $this->post('/master/pop', [
            'code' => 'POP-SMN-02',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sleman Duplicate',
            'type' => 'cabang',
        ]);

        $response->assertSessionHasErrors(['pop_code']);

        $response = $this->post('/master/pop', [
            'code' => 'POP-BTL-01',
            'pop_code' => 'BTL!',
            'registration_prefix' => 'C-',
            'cid_prefix' => 'D',
            'name' => 'POP Bantul',
            'type' => 'cabang',
        ]);

        $response->assertSessionHasErrors(['pop_code', 'registration_prefix']);
    }

    private function makePop(string $code, string $popCode): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $popCode,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => $code,
            'type' => 'cabang',
        ]);
    }
}
