@extends('layouts.app')

@section('title', 'Worksheet NOC — Ticket Service Desk')
@section('page_title', 'Worksheet NOC')

@php
    use App\Http\Controllers\TicketHistoryController;
    use App\Support\IndonesianDate;
@endphp

@section('content')
{{--
    Tabel padat + cari + filter + dua tab (ADHOC-09), bukan daftar kartu lagi.

    Tab `assign_fop` MURNI turunan data (handler=fop + jejak riwayat eskalasi ke
    NOC) — bukan window "Pending NOC" yang dihapus ADHOC-06. Gak ada aksi
    "terima/ambil" di halaman ini, dan tab itu read-only.

    Aksi diambil dari BARIS TERPILIH lewat drawer (x-ui.drawer), bukan kolom
    tombol per baris: 10 kolom + 4 tombol bikin tabel kedorong keluar layar.
--}}
<div class="space-y-5 pb-12" x-data="nocWorksheet()">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-text-main tracking-tight">Worksheet NOC</h1>
            <p class="text-xs text-text-muted mt-1 font-medium">
                @if($tab === 'assign_fop')
                    Tiket yang sudah NOC teruskan ke FOP. Progres pengerjaan lapangan dibaca di halaman Task FOP.
                @else
                    Ticket yang lagi diproses NOC — klik barisnya untuk melihat detail dan mengambil tindakan.
                @endif
            </p>
        </div>
        <span class="text-[11px] font-mono font-bold px-2.5 py-1 rounded-full bg-amber-600 text-white shrink-0">
            {{ $tickets->total() }} Ticket
        </span>
    </div>

    {{--
        Tab = link GET (bukan state Alpine): paginasi & filter ikut kebawa di URL,
        jadi halaman tetap bisa di-bookmark/dibagikan per tab.
    --}}
    <div class="flex items-center gap-1 p-1 rounded-lg bg-surface-muted border border-border w-fit text-xs">
        @foreach([
            'masuk' => ['label' => 'Tiket Masuk', 'badge' => 'bg-amber-600'],
            'assign_fop' => ['label' => 'Assign FOP', 'badge' => 'bg-sky-600'],
        ] as $tabValue => $meta)
            <a href="{{ route('noc.worksheet', array_merge(request()->except(['tab', 'page']), ['tab' => $tabValue])) }}"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors {{ $tab === $tabValue ? 'bg-surface text-text-main font-bold shadow-sm' : 'text-text-muted hover:text-text-main' }}">
                <span>{{ $meta['label'] }}</span>
                <span class="shrink-0 px-1.5 py-px rounded-full text-[10px] font-bold font-mono text-white {{ $meta['badge'] }}">{{ $tabCounts[$tabValue] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Filter Ringkas & Fungsional (ADHOC-09 Redesign) --}}
    @php
        $activeSecondaryFilters = array_filter(array_diff_key($filters, ['q' => '']));
        $hasActiveSecondary = count($activeSecondaryFilters) > 0;
        $totalActiveFilters = count(array_filter($filters));
    @endphp

    <form method="GET" action="{{ route('noc.worksheet') }}"
          x-data="{ showFilters: {{ $hasActiveSecondary ? 'true' : 'false' }} }"
          class="rounded-xl border border-border bg-surface shadow-xs transition-all">
        <input type="hidden" name="tab" value="{{ $tab }}">

        {{-- Baris Utama (Search Bar + Action Hub) --}}
        <div class="p-3 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            {{-- Input Cari Utama --}}
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="q" value="{{ $filters['q'] }}"
                       placeholder="Cari tiket, nama pelanggan, CID, desa, keluhan..."
                       class="w-full pl-9 pr-8 text-xs rounded-lg border border-border bg-background py-2 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                @if($filters['q'])
                    <a href="{{ route('noc.worksheet', array_merge(request()->except(['q', 'page']), ['tab' => $tab])) }}"
                       class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-text-muted hover:text-text-main"
                       title="Hapus pencarian">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif
            </div>

            {{-- Tombol Filter & Aksi --}}
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="showFilters = !showFilters"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-border bg-background hover:bg-surface-muted text-xs font-semibold text-text-secondary hover:text-text-main transition-colors cursor-pointer"
                        :class="{ 'border-sky-500 text-sky-600 dark:text-sky-400 bg-sky-50/50 dark:bg-sky-950/30': showFilters || {{ $hasActiveSecondary ? 'true' : 'false' }} }">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                    @if(count($activeSecondaryFilters) > 0)
                        <span class="px-1.5 py-0.5 text-[10px] font-bold font-mono rounded-full bg-sky-600 text-white">
                            {{ count($activeSecondaryFilters) }}
                        </span>
                    @endif
                    <svg class="w-3 h-3 text-text-muted transition-transform duration-200" :class="{ 'rotate-180': showFilters }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-sky-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-sky-700 active:scale-95 transition-all cursor-pointer shadow-xs">
                    Terapkan
                </button>

                @if($totalActiveFilters > 0)
                    <a href="{{ route('noc.worksheet', ['tab' => $tab]) }}"
                       class="px-3 py-2 rounded-lg text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </div>

        {{-- Ringkasan Filter Aktif (Pills) --}}
        @if($totalActiveFilters > 0)
            <div class="px-3 pb-3 flex items-center gap-1.5 flex-wrap border-t border-border/50 pt-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted mr-1">Filter Aktif:</span>

                @if($filters['q'])
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Cari:</span> "{{ $filters['q'] }}"
                        <a href="{{ route('noc.worksheet', array_merge(request()->except(['q', 'page']), ['tab' => $tab])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                @endif

                @if($filters['pop_id'])
                    @php $popName = collect($popOptions)->firstWhere('id', (int) $filters['pop_id'])?->name; @endphp
                    @if($popName)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                            <span class="text-text-muted">POP:</span> {{ $popName }}
                            <a href="{{ route('noc.worksheet', array_merge(request()->except(['pop_id', 'page']), ['tab' => $tab])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['issue_category_id'])
                    @php $catName = collect($categoryOptions)->firstWhere('id', (int) $filters['issue_category_id'])?->name; @endphp
                    @if($catName)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                            <span class="text-text-muted">Kategori:</span> {{ $catName }}
                            <a href="{{ route('noc.worksheet', array_merge(request()->except(['issue_category_id', 'page']), ['tab' => $tab])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['priority'])
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Prioritas:</span> {{ $filters['priority'] }}
                        <a href="{{ route('noc.worksheet', array_merge(request()->except(['priority', 'page']), ['tab' => $tab])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                @endif

                @if($filters['type'])
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Tipe:</span> {{ $filters['type'] }}
                        <a href="{{ route('noc.worksheet', array_merge(request()->except(['type', 'page']), ['tab' => $tab])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                @endif

                @if($filters['created_by'])
                    @php $creatorName = collect($creatorOptions)->firstWhere('id', (int) $filters['created_by'])?->name; @endphp
                    @if($creatorName)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                            <span class="text-text-muted">Pengirim:</span> {{ $creatorName }}
                            <a href="{{ route('noc.worksheet', array_merge(request()->except(['created_by', 'page']), ['tab' => $tab])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['date_from'] || $filters['date_to'])
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Tanggal:</span> {{ $filters['date_from'] ?: '—' }} s/d {{ $filters['date_to'] ?: '—' }}
                        <a href="{{ route('noc.worksheet', array_merge(request()->except(['date_from', 'date_to', 'page']), ['tab' => $tab])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                @endif
            </div>
        @endif

        {{-- Grid Filter Sekunder (Expandable) --}}
        <div x-show="showFilters" x-collapse x-cloak class="p-3 border-t border-border bg-surface-muted/40 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">POP / Cabang</label>
                    <select name="pop_id" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua POP</option>
                        @foreach($popOptions as $pop)
                            <option value="{{ $pop->id }}" @selected((string) $filters['pop_id'] === (string) $pop->id)>{{ $pop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Kategori Issue</label>
                    <select name="issue_category_id" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Kategori</option>
                        @foreach($categoryOptions as $category)
                            <option value="{{ $category->id }}" @selected((string) $filters['issue_category_id'] === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Prioritas</label>
                    <select name="priority" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Prioritas</option>
                        @foreach($priorityOptions as $priority)
                            <option value="{{ $priority->value }}" @selected($filters['priority'] === $priority->value)>{{ $priority->value }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Tipe Tiket</label>
                    <select name="type" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Tipe</option>
                        @foreach($typeOptions as $opt)
                            <option value="{{ $opt['value'] }}" @selected($filters['type'] === $opt['value'])>{{ $opt['value'] }} — {{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Dikirim Oleh</label>
                    <select name="created_by" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua User</option>
                        @foreach($creatorOptions as $creator)
                            <option value="{{ $creator->id }}" @selected((string) $filters['created_by'] === (string) $creator->id)>{{ $creator->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                           class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                           class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                </div>
            </div>
        </div>
    </form>

    {{-- Tabel — scroll horizontal sendiri, halaman gak ikut melebar --}}
    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-xs whitespace-nowrap">
                <thead class="bg-surface-muted dark:bg-slate-900/60 text-text-muted">
                    <tr class="text-left">
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Masuk</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Tiket</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Nama / CID</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">HP</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Desa</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">POP</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Aduan</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Kategori</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Prioritas</th>
                        @if($tab === 'assign_fop')
                            <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Status</th>
                            <th class="px-3 py-2.5 font-bold uppercase tracking-wider" title="Kapan tiket diserahkan ke FOP">Diserahkan</th>
                            <th class="px-3 py-2.5 font-bold uppercase tracking-wider" title="Yang mengirim tiket ke FOP">Dikirim Oleh</th>
                        @else
                            {{-- Umur = lama tiket nunggu di meja NOC, sinyal antrean menumpuk --}}
                            <th class="px-3 py-2.5 font-bold uppercase tracking-wider text-right" title="Lama tiket menunggu di meja NOC">Umur</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($tickets as $ticket)
                        @php
                            $actions = $ticket->actionFlagsFor(auth()->user());
                            $ageMinutes = (int) $ticket->created_at->diffInMinutes(now());
                            $ageLabel = sprintf('%dj %02dm', intdiv($ageMinutes, 60), $ageMinutes % 60);
                            $ageClass = match (true) {
                                $ageMinutes >= 1440 => 'text-rose-600 dark:text-rose-400 font-bold',
                                $ageMinutes >= 480 => 'text-amber-600 dark:text-amber-400 font-bold',
                                default => 'text-text-muted',
                            };

                        @endphp

                        {{--
                            URL aksi ditaruh di data-attribute baris: cuma dirender kalau
                            flag aksinya nyala, jadi tab read-only (assign_fop) gak pernah
                            memuat endpoint mutasi sama sekali. JS aksi bacanya dari
                            dataset baris waktu drawer men-dispatch 'ticket-drawer-action'.

                            Isi drawer TIDAK ikut di sini — diambil dari endpoint
                            tickets.detail-json (riwayat + lampiran cuma perlu buat satu
                            tiket yang dibuka, bukan 50 baris tabel).
                        --}}
                        <tr data-ticket-row="{{ $ticket->id }}"
                            data-ticket-code="{{ $ticket->ticket_number }}"
                            @if($actions['can_close']) data-url-close="{{ route('tickets.close', $ticket) }}" @endif
                            @if($actions['can_escalate_fop']) data-url-escalate="{{ route('tickets.escalate', $ticket) }}" @endif
                            @if($actions['can_return_to_helpdesk']) data-url-return="{{ route('tickets.return-to-helpdesk', $ticket) }}" @endif
                            @if($actions['can_cancel']) data-url-cancel="{{ route('tickets.cancel', $ticket) }}" @endif
                            @click="openDetail({{ $ticket->id }})"
                            class="hover:bg-amber-50/50 dark:hover:bg-slate-800/40 transition-colors cursor-pointer">
                            <td class="px-3 py-2.5 font-mono text-text-muted">{{ IndonesianDate::dateTime($ticket->created_at) }}</td>
                            <td class="px-3 py-2.5">
                                {{--
                                    Nomor tiket buka DRAWER, bukan halaman /tickets/{id}.
                                    Halaman detail penuh disisakan buat halaman arsip
                                    (Ticket Selesai/Dibatalkan/History) — di halaman kerja,
                                    keluar halaman berarti kehilangan filter & posisi scroll.
                                --}}
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 hover:underline">
                                    {{ $ticket->ticket_number }}
                                </span>
                                <span class="block text-[10px] font-mono text-text-muted">{{ $ticket->type->value }}</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="font-semibold text-text-main">{{ $ticket->customer->full_name ?? $ticket->customer_name ?? '—' }}</span>
                                <span class="block font-mono text-[10px] text-text-muted">{{ $ticket->customer?->display_id ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-text-secondary">{{ $ticket->customer_phone ?? '—' }}</td>
                            {{-- Snapshot saat tiket dibuat — sengaja bukan relasi desa terkini --}}
                            <td class="px-3 py-2.5 text-text-secondary">{{ $ticket->customer_village ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-text-secondary">{{ $ticket->pop?->name ?? '—' }}</td>
                            <td class="px-3 py-2.5 max-w-xs truncate text-text-secondary" title="{{ $ticket->detail_keluhan }}">{{ $ticket->detail_keluhan }}</td>
                            <td class="px-3 py-2.5 text-text-secondary">{{ $ticket->issueCategory?->name ?? '—' }}</td>
                            <td class="px-3 py-2.5">
                                @if($ticket->priority)
                                    <span class="inline-block px-2 py-0.5 rounded border text-[10px] font-bold
                                        @switch($ticket->priority->value)
                                            @case('Urgent') bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800/50 text-rose-700 dark:text-rose-400 @break
                                            @case('High') bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-400 @break
                                            @default bg-surface-muted border-border text-text-secondary
                                        @endswitch">
                                        {{ $ticket->priority->value }}
                                    </span>
                                @else
                                    <span class="text-text-muted">—</span>
                                @endif
                            </td>
                            @if($tab === 'assign_fop')
                                <td class="px-3 py-2.5">
                                    <span class="inline-block px-2 py-0.5 rounded border text-[10px] font-bold {{ TicketHistoryController::statusBadgeFor($ticket) }}">
                                        {{ TicketHistoryController::statusLabelFor($ticket) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-text-muted">
                                    {{ $ticket->resolved_at ? IndonesianDate::dateTime($ticket->resolved_at) : '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-text-secondary">{{ $ticket->escalatedToFopBy()?->name ?? '—' }}</td>
                            @else
                                <td class="px-3 py-2.5 font-mono text-right {{ $ageClass }}">{{ $ageLabel }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $tab === 'assign_fop' ? 12 : 10 }}" class="px-3 py-10 text-center text-text-muted">
                                @if($tab === 'assign_fop')
                                    Belum ada tiket yang diteruskan NOC ke FOP.
                                @else
                                    Belum ada ticket yang diproses NOC.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $tickets->links() }}</div>

    {{--
        Drawer detail kanan — partial BERSAMA dengan Worksheet Helpdesk
        (tickets/partials/detail-drawer.blade.php). Isinya di-fetch dari
        tickets.detail-json; tombol aksinya cuma men-dispatch
        'ticket-drawer-action', yang ditangani JS halaman ini di bawah.
    --}}
    @include('tickets.partials.detail-drawer')

    {{--
        Konfirmasi + input alasan buat semua tombol aksi — numpang window.Dialog
        global (lihat tickets/partials/action-dialog.blade.php), bukan modal
        sendiri per halaman.
    --}}
    @include('tickets.partials.action-dialog')
</div>
@endsection

@push('scripts')
<script>
    function nocWorksheet() {
        return {
            openDetail(id) {
                window.dispatchEvent(new CustomEvent('open-ticket-drawer', { detail: { id } }));
            },
        };
    }

    /**
     * Tombol aksi di drawer cuma men-dispatch niat; konfirmasi + POST tetap di
     * halaman ini (satu sumber per halaman). URL endpoint dibaca dari
     * data-attribute baris — cuma dirender kalau flag aksinya nyala, jadi tab
     * read-only gak punya URL buat dipakai sama sekali.
     */
    window.addEventListener('ticket-drawer-action', (event) => {
        const { id, action } = event.detail;
        const row = document.querySelector(`[data-ticket-row="${id}"]`);

        if (! row) {
            return;
        }

        const code = row.dataset.ticketCode;

        const map = {
            close: {
                url: row.dataset.urlClose,
                payload: {},
                title: 'Selesaikan Tiket',
                label: 'Apa yang sudah dikerjakan? (opsional)',
                message: `Tandai tiket ${code} selesai?`,
                required: false,
            },
            fop: {
                url: row.dataset.urlEscalate,
                payload: { target: 'fop' },
                title: 'Kirim Tiket ke FOP',
                label: 'Catatan buat FOP (opsional)',
                message: `Kirim tiket ${code} ke FOP?`,
                required: false,
            },
            return: {
                url: row.dataset.urlReturn,
                payload: {},
                title: 'Kembalikan ke Helpdesk',
                label: 'Alasan dikembalikan (opsional)',
                message: `Kembalikan tiket ${code} ke Helpdesk?`,
                required: false,
            },
            cancel: {
                url: row.dataset.urlCancel,
                payload: {},
                title: 'Batalkan Tiket',
                label: 'Alasan pembatalan (wajib diisi)',
                message: `Batalkan tiket ${code}?`,
                required: true,
            },
        }[action];

        if (! map || ! map.url) {
            return;
        }

        window.confirmTicketAction({
            title: map.title,
            message: map.message,
            label: map.label,
            required: map.required,
            confirmText: map.required ? 'Ya, Batalkan' : 'Ya, Lanjutkan',
            confirmType: map.required ? 'danger' : 'primary',
            icon: map.required ? 'error' : 'warning',
            onConfirm: (reason) => performTicketAction(id, map.url, { ...map.payload, reason }),
        });
    });

    /**
     * POST ke endpoint TicketController yang sudah ada (halaman ini gak punya
     * logic mutasi sendiri), lalu tutup drawer & buang barisnya dari tabel —
     * tiket yang sudah diaksi gak lagi masuk tab ini.
     */
    async function performTicketAction(ticketId, url, payload) {
        const row = document.querySelector(`[data-ticket-row="${ticketId}"]`);
        const buttons = document.querySelectorAll('[data-drawer-action]');
        buttons.forEach(b => { b.disabled = true; });

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });
            const body = await res.json();

            if (!res.ok) {
                window.Toast?.error('Gagal', body.message || 'Aksi gagal, coba lagi.');
                buttons.forEach(b => { b.disabled = false; });
                return;
            }

            window.Toast?.success('Berhasil', body.message);
            window.dispatchEvent(new CustomEvent('close-ticket-drawer'));

            if (row) {
                row.style.transition = 'opacity 0.25s';
                row.style.opacity = '0';
                setTimeout(() => { row.remove(); }, 250);
            }
        } catch (e) {
            window.Toast?.error('Gagal', 'Aksi gagal, coba lagi.');
            buttons.forEach(b => { b.disabled = false; });
        }
    }
</script>
@endpush
