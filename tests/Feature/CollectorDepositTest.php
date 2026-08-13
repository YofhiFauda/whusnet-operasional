<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Models\CollectorDeposit;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CollectorBalanceService;
use App\Services\EffectiveAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setoran Kolektor (Fase 2) — saldo turunan, cross check, selisih, pelunasan
 * lintas setoran, hapus buku.
 *
 * Skenario angkanya sengaja mengikuti studi kasus di §11.5: kolektor menagih
 * 350rb, menyetor fisik 320rb (kurang 30rb), lalu esok harinya melunasi 30rb
 * itu bersama setoran hari berikutnya. Yang diuji bukan cuma "jalan", tapi
 * bahwa selisihnya tidak bisa menguap di tengah jalan.
 */
class CollectorDepositTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $kolektor;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = $this->createPop('DEP1');
        $this->kolektor = $this->createUser('kolektor', $this->pop);
        $this->admin = $this->createUser('pop_admin', $this->pop);
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => 'POP-'.$code,
            'pop_code' => $code,
            'registration_prefix' => 'C'.substr($code, -1),
            'cid_prefix' => 'D'.substr($code, -1),
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createUser(string $roleCode, ?Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        if ($pop) {
            $scope = UserRoleScope::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => ScopeType::SELECTED_POP,
            ]);
            UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
            $user->pops()->attach($pop->id);
        }

        return $user;
    }

    private function createInvoice(Pop $pop, string $code, float $total): Invoice
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $this->kolektor->id,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $total,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-'.$code,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    /** Kolektor menagih satu pelanggan lewat jalur resminya. */
    private function collect(string $code, float $amount, ?string $key = null): Invoice
    {
        $invoice = $this->createInvoice($this->pop, $code, $amount);

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => $key ?? 'pay-'.$code,
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => $amount, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ])->assertOk();

        return $invoice;
    }

    private function balance(): float
    {
        return app(CollectorBalanceService::class)->balance($this->kolektor->fresh());
    }

    private function shortfall(): float
    {
        return app(CollectorBalanceService::class)->outstandingShortfall($this->kolektor->fresh());
    }

    // ================= SALDO =================

    public function test_balance_is_derived_from_unsettled_payments(): void
    {
        $this->assertSame(0.0, $this->balance());

        $this->collect('C-DEP-A', 100000);
        $this->collect('C-DEP-B', 250000);

        $this->assertSame(350000.0, $this->balance());
    }

    /**
     * Payment ditolak ⇒ saldo turun sendiri, tanpa kompensasi manual. Inilah
     * alasan saldo dihitung, bukan disimpan di kolom yang di-increment.
     */
    public function test_rejected_payment_lowers_balance_without_manual_adjustment(): void
    {
        $this->collect('C-DEP-REJ', 100000);
        $this->assertSame(100000.0, $this->balance());

        $payment = Payment::query()->firstOrFail();
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id, 'status' => 'active']);

        $this->actingAs($owner)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Uang tidak diterima kantor.',
        ])->assertRedirect();

        $this->assertSame(0.0, $this->balance());
    }

    // ================= SETOR =================

    public function test_deposit_takes_entire_balance_and_resets_it_to_zero(): void
    {
        $this->collect('C-DEP-C', 100000);
        $this->collect('C-DEP-D', 250000);

        $this->actingAs($this->kolektor)
            ->post(route('collector-worklist.deposit'))
            ->assertRedirect(route('collector-worklist.index'));

        $deposit = CollectorDeposit::query()->firstOrFail();
        $this->assertSame(DepositStatus::MENUNGGU_VERIFIKASI, $deposit->status);
        $this->assertSame(350000.0, $deposit->computedAmount());
        $this->assertSame(2, $deposit->payments()->count());
        $this->assertSame(0.0, $this->balance());
    }

    /**
     * Penagihan sesudah submit masuk saldo BARU — tidak menggeser angka
     * setoran yang sedang dihitung admin. Itu sebabnya setoran menyimpan
     * daftar payment, bukan totalnya.
     */
    public function test_payments_collected_after_submit_do_not_join_pending_deposit(): void
    {
        $this->collect('C-DEP-E', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));

        $this->collect('C-DEP-F', 75000);

        $deposit = CollectorDeposit::query()->firstOrFail();
        $this->assertSame(100000.0, $deposit->computedAmount());
        $this->assertSame(75000.0, $this->balance());
    }

    public function test_deposit_rejected_when_balance_is_empty(): void
    {
        $this->actingAs($this->kolektor)
            ->post(route('collector-worklist.deposit'))
            ->assertSessionHasErrors('deposit');

        $this->assertDatabaseCount('collector_deposits', 0);
    }

    public function test_deposit_submit_is_idempotent(): void
    {
        $this->collect('C-DEP-IDEM', 100000);

        $payload = ['idempotency_key' => 'dep-key-1'];
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'), $payload);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'), $payload);

        $this->assertDatabaseCount('collector_deposits', 1);
    }

    // ================= VERIFIKASI =================

    public function test_matching_cash_closes_deposit_as_verified(): void
    {
        $this->collect('C-DEP-G', 350000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 350000,
        ])->assertRedirect();

        $deposit->refresh();
        $this->assertSame(DepositStatus::TERVERIFIKASI, $deposit->status);
        $this->assertEquals(0, (float) $deposit->difference);
        $this->assertSame($this->admin->id, (int) $deposit->verified_by);
        $this->assertSame(0.0, $this->shortfall());
    }

    /** Studi kasus §11.5: tercatat 350rb, fisik 320rb → kurang setor 30rb. */
    public function test_short_cash_becomes_tracked_shortfall_not_a_silent_zero(): void
    {
        $this->collect('C-DEP-H', 350000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 320000,
            'note' => 'Kolektor mengaku terpakai dulu.',
        ])->assertRedirect();

        $deposit->refresh();
        $this->assertSame(DepositStatus::SELISIH, $deposit->status);
        $this->assertEquals(-30000, (float) $deposit->difference);

        // Saldo kembali nol TAPI kewajiban tetap terlihat — dua angka
        // terpisah, tidak boleh saling menutupi.
        $this->assertSame(0.0, $this->balance());
        $this->assertSame(30000.0, $this->shortfall());
    }

    public function test_difference_requires_note(): void
    {
        $this->collect('C-DEP-I', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 90000,
        ])->assertSessionHasErrors('deposit');

        $this->assertSame(DepositStatus::MENUNGGU_VERIFIKASI, $deposit->fresh()->status);
    }

    /**
     * Skenario "peran rangkap": user ber-permission verifikasi yang sekaligus
     * jadi penyetor setoran itu. Kalau dia boleh menutup setorannya sendiri,
     * cross check cuma jadi tanda tangan di atas kertas sendiri — dia mengontrol
     * kedua sisi persamaan (uang fisik & angka yang dibandingkan).
     */
    public function test_verifier_cannot_be_the_depositing_collector(): void
    {
        $this->collect('C-DEP-J', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();

        // Penyetornya adalah admin yang sekarang mencoba memverifikasi.
        $deposit->update(['collector_id' => $this->admin->id]);

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 100000,
        ])->assertSessionHasErrors('deposit');

        $this->assertSame(DepositStatus::MENUNGGU_VERIFIKASI, $deposit->fresh()->status);
    }

    public function test_admin_from_other_pop_cannot_verify(): void
    {
        $otherPop = $this->createPop('DEP2');
        $otherAdmin = $this->createUser('pop_admin', $otherPop);

        $this->collect('C-DEP-K', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();

        $this->actingAs($otherAdmin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 100000,
        ])->assertSessionHasErrors('deposit');

        $this->assertSame(DepositStatus::MENUNGGU_VERIFIKASI, $deposit->fresh()->status);
    }

    public function test_deposit_cannot_be_verified_twice(): void
    {
        $this->collect('C-DEP-L', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), ['declared_amount' => 100000]);
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), ['declared_amount' => 999999])
            ->assertSessionHasErrors('deposit');

        $this->assertEquals(100000, (float) $deposit->fresh()->declared_amount);
    }

    // ================= PELUNASAN SELISIH =================

    /**
     * Uang pelunasan WAJIB lewat field sendiri. Kalau dilebur ke `declared`,
     * setoran kedua tercatat "lebih setor" dan lahir selisih baru yang
     * menggantung — laporan selisih tak pernah nol (§11.5).
     */
    public function test_shortfall_is_settled_by_next_deposit(): void
    {
        // Hari 1: kurang 30rb.
        $this->collect('C-DEP-M', 350000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $first = CollectorDeposit::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $first->id), [
            'declared_amount' => 320000,
            'note' => 'Kurang 30rb.',
        ]);
        $this->assertSame(30000.0, $this->shortfall());

        // Hari 2: tagih 280rb, setor fisik 310rb (280rb + pelunasan 30rb).
        $this->collect('C-DEP-N', 280000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $second = CollectorDeposit::query()->where('id', '!=', $first->id)->firstOrFail();

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $second->id), [
            'declared_amount' => 310000,
            'settles_deposit_id' => $first->id,
            'settlement_amount' => 30000,
        ])->assertRedirect();

        $second->refresh();
        $first->refresh();

        $this->assertSame(DepositStatus::TERVERIFIKASI, $second->status);
        $this->assertEquals(0, (float) $second->difference);
        $this->assertSame(DepositStatus::SELISIH_LUNAS, $first->status);
        $this->assertSame(0.0, $this->shortfall());
    }

    public function test_partial_settlement_keeps_remaining_shortfall_visible(): void
    {
        $this->collect('C-DEP-O', 350000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $first = CollectorDeposit::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $first->id), [
            'declared_amount' => 320000,
            'note' => 'Kurang 30rb.',
        ]);

        $this->collect('C-DEP-P', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $second = CollectorDeposit::query()->where('id', '!=', $first->id)->firstOrFail();

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $second->id), [
            'declared_amount' => 110000,
            'settles_deposit_id' => $first->id,
            'settlement_amount' => 10000,
        ]);

        $first->refresh();
        $this->assertSame(DepositStatus::SELISIH, $first->status);
        $this->assertSame(20000.0, $first->outstandingShortfall());
        $this->assertSame(20000.0, $this->shortfall());
    }

    public function test_settlement_cannot_exceed_outstanding_shortfall(): void
    {
        $this->collect('C-DEP-Q', 350000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $first = CollectorDeposit::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $first->id), [
            'declared_amount' => 320000,
            'note' => 'Kurang 30rb.',
        ]);

        $this->collect('C-DEP-R', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $second = CollectorDeposit::query()->where('id', '!=', $first->id)->firstOrFail();

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $second->id), [
            'declared_amount' => 200000,
            'settles_deposit_id' => $first->id,
            'settlement_amount' => 100000,
        ])->assertSessionHasErrors('deposit');

        $this->assertSame(30000.0, $this->shortfall());
    }

    /**
     * Regresi (review #6): lebih setor dulu ikut berstatus `SELISIH` padahal
     * `outstandingShortfall()`-nya 0 — worklist kolektor menampilkan badge
     * merah permanen "Kurang setor Rp0", setoran itu tak bisa dipilih untuk
     * pelunasan, dan satu-satunya jalan keluar adalah hapus buku bernilai nol.
     * Sekarang punya status sendiri dan terminal: uangnya dikembalikan fisik.
     */
    public function test_over_deposit_gets_its_own_terminal_status(): void
    {
        $this->collect('C-DEP-LEBIH', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 130000,
            'note' => 'Kolektor menyerahkan lebih, kembalian dikembalikan tunai.',
        ])->assertRedirect();

        $deposit->refresh();
        $this->assertSame(DepositStatus::LEBIH_SETOR, $deposit->status);
        $this->assertEquals(30000, (float) $deposit->difference);

        // Bukan kewajiban kolektor, jadi tak boleh muncul di angka kurang setor
        // maupun daftar yang bisa dilunasi.
        $this->assertSame(0.0, $this->shortfall());
        $this->assertTrue(app(CollectorBalanceService::class)->openShortfallDeposits($this->kolektor)->isEmpty());
    }

    public function test_over_deposit_cannot_be_written_off(): void
    {
        $this->collect('C-DEP-LEBIH2', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();

        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 130000,
            'note' => 'Lebih 30rb.',
        ]);

        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id, 'status' => 'active']);

        $this->actingAs($owner)->post(route('collector-deposits.write-off', $deposit->id), [
            'write_off_reason' => 'Coba hapus buku padahal lebih setor.',
        ])->assertSessionHasErrors('deposit');

        $this->assertSame(DepositStatus::LEBIH_SETOR, $deposit->fresh()->status);
    }

    /**
     * Regresi (review #5): assertion pelunasan dulu hanya berjalan di luar
     * transaksi, pada baris tak terkunci. Kalau setoran target dihapus-buku di
     * sela pemeriksaan dan transaksi, uang pelunasan masuk ke baris
     * `DIHAPUS_BUKU` yang `outstandingShortfall()`-nya selalu 0 — uangnya
     * lenyap dari semua laporan tanpa satu pun error.
     */
    public function test_settlement_is_revalidated_against_the_locked_row(): void
    {
        // Hari 1: kurang 30rb.
        $this->collect('C-DEP-RACE-A', 350000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $first = CollectorDeposit::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $first->id), [
            'declared_amount' => 320000,
            'note' => 'Kurang 30rb.',
        ]);

        // Hari 2: setoran baru menunggu verifikasi.
        $this->collect('C-DEP-RACE-B', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $second = CollectorDeposit::query()->where('id', '!=', $first->id)->firstOrFail();

        // Di sela itu, Owner menghapus-buku selisih hari 1.
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id, 'status' => 'active']);
        $this->actingAs($owner)->post(route('collector-deposits.write-off', $first->id), [
            'write_off_reason' => 'Kolektor resign.',
        ])->assertRedirect();

        // Admin tetap mengirim form lamanya yang masih memuat pelunasan 30rb.
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $second->id), [
            'declared_amount' => 130000,
            'settles_deposit_id' => $first->id,
            'settlement_amount' => 30000,
        ])->assertSessionHasErrors('deposit');

        // Uangnya tidak boleh mendarat di baris yang sudah dihapus buku.
        $this->assertSame(DepositStatus::DIHAPUS_BUKU, $first->fresh()->status);
        $this->assertEquals(0, (float) $first->fresh()->settled_amount);
        $this->assertSame(DepositStatus::MENUNGGU_VERIFIKASI, $second->fresh()->status);
    }

    /**
     * Regresi (review lanjutan): notifikasi dulu dikirim DI DALAM transaksi
     * setor & verifikasi. Dua akibatnya sama buruk — broadcast mati membuat
     * kolektor tak bisa menyerahkan uangnya sama sekali, dan kalau dispatch
     * sempat berhasil lalu transaksi rollback, admin menerima kabar setoran
     * yang tak pernah ada.
     *
     * Pelajaran yang sama dengan review #2, tapi di jalur setoran — pola yang
     * sempat lolos karena perbaikan pertama cuma menyasar jalur pembayaran.
     */
    public function test_notification_failure_blocks_neither_deposit_nor_verification(): void
    {
        $this->collect('C-DEP-NOTIF', 100000);

        $this->app->bind(NotificationDispatcher::class, fn () => new class implements NotificationDispatcher
        {
            public function send($notifiables, $notification)
            {
                throw new \RuntimeException('Broadcast mati.');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new \RuntimeException('Broadcast mati.');
            }
        });

        $this->actingAs($this->kolektor)
            ->post(route('collector-worklist.deposit'))
            ->assertSessionHasNoErrors();

        $deposit = CollectorDeposit::query()->firstOrFail();
        $this->assertSame(100000.0, $deposit->computedAmount());
        $this->assertSame(0.0, $this->balance());

        $this->actingAs($this->admin)
            ->post(route('collector-deposits.verify', $deposit->id), ['declared_amount' => 100000])
            ->assertSessionHasNoErrors();

        $this->assertSame(DepositStatus::TERVERIFIKASI, $deposit->fresh()->status);
    }

    // ================= HAPUS BUKU =================

    public function test_only_owner_can_write_off_shortfall(): void
    {
        $this->collect('C-DEP-S', 350000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 320000,
            'note' => 'Kurang 30rb.',
        ]);

        // Admin yang menemukan selisih tidak boleh menutup kerugiannya sendiri.
        $this->actingAs($this->admin)->post(route('collector-deposits.write-off', $deposit->id), [
            'write_off_reason' => 'Dianggap kerugian.',
        ])->assertForbidden();

        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id, 'status' => 'active']);
        $this->actingAs($owner)->post(route('collector-deposits.write-off', $deposit->id), [
            'write_off_reason' => 'Kolektor resign, kerugian diakui.',
        ])->assertRedirect();

        $deposit->refresh();
        $this->assertSame(DepositStatus::DIHAPUS_BUKU, $deposit->status);
        $this->assertSame(0.0, $this->shortfall());
    }

    /**
     * Regresi (review #7): `writeOff()` dulu tanpa guard POP sama sekali,
     * sementara `verify()` punya. Aman hanya selama `collector_worksheet.approve`
     * cuma dipegang Owner — padahal permission-nya bisa diberikan ke role
     * ber-scope lewat Role Matrix kapan saja. Menutup kerugian adalah kewenangan
     * yang LEBIH besar dari memverifikasi, jadi guard-nya tak boleh lebih longgar.
     */
    public function test_write_off_is_blocked_for_approver_outside_the_deposit_pop(): void
    {
        $this->collect('C-DEP-WOSCOPE', 350000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 320000,
            'note' => 'Kurang 30rb.',
        ]);

        // Punya permission approve, tapi scope-nya POP lain.
        $otherPop = $this->createPop('DEP9');
        $approverLuar = $this->createUser('pop_admin', $otherPop);
        $approverLuar->role->permissions()->syncWithoutDetaching(
            Permission::where('code', 'collector_worksheet.approve')->pluck('id')->all()
        );
        app(EffectiveAccessService::class)->clearCache($approverLuar);

        $this->actingAs($approverLuar)->post(route('collector-deposits.write-off', $deposit->id), [
            'write_off_reason' => 'Hapus buku dari cabang lain.',
        ])->assertSessionHasErrors('deposit');

        $this->assertSame(DepositStatus::SELISIH, $deposit->fresh()->status);
        $this->assertSame(30000.0, $this->shortfall());
    }

    // ================= GUARD PAYMENT & USER =================

    public function test_payment_inside_verified_deposit_cannot_be_rejected(): void
    {
        $this->collect('C-DEP-T', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), ['declared_amount' => 100000]);

        $payment = Payment::query()->firstOrFail();
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id, 'status' => 'active']);

        $this->actingAs($owner)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Mau dikoreksi.',
        ])->assertSessionHasErrors('reject_reason');

        $this->assertSame(PaymentStatus::VALID, $payment->fresh()->payment_status);
    }

    /** Sebelum verifikasi masih boleh — setoran belum jadi dokumen sepakat. */
    public function test_payment_inside_pending_deposit_can_still_be_rejected(): void
    {
        $this->collect('C-DEP-U', 100000);
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));

        $payment = Payment::query()->firstOrFail();
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id, 'status' => 'active']);

        $this->actingAs($owner)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Uang tidak diterima.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(PaymentStatus::DITOLAK, $payment->fresh()->payment_status);
    }

    public function test_collector_holding_cash_or_shortfall_cannot_be_deactivated(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id, 'status' => 'active']);

        // Pegang saldo.
        $this->collect('C-DEP-V', 100000);
        Customer::where('collector_id', $this->kolektor->id)->update(['collector_id' => null]);

        $payload = [
            'name' => $this->kolektor->name,
            'email' => $this->kolektor->email,
            'status' => 'inactive',
            'role_id' => $this->kolektor->role_id,
        ];

        $this->actingAs($owner)->put(route('users.update', $this->kolektor->id), $payload)
            ->assertSessionHasErrors('status');

        // Setor & verifikasi dengan kurang setor → tetap tak boleh nonaktif.
        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'));
        $deposit = CollectorDeposit::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('collector-deposits.verify', $deposit->id), [
            'declared_amount' => 70000,
            'note' => 'Kurang 30rb.',
        ]);

        $this->actingAs($owner)->put(route('users.update', $this->kolektor->id), $payload)
            ->assertSessionHasErrors('status');

        $this->assertSame(UserStatus::ACTIVE, $this->kolektor->fresh()->status);
    }
}
