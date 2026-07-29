@extends('layouts.app')

@section('title', 'Worksheet Helpdesk - Create Service Ticket')
@section('page_title', 'Worksheet Helpdesk — New Ticket')

@section('content')
<div x-data="ticketPage()" @keydown.window="handleShortcut($event)" class="max-w-[1700px] mx-auto">

    {{-- Toast Notification --}}
    <div x-show="toast.show" x-transition x-cloak
         class="fixed top-20 right-6 z-50 max-w-sm rounded-lg shadow-lg border px-4 py-3 flex items-start gap-2.5"
         :class="toast.type === 'error' ? 'bg-rose-50 dark:bg-rose-900/30 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300' : 'bg-emerald-50 dark:bg-emerald-900/30 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300'">
        <svg class="h-5 w-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path x-show="toast.type !== 'error'" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            <path x-show="toast.type === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span class="text-xs font-semibold" x-text="toast.message"></span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 pb-12">

        {{-- LEFT COLUMN: WORKSHEET FORM --}}
        <div class="lg:col-span-7 xl:col-span-8 space-y-6">

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs text-text-muted">
                    <span>Ticketing</span>
                    <span>/</span>
                    <span class="text-text-main font-semibold">Worksheet Helpdesk — New Ticket</span>
                </div>

                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 border border-border text-[11px] font-bold text-text-muted uppercase tracking-wider">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Draft Mode
                </div>
            </div>

            <div class="bg-surface border border-border rounded-xl shadow-sm overflow-hidden transition-all">

                <div class="px-6 py-4 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                    <div>
                        <h1 class="text-lg font-bold text-text-main tracking-tight flex items-center gap-2">
                            <svg class="h-5 w-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                            Create Service Ticket
                        </h1>
                        <p class="text-[11px] text-text-muted font-medium uppercase tracking-wider mt-0.5">
                            Worksheet Helpdesk — Pengganti Pencatatan Manual Excel
                        </p>
                    </div>

                    <a href="{{ route('dashboard') }}" class="text-xs text-text-muted hover:text-text-main transition-colors flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Tutup
                    </a>
                </div>

                {{--
                    Submit mode — rancangan bagian D (RANCANGAN_MASTER_ISSUE_TICKETING.md):
                    fetch() POST JSON (Accept: application/json) ke /tickets, BUKAN native
                    form POST — user tetap di halaman ini setelah simpan, gak ada history
                    entry POST yang bisa di-refresh & resubmit (prinsip PRG tetap terjaga
                    lewat mekanisme lain, lihat docs/PRG_REDIRECT_CONVENTION.md).
                    TicketController@store bercabang wantsJson(): fallback non-JS (mis.
                    submit dari /fop-tasks) tetap PRG normal.
                --}}
                <form @submit.prevent="submitForm()" enctype="multipart/form-data" class="divide-y divide-border">

                    <!-- <div x-show="Object.keys(errors).length > 0" x-cloak class="p-4 bg-rose-50 dark:bg-rose-900/20 border-b border-rose-200 dark:border-rose-800">
                        <p class="text-xs font-bold text-rose-700 dark:text-rose-300 mb-1">Validasi gagal — periksa kembali field bertanda merah:</p>
                        <ul class="text-xs text-rose-600 dark:text-rose-400 list-disc list-inside">
                            <template x-for="(msg, field) in errors" :key="field">
                                <li x-text="msg"></li>
                            </template>
                        </ul>
                    </div> -->

                    {{-- SECTION 01: CLASSIFICATION --}}
                    <div class="p-6 space-y-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-1 h-4 bg-sky-600 rounded-full"></span>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">SECTION 01: CLASSIFICATION</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                                    Ticket Type <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <select x-model="ticketType" required class="w-full text-sm rounded-lg border border-border bg-background px-3 py-2.5 text-text-main appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-mono">
                                        <option value="" disabled>-- SELECT CLASSIFICATION --</option>
                                        @foreach($typeOptions as $opt)
                                            <option value="{{ $opt['value'] }}">{{ $opt['value'] }} — {{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <svg class="h-4 w-4 absolute right-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                                    Prioritas <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <select x-model="priority" required class="w-full text-sm rounded-lg border border-border bg-background px-3 py-2.5 text-text-main appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                                        @foreach($priorityOptions as $p)
                                            <option value="{{ $p->value }}">{{ $p->value }}</option>
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

                        <div class="space-y-1.5 relative">
                            <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                                Search Customer Data <span class="text-rose-500">*</span>
                            </label>

                            <div class="relative flex items-center">
                                <svg class="h-4 w-4 absolute left-3 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>

                                <input type="text" x-ref="searchInput" x-model="cidQuery" @input.debounce.300ms="searchCustomer()"
                                       :disabled="selected !== null"
                                       placeholder="ENTER CID OR NAME..."
                                       class="w-full text-sm font-mono tracking-wide rounded-lg border border-border bg-surface pl-9 pr-10 py-2.5 text-text-main placeholder:text-text-muted placeholder:font-sans focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 disabled:bg-slate-100 dark:disabled:bg-slate-800 disabled:text-text-muted transition-all">

                                <button type="button" x-show="selected" @click="clearSelection()" class="absolute right-3 text-xs font-bold text-sky-600 hover:text-sky-700 underline cursor-pointer">
                                    Ganti
                                </button>

                                <button type="button" x-show="cidQuery && !selected" @click="cidQuery = ''; results = []" class="absolute right-3 text-text-muted hover:text-text-main">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div x-show="results.length > 0 && !selected" x-cloak class="absolute z-30 mt-1 w-full bg-surface border border-border rounded-lg shadow-xl max-h-60 overflow-y-auto divide-y divide-border">
                                <template x-for="r in results" :key="r.id">
                                    <button type="button" @click="pick(r)" class="w-full text-left px-4 py-3 text-sm hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-between cursor-pointer group">
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

                            <p x-show="!searching && searched && results.length === 0 && !selected" x-cloak class="text-xs text-rose-500 mt-1 font-medium">
                                Pelanggan tidak ditemukan. Silakan periksa kembali CID atau Nama.
                            </p>
                        </div>

                        {{-- Duplicate Ticket Warning — dicek dari snapshot tasks panel kanan --}}
                        <div x-show="selected && duplicateTickets.length > 0" x-cloak class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                            <svg class="h-4 w-4 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">
                                Pelanggan ini masih punya <span x-text="duplicateTickets.length"></span> tiket open:
                                <template x-for="d in duplicateTickets" :key="d.id">
                                    <span class="font-mono" x-text="d.code + ' (' + bucketLabel(d.bucket) + ')  '"></span>
                                </template>
                            </p>
                        </div>

                        <div class="border border-border rounded-lg overflow-hidden bg-border shadow-xs">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-px bg-border">
                                <div class="bg-surface p-3 space-y-1">
                                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Customer Name</span>
                                    <div class="text-sm font-semibold text-text-main truncate" x-text="selected?.nama || '—'"></div>
                                </div>
                                <div class="bg-surface p-3 space-y-1">
                                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">CID Number</span>
                                    <div class="text-sm font-bold font-mono text-sky-600 dark:text-sky-400 truncate" x-text="selected?.cid || '—'"></div>
                                </div>
                                <div class="bg-surface p-3 space-y-1">
                                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Phone / HP</span>
                                    <div class="text-sm font-mono text-text-main truncate" x-text="selected?.no_hp || '—'"></div>
                                </div>
                                <div class="bg-surface p-3 space-y-1">
                                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Active Package</span>
                                    <div class="flex items-center gap-1.5">
                                        <div class="text-sm font-medium text-text-main truncate" x-text="selected?.paket || '—'"></div>
                                        <span x-show="selected?.paket" class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300">Active</span>
                                    </div>
                                </div>
                                <div class="bg-surface p-3 space-y-1 md:col-span-2">
                                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Site Address</span>
                                    <div class="text-xs text-text-main line-clamp-2" x-text="selected?.alamat || '—'"></div>
                                </div>
                                <div class="bg-surface p-3 space-y-1">
                                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">POP / Cabang</span>
                                    <div class="text-xs font-semibold text-text-main" x-text="selected?.pop || '—'"></div>
                                </div>
                                <div class="bg-surface p-3 space-y-1">
                                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">ODP Port</span>
                                    <div class="text-xs font-mono font-medium text-text-main" x-text="selected?.odp || '—'"></div>
                                </div>
                                <div class="bg-surface p-3 space-y-1 md:col-span-2">
                                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Perangkat Pelanggan (ONT/Router)</span>
                                    <div class="text-xs font-mono text-text-main" x-text="selected?.perangkat || '—'"></div>
                                </div>
                                <div class="bg-surface p-3 space-y-1 md:col-span-2">
                                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">GPS Coordinates</span>
                                    <div class="text-xs font-mono text-sky-600 dark:text-sky-400 flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <template x-if="selected?.maps_url">
                                            <a :href="selected.maps_url" target="_blank" rel="noopener" class="hover:underline font-bold" x-text="selected.koordinat"></a>
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
                            <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">SECTION 03: COMPLAINT &amp; NOTES</h2>
                        </div>

                        <div class="space-y-4">
                            {{-- Kategori Issue — Master Issue (rancangan bagian C) --}}
                            <div class="space-y-1.5">
                                <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                                    Kategori Issue <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <select x-model="issueCategoryId" @change="onIssueCategoryChange()" required class="w-full text-sm rounded-lg border border-border bg-background px-3 py-2.5 text-text-main appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                                        <option value="" disabled>-- PILIH KATEGORI ISSUE --</option>
                                        <template x-for="c in issueCategories" :key="c.id">
                                            <option :value="c.id" x-text="c.name"></option>
                                        </template>
                                        <option value="lainnya">Lainnya (isi manual)</option>
                                    </select>
                                    <svg class="h-4 w-4 absolute right-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                                <p class="text-[10px] text-text-muted">Pilih kategori otomatis isi Prioritas. Pilih "Lainnya" kalau issue belum ada di master.</p>
                                <p x-show="selectedCategorySlaSource" x-cloak class="inline-flex items-center gap-1 text-[10px] font-semibold text-sky-600 dark:text-sky-400">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01"></path></svg>
                                    SLA kategori ini: <span x-text="selectedCategorySlaSource === 'paket' ? 'sesuai Paket Internet pelanggan' : 'sesuai Prioritas di atas'"></span>
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                                    Detail Keluhan (Customer Complaint) <span class="text-rose-500">*</span>
                                </label>
                                <textarea x-model="detailKeluhan" @input="delete errors.detail_keluhan" rows="4" required maxlength="2000"
                                          placeholder="Describe the issue reported by the customer (misal: Koneksi LOS merah, internet lambat jam tertentu, dsb)..."
                                          :class="errors.detail_keluhan ? 'border-rose-400 focus:ring-rose-500/30 focus:border-rose-500' : 'border-border focus:ring-sky-500/30 focus:border-sky-500'"
                                          class="w-full text-sm rounded-lg border bg-background p-3 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 transition-all"></textarea>
                                <p x-show="errors.detail_keluhan" x-cloak class="text-[11px] font-semibold text-rose-600 dark:text-rose-400" x-text="errors.detail_keluhan"></p>
                            </div>

                            <button type="button" @click="showExtra = !showExtra" class="flex items-center gap-1.5 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                                <svg class="h-3.5 w-3.5 transition-transform" :class="showExtra ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                </svg>
                                <span x-text="showExtra ? 'Sembunyikan Detail Tambahan' : 'Tampilkan Detail Tambahan (Catatan Teknis & Lampiran, Opsional)'"></span>
                            </button>

                            <div x-show="showExtra" x-cloak x-transition class="space-y-4">
                                <div class="space-y-1.5">
                                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                                        Catatan Teknis
                                    </label>
                                    <textarea x-model="catatanTeknis" rows="3" maxlength="2000"
                                              placeholder="NOC assessment, ping results, optical power checks (-dBm), redaman OPM, atau petunjuk awal untuk teknisi FOP..."
                                              class="w-full font-mono text-xs rounded-lg border border-border bg-slate-900/5 dark:bg-slate-900/40 p-3 text-text-main italic placeholder:text-text-muted placeholder:not-italic focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all"></textarea>
                                </div>

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
                                                <p class="text-xs text-text-secondary font-medium"><span class="font-bold text-sky-600">Klik untuk upload file</span> atau drag &amp; drop</p>
                                                <p class="text-[10px] text-text-muted mt-0.5">Maks. 5 file, tiap file maks. 5 MB (JPG, PNG, WEBP, PDF)</p>
                                            </div>
                                            <input type="file" x-ref="fileInput" @change="attachments = Array.from($event.target.files)" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden">
                                        </label>
                                    </div>
                                    <p x-show="attachments.length > 0" x-cloak class="text-[10px] text-text-muted" x-text="attachments.length + ' file dipilih'"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Footer Info & Actions --}}
                    <div class="p-6 bg-slate-50/50 dark:bg-slate-900/40 border-t border-border">

                        <div class="flex items-center justify-between pt-2">
                            <button type="button" @click="resetForm()" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-text-muted hover:text-text-main transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                DISCARD <span class="opacity-60 normal-case font-normal">(Esc)</span>
                            </button>

                            <button type="submit" :disabled="!selected || submitting" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-sky-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-sky-700 shadow-md shadow-sky-600/20 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!submitting">CREATE TICKET <span class="opacity-70 normal-case font-normal">(Ctrl+Enter)</span></span>
                                <span x-show="submitting">MENYIMPAN...</span>
                                <svg x-show="!submitting" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                                <svg x-show="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{--
            RIGHT COLUMN: LIST TASK TICKETING PANEL — rancangan bagian E.
            Initial load lewat data server-side ($initialTasks). Realtime push
            (Echo listener TicketQueueUpdated) masih Task 3 terpisah — di sini
            item baru yang habis disubmit di-prepend lokal (optimistik) dari
            respons JSON, bukan broadcast asli.
        --}}
        <div class="lg:col-span-5 xl:col-span-4 space-y-4">
            <div class="bg-surface border border-border rounded-xl shadow-sm p-5 sticky top-20 flex flex-col min-h-[calc(100vh-120px)]">

                <div class="pb-4 border-b border-border space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-text-main tracking-tight">List Task Ticketing</h2>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300" x-text="tasks.length + ' Ticket'"></span>
                        </div>
                        <div class="flex items-center gap-3">
                            {{--
                                Refresh manual + auto-refresh via broadcast Reverb
                                (App\Events\TicketQueueUpdated, Gap #3). Auto-refresh
                                jalan sendiri kalau Echo kekoneksi; tombol ini fallback
                                kalau Reverb down/gak jalan di browser user.
                            --}}
                            <button type="button" @click="refreshWorksheet()" :disabled="refreshing"
                                    class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1 disabled:opacity-50 cursor-pointer">
                                <svg class="h-3.5 w-3.5" :class="refreshing ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Refresh</span>
                            </button>
                            {{--
                                Halaman list penuh sekarang kepecah per-halaman
                                (Worksheet NOC / Ticket Selesai / Dibatalkan) —
                                gak ada lagi bucket generik. Link cuma muncul buat
                                yang emang punya akses Worksheet NOC.
                            --}}
                            @if(auth()->user()->hasPermission('noc_worksheet.masuk.view'))
                            <a href="{{ route('noc.worksheet.masuk') }}" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1">
                                <span>Worksheet NOC</span>
                            </a>
                            @endif
                        </div>
                    </div>

                    {{--
                        Filter Tabs — value = TicketHandler->value asli (helpdesk/noc/fop),
                        BUKAN TicketBucket lagi. Tab di sini nunjukin "tiket ini lagi
                        di tangan siapa", bukan status pengerjaannya:
                          Ticket     = masih di tangan pembuat, belum dikirim ke mana pun
                          Assign NOC = udah dikirim ke NOC (Pending / OnCheck)
                          Assign FOP = udah dikirim ke FOP (pantau status Task FOP)
                    --}}
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 p-1 rounded-lg text-xs font-medium text-text-muted">
                        <button type="button" @click="taskFilter = 'helpdesk'" :class="taskFilter === 'helpdesk' ? 'bg-white dark:bg-slate-800 text-text-main font-semibold shadow-xs' : 'hover:text-text-main'" class="flex-1 py-1.5 px-2 rounded-md transition-all text-center">Ticket</button>
                        <button type="button" @click="taskFilter = 'noc'" :class="taskFilter === 'noc' ? 'bg-white dark:bg-slate-800 text-text-main font-semibold shadow-xs' : 'hover:text-text-main'" class="flex-1 py-1.5 px-2 rounded-md transition-all text-center">Assign NOC</button>
                        <button type="button" @click="taskFilter = 'fop'" :class="taskFilter === 'fop' ? 'bg-white dark:bg-slate-800 text-text-main font-semibold shadow-xs' : 'hover:text-text-main'" class="flex-1 py-1.5 px-2 rounded-md transition-all text-center">Assign FOP</button>
                    </div>
                    <p class="text-[10px] text-text-muted">Selesai &amp; Dibatalkan gak masuk antrean kerja — ada di halaman sendiri.</p>
                </div>

                <div class="flex-1 overflow-y-auto py-4 space-y-3 pr-1 custom-scrollbar">
                    <template x-if="filteredTasks.length === 0">
                        <p class="text-xs text-text-muted text-center py-6">Belum ada tiket di tab ini.</p>
                    </template>
                    <template x-for="task in filteredTasks" :key="task.id">
                        <div class="p-3.5 rounded-lg border border-border bg-slate-50/50 dark:bg-slate-900/40 hover:border-sky-500/50 transition-all space-y-2.5">
                            <a :href="'{{ url('/tickets') }}/' + task.id" class="block space-y-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold font-mono text-sky-600 dark:text-sky-400" x-text="task.code"></span>
                                            <span class="px-1.5 py-0.5 text-[9px] font-bold rounded uppercase"
                                                :class="{
                                                    'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300': task.priority === 'Urgent',
                                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300': task.priority === 'High',
                                                    'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300': task.priority === 'Medium' || task.priority === 'low'
                                                }"
                                                x-text="task.priority">
                                            </span>
                                        </div>
                                        <h4 class="text-xs font-bold text-text-main mt-0.5" x-text="task.title"></h4>
                                    </div>
                                    <span class="text-[10px] text-text-muted shrink-0 font-mono" x-text="task.time"></span>
                                </div>

                                <div>
                                    <span class="text-xs font-semibold text-text-main truncate block" x-text="task.customer_name"></span>
                                    <div class="flex items-center gap-3 mt-1 text-[11px] text-text-muted truncate">
                                        <template x-if="task.issue_category">
                                            <span class="flex items-center gap-1">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                                </svg>
                                                <span x-text="task.issue_category"></span>
                                            </span>
                                        </template>

                                        <span class="flex items-center gap-1">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                              <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            <span x-text="task.customer_phone"></span>
                                        </span>
                                    </div>
                                </div>

                                <p class="text-xs text-text-muted line-clamp-2" x-text="task.desc"></p>

                                {{--
                                    Atribusi "siapa ngapain" — dibuat/dikirim ke NOC/dikirim
                                    ke FOP/diselesaikan siapa. Dari ticket_histories via
                                    TicketController::worksheetCardPayload(); null kalau
                                    belum kejadian (mis. tiket masih di Helpdesk, belum
                                    pernah dieskalasi).
                                --}}
                                <div class="flex items-center gap-2 flex-wrap text-[10px] text-text-muted font-medium" x-show="task.escalated_noc_by || task.escalated_fop_by || task.returned_to_helpdesk_by || task.closed_by">
                                    <span x-show="task.escalated_noc_by">→ NOC oleh <span class="font-bold text-text-secondary" x-text="task.escalated_noc_by"></span></span>
                                    <span x-show="task.escalated_fop_by">→ FOP oleh <span class="font-bold text-text-secondary" x-text="task.escalated_fop_by"></span></span>
                                    <span x-show="task.returned_to_helpdesk_by">↩ Kembali ke Helpdesk oleh <span class="font-bold text-text-secondary" x-text="task.returned_to_helpdesk_by"></span></span>
                                    <span x-show="task.closed_by">✓ Selesai oleh <span class="font-bold text-emerald-600 dark:text-emerald-400" x-text="task.closed_by"></span></span>
                                </div>

                                <div class="flex items-center justify-between pt-1 border-t border-border/60 text-[11px]">
                                    <span class="font-medium text-text-secondary">CID: <span class="font-mono text-text-main" x-text="task.cid"></span></span>
                                    <span class="font-semibold flex items-center gap-1"
                                        :class="{
                                            'text-amber-600 dark:text-amber-400': task.bucket === 'diproses',
                                            'text-sky-600 dark:text-sky-400': task.bucket === 'masuk',
                                            'text-emerald-600 dark:text-emerald-400': task.bucket === 'selesai',
                                            'text-slate-500 dark:text-slate-400': task.bucket === 'dibatalkan'
                                        }">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                            :class="{
                                                'bg-amber-500': task.bucket === 'diproses',
                                                'bg-sky-500': task.bucket === 'masuk',
                                                'bg-emerald-500': task.bucket === 'selesai',
                                                'bg-slate-400': task.bucket === 'dibatalkan'
                                            }">
                                        </span>
                                        <span x-text="bucketLabel(task.bucket)"></span>
                                    </span>
                                </div>
                            </a>

                            {{--
                                Aksi Selesaikan Sendiri / Kirim ke NOC / Kirim ke FOP
                                (docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD) — kembaran
                                panel "Aksi Tiket" di tickets/show.blade.php, cuma di sini
                                langsung dari List Task Ticketing. Flag task.actions.* dari
                                TicketController::ticketActionFlags() (server-side, UI-only
                                gate — otorisasi sungguhan tetap TicketService::assertActorOwnsTicket()).
                            --}}
                            <div x-show="task.actions && (task.actions.can_close || task.actions.can_escalate_noc || task.actions.can_escalate_fop || task.actions.can_return_to_helpdesk)"
                                 class="flex items-center gap-1.5 pt-2 border-t border-border/60">
                                <button type="button" x-show="task.actions.can_close" :disabled="actionLoadingId === task.id"
                                        @click="closeTicket(task)"
                                        class="flex-1 px-2 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                    Selesai
                                </button>
                                <button type="button" x-show="task.actions.can_escalate_noc" :disabled="actionLoadingId === task.id"
                                        @click="escalateTicket(task, 'noc')"
                                        class="flex-1 px-2 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                    Ke NOC
                                </button>
                                <button type="button" x-show="task.actions.can_escalate_fop" :disabled="actionLoadingId === task.id"
                                        @click="escalateTicket(task, 'fop')"
                                        class="flex-1 px-2 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-sky-600 text-white hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                    Ke FOP
                                </button>
                                {{-- Gap #7 — jalur pemulihan kalau NOC salah terima/pencet. --}}
                                <button type="button" x-show="task.actions.can_return_to_helpdesk" :disabled="actionLoadingId === task.id"
                                        @click="returnTicketToHelpdesk(task)"
                                        class="flex-1 px-2 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-slate-600 text-white hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                    Kembalikan
                                </button>
                            </div>
                        </div>
                    </template>

                    {{--
                        Gap #4 (docs/plan/analisa-efektivitas-worksheet-ticketing.md)
                        — panel cuma nampilin WORKSHEET_DISPLAY_LIMIT (30) tiket
                        terbaru. Sebelumnya sisanya diem-diem ilang tanpa indikator.
                        worksheetTotalCount dari server (activeForWorksheet()->count())
                        gak kena limit, jadi bisa dibandingin buat tau ada berapa lagi.
                    --}}
                    <template x-if="worksheetTotalCount > tasks.length">
                        <p class="block text-center text-xs font-semibold text-text-muted py-3 border-t border-border/60 mt-2">
                            <span x-text="'+ ' + (worksheetTotalCount - tasks.length) + ' tiket aktif lainnya (di luar 30 terbaru)'"></span>
                        </p>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
@include('tickets.partials.action-dialog')
@endsection

@push('scripts')
<script>
    function ticketPage() {
        return {
            // Form state
            cidQuery: '',
            results: [],
            selected: null,
            searching: false,
            searched: false,
            ticketType: '',
            priority: 'Medium',
            issueCategoryId: '',
            detailKeluhan: '',
            catatanTeknis: '',
            attachments: [],
            showExtra: false,
            toast: { show: false, type: 'success', message: '' },
            errors: {},
            submitting: false,
            taskFilter: 'helpdesk',
            actionLoadingId: null,
            refreshing: false,

            // Master Issue — dari Master Data sungguhan (Task 1 rancangan), bukan mock lagi.
            issueCategories: @json($issueCategories),

            // Snapshot awal panel kanan — data server-side saat halaman dimuat.
            // Item baru hasil submit di-prepend lokal (optimistik) di submitForm(),
            // update dari aktor lain masuk lewat broadcast Reverb (initEchoListeners()).
            tasks: @json($initialTasks),

            // Total tiket aktif TANPA kena cap panel (Gap #4) — dibandingin sama
            // tasks.length buat nampilin indikator "+N lainnya".
            worksheetTotalCount: {{ $worksheetTotalCount }},

            // POP yang kelihatan user — subscribe Echo.private('tickets.{popId}')
            // per POP ini (Gap #3).
            allowedPopIds: @json($allowedPopIds),

            init() {
                this.initEchoListeners();
            },

            /**
             * Auto-refresh (Gap #3) — subscribe channel tickets.{popId} per POP
             * yang kelihatan user, dengerin App\Events\TicketQueueUpdated (broadcast
             * dari TicketService setelah create/close/escalate commit). Retry loop
             * nunggu window.Echo kebentuk sama kayak pola fop/dashboard.blade.php
             * (Echo diinisialisasi script terpisah, kadang belum ready pas Alpine init()).
             */
            initEchoListeners() {
                const popIds = this.allowedPopIds;
                let attempts = 0;
                const setup = () => {
                    if (typeof window.Echo === 'undefined' || !window.Echo) {
                        attempts++;
                        if (attempts < 20) setTimeout(setup, 100);
                        return;
                    }
                    popIds.forEach(popId => {
                        window.Echo.private(`tickets.${popId}`)
                            .listen('.TicketQueueUpdated', () => this.refreshWorksheet());
                    });
                };
                setup();
            },

            async refreshWorksheet() {
                this.refreshing = true;
                try {
                    const res = await fetch('{{ route('tickets.worksheet-tasks') }}', {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) return;
                    const body = await res.json();
                    this.tasks = body.tasks;
                    this.worksheetTotalCount = body.total;
                } catch (e) {
                    // Diam-diam gagal — tombol Refresh manual tetap bisa dicoba lagi.
                } finally {
                    this.refreshing = false;
                }
            },

            get selectedCategorySlaSource() {
                if (!this.issueCategoryId || this.issueCategoryId === 'lainnya') return null;
                return this.issueCategories.find(c => c.id == this.issueCategoryId)?.sla_source || null;
            },

            // Label persis App\Enums\TicketBucket::label() — jangan bikin teks bebas.
            bucketLabel(bucket) {
                return { masuk: 'Ticket Masuk', diproses: 'Ticket di Proses', selesai: 'Ticket Selesai', dibatalkan: 'Ticket Dibatalkan' }[bucket] || bucket;
            },

            // Semua aksi lewat window.confirmTicketAction() — dialog global
            // (components/dialog.blade.php), BUKAN confirm() native. Selain
            // tampilannya seragam sama seluruh app, dialog ini bisa nampung
            // textarea alasan yang kekirim sebagai `reason` ke
            // ticket_histories (lihat tickets/partials/action-dialog.blade.php).
            closeTicket(task) {
                window.confirmTicketAction({
                    title: 'Selesaikan Tiket',
                    message: `Tandai tiket ${task.code} selesai?`,
                    label: 'Apa yang sudah dikerjakan? (opsional)',
                    required: false,
                    confirmText: 'Ya, Selesaikan',
                    icon: 'success',
                    onConfirm: (reason) => this.performTicketAction(
                        task.id, `{{ url('/tickets') }}/${task.id}/close`, { reason }
                    ),
                });
            },

            escalateTicket(task, target) {
                const label = target === 'noc' ? 'NOC' : 'FOP';

                window.confirmTicketAction({
                    title: `Kirim Tiket ke ${label}`,
                    message: target === 'fop'
                        ? `Kirim tiket ${task.code} ke FOP? Task FOP baru akan dibuat.`
                        : `Kirim tiket ${task.code} ke NOC?`,
                    label: `Catatan buat ${label} (opsional)`,
                    required: false,
                    confirmText: `Ya, Kirim ke ${label}`,
                    onConfirm: (reason) => this.performTicketAction(
                        task.id, `{{ url('/tickets') }}/${task.id}/escalate`, { target, reason }
                    ),
                });
            },

            // Gap #7 — jalur pemulihan kalau NOC salah terima/pencet.
            returnTicketToHelpdesk(task) {
                window.confirmTicketAction({
                    title: 'Kembalikan ke Helpdesk',
                    message: `Kembalikan tiket ${task.code} ke Helpdesk?`,
                    label: 'Alasan dikembalikan (opsional)',
                    required: false,
                    confirmText: 'Ya, Kembalikan',
                    onConfirm: (reason) => this.performTicketAction(
                        task.id, `{{ url('/tickets') }}/${task.id}/return-to-helpdesk`, { reason }
                    ),
                });
            },

            /**
             * Dipakai closeTicket()/escalateTicket() — POST JSON, lalu update
             * item task.id di array `tasks` in-place dari respons server
             * (worksheetCardPayload() balikin bentuk yang sama persis kayak
             * initial load, biar card gak "lompat" bentuk).
             */
            async performTicketAction(taskId, url, payload) {
                this.actionLoadingId = taskId;
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
                        this.showToast(body.message || 'Aksi gagal, coba lagi.', 'error');
                        return;
                    }

                    const idx = this.tasks.findIndex(t => t.id === taskId);
                    // Close bikin bucket jadi 'selesai' — keluar dari scope
                    // activeForWorksheet() di server, jadi dihapus juga di sini
                    // (bukan diganti), biar konsisten sama query backend.
                    const stillActive = body.ticket && (body.ticket.bucket === 'masuk' || body.ticket.bucket === 'diproses');
                    if (idx !== -1) {
                        if (stillActive) {
                            this.tasks[idx] = body.ticket;
                        } else {
                            this.tasks.splice(idx, 1);
                            this.worksheetTotalCount = Math.max(0, this.worksheetTotalCount - 1);
                        }
                    }

                    this.showToast(body.message);
                } catch (e) {
                    this.showToast('Aksi gagal, coba lagi.', 'error');
                } finally {
                    this.actionLoadingId = null;
                }
            },

            // Filter per TAB = per `handler` (di tangan siapa tiketnya), BUKAN
            // per bucket/status pengerjaan. Lihat komentar tab di atas.
            get filteredTasks() {
                return this.tasks.filter(t => t.handler === this.taskFilter);
            },

            // Tiket open milik customer terpilih (masuk/diproses) — bantu Helpdesk
            // sadar udah ada tiket berjalan sebelum bikin duplikat.
            //
            // Gap #5 (docs/plan/analisa-efektivitas-worksheet-ticketing.md) —
            // SENGAJA query server-side (checkDuplicates()), BUKAN filter array
            // `tasks` lokal kayak sebelumnya. `tasks` kena cap 30 (Gap #4),
            // tiket lama customer yang udah kegeser dari cap gak bakal
            // kedeteksi kalau cuma nyisir array lokal.
            duplicateTickets: [],

            async checkDuplicates(customerId) {
                try {
                    const res = await fetch(`{{ route('tickets.duplicates') }}?customer_id=${customerId}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    this.duplicateTickets = res.ok ? await res.json() : [];
                } catch (e) {
                    this.duplicateTickets = [];
                }
            },

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
                this.checkDuplicates(customer.id);
            },

            clearSelection() {
                this.selected = null;
                this.cidQuery = '';
                this.results = [];
                this.searched = false;
                this.duplicateTickets = [];
            },

            // Kategori issue auto-fill prioritas — user tetap bisa override manual.
            onIssueCategoryChange() {
                if (this.issueCategoryId === 'lainnya' || this.issueCategoryId === '') return;
                const cat = this.issueCategories.find(c => c.id == this.issueCategoryId);
                if (cat) this.priority = cat.default_priority;
            },

            showToast(message, type = 'success') {
                this.toast = { show: true, type, message };
                setTimeout(() => { this.toast.show = false; }, 3000);
            },

            resetForm() {
                this.clearSelection();
                this.ticketType = '';
                this.priority = 'Medium';
                this.issueCategoryId = '';
                this.detailKeluhan = '';
                this.catatanTeknis = '';
                this.attachments = [];
                this.showExtra = false;
                this.errors = {};
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                this.$nextTick(() => this.$refs.searchInput?.focus());
            },

            handleShortcut(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    if (this.selected && !this.submitting) this.submitForm();
                    return;
                }
                if (e.key === 'Escape') {
                    this.resetForm();
                }
            },

            async submitForm() {
                if (!this.selected || this.submitting) return;
                this.submitting = true;
                this.errors = {};

                const formData = new FormData();
                formData.append('type', this.ticketType);
                formData.append('priority', this.priority);
                formData.append('customer_id', this.selected.id);
                if (this.issueCategoryId && this.issueCategoryId !== 'lainnya') {
                    formData.append('issue_category_id', this.issueCategoryId);
                }
                formData.append('detail_keluhan', this.detailKeluhan);
                if (this.catatanTeknis) formData.append('catatan_teknis', this.catatanTeknis);
                this.attachments.forEach(file => formData.append('attachments[]', file));

                try {
                    const res = await fetch('{{ route('tickets.store') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: formData,
                    });

                    if (res.status === 422) {
                        const body = await res.json();
                        const flat = {};
                        Object.entries(body.errors || {}).forEach(([field, msgs]) => { flat[field] = msgs[0]; });
                        this.errors = flat;
                        this.showToast('Validasi gagal, periksa kembali form.', 'error');
                        return;
                    }

                    if (!res.ok) {
                        // Gap #8 (docs/plan/analisa-efektivitas-worksheet-ticketing.md)
                        // — coba baca pesan server dulu (kalau ada), jangan cuma
                        // teks generik. Form & lampiran yang udah dipilih TETAP
                        // gak dihapus di jalur ini (resetForm() cuma dipanggil pas
                        // sukses) — user cuma perlu klik submit ulang, bukan isi
                        // dari nol.
                        let message = 'Gagal membuat tiket, coba lagi.';
                        try {
                            const body = await res.json();
                            if (body?.message) message = body.message;
                        } catch (e) { /* respons bukan JSON — pakai pesan default */ }
                        if (this.attachments.length > 0) {
                            message += ' Lampiran yang sudah dipilih masih tersimpan, tinggal submit ulang.';
                        }
                        this.showToast(message, 'error');
                        return;
                    }

                    const body = await res.json();
                    this.showToast(`${body.ticket.code} dibuat & disinkronkan ke FOP.`);
                    this.tasks.unshift(body.ticket);
                    this.worksheetTotalCount++;
                    this.resetForm();
                } catch (e) {
                    // fetch() sendiri gagal (network putus/timeout) — beda dari
                    // respons !res.ok di atas, di sini gak ada respons server
                    // sama sekali buat dibaca.
                    let message = 'Gagal membuat tiket — periksa koneksi internet, coba lagi.';
                    if (this.attachments.length > 0) {
                        message += ' Lampiran yang sudah dipilih masih tersimpan.';
                    }
                    this.showToast(message, 'error');
                } finally {
                    this.submitting = false;
                }
            },
        };
    }
</script>
@endpush
