<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Q3: foto penarikan alat privat (disk local) — hanya lewat controller yang
 * mengecek permission + POP scope. Foto lama di disk public tetap terbaca
 * sampai dipindah command one-off.
 */
class DeviceRetrievalPhotoPrivateAccessTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function pemegang_hak_dalam_scope_bisa_membuka_foto(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->fop)->get(route('device-retrievals.photo', $attempt))->assertOk();
        $this->actingAs($this->cs)->get(route('device-retrievals.photo', $attempt))->assertOk();
        // Teknisi pelaksana task-nya sendiri.
        $this->actingAs($this->teknisi)->get(route('device-retrievals.photo', $attempt))->assertOk();
    }

    #[Test]
    public function luar_scope_atau_tanpa_hak_ditolak(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);

        $fopB = $this->makeScopedUser('fop', $this->popB);
        $this->actingAs($fopB)->get(route('device-retrievals.photo', $attempt))->assertForbidden();

        $noRole = User::factory()->create(['role_id' => Role::where('code', 'sales')->firstOrFail()->id]);
        $this->actingAs($noRole)->get(route('device-retrievals.photo', $attempt))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->get(route('device-retrievals.photo', $attempt))->assertRedirect(route('login'));
    }

    #[Test]
    public function foto_lama_di_disk_public_tetap_terbaca_lalu_bisa_dipindah(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);
        $path = $attempt->condition_photo;
        Storage::disk('local')->delete($path);
        Storage::disk('public')->put($path, 'jpg-lama');

        $this->actingAs($this->fop)->get(route('device-retrievals.photo', $attempt))->assertOk();

        $this->artisan('device-retrieval:move-photos-private', ['--dry-run' => true])->assertSuccessful();
        Storage::disk('public')->assertExists($path);

        $this->artisan('device-retrieval:move-photos-private')->assertSuccessful();
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('jpg-lama', Storage::disk('local')->get($path));
    }

    #[Test]
    public function salinan_publik_identik_dihapus_salinan_berbeda_dilaporkan(): void
    {
        // Sudah ada di kedua disk dengan isi sama (command dijalankan ulang).
        Storage::disk('local')->put('device-retrieval/sama.jpg', 'isi-sama');
        Storage::disk('public')->put('device-retrieval/sama.jpg', 'isi-sama');
        // Nama sama, isi berbeda — tidak boleh ditimpa/dihapus diam-diam.
        Storage::disk('local')->put('device-retrieval/beda.jpg', 'isi-privat');
        Storage::disk('public')->put('device-retrieval/beda.jpg', 'isi-publik');

        $this->artisan('device-retrieval:move-photos-private')
            ->expectsOutputToContain('device-retrieval/beda.jpg')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing('device-retrieval/sama.jpg');
        Storage::disk('public')->assertExists('device-retrieval/beda.jpg');
        $this->assertSame('isi-privat', Storage::disk('local')->get('device-retrieval/beda.jpg'));
    }

    #[Test]
    public function halaman_review_tidak_memakai_url_storage_publik(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.show', $this->customer))
            ->assertOk()
            ->assertSee(route('device-retrievals.photo', $attempt), false)
            ->assertDontSee('storage/device-retrieval', false);
    }
}
