<?php

namespace Database\Seeders;

use App\Models\Distribution;
use App\Models\Pop;
use Illuminate\Database\Seeder;

class JetisPopSeeder extends Seeder
{
    /**
     * Seed POP Jetis with branch and mini POP hierarchy.
     */
    public function run(): void
    {
        $branch = Pop::query()->updateOrCreate(
            ['pop_code' => 'C'],
            [
                'code' => 'C',
                'name' => 'Jetis',
                'type' => 'cabang',
                'parent_id' => null,
                'status' => 'active',
                'registration_prefix' => 'RQ',
                'cid_prefix' => 'C',
            ]
        );

        $miniPops = [
            'C1' => [
                'name' => 'Jetis C1',
                'distributions' => ['X4A', 'X4B', 'X4C', 'X4D'],
            ],
            'C2' => [
                'name' => 'Jetis C2',
                'distributions' => ['X4E', 'X4F', 'X4G', 'X4H'],
            ],
        ];

        foreach ($miniPops as $code => $config) {
            $miniPop = Pop::query()->updateOrCreate(
                ['pop_code' => $code],
                [
                    'code' => $code,
                    'name' => $config['name'],
                    'type' => 'mini_pop',
                    'parent_id' => $branch->id,
                    'status' => 'active',
                    'registration_prefix' => 'RQ',
                    'cid_prefix' => 'C',
                ]
            );

            foreach ($config['distributions'] as $distributionCode) {
                Distribution::query()->updateOrCreate(
                    [
                        'pop_id' => $miniPop->id,
                        'code' => $distributionCode,
                    ],
                    [
                        'description' => 'Distribusi Jetis ' . $code . ' ' . $distributionCode,
                    ]
                );
            }
        }
    }
}