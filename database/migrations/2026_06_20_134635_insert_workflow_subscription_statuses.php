<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $shifts = [
            'surveyed' => 4,
            'waiting_installation' => 6,
            'installed' => 8,
            'active' => 10,
            'suspended' => 11,
            'terminated' => 12,
            'rejected' => 13,
        ];

        // shift to temporary high numbers to avoid unique constraint violations
        foreach ($shifts as $code => $order) {
            DB::table('subscription_statuses')->where('code', $code)->update(['workflow_order' => $order + 100]);
        }

        // shift to correct numbers
        foreach ($shifts as $code => $order) {
            DB::table('subscription_statuses')->where('code', $code)->update(['workflow_order' => $order]);
        }

        // insert new statuses
        DB::table('subscription_statuses')->insert([
            [
                'code' => 'survey_in_progress',
                'name' => 'Proses Survey',
                'workflow_order' => 3,
                'badge_color' => 'indigo',
                'description' => 'Countdown survey berjalan (Proses Survey).',
                'is_terminal' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'waiting_acc',
                'name' => 'Menunggu ACC',
                'workflow_order' => 5,
                'badge_color' => 'orange',
                'description' => 'Menunggu ACC / Verifikasi Admin setelah survey selesai.',
                'is_terminal' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'installation_in_progress',
                'name' => 'Mulai Pasang',
                'workflow_order' => 7,
                'badge_color' => 'indigo',
                'description' => 'Countdown pemasangan berjalan (Mulai Pasang).',
                'is_terminal' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'verification_admin',
                'name' => 'Verifikasi Admin',
                'workflow_order' => 9,
                'badge_color' => 'amber',
                'description' => 'Menunggu admin isi tagihan & verifikasi akhir sebelum aktif.',
                'is_terminal' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('subscription_statuses')
            ->whereIn('code', [
                'survey_in_progress',
                'waiting_acc',
                'installation_in_progress',
                'verification_admin',
            ])->delete();

        $revert = [
            'surveyed' => 3,
            'waiting_installation' => 4,
            'installed' => 5,
            'active' => 6,
            'suspended' => 7,
            'terminated' => 8,
            'rejected' => 9,
        ];

        foreach ($revert as $code => $order) {
            DB::table('subscription_statuses')->where('code', $code)->update(['workflow_order' => $order + 100]);
        }
        foreach ($revert as $code => $order) {
            DB::table('subscription_statuses')->where('code', $code)->update(['workflow_order' => $order]);
        }
    }
};
