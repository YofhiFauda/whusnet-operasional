<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADHOC-288 Tahap 8 — dasar dashboard delivery rate per merek HP/OS
     * (ditunda dari Tahap 6 item 4). `device_name` = merek hasil deteksi
     * user agent (lihat `App\Support\DeviceBrandDetector`), `user_agent`
     * = string mentah buat audit/debug kalau deteksi merek meleset.
     */
    public function up(): void
    {
        /** @var string|null $connection */
        $connection = config('webpush.database_connection');
        /** @var string $tableName */
        $tableName = config('webpush.table_name');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) {
            $table->string('device_name')->nullable()->after('content_encoding');
            $table->string('user_agent', 512)->nullable()->after('device_name');
        });
    }

    public function down(): void
    {
        /** @var string|null $connection */
        $connection = config('webpush.database_connection');
        /** @var string $tableName */
        $tableName = config('webpush.table_name');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) {
            $table->dropColumn(['device_name', 'user_agent']);
        });
    }
};
