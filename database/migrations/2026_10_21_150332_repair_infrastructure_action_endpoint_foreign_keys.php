<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENDPOINTS = [
        'end_from_infrastructure_port_id' => 'tia_end_from_port_foreign',
        'end_to_infrastructure_port_id' => 'tia_end_to_port_foreign',
    ];

    public function up(): void
    {
        foreach (self::ENDPOINTS as $column => $name) {
            $identity = Schema::getConnection()->getDriverName() === 'sqlite' ? [$column] : $name;
            if (! Schema::hasForeignKey('task_infrastructure_actions', $identity)) {
                Schema::table('task_infrastructure_actions', function (Blueprint $table) use ($column, $name): void {
                    $table->foreign($column, $name)->references('id')->on('infrastructure_ports')->restrictOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::ENDPOINTS as $column => $name) {
            $identity = Schema::getConnection()->getDriverName() === 'sqlite' ? [$column] : $name;
            if (Schema::hasForeignKey('task_infrastructure_actions', $identity)) {
                Schema::table('task_infrastructure_actions', function (Blueprint $table) use ($identity): void {
                    $table->dropForeign($identity);
                });
            }
        }
    }
};
