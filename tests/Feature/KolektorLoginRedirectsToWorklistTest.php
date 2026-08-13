<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi: kolektor cuma punya `kolektor.view` (§B-8 no. 5 — sengaja tanpa
 * dashboard.view/customers.view/task.view.own). Redirect default setelah
 * login (`route('dashboard')` = `/`) sebelumnya cuma punya fallback buat 3
 * permission itu — kolektor tanpa satu pun langsung `abort(403)` di
 * DashboardController, kelihatan kayak "gagal login" padahal auth-nya sah.
 *
 * Ditemukan lewat laporan user 2026-08-03: "matrix permission kolektor cuma
 * 1 (Worklist Saya) → auth.failed/403 pas login; tambah permission lain →
 * berhasil". Root cause: bukan bug RBAC, tapi DashboardController gak punya
 * fallback buat kolektor.view.
 */
class KolektorLoginRedirectsToWorklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_kolektor_with_only_kolektor_permissions_is_redirected_to_worklist_not_403(): void
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        // Role ini SENGAJA cuma punya permission bertema `kolektor.*` — tanpa
        // dashboard.view (kondisi persis yang dulu bikin 403). `kolektor.pay`,
        // `.deposit`, `.visit` menyusul di kolektor-2.0 dan tetap tak membuka
        // halaman lain.
        $this->assertEqualsCanonicalizing(
            ['kolektor.view', 'kolektor.pay', 'kolektor.deposit', 'kolektor.visit'],
            $kolektor->role->permissions()->pluck('code')->toArray()
        );

        $response = $this->actingAs($kolektor)->get(route('dashboard'));

        $response->assertRedirect(route('collector-worklist.index'));
        $response->assertStatus(302);
    }

    public function test_kolektor_worklist_after_redirect_loads_successfully(): void
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $response = $this->actingAs($kolektor)->get(route('dashboard'));
        $response->assertRedirect(route('collector-worklist.index'));

        $followUp = $this->actingAs($kolektor)->get($response->headers->get('Location'));
        $followUp->assertOk();
    }
}
