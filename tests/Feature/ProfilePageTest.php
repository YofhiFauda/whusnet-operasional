<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function halaman_profil_menampilkan_data_user_yang_login(): void
    {
        $user = $this->loginAsAdmin();

        $response = $this->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee($user->name);
        $response->assertSee($user->email);
    }

    #[Test]
    public function user_bisa_ganti_password_dengan_password_lama_yang_benar(): void
    {
        $user = $this->loginAsAdmin();

        $response = $this->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'PasswordBaru-123!',
            'password_confirmation' => 'PasswordBaru-123!',
        ]);

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('PasswordBaru-123!', $user->fresh()->password));
    }

    #[Test]
    public function ganti_password_ditolak_kalau_password_lama_salah(): void
    {
        $user = $this->loginAsAdmin();

        $response = $this->put(route('profile.password'), [
            'current_password' => 'password-salah',
            'password' => 'PasswordBaru-123!',
            'password_confirmation' => 'PasswordBaru-123!',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    #[Test]
    public function ganti_password_ditolak_kalau_konfirmasi_tidak_cocok(): void
    {
        $this->loginAsAdmin();

        $response = $this->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'PasswordBaru-123!',
            'password_confirmation' => 'tidak-cocok',
        ]);

        $response->assertSessionHasErrors('password');
    }

    #[Test]
    public function halaman_profil_menampilkan_pop_yang_ditugaskan(): void
    {
        $user = $this->loginAsAdmin();
        $pop = Pop::factory()->create([
            'name' => 'POP Cabang Timur',
            'code' => 'PCT-01',
        ]);
        $user->pops()->attach($pop->id);

        $response = $this->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('POP Cabang Timur');
        $response->assertSee('PCT-01');
    }

    #[Test]
    public function halaman_profil_menampilkan_riwayat_aktivitas(): void
    {
        $user = $this->loginAsAdmin();
        AuditLog::create([
            'user_id' => $user->id,
            'module' => 'User Management',
            'action' => 'update',
            'auditable_type' => $user::class,
            'auditable_id' => $user->id,
            'new_values' => ['test' => 'sample'],
            'ip_address' => '192.168.1.100',
            'created_at' => now(),
        ]);

        $response = $this->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('User Management');
        $response->assertDontSee('192.168.1.100');
    }
}
