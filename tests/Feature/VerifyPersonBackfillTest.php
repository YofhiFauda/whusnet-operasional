<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Person;
use App\Models\Pop;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyPersonBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(Pop $pop, ?Person $person): Customer
    {
        return Customer::factory()->create([
            'pop_id' => $pop->id,
            'person_id' => $person?->id,
        ]);
    }

    public function test_lolos_saat_setiap_customer_punya_person_1_banding_1(): void
    {
        $pop = Pop::factory()->create();
        foreach (range(1, 3) as $i) {
            $this->makeCustomer($pop, Person::create(['legacy_key' => "BR:CUST{$i}"]));
        }

        $this->artisan('persons:verify-backfill')
            ->assertSuccessful();
    }

    public function test_gagal_saat_ada_customer_tanpa_person(): void
    {
        $pop = Pop::factory()->create();
        $this->makeCustomer($pop, Person::create(['legacy_key' => 'BR:CUST1']));
        $this->makeCustomer($pop, null); // yatim — person_id null

        $this->artisan('persons:verify-backfill')
            ->assertFailed();
    }

    public function test_gagal_saat_legacy_key_ganda_lewat_jalur_langsung(): void
    {
        // Unique index menolak insert kedua; buktikan guard DB-nya nyata.
        Person::create(['legacy_key' => 'BR:DUP']);

        $this->expectException(UniqueConstraintViolationException::class);
        Person::create(['legacy_key' => 'BR:DUP']);
    }
}
