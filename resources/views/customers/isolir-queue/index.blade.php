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
@endphp

@section('title', $pageTitle)
@section('page_title', $pageTitle)
@section('breadcrumb_parent_url', '/customers')

@section('content')
<div class="mb-4 text-xs text-slate-500 dark:text-slate-400">
    Memantau seluruh perjalanan kasus isolir — dari pelanggan menunggak, menunggu tindakan NOC, sudah diisolir,
    membayar, hingga dipulihkan — tanpa kehilangan jejak saat status layanan berubah.
</div>

{{-- KPI/ringkasan (§8.2) — angka ikut filter POP scope user, tanpa filter tab aktif
     (ringkasan lintas tab, bukan hitungan tab yang sedang ditampilkan). Tiap
     kartu = satu/beberapa status `IsolationCaseStatus` (lihat
     `IsolationCaseService::kpiSummary()`) dari tabel `isolation_cases`
     ter-scope POP user — BUKAN hitungan pelanggan, satu pelanggan bisa punya
     >1 kasus di tab Riwayat. Kartu nge-link ke tab + filter status terkait,
     jadi berfungsi sebagai pintasan, bukan cuma angka mati.

     Disederhanakan 8 → 6 kartu (permintaan user 2026-11-20, "cuma yang
     benar-benar dibutuhkan"): `isolation_in_progress`/`restoration_in_progress`
     DICABUT jadi kartu sendiri — keduanya status TRANSIEN yang cuma hidup
     selama satu request `toggleSuspend()` (lihat ISOLATION_STARTED →
     ISOLATION_IN_PROGRESS → ISOLATION_SUCCEEDED di IsolationCaseService),
     praktis selalu 0 kalau dilihat di antara request, jadi bukan kartu yang
     "benar-benar dibutuhkan" buat dipantau terus. Datanya TIDAK dibuang,
     cuma dipindah jadi badge kecil nempel di kartu induknya (Siap Isolir /
     Menunggu Pemulihan) dan HANYA muncul kalau nilainya > 0 — itu artinya
     ada kasus yang nyangkut di tengah proses (biasanya job gagal setengah
     jalan), justru saat itu baru perlu kelihatan. "Gagal / Perlu Tindakan"
     tetap menggabung `isolation_failed` (tab Siap Isolir) +
     `restoration_failed` (tab Pemulihan) — diarahkan ke Siap Isolir karena
     retry paling sering terjadi di sana, captionnya menyebut keduanya. --}}
<div class="grid grid-cols-2 @min-[40rem]:grid-cols-3 @min-[64rem]:grid-cols-6 gap-3 mb-6">
    @php
        $kpiCards = [
            [
                'label' => 'Menunggu Isolir', 'value' => $kpi['waiting_isolation'],
                'caption' => 'Menunggak, belum diputuskan Admin.',
                'accent' => 'border-l-amber-500', 'icon_bg' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400',
                'value_class' => 'text-amber-600 dark:text-amber-400',
                'icon' => 'M12 6v6l4 2M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z',
                'href' => route('customers.isolir-queue.index', ['tab' => 'menunggu']),
            ],
            [
                'label' => 'Siap Isolir', 'value' => $kpi['ready_for_isolation'],
                'caption' => 'Sudah "Eksekusi" Admin, antre NOC isolir.',
                'accent' => 'border-l-amber-500', 'icon_bg' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400',
                'value_class' => 'text-amber-600 dark:text-amber-400',
                'icon' => 'M13 2 3 14h7l-1 8 10-12h-7l1-8Z',
                'href' => route('customers.isolir-queue.index', ['tab' => 'siap', 'status' => 'ready_for_isolation']),
                'sub_label' => 'proses', 'sub_value' => $kpi['isolation_in_progress'],
                'sub_href' => route('customers.isolir-queue.index', ['tab' => 'siap', 'status' => 'isolation_in_progress']),
            ],
            [
                'label' => 'Sudah Diisolir', 'value' => $kpi['suspended'],
                'caption' => 'Layanan aktif suspended, menunggu pelanggan bayar/respon.',
                'accent' => 'border-l-rose-500', 'icon_bg' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400',
                'value_class' => 'text-rose-600 dark:text-rose-400',
                'icon' => 'M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2ZM8 11V7a4 4 0 1 1 8 0v4',
                'href' => route('customers.isolir-queue.index', ['tab' => 'diisolir']),
            ],
            [
                'label' => 'Menunggu Pemulihan', 'value' => $kpi['restoration_pending'],
                'caption' => 'Terdeteksi lunas, menunggu NOC aktifkan kembali.',
                'accent' => 'border-l-sky-500', 'icon_bg' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400',
                'value_class' => 'text-sky-600 dark:text-sky-400',
                'icon' => 'M3 12a9 9 0 0 1 15-6.7L21 8M21 3v6h-6M21 12a9 9 0 0 1-15 6.7L3 16m-0-1v6h6',
                'href' => route('customers.isolir-queue.index', ['tab' => 'pemulihan', 'status' => 'restoration_pending']),
                'sub_label' => 'proses', 'sub_value' => $kpi['restoration_in_progress'],
                'sub_href' => route('customers.isolir-queue.index', ['tab' => 'pemulihan', 'status' => 'restoration_in_progress']),
            ],
            [
                'label' => 'Gagal / Perlu Tindakan', 'value' => $kpi['failed'],
                'caption' => 'Eksekusi isolir ATAU aktivasi-kembali gagal — cek & ulangi manual.',
                'accent' => 'border-l-red-500', 'icon_bg' => 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400',
                'value_class' => 'text-red-600 dark:text-red-400',
                'icon' => 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z',
                'href' => route('customers.isolir-queue.index', ['tab' => 'siap', 'status' => 'isolation_failed']),
            ],
            [
                'label' => 'Selesai Hari Ini', 'value' => $kpi['completed_today'],
                'caption' => 'Kasus ditutup "completed" (lunas & pulih) hari ini.',
                'accent' => 'border-l-emerald-500', 'icon_bg' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400',
                'value_class' => 'text-emerald-600 dark:text-emerald-400',
                'icon' => 'M20 6 9 17l-5-5',
                'href' => route('customers.isolir-queue.index', ['tab' => 'riwayat', 'status' => 'completed']),
            ],
        ];
    @endphp
    @foreach($kpiCards as $card)
    <a href="{{ $card['href'] }}"
       title="{{ $card['caption'] }}"
       class="group bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800/80 border-l-4 {{ $card['accent'] }} p-3.5 flex flex-col gap-2 hover:shadow-sm hover:-translate-y-0.5 transition-all">
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 leading-tight">{{ $card['label'] }}</span>
            <span class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center {{ $card['icon_bg'] }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="{{ $card['icon'] }}"></path>
                </svg>
            </span>
        </div>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl font-bold {{ $card['value_class'] }}">{{ $card['value'] }}</span>
            @if(($card['sub_value'] ?? 0) > 0)
            <span onclick="event.preventDefault(); event.stopPropagation(); window.location.href = '{{ $card['sub_href'] }}';"
                  class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer">
                {{ $card['sub_value'] }} {{ $card['sub_label'] }}
            </span>
            @endif
        </div>
        <p class="text-[10.5px] leading-snug text-slate-400 dark:text-slate-500 group-hover:text-slate-500 dark:group-hover:text-slate-400">{{ $card['caption'] }}</p>
    </a>
    @endforeach
</div>

<div class="@container bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden mb-6">
    <div class="border-b border-slate-200 dark:border-slate-800 px-6 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/30 overflow-x-auto">
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
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Status Tagihan</label>
            <select name="invoice_status" class="w-full h-9 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:outline-none focus:border-sky-500">
                <option value="">Semua</option>
                <option value="belum_lunas" {{ $filters['invoice_status'] === 'belum_lunas' ? 'selected' : '' }}>Belum Lunas</option>
                <option value="lunas" {{ $filters['invoice_status'] === 'lunas' ? 'selected' : '' }}>Lunas</option>
            </select>
        </div>
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
            @if($filters['q'] !== '' || $filters['pop_id'] || $filters['status'] || $filters['invoice_status'] || $filters['date_from'] || $filters['date_to'])
            <a href="{{ route('customers.isolir-queue.index', ['tab' => $tab]) }}" class="h-9 px-4 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold flex items-center hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Reset</a>
            @endif
        </div>
    </form>

    {{-- DESKTOP --}}
    <div class="hidden @min-[64rem]:block overflow-x-auto">
        <table class="w-full min-w-[1140px] border-collapse text-left text-xs">
            <thead>
                <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    <th scope="col" class="py-3.5 px-4 w-12 text-center">No</th>
                    <th scope="col" class="py-3.5 px-4">Kasus</th>
                    <th scope="col" class="py-3.5 px-4">Nama Pelanggan</th>
                    <th scope="col" class="py-3.5 px-4">POP</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Piutang Kasus</th>
                    <th scope="col" class="py-3.5 px-4">Status Layanan</th>
                    <th scope="col" class="py-3.5 px-4">Status Proses</th>
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
                            @php $latest = $case->events->first(); @endphp
                            @if($latest)
                                <div class="truncate">{{ $latest->event_type->label() }}</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $latest->actor?->name ?? 'Sistem' }} • {{ \App\Support\IndonesianDate::dateTime($latest->occurred_at) }}</div>
                            @else
                                -
                            @endif
                            @if($case->events->count() > 1)
                            <button type="button" @click="open = !open" class="text-[10px] text-sky-600 dark:text-sky-400 hover:underline mt-1 cursor-pointer">
                                <span x-text="open ? 'Tutup timeline' : 'Lihat timeline ({{ $case->events->count() }})'"></span>
                            </button>
                            <div x-show="open" x-collapse class="mt-2 space-y-1.5 border-l-2 border-slate-200 dark:border-slate-700 pl-2.5">
                                @foreach($case->events as $event)
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">
                                    <div class="font-semibold">{{ $event->event_type->label() }}</div>
                                    @if($event->description)<div>{{ $event->description }}</div>@endif
                                    <div>{{ $event->actor?->name ?? 'Sistem' }} • {{ \App\Support\IndonesianDate::dateTime($event->occurred_at) }}</div>
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
