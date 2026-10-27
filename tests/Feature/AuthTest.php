<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_view_loads_successfully(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Login - Ruang Kerja Whusnet');
        $response->assertSee('RUANG KERJA');
        $response->assertSee('WHUSNET');
        $response->assertSee('showPassword', false);
        $response->assertSee('Tampilkan / sembunyikan kata sandi', false);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@whusnet.net',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'owner@whusnet.net',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_remember_me(): void
    {
        $user = User::factory()->create([
            'email' => 'remember@whusnet.net',
            'password' => bcrypt('password'),
            'status' => 'active',
            'remember_token' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'remember@whusnet.net',
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        // Verify remember_token was generated in database
        $user->refresh();
        $this->assertNotNull($user->remember_token);

        // Verify remember cookie is set in response
        $response->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_user_login_without_remember_me_does_not_set_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'noremember@whusnet.net',
            'password' => bcrypt('password'),
            'status' => 'active',
            'remember_token' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'noremember@whusnet.net',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        // Verify remember cookie is not present
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@whusnet.net',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'owner@whusnet.net',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Email atau kata sandi yang Anda masukkan tidak sesuai.',
        ]);
        $this->assertGuest();
    }

    public function test_login_page_renders_password_criteria_helper(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Minimal 8 karakter', false);
        $response->assertSee('Huruf besar dan huruf kecil', false);
        $response->assertSee('Minimal 1 angka', false);
        $response->assertSee('Minimal 1 simbol (!@#$%dst)', false);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@whusnet.net',
            'password' => bcrypt('password'),
            'status' => 'inactive',
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@whusnet.net',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
