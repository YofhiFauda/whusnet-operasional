<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * invoice_type had a silent DB-level default of 'bulanan' (added in
     * 2026_07_02_133000_add_invoice_type_to_invoices_table.php), which is the
     * exact ambiguity that caused invoices to be mistagged during the legacy
     * migration. Dropping the default forces every insert path (controller,
     * command, future API) to state the type explicitly.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY invoice_type VARCHAR(30) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY invoice_type VARCHAR(30) NOT NULL DEFAULT 'bulanan'");
    }
};
