<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris per PENGIRIMAN notifikasi (bukan per device/subscription) —
     * `WebPushChannel::send()` bikin SATU payload yang sama lalu dikirim ke
     * SEMUA subscription milik notifiable, jadi "diterima" cukup ditandai
     * begitu salah satu device-nya ack (ADHOC-288, Tahap 6).
     */
    public function up(): void
    {
        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->morphs('notifiable');
            $table->string('notification_class');
            $table->string('status')->default('sent');
            $table->timestamp('sent_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            // Dashboard delivery memfilter per periode (WebPushDashboardController).
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
    }
};
