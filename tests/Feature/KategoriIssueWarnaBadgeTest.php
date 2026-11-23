<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Database\Seeders\TicketIssueCategoryFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Color picker warna badge di Master Issue/Kategori Keluhan (ADHOC-314).
 * Dulu warna badge cuma ditebak dari kata kunci nama kategori di Worksheet
 * Helpdesk — admin gak bisa ngatur. Kosong = tetap pakai tebakan lama.
 */
class KategoriIssueWarnaBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(TicketIssueCategoryFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'ODP LOS',
            'default_priority' => 'High',
            'sla_source' => 'prioritas',
            'is_active' => '1',
        ], $override);
    }

    #[Test]
    public function warna_badge_tersimpan_dalam_huruf_kecil(): void
    {
        $this->loginAsAdmin();

        $this->post(route('master.ticket-issue-categories.store'), $this->payload(['badge_color' => '#E11D48']))
            ->assertRedirect(route('master.ticket-issue-categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('#e11d48', TicketIssueCategory::where('name', 'ODP LOS')->value('badge_color'));
    }

    #[Test]
    public function warna_kosong_disimpan_null_artinya_otomatis(): void
    {
        $this->loginAsAdmin();

        $this->post(route('master.ticket-issue-categories.store'), $this->payload(['badge_color' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(TicketIssueCategory::where('name', 'ODP LOS')->value('badge_color'));
    }

    #[Test]
    public function warna_bisa_dikosongkan_lagi_lewat_edit(): void
    {
        $this->loginAsAdmin();
        $category = TicketIssueCategory::create($this->payload(['badge_color' => '#10b981', 'is_active' => true]));

        $this->put(route('master.ticket-issue-categories.update', $category), $this->payload(['badge_color' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($category->fresh()->badge_color);
    }

    /**
     * Nilai dirender ke atribut `style` — format selain hex 6 digit ditolak
     * supaya gak bisa nyisipin deklarasi CSS lain.
     */
    #[Test]
    #[DataProvider('warnaTidakValid')]
    public function warna_selain_hex_enam_digit_ditolak(string $color): void
    {
        $this->loginAsAdmin();

        $this->post(route('master.ticket-issue-categories.store'), $this->payload(['badge_color' => $color]))
            ->assertSessionHasErrors('badge_color');

        $this->assertDatabaseMissing('ticket_issue_categories', ['name' => 'ODP LOS']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function warnaTidakValid(): array
    {
        return [
            'nama warna' => ['red'],
            'hex 3 digit' => ['#f00'],
            'tanpa pagar' => ['e11d48'],
            'sisipan css' => ['#e11d48; background:url(x)'],
        ];
    }

    #[Test]
    public function warna_teks_menyesuaikan_terang_gelap_latar(): void
    {
        $this->assertSame('#ffffff', TicketIssueCategory::textColorFor('#1e293b'));
        $this->assertSame('#ffffff', TicketIssueCategory::textColorFor('#e11d48'));
        $this->assertSame('#0f172a', TicketIssueCategory::textColorFor('#fde047'));
        $this->assertSame('#0f172a', TicketIssueCategory::textColorFor('#ffffff'));
        $this->assertNull(TicketIssueCategory::textColorFor(null));
    }

    #[Test]
    public function halaman_master_menampilkan_badge_berwarna(): void
    {
        $this->loginAsAdmin();
        TicketIssueCategory::create($this->payload(['badge_color' => '#9333ea', 'is_active' => true]));

        $this->get(route('master.ticket-issue-categories.index'))
            ->assertOk()
            ->assertSee('background-color: #9333ea; color: #ffffff', false);
    }

    #[Test]
    public function form_create_dan_edit_menampilkan_color_picker(): void
    {
        $this->loginAsAdmin();
        $category = TicketIssueCategory::create($this->payload(['badge_color' => '#4f46e5', 'is_active' => true]));

        $this->get(route('master.ticket-issue-categories.create'))
            ->assertOk()
            ->assertSee('name="badge_color"', false)
            ->assertSee('type="color"', false);

        $this->get(route('master.ticket-issue-categories.edit', $category))
            ->assertOk()
            ->assertSee('#4f46e5');
    }

    #[Test]
    public function worksheet_helpdesk_mengirim_warna_badge_kategori(): void
    {
        $role = Role::where('code', 'helpdesk')->firstOrFail();
        $helpdesk = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $helpdesk->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);
        $pop = Pop::create(['name' => 'POP Polorejo', 'code' => 'POP-PLR', 'type' => 'branch', 'address' => 'Polorejo', 'status' => 'active', 'city_id' => $city->id]);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'village_id' => $village->id]);

        $berwarna = TicketIssueCategory::create($this->payload(['badge_color' => '#fde047', 'is_active' => true]));
        $otomatis = TicketIssueCategory::create($this->payload(['name' => 'Lemot', 'is_active' => true]));

        foreach ([$berwarna, $otomatis] as $category) {
            $this->actingAs($helpdesk)->post(route('tickets.store'), [
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $customer->id,
                'detail_keluhan' => 'Internet mati.',
                'priority' => 'High',
                'issue_category_id' => $category->id,
            ])->assertRedirect();
            Ticket::latest('id')->firstOrFail()->forceFill(['issue_category_id' => $category->id])->save();
        }

        $tasks = collect($this->actingAs($helpdesk)->getJson(route('tickets.worksheet-tasks'))->assertOk()->json('tasks'))
            ->keyBy('issue_category');

        $this->assertSame('#fde047', $tasks['ODP LOS']['issue_category_color']);
        $this->assertSame('#0f172a', $tasks['ODP LOS']['issue_category_text_color']);
        $this->assertNull($tasks['Lemot']['issue_category_color']);
        $this->assertNull($tasks['Lemot']['issue_category_text_color']);
    }
}
