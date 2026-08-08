<?php

namespace Database\Seeders;

use App\Models\TicketIssueCategory;
use Illuminate\Database\Seeder;

/**
 * DATA CONTOH — ganti sebelum go-live. Kategori issue di bawah ini dummy,
 * wajib direvisi user/NOC lewat halaman Master Issue sebelum production.
 */
class TicketIssueCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Lemot', 'default_priority' => 'low', 'sla_source' => 'prioritas'],
            ['name' => 'LOS', 'default_priority' => 'Medium', 'sla_source' => 'prioritas'],
            ['name' => 'Backbone CUT', 'default_priority' => 'High', 'sla_source' => 'prioritas'],
            ['name' => 'ODP LOS', 'default_priority' => 'High', 'sla_source' => 'prioritas'],
        ];

        foreach ($categories as $category) {
            TicketIssueCategory::updateOrCreate(
                ['name' => $category['name']],
                $category + ['is_active' => true]
            );
        }
    }
}
