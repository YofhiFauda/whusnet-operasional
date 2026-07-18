<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketBucket;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketingTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;
    private User $teknisiUser;
    private Customer $customer;
    private Pop $pop;
    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\TicketFeatureSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $helpdeskRole = Role::where('code', 'helpdesk')->first();
        $teknisiRole = Role::where('code', 'teknisi')->first();

        $this->helpdeskUser = $this->makeHelpdesk();
        $this->teknisiUser = User::factory()->create(['role_id' => $teknisiRole->id]);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create([
            'district_id' => $district->id,
            'name' => 'Polorejo',
            'postal_code' => '63491',
        ]);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);

        $this->customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'full_name' => 'Budi Santoso',
        ]);
    }

    /**
     * Helpdesk ber-scope ALL_POP — tanpa scope, HasPopScope menolak semua baris
     * dan setiap lookup pelanggan jatuh ke 404.
     */
    private function makeHelpdesk(): User
    {
        $role = Role::where('code', 'helpdesk')->first();
        $user = User::factory()->create(['role_id' => $role->id]);

        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => \App\Enums\ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'catatan_teknis' => 'Redaman -28 dBm, indikasi FO putus.',
            'priority' => 'High',
        ], $overrides);
    }

    public function test_guests_cannot_access_ticketing(): void
    {
        $this->get(route('tickets.index'))->assertRedirect('/login');
    }

    public function test_role_without_permission_cannot_access_ticketing(): void
    {
        $this->actingAs($this->teknisiUser)
            ->get(route('tickets.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_ticketing_index(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Ticketing');
    }

    public function test_submitting_ticket_auto_creates_synced_fop_task(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload())
            ->assertRedirect();

        $ticket = Ticket::first();

        $this->assertNotNull($ticket);
        $this->assertSame(TaskType::MAINTENANCE, $ticket->type);
        $this->assertSame($this->helpdeskUser->id, $ticket->created_by);
        $this->assertSame($this->pop->id, $ticket->pop_id);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);

        // Inti fitur: satu tiket = satu FopTask kembar di halaman Task FOP.
        $fopTask = $ticket->fopTask;

        $this->assertNotNull($fopTask, 'Ticket harus otomatis membuat FopTask.');
        $this->assertStringStartsWith('TFOP-', $fopTask->task_number);
        $this->assertSame(TaskType::MAINTENANCE, $fopTask->category);
        $this->assertSame($this->customer->id, $fopTask->customer_id);
        $this->assertSame($this->pop->id, $fopTask->pop_id);
        $this->assertSame($this->village->id, $fopTask->village_id);
        $this->assertSame('Internet mati total sejak pagi.', $fopTask->issue);

        // Dibuat sebagai antrean mentah — penugasan teknisi tetap keputusan FOP.
        $this->assertSame(TaskStatus::DRAFT, $fopTask->status);
        $this->assertCount(0, $fopTask->technicians);
        $this->assertNull($fopTask->task_id);
    }

    /**
     * fop_tasks.notes cuma nyimpen pointer pendek asal-usul (nomor ticket +
     * pengirim) — SENGAJA gak nyalin ulang catatan_teknis ke sini. Catatan
     * teknis punya rumah sendiri yang proper di ticket->catatan_teknis,
     * ditampilkan utuh di Detail Task (section "Detail Ticket"). Nyalin ke
     * notes bikin dua sumber kebenaran yang bisa menyimpang + notes jadi blob
     * teks campur aduk, bukan catatan yang bersih.
     */
    public function test_fop_task_notes_records_only_ticket_origin_pointer(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();

        $this->assertStringContainsString($ticket->ticket_number, $ticket->fopTask->notes);
        $this->assertStringContainsString($this->helpdeskUser->name, $ticket->fopTask->notes);
        $this->assertStringNotContainsString('Redaman -28 dBm', $ticket->fopTask->notes);
    }

    /**
     * Point 1 sinkronisasi: tugas FopTask hasil auto-sync Ticketing harus
     * "{CID}_{Nama}" — konsisten sama identitas pelanggan yang dipakai di
     * seluruh sistem, bukan label tipe tiket generik.
     */
    public function test_fop_task_tugas_uses_cid_and_customer_name_format(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();
        $expectedCid = $this->customer->fresh()->display_id;

        $this->assertSame("{$expectedCid}_{$this->customer->full_name}", $ticket->fopTask->tugas);
    }

    public function test_creq_ticket_is_accepted(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload(['type' => TaskType::CREQ->value]))
            ->assertRedirect();

        $this->assertSame(TaskType::CREQ, Ticket::first()->type);
    }

    /**
     * Ticketing dibatasi MTN & C-REQ — tipe lain tetap lewat /fop-tasks.
     */
    public function test_ticket_type_outside_mtn_and_creq_is_rejected(): void
    {
        foreach ([TaskType::SURVEY, TaskType::PEMASANGAN, TaskType::OREQ, TaskType::INFR] as $type) {
            $this->actingAs($this->helpdeskUser)
                ->post(route('tickets.store'), $this->validPayload(['type' => $type->value]))
                ->assertSessionHasErrors('type');
        }

        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, FopTask::count());
    }

    public function test_ticket_requires_customer_and_complaint(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload([
                'customer_id' => null,
                'detail_keluhan' => '',
            ]))
            ->assertSessionHasErrors(['customer_id', 'detail_keluhan']);
    }

    public function test_ticket_for_customer_without_pop_is_rejected_with_readable_error(): void
    {
        $orphan = Customer::factory()->create(['pop_id' => null]);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload(['customer_id' => $orphan->id]))
            ->assertSessionHasErrors('customer_id');

        // Rollback transaksi harus bersih — gak boleh ninggalin tiket/FopTask yatim.
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, FopTask::count());
    }

    public function test_role_without_create_permission_cannot_submit_ticket(): void
    {
        $this->actingAs($this->teknisiUser)
            ->post(route('tickets.store'), $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, Ticket::count());
    }

    public function test_ticket_detail_shows_customer_data_sender_and_created_at(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertSee('Budi Santoso')
            ->assertSee($this->helpdeskUser->name)
            ->assertSee('Assigned by')
            ->assertSee('Created')
            ->assertSee('Internet mati total sejak pagi.')
            ->assertSee('Redaman -28 dBm, indikasi FO putus.');
    }

    public function test_customer_lookup_returns_full_customer_panel_data(): void
    {
        $this->customer->update([
            'address' => 'Jl. Merdeka No. 1',
            'primary_phone' => '081234567890',
            'odp_code' => 'ODP-PLR-01',
            'latitude' => -7.8681,
            'longitude' => 111.4619,
        ]);

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.lookup-customer', ['q' => 'Budi']))
            ->assertOk();

        $payload = $response->json();

        $this->assertCount(1, $payload);
        $this->assertSame('Budi Santoso', $payload[0]['nama']);
        $this->assertSame('Jl. Merdeka No. 1', $payload[0]['alamat']);
        $this->assertSame('081234567890', $payload[0]['no_hp']);
        $this->assertSame('POP Polorejo', $payload[0]['pop']);
        $this->assertSame('ODP-PLR-01', $payload[0]['odp']);
        $this->assertSame('-7.8681, 111.4619', $payload[0]['koordinat']);
        $this->assertStringContainsString('-7.8681,111.4619', $payload[0]['maps_url']);
    }

    public function test_ticket_survives_fop_task_deletion_and_reports_terputus(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();
        $ticket->fopTask->delete();

        $ticket->refresh()->load('fopTask');

        $this->assertNull($ticket->fop_task_id);
        $this->assertSame('Terputus', $ticket->statusLabel());
    }

    public function test_ticket_status_follows_fop_task_status(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();
        $ticket->fopTask->update(['status' => TaskStatus::SELESAI]);

        $this->assertSame('Selesai', $ticket->refresh()->load('fopTask')->statusLabel());
    }

    public function test_attachments_are_stored_privately_and_downloadable(): void
    {
        Storage::fake('local');

        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload([
            'attachments' => [UploadedFile::fake()->image('bukti.jpg')],
        ]));

        $ticket = Ticket::first();
        $attachment = $ticket->attachments->first();

        $this->assertNotNull($attachment);
        $this->assertSame('bukti.jpg', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.attachments.download', $attachment))
            ->assertOk();
    }

    public function test_attachment_download_is_denied_without_ticket_permission(): void
    {
        Storage::fake('local');

        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload([
            'attachments' => [UploadedFile::fake()->image('bukti.jpg')],
        ]));

        $attachment = Ticket::first()->attachments->first();

        $this->actingAs($this->teknisiUser)
            ->get(route('tickets.attachments.download', $attachment))
            ->assertForbidden();
    }

    /**
     * Kontrak submenu: tiap TaskStatus wajib punya tepat satu bucket. Kalau
     * ada status baru di TaskStatus dan lupa dipetakan, tiketnya bakal ilang
     * dari semua submenu — test ini yang nangkep duluan.
     */
    public function test_buckets_cover_every_task_status_exactly_once(): void
    {
        $mapped = [];

        foreach (TicketBucket::cases() as $bucket) {
            foreach ($bucket->statuses() as $status) {
                $this->assertArrayNotHasKey(
                    $status->value,
                    $mapped,
                    "Status {$status->value} kepetak di lebih dari satu bucket."
                );

                $mapped[$status->value] = $bucket->value;
            }
        }

        foreach (TaskStatus::cases() as $status) {
            $this->assertArrayHasKey(
                $status->value,
                $mapped,
                "Status {$status->value} gak masuk bucket mana pun — tiketnya bakal ilang dari semua submenu."
            );
        }
    }

    public function test_new_ticket_lands_in_ticket_masuk_bucket(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();

        $this->assertSame(TaskStatus::DRAFT, $ticket->fopTask->status);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'masuk'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);

        foreach (['diproses', 'selesai', 'dibatalkan'] as $otherBucket) {
            $this->actingAs($this->helpdeskUser)
                ->get(route('tickets.bucket', $otherBucket))
                ->assertOk()
                ->assertDontSee($ticket->ticket_number);
        }
    }

    #[DataProvider('diprosesStatusProvider')]
    public function test_assigned_ticket_moves_to_diproses_bucket(string $status): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();
        $ticket->fopTask->update(['status' => $status]);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'diproses'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'masuk'))
            ->assertOk()
            ->assertDontSee($ticket->ticket_number);
    }

    public static function diprosesStatusProvider(): array
    {
        return [
            'terjadwal' => [TaskStatus::TERJADWAL->value],
            'in progress' => [TaskStatus::IN_PROGRESS->value],
            'pending / lapor nanti' => [TaskStatus::PENDING->value],
        ];
    }

    public function test_completed_ticket_moves_to_selesai_bucket(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();
        $ticket->fopTask->update(['status' => TaskStatus::SELESAI]);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'selesai'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }

    public function test_cancelled_ticket_moves_to_dibatalkan_bucket(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();
        $ticket->fopTask->update(['status' => TaskStatus::DIBATALKAN]);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'dibatalkan'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }

    /**
     * Tiket Terputus gak punya status buat dicocokin — tanpa penampung, dia
     * ilang dari keempat submenu.
     */
    public function test_orphaned_ticket_appears_in_dibatalkan_bucket(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();
        $ticket->fopTask->delete();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'dibatalkan'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }

    public function test_unknown_bucket_is_rejected(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->get('/tickets/ngawur')
            ->assertNotFound();
    }

    public function test_new_ticket_page_is_reachable_and_gated(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('Create Service Ticket')
            ->assertSee('Detail Keluhan');

        $this->actingAs($this->teknisiUser)
            ->get(route('tickets.create'))
            ->assertForbidden();
    }

    public function test_index_can_filter_to_own_tickets(): void
    {
        $otherHelpdesk = $this->makeHelpdesk();

        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());
        $this->actingAs($otherHelpdesk)->post(route('tickets.store'), $this->validPayload([
            'detail_keluhan' => 'Keluhan dari user lain.',
        ]));

        $this->assertSame(2, Ticket::count());

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.index', ['mine' => 1]))
            ->assertOk()
            ->assertSee('Internet mati total sejak pagi.')
            ->assertDontSee('Keluhan dari user lain.');
    }

    // ── Snapshot data pelanggan (auto-fill dari CID) ─────────────

    public function test_ticket_snapshots_full_customer_panel_at_creation(): void
    {
        $package = \App\Models\InternetPackage::create([
            'package_code' => 'GOLD-50',
            'name' => 'Paket Gold 50Mbps',
            'category' => 'Home',
            'package_group' => 'Reguler',
            'bandwidth_label' => '50 Mbps',
            'monthly_price' => 300000,
        ]);

        $this->customer->update([
            'address' => 'Jl. Merdeka No. 1',
            'primary_phone' => '081234567890',
            'odp_code' => 'ODP-PLR-01',
            'internet_package_id' => $package->id,
            'latitude' => -7.8681,
            'longitude' => 111.4619,
        ]);

        \App\Models\CustomerDevice::create([
            'customer_id' => $this->customer->id,
            'device_type' => 'ONT',
            'brand' => 'Huawei',
            'model' => 'HG8245H',
        ]);

        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();

        $this->assertSame('Budi Santoso', $ticket->customer_name);
        $this->assertSame('Jl. Merdeka No. 1', $ticket->customer_address);
        $this->assertSame('081234567890', $ticket->customer_phone);
        $this->assertSame('ODP-PLR-01', $ticket->customer_odp);
        $this->assertSame('Paket Gold 50Mbps', $ticket->customer_package);
        $this->assertSame('Huawei HG8245H ONT', $ticket->customer_device);
        $this->assertSame('-7.8681000', (string) $ticket->customer_latitude);
        $this->assertSame('111.4619000', (string) $ticket->customer_longitude);
    }

    public function test_ticket_falls_back_to_customer_device_odp_when_denormalized_code_missing(): void
    {
        $this->customer->update(['odp_code' => null]);

        \App\Models\CustomerDevice::create([
            'customer_id' => $this->customer->id,
            'device_type' => 'ONT',
            'odp' => 'ODP-FROM-DEVICE',
        ]);

        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $this->assertSame('ODP-FROM-DEVICE', Ticket::first()->customer_odp);
    }

    /**
     * Kalau field-nya kosong di sumbernya (belum diisi admin), snapshot ikut
     * kosong (null) — bukan error, bukan dipaksa string placeholder.
     * `phone` sendiri wajib diisi di skema customers (beda dari
     * `primary_phone` yang opsional), jadi customer_phone tetap fallback ke
     * situ, bukan ikut null.
     */
    public function test_ticket_snapshot_tolerates_missing_optional_customer_fields(): void
    {
        $bare = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'address' => null,
            'primary_phone' => null,
            'odp_code' => null,
            'internet_package_id' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload(['customer_id' => $bare->id]))
            ->assertRedirect();

        $ticket = Ticket::first();

        $this->assertNull($ticket->customer_address);
        $this->assertSame($bare->phone, $ticket->customer_phone);
        $this->assertNull($ticket->customer_odp);
        $this->assertNull($ticket->customer_package);
        $this->assertNull($ticket->customer_device);
        $this->assertNull($ticket->customer_latitude);
        $this->assertNull($ticket->customer_longitude);
    }

    /**
     * Inti fitur: snapshot BEKU. Kalau data pelanggan berubah SETELAH ticket
     * dibuat (pindah alamat, ganti paket), riwayat ticket yang sudah ada TETAP
     * menampilkan kondisi saat keluhan dilaporkan — bukan data pelanggan
     * terkini.
     */
    public function test_ticket_snapshot_does_not_change_after_customer_data_is_updated_later(): void
    {
        $this->customer->update(['address' => 'Alamat Lama']);

        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());
        $ticket = Ticket::first();

        $this->assertSame('Alamat Lama', $ticket->customer_address);

        $this->customer->update(['address' => 'Alamat Baru Setelah Pindah']);

        $this->assertSame('Alamat Lama', $ticket->refresh()->customer_address);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Alamat Lama')
            ->assertDontSee('Alamat Baru Setelah Pindah');
    }

    public function test_ticket_detail_shows_maps_link_from_snapshot_coordinates(): void
    {
        $this->customer->update(['latitude' => -7.8681, 'longitude' => 111.4619]);

        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());
        $ticket = Ticket::first();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('www.google.com/maps/search', false)
            ->assertSee('-7.8681000, 111.4619000');
    }
}
