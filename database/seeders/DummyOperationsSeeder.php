<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Lanjutan `DummyScaleSeeder`: permintaan stok gudang, tiket, FOP task, dan task
 * teknisi di atas pelanggan dummy yang SUDAH ada. Dipanggil otomatis di akhir
 * `DummyScaleSeeder`; bisa juga dijalankan sendiri
 * (`DUMMY_TICKETS=N php artisan db:seed --class=DummyOperationsSeeder`).
 *
 * Penanda purge (dibersihkan `DummyScaleSeeder::purge()`, bukan di sini): tiket /
 * FOP task / task menempel ke pelanggan dummy lewat `customer_id`, permintaan
 * stok lewat `notes LIKE [DUMMY]%`, tim harian lewat nama `DMY …`. Baris baru yang
 * tidak menempel ke salah satu penanda itu akan jadi yatim saat purge.
 *
 * Alur sinkron Ticket ↔ FopTask ↔ Task (CLAUDE.md) ditiru APA ADANYA, karena seeder
 * melewati service/observer: FopTask cuma lahir dari eskalasi ke FOP, tiket
 * `handler=fop` itu terminal, riwayat tiket sisi Ticketing hanya sampai "dieskalasi",
 * dan progres lapangan hidup di `fop_task_status_history`. Status task selalu ikut
 * `TaskStatus`; `pending` ≠ `lapor_nanti` (Lapor Nanti: kerja selesai, tidak ada
 * `completed_at`, tim tetap menempel).
 */
class DummyOperationsSeeder extends Seeder
{
    private const MARK = 'DUMMY-SEED';

    private const CHUNK = 1000;

    /** @var array<string, int> */
    private array $nextId = [];

    /** @var array<string, int> */
    private array $docCounters = [];

    /** @var array<string, int> */
    private array $teamIds = [];

    /** @var array<string, mixed> */
    private array $ref = [];

    public function run(): void
    {
        ini_set('memory_limit', '1G');
        DB::connection()->disableQueryLog();
        mt_srand(20261029);

        $billed = DB::table('customers')->where('registered_by_name', self::MARK)->whereIn('status', ['active', 'suspended'])->count();
        if ($billed === 0) {
            $this->command?->error('Pelanggan dummy belum ada — jalankan DummyScaleSeeder dulu.');

            return;
        }

        $alreadySeeded = DB::table('tickets')->join('customers', 'customers.id', '=', 'tickets.customer_id')
            ->where('customers.registered_by_name', self::MARK)->exists();
        if ($alreadySeeded && ! getenv('DUMMY_RESET')) {
            $this->command?->info('Tiket dummy sudah ada — skip operasional. Jalankan dengan DUMMY_RESET=1 untuk membersihkan & seed ulang (mis. setelah run terputus).');

            return;
        }

        if (! $this->loadReferences()) {
            return;
        }

        $this->purgePreviousDummyOperations();

        $ticketTotal = (int) (getenv('DUMMY_TICKETS') ?: intdiv($billed * 25, 100));

        $this->seedStaff();
        $this->seedStockRequests();
        $this->seedTicketsAndTasks($ticketTotal);
        $this->seedCustomerLifecycleTasks();
        $this->syncSequences();

        $this->command?->info('Operasional dummy selesai.');
    }

    private function loadReferences(): bool
    {
        $roles = DB::table('roles')->pluck('id', 'code');
        $branches = DB::table('pops')->where('type', 'cabang')->where('status', 'active')->orderBy('id')->get()
            ->map(fn ($pop) => ['pop' => $pop, 'mini' => DB::table('pops')->where('parent_id', $pop->id)->where('type', 'mini_pop')->orderBy('id')->first()])
            ->filter(fn ($b) => $b['mini'])->values();

        $technicians = [];
        $pics = [];
        foreach ($branches as $b => $branch) {
            $suffix = strtolower($branch['pop']->code);
            $technicians[$b] = DB::table('users')->where('email', 'like', "dummy.teknisi.{$suffix}%@whusnet.test")->orderBy('id')->pluck('id')->all();
            $pics[$b] = DB::table('users')->where('email', 'like', "dummy.pic_gudang.{$suffix}%@whusnet.test")->orderBy('id')->value('id');
        }

        if ($branches->isEmpty() || collect($technicians)->contains(fn ($t) => $t === []) || ! $roles->has('fop') || ! $roles->has('noc') || ! $roles->has('helpdesk')) {
            $this->command?->error('Staf dummy / role fop-noc-helpdesk belum lengkap — jalankan DummyScaleSeeder dulu.');

            return false;
        }

        $this->ref = [
            'roles' => $roles,
            'branches' => $branches,
            'technicians' => $technicians,
            'pics' => $pics,
            'categories' => DB::table('ticket_issue_categories')->where('is_active', 1)->get(),
            'pusat' => DB::table('pops')->where('type', 'pusat')->value('id'),
            'items' => DB::table('items')->leftJoin('item_categories', 'item_categories.id', '=', 'items.item_category_id')
                ->where('items.is_active', 1)->get(['items.id', 'items.code', 'items.name', 'items.unit', 'items.tracking_type', 'item_categories.code as category_code']),
        ];

        return true;
    }

    // ───────────────────────── staf ─────────────────────────

    /** FOP per cabang, NOC & Helpdesk lintas cabang (scope selected_pop ke semua cabang + mini POP-nya). */
    private function seedStaff(): void
    {
        $hash = Hash::make('password');
        $allPops = $this->ref['branches']->flatMap(fn ($b) => [$b['pop']->id, $b['mini']->id])->all();
        $staff = ['fop' => [], 'noc' => [], 'helpdesk' => []];

        $make = function (string $role, string $email, string $name, array $popIds) use ($hash): int {
            $roleId = $this->ref['roles'][$role];
            $id = DB::table('users')->where('email', $email)->value('id') ?? DB::table('users')->insertGetId([
                'role_id' => $roleId, 'name' => $name, 'email' => $email, 'email_verified_at' => now(), 'password' => $hash,
                'phone' => '0812'.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT), 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $scopeId = DB::table('user_role_scopes')->where(['user_id' => $id, 'role_id' => $roleId])->value('id')
                ?? DB::table('user_role_scopes')->insertGetId(['user_id' => $id, 'role_id' => $roleId, 'scope_type' => 'selected_pop', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($popIds as $popId) {
                DB::table('user_role_scope_targets')->updateOrInsert(['user_role_scope_id' => $scopeId, 'pop_id' => $popId]);
            }

            return $id;
        };

        foreach ($this->ref['branches'] as $b => $branch) {
            $code = strtolower($branch['pop']->code);
            $staff['fop'][$b] = $make('fop', "dummy.fop.{$code}1@whusnet.test", "Dummy FOP {$branch['pop']->name}", [$branch['pop']->id, $branch['mini']->id]);
        }
        foreach ([1, 2] as $n) {
            $staff['noc'][] = $make('noc', "dummy.noc.{$n}@whusnet.test", "Dummy NOC {$n}", $allPops);
            $staff['helpdesk'][] = $make('helpdesk', "dummy.helpdesk.{$n}@whusnet.test", "Dummy Helpdesk {$n}", $allPops);
        }

        $this->ref['staff'] = $staff;
    }

    // ───────────────────────── permintaan stok gudang ─────────────────────────

    /**
     * Permintaan Stok Cabang → Pusat (`stock_requests`). Tidak menulis ledger: realisasi
     * pengiriman aslinya lewat transfer, sedangkan transfer dummy sudah ada dari
     * `DummyScaleSeeder`; di sini hanya antrean & riwayat keputusannya.
     */
    private function seedStockRequests(): void
    {
        // Tahap ini commit sendiri di luar transaksi tiket. Kalau run sebelumnya mati
        // di tahap tiket, permintaan stok sudah terlanjur masuk sementara guard `run()`
        // (berbasis tiket) tidak menangkapnya. Tanpa skip ini run ulang menerbitkan
        // PST-… yang sama persis (counter mulai dari 0, seed acak tetap) dan mentok
        // di unique `reference_number`.
        if (DB::table('stock_requests')->where('notes', 'like', '[DUMMY]%')->exists()) {
            $this->info('Permintaan stok dummy sudah ada — skip.');

            return;
        }

        $requestId = $this->takeId('stock_requests');
        $itemId = $this->takeId('stock_request_items');
        $items = $this->ref['items']->whereIn('tracking_type', ['quantity', 'serialized'])->values();
        $requests = [];
        $lines = [];
        $reasons = ['Stok menipis, mohon kirim', 'Persiapan pemasangan baru bulan depan', 'Pengganti perangkat rusak', 'Kebutuhan perluasan ODP', 'Stok minimum terlewati'];

        foreach ($this->ref['branches'] as $b => $branch) {
            for ($n = 1; $n <= 80; $n++) {
                $created = Carbon::now()->subDays(mt_rand(0, 120))->setTime(mt_rand(8, 16), mt_rand(0, 59));
                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $roll <= 20 => 'pending',
                    $roll <= 35 => 'partial',
                    $roll <= 75 => 'fulfilled',
                    $roll <= 85 => 'rejected',
                    default => 'cancelled',
                };
                $decided = $status === 'pending' ? null : $created->copy()->addHours(mt_rand(2, 48));
                $ymd = $created->format('Ymd');
                $refNumber = $this->nextDocNumber("LEDGER:PST:{$ymd}", 'PST', $ymd, 'stock_requests', 'reference_number');
                $id = $requestId++;

                $requests[] = [
                    'id' => $id, 'reference_number' => $refNumber,
                    'cabang_pop_id' => $branch['pop']->id, 'status' => $status, 'notes' => '[DUMMY] '.$reasons[$n % 5],
                    'requested_by' => $this->ref['pics'][$b] ?? 1, 'decided_by' => $decided ? 1 : null, 'decided_at' => $decided,
                    'decision_notes' => $status === 'rejected' ? 'Stok pusat tidak mencukupi' : ($status === 'cancelled' ? 'Dibatalkan peminta' : null),
                    'created_at' => $created, 'updated_at' => $decided ?? $created,
                ];

                foreach ($items->random(mt_rand(3, 6)) as $item) {
                    $qty = $item->tracking_type === 'serialized' ? mt_rand(2, 30) : mt_rand(20, 300);
                    $fulfilled = match ($status) {
                        'fulfilled' => $qty,
                        'partial' => (int) floor($qty * mt_rand(30, 80) / 100),
                        default => 0,
                    };
                    $lines[] = [
                        'id' => $itemId++, 'stock_request_id' => $id, 'item_id' => $item->id, 'qty_requested' => $qty, 'qty_fulfilled' => $fulfilled,
                        'lot_no' => null, 'created_at' => $created, 'updated_at' => $decided ?? $created,
                    ];
                }
            }
        }

        $this->bulk('stock_requests', $requests);
        $this->bulk('stock_request_items', $lines);
        $this->info('Permintaan stok: '.count($requests).' permintaan, '.count($lines).' baris barang.');
    }

    // ───────────────────────── tiket → FOP task → task teknisi ─────────────────────────

    private function seedTicketsAndTasks(int $total): void
    {
        $ids = [];
        foreach (['tickets', 'fop_tasks', 'tasks', 'task_teams', 'fop_task_teams'] as $t) {
            $ids[$t] = $this->takeId($t);
        }

        $billed = DB::table('customers')->where('registered_by_name', self::MARK)->whereIn('status', ['active', 'suspended'])->count();
        $step = max(1, intdiv($billed, max(1, $total)));
        $customers = DB::table('customers as c')
            ->join('customer_services as s', 's.customer_id', '=', 'c.id')
            ->leftJoin('villages as v', 'v.id', '=', 'c.village_id')
            ->where('c.registered_by_name', self::MARK)->whereIn('c.status', ['active', 'suspended'])
            ->whereRaw('c.id % ? = 0', [$step])->orderBy('c.id')->limit($total)
            ->get(['c.id', 'c.cid', 'c.customer_code', 'c.full_name', 'c.address', 'c.primary_phone', 'c.odp_code', 'c.ont_sn', 'c.pop_id', 'c.latitude', 'c.longitude', 'c.village_id', 'v.name as village', 's.package_name_snapshot']);

        $branchByPop = [];
        foreach ($this->ref['branches'] as $index => $branch) {
            $branchByPop[$branch['pop']->id] = $index;
        }
        $staff = $this->ref['staff'];
        $categories = $this->ref['categories'];
        $quantityItems = $this->ref['items']->where('tracking_type', 'quantity')->values();
        $now = Carbon::now('Asia/Jakarta');

        $this->info('Tiket: '.$customers->count().' tiket akan dibuat…');

        foreach ($customers->chunk(self::CHUNK) as $chunk) {
            $rows = array_fill_keys(['tickets', 'ticket_histories', 'fop_tasks', 'tasks', 'task_teams', 'fop_task_user', 'fop_task_status_history', 'task_reports', 'task_maintenances', 'task_materials', 'task_creq_details', 'fop_task_teams', 'fop_task_team_user'], []);

            foreach ($chunk->values() as $i => $customer) {
                $b = $branchByPop[$customer->pop_id] ?? 0;
                $techs = $this->ref['technicians'][$b];
                $fopUser = $staff['fop'][$b];
                $helpdesk = $staff['helpdesk'][$i % 2];
                $noc = $staff['noc'][$i % 2];

                $isCreq = mt_rand(1, 100) <= 15;
                $type = $isCreq ? 'C-REQ' : 'MTN';
                $category = $isCreq ? null : $categories[$i % $categories->count()];
                $priority = $category->default_priority ?? 'Medium';
                $slaHours = ['Urgent' => 4, 'High' => 8, 'Medium' => 24, 'low' => 48][$priority];

                // 37 koprima dengan 100 → sebaran merata walau chunk kecil (uji 60 pelanggan tetap kena semua jalur).
                $disposition = ($i * 37 + 11) % 100;
                $route = match (true) {
                    $disposition < 8 => 'helpdesk_open',
                    $disposition < 28 => 'helpdesk_closed',
                    $disposition < 32 => 'helpdesk_cancelled',
                    $disposition < 40 => 'noc_open',
                    $disposition < 45 => 'noc_closed',
                    default => 'fop',
                };

                // Umur tiket condong ke baru; status FOP ditentukan dari umur supaya tidak ada task "selesai" yang lahir hari ini.
                $age = (int) (pow(mt_rand(0, 1000) / 1000, 1.6) * 90);
                $fopStatus = $route === 'fop' ? $this->fopStatusFor($age) : null;
                if (in_array($fopStatus, ['in_progress', 'selesai', 'pending', 'lapor_nanti', 'dibatalkan'], true)) {
                    $age = max($age, 1);
                }
                $created = $now->copy()->startOfDay()->subDays($age)->setTime(mt_rand(7, 20), mt_rand(0, 59));
                if ($created->gt($now)) {
                    $created = $now->copy()->subMinutes(mt_rand(5, 300));
                }

                $ticketId = $ids['tickets']++;
                $ymd = $created->format('Ymd');
                $ticketNumber = $this->nextDocNumber("TKT:{$ymd}", 'TKT', $ymd, 'tickets', 'ticket_number');
                $displayId = $customer->cid ?: $customer->customer_code;

                $handler = match (true) {
                    str_starts_with($route, 'noc') => 'noc',
                    $route === 'fop' => 'fop',
                    default => 'helpdesk',
                };
                $handling = match ($route) {
                    'helpdesk_closed', 'noc_closed' => 'closed',
                    'helpdesk_cancelled' => 'cancelled',
                    default => 'open',
                };

                $history = [['dibuat', null, 'helpdesk', null, $helpdesk, $created]];
                $closedAt = null;
                if (in_array($route, ['noc_open', 'noc_closed'], true)) {
                    $history[] = ['dieskalasi', 'helpdesk', 'noc', 'Perlu pengecekan jaringan', $helpdesk, $created->copy()->addMinutes(mt_rand(10, 90))];
                }
                if (in_array($handling, ['closed', 'cancelled'], true)) {
                    $closedAt = $created->copy()->addHours(mt_rand(1, 20));
                    $actor = str_starts_with($route, 'noc') ? $noc : $helpdesk;
                    $history[] = [$handling === 'closed' ? 'diselesaikan' : 'dibatalkan', 'open', $handling, $handling === 'closed' ? 'Teratasi remote' : 'Dibatalkan pelapor', $actor, $closedAt];
                }

                $fopTaskId = null;
                if ($route === 'fop') {
                    $viaNoc = mt_rand(1, 100) <= 30;
                    $escalated = $created->copy()->addMinutes(mt_rand(15, 300));
                    if ($viaNoc) {
                        $history[] = ['dieskalasi', 'helpdesk', 'noc', 'Perlu pengecekan jaringan', $helpdesk, $created->copy()->addMinutes(10)];
                        $history[] = ['dieskalasi', 'noc', 'fop', 'Butuh teknisi lapangan', $noc, $escalated];
                    } else {
                        $history[] = ['dieskalasi', 'helpdesk', 'fop', 'Butuh teknisi lapangan', $helpdesk, $escalated];
                    }
                    $closedAt = $escalated; // `resolved_at` terisi saat tiket diserahkan ke FOP (TicketService::escalateToFop)

                    $fopTaskId = $ids['fop_tasks']++;
                    $taskId = $ids['tasks']++;
                    $this->buildFieldWork($rows, $ids, [
                        'fop_task_id' => $fopTaskId, 'task_id' => $taskId, 'customer' => $customer, 'type' => $type, 'status' => $fopStatus,
                        'escalated' => $escalated, 'now' => $now, 'techs' => $techs, 'fop_user' => $fopUser, 'branch' => $b,
                        'ticket_number' => $ticketNumber, 'sender' => $viaNoc ? $noc : $helpdesk, 'priority' => $priority, 'sla_hours' => $slaHours,
                        'issue' => $category->name ?? 'Permintaan perubahan layanan', 'display_id' => $displayId, 'quantity_items' => $quantityItems,
                    ]);
                }

                $rows['tickets'][] = [
                    'id' => $ticketId, 'ticket_number' => $ticketNumber, 'type' => $type, 'issue_category_id' => $category->id ?? null, 'handler' => $handler,
                    'status' => $handling, 'resolved_at' => $closedAt, 'customer_id' => $customer->id, 'customer_name' => $customer->full_name,
                    'customer_address' => $customer->address, 'customer_village' => $customer->village, 'customer_phone' => $customer->primary_phone,
                    'reporter_phone' => $customer->primary_phone, 'customer_odp' => $customer->odp_code, 'customer_package' => $customer->package_name_snapshot,
                    'customer_device' => $customer->ont_sn, 'customer_latitude' => $customer->latitude, 'customer_longitude' => $customer->longitude,
                    'pop_id' => $customer->pop_id,
                    'detail_keluhan' => $isCreq ? 'Pelanggan meminta perubahan layanan (dummy).' : ($category->name.' — pelanggan melaporkan gangguan sejak '.$created->copy()->subHours(mt_rand(1, 20))->format('d/m H:i').'.'),
                    'catatan_teknis' => $route === 'helpdesk_open' ? null : 'Sudah dicek remote, redaman '.mt_rand(18, 28).' dBm.',
                    'priority' => $priority, 'sla_hours' => $slaHours, 'sla_deadline_at' => $created->copy()->addHours($slaHours), 'created_by' => $helpdesk,
                    'fop_task_id' => $fopTaskId, 'created_at' => $created, 'updated_at' => $closedAt ?? $created,
                ];

                foreach ($history as [$action, $from, $to, $reason, $actor, $at]) {
                    $rows['ticket_histories'][] = [
                        'ticket_id' => $ticketId, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason,
                        'actor_id' => $actor, 'happened_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                    ];
                }
            }

            $this->flushTickets($rows);
            $this->info(sprintf('  %d tiket…', $ids['tickets'] - $this->nextId['tickets']));
        }
    }

    /**
     * @return 'draft'|'terjadwal'|'in_progress'|'selesai'|'dibatalkan'|'pending'|'lapor_nanti'
     */
    private function fopStatusFor(int $ageDays): string
    {
        $r = mt_rand(1, 100);

        return match (true) {
            $ageDays <= 2 => match (true) {
                $r <= 40 => 'draft', $r <= 70 => 'terjadwal', $r <= 85 => 'in_progress', $r <= 95 => 'selesai', default => 'pending',
            },
            $ageDays <= 14 => match (true) {
                $r <= 8 => 'draft', $r <= 22 => 'terjadwal', $r <= 30 => 'in_progress', $r <= 75 => 'selesai', $r <= 82 => 'dibatalkan', $r <= 90 => 'pending', default => 'lapor_nanti',
            },
            default => match (true) {
                $r <= 70 => 'selesai', $r <= 78 => 'dibatalkan', $r <= 86 => 'pending', $r <= 92 => 'lapor_nanti', $r <= 96 => 'in_progress', default => 'draft',
            },
        };
    }

    /**
     * Bangun satu rantai FopTask + Task + tim + laporan untuk status tertentu.
     *
     * @param  array<string, list<array<string, mixed>>>  $rows
     * @param  array<string, int>  $ids
     * @param  array<string, mixed>  $c
     */
    private function buildFieldWork(array &$rows, array &$ids, array $c): void
    {
        /** @var Carbon $escalated */
        $escalated = $c['escalated'];
        /** @var Carbon $now */
        $now = $c['now'];
        $status = $c['status'];
        $customer = $c['customer'];
        $isCreq = $c['type'] === 'C-REQ';
        $techs = $c['techs'];

        $tfopYmd = $escalated->format('Ymd');
        $tfopNumber = $this->nextDocNumber("TFOP:{$tfopYmd}", 'TFOP', $tfopYmd, 'fop_tasks', 'task_number');
        $taskYmd = $tfopYmd;
        $taskNumber = $this->nextDocNumber("TASK:{$taskYmd}", 'TASK', $taskYmd, 'tasks', 'task_number');

        $scheduled = null;
        $started = null;
        $workFinished = null;
        $completed = null;
        $slaMinutes = $isCreq ? 240 : 120;

        if ($status !== 'draft') {
            $scheduled = $escalated->copy()->addDays(mt_rand(0, 2))->setTime(mt_rand(8, 15), [0, 30][mt_rand(0, 1)]);
            if ($status === 'terjadwal') {
                $scheduled = $scheduled->gt($now) ? $scheduled : $now->copy()->addDays(mt_rand(0, 2))->setTime(mt_rand(9, 15), 0);
            } else {
                // Sudah dikerjakan/dibatalkan: jadwal wajib sudah lewat dan setelah eskalasi.
                if ($scheduled->gt($now->copy()->subHours(6))) {
                    $scheduled = $now->copy()->subHours(mt_rand(7, 30));
                }
                if ($scheduled->lt($escalated)) {
                    $scheduled = $escalated->copy()->addHour();
                }
            }
        }
        if (in_array($status, ['in_progress', 'selesai', 'pending', 'lapor_nanti'], true) && $scheduled) {
            $started = $scheduled->copy()->addMinutes(mt_rand(0, 60));
            $workFinished = in_array($status, ['selesai', 'lapor_nanti'], true) ? $started->copy()->addMinutes(mt_rand(30, 240)) : null;
            $completed = $status === 'selesai' ? $workFinished->copy()->addMinutes(mt_rand(5, 30)) : null;
            // Jadwal di pagi/siang hari ini bisa membuat jam kerja jatuh di masa depan — tarik mundur supaya tak ada "selesai besok".
            if ($started->gt($now)) {
                $started = $now->copy()->subMinutes(mt_rand(60, 180));
            }
            if ($workFinished && $workFinished->gt($now)) {
                $workFinished = $now->copy()->subMinutes(mt_rand(30, 59));
            }
            if ($completed && $completed->gt($now)) {
                $completed = $now->copy()->subMinutes(mt_rand(1, 29));
            }
        }

        // Tim harian FOP (satu tim per cabang per tanggal kerja), sama seperti `FopTaskTeamService::rebuildTeamsForDate()`.
        $teamId = null;
        // `pending` melepas tim dan `dibatalkan` tak butuh tim — keduanya tidak boleh menempel ke tim harian.
        if ($scheduled && ! in_array($status, ['pending', 'dibatalkan'], true)) {
            $teamKey = $c['branch'].'|'.$scheduled->toDateString();
            if (! isset($this->teamIds[$teamKey])) {
                $teamId = $this->teamIds[$teamKey] = $this->nextId['fop_task_teams']++;
                $day = (int) $scheduled->format('z');
                $pair = array_values(array_unique([$techs[$day % count($techs)], $techs[($day + 1) % count($techs)]]));
                $rows['fop_task_teams'][] = ['id' => $teamId, 'name' => 'DMY Tim '.$scheduled->format('d/m/Y').' #'.($c['branch'] + 1), 'work_date' => $scheduled->toDateString(), 'created_by' => $c['fop_user'], 'created_at' => $escalated, 'updated_at' => $escalated];
                foreach ($pair as $userId) {
                    $rows['fop_task_team_user'][] = ['fop_task_team_id' => $teamId, 'user_id' => $userId, 'created_at' => $escalated, 'updated_at' => $escalated];
                }
                $this->ref['teamUsers'][$teamId] = $pair;
            }
            $teamId = $this->teamIds[$teamKey];
        }

        $cancelReason = $status === 'dibatalkan' ? ['Pelanggan membatalkan', 'Gangguan sudah pulih sendiri', 'Pelanggan tidak bisa ditemui'][mt_rand(0, 2)] : null;
        $cancelledAt = $status === 'dibatalkan' ? $scheduled->copy()->addHours(mt_rand(1, 4)) : null;
        $pendingReason = $status === 'pending' ? ['Menunggu material', 'Pelanggan tidak di tempat', 'Cuaca buruk'][mt_rand(0, 2)] : null;

        $rows['fop_tasks'][] = [
            'id' => $c['fop_task_id'], 'task_id' => $c['task_id'], 'team_id' => $teamId, 'manual_override_at' => null,
            'task_number' => $tfopNumber, 'category' => $c['type'], 'task_date' => $scheduled,
            'tugas' => $c['display_id'].'_'.$customer->full_name, 'village_id' => $customer->village_id, 'pop_id' => $customer->pop_id, 'scope_pop_id' => null,
            'scope_network_node_id' => null, 'scope_infrastructure_slot_id' => null, 'scope_infrastructure_port_id' => null, 'scope_snapshot' => null,
            'customer_id' => $customer->id, 'issue' => $c['issue'], 'notes' => "Ticket {$c['ticket_number']} — dikirim oleh Dummy",
            'status' => $status, 'priority' => $c['priority'], 'handling_sla_hours' => $c['sla_hours'], 'sla_breach_notified_at' => null,
            'pending_reason' => $pendingReason, 'client_request_date' => null, 'cancelled_at' => $cancelledAt, 'cancel_reason' => $cancelReason,
            'created_at' => $escalated, 'updated_at' => $completed ?? $cancelledAt ?? $started ?? $escalated,
        ];

        $rows['tasks'][] = [
            'id' => $c['task_id'], 'task_number' => $taskNumber, 'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id, 'task_type' => $c['type'], 'title' => $c['issue'].': '.$customer->full_name, 'description' => $c['issue'],
            'status' => $status, 'scheduled_at' => $scheduled, 'started_at' => $started, 'started_via' => $started ? 'manual' : null,
            'started_latitude' => null, 'started_longitude' => null, 'started_accuracy_meters' => null, 'started_distance_meters' => null,
            'work_finished_at' => $workFinished, 'completed_at' => $completed, 'completed_by' => $completed ? $techs[0] : null,
            'cancelled_at' => $cancelledAt, 'cancel_reason' => $cancelReason, 'pending_reason' => $pendingReason, 'fop_id' => $c['fop_user'],
            'sla_minutes' => $slaMinutes, 'conflict_override' => 0, 'created_by' => $c['fop_user'], 'updated_by' => $c['fop_user'],
            'reject_reason' => null, 'fop_review_status' => $status === 'selesai' ? (mt_rand(1, 100) <= 80 ? 'approved' : 'pending') : 'pending',
            'created_at' => $escalated, 'updated_at' => $completed ?? $cancelledAt ?? $started ?? $escalated,
        ];

        // Tim menempel selama task terjadwal & seterusnya — kecuali `pending` (tim dilepas, balik antrean FOP) dan `dibatalkan`.
        if ($teamId && ! in_array($status, ['pending', 'dibatalkan'], true)) {
            foreach ($this->ref['teamUsers'][$teamId] as $n => $userId) {
                $rows['task_teams'][] = ['task_id' => $c['task_id'], 'user_id' => $userId, 'role_in_task' => $n === 0 ? 'Lead' : 'teknisi', 'created_at' => $escalated, 'updated_at' => $escalated];
                $rows['fop_task_user'][] = ['fop_task_id' => $c['fop_task_id'], 'user_id' => $userId, 'created_at' => $escalated, 'updated_at' => $escalated];
            }
        }

        // Riwayat status sisi FOP — urutan sama dengan perjalanan task sebenarnya.
        $steps = [[null, 'draft', $escalated]];
        if ($scheduled && $status !== 'draft') {
            $steps[] = ['draft', 'terjadwal', $escalated->copy()->addMinutes(mt_rand(5, 120))];
        }
        if ($started) {
            $steps[] = ['terjadwal', 'in_progress', $started];
        }
        match ($status) {
            'selesai' => $steps[] = ['in_progress', 'selesai', $completed],
            'lapor_nanti' => $steps[] = ['in_progress', 'lapor_nanti', $workFinished],
            'pending' => $steps[] = ['in_progress', 'pending', $started->copy()->addMinutes(mt_rand(10, 60))],
            'dibatalkan' => $steps[] = ['terjadwal', 'dibatalkan', $cancelledAt],
            default => null,
        };
        foreach ($steps as [$from, $to, $at]) {
            $rows['fop_task_status_history'][] = ['fop_task_id' => $c['fop_task_id'], 'from_status' => $from, 'to_status' => $to, 'changed_by' => $c['fop_user'], 'changed_at' => $at, 'created_at' => $at, 'updated_at' => $at];
        }

        if ($started) {
            $end = $completed ?? $workFinished ?? $now;
            $duration = max(1, (int) $started->diffInMinutes($end));
            $rows['task_reports'][] = [
                'task_id' => $c['task_id'], 'started_at' => $started, 'pending_at' => $status === 'pending' ? $end : null, 'resumed_at' => null, 'completed_at' => $completed,
                'total_duration_minutes' => $duration, 'sla_target_minutes' => $slaMinutes, 'sla_status' => $completed ? ($duration <= $slaMinutes ? 'on_time' : 'over') : null,
                'sla_overrun_minutes' => $completed && $duration > $slaMinutes ? $duration - $slaMinutes : null, 'created_at' => $started, 'updated_at' => $end,
            ];
        }

        if ($status === 'selesai' && ! $isCreq) {
            $rows['task_maintenances'][] = [
                'task_id' => $c['task_id'], 'kendala_teknis' => $c['issue'].' — penyebab: konektor longgar / kabel tertekuk, sudah diperbaiki.',
                'kabel' => mt_rand(0, 1) ? mt_rand(5, 40).' m' : null, 'modem' => mt_rand(1, 10) === 1 ? 'Ganti ONT' : null, 'patchcord' => mt_rand(0, 1) ? '1 pcs' : null,
                'sleeve' => null, 'lainnya' => null, 'opm_photo' => null, 'speedtest_photo' => null, 'created_at' => $completed, 'updated_at' => $completed,
            ];
            if (mt_rand(1, 100) <= 40 && $c['quantity_items']->isNotEmpty()) {
                $item = $c['quantity_items'][mt_rand(0, $c['quantity_items']->count() - 1)];
                $rows['task_materials'][] = [
                    'fop_task_id' => $c['fop_task_id'], 'customer_id' => $customer->id, 'kind' => 'terpakai', 'item_id' => $item->id, 'lot_no' => null, 'source_custody_id' => null,
                    'item_type' => $item->category_code ?? 'lainnya', 'item_category_id' => null, 'item_name' => $item->name, 'qty' => mt_rand(1, 4), 'unit' => $item->unit ?: 'pcs',
                    'unit_price_snapshot' => 5000, 'note' => null, 'recorded_by' => $techs[0], 'created_at' => $completed, 'updated_at' => $completed,
                ];
            }
        }

        if ($isCreq) {
            $category = ['pindah_lokasi', 'pindah_kabel', 'tambah_modem', 'migrasi', 'lainnya'][mt_rand(0, 4)];
            $verification = $status === 'selesai' ? ['verified', 'verified', 'pending', 'rejected'][mt_rand(0, 3)] : 'pending';
            $rows['task_creq_details'][] = [
                'task_id' => $c['task_id'], 'category' => $category, 'category_custom_name' => $category === 'lainnya' ? 'Permintaan khusus' : null, 'target_pop_id' => null,
                'tikor_lama_lat' => $customer->latitude, 'tikor_lama_lng' => $customer->longitude,
                'tikor_baru_lat' => $category === 'pindah_lokasi' ? $customer->latitude + 0.002 : null, 'tikor_baru_lng' => $category === 'pindah_lokasi' ? $customer->longitude + 0.002 : null,
                'is_billable' => in_array($category, ['pindah_lokasi', 'pindah_kabel', 'tambah_modem'], true) ? 1 : 0, 'billing_note' => null,
                'verification_status' => $verification, 'verified_by' => $verification === 'pending' ? null : $c['fop_user'],
                'verified_at' => $verification === 'pending' ? null : $completed, 'rejection_reason' => $verification === 'rejected' ? 'Biaya belum sesuai' : null,
                'invoice_id' => null, 'created_at' => $escalated, 'updated_at' => $completed ?? $escalated,
            ];
        }
    }

    // ───────────────────────── task otomatis siklus pelanggan ─────────────────────────

    /**
     * SURVEY (menunggu survei), PSB (menunggu pemasangan), DEAC (ambil modem pelanggan berhenti).
     * Task ini lahir otomatis dari alur pelanggan — tidak lewat tiket dan tidak punya FopTask.
     */
    private function seedCustomerLifecycleTasks(): void
    {
        $taskId = $this->takeId('tasks');
        $teamLinkId = $this->takeId('task_teams');
        $fopUsers = $this->ref['staff']['fop'];
        $now = Carbon::now('Asia/Jakarta');
        $count = 0;

        $plan = [
            ['waiting_survey', 'SURVEY', 'Survey Pelanggan', null],
            ['waiting_installation', 'PSB', 'Pemasangan Baru', null],
            ['terminated', 'DEAC', 'Ambil Alat', 400],
        ];

        foreach ($plan as [$status, $type, $title, $limit]) {
            $query = DB::table('customers')->where('registered_by_name', self::MARK)->where('status', $status)->orderBy('id');
            if ($limit) {
                $query->limit($limit);
            }

            foreach ($query->get(['id', 'full_name', 'pop_id', 'registration_date'])->chunk(1000) as $chunk) {
                $tasks = [];
                $teams = [];

                foreach ($chunk as $customer) {
                    $b = $this->ref['branches']->search(fn ($x) => $x['pop']->id === $customer->pop_id) ?: 0;
                    $techs = $this->ref['technicians'][$b];
                    $created = Carbon::parse($customer->registration_date)->addDay();
                    if ($created->gt($now)) {
                        $created = $now->copy()->subHours(2);
                    }
                    $ymd = $created->format('Ymd');
                    $taskNumber = $this->nextDocNumber("TASK:{$ymd}", 'TASK', $ymd, 'tasks', 'task_number');

                    // Task otomatis lahir `pending` (antrean FOP, belum terjadwal) — sebagian sudah dijadwalkan.
                    $scheduled = mt_rand(1, 100) <= 35 ? $now->copy()->addDays(mt_rand(0, 3))->setTime(mt_rand(9, 15), 0) : null;
                    $status2 = $scheduled ? 'terjadwal' : 'pending';
                    $id = $taskId++;
                    $tasks[] = [
                        'id' => $id, 'task_number' => $taskNumber, 'customer_id' => $customer->id, 'pop_id' => $customer->pop_id,
                        'task_type' => $type, 'title' => "{$title}: {$customer->full_name}", 'description' => "{$title} pelanggan {$customer->full_name}", 'status' => $status2,
                        'scheduled_at' => $scheduled, 'started_at' => null, 'started_via' => null, 'started_latitude' => null, 'started_longitude' => null,
                        'started_accuracy_meters' => null, 'started_distance_meters' => null, 'work_finished_at' => null, 'completed_at' => null, 'completed_by' => null,
                        'cancelled_at' => null, 'cancel_reason' => null, 'pending_reason' => null, 'fop_id' => $fopUsers[$b], 'sla_minutes' => 120, 'conflict_override' => 0,
                        'created_by' => $fopUsers[$b], 'updated_by' => $fopUsers[$b], 'reject_reason' => null, 'fop_review_status' => 'pending',
                        'created_at' => $created, 'updated_at' => $created,
                    ];
                    if ($scheduled) {
                        $teams[] = ['id' => $teamLinkId++, 'task_id' => $id, 'user_id' => $techs[0], 'role_in_task' => 'Lead', 'created_at' => $created, 'updated_at' => $created];
                    }
                    $count++;
                }

                $this->bulk('tasks', $tasks);
                $this->bulk('task_teams', $teams);
            }
        }

        $this->info("Task siklus pelanggan (SURVEY/PSB/DEAC): {$count}.");
    }

    // ───────────────────────── helper ─────────────────────────

    /** Bersihkan sisa data operasional dummy terdahulu (misal dari run seeder yang terputus di tengah jalan). */
    private function purgePreviousDummyOperations(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            $customerIds = DB::table('customers')->where('registered_by_name', self::MARK)->pluck('id')->all();
            if (! empty($customerIds)) {
                $ticketIds = DB::table('tickets')->whereIn('customer_id', $customerIds)->pluck('id')->all();
                $fopIds = DB::table('fop_tasks')->whereIn('customer_id', $customerIds)->pluck('id')->all();
                $taskIds = DB::table('tasks')->whereIn('customer_id', $customerIds)->pluck('id')->all();

                foreach (array_chunk($ticketIds, 2000) as $chunk) {
                    DB::table('ticket_histories')->whereIn('ticket_id', $chunk)->delete();
                }
                DB::table('tickets')->whereIn('customer_id', $customerIds)->delete();

                foreach (array_chunk($fopIds, 2000) as $chunk) {
                    foreach (['fop_task_status_history', 'fop_task_user', 'task_materials'] as $table) {
                        DB::table($table)->whereIn('fop_task_id', $chunk)->delete();
                    }
                }
                DB::table('fop_tasks')->whereIn('customer_id', $customerIds)->delete();

                foreach (array_chunk($taskIds, 2000) as $chunk) {
                    foreach (['task_reports', 'task_teams', 'task_maintenances', 'task_creq_details'] as $table) {
                        DB::table($table)->whereIn('task_id', $chunk)->delete();
                    }
                }
                DB::table('tasks')->whereIn('customer_id', $customerIds)->delete();
            }

            // Run yang terputus meninggalkan anak yatim (task/FOP task induknya sudah
            // di-rollback, anaknya sudah commit karena FK dimatikan di sini). ID induk
            // dipakai ulang oleh run berikutnya, jadi anak yatim itu bentrok di unique
            // (task_id, user_id). Pembersihan via pelanggan di atas tidak menjangkaunya.
            foreach (['task_teams', 'task_reports', 'task_maintenances', 'task_creq_details'] as $table) {
                DB::table($table)->whereNotIn('task_id', DB::table('tasks')->select('id'))->delete();
            }
            foreach (['fop_task_status_history', 'fop_task_user', 'task_materials'] as $table) {
                DB::table($table)->whereNotIn('fop_task_id', DB::table('fop_tasks')->select('id'))->delete();
            }

            $requestIds = DB::table('stock_requests')->where('notes', 'like', '[DUMMY]%')->pluck('id')->all();
            foreach (array_chunk($requestIds, 2000) as $chunk) {
                DB::table('stock_request_items')->whereIn('stock_request_id', $chunk)->delete();
            }
            DB::table('stock_requests')->where('notes', 'like', '[DUMMY]%')->delete();

            $teamIds = DB::table('fop_task_teams')->where('name', 'like', 'DMY %')->pluck('id')->all();
            foreach (array_chunk($teamIds, 2000) as $chunk) {
                DB::table('fop_task_team_user')->whereIn('fop_task_team_id', $chunk)->delete();
            }
            DB::table('fop_task_teams')->where('name', 'like', 'DMY %')->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function nextDocNumber(string $key, string $prefix, string $ymd, string $table, string $column): string
    {
        if (! isset($this->docCounters[$key])) {
            $seq = (int) DB::table('number_sequences')->where('key', $key)->value('value');
            $maxVal = DB::table($table)->where($column, 'like', "{$prefix}-{$ymd}-%")->orderByDesc($column)->value($column);
            $maxTable = 0;
            if ($maxVal && preg_match('/-(\d+)$/', (string) $maxVal, $matches)) {
                $maxTable = (int) $matches[1];
            }
            $this->docCounters[$key] = max($seq, $maxTable);
        }

        $this->docCounters[$key]++;

        return sprintf('%s-%s-%06d', $prefix, $ymd, $this->docCounters[$key]);
    }

    /** @param  array<string, list<array<string, mixed>>>  $rows */
    private function flushTickets(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            // Urutan FK: tim → task → FOP task → tiket → anak-anaknya.
            foreach (['fop_task_teams', 'fop_task_team_user', 'tasks', 'fop_tasks', 'tickets', 'ticket_histories', 'task_teams', 'fop_task_user', 'fop_task_status_history', 'task_reports', 'task_maintenances', 'task_materials', 'task_creq_details'] as $table) {
                $this->bulk($table, $rows[$table]);
            }
        });
    }

    /** Counter nomor dokumen disinkronkan supaya terbit berikutnya lewat NumberSequenceService tidak menabrak; tak pernah menurunkan counter. */
    private function syncSequences(): void
    {
        $rows = [];
        foreach ($this->docCounters as $key => $value) {
            $rows[] = ['key' => $key, 'value' => max((int) DB::table('number_sequences')->where('key', $key)->value('value'), $value)];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('number_sequences')->upsert($chunk, ['key'], ['value']);
        }
    }

    private function takeId(string $table): int
    {
        return $this->nextId[$table] = (int) DB::table($table)->max('id') + 1;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function bulk(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function info(string $message): void
    {
        $this->command?->info($message);
    }
}
