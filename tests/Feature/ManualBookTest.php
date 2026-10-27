<?php

namespace Tests\Feature;

use App\Models\ManualArticle;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ManualArticleSeeder;
use Database\Seeders\ManualFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualBookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function draft_dan_revisi_terbit_terpisah(): void
    {
        $this->loginAsAdmin();
        $this->get(route('manuals.create'))->assertOk();
        $this->get(route('manuals.manage'))->assertOk();
        $this->post(route('manuals.store'), [
            'slug' => 'pasang-switch',
            'title' => 'Pasang Switch',
            'category' => 'Infrastruktur',
            'summary' => 'Cara pemasangan',
            'body_markdown' => 'Versi awal',
            'required_permission' => null,
            'route_names_text' => "network.map.index\nnetwork.nodes.index",
        ])->assertRedirect();

        $article = ManualArticle::where('slug', 'pasang-switch')->firstOrFail();
        $this->assertSame(['network.map.index', 'network.nodes.index'], $article->route_names);
        $this->get(route('manuals.edit', $article))->assertOk()->assertSee('Pratinjau draf tersimpan');
        $this->assertCount(1, $article->revisions);
        $this->get(route('manuals.show', $article->slug))->assertNotFound();

        $this->post(route('manuals.publish', $article))->assertRedirect();
        $this->get(route('manuals.show', $article->slug))->assertSee('Versi awal');

        $this->put(route('manuals.update', $article), [
            'title' => 'Pasang Switch',
            'category' => 'Infrastruktur',
            'summary' => 'Cara baru',
            'body_markdown' => 'Versi berikutnya',
            'required_permission' => null,
            'route_names' => ['network.map.index'],
        ])->assertRedirect();
        $this->get(route('manuals.show', $article->slug))
            ->assertSee('Versi awal')->assertDontSee('Versi berikutnya');
        $this->assertCount(2, $article->fresh()->revisions);
        $this->post(route('manuals.publish', $article))->assertRedirect();
        $this->get(route('manuals.show', $article->slug))->assertSee('Versi berikutnya');
    }

    #[Test]
    public function pratinjau_draf_aman_dan_tidak_dapat_dibaca_staf(): void
    {
        $this->loginAsAdmin();
        $article = ManualArticle::factory()->create([
            'slug' => 'pratinjau-odp',
            'title' => 'Pratinjau ODP',
            'category' => 'Infrastruktur',
            'summary' => 'Uji pratinjau',
            'body_markdown' => "<script>alert(1)</script>\n\n**Draf aman**",
        ]);

        $this->get(route('manuals.preview', $article))
            ->assertOk()->assertSee('Draf aman')->assertDontSee('alert(1)', false);
        $this->get(route('manuals.show', $article->slug))->assertNotFound();

        $this->actingAs(User::factory()->create(['role_id' => null]));
        $this->get(route('manuals.preview', $article))->assertForbidden();
    }

    #[Test]
    public function seeder_memecah_manual_infrastruktur_tanpa_menimpa_edit_admin(): void
    {
        $this->seed(ManualArticleSeeder::class);
        $this->assertSame(11, ManualArticle::count());
        $article = ManualArticle::where('slug', 'menambah-odp-baru')->firstOrFail();
        $this->assertNotNull($article->published_revision_id);
        $article->update(['title' => 'Judul disunting admin']);
        $this->seed(ManualArticleSeeder::class);
        $this->assertSame(11, ManualArticle::count());
        $this->assertSame('Judul disunting admin', $article->fresh()->title);
    }

    #[Test]
    public function revisi_lama_dapat_dipulihkan_dan_publikasi_dapat_ditarik(): void
    {
        $this->loginAsAdmin();
        $article = ManualArticle::factory()->create([
            'slug' => 'pulihkan-panduan',
            'body_markdown' => 'Langkah lama',
        ]);
        $old = $article->recordRevision(auth()->id());
        $this->post(route('manuals.publish', $article))->assertRedirect();
        $this->put(route('manuals.update', $article), [
            'title' => $article->title,
            'category' => $article->category,
            'summary' => $article->summary,
            'body_markdown' => 'Langkah baru',
            'required_permission' => null,
            'route_names' => [],
        ])->assertRedirect();

        $this->post(route('manuals.restore', [$article, $old]))->assertRedirect();
        $this->assertSame('Langkah lama', $article->fresh()->body_markdown);
        $this->assertCount(3, $article->fresh()->revisions);
        $this->get(route('manuals.show', $article->slug))->assertSee('Langkah lama');

        $this->post(route('manuals.unpublish', $article))->assertRedirect();
        $this->get(route('manuals.show', $article->slug))->assertNotFound();
    }

    #[Test]
    public function izin_admin_diberikan_melalui_matriks_role(): void
    {
        $role = Role::where('code', 'admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($admin);
        $this->get(route('manuals.create'))->assertForbidden();

        $permissionIds = Permission::whereIn('code', [
            'manuals.create', 'manuals.update', 'manuals.publish',
        ])->pluck('id')->all();
        $this->assertCount(3, $permissionIds);
        $role->permissions()->syncWithoutDetaching($permissionIds);
        app(EffectiveAccessService::class)->clearCache($admin);

        $this->get(route('manuals.create'))->assertOk();
        $article = ManualArticle::factory()->create();
        $this->get(route('manuals.edit', $article))->assertOk();
        $this->get(route('manuals.manage'))->assertOk()->assertSee('Terbitkan revisi terbaru');
        $this->post(route('manuals.publish', $article))->assertRedirect();
        $this->assertNotNull($article->fresh()->published_revision_id);
    }

    #[Test]
    public function penerbit_dapat_menerbitkan_tanpa_izin_menyunting(): void
    {
        $role = Role::where('code', 'admin')->firstOrFail();
        $publisher = User::factory()->create(['role_id' => $role->id]);
        $permissionId = Permission::where('code', 'manuals.publish')->firstOrFail()->id;
        $role->permissions()->syncWithoutDetaching([$permissionId]);
        app(EffectiveAccessService::class)->clearCache($publisher);
        $this->actingAs($publisher);

        $article = ManualArticle::factory()->create();
        $this->get(route('manuals.manage'))->assertOk()->assertSee('Terbitkan revisi terbaru');
        $this->get(route('manuals.edit', $article))->assertForbidden();
        $this->post(route('manuals.publish', $article))
            ->assertRedirect(route('manuals.manage'));
        $this->assertNotNull($article->fresh()->published_revision_id);
    }

    #[Test]
    public function seeder_izin_panduan_aman_dijalankan_ulang_tanpa_mengubah_role(): void
    {
        $role = Role::where('code', 'admin')->firstOrFail();
        $before = $role->permissions()->pluck('code')->all();
        $this->seed(ManualFeatureSeeder::class);
        $this->seed(ManualFeatureSeeder::class);

        $this->assertSame($before, $role->permissions()->pluck('code')->all());
        $this->assertSame(3, Permission::whereIn('code', [
            'manuals.create', 'manuals.update', 'manuals.publish',
        ])->count());
    }

    #[Test]
    public function daftar_pencarian_dan_izin_pembaca_dibatasi(): void
    {
        $article = ManualArticle::factory()->create([
            'slug' => 'tambah-odp',
            'title' => 'Tambah ODP',
            'category' => 'Infrastruktur',
            'summary' => 'Panduan ODP',
            'body_markdown' => 'Periksa **port**.',
            'required_permission' => 'network_nodes.view',
            'route_names' => ['network.map.index', 'network.nodes.index'],
        ]);
        $this->get(route('manuals.index'))->assertRedirect(route('login'));
        $this->loginAsAdmin();
        $this->post(route('manuals.publish', $article))->assertRedirect();
        $this->get(route('manuals.index', ['q' => 'ODP']))->assertSee('Tambah ODP');
        $this->get(route('manuals.index', ['q' => 'OLT']))->assertDontSee('Tambah ODP');
        $this->get(route('manuals.context', ['route' => 'network.map.index']))
            ->assertRedirect(route('manuals.show', $article->slug));
        $this->get(route('manuals.context', ['route' => 'network.nodes.index']))
            ->assertRedirect(route('manuals.show', $article->slug));

        $staff = User::factory()->create(['role_id' => null]);
        $this->actingAs($staff);
        $this->get(route('manuals.index'))->assertDontSee('Tambah ODP');
        $this->get(route('manuals.show', $article->slug))->assertNotFound();
        $this->get(route('manuals.create'))->assertForbidden();
        $this->post(route('manuals.publish', $article))->assertForbidden();
    }
}
