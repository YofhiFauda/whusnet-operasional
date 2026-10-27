<?php

namespace Database\Seeders;

use App\Enums\ActionCode;
use App\Enums\FeatureType;
use App\Models\Action;
use App\Models\Feature;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManualFeatureSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $feature = Feature::firstOrCreate(
                ['code' => 'manuals'],
                ['name' => 'Pusat Panduan', 'type' => FeatureType::ROOT, 'sort_order' => 30]
            );

            foreach ([
                ActionCode::CREATE->value => ['Create', 'Membuat artikel panduan'],
                ActionCode::UPDATE->value => ['Update', 'Menyunting dan memulihkan artikel panduan'],
                ActionCode::PUBLISH->value => ['Publish', 'Menerbitkan atau menarik artikel panduan'],
            ] as $code => [$name, $description]) {
                $action = Action::firstOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'description' => $description]
                );

                Permission::firstOrCreate(
                    ['code' => "manuals.{$code}"],
                    [
                        'feature_id' => $feature->id,
                        'action_id' => $action->id,
                        'name' => $description,
                        'module' => 'Pusat Panduan',
                    ]
                );
            }
        });
    }
}
