@extends('layouts.app')

{{-- Monitoring Isolir (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §8) —
     pusat pemantauan lifecycle kasus isolir, BUKAN cuma daftar "yang belum
     dieksekusi". Kasus TIDAK PERNAH hilang begitu status layanan berubah
     (BR-10) — cuma berpindah tab sesuai status prosesnya. Query & controller
     SENDIRI (CustomerIsolirQueueController + IsolationCaseService). --}}
@php
    $pageTitle = 'Monitoring Isolir';
    $canExecuteIsolir = auth()->user()->hasPermission('customers.isolir');
    $canUnmark = auth()->user()->hasPermission('customers.isolir_queue.delete');
    $canDecide = auth()->user()->hasPermission('customers.isolir_queue.create');
    $canViewCustomerDetail = auth()->user()->hasPermission('customers.detail.view');

    // Dua antrean terpisah (follow-up 2026-11-19, diperkuat lagi hari ini) —
    // "Menunggu Isolir" = antrean REVIEW Admin, "Siap Isolir" = antrean
    // EKSEKUSI NOC. NOC cuma boleh isolir kasus yang statusnya sudah pindah
    // ke `readyForIsolationGroup()` (bukan sekadar kolom admin_decision).
    // Dicek ulang di sini SEKADAR UI (sembunyikan tombol Isolir); gerbang
    // aslinya di server (`CustomerController::toggleSuspend()`).
    $waitingStatusValues = array_map(fn ($s) => $s->value, \App\Enums\IsolationCaseStatus::waitingIsolationGroup());
    $readyStatusValues = array_map(fn ($s) => $s->value, \App\Enums\IsolationCaseStatus::readyForIsolationGroup());
    $decisionReady = fn ($case) => in_array($case->status->value, $readyStatusValues, true);

    $tabs = [
        'menunggu' => 'Menunggu Isolir',
        'siap' => 'Siap Isolir',
        'diisolir' => 'Sudah Diisolir',
        'pemulihan' => 'Pemulihan',
        'riwayat' => 'Riwayat',
    ];

    $statusOptions = collect($tabStatuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()]);

    // Warna titik timeline per jenis kejadian (permintaan "timeline mudah
    // dibaca", 2026-11-20) — dicocokkan dari substring `IsolationEventType`
    // value, bukan daftar 14 case manual: "gagal" selalu merah, "berhasil"/
    // "verified" selalu hijau, dst. Konsisten dengan §2.3 Design.md (warna
    // status cuma di teks/dot, bukan background blok).
    $eventDot = function (string $typeValue): string {
        return match (true) {
            str_contains($typeValue, 'failed') => 'bg-rose-500',
            str_contains($typeValue, 'succeeded'), str_contains($typeValue, 'verified') => 'bg-emerald-500',
            str_contains($typeValue, 'cancelled') => 'bg-slate-400',
            str_contains($typeValue, 'admin_marked') => 'bg-amber-500',
            default => 'bg-sky-500',
        };
    };
@endphp

@section('title', $pageTitle)
@section('page_title', $pageTitle)
@section('breadcrumb_parent_url', '/customers')

@section('content')
{{-- Header + tombol Import/Tambah. Tombolnya digerbangi permission masing-masing
     (customers.import.view / customers.create), jadi aman ikut di halaman Putus &
     Gagal juga — role yang cuma boleh melihat arsip tidak melihatnya. --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">{{ $pageTitle }}</h1>
        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">Memantau seluruh perjalanan kasus isolir — dari pelanggan menunggak, menunggu tindakan NOC, sudah diisolir,
    membayar, hingga dipulihkan — tanpa kehilangan jejak saat status layanan berubah..</p>
    </div>
</div>

{{-- KPI/ringkasan (§8.2) — angka ikut filter POP scope user, tanpa filter tab
     aktif (ringkasan lintas tab, bukan hitungan tab yang sedang ditampilkan).
     Tiap kolom = satu/beberapa status `IsolationCaseStatus` (lihat
     `IsolationCaseService::kpiSummary()`) dari tabel `isolation_cases`
     ter-scope POP user — BUKAN hitungan pelanggan, satu pelanggan bisa punya
     >1 kasus di tab Riwayat.

     Dirender sebagai SATU summary-strip (bar flat + divider vertikal),
     BUKAN grid of card — ini halaman Type A (data list) di
     design-system/whusnet-operasional/Design.md §1.3/§1.6: "Summary/KPI
     strip di Type A adalah flat bar, bukan grid card" (card per-KPI eksplisit
     didaftar sebagai pattern terlarang di §1.5). Tiap kolom tetap nge-link
     ke tab + filter status terkait (pintasan, bukan angka mati), dan warna
     cuma nempel di teks angka (§1.5 poin 4 — bukan background kolom).

     `isolation_in_progress`/`restoration_in_progress` TIDAK dapat kolom
     sendiri — status TRANSIEN yang cuma hidup selama satu request
     `toggleSuspend()` (ISOLATION_STARTED → ISOLATION_IN_PROGRESS →
     ISOLATION_SUCCEEDED di IsolationCaseService), praktis selalu 0 di antara
     request. Munculnya cuma sebagai badge kecil nempel di kolom induknya
     (Siap Isolir / Menunggu Pemulihan), HANYA kalau >0 (artinya ada kasus
     nyangkut di tengah proses — justru saat itu baru perlu kelihatan).
     "Gagal / Perlu Tindakan" menggabung `isolation_failed` (tab Siap Isolir)
     + `restoration_failed` (tab Pemulihan) — diarahkan ke Siap Isolir karena
     retry paling sering terjadi di sana. --}}
@php
    $kpiCols = [
        [
            'label' => 'Menunggu Isolir', 'value' => $kpi['waiting_isolation'],
            'sub' => 'Menunggak, belum diputuskan Admin.', 'value_class' => 'text-amber-600 dark:text-amber-400',
            'href' => route('customers.isolir-queue.index', ['tab' => 'menunggu']),
        ],
        [
            'label' => 'Siap Isolir', 'value' => $kpi['ready_for_isolation'],
            'sub' => 'Sudah "Eksekusi" Admin, antre NOC isolir.', 'value_class' => 'text-amber-600 dark:text-amber-400',
            'href' => route('customers.isolir-queue.index', ['tab' => 'siap', 'status' => 'ready_for_isolation']),
            'badge_label' => 'proses', 'badge_value' => $kpi['isolation_in_progress'],
            'badge_href' => route('customers.isolir-queue.index', ['tab' => 'siap', 'status' => 'isolation_in_progress']),
        ],
        [
            'label' => 'Sudah Diisolir', 'value' => $kpi['suspended'],
            'sub' => 'Layanan suspended, menunggu bayar/respon.', 'value_class' => 'text-purple-600 dark:text-purple-400',
            'href' => route('customers.isolir-queue.index', ['tab' => 'diisolir']),
        ],
        [
            'label' => 'Menunggu Pemulihan', 'value' => $kpi['restoration_pending'],
            'sub' => 'Terdeteksi lunas, menunggu NOC aktifkan.', 'value_class' => 'text-sky-600 dark:text-sky-400',
            'href' => route('customers.isolir-queue.index', ['tab' => 'pemulihan', 'status' => 'restoration_pending']),
            'badge_label' => 'proses', 'badge_value' => $kpi['restoration_in_progress'],
            'badge_href' => route('customers.isolir-queue.index', ['tab' => 'pemulihan', 'status' => 'restoration_in_progress']),
        ],
        [
            'label' => 'Gagal / Perlu Tindakan', 'value' => $kpi['failed'],
            'sub' => 'Isolir ATAU aktivasi-kembali gagal.', 'value_class' => 'text-red-600 dark:text-red-400',
            'href' => route('customers.isolir-queue.index', ['tab' => 'siap', 'status' => 'isolation_failed']),
        ],
        [
            'label' => 'Selesai Hari Ini', 'value' => $kpi['completed_today'],
            'sub' => 'Kasus ditutup "completed" hari ini.', 'value_class' => 'text-emerald-600 dark:text-emerald-400',
            'href' => route('customers.isolir-queue.index', ['tab' => 'riwayat', 'status' => 'completed']),
        ],
    ];
@endphp
<div class="flex flex-wrap @min-[72rem]:flex-nowrap bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-lg mb-6 overflow-hidden">
    @foreach($kpiCols as $i => $col)
    <a href="{{ $col['href'] }}"
       title="{{ $col['sub'] }}"
       class="group flex-1 min-w-[9rem] px-4 py-3 flex flex-col gap-1 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors {{ $i > 0 ? 'border-t @min-[72rem]:border-t-0 @min-[72rem]:border-l' : '' }}">
        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $col['label'] }}</span>
        <div class="flex items-baseline gap-2">
            <span class="font-mono text-lg font-semibold {{ $col['value_class'] }}">{{ $col['value'] }}</span>
            @if(($col['badge_value'] ?? 0) > 0)
            <span onclick="event.preventDefault(); event.stopPropagation(); window.location.href = '{{ $col['badge_href'] }}';"
                  class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer">
                {{ $col['badge_value'] }} {{ $col['badge_label'] }}
            </span>
            @endif
        </div>
        <span class="text-[10.5px] leading-snug text-slate-400 dark:text-slate-500">{{ $col['sub'] }}</span>
    </a>
    @endforeach
</div>

<div class="@container bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden mb-6">
    <div class="border-b border-slate-200 dark:border-slate-800 px-6 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/30 overflow-x-auto scrollbar-none">
        <nav class="flex gap-1 -mb-px">
            @foreach($tabs as $key => $label)
            <a href="{{ route('customers.isolir-queue.index', array_merge(request()->except(['tab', 'page', 'status']), ['tab' => $key])) }}"
               class="px-4 py-3 text-xs font-semibold border-b-2 whitespace-nowrap transition-colors {{ $tab === $key ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }}">
                {{ $label }} ({{ $counts[$key] }})
            </a>
            @endforeach
        </nav>
        <a href="{{ route('customers.index') }}" class="text-xs text-sky-600 dark:text-sky-400 hover:underline whitespace-nowrap">Lihat Semua Pelanggan</a>
    </div>

    {{-- Pencarian & filter (§8.5) — status proses dibatasi ke anggota tab
         aktif di server (CustomerIsolirQueueController::applyFilters()),
         select ini juga cuma menawarkan pilihan dalam grup itu. --}}
    <form method="GET" action="{{ route('customers.isolir-queue.index') }}" class="px-6 py-3.5 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-end gap-3 bg-slate-50/30 dark:bg-slate-900/20">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="flex-1 min-w-[12rem]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Cari</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Nama, CID, nomor kasus, nomor invoice"
                   class="w-full h-9 px-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:outline-none focus:border-sky-500">
        </div>
        <div class="min-w-[9rem]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">POP/Cabang</label>
            <select name="pop_id" class="w-full h-9 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:outline-none focus:border-sky-500">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                <option value="{{ $pop->id }}" {{ (string) $filters['pop_id'] === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>
        @if($statusOptions->count() > 1)
        <div class="min-w-[9rem]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Status Proses</label>
            <select name="status" class="w-full h-9 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:outline-none focus:border-sky-500">
                <option value="">Semua</option>
                @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" {{ $filters['status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="min-w-[8rem]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Dari Tgl Deteksi</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                   class="w-full h-9 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:outline-none focus:border-sky-500">
        </div>
        <div class="min-w-[8rem]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Sampai Tgl Deteksi</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                   class="w-full h-9 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:outline-none focus:border-sky-500">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="h-9 px-4 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold transition-colors cursor-pointer">Terapkan</button>
            @if($filters['q'] !== '' || $filters['pop_id'] || $filters['status'] || $filters['date_from'] || $filters['date_to'])
            <a href="{{ route('customers.isolir-queue.index', ['tab' => $tab]) }}" class="h-9 px-4 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold flex items-center hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Reset</a>
            @endif
        </div>
    </form>

    {{-- DESKTOP --}}
    <div class="hidden @min-[64rem]:block overflow-x-auto">
        @php
            // Sort header (permintaan user 2026-11-20) — toggle ASC → DESC →
            // balik ke default (detected_at DESC, lihat
            // CustomerIsolirQueueController::applySort()). Pola & markup sama
            // dengan List Pelanggan (_list_table.blade.php) biar konsisten
            // satu aplikasi: klik kolom baru → asc, klik lagi → desc, klik
            // lagi → lepas sort. request()->fullUrlWithQuery() ikut bawa
            // filter/tab yang lagi aktif, 'page' sengaja dihapus (hasil sort
            // baru selalu balik ke halaman 1).
            $sortHeaders = [
                'case_number' => 'Kasus',
                'customer_name' => 'Nama Pelanggan',
                'pop_name' => 'POP',
                'outstanding' => 'Piutang Kasus',
                'status' => 'Status Proses',
            ];
            $sortLink = function (string $key) use ($sort, $dir) {
                $isSorted = $sort === $key;
                $next = ! $isSorted
                    ? ['sort' => $key, 'dir' => 'asc', 'page' => null]
                    : ($dir === 'asc' ? ['sort' => $key, 'dir' => 'desc', 'page' => null] : ['sort' => null, 'dir' => null, 'page' => null]);

                return ['sorted' => $isSorted, 'href' => request()->fullUrlWithQuery($next)];
            };
        @endphp
        <table class="w-full min-w-[1140px] border-collapse text-left text-xs">
            <thead>
                <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    <th scope="col" class="py-3.5 px-4 w-12 text-center">No</th>
                    @foreach(['case_number', 'customer_name', 'pop_name'] as $key)
                        @php $s = $sortLink($key); @endphp
                        <th scope="col" class="py-3.5 px-4 select-none">
                            <a href="{{ $s['href'] }}" class="inline-flex items-center gap-1 hover:text-sky-600 dark:hover:text-sky-400 {{ $s['sorted'] ? 'text-sky-600 dark:text-sky-400' : '' }}" title="Klik untuk urutkan">
                                <span>{{ $sortHeaders[$key] }}</span>
                                <span class="text-[9px]" aria-hidden="true">{{ $s['sorted'] ? ($dir === 'desc' ? '▼' : '▲') : '' }}</span>
                            </a>
                        </th>
                    @endforeach
                    @php $s = $sortLink('outstanding'); @endphp
                    <th scope="col" class="py-3.5 px-4 text-right select-none">
                        <a href="{{ $s['href'] }}" class="inline-flex items-center justify-end gap-1 w-full hover:text-sky-600 dark:hover:text-sky-400 {{ $s['sorted'] ? 'text-sky-600 dark:text-sky-400' : '' }}" title="Klik untuk urutkan">
                            <span>Piutang Kasus</span>
                            <span class="text-[9px]" aria-hidden="true">{{ $s['sorted'] ? ($dir === 'desc' ? '▼' : '▲') : '' }}</span>
                        </a>
                    </th>
                    <th scope="col" class="py-3.5 px-4">Status Layanan</th>
                    @php $s = $sortLink('status'); @endphp
                    <th scope="col" class="py-3.5 px-4 select-none">
                        <a href="{{ $s['href'] }}" class="inline-flex items-center gap-1 hover:text-sky-600 dark:hover:text-sky-400 {{ $s['sorted'] ? 'text-sky-600 dark:text-sky-400' : '' }}" title="Klik untuk urutkan">
                            <span>Status Proses</span>
                            <span class="text-[9px]" aria-hidden="true">{{ $s['sorted'] ? ($dir === 'desc' ? '▼' : '▲') : '' }}</span>
                        </a>
                    </th>
                    <th scope="col" class="py-3.5 px-4">Riwayat</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-200">
                @forelse($cases as $case)
                    @php
                        $customer = $case->customer;
                    @endphp
                    <tr class="hover:bg-sky-50/40 dark:hover:bg-sky-950/20 transition-colors align-top" x-data="{ open: false }">
                        <td class="px-4 py-3.5 text-center text-slate-400 font-mono">
                            {{ ($cases->currentPage() - 1) * $cases->perPage() + $loop->iteration }}
                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-500 whitespace-nowrap">{{ $case->case_number }}</td>
                        <td class="px-4 py-3.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                            {{ $customer->full_name }}
                            <div class="text-[10px] font-mono font-normal text-sky-600 dark:text-sky-400">{{ $customer->display_id }}</div>
                        </td>
                        <td class="px-4 py-3.5 font-medium text-slate-700 dark:text-slate-300">
                            {{ $customer->pop->name ?? '-' }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono whitespace-nowrap {{ $case->current_outstanding_amount > 0 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-slate-400' }}">
                            {{ $case->current_outstanding_amount > 0 ? 'Rp '.number_format($case->current_outstanding_amount, 0, ',', '.') : 'Lunas' }}
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $customer->status === 'suspended' ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800/60' : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800/60' }}">
                                {{ $customer->status === 'suspended' ? 'Suspended' : 'Aktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full border bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
                                {{ $case->status->label() }}
                            </span>
                            <div class="text-[10px] text-slate-400 mt-0.5">{{ $case->trigger_type->label() }}</div>
                            @if(in_array($case->status->value, $waitingStatusValues, true))
                                @if($case->admin_decision?->value === 'tolerate')
                                <div class="mt-1 inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800/60"
                                     title="{{ $case->admin_decision_note }}{{ $case->admin_decision_until ? ' (sampai '.$case->admin_decision_until->format('d/m/Y').')' : '' }}">
                                    Toleransi{{ $case->admin_decision_until ? ' s.d. '.$case->admin_decision_until->format('d/m') : '' }}
                                </div>
                                @else
                                <div class="mt-1 inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700">Menunggu Keputusan</div>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3.5 max-w-xs text-slate-600 dark:text-slate-400">
                            {{-- Timeline (permintaan user 2026-11-20: "mudah dibaca") — event
                                 terbaru selalu tampil sebagai baris ringkas bertitik warna
                                 (lihat $eventDot), sisanya di balik toggle. Dulu semuanya
                                 10px rapat tanpa pembeda visual antar jenis kejadian —
                                 sekarang titik + garis sambung bikin urutan kejadian
                                 kebaca sebagai ALUR, bukan daftar teks lepas. --}}
                            @php $latest = $case->events->first(); @endphp
                            @if($latest)
                                <div class="flex items-start gap-2">
                                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $eventDot($latest->event_type->value) }}"></span>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $latest->event_type->label() }}</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">{{ $latest->actor?->name ?? 'Sistem' }} • {{ \App\Support\IndonesianDate::dateTime($latest->occurred_at) }}</div>
                                    </div>
                                </div>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                            @if($case->events->count() > 1)
                            <button type="button" @click="open = !open" class="text-[10px] font-semibold text-sky-600 dark:text-sky-400 hover:underline mt-1.5 ml-4 cursor-pointer">
                                <span x-text="open ? 'Tutup timeline' : 'Lihat riwayat lengkap ({{ $case->events->count() }})'"></span>
                            </button>
                            <div x-show="open" x-collapse class="mt-2 ml-1 space-y-3">
                                @foreach($case->events as $event)
                                <div class="flex items-start gap-2 {{ ! $loop->last ? 'relative' : '' }}">
                                    <span class="flex flex-col items-center shrink-0 self-stretch">
                                        <span class="w-2 h-2 rounded-full mt-1 {{ $eventDot($event->event_type->value) }}"></span>
                                        @if(! $loop->last)
                                        <span class="w-px flex-1 bg-slate-200 dark:bg-slate-700 mt-1"></span>
                                        @endif
                                    </span>
                                    <div class="min-w-0 pb-0.5">
                                        <div class="text-[11px] font-semibold text-slate-600 dark:text-slate-300">{{ $event->event_type->label() }}</div>
                                        @if($event->description)
                                        <div class="text-[10.5px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $event->description }}</div>
                                        @endif
                                        <div class="text-[10px] text-slate-400 mt-0.5">{{ $event->actor?->name ?? 'Sistem' }} • {{ \App\Support\IndonesianDate::dateTime($event->occurred_at) }}</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-2">
                                @if($canViewCustomerDetail)
                                <a href="{{ route('customers.show', $customer->id) }}"
                                   class="px-2.5 py-1 text-xs font-medium text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 rounded-lg hover:bg-sky-50 dark:hover:bg-sky-950/30 transition-colors">
                                    Detail
                                </a>
                                @endif
                                @if($canDecide && $customer->status === 'active' && in_array($case->status->value, $waitingStatusValues, true))
                                <form action="{{ route('customers.isolir-queue.decide', $case->service_id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="decision" value="execute">
                                    <button type="submit" class="px-2.5 py-1 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors cursor-pointer">
                                        Eksekusi
                                    </button>
                                </form>
                                <button type="button" class="px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-colors cursor-pointer"
                                        onclick="openToleransiDialog('{{ route('customers.isolir-queue.decide', $case->service_id) }}', @js($customer->full_name))">
                                    Toleransi
                                </button>
                                @endif
                                @if($canDecide && $customer->status === 'active' && in_array($case->status->value, $readyStatusValues, true))
                                <form action="{{ route('customers.isolir-queue.decide', $case->service_id) }}" method="POST"
                                      onsubmit="event.preventDefault(); window.confirmAction(@js('Tarik kasus '.$customer->full_name.' balik ke Menunggu Isolir? NOC tidak bisa isolir lagi sampai Admin mengeksekusi ulang.'), this);">
                                    @csrf
                                    <input type="hidden" name="decision" value="tolerate">
                                    <input type="hidden" name="note" value="Ditarik kembali ke Menunggu Isolir dari tab Siap Isolir">
                                    <button type="submit" class="px-2.5 py-1 text-xs font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                        Tarik ke Menunggu
                                    </button>
                                </form>
                                @endif
                                @if($canExecuteIsolir && $customer->status === 'active' && $decisionReady($case))
                                <form action="{{ route('customers.toggle-suspend', $customer->id) }}" method="POST"
                                      onsubmit="event.preventDefault(); window.confirmAction(@js('Isolir layanan pelanggan '.$customer->full_name.'?'), this);">
                                    @csrf
                                    <input type="hidden" name="note" value="Isolir Layanan dari Monitoring Isolir">
                                    <button type="submit" class="px-2.5 py-1 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition-colors cursor-pointer">
                                        Isolir
                                    </button>
                                </form>
                                @endif
                                @if($canExecuteIsolir && $customer->status === 'suspended')
                                <form action="{{ route('customers.toggle-suspend', $customer->id) }}" method="POST"
                                      onsubmit="event.preventDefault(); window.confirmAction(@js('Aktifkan kembali layanan pelanggan '.$customer->full_name.'?'), this);">
                                    @csrf
                                    <input type="hidden" name="note" value="Aktifkan Kembali dari Monitoring Isolir">
                                    <button type="submit" class="px-2.5 py-1 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors cursor-pointer">
                                        Aktifkan Kembali
                                    </button>
                                </form>
                                @endif
                                @if($canUnmark && (in_array($case->status->value, $waitingStatusValues, true) || in_array($case->status->value, $readyStatusValues, true)))
                                <form action="{{ route('customers.isolir-queue.destroy', $case->service_id) }}" method="POST"
                                      onsubmit="event.preventDefault(); window.confirmAction(@js('Batalkan kasus isolir '.$customer->full_name.'?'), this);">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1 text-xs font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                        Batalkan
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-slate-400">Tidak ada kasus di tab ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE & TABLET --}}
    <div class="@min-[64rem]:hidden p-4 bg-slate-50/40 dark:bg-slate-950/15 border-t border-slate-100 dark:border-slate-800/80">
        <div class="grid grid-cols-1 @min-[36rem]:grid-cols-2 gap-4">
            @forelse($cases as $case)
                @php
                    $customer = $case->customer;
                    $latest = $case->events->first();
                @endphp
                <article class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 space-y-3.5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="font-mono text-xs font-semibold text-sky-600 dark:text-sky-400">{{ $customer->display_id }}</span>
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate mt-0.5">{{ $customer->full_name }}</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $customer->pop->name ?? '-' }} • {{ $case->case_number }}</p>
                        </div>
                        <div class="shrink-0 text-right space-y-1">
                            <span class="inline-flex items-center px-2 py-0.5 text-[9px] font-bold rounded-full border bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
                                {{ $case->status->label() }}
                            </span>
                            @if(in_array($case->status->value, $waitingStatusValues, true))
                                @if($case->admin_decision?->value === 'tolerate')
                                <div class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800/60">Toleransi</div>
                                @else
                                <div class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700">Menunggu Keputusan</div>
                                @endif
                            @endif
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs border-t border-slate-100 dark:border-slate-800/80 pt-3">
                        <div class="min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Piutang Kasus</dt>
                            <dd class="font-mono font-semibold mt-0.5 {{ $case->current_outstanding_amount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                                {{ $case->current_outstanding_amount > 0 ? 'Rp '.number_format($case->current_outstanding_amount, 0, ',', '.') : 'Lunas' }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Status Layanan</dt>
                            <dd class="mt-0.5">{{ $customer->status === 'suspended' ? 'Suspended' : 'Aktif' }}</dd>
                        </div>
                        @if($latest)
                        <div class="col-span-2 min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Terakhir</dt>
                            <dd class="text-slate-600 dark:text-slate-400 mt-0.5">{{ $latest->event_type->label() }} — {{ $latest->actor?->name ?? 'Sistem' }}</dd>
                        </div>
                        @endif
                    </dl>

                    <div class="border-t border-slate-100 dark:border-slate-800/80 pt-3 flex flex-wrap gap-2 justify-end">
                        @if($canViewCustomerDetail)
                        <a href="{{ route('customers.show', $customer->id) }}"
                           class="flex-1 sm:flex-none h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 rounded-lg hover:bg-sky-50 dark:hover:bg-sky-950/30 transition-colors flex items-center justify-center cursor-pointer">
                            Detail
                        </a>
                        @endif
                        @if($canDecide && $customer->status === 'active' && in_array($case->status->value, $waitingStatusValues, true))
                        <form action="{{ route('customers.isolir-queue.decide', $case->service_id) }}" method="POST" class="flex-1 sm:flex-none flex">
                            @csrf
                            <input type="hidden" name="decision" value="execute">
                            <button type="submit" class="w-full h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors cursor-pointer">
                                Eksekusi
                            </button>
                        </form>
                        <button type="button" class="flex-1 sm:flex-none h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-colors cursor-pointer"
                                onclick="openToleransiDialog('{{ route('customers.isolir-queue.decide', $case->service_id) }}', @js($customer->full_name))">
                            Toleransi
                        </button>
                        @endif
                        @if($canDecide && $customer->status === 'active' && in_array($case->status->value, $readyStatusValues, true))
                        <form action="{{ route('customers.isolir-queue.decide', $case->service_id) }}" method="POST" class="flex-1 sm:flex-none flex"
                              onsubmit="event.preventDefault(); window.confirmAction(@js('Tarik kasus '.$customer->full_name.' balik ke Menunggu Isolir?'), this);">
                            @csrf
                            <input type="hidden" name="decision" value="tolerate">
                            <input type="hidden" name="note" value="Ditarik kembali ke Menunggu Isolir dari tab Siap Isolir">
                            <button type="submit" class="w-full h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                Tarik ke Menunggu
                            </button>
                        </form>
                        @endif
                        @if($canExecuteIsolir && $customer->status === 'active' && $decisionReady($case))
                        <form action="{{ route('customers.toggle-suspend', $customer->id) }}" method="POST" class="flex-1 sm:flex-none flex"
                              onsubmit="event.preventDefault(); window.confirmAction(@js('Isolir layanan pelanggan '.$customer->full_name.'?'), this);">
                            @csrf
                            <input type="hidden" name="note" value="Isolir Layanan dari Monitoring Isolir">
                            <button type="submit" class="w-full h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition-colors cursor-pointer">
                                Isolir
                            </button>
                        </form>
                        @endif
                        @if($canExecuteIsolir && $customer->status === 'suspended')
                        <form action="{{ route('customers.toggle-suspend', $customer->id) }}" method="POST" class="flex-1 sm:flex-none flex"
                              onsubmit="event.preventDefault(); window.confirmAction(@js('Aktifkan kembali layanan pelanggan '.$customer->full_name.'?'), this);">
                            @csrf
                            <input type="hidden" name="note" value="Aktifkan Kembali dari Monitoring Isolir">
                            <button type="submit" class="w-full h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors cursor-pointer">
                                Aktifkan Kembali
                            </button>
                        </form>
                        @endif
                        @if($canUnmark && (in_array($case->status->value, $waitingStatusValues, true) || in_array($case->status->value, $readyStatusValues, true)))
                        <form action="{{ route('customers.isolir-queue.destroy', $case->service_id) }}" method="POST" class="flex-1 sm:flex-none flex"
                              onsubmit="event.preventDefault(); window.confirmAction(@js('Batalkan kasus isolir '.$customer->full_name.'?'), this);">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                Batalkan
                            </button>
                        </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="col-span-full py-8 text-center text-slate-400">Tidak ada kasus di tab ini.</div>
            @endforelse
        </div>
    </div>

    @include('customers.partials._list_pagination', ['customers' => $cases])
</div>
@endsection

@push('scripts')
<script>
    {{-- Dialog "Toleransi" (follow-up 2026-11-19) — catatan wajib + tanggal
         opsional, fetch form POST biasa (bukan JSON) karena kita SUDAH di
         halaman Monitoring Isolir sendiri: `back()->withErrors()` di sini
         aman, cuma menampilkan ulang halaman list ini dengan pesan error,
         beda dengan kasus "Tandai Manual" di List Pelanggan yang dipanggil
         dari modal di atas halaman lain (lihat CLAUDE.md aturan 3-pola). --}}
    function openToleransiDialog(actionUrl, customerName) {
        const fieldId = 'toleransi-note';
        const untilId = 'toleransi-until';
        const errorId = fieldId + '-error';

        const doSubmit = () => {
            const note = (document.getElementById(fieldId)?.value || '').trim();
            if (note === '') {
                document.getElementById(errorId)?.classList.remove('hidden');
                document.getElementById(fieldId)?.focus();
                return;
            }

            window.Dialog.close();

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;
            form.innerHTML = `
                <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content ?? ''}">
                <input type="hidden" name="decision" value="tolerate">
                <input type="hidden" name="note" value="${note.replace(/"/g, '&quot;')}">
                <input type="hidden" name="until" value="${document.getElementById(untilId)?.value ?? ''}">
            `;
            document.body.appendChild(form);
            if (typeof NProgress !== 'undefined') NProgress.start();
            form.submit();
        };

        window.Dialog.show({
            title: 'Toleransi Isolir',
            icon: 'warning',
            contentHtml: `
                <p class="mb-3">Tandai pelanggan "${customerName}" ditoleransi — NOC tidak bisa isolir sampai keputusan ini diubah. Catatan/alasan wajib diisi:</p>
                <textarea id="${fieldId}" rows="3" maxlength="500"
                    placeholder="Contoh: Sudah janji bayar minggu ini, konfirmasi langsung ke kolektor."
                    class="w-full text-sm rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all mb-2"></textarea>
                <span id="${errorId}" class="hidden text-xs text-rose-600 font-semibold block mb-2">Catatan wajib diisi.</span>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Toleransi sampai tanggal (opsional)</label>
                <input type="date" id="${untilId}" min="${new Date().toISOString().slice(0, 10)}"
                    class="w-full text-sm rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-2.5 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            `,
            buttons: [
                { text: 'Batal', type: 'secondary', onClick: () => window.Dialog.close() },
                { text: 'Tandai Toleransi', type: 'primary', onClick: () => doSubmit() },
            ],
        });

        setTimeout(() => document.getElementById(fieldId)?.focus(), 50);
    }
</script>
<script>
    {{-- Notifikasi "data berubah" realtime (docs/plan/Rancangan_Monitoring_
         Isolir_Pelanggan.md §21 Tahap 5) — SENGAJA cuma toast + saran muat
         ulang, bukan patch baris per baris: halaman ini sudah berisi banyak
         timeline per kasus (events/caseInvoices eager-loaded), jadi fetch +
         render ulang parsial lebih rawan drift daripada reload penuh.
         Channel sama dengan badge sidebar (layouts/app.blade.php) — POP yang
         kelihatan di halaman ini (dari filter/daftar) cukup, tidak perlu
         daftar POP pengguna secara penuh. --}}
    (function () {
        if (window.__isolirQueuePageRefreshBound) {
            return;
        }
        window.__isolirQueuePageRefreshBound = true;

        const popIds = @json($pops->pluck('id'));
        if (popIds.length === 0) {
            return;
        }

        let notified = false;
        const notify = () => {
            if (notified) {
                return;
            }
            notified = true;
            if (window.Toast && typeof window.Toast.info === 'function') {
                window.Toast.info('Monitoring Isolir', 'Ada perubahan data. Muat ulang halaman untuk melihat yang terbaru.', 8000);
            }
        };

        const attach = () => {
            if (window.__isolirQueuePageEchoBound || typeof window.Echo === 'undefined') {
                return;
            }
            window.__isolirQueuePageEchoBound = true;
            popIds.forEach((popId) => {
                window.Echo.private('isolation-cases.' + popId).listen('.IsolationCaseQueueChanged', notify);
                window.Echo.private('customers-list.' + popId).listen('.CustomerVerificationStatusChanged', notify);
                window.Echo.private('invoices.' + popId).listen('.InvoiceStatusUpdated', notify);
            });
        };

        if (typeof window.Echo !== 'undefined') {
            attach();
        } else {
            window.addEventListener('echo:ready', attach, { once: true });
        }
    })();
</script>
@endpush
