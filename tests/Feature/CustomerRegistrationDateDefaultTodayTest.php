<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationDateDefaultTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_create_page_defaults_registration_date_to_today(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get(route('customers.create'));

        $response->assertOk();
        $todayStr = today()->format('Y-m-d');
        $response->assertSee('value="'.$todayStr.'"', false);
    }
}
