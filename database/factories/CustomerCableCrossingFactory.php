<?php

namespace Database\Factories;

use App\Enums\CableCrossingStatus;
use App\Models\Customer;
use App\Models\CustomerCableCrossing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCableCrossing>
 */
class CustomerCableCrossingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'customer_installation_id' => null,
            'inventory_roll_id' => null,
            'nama_jalan' => 'Jl. '.fake()->streetName(),
            'jenis_jalan' => 'Jalan raya',
            'meter_terpakai' => fake()->randomFloat(2, 5, 80),
            'status' => CableCrossingStatus::TERPASANG,
            'foto_bukti' => 'cable-crossings/bukti.jpg',
            'dicatat_oleh' => null,
            'dicatat_at' => now(),
            'deact_task_id' => null,
            'diselesaikan_at' => null,
            'foto_penarikan' => null,
            'alasan_tidak_ditarik' => null,
        ];
    }

    public function ditarik(): static
    {
        return $this->state(fn () => [
            'status' => CableCrossingStatus::DITARIK,
            'diselesaikan_at' => now(),
            'foto_penarikan' => 'cable-crossings/penarikan.jpg',
        ]);
    }

    public function tidakBisaDitarik(): static
    {
        return $this->state(fn () => [
            'status' => CableCrossingStatus::TIDAK_BISA_DITARIK,
            'alasan_tidak_ditarik' => 'Kabel tertanam di aspal, tidak bisa ditarik tanpa galian.',
        ]);
    }
}
