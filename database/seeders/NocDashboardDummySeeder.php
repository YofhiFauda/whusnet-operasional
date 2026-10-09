<?php

namespace Database\Seeders;

use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\TicketService;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * DATA CONTOH — mengisi Dashboard NOC (Tren Bulanan Komplain, Leaderboard
 * Performa, SLA Compliance, Aging, Tren Harian, dsb) dengan volume tiket yang
 * realistis, biar chart-chart itu punya sesuatu buat ditampilkan waktu
 * didemokan/dites manual. TIDAK dipanggil dari `DatabaseSeeder::run()` —
 * jalankan manual:
 *
 *   php artisan db:seed --class=NocDashboardDummySeeder
 *
 * Prinsip:
 *  - Pelanggan REAL yang sudah ada di DB dipakai apa adanya (bukan bikin
 *    pelanggan palsu) — biar breakdown daerah/POP di chart mencerminkan
 *    struktur data yang sungguhan dipakai tim, bukan nama fiktif.
 *  - Tiket dibuat & ditransisikan lewat `TicketService` ASLI (create/close/
 *    cancel/escalateToNoc/escalateToFop) — BUKAN insert manual ke tabel —
 *    supaya semua invariant (ticket_histories, resolved_at, sla_deadline_at,
 *    FopTask turunan) tetap konsisten dengan alur produksi sungguhan.
 *  - `Carbon::setTestNow()` dipakai buat "time travel" mundur sampai 12
 *    bulan, karena `TicketService` menulis semua timestamp pakai `now()`
 *    (created_at, happened_at, resolved_at) — SATU-SATUNYA cara membuat data
 *    historis yang tetap lewat jalur asli, bukan tempel tanggal manual
 *    belakangan. WAJIB direset di `finally` — kalau proses ini mati di
 *    tengah jalan, seluruh aplikasi (bukan cuma seeder ini) bakal mengira
 *    "sekarang" itu tanggal lampau.
 *
 * Catatan: `TicketService::generateTicketNumber()` pakai `date('Y')` (bukan
 * `now()->year`), jadi nomor tiket hasil seeder ini tetap berlabel tahun
 * SEKARANG walau `created_at`-nya dibackdate lintas tahun — kosmetik saja,
 * tidak memengaruhi query dashboard mana pun (semuanya filter by `created_at`).
 */
class NocDashboardDummySeeder extends Seeder
{
    private TicketService $ticketService;

    private Carbon $originalNow;

    /** @var Collection<int, User> */
    private Collection $helpdeskUsers;

    /** @var Collection<int, User> */
    private Collection $nocUsers;

    /** @var Collection<int, Customer> */
    private Collection $customers;

    /** @var Collection<int, TicketIssueCategory> */
    private Collection $issueCategories;

    private int $skipped = 0;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('NocDashboardDummySeeder cuma buat data contoh — JANGAN dijalankan di production.');

            return;
        }

        $this->ticketService = app(TicketService::class);
        $this->originalNow = Carbon::now();

        $this->ensureDemoUsers();
        $this->loadPools();

        if ($this->customers->isEmpty()) {
            $this->command?->error('Tidak ada pelanggan dengan pop_id+district_id terisi — jalankan import/CustomerSeeder pelanggan dulu.');

            return;
        }

        $createdCount = 0;

        try {
            // 12 bulan terakhir. $monthsAgo = 0 (bulan berjalan) SENGAJA
            // dikecualikan dari "wajib resolved" — biar ada tiket yang masih
            // terbuka buat ngisi antrean aktif & distribusi aging.
            for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
                $monthStart = $this->originalNow->copy()->subMonths($monthsAgo)->startOfMonth();
                $monthEnd = $monthsAgo === 0
                    ? $this->originalNow->copy()
                    : $this->originalNow->copy()->subMonths($monthsAgo)->endOfMonth();

                $ticketsThisMonth = random_int(18, 35);

                for ($i = 0; $i < $ticketsThisMonth; $i++) {
                    $createdAt = Carbon::createFromTimestamp(
                        random_int($monthStart->timestamp, max($monthStart->timestamp, $monthEnd->timestamp - 1))
                    );

                    if ($this->seedOneTicket($createdAt, forceResolve: $monthsAgo > 0)) {
                        $createdCount++;
                    }
                }
            }
        } finally {
            // WAJIB — lihat catatan class doc di atas.
            Carbon::setTestNow($this->originalNow);
        }

        // Distribusi acak murni kadang kebetulan gak nyentuh satu bucket
        // (mis. gak ada satu pun tiket yang "baru dibuat < 8 jam lalu" waktu
        // seeder ini kelar jalan) — chart Distribusi Aging jadi ada bar 0.
        // Top-up ini MENJAMIN ketiga bucket (0-8j/8-24j/>24j) selalu keisi,
        // dijalankan di waktu ASLI (bukan di dalam try/finally time-travel
        // di atas).
        $this->topUpAgingCoverage();
        // Sama alasan aging di atas — 20% peluang breach per tiket NOC bisa
        // kebetulan 0 kejadian di bulan berjalan kalau sample-nya kecil
        // (mis. baru tanggal 9), bikin donut SLA Compliance keliatan 100%
        // hijau padahal datanya sendiri punya breach (cuma gak di bulan ini).
        $this->topUpSlaBreachCoverage();

        $this->command?->info("✅ NocDashboardDummySeeder: {$createdCount} tiket dummy dibuat lewat TicketService (12 bulan terakhir, {$this->skipped} dilewati karena error data pelanggan).");
        $this->command?->info('Login demo — helpdesk: helpdesk.demo1@whusnet.com, noc: noc.demo1@whusnet.com (password: password).');
    }

    /**
     * Entry point terpisah buat nambal SATU bucket aging tanpa menjalankan
     * ulang seluruh riwayat 12 bulan (yang bakal dobel ~300 tiket lagi tiap
     * dipanggil). Dipakai waktu data sudah pernah di-seed lewat `run()` tapi
     * distribusi acaknya kebetulan bikin satu bucket kosong — jalankan:
     *
     *   php artisan tinker --execute="(new Database\Seeders\NocDashboardDummySeeder)->topUpOnly();"
     */
    public function topUpOnly(): void
    {
        $this->ticketService = app(TicketService::class);
        $this->originalNow = Carbon::now();
        $this->ensureDemoUsers();
        $this->loadPools();

        if ($this->customers->isEmpty()) {
            $this->command?->error('Tidak ada pelanggan dengan pop_id+district_id terisi.');

            return;
        }

        $this->topUpAgingCoverage();
        $this->topUpSlaBreachCoverage();
        $this->command?->info('✅ Top-up distribusi aging & SLA breach bulan berjalan selesai.');
    }

    /**
     * Bikin 2 tiket per bucket (0-8j / 8-24j / >24j), handler=NOC, status
     * tetap OPEN — SENGAJA gak lewat `applyOutcome()` (yang isinya
     * probabilistik dan bisa nutup/eskalasi tiketnya lagi). Target umur
     * dipilih di tengah tiap rentang (4j/16j/36j) biar aman dari boundary
     * begitu beberapa menit berlalu sampai dashboard-nya dibuka.
     */
    private function topUpAgingCoverage(): void
    {
        foreach ([4, 16, 36] as $targetAgeHours) {
            for ($i = 0; $i < 2; $i++) {
                $createdAt = $this->originalNow->copy()->subHours($targetAgeHours)->subMinutes(random_int(0, 30));
                Carbon::setTestNow($createdAt);

                $customer = $this->customers->random();
                $issueCategory = $this->issueCategories->isNotEmpty() ? $this->issueCategories->random() : null;

                $ticket = $this->safeCall(fn () => $this->ticketService->create([
                    'type' => TaskType::MAINTENANCE->value,
                    'customer_id' => $customer->id,
                    'issue_category_id' => $issueCategory?->id,
                    'detail_keluhan' => $this->complaintText($issueCategory?->name),
                    'priority' => $issueCategory?->default_priority?->value ?? 'High',
                ], $this->helpdeskUsers->random())['ticket']);

                if ($ticket) {
                    Carbon::setTestNow($createdAt->copy()->addMinutes(random_int(5, 30)));
                    $this->safeCall(fn () => $this->ticketService->escalateToNoc(
                        $ticket->fresh(), $this->helpdeskUsers->random(), 'Butuh pengecekan jaringan lebih lanjut (data contoh).'
                    ));
                }

                Carbon::setTestNow($this->originalNow);
            }
        }
    }

    /**
     * 2 tiket bulan berjalan yang DIJAMIN breach SLA — dibuat awal bulan
     * lalu ditutup jauh melewati `sla_hours` (semua kategori issue seed
     * `sla_source='prioritas'`, maks 48 jam — 60-90 jam pasti lewat).
     * `travelTo()` (bukan `Carbon::setTestNow()` langsung) — kalau
     * `$createdAt` mepet ke awal bulan tapi bulan berjalan baru masuk hari
     * ke-2/3, delay 60-90 jam bisa mendorong titik tutup ke masa depan.
     */
    private function topUpSlaBreachCoverage(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $createdAt = $this->originalNow->copy()->startOfMonth()->addDays(random_int(0, 2))->setTime(random_int(7, 17), random_int(0, 59));
            Carbon::setTestNow($createdAt);

            $issueCategory = $this->issueCategories->isNotEmpty() ? $this->issueCategories->random() : null;
            $ticket = $this->safeCall(fn () => $this->ticketService->create([
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $this->customers->random()->id,
                'issue_category_id' => $issueCategory?->id,
                'detail_keluhan' => $this->complaintText($issueCategory?->name),
                'priority' => $issueCategory?->default_priority?->value ?? 'High',
            ], $this->helpdeskUsers->random())['ticket']);

            if ($ticket) {
                $this->travelTo($createdAt->copy()->addMinutes(random_int(5, 30)));
                $escalated = $this->safeCall(fn () => $this->ticketService->escalateToNoc(
                    $ticket->fresh(), $this->helpdeskUsers->random(), 'Butuh pengecekan jaringan lebih lanjut (data contoh).'
                ));

                if ($escalated) {
                    $this->travelTo($createdAt->copy()->addHours(random_int(60, 90)));
                    $this->safeCall(fn () => $this->ticketService->close(
                        $escalated->fresh(), $this->nocUsers->random(), 'Selesai ditangani NOC — telat, butuh eskalasi ulang (data contoh, breach SLA).'
                    ));
                }
            }

            Carbon::setTestNow($this->originalNow);
        }
    }

    /**
     * User demo Helpdesk & NOC — pola sama `SalesSeeder`/`TechnicianSeeder`
     * (scope `all_pop`, password seragam). Beberapa orang per role (bukan
     * satu) SENGAJA — Leaderboard Performa Individu butuh variasi aktor
     * buat kelihatan gunanya, satu orang doang bikin ranking gak bermakna.
     */
    private function ensureDemoUsers(): void
    {
        $helpdeskRole = Role::where('code', 'helpdesk')->first();
        $nocRole = Role::where('code', 'noc')->first();

        if (! $helpdeskRole || ! $nocRole) {
            $this->command?->error('Role helpdesk/noc belum ada — jalankan RoleSeeder dulu.');

            return;
        }

        $names = [
            'helpdesk' => ['Rina Wulandari', 'Dedi Kurniawan', 'Siti Aminah'],
            'noc' => ['Bagus Prasetyo', 'Yuni Lestari', 'Fajar Nugroho'],
        ];

        foreach (['helpdesk' => $helpdeskRole, 'noc' => $nocRole] as $code => $role) {
            foreach ($names[$code] as $i => $name) {
                $user = User::updateOrCreate(
                    ['email' => sprintf('%s.demo%d@whusnet.com', $code, $i + 1)],
                    [
                        'name' => $name,
                        'phone' => sprintf('0812%08d', 10000000 + $i + ($code === 'noc' ? 100 : 0)),
                        'password' => Hash::make('password'),
                        'status' => 'active',
                        'role_id' => $role->id,
                        'email_verified_at' => now(),
                    ]
                );

                $scope = UserRoleScope::updateOrCreate(
                    ['user_id' => $user->id, 'role_id' => $role->id],
                    ['scope_type' => 'all_pop']
                );
                UserRoleScopeTarget::where('user_role_scope_id', $scope->id)->delete();
            }
        }
    }

    private function loadPools(): void
    {
        // `->with('role')` WAJIB — tanpa ini `whereHas('role', ...)` balik
        // model tanpa relasi 'role' ter-eager-load, dan `User::hasRole()`
        // yang dipanggil `Ticket::holderRoles()`/`HasPopScope::applyUserScope()`
        // di dalam `TicketService` bakal lazy-load relasi itu — meledak
        // `LazyLoadingViolationException` (Model::preventLazyLoading aktif
        // di semua environment non-production, lihat AppServiceProvider).
        // Pakai `where('role_id', ...)` (bukan `whereHas`) biar gak
        // tergantung whereHas — reproduksi lazy-load di atas ternyata
        // konsisten muncul lewat whereHas walau sudah `->with()`.
        $helpdeskRoleId = Role::where('code', 'helpdesk')->value('id');
        $nocRoleId = Role::where('code', 'noc')->value('id');

        $this->helpdeskUsers = User::where('role_id', $helpdeskRoleId)->with('role')->get();
        $this->nocUsers = User::where('role_id', $nocRoleId)->with('role')->get();

        // POP kode 'DEMO-PUSAT' itu "Gudang Pusat (Contoh)" — POP demo modul
        // Gudang, bukan cabang pelanggan sungguhan. Dikecualikan biar chart
        // per-daerah gak nyampur data operasional dengan data demo gudang.
        $realPopIds = Pop::where('code', '!=', 'DEMO-PUSAT')->pluck('id');

        $this->customers = Customer::whereNotNull('pop_id')
            ->whereNotNull('district_id')
            ->whereIn('pop_id', $realPopIds)
            ->with(['pop:id,name', 'district:id,name', 'internetPackage'])
            ->inRandomOrder()
            ->limit(500)
            ->get();

        $this->issueCategories = TicketIssueCategory::where('is_active', true)->get();
    }

    /**
     * Satu siklus hidup tiket lengkap: create() lewat TicketService, lalu
     * outcome acak (selesai Helpdesk/dieskalasi NOC/dst) via applyOutcome().
     * Return false kalau create()-nya gagal (data pelanggan tepi kasus,
     * mis. paket kosong bikin resolveSlaHours() menolak) — dilewati, jangan
     * hentikan seluruh seeding gara-gara satu baris data lama yang aneh.
     */
    private function seedOneTicket(Carbon $createdAt, bool $forceResolve): bool
    {
        Carbon::setTestNow($createdAt);

        $customer = $this->customers->random();
        // 78% "komplain" (proksi type=MTN, konvensi sama FopAnalyticsController
        // & buildMonthlyComplaintTrend() — lihat NocDashboardController), 22%
        // Customer Request (bukan keluhan, permintaan administratif).
        $isComplaint = random_int(1, 100) <= 78;
        $type = $isComplaint ? TaskType::MAINTENANCE : TaskType::CREQ;
        $issueCategory = $isComplaint && $this->issueCategories->isNotEmpty()
            ? $this->issueCategories->random()
            : null;

        $priority = $issueCategory?->default_priority?->value
            ?? collect(['low', 'Medium', 'High'])->random();

        $creator = $this->helpdeskUsers->random();
        $detailKeluhan = $isComplaint
            ? $this->complaintText($issueCategory?->name)
            : $this->requestText();

        $ticket = $this->safeCall(fn () => $this->ticketService->create([
            'type' => $type->value,
            'customer_id' => $customer->id,
            'issue_category_id' => $issueCategory?->id,
            'detail_keluhan' => $detailKeluhan,
            'priority' => $priority,
        ], $creator)['ticket']);

        if (! $ticket) {
            $this->skipped++;

            return false;
        }

        $this->applyOutcome($ticket, $createdAt, $forceResolve);

        return true;
    }

    /**
     * Transisi lanjutan tiket dari titik `create()` — proporsi kasarnya:
     * 10% dibatalkan Helpdesk, 30% selesai langsung Helpdesk, sisanya (60%)
     * dieskalasi ke NOC lalu bercabang lagi (selesai/eskalasi FOP/batal/
     * dibiarkan terbuka). `$cursor` maju kumulatif dari `$createdAt` —
     * BUKAN reset per langkah — supaya urutan waktu antar histori (dibuat →
     * dieskalasi → diselesaikan) tetap kronologis logis.
     */
    private function applyOutcome(Ticket $ticket, Carbon $createdAt, bool $forceResolve): void
    {
        $cursor = $createdAt->copy();
        $roll = random_int(1, 100);

        if ($roll <= 10) {
            $this->travelTo($cursor->addMinutes(random_int(5, 180)));
            $this->safeCall(fn () => $this->ticketService->cancel(
                $ticket->fresh(), $this->helpdeskUsers->random(), 'Duplikat/salah lapor (data contoh).'
            ));

            return;
        }

        if ($roll <= 40) {
            $this->travelTo($cursor->addMinutes(random_int(10, 240)));
            $this->safeCall(fn () => $this->ticketService->close(
                $ticket->fresh(), $this->helpdeskUsers->random(), 'Selesai ditangani Helpdesk (data contoh).'
            ));

            return;
        }

        $this->travelTo($cursor->addMinutes(random_int(5, 60)));
        $escalated = $this->safeCall(fn () => $this->ticketService->escalateToNoc(
            $ticket->fresh(), $this->helpdeskUsers->random(), 'Butuh pengecekan jaringan lebih lanjut (data contoh).'
        ));

        if (! $escalated) {
            return;
        }

        // Dibiarkan terbuka di NOC — ngisi antrean aktif & distribusi aging.
        // Cuma untuk bulan berjalan ($forceResolve=false); bulan lampau
        // wajib tuntas biar tren bulanan gak menghitung tiket yang
        // "menggantung selamanya" secara gak realistis.
        if (! $forceResolve && random_int(1, 100) <= 35) {
            return;
        }

        $nocRoll = random_int(1, 100);

        if ($nocRoll <= 15) {
            $this->travelTo($cursor->addMinutes(random_int(30, 480)));
            $this->safeCall(fn () => $this->ticketService->escalateToFop(
                $escalated->fresh(), $this->nocUsers->random(), 'Butuh penanganan lapangan (data contoh).'
            ));

            return;
        }

        if ($nocRoll <= 20) {
            $this->travelTo($cursor->addMinutes(random_int(30, 300)));
            $this->safeCall(fn () => $this->ticketService->cancel(
                $escalated->fresh(), $this->nocUsers->random(), 'Gangguan pulih sendiri (data contoh).'
            ));

            return;
        }

        // Sisanya NOC selesaikan — 20% dibuat breach SLA sengaja, biar chart
        // SLA Compliance gak selalu 100% on-time (gak realistis kalau selalu).
        $breach = random_int(1, 100) <= 20;
        $this->travelTo($cursor->addMinutes($breach ? random_int(600, 2400) : random_int(20, 480)));
        $this->safeCall(fn () => $this->ticketService->close(
            $escalated->fresh(), $this->nocUsers->random(), 'Selesai ditangani NOC (data contoh).'
        ));
    }

    /**
     * `Carbon::setTestNow()`, tapi diklem supaya gak pernah lewat waktu asli
     * seeder dijalankan — delay acak yang panjang (mis. breach SLA 40 jam)
     * bisa mendorong `$cursor` ke masa depan kalau tiketnya dibuat mepet ke
     * `$originalNow` (bulan berjalan). Tiket yang "selesai di masa depan"
     * bikin metrik ganjil (avg durasi negatif dsb) — diklem ke `now()` asli.
     */
    private function travelTo(Carbon $moment): void
    {
        Carbon::setTestNow($moment->greaterThan($this->originalNow) ? $this->originalNow->copy() : $moment);
    }

    /**
     * @return mixed null kalau `$fn` melempar exception (data pelanggan tepi
     *               kasus) — dilewati, dihitung ke `$skipped`.
     */
    private function safeCall(Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            $this->skipped++;

            return null;
        }
    }

    /**
     * Teks keluhan realistis per kategori issue — dipilih acak dari template
     * per kategori supaya chart "Tren Bulanan Komplain" gak cuma keisi angka
     * tapi juga contoh nyata "komplainnya apa saja" waktu di-drill ke detail
     * tiket.
     */
    private function complaintText(?string $categoryName): string
    {
        $templates = [
            'Lemot' => [
                'Internet pelanggan lemot sejak pagi, buat browsing aja lama.',
                'Kecepatan internet turun drastis, biasanya lancar sekarang loading terus.',
                'Streaming sering buffering, pelanggan komplain jaringan lemot dari kemarin.',
                'Koneksi kadang cepat kadang lambat, tidak stabil sepanjang hari.',
            ],
            'LOS' => [
                'Modem pelanggan indikator LOS merah, internet total mati.',
                'ONT menyala tapi lampu LOS blinking merah, tidak connect ke jaringan.',
                'Internet putus total, dicek modem statusnya LOS.',
                'Pelanggan lapor internet mati dari semalam, kemungkinan LOS di ODP.',
            ],
            'Backbone CUT' => [
                'Beberapa pelanggan satu area laporan internet mati bersamaan, dugaan kabel backbone putus.',
                'Diduga ada kabel FO utama putus, satu kecamatan komplain internet down.',
                'Gangguan massal, kemungkinan backbone cut akibat proyek galian jalan.',
                'Internet mati serentak sejak siang, tim dicurigai ada pemotongan kabel backbone.',
            ],
            'ODP LOS' => [
                'ODP di area pelanggan diduga bermasalah, beberapa rumah sekitar ikut mati.',
                'Kabel dari ODP ke rumah pelanggan diduga putus/kendor, LOS di sisi ODP.',
                'Satu ODP kompak lapor internet mati semua, dugaan core ODP bermasalah.',
                'Pelanggan baru pindah rumah, sambungan dari ODP belum stabil dan sering LOS.',
            ],
        ];

        $fallback = [
            'Pelanggan komplain gangguan internet, perlu dicek lebih lanjut oleh tim.',
            'Ada keluhan jaringan dari pelanggan, detail teknis menyusul setelah pengecekan.',
        ];

        $pool = $templates[$categoryName] ?? $fallback;

        return $pool[array_rand($pool)];
    }

    private function requestText(): string
    {
        $templates = [
            'Pelanggan minta upgrade paket ke kecepatan lebih tinggi.',
            'Permintaan pindah alamat pemasangan ke rumah baru, masih satu area POP.',
            'Pelanggan minta ganti nomor HP yang terdaftar di sistem.',
            'Permintaan penjadwalan ulang kunjungan teknisi untuk pemasangan tambahan.',
            'Pelanggan tanya status tagihan dan minta dikirim ulang invoice bulan ini.',
        ];

        return $templates[array_rand($templates)];
    }
}
