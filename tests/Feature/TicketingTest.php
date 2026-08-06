<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketBucket;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\District;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
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

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

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
            'scope_type' => ScopeType::ALL_POP->value,
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

    /**
     * FopTask sekarang gak lagi auto-dibuat pas tiket disubmit — baru
     * kebentuk begitu Helpdesk/NOC eksplisit "Kirim ke FOP" (lihat
     * TicketService::escalateToFop()). Test lama yang butuh FopTask buat
     * ngetes status/bucket turunannya wajib eskalasi dulu lewat helper ini.
     */
    private function escalateTicketToFop(Ticket $ticket): Ticket
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        return $ticket->fresh()->load('fopTask');
    }

    public function test_guests_cannot_access_ticketing(): void
    {
        $this->get(route('tickets.selesai'))->assertRedirect('/login');
    }

    public function test_role_without_permission_cannot_access_ticketing(): void
    {
        $this->actingAs($this->teknisiUser)
            ->get(route('tickets.selesai'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_ticketing_index(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.selesai'))
            ->assertOk()
            ->assertSee('Ticket Selesai');
    }

    public function test_new_ticket_is_not_auto_synced_to_fop(): void
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

        // Inti perubahan: lahir di tangan Helpdesk, BELUM ada FopTask kembar.
        $this->assertSame(TicketHandler::HELPDESK, $ticket->handler);
        $this->assertSame(TicketHandlingStatus::OPEN, $ticket->status);
        $this->assertNull($ticket->fop_task_id);
        $this->assertNull($ticket->fopTask);
    }

    public function test_escalating_ticket_to_fop_creates_synced_fop_task(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload())
            ->assertRedirect();

        $ticket = $this->escalateTicketToFop(Ticket::first());

        // Inti fitur: satu tiket dieskalasi = satu FopTask kembar di halaman Task FOP.
        $fopTask = $ticket->fopTask;

        $this->assertNotNull($fopTask, 'Escalate ke FOP harus membuat FopTask.');
        $this->assertSame(TicketHandler::FOP, $ticket->handler);
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

        $ticket = $this->escalateTicketToFop(Ticket::first());

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

        $ticket = $this->escalateTicketToFop(Ticket::first());
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

    public function test_customer_lookup_supports_search_by_phone_number(): void
    {
        $this->customer->update([
            'primary_phone' => '089876543210',
        ]);

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.lookup-customer', ['q' => '089876543210']))
            ->assertOk();

        $payload = $response->json();

        $this->assertCount(1, $payload);
        $this->assertSame('Budi Santoso', $payload[0]['nama']);
        $this->assertSame('089876543210', $payload[0]['no_hp']);
    }

    public function test_ticket_survives_fop_task_deletion_and_reports_terputus(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = $this->escalateTicketToFop(Ticket::first());
        $ticket->fopTask->delete();

        $ticket->refresh()->load('fopTask');

        $this->assertNull($ticket->fop_task_id);
        $this->assertSame('Terputus', $ticket->statusLabel());
    }

    public function test_ticket_status_follows_fop_task_status(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = $this->escalateTicketToFop(Ticket::first());
        $ticket->fopTask->update(['status' => TaskStatus::SELESAI]);

        $this->assertSame('Selesai', $ticket->refresh()->load('fopTask')->statusLabel());
    }

    /**
     * Sebelum eskalasi, status turunan bukan lagi dari FopTask — masih di
     * tangan Helpdesk/NOC.
     */
    public function test_ticket_status_label_reflects_internal_handler_before_escalation(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());
        $ticket = Ticket::first();

        $this->assertSame('Ditangani Helpdesk', $ticket->statusLabel());

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        // Diassign ke NOC = langsung diproses (ADHOC-06). Label "Pending NOC"
        // & "OnCheck NOC" sudah dihapus — lihat TicketOnCheckNocTest.
        $this->assertSame('Diproses NOC', $ticket->refresh()->statusLabel());
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

    /**
     * Bucket Masuk gak lagi punya halaman list sendiri (Masuk & Diproses
     * pindah jadi tab Worksheet NOC — dan itu cuma buat tiket handler=NOC).
     * Tiket baru handler=Helpdesk kelihatannya di panel "List Task Ticketing"
     * tab Ticket, jadi yang dites di sini: klasifikasi bucket-nya benar DAN
     * dia gak bocor ke halaman arsip.
     */
    public function test_new_ticket_lands_in_ticket_masuk_bucket(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = Ticket::first();

        $this->assertNull($ticket->fopTask);
        $this->assertSame(TicketBucket::MASUK, $ticket->bucket());

        // Flash 'success' dari store() nyebut nomor tiket — kalau gak dibuang,
        // dia ke-render di GET berikutnya dan bikin assertDontSee() salah alarm.
        $this->flushSession();

        foreach (['tickets.selesai', 'tickets.dibatalkan'] as $archiveRoute) {
            $this->actingAs($this->helpdeskUser)
                ->get(route($archiveRoute))
                ->assertOk()
                ->assertDontSee($ticket->ticket_number);
        }
    }

    #[DataProvider('diprosesStatusProvider')]
    public function test_assigned_ticket_moves_to_diproses_bucket(string $status): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = $this->escalateTicketToFop(Ticket::first());
        $ticket->fopTask->update(['status' => $status]);

        $this->assertSame(TicketBucket::DIPROSES, $ticket->fresh()->load('fopTask')->bucket());

        // Lihat catatan flushSession() di test bucket Masuk di atas.
        $this->flushSession();

        // Belum kelar — gak boleh nongol di arsip mana pun.
        foreach (['tickets.selesai', 'tickets.dibatalkan'] as $archiveRoute) {
            $this->actingAs($this->helpdeskUser)
                ->get(route($archiveRoute))
                ->assertOk()
                ->assertDontSee($ticket->ticket_number);
        }
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

        $ticket = $this->escalateTicketToFop(Ticket::first());
        $ticket->fopTask->update(['status' => TaskStatus::SELESAI]);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.selesai'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }

    /**
     * Bucket Selesai juga dicapai lewat jalur internal (tanpa FOP sama
     * sekali) — Skenario A worksheet, Helpdesk selesaikan sendiri.
     */
    public function test_internally_closed_ticket_moves_to_selesai_bucket(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());
        $ticket = Ticket::first();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket), ['reason' => 'Sudah dibantu reset ONT.'])
            ->assertRedirect();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.selesai'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);

        $this->assertNull($ticket->fresh()->fopTask);
    }

    public function test_cancelled_ticket_moves_to_dibatalkan_bucket(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = $this->escalateTicketToFop(Ticket::first());
        $ticket->fopTask->update(['status' => TaskStatus::DIBATALKAN]);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.dibatalkan'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }

    /**
     * Tiket Terputus gak punya status buat dicocokin — tanpa penampung, dia
     * ilang dari keempat submenu. Cuma berlaku buat tiket yang UDAH pernah
     * ke FOP (handler=fop) terus FopTask-nya kehapus — beda dari tiket yang
     * memang belum pernah dieskalasi sama sekali.
     */
    public function test_orphaned_ticket_appears_in_dibatalkan_bucket(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());

        $ticket = $this->escalateTicketToFop(Ticket::first());
        $ticket->fopTask->delete();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.dibatalkan'))
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

    /**
     * Worksheet Helpdesk (docs/plan/RANCANGAN_MASTER_ISSUE_TICKETING.md Task 2)
     * — dropdown kategori dari Master Issue aktif, panel kanan List Task
     * Ticketing keisi snapshot tiket yang udah ada.
     */
    public function test_new_ticket_page_shows_active_issue_categories_and_worksheet_panel(): void
    {
        TicketIssueCategory::create([
            'name' => 'Backbone CUT',
            'default_priority' => 'High',
            'sla_source' => 'prioritas',
            'is_active' => true,
        ]);

        TicketIssueCategory::create([
            'name' => 'Kategori Nonaktif',
            'default_priority' => 'low',
            'sla_source' => 'prioritas',
            'is_active' => false,
        ]);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload());

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.create'));

        $response->assertOk()
            // Panel kanan sekarang tabel (Frame 139) — judul "List Task Ticketing"
            // diganti header kolom + tab; header kolom ini penanda panelnya render.
            ->assertSee('Quick Dispatch Actions')
            ->assertSee('Backbone CUT')
            ->assertDontSee('Kategori Nonaktif');

        // Tiket yang udah dibuat sebelumnya nampil di panel kanan (initial load server-side).
        $response->assertSee(Ticket::first()->ticket_number);
    }

    /**
     * Panel kanan worksheet = tabel padat (Frame 139): 6 kolom tetap + tab
     * per-handler + filter prioritas. Header kolom & kontrolnya dirender
     * server-side, jadi bisa dites tanpa browser.
     */
    public function test_worksheet_panel_renders_dense_table_layout(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload());

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('Ticket ID &amp; Time', false)
            ->assertSee('Status / Issue')
            ->assertSee('Pelanggan (CID &amp; Contact)', false)
            ->assertSee('Lokasi / POP / ODP')
            ->assertSee('Keluhan (Detail)')
            ->assertSee('Quick Dispatch Actions')
            ->assertSee('Semua Prioritas');
    }

    /**
     * Kolom "Lokasi / POP / ODP" + jam absolut + label status di tabel
     * worksheet butuh field baru di payload kartu. Bentuk payload ini dipakai
     * initial load DAN respons aksi AJAX — kalau salah satu field ilang,
     * kolomnya kosong tanpa error, jadi dikunci di test.
     */
    public function test_worksheet_payload_includes_location_and_status_fields(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload());

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.worksheet-tasks'));

        $response->assertOk();
        $response->assertJsonStructure([
            'total',
            'tasks' => [['id', 'code', 'time', 'time_at', 'pop', 'odp', 'address', 'status_label']],
        ]);
        $response->assertJsonPath('tasks.0.pop', 'POP Polorejo');
        $response->assertJsonPath('tasks.0.status_label', 'Ditangani Helpdesk');
    }

    /**
     * Submit mode AJAX (rancangan bagian D) — fetch() JSON, stay-on-page,
     * BUKAN PRG redirect. TicketController@store bercabang wantsJson().
     */
    public function test_ajax_submit_returns_json_and_stays_on_page(): void
    {
        $category = TicketIssueCategory::create([
            'name' => 'LOS',
            'default_priority' => 'Medium',
            'sla_source' => 'prioritas',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.store'), $this->validPayload([
                'issue_category_id' => $category->id,
            ]));

        $response->assertCreated();
        $response->assertJsonPath('ticket.title', 'LOS');
        $response->assertJsonPath('ticket.bucket', 'masuk');
        $response->assertJsonStructure(['ticket' => ['id', 'code', 'priority', 'title', 'desc', 'time', 'cid', 'bucket', 'fop_task_number']]);

        $ticket = Ticket::first();
        $this->assertSame($category->id, $ticket->issue_category_id);
    }

    /**
     * 422 dari AJAX submit harus tetap JSON (bukan redirect balik ke form
     * dengan session errors) — form worksheet nangkep field errors lewat body.
     */
    public function test_ajax_submit_returns_json_validation_errors(): void
    {
        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.store'), $this->validPayload(['detail_keluhan' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['detail_keluhan']);
    }

    /**
     * "Lainnya (isi manual)" — issue_category_id null tetap valid, detail_keluhan
     * satu-satunya sumber klasifikasi (rancangan bagian C).
     */
    public function test_ticket_without_issue_category_is_still_valid(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload())
            ->assertRedirect();

        $this->assertNull(Ticket::first()->issue_category_id);
    }

    public function test_index_can_filter_to_own_tickets(): void
    {
        $otherHelpdesk = $this->makeHelpdesk();

        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->validPayload());
        $mine = Ticket::latest('id')->first();

        $this->actingAs($otherHelpdesk)->post(route('tickets.store'), $this->validPayload([
            'detail_keluhan' => 'Keluhan dari user lain.',
        ]));
        $theirs = Ticket::latest('id')->first();

        $this->assertSame(2, Ticket::count());

        // Filter "Ticket Saya" hidup di halaman arsip — dua tiket ditutup dulu
        // biar masuk ke sana (bucket Masuk gak punya halaman list lagi).
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $mine))->assertOk();
        $this->actingAs($otherHelpdesk)->postJson(route('tickets.close', $theirs))->assertOk();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.selesai', ['mine' => 1]))
            ->assertOk()
            ->assertSee('Internet mati total sejak pagi.')
            ->assertDontSee('Keluhan dari user lain.');
    }

    // ── Snapshot data pelanggan (auto-fill dari CID) ─────────────

    public function test_ticket_snapshots_full_customer_panel_at_creation(): void
    {
        $package = InternetPackage::create([
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

        CustomerDevice::create([
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

        CustomerDevice::create([
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
