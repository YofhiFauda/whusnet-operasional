@extends('layouts.app')

@section('title', 'Create Service Ticket — ISP NOC')
@section('page_title', 'Create Service Ticket')

@section('content')
<div x-data="ticketForm()" class="max-w-5xl mx-auto space-y-6 pb-12">

    {{-- Top Navigation & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs text-text-muted">
            <a href="{{ route('tickets.bucket', 'masuk') }}" class="hover:text-sky-600 transition-colors flex items-center gap-1">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Ticketing
            </a>
            <span>/</span>
            <span class="text-text-main font-semibold">Create New Ticket</span>
        </div>

        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 border border-border text-[11px] font-bold text-text-muted uppercase tracking-wider">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            NOC Draft Mode
        </div>
    </div>

    {{-- Main High-Density Service Ticket Form Container --}}
    <div class="bg-surface border border-border rounded-xl shadow-sm overflow-hidden transition-all">
        
        {{-- Form Header --}}
        <div class="px-6 py-4 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold text-text-main tracking-tight flex items-center gap-2">
                    <svg class="h-5 w-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                    Create Service Ticket
                </h1>
                <p class="text-[11px] text-text-muted font-medium uppercase tracking-wider mt-0.5">
                    Internal Operations / NOC Entry & FOP Sync
                </p>
            </div>
            
            <a href="{{ route('tickets.bucket', 'masuk') }}" 
               class="text-xs text-text-muted hover:text-text-main transition-colors flex items-center gap-1">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Tutup
            </a>
        </div>

        @if($errors->any())
            <div class="m-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60">
                <div class="flex items-center gap-2 text-rose-800 dark:text-rose-300 font-semibold text-xs mb-1.5">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Ticket Gagal Dikirim — Periksa Inputan Berikut:
                </div>
                <ul class="list-disc list-inside text-xs text-rose-700 dark:text-rose-400 space-y-0.5 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="divide-y divide-border">
            @csrf

            {{-- SECTION 01: CLASSIFICATION --}}
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-1 h-4 bg-sky-600 rounded-full"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">SECTION 01: CLASSIFICATION</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Tipe Ticket --}}
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                            Ticket Type <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <select name="type" required
                                    class="w-full text-sm rounded-lg border border-border bg-background px-3 py-2.5 text-text-main appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-mono">
                                <option value="" disabled @selected(!old('type'))>-- SELECT CLASSIFICATION --</option>
                                @foreach($typeOptions as $opt)
                                    <option value="{{ $opt['value'] }}" @selected(old('type') === $opt['value'])>
                                        {{ $opt['value'] }} — {{ $opt['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <svg class="h-4 w-4 absolute right-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>

                    {{-- Prioritas --}}
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                            Prioritas <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <select name="priority" required
                                    class="w-full text-sm rounded-lg border border-border bg-background px-3 py-2.5 text-text-main appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                                @foreach($priorityOptions as $p)
                                    <option value="{{ $p->value }}" @selected(old('priority', 'Medium') === $p->value)>
                                        {{ $p->value }} {{ $p->value === 'Critical' ? '⚡ (Urgent Response)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <svg class="h-4 w-4 absolute right-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION 02: CUSTOMER IDENTITY --}}
            <div class="p-6 bg-slate-50/30 dark:bg-slate-900/20 space-y-4">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-1 h-4 bg-sky-600 rounded-full"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">SECTION 02: CUSTOMER IDENTITY</h2>
                </div>

                {{-- Search Box Autocomplete --}}
                <div class="space-y-1.5 relative">
                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                        Search Customer Data <span class="text-rose-500">*</span>
                    </label>

                    <div class="relative flex items-center">
                        <svg class="h-4 w-4 absolute left-3 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>

                        <input type="text" x-model="cidQuery" @input.debounce.300ms="searchCustomer()"
                               :disabled="selected !== null"
                               placeholder="ENTER CID OR NAME..."
                               class="w-full text-sm font-mono tracking-wide rounded-lg border border-border bg-surface pl-9 pr-10 py-2.5 text-text-main placeholder:text-text-muted placeholder:font-sans focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 disabled:bg-slate-100 dark:disabled:bg-slate-800 disabled:text-text-muted transition-all">

                        <button type="button" x-show="selected" @click="clearSelection()" 
                                class="absolute right-3 text-xs font-bold text-sky-600 hover:text-sky-700 underline cursor-pointer">
                            Ganti
                        </button>

                        <button type="button" x-show="cidQuery && !selected" @click="cidQuery = ''; results = []"
                                class="absolute right-3 text-text-muted hover:text-text-main">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <input type="hidden" name="customer_id" :value="selected?.id ?? ''">

                    {{-- Dropdown Search Results --}}
                    <div x-show="results.length > 0 && !selected" x-cloak
                         class="absolute z-30 mt-1 w-full bg-surface border border-border rounded-lg shadow-xl max-h-60 overflow-y-auto divide-y divide-border">
                        <template x-for="r in results" :key="r.id">
                            <button type="button" @click="pick(r)"
                                    class="w-full text-left px-4 py-3 text-sm hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-between cursor-pointer group">
                                <div>
                                    <div class="font-bold text-text-main group-hover:text-sky-600 transition-colors" x-text="r.nama"></div>
                                    <div class="text-xs text-text-muted font-mono" x-text="r.cid"></div>
                                </div>
                                <div class="text-right text-xs">
                                    <span class="inline-block px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 font-bold text-[10px]" x-text="r.pop || 'NO POP'"></span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <p x-show="searching" x-cloak class="text-xs text-sky-600 animate-pulse mt-1 flex items-center gap-1">
                        <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Mencari data pelanggan di database...
                    </p>
                    
                    <p x-show="!searching && searched && results.length === 0 && !selected" x-cloak
                       class="text-xs text-rose-500 mt-1 font-medium">
                        Pelanggan tidak ditemukan. Silakan periksa kembali CID atau Nama.
                    </p>
                </div>

                {{-- Structured Technical Customer Grid (9-Cell Technical Data Box) --}}
                <div class="border border-border rounded-lg overflow-hidden bg-border shadow-xs">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-px bg-border">

                        {{-- Cell 1: Customer Name --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Customer Name</span>
                            <div class="text-sm font-semibold text-text-main truncate" x-text="selected?.nama || '—'"></div>
                        </div>

                        {{-- Cell 2: CID Number --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">CID Number</span>
                            <div class="text-sm font-bold font-mono text-sky-600 dark:text-sky-400 truncate" x-text="selected?.cid || '—'"></div>
                        </div>

                        {{-- Cell 3: Phone / HP --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Phone / HP</span>
                            <div class="text-sm font-mono text-text-main truncate" x-text="selected?.no_hp || '—'"></div>
                        </div>

                        {{-- Cell 4: Active Package --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Active Package</span>
                            <div class="flex items-center gap-1.5">
                                <div class="text-sm font-medium text-text-main truncate" x-text="selected?.paket || '—'"></div>
                                <span x-show="selected?.paket" class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300">Active</span>
                            </div>
                        </div>

                        {{-- Cell 5: Site Address (2 cols) --}}
                        <div class="bg-surface p-3 space-y-1 md:col-span-2">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Site Address</span>
                            <div class="text-xs text-text-main line-clamp-2" x-text="selected?.alamat || '—'"></div>
                        </div>

                        {{-- Cell 6: POP / Cabang --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">POP / Cabang</span>
                            <div class="text-xs font-semibold text-text-main" x-text="selected?.pop || '—'"></div>
                        </div>

                        {{-- Cell 7: ODP Port --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">ODP Port</span>
                            <div class="text-xs font-mono font-medium text-text-main" x-text="selected?.odp || '—'"></div>
                        </div>

                        {{-- Cell 8: Perangkat Pelanggan --}}
                        <div class="bg-surface p-3 space-y-1 md:col-span-2">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Perangkat Pelanggan (ONT/Router)</span>
                            <div class="text-xs font-mono text-text-main" x-text="selected?.perangkat || '—'"></div>
                        </div>

                        {{-- Cell 9: GPS Coordinates --}}
                        <div class="bg-surface p-3 space-y-1 md:col-span-2">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">GPS Coordinates</span>
                            <div class="text-xs font-mono text-sky-600 dark:text-sky-400 flex items-center gap-1">
                                <svg class="h-3.5 w-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <template x-if="selected?.maps_url">
                                    <a :href="selected.maps_url" target="_blank" rel="noopener"
                                       class="hover:underline font-bold" x-text="selected.koordinat"></a>
                                </template>
                                <template x-if="!selected?.maps_url">
                                    <span>—</span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION 03: COMPLAINT & NOTES --}}
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-1 h-4 bg-sky-600 rounded-full"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">SECTION 03: COMPLAINT & NOTES</h2>
                </div>

                <div class="space-y-4">
                    {{-- Detail Keluhan --}}
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                            Detail Keluhan (Customer Complaint) <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="detail_keluhan" rows="4" required maxlength="2000"
                                  placeholder="Describe the issue reported by the customer (misal: Koneksi LOS merah, internet lambat jam tertentu, dsb)..."
                                  class="w-full text-sm rounded-lg border border-border bg-background p-3 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">{{ old('detail_keluhan') }}</textarea>
                    </div>

                    {{-- Catatan Teknis (NOC Monospace Box) --}}
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                            Catatan Teknis (Initial NOC Notes)
                        </label>
                        <textarea name="catatan_teknis" rows="3" maxlength="2000"
                                  placeholder="NOC assessment, ping results, optical power checks (-dBm), redaman OPM, atau petunjuk awal untuk teknisi FOP..."
                                  class="w-full font-mono text-xs rounded-lg border border-border bg-slate-900/5 dark:bg-slate-900/40 p-3 text-text-main italic placeholder:text-text-muted placeholder:not-italic focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">{{ old('catatan_teknis') }}</textarea>
                    </div>

                    {{-- Lampiran File --}}
                    <div class="space-y-1.5 pt-2">
                        <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                            Lampiran (Evidence / OPM Screenshot)
                        </label>
                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-border border-dashed rounded-lg cursor-pointer bg-slate-50/50 dark:bg-slate-900/20 hover:bg-sky-50/50 dark:hover:bg-slate-800/50 transition-colors">
                                <div class="flex flex-col items-center justify-center pt-3 pb-3">
                                    <svg class="w-6 h-6 mb-1 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <p class="text-xs text-text-secondary font-medium"><span class="font-bold text-sky-600">Klik untuk upload file</span> atau drag & drop</p>
                                    <p class="text-[10px] text-text-muted mt-0.5">Maks. 5 file, tiap file maks. 5 MB (JPG, PNG, WEBP, PDF)</p>
                                </div>
                                <input type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden">
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer SLA & Audit Protocol Cards --}}
            <div class="p-6 bg-slate-50/50 dark:bg-slate-900/40 border-t border-border">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

                    {{-- SLA Protocol --}}
                    <div class="flex items-start gap-3 p-3.5 bg-surface border border-amber-200 dark:border-amber-900/50 rounded-lg shadow-xs">
                        <svg class="h-5 w-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <div class="text-[11px] font-bold text-text-main uppercase tracking-wider">SLA Protocol Standard</div>
                            <p class="text-xs text-text-muted font-mono leading-relaxed mt-0.5">
                                MTN: 4h Response / 24h Resolution. Pastikan catatan teknis mencantumkan indikator redaman OPM jika terjadi kerusakan jaringan fisik.
                            </p>
                        </div>
                    </div>

                    {{-- Audit Compliance --}}
                    <div class="flex items-start gap-3 p-3.5 bg-surface border border-sky-200 dark:border-sky-900/50 rounded-lg shadow-xs">
                        <svg class="h-5 w-5 text-sky-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <div>
                            <div class="text-[11px] font-bold text-text-main uppercase tracking-wider">Audit & Security Compliance</div>
                            <p class="text-xs text-text-muted font-mono leading-relaxed mt-0.5">
                                Seluruh pembuatan dan perubahan ticket ini akan dicatat secara otomatis under session UID: <span class="font-bold text-text-main">{{ strtoupper(auth()->user()->name) }}</span>.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Action Bar --}}
                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('tickets.bucket', 'masuk') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-text-muted hover:text-text-main transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        DISCARD
                    </a>

                    <div class="flex items-center gap-3">
                        {{-- "SAVE AS DRAFT" dulu ada di sini sebagai tombol kedua, tapi
                             dua-duanya submit ke endpoint & field yang SAMA PERSIS —
                             gak ada logic backend yang bedain 'action=draft'. Ticket
                             yang dikirim dari halaman ini SELALU jadi Draft (Ticket
                             Masuk) apa pun tombolnya, karena FOP yang assign teknisi
                             belakangan — jadi nawarin 2 tombol seolah ada pilihan
                             "draft vs langsung jalan" itu nyesatin. Dihapus, disatuin
                             jadi satu tombol yang jujur sama hasilnya. --}}
                        <button type="submit" :disabled="!selected"
                                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-sky-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-sky-700 shadow-md shadow-sky-600/20 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <span>CREATE TICKET</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function ticketForm() {
        return {
            cidQuery: '',
            results: [],
            selected: null,
            searching: false,
            searched: false,

            async searchCustomer() {
                const q = this.cidQuery.trim();

                if (q.length < 2) {
                    this.results = [];
                    this.searched = false;
                    return;
                }

                this.searching = true;

                try {
                    const res = await fetch(`{{ route('tickets.lookup-customer') }}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    this.results = res.ok ? await res.json() : [];
                } catch (e) {
                    this.results = [];
                } finally {
                    this.searching = false;
                    this.searched = true;
                }
            },

            pick(customer) {
                this.selected = customer;
                this.cidQuery = customer.label;
                this.results = [];
            },

            clearSelection() {
                this.selected = null;
                this.cidQuery = '';
                this.results = [];
                this.searched = false;
            },
        };
    }
</script>
@endpush

