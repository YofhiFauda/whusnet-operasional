<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_date_lists_and_recent_incomplete_customers_have_ordering_indexes(): void
    {
        $this->assertSame(['issue_date'], $this->indexColumns('invoices', 'invoices_issue_date_idx'));
        $this->assertSame(['payment_date'], $this->indexColumns('payments', 'payments_payment_date_idx'));
        $this->assertSame(['updated_at'], $this->indexColumns('customers', 'customers_updated_at_idx'));
    }

    public function test_duplicate_workflow_transition_pair_is_rejected(): void
    {
        DB::table('workflow_transition_permissions')->insert([
            'from_status' => '__index_test_from__',
            'to_status' => '__index_test_to__',
            'permission_name' => 'task.manage',
        ]);

        $this->expectException(QueryException::class);

        DB::table('workflow_transition_permissions')->insert([
            'from_status' => '__index_test_from__',
            'to_status' => '__index_test_to__',
            'permission_name' => 'task.cancel',
        ]);
    }

    /**
     * @return list<string>
     */
    private function indexColumns(string $table, string $name): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $name) {
                return $index['columns'];
            }
        }

        return [];
    }
}
