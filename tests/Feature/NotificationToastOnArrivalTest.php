<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notif realtime yang masuk lewat Echo sebelumnya cuma nambah badge diam-diam
 * — gak ada toast pop-up, jadi user gak sadar ada kejadian baru (tiket masuk
 * NOC, task selesai buat FOP, dll) kecuali buka dropdown lonceng manual.
 * Regresi ringan: pastikan pemanggilan `window.Toast` tetap nempel di handler
 * `.notification()` komponen navbar (resources/views/components/
 * notification-dropdown.blade.php) — kalau ada yang gak sengaja nyabut ini
 * lagi, test ini nangkep duluan (bukan uji visual toast-nya sendiri, itu di
 * luar jangkauan PHPUnit tanpa Dusk).
 */
class NotificationToastOnArrivalTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_dropdown_triggers_toast_on_realtime_arrival(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('window.Toast', false);
        $response->assertSee('(window.Toast[type] || window.Toast.info)(notification.title, notification.message)', false);
    }
}
