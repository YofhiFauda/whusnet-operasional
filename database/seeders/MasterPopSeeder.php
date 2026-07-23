<?php

namespace Database\Seeders;

use App\Models\Distribution;
use App\Models\Pop;
use Illuminate\Database\Seeder;

class MasterPopSeeder extends Seeder
{
    /**
     * Seed POP Jetis and Siman with branch and mini POP hierarchies.
     */
    public function run(): void
    {
        // 1. Seed POP Jetis
        $jetisBranch = Pop::query()->updateOrCreate(
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

        $jetisMiniPops = [
            'C1' => [
                'name' => 'Jetis C1',
                'distributions' => ['X4A', 'X4B', 'X4C', 'X4D'],
            ],
            'C2' => [
                'name' => 'Jetis C2',
                'distributions' => ['X4E', 'X4F', 'X4G', 'X4H'],
            ],
        ];

        foreach ($jetisMiniPops as $code => $config) {
            $miniPop = Pop::query()->updateOrCreate(
                ['pop_code' => $code],
                [
                    'code' => $code,
                    'name' => $config['name'],
                    'type' => 'mini_pop',
                    'parent_id' => $jetisBranch->id,
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
                        'description' => 'Distribusi Jetis '.$code.' '.$distributionCode,
                    ]
                );
            }
        }

        // 2. Seed POP Siman
        $simanBranch = Pop::query()->updateOrCreate(
            ['pop_code' => 'D'],
            [
                'code' => 'D',
                'name' => 'Siman',
                'type' => 'cabang',
                'parent_id' => null,
                'status' => 'active',
                'registration_prefix' => 'RQ',
                'cid_prefix' => 'D',
            ]
        );

        $simanMiniPops = [
            'D1' => [
                'name' => 'Siman D1',
                'distributions' => ['X6A', 'X6B', 'X6C', 'X6D'],
            ],
            'D2' => [
                'name' => 'Siman D2',
                'distributions' => ['X6E', 'X6F', 'X6G'],
            ],
            'D3' => [
                'name' => 'Siman D3',
                'distributions' => ['X6H', 'X6I', 'X6J'],
            ],
            'D4' => [
                'name' => 'Siman D4',
                'distributions' => ['X6K', 'X6L', 'X6M'],
            ],
        ];

        foreach ($simanMiniPops as $code => $config) {
            $miniPop = Pop::query()->updateOrCreate(
                ['pop_code' => $code],
                [
                    'code' => $code,
                    'name' => $config['name'],
                    'type' => 'mini_pop',
                    'parent_id' => $simanBranch->id,
                    'status' => 'active',
                    'registration_prefix' => 'RQ',
                    'cid_prefix' => 'D',
                ]
            );

            foreach ($config['distributions'] as $distributionCode) {
                Distribution::query()->updateOrCreate(
                    [
                        'pop_id' => $miniPop->id,
                        'code' => $distributionCode,
                    ],
                    [
                        'description' => 'Distribusi Siman '.$code.' '.$distributionCode,
                    ]
                );
            }
        }
    }
}
