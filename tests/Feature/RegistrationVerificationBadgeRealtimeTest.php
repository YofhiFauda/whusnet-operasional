<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\WorkflowTransition;
use App\Events\RegistrationVerificationQueueChanged;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi: badge sidebar "Verifikasi Registrasi" tidak realtime — angkanya
 * cuma dihitung saat halaman dirender, jadi registrasi baru baru kelihatan
 * setelah pindah halaman. Sekarang CustomerObserver mem-broadcast
 * RegistrationVerificationQueueChanged dan sidebar mengambil ulang angkanya
 * lewat endpoint badge-count.
 */
class RegistrationVerificationBadgeRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->pop = $this->makePop('RVB1');
    }

    private function makePop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeCustomer(string $status, ?Pop $pop = null): Customer
    {
        return Customer::create([
            'customer_code' => 'RVB-'.random_int(10000, 99999),
            'full_name' => 'Pelanggan Badge Test',
            'primary_phone' => '081234500000',
            'status' => $status,
            'pop_id' => ($pop ?? $this->pop)->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function scopedHelpdesk(Pop $pop): User
    {
        $role = Role::where('code', 'helpdesk')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    #[Test]
    public function registrasi_baru_berstatus_registered_mem_broadcast_ke_pop_nya(): void
    {
        Event::fake([RegistrationVerificationQueueChanged::class]);

        $this->makeCustomer(WorkflowTransition::REGISTERED->value);

        Event::assertDispatched(RegistrationVerificationQueueChanged::class, fn ($e) => $e->popId === $this->pop->id);
    }

    #[Test]
    public function pelanggan_baru_di_luar_antrean_tidak_mem_broadcast(): void
    {
        Event::fake([RegistrationVerificationQueueChanged::class]);

        $this->makeCustomer(WorkflowTransition::ACTIVE->value);

        Event::assertNotDispatched(RegistrationVerificationQueueChanged::class);
    }

    #[Test]
    public function keluar_dari_registered_mem_broadcast(): void
    {
        $customer = $this->makeCustomer(WorkflowTransition::REGISTERED->value);
        Event::fake([RegistrationVerificationQueueChanged::class]);

        $customer->update(['status' => WorkflowTransition::WAITING_SURVEY->value]);

        Event::assertDispatchedTimes(RegistrationVerificationQueueChanged::class, 1);
    }

    #[Test]
    public function edit_data_tanpa_ganti_status_atau_pop_tidak_mem_broadcast(): void
    {
        $customer = $this->makeCustomer(WorkflowTransition::REGISTERED->value);
        Event::fake([RegistrationVerificationQueueChanged::class]);

        $customer->update(['full_name' => 'Nama Baru']);

        Event::assertNotDispatched(RegistrationVerificationQueueChanged::class);
    }

    #[Test]
    public function pindah_pop_selagi_registered_memberi_tahu_pop_lama_dan_baru(): void
    {
        $customer = $this->makeCustomer(WorkflowTransition::REGISTERED->value);
        $popBaru = $this->makePop('RVB2');
        Event::fake([RegistrationVerificationQueueChanged::class]);

        $customer->update(['pop_id' => $popBaru->id]);

        Event::assertDispatched(RegistrationVerificationQueueChanged::class, fn ($e) => $e->popId === $this->pop->id);
        Event::assertDispatched(RegistrationVerificationQueueChanged::class, fn ($e) => $e->popId === $popBaru->id);
    }

    #[Test]
    public function badge_count_mengikuti_pop_scope_user(): void
    {
        $popLain = $this->makePop('RVB3');
        $this->makeCustomer(WorkflowTransition::REGISTERED->value);
        $this->makeCustomer(WorkflowTransition::REGISTERED->value);
        $this->makeCustomer(WorkflowTransition::REGISTERED->value, $popLain);
        $this->makeCustomer(WorkflowTransition::ACTIVE->value);

        $this->actingAs($this->scopedHelpdesk($this->pop))
            ->getJson(route('customer-registration-verifications.badge-count'))
            ->assertOk()
            ->assertExactJson(['count' => 2]);
    }

    #[Test]
    public function badge_count_ditolak_tanpa_permission(): void
    {
        $role = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->actingAs($teknisi)
            ->getJson(route('customer-registration-verifications.badge-count'))
            ->assertForbidden();
    }

    #[Test]
    public function sidebar_merender_pop_yang_di_subscribe(): void
    {
        $this->actingAs($this->scopedHelpdesk($this->pop))
            ->get(route('customer-registration-verifications.index'))
            ->assertOk()
            ->assertSee('id="badge-registration-verification"', false)
            ->assertSee("data-pop-ids='[{$this->pop->id}]'", false);
    }

    /**
     * BROADCAST_CONNECTION=null di test ⇒ POST /broadcasting/auth tidak pernah
     * memanggil closure channel. Closure-nya dipanggil langsung.
     */
    #[Test]
    public function channel_hanya_untuk_pop_dalam_scope(): void
    {
        $popLain = $this->makePop('RVB4');
        $helpdesk = $this->scopedHelpdesk($this->pop);
        $teknisi = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->value('id'), 'status' => 'active']);

        $channel = Broadcast::driver()->getChannels()->get('registration-verifications.{popId}');

        $this->assertTrue($channel($helpdesk, (string) $this->pop->id));
        $this->assertFalse($channel($helpdesk, (string) $popLain->id));
        $this->assertFalse($channel($teknisi, (string) $this->pop->id));
    }
}
