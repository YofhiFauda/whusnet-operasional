<?php

namespace App\Http\Controllers;

use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Events\CollectorActivityUpdated;
use App\Models\CashDeposit;
use App\Models\CollectorDeposit;
use App\Models\Customer;
use App\Models\District;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Pop;
use App\Models\User;
use App\Models\Village;
use App\Notifications\AppNotification;
use App\Services\AdminCashBalanceService;
use App\Services\CollectorBalanceService;
use App\Services\CollectorVisitService;
use App\Services\CollectorWorklistService;
use App\Services\EffectiveAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Worksheet Admin — halaman kerja ADMIN atas para kolektor.
 *
 * Dipisah tegas dari Worklist Kolektor (`/collector-worklist`,
 * CollectorWorklistController) menurut SIAPA PENGGUNANYA, bukan menurut data:
 * halaman ini dipakai admin di kantor, halaman itu dipakai kolektor di
 * lapangan. Konsekuensinya kolektor tak pernah membuka halaman ini, jadi role
 * `kolektor` tetap tak perlu `payments.create` maupun `customers.update`.
 *
 * Isi:
 *   - index()  : 2 panel — daftar kolektor (kiri) + pelanggan yang BELUM
 *                di-assign ke kolektor mana pun (kanan, multi-select → assign).
 *   - show()   : per-kolektor, 5 tab —
 *                · Pembayaran : tunggakan kolektor ini + bayar mewakili
 *                  (1-by-1 / massal), untuk cross check.
 *                · Setoran    : hitung uang fisik, verifikasi, tangani
 *                  selisih/lebih setor, hapus buku (Owner).
 *                · Kunjungan  : laporan aging + riwayat kunjungan.
 *                · Kwitansi   : cetak ber-QR, upload bulk, pantau pencocokan,
 *                  cocokkan manual. SUMBU DOKUMEN — tak menyentuh status kas.
 *                · Atur Pelanggan : assign/reassign/lepas rute permanen
 *                  `customers.collector_id`, di-scope ke kolektor ini.
 *
 * Tiga guard §B-3 tetap berlaku: (1) target wajib ber-role kolektor —
 * dijamin authorizeCollector(), (2) POP pelanggan wajib masuk scope kolektor,
 * (3) larangan nonaktifkan kolektor bermuatan — di UserController::update().
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §3, §9.
 */
class CollectorWorksheetController extends Controller
{
    public function __construct(
        private readonly CollectorWorklistService $worklist,
        private readonly CollectorBalanceService $balance,
        private readonly CollectorVisitService $visits,
        private readonly EffectiveAccessService $access,
        private readonly AdminCashBalanceService $cash,
    ) {}

    /**
     * Channel `collector-activity.{popId}` yang boleh didengarkan viewer ini —
     * dipakai partial realtime aktivitas kas (setoran + pembayaran + rute).
     *
     * `getAllowedPopIds()` mengembalikan array KOSONG untuk akses semua POP,
     * dan array kosong itu ambigu (bisa berarti "belum di-setup"). Karena itu
     * `hasAllPopAccess()` diperiksa lebih dulu, sesuai aturan POP scope di
     * CLAUDE.md — kalau dibalik, admin ber-akses penuh justru tidak berlangganan
     * channel apa pun dan diam-diam tak pernah menerima kabar setoran.
     *
     * @return array<int, string>
     */
    private function activityChannels(User $viewer): array
    {
        $popIds = $this->access->hasAllPopAccess($viewer)
            ? Pop::query()->pluck('id')->all()
            : $this->access->getAllowedPopIds($viewer);

        return array_map(fn ($popId) => 'collector-activity.'.$popId, $popIds);
    }

    public function index(Request $request): View
    {
        $collectors = User::query()
            ->whereHas('role', fn ($q) => $q->where('code', 'kolektor'))
            ->withCount(['assignedCustomers as customer_count' => function ($q) {
                $q->applyUserScope();
            }])
            ->orderBy('name')
            ->get();

        // Total tunggakan per kolektor. SENGAJA bukan JOIN + GROUP BY di SQL —
        // HasPopScope::scopeApplyUserScope() menulis `pop_id` TANPA qualifier
        // tabel, jadi begitu di-JOIN dengan `customers` (yang juga punya
        // `pop_id`) langsung ambiguous di MySQL/SQLite. Volume datanya kecil
        // (tunggakan per kolektor), agregasi di PHP lebih aman ketimbang
        // menambal tiap query yang kebetulan JOIN sama trait scope ini.
        $unpaidTotals = Invoice::query()
            ->applyUserScope()
            ->whereIn('invoice_status', [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value])
            ->whereHas('customer', fn ($q) => $q->whereNotNull('collector_id'))
            ->with('customer:id,collector_id')
            ->get(['id', 'customer_id', 'remaining_amount'])
            ->groupBy(fn (Invoice $invoice) => $invoice->customer->collector_id)
            ->map(fn ($group) => $group->sum('remaining_amount'));

        foreach ($collectors as $collector) {
            $collector->unpaid_total = (float) ($unpaidTotals[$collector->id] ?? 0);
        }

        // Panel kanan: pelanggan yang belum dipegang kolektor mana pun.
        // `applyUserScope()` WAJIB — ini query pelanggan baru, dan panel ini
        // memuat seluruh pelanggan tak ber-kolektor kalau tidak dibatasi.
        $popIds = array_values(array_filter((array) $request->query('pop_id', [])));
        $miniPopIds = array_values(array_filter((array) $request->query('mini_pop_id', [])));
        $districtIds = array_values(array_filter((array) $request->query('district_id', [])));
        $villageIds = array_values(array_filter((array) $request->query('village_id', [])));
        $search = trim((string) $request->query('search', ''));

        $unassignedCustomers = Customer::query()
            ->applyUserScope()
            ->whereNull('collector_id')
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('full_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('cid', 'like', "%{$search}%");
            }))
            ->when(! empty($popIds), fn ($q) => $q->whereIn('pop_id', $popIds))
            ->when(! empty($miniPopIds), fn ($q) => $q->whereIn('mini_pop_id', $miniPopIds))
            ->when(! empty($districtIds), fn ($q) => $q->whereIn('district_id', $districtIds))
            ->when(! empty($villageIds), fn ($q) => $q->whereIn('village_id', $villageIds))
            ->with('pop')
            ->orderBy('full_name')
            ->paginate(50, ['*'], 'unassigned_page')
            ->withQueryString();

        $selectedCabang = empty($popIds)
            ? collect()
            : Pop::forUser()->whereIn('id', $popIds)->orderBy('name')->get(['id', 'name']);
        $selectedMini = empty($miniPopIds)
            ? collect()
            : Pop::forUser()->whereIn('id', $miniPopIds)->with('parent:id,name')->orderBy('name')->get(['id', 'name', 'parent_id']);
        $selectedDistricts = empty($districtIds)
            ? collect()
            : District::whereIn('id', $districtIds)->orderBy('name')->get(['id', 'name']);
        $selectedVillages = empty($villageIds)
            ? collect()
            : Village::whereIn('id', $villageIds)->with('district:id,name')->orderBy('name')->get(['id', 'name', 'district_id']);

        $activityChannels = $this->activityChannels($request->user());

        // Posisi kas ADMIN yang sedang membuka halaman ini. Ditampilkan di sini
        // karena inilah tempat uang kolektor berpindah tangan: begitu admin
        // memverifikasi setoran, uangnya menjadi tanggung jawab dia — dan
        // sebelum modul Setoran Kas ada, perpindahan itu tak pernah tercatat
        // di mana pun (docs/plan/kolektor/analisa-setoran-kas-admin.md §1).
        $kasTunai = $this->cash->tunaiBelumDisetor($request->user());
        $kasNonTunai = $this->cash->nonTunaiRekap($request->user());
        $kasSelisih = $this->cash->selisihTerbuka($request->user());

        // Cuma JUMLAH sumber, bukan barisnya. Halaman ini sudah menarik daftar
        // kolektor, seluruh tunggakan, dan pelanggan tak ber-kolektor —
        // menambah dua query agregat aman, menambah dua daftar penuh tidak.
        // Rincian per pelanggan tetap jadi urusan halaman Setoran Kas (§9.1).
        $kasSumberCount = $this->cash->unsettledCollectorDepositsQuery($request->user())->count()
            + $this->cash->unsettledManualPaymentsQuery($request->user())->count();

        // Kunci idempotensi dibuat SERVER-SIDE per pemuatan halaman: klik dobel
        // atau retry jaringan tidak boleh melahirkan dua setoran atas uang yang
        // sama.
        $kasIdempotencyKey = (string) str()->uuid();

        // Riwayat setoran SENDIRI — pandangan PENYETOR (§10).
        //
        // Sengaja tidak memuat `payments.customer` maupun nama kolektor:
        // pertanyaan admin di sini cuma "setoran saya sudah diperiksa belum",
        // bukan "uang itu dari pelanggan siapa". Rincian sampai tingkat
        // pelanggan adalah pandangan PEMERIKSA dan tinggal di `/cash-deposits`.
        // Yang di-eager-load hanya kolom nominal, supaya total tetap bisa
        // dihitung tanpa N+1 dan tanpa menarik data pelanggan.
        $kasRiwayat = CashDeposit::query()
            ->realDeposits()
            ->where('depositor_id', $request->user()->id)
            ->with([
                'verifier:id,name',
                'collectorDeposits:id,cash_deposit_id,declared_amount,difference',
                'manualPayments:id,cash_deposit_id,amount',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(5, ['*'], 'kas_page')
            ->withQueryString();

        return view('collector-worksheet.index', compact(
            'activityChannels',
            'kasTunai',
            'kasNonTunai',
            'kasSelisih',
            'kasSumberCount',
            'kasIdempotencyKey',
            'kasRiwayat',
            'collectors',
            'unassignedCustomers',
            'search',
            'popIds',
            'miniPopIds',
            'districtIds',
            'villageIds',
            'selectedCabang',
            'selectedMini',
            'selectedDistricts',
            'selectedVillages'
        ));
    }

    public function show(Request $request, User $collector): View
    {
        $this->authorizeCollector($collector);

        // Gerbang POP halaman kas kolektor.
        //
        // `authorizeCollector()` cuma memastikan targetnya memang kolektor —
        // itu tidak menghalangi admin cabang A membuka kolektor cabang B dan
        // membaca saldo, riwayat setoran, catatan verifikasi, sampai alasan
        // hapus buku. Semua query lain di halaman ini sudah ber-scope, jadi
        // ketiadaan gerbang di sini adalah kelupaan, bukan desain.
        //
        // All-or-nothing, bukan "saring yang boleh": halaman ini menyajikan
        // ANGKA TOTAL. Total yang diam-diam disaring bukan menyembunyikan
        // baris — ia berbohong, dan admin menghitung uang fisik dengan
        // patokan yang salah.
        abort_unless(
            $this->balance->isVisibleTo($collector, $request->user()),
            403,
            'Kolektor ini menagih di POP di luar scope Anda. Posisi kasnya hanya boleh dibuka admin yang membawahi seluruh POP-nya.'
        );

        $tab = in_array($request->query('tab'), ['assign', 'setoran', 'kunjungan', 'kwitansi'], true)
            ? $request->query('tab')
            : 'pembayaran';

        // Sumbu DOKUMEN — sengaja tidak menyentuh status setoran (§13.2).
        // Yang belum tercocokkan sengaja ikut ditampilkan: berkas tanpa pemilik
        // adalah pekerjaan yang tertinggal, bukan sesuatu yang boleh hilang
        // diam-diam dari layar admin.
        // Berkas TERCOCOKKAN → disaring POP scope seperti query lain.
        // Berkas BELUM tercocokkan → belum punya `pop_id` sehingga tak bisa
        // di-scope; yang dipakai sebagai gantinya adalah kepemilikan unggahan.
        // Tanpa pembatas itu, panel ini membeberkan SELURUH kwitansi yatim di
        // sistem — nama berkas, pengunggah, nomor yang terbaca — ke tiap admin,
        // lintas cabang.
        $viewer = $request->user();

        // Aturan aksesnya tinggal di PaymentReceipt::scopeForWorksheet() —
        // dipakai bersama endpoint progres (PaymentReceiptController::progress)
        // supaya daftar dan penghitungnya tak pernah menyimpang.
        $receipts = PaymentReceipt::query()
            ->forWorksheet($collector, $viewer)
            ->with(['payment.customer:id,full_name', 'uploader:id,name', 'matcher:id,name'])
            ->orderByRaw("CASE WHEN status = 'matched' THEN 1 ELSE 0 END")
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'receipt_page')
            ->withQueryString();

        // Kandidat pencocokan manual: pembayaran kolektor ini yang belum punya
        // kwitansi. Dibatasi supaya admin tidak memilih dari ribuan baris.
        // Kandidat cetak = PEMBAYARAN, bukan tagihan. Satu baris di sini
        // artinya uangnya sudah diterima dan tercatat; pelanggan yang belum
        // bayar tak punya baris payment sehingga mustahil ikut tercetak.
        //
        // `payment_status` WAJIB disaring: pembayaran yang DITOLAK (uang tak
        // pernah sampai kantor) tak boleh dicetak kwitansinya — kertas resmi
        // yang menyatakan pelanggan sudah bayar untuk uang yang kantor sendiri
        // sudah tolak adalah dokumen yang melawan catatannya sendiri.
        //
        // Syarat KETIGA (keputusan user 2026-08-08): setorannya harus sudah
        // DIPERIKSA KANTOR. Kwitansi adalah dokumen kantor, bukan sesuatu yang
        // terbit di lapangan — selama uangnya masih di tas kolektor, kantor
        // belum punya dasar menerbitkan bukti apa pun.
        //
        // Yang dipakai `isVerified()`, bukan "harus TERVERIFIKASI": setoran
        // yang berakhir Kurang Setor pun sudah selesai diperiksa. Pelanggan
        // yang membayar penuh tidak boleh kehilangan kwitansinya cuma karena
        // kolektor kurang menyetor — itu urusan kantor dengan kolektor, bukan
        // urusan pelanggan.
        $receiptCandidates = Payment::query()
            ->applyUserScope()
            ->where('collected_by', $collector->id)
            ->where('payment_status', PaymentStatus::VALID->value)
            ->whereHas('collectorDeposit', fn ($q) => $q->where('status', '!=', DepositStatus::MENUNGGU_VERIFIKASI->value))
            ->whereDoesntHave('receipts')
            ->with('customer:id,full_name')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // Laporan aging kunjungan — bukan sekadar riwayat. Yang dicari admin
        // di sini adalah POLA: pelanggan yang berulang kali "tidak ada orang"
        // sementara tunggakannya menua. Satu baris belum tentu berarti apa-apa
        // (§12); yang layak diaudit adalah pengulangannya.
        $aging = $this->visits->agingFor($collector)->paginate(25, ['*'], 'aging_page')->withQueryString();
        $visitHistory = $this->visits->historyFor($collector)->paginate(25, ['*'], 'visit_page')->withQueryString();

        // Dua angka uang kolektor, sengaja dipisah dan tak pernah dijumlahkan
        // (§11.2): saldo di tangan vs kewajiban kurang setor.
        $balance = $this->balance->balance($collector);
        $outstandingShortfall = $this->balance->outstandingShortfall($collector);
        $openShortfallDeposits = $this->balance->openShortfallDeposits($collector);

        // `payments.customer` dimuat di sini supaya tab Setoran bisa menampilkan
        // "pelanggan yang bayar" per setoran tanpa N+1 — sebelumnya cuma
        // hitungan jumlah transaksi yang muncul, baris pelanggannya sendiri
        // tak pernah ditarik.
        $deposits = CollectorDeposit::query()
            ->where('collector_id', $collector->id)
            ->with(['payments.customer:id,full_name', 'verifier', 'settlesDeposit'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'deposit_page')
            ->withQueryString();

        // Pandangan ADMIN: seluruh tunggakan kolektor ini, tanpa filter jendela
        // jatuh tempo. Admin bukan pengetuk pintu — dia butuh gambaran penuh
        // buat cross check. Jendela tagih cuma berlaku di Worklist Kolektor
        // (CollectorWorklistService::dueInvoices(), §10).
        $invoices = $this->worklist
            ->outstandingInvoices($collector)
            ->paginate(150, ['*'], 'invoice_page')
            ->withQueryString();

        $assignedCustomers = Customer::query()
            ->applyUserScope()
            ->where('collector_id', $collector->id)
            ->with('pop')
            ->orderBy('full_name')
            ->paginate(50, ['*'], 'assigned_page')
            ->withQueryString();

        $search = trim((string) $request->query('search', ''));
        $searchResults = null;
        if ($tab === 'assign' && $search !== '') {
            $searchResults = Customer::query()
                ->applyUserScope()
                ->where(function ($q) use ($collector) {
                    $q->whereNull('collector_id')
                        ->orWhere('collector_id', '!=', $collector->id);
                })
                ->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('cid', 'like', "%{$search}%");
                })
                ->with(['pop', 'collector'])
                ->orderBy('full_name')
                ->paginate(50, ['*'], 'search_page')
                ->withQueryString();
        }

        $activityChannels = $this->activityChannels($viewer);

        return view('collector-worksheet.show', compact(
            'collector', 'tab', 'invoices', 'assignedCustomers', 'search', 'searchResults',
            'deposits', 'balance', 'outstandingShortfall', 'openShortfallDeposits',
            'aging', 'visitHistory', 'receipts', 'receiptCandidates', 'activityChannels',
        ));
    }

    /**
     * Assign banyak pelanggan sekaligus ke seorang kolektor.
     *
     * Dua jalur masuk, SATU method dan satu blok guard:
     *   - tab Atur Pelanggan → kolektor dari route `{collector}`, sudah tetap
     *     dari halamannya;
     *   - panel index → kolektor dari `collector_id` di body, karena di sana
     *     tujuannya baru dipilih lewat dropdown.
     *
     * Versi body SENGAJA ditambahkan (2026-08-08). Sebelumnya panel index
     * menyusun URL tujuan di klien lewat Alpine (`:action`), dan Alpine dimuat
     * dari CDN — begitu CDN tak termuat, `form.action` jatuh ke URL halaman
     * sendiri dan assign diam-diam gagal tanpa pesan apa pun. Target POST aksi
     * yang mengubah data tidak boleh bergantung skrip pihak ketiga.
     *
     * Yang TIDAK boleh: menyalin guard POP ke method kedua. Dua jalur tulis
     * dengan dua salinan guard adalah cara tercepat salah satunya ketinggalan.
     */
    public function assign(Request $request, ?User $collector = null): RedirectResponse
    {
        $validated = $request->validate([
            // Wajib HANYA kalau kolektornya tak datang dari route parameter
            // (panel index memilih tujuan lewat dropdown).
            'collector_id' => [$collector ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'integer|exists:customers,id',
        ], [
            'collector_id.required' => 'Pilih kolektor tujuan dulu.',
            'customer_ids.required' => 'Centang minimal satu pelanggan.',
        ]);

        $collector ??= User::findOrFail($validated['collector_id']);

        $this->authorizeCollector($collector);

        $redirectTo = $request->input('redirect_to') === 'index'
            ? route('collector-worksheet.index')
            : route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'assign']);

        $customers = Customer::query()
            ->applyUserScope()
            ->whereIn('id', $validated['customer_ids'])
            ->get();

        if ($customers->isEmpty()) {
            return redirect($redirectTo)
                ->withErrors(['customer_ids' => 'Tidak ada pelanggan valid dalam scope Anda yang dipilih.']);
        }

        // Guard 2: POP tiap pelanggan wajib masuk scope kolektor.
        $accessService = app(EffectiveAccessService::class);
        $hasAllPop = $accessService->hasAllPopAccess($collector);
        $allowedPopIds = $hasAllPop ? [] : $accessService->getAllowedPopIds($collector);

        $outOfScope = $customers->filter(function (Customer $customer) use ($hasAllPop, $allowedPopIds) {
            if ($hasAllPop) {
                return false;
            }

            return ! in_array($customer->pop_id, $allowedPopIds, true);
        });

        if ($outOfScope->isNotEmpty()) {
            $names = $outOfScope->pluck('full_name')->implode(', ');

            return redirect($redirectTo)
                ->withErrors([
                    'customer_ids' => "Kolektor {$collector->name} tak punya akses POP untuk: {$names}. Assign dibatalkan untuk seluruh batch.",
                ]);
        }

        foreach ($customers as $customer) {
            $customer->update(['collector_id' => $collector->id]);
        }

        $this->kabariPerubahanRute(
            $collector,
            (int) $customers->first()->pop_id,
            'pelanggan_diassign',
            $customers->count(),
            $customers->count() === 1 ? $customers->first()->full_name : null,
        );

        return redirect($redirectTo)
            ->with('success', "{$customers->count()} pelanggan berhasil di-assign ke kolektor {$collector->name}.");
    }

    /**
     * Lepas SATU pelanggan dari kolektor ini (collector_id → null).
     */
    public function release(User $collector, Customer $customer): RedirectResponse
    {
        $this->authorizeCollector($collector);

        abort_unless(
            Customer::query()->applyUserScope()->whereKey($customer->id)->exists(),
            403,
            'Anda tidak memiliki akses ke pelanggan POP ini.'
        );

        if ((int) $customer->collector_id !== $collector->id) {
            return redirect()
                ->route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'assign'])
                ->withErrors(['customer_ids' => 'Pelanggan ini sudah bukan tanggung jawab kolektor ini.']);
        }

        $popId = (int) $customer->pop_id;

        $customer->update(['collector_id' => null]);

        $this->kabariPerubahanRute($collector, $popId, 'pelanggan_dilepas', 1, $customer->full_name);

        return redirect()
            ->route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'assign'])
            ->with('success', "{$customer->full_name} dilepas dari kolektor {$collector->name}.");
    }

    /**
     * Beri tahu kolektor bahwa rutenya berubah — notifikasi + siaran realtime.
     *
     * Sebelum ini assign/lepas TIDAK memberi tahu siapa pun. Kolektor baru tahu
     * saat kebetulan membuka Worklist, dan pelanggan yang dilepas SESUDAH dia
     * berangkat berarti dia menagih orang yang bukan lagi tanggungannya —
     * kunjungan sia-sia, dan kalau uangnya terlanjur diterima, uang yang tak
     * punya tempat mendarat di sistem.
     *
     * Kegagalan mengabari tidak boleh membatalkan perubahan rute yang sudah
     * tersimpan — pola sama dengan `CollectorDepositService::safelyNotify()`.
     */
    private function kabariPerubahanRute(User $collector, int $popId, string $aksi, int $jumlah, ?string $namaPelanggan): void
    {
        $diassign = $aksi === 'pelanggan_diassign';

        $pesan = $namaPelanggan !== null
            ? ($diassign
                ? "{$namaPelanggan} masuk ke rute penagihan Anda."
                : "{$namaPelanggan} dikeluarkan dari rute penagihan Anda — jangan ditagih lagi.")
            : ($diassign
                ? "{$jumlah} pelanggan masuk ke rute penagihan Anda."
                : "{$jumlah} pelanggan dikeluarkan dari rute penagihan Anda.");

        try {
            $collector->notify(new AppNotification(
                title: $diassign ? 'Rute bertambah' : 'Rute berkurang',
                message: $pesan,
                actionUrl: route('collector-worklist.index'),
                type: $diassign ? NotificationType::INFO : NotificationType::WARNING
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            CollectorActivityUpdated::dispatch($collector, $popId, $aksi, $jumlah, 0.0, $namaPelanggan);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function authorizeCollector(User $collector): void
    {
        abort_unless($collector->hasRole('kolektor'), 404, 'User ini bukan kolektor.');
    }
}
