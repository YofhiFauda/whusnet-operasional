<?php $__env->startSection('title', 'Worksheet Helpdesk - Create Service Ticket'); ?>
<?php $__env->startSection('page_title', 'Worksheet Helpdesk — New Ticket'); ?>

<?php $__env->startSection('content'); ?>


<div x-data="ticketPage()" @keydown.window="handleShortcut($event)"
     @ticket-drawer-action.window="handleDrawerAction($event.detail)"
     
     @ticket-drawer-shown.window="drawerOpen = true"
     @ticket-drawer-hidden.window="drawerOpen = false"
     class="relative -m-4 sm:-m-6 lg:-m-8 h-[calc(100dvh-4rem)] flex overflow-hidden bg-background">

    
    <div x-show="toast.show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-3 scale-95"
         class="fixed bottom-5 right-5 z-50 max-w-sm rounded-xl shadow-2xl border px-4 py-3 flex items-start gap-2.5"
         :class="toast.type === 'error' ? 'bg-rose-50 dark:bg-rose-900/40 border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-200' : 'bg-emerald-50 dark:bg-emerald-900/40 border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-200'">
        <svg class="h-5 w-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path x-show="toast.type !== 'error'" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            <path x-show="toast.type === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span class="text-xs font-semibold" x-text="toast.message"></span>
    </div>

    
    <button type="button" @click="setFormOpen(true)" tabindex="-1"
            :aria-hidden="formOpen ? 'true' : 'false'"
            :tabindex="formOpen ? '-1' : '0'"
            title="Buka form tiket baru (N)"
            class="group shrink-0 overflow-hidden flex flex-col items-center justify-between py-6 bg-surface border-r border-border hover:bg-sky-50 dark:hover:bg-slate-800/60 cursor-pointer panel-motion"
            :class="[
                formOpen ? 'w-0 border-r-0 opacity-0 pointer-events-none' : 'w-11 opacity-100',
                animReady ? '' : 'panel-motion-off',
            ]">
        <div class="flex flex-col items-center gap-4">
            <span class="w-7 h-7 rounded-lg bg-sky-100 dark:bg-sky-900/40 border border-sky-300 dark:border-sky-700 text-sky-600 dark:text-sky-400 flex items-center justify-center transition-transform duration-200 group-hover:scale-110 group-hover:bg-sky-600 group-hover:text-white">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
            </span>
            <span class="[writing-mode:vertical-rl] text-[11px] font-bold uppercase tracking-[0.35em] text-text-muted group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors">
                Ticketing
            </span>
        </div>
        <svg class="h-3.5 w-3.5 text-text-muted group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-all duration-200 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m13 5 7 7-7 7M5 5l7 7-7 7" />
        </svg>
    </button>

    
    
    <div x-show="formOpen" x-cloak @click="setFormOpen(false)"
         x-transition.opacity.duration.200ms
         class="absolute inset-0 z-10 bg-slate-900/40 dark:bg-slate-950/60 xl:hidden"></div>

    
    <div class="absolute inset-y-0 left-0 z-20 w-full max-w-[440px] sm:max-w-[400px] lg:static lg:z-auto lg:max-w-none shrink-0 overflow-hidden flex panel-motion"
         :inert="!formOpen"
         :class="[
             formOpen
                 ? 'translate-x-0 opacity-100 lg:w-[380px] xl:w-[400px] 2xl:w-[440px]'
                 : '-translate-x-full opacity-0 lg:translate-x-0 lg:w-0',
             animReady ? '' : 'panel-motion-off',
         ]">
    <div class="w-full lg:w-[380px] xl:w-[400px] 2xl:w-[440px] shrink-0 flex flex-col min-w-0 bg-surface border-r border-border shadow-xl z-10">

        
        <div class="shrink-0 px-4 sm:px-5 py-3.5 border-b border-border bg-surface-muted/60 dark:bg-slate-900/40 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="shrink-0 text-sky-600 dark:text-sky-400">
                </span>
                <div class="min-w-0">
                    <h1 class="text-sm font-extrabold text-text-main uppercase tracking-wide truncate">Create Service Ticket</h1>
                    <p class="text-[11px] text-text-muted truncate">Input tiket baru dengan cepat</p>
                </div>
            </div>

            
            <button type="button" @click="setFormOpen(false)" title="Tutup form (N)"
                    class="shrink-0 p-1.5 rounded-lg text-text-muted hover:bg-rose-600 hover:text-white active:scale-95 transition-all duration-200 cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        
        
        <form action="<?php echo e(route('tickets.store')); ?>" method="POST" @submit.prevent="submitForm()" enctype="multipart/form-data" class="flex-1 flex flex-col min-h-0">
            <?php echo csrf_field(); ?>

            <div class="flex-1 overflow-y-auto custom-scrollbar">

                
                <section class="border border-border bg-surface-muted/40 dark:bg-slate-900/30 p-4 space-y-3.5 transition-colors">
                    <h2 class="text-[11px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">SECTION 01: CLASSIFICATION</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                                Ticket Type <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <select x-model="ticketType" required class="w-full text-[13px] rounded-lg border border-border bg-background px-3 py-2.5 text-text-main appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-mono">
                                    <option value="" disabled>-- SELECT CLASSIFICATION --</option>
                                    <?php $__currentLoopData = $typeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($opt['value']); ?>"><?php echo e($opt['value']); ?> — <?php echo e($opt['label']); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                                    <?php $__currentLoopData = $priorityOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($p->value); ?>"><?php echo e($p->value); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                                <svg class="h-4 w-4 absolute right-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </section>

                
                <section class="border border-border bg-surface-muted/40 dark:bg-slate-900/30 p-4 space-y-3.5 transition-colors">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-[11px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">SECTION 02: CUSTOMER IDENTITY</h2>
                        
                        <span x-show="selected" x-cloak class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Matched</span>
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
                                   class="w-full text-sm font-mono tracking-wide rounded-lg border border-border bg-surface pl-9 pr-16 py-2.5 text-text-main placeholder:text-text-muted placeholder:font-sans focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 disabled:bg-surface-muted disabled:text-text-muted transition-all">

                            <button type="button" x-show="selected" x-cloak @click="clearSelection()" class="absolute right-3 text-xs font-bold text-sky-600 hover:text-sky-700 underline cursor-pointer">
                                Ganti
                            </button>

                            <button type="button" x-show="cidQuery && !selected" x-cloak @click="cidQuery = ''; results = []" class="absolute right-3 text-text-muted hover:text-text-main transition-colors cursor-pointer">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div x-show="results.length > 0 && !selected" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-1"
                             class="absolute z-30 mt-1 w-full bg-surface border border-border rounded-lg shadow-xl max-h-60 overflow-y-auto custom-scrollbar divide-y divide-border">
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

                        <p x-show="searching" x-cloak class="text-xs text-sky-600 mt-1 flex items-center gap-1">
                            <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Mencari data pelanggan di database...
                        </p>

                        <p x-show="!searching && searched && results.length === 0 && !selected" x-cloak class="text-xs text-rose-500 mt-1 font-medium">
                            Pelanggan tidak ditemukan. Silakan periksa kembali CID atau Nama.
                        </p>
                    </div>

                    
                    <div x-show="selected && duplicateTickets.length > 0" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
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

                    
                    <div class="border border-border rounded-lg bg-surface p-3 space-y-2.5 shadow-xs">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-text-main truncate" x-text="selected?.nama || '—'"></div>
                                <div class="flex items-center gap-2 text-[11px] font-mono text-text-muted mt-0.5 truncate">
                                    <span class="px-1 rounded bg-surface-muted dark:bg-slate-800 font-bold text-sky-600 dark:text-sky-400" x-text="selected?.cid || '—'"></span>
                                    <span>•</span>
                                    <span x-text="selected?.no_hp || '—'"></span>
                                </div>
                            </div>
                            <span x-show="selected" x-cloak class="shrink-0 px-1.5 py-0.5 text-[10px] font-extrabold uppercase rounded bg-emerald-500 text-white">Active</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-border">
                            <div class="min-w-0">
                                <span class="block text-[9px] font-bold text-text-muted uppercase tracking-wider">Paket Aktif</span>
                                <div class="text-xs font-semibold text-text-main truncate" x-text="selected?.paket || '—'"></div>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[9px] font-bold text-text-muted uppercase tracking-wider">POP / ODP</span>
                                <div class="text-xs font-semibold text-text-main truncate" x-text="(selected?.pop || '—') + ' / ' + (selected?.odp || '—')"></div>
                            </div>
                        </div>

                        <div class="flex items-start gap-1.5 text-[11px] text-text-muted">
                            <svg class="h-3.5 w-3.5 text-rose-500 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="truncate" x-text="selected?.alamat || '—'"></span>
                        </div>

                        <div class="grid grid-cols-1 gap-1.5 pt-2 border-t border-border">
                            <div class="min-w-0">
                                <span class="block text-[9px] font-bold text-text-muted uppercase tracking-wider">Perangkat (ONT/Router)</span>
                                <div class="text-[11px] font-mono text-text-main truncate" x-text="selected?.perangkat || '—'"></div>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[9px] font-bold text-text-muted uppercase tracking-wider">GPS Coordinates</span>
                                <div class="text-[11px] font-mono text-sky-600 dark:text-sky-400 truncate">
                                    <template x-if="selected?.maps_url">
                                        <a :href="selected.maps_url" target="_blank" rel="noopener" class="hover:underline font-bold" x-text="selected.koordinat"></a>
                                    </template>
                                    <template x-if="!selected?.maps_url">
                                        <span class="text-text-muted">—</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                
                <section class="border border-border bg-surface-muted/40 dark:bg-slate-900/30 p-4 space-y-3.5 transition-colors">
                    <h2 class="text-[11px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">SECTION 03: COMPLAINT &amp; NOTES</h2>

                    
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
                                  class="w-full text-sm rounded-lg border bg-background p-3 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 transition-all resize-none"></textarea>
                        <p x-show="errors.detail_keluhan" x-cloak class="text-[11px] font-semibold text-rose-600 dark:text-rose-400" x-text="errors.detail_keluhan"></p>
                    </div>

                    <button type="button" @click="showExtra = !showExtra" class="flex items-center gap-1.5 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer">
                        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="showExtra ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                        </svg>
                        <span x-text="showExtra ? 'Sembunyikan Detail Tambahan' : 'Tampilkan Detail Tambahan (Catatan Teknis & Lampiran, Opsional)'"></span>
                    </button>

                    <div x-show="showExtra" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                                Catatan Teknis
                            </label>
                            <textarea x-model="catatanTeknis" rows="3" maxlength="2000"
                                      placeholder="NOC assessment, ping results, optical power checks (-dBm), redaman OPM, atau petunjuk awal untuk teknisi FOP..."
                                      class="w-full font-mono text-xs rounded-lg border border-border bg-slate-900/5 dark:bg-slate-900/40 p-3 text-text-main italic placeholder:text-text-muted placeholder:not-italic focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all resize-none"></textarea>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">
                                Lampiran (Evidence / OPM Screenshot)
                            </label>
                            <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-border border-dashed rounded-lg cursor-pointer bg-surface-muted/50 dark:bg-slate-900/20 hover:bg-sky-50/60 dark:hover:bg-slate-800/50 hover:border-sky-400 transition-colors">
                                <div class="flex flex-col items-center justify-center pt-3 pb-3">
                                    <svg class="w-6 h-6 mb-1 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <p class="text-xs text-text-secondary font-medium"><span class="font-bold text-sky-600">Klik untuk upload file</span> atau drag &amp; drop</p>
                                    <p class="text-[10px] text-text-muted mt-0.5">Maks. 5 file, tiap file maks. 5 MB (JPG, PNG, WEBP, PDF)</p>
                                </div>
                                <input type="file" x-ref="fileInput" @change="attachments = Array.from($event.target.files)" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden">
                            </label>
                            <p x-show="attachments.length > 0" x-cloak class="text-[10px] text-text-muted" x-text="attachments.length + ' file dipilih'"></p>
                        </div>
                    </div>
                </section>
            </div>

            
            <div class="shrink-0 px-3 sm:px-4 py-3 border-t border-border bg-surface-muted/60 dark:bg-slate-900/40 flex items-center justify-between gap-2">
                <button type="button" @click="resetForm()" class="inline-flex items-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-bold text-text-muted hover:text-text-main hover:bg-surface border border-transparent hover:border-border active:scale-95 transition-all duration-200 cursor-pointer">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>DISCARD</span> <span class="hidden sm:inline opacity-60 normal-case font-normal">(Esc)</span>
                </button>

                <button type="submit" :disabled="!selected || submitting" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-sky-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-sky-700 shadow-lg shadow-sky-600/25 hover:shadow-sky-600/40 active:scale-95 transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none disabled:active:scale-100">
                    <span x-show="!submitting">CREATE TICKET <span class="hidden sm:inline opacity-70 normal-case font-normal">(Ctrl+Enter)</span></span>
                    <span x-show="submitting" x-cloak>MENYIMPAN...</span>
                    <svg x-show="!submitting" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
    </div>

    
    <div class="flex-1 flex min-w-0 overflow-hidden bg-background ticket-queue-container">
    <div class="flex-1 flex flex-col min-w-[280px] overflow-hidden">

        <div class="shrink-0 p-3 border-b border-border bg-surface flex items-center gap-2 flex-wrap queue-toolbar">
            
            <div class="flex-1 basis-full sm:basis-auto min-w-[180px] flex items-center gap-1 bg-surface-muted dark:bg-slate-900 p-1 rounded-lg text-xs font-medium text-text-muted queue-toolbar-tabs">
                <template x-for="tab in tabs" :key="tab.value">
                    <button type="button" @click="setTab(tab.value)"
                            :class="taskFilter === tab.value ? 'bg-surface text-text-main font-bold shadow-sm' : 'hover:text-text-main'"
                            class="flex-1 min-w-0 py-1.5 px-1.5 sm:px-2 rounded-md transition-all duration-200 flex items-center justify-center gap-1 sm:gap-1.5 cursor-pointer">
                        
                        <span class="truncate sm:hidden" x-text="tab.shortLabel"></span>
                        <span class="truncate hidden sm:inline" x-text="tab.label"></span>
                        <span class="shrink-0 px-1.5 py-px rounded-full text-[10px] font-bold font-mono text-white" :class="tab.badgeClass" x-text="tabCounts[tab.value]"></span>
                    </button>
                </template>
            </div>

            <div class="shrink-0 flex items-center gap-2 ml-auto flex-wrap sm:flex-nowrap queue-toolbar-controls">
                
                <div class="relative min-w-[130px] max-w-[180px]">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-text-muted">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" x-model="searchQuery"
                           placeholder="Cari tiket..."
                           class="w-full pl-8 pr-7 py-1.5 text-xs rounded-lg border border-border bg-surface-muted dark:bg-slate-900 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 transition-all font-medium">
                    <button type="button" x-show="searchQuery" @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-2 flex items-center text-text-muted hover:text-text-main cursor-pointer"
                            title="Hapus pencarian">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                
                <select x-model="filterPriority"
                        class="max-w-[9.5rem] bg-surface-muted dark:bg-slate-900 border border-border text-xs font-medium rounded-lg px-2 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                    <option value="ALL">Semua Prioritas</option>
                    <option value="Urgent">🔴 Urgent</option>
                    <option value="High">🟠 High</option>
                    <option value="Medium">🟡 Medium</option>
                    <option value="low">🔵 Low</option>
                </select>

                
                <div class="hidden lg:flex items-center gap-0.5 p-0.5 rounded-lg bg-surface-muted dark:bg-slate-900 border border-border">
                    <button type="button" @click="setViewMode('table')" title="Tampilan tabel padat"
                            :class="viewMode === 'table' ? 'bg-surface text-sky-600 dark:text-sky-400 shadow-sm' : 'text-text-muted hover:text-text-main'"
                            class="p-1.5 rounded-md transition-all duration-200 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M3 10h18M3 15h18M3 20h18" />
                        </svg>
                    </button>
                    <button type="button" @click="setViewMode('cards')" title="Tampilan kartu"
                            :class="viewMode === 'cards' ? 'bg-surface text-sky-600 dark:text-sky-400 shadow-sm' : 'text-text-muted hover:text-text-main'"
                            class="p-1.5 rounded-md transition-all duration-200 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" />
                        </svg>
                    </button>
                </div>

                
                <button type="button" @click="refreshWorksheet()" :disabled="refreshing" title="Refresh antrean tiket"
                        class="p-1.5 rounded-lg text-text-muted hover:text-sky-600 dark:hover:text-sky-400 hover:bg-surface-muted dark:hover:bg-slate-900 disabled:opacity-50 transition-colors cursor-pointer">
                    <svg class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto overflow-x-hidden custom-scrollbar p-3">
            <template x-if="filteredTasks.length === 0">
                <p class="text-xs text-text-muted text-center py-10">Belum ada tiket di tab ini.</p>
            </template>

            
            <div x-show="activeViewMode === 'table'" class="border border-border bg-surface overflow-hidden shadow-xs">
                
                
                <div class="overflow-x-auto 2xl:overflow-x-hidden custom-scrollbar">
                    <table class="w-full min-w-[660px] 2xl:min-w-0 table-fixed text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-surface-muted/60 dark:bg-slate-900/40 text-text-muted border-b border-border uppercase tracking-wider text-[10px] font-bold">
                                
                                <th class="py-2.5 px-3 w-[16%] 2xl:w-[14%] whitespace-nowrap">
                                    <button type="button" @click="sortBy('code')" class="inline-flex items-center gap-1 cursor-pointer hover:text-text-main">
                                        Ticket ID &amp; Time
                                        <svg class="h-3 w-3 shrink-0 transition-transform" :class="sortField === 'code' && sortDir === 'desc' ? 'rotate-180' : ''" :style="sortField === 'code' ? '' : 'opacity:.35'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                </th>
                                <th class="py-2.5 px-3 w-[14%] 2xl:w-[12%] whitespace-nowrap">
                                    <button type="button" @click="sortBy('issue_category')" class="inline-flex items-center gap-1 cursor-pointer hover:text-text-main">
                                        Status / Issue
                                        <svg class="h-3 w-3 shrink-0 transition-transform" :class="sortField === 'issue_category' && sortDir === 'desc' ? 'rotate-180' : ''" :style="sortField === 'issue_category' ? '' : 'opacity:.35'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                </th>
                                <th class="py-2.5 px-3 w-[20%] 2xl:w-[18%]">Pelanggan (CID &amp; Contact)</th>
                                <th class="py-2.5 px-3 hidden 2xl:table-cell 2xl:w-[15%]">
                                    <button type="button" @click="sortBy('odp')" class="inline-flex items-center gap-1 cursor-pointer hover:text-text-main">
                                        Lokasi / POP / ODP
                                        <svg class="h-3 w-3 shrink-0 transition-transform" :class="sortField === 'odp' && sortDir === 'desc' ? 'rotate-180' : ''" :style="sortField === 'odp' ? '' : 'opacity:.35'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                </th>
                                <th class="py-2.5 px-3 w-[24%] 2xl:w-[22%]">Keluhan (Detail)</th>
                                <th class="py-2.5 px-3 text-right w-[26%] 2xl:w-[19%] whitespace-nowrap">Quick Dispatch Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template x-for="task in sortedTasks" :key="task.id">
                                <tr class="hover:bg-surface-muted/60 dark:hover:bg-slate-800/40 transition-colors align-top group"
                                    :data-ticket-row="task.id"
                                    :class="task.id === focusedTicketId ? 'bg-sky-50/60 dark:bg-sky-950/30 ring-1 ring-inset ring-sky-400/60' : ''">

                                    
                                    <td class="py-2.5 px-3">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            
                                            <button type="button" @click="openTicketDetail(task.id)"
                                                    class="font-mono font-extrabold text-sky-600 dark:text-sky-400 hover:underline truncate cursor-pointer text-left" x-text="task.code"></button>
                                            <span class="shrink-0 px-1.5 py-px rounded text-[9px] font-extrabold uppercase" :class="priorityBadgeClass(task.priority)" x-text="task.priority"></span>
                                        </div>
                                        
                                        <div class="mt-0.5" x-show="task.sla_label">
                                            <span class="inline-block px-1.5 py-px rounded text-[9px] font-bold border" :class="task.sla_badge_class" x-text="task.sla_label"></span>
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-1 text-[10px] text-text-muted font-mono min-w-0">
                                            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="9"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"></path>
                                            </svg>
                                            <span class="truncate" x-text="task.time + ' (' + task.time_at + ')'"></span>
                                        </div>
                                    </td>

                                    
                                    <td class="py-2.5 px-3">
                                        <span class="block truncate px-2 py-0.5 rounded text-[10px] font-bold" :class="issueBadgeClass(task.issue_category)"
                                              :title="task.issue_category" x-text="task.issue_category || 'Tanpa Kategori'"></span>
                                        <div class="mt-1 flex items-center gap-1 text-[10px] text-text-muted min-w-0">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="bucketDotClass(task.bucket)"></span>
                                            <span class="truncate" x-text="task.status_label"></span>
                                        </div>
                                    </td>

                                    
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-text-main truncate" :title="task.customer_name" x-text="task.customer_name"></div>
                                        <div class="mt-0.5 flex items-center gap-1.5 text-[11px] font-mono text-text-muted min-w-0">
                                            <span class="font-bold text-text-secondary truncate" x-text="task.cid"></span>
                                            <span class="shrink-0">•</span>
                                            <a :href="'https://wa.me/' + task.customer_phone" target="_blank" rel="noopener"
                                               class="text-emerald-600 dark:text-emerald-400 hover:underline truncate" x-text="task.customer_phone"></a>
                                        </div>
                                        
                                        <div class="2xl:hidden mt-0.5 text-[10px] text-text-muted truncate" :title="task.pop + ' / ' + task.odp + ' — ' + task.address"
                                             x-text="task.pop + ' / ' + task.odp"></div>
                                    </td>

                                    
                                    <td class="py-2.5 px-3 text-[11px] hidden 2xl:table-cell">
                                        <div class="font-semibold text-text-secondary truncate" :title="task.pop + ' / ' + task.odp">
                                            <span x-text="task.pop"></span>
                                            <span class="text-text-muted font-normal" x-text="' / ' + task.odp"></span>
                                        </div>
                                        <div class="text-[10px] text-text-muted truncate" :title="task.address" x-text="task.address"></div>
                                    </td>

                                    
                                    <td class="py-2.5 px-3">
                                        <p class="text-[11px] text-text-secondary line-clamp-2 leading-snug" :title="task.desc" x-text="task.desc"></p>
                                        <div class="flex items-center gap-2 flex-wrap text-[10px] text-text-muted mt-0.5"
                                             x-show="task.escalated_noc_by || task.escalated_fop_by || task.returned_to_helpdesk_by || task.closed_by">
                                            <span x-show="task.escalated_noc_by">→ NOC: <span class="font-semibold text-text-secondary" x-text="task.escalated_noc_by"></span></span>
                                            <span x-show="task.escalated_fop_by">→ FOP: <span class="font-semibold text-text-secondary" x-text="task.escalated_fop_by"></span></span>
                                            <span x-show="task.returned_to_helpdesk_by">↩ Helpdesk: <span class="font-semibold text-text-secondary" x-text="task.returned_to_helpdesk_by"></span></span>
                                        </div>
                                    </td>

                                    
                                    <td class="py-2.5 px-3">
                                        <div class="flex items-center justify-end gap-1 sm:gap-1.5 flex-nowrap">
                                            <button type="button" x-show="task.actions?.can_close" :disabled="actionLoadingId === task.id"
                                                    @click="closeTicket(task)"
                                                    class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 cursor-pointer shrink-0 shadow-2xs">
                                                Selesai
                                            </button>
                                            <button type="button" x-show="task.actions?.can_escalate_noc" :disabled="actionLoadingId === task.id"
                                                    @click="escalateTicket(task, 'noc')"
                                                    class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-600 text-white hover:bg-amber-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 cursor-pointer shrink-0 shadow-2xs">
                                                Ke NOC
                                            </button>
                                            <button type="button" x-show="task.actions?.can_escalate_fop" :disabled="actionLoadingId === task.id"
                                                    @click="escalateTicket(task, 'fop')"
                                                    class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-sky-600 text-white hover:bg-sky-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 cursor-pointer shrink-0 shadow-2xs">
                                                Ke FOP
                                            </button>
                                            <button type="button" x-show="task.actions?.can_return_to_helpdesk" :disabled="actionLoadingId === task.id"
                                                    @click="returnTicketToHelpdesk(task)"
                                                    class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide bg-slate-600 text-white hover:bg-slate-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 cursor-pointer shrink-0 shadow-2xs">
                                                Kembalikan
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div x-show="activeViewMode === 'cards'" class="space-y-2">
                <template x-for="(task, index) in sortedTasks" :key="task.id">
                    
                    <div class="ticket-card flex flex-col 2xl:flex-row items-stretch 2xl:items-center justify-between gap-2.5 p-3 2xl:py-2.5 rounded-xl border border-l-2 border-border bg-surface hover:border-sky-500/60 hover:shadow-md transition-[transform,box-shadow,border-color,background-color] duration-200 group"
                         :data-ticket-row="task.id"
                         :class="{
                             'border-l-rose-500': task.priority === 'Urgent',
                             'border-l-amber-500': task.priority === 'High',
                             'border-l-border': task.priority !== 'Urgent' && task.priority !== 'High',
                             'ring-1 ring-sky-400/60 bg-sky-50/60 dark:bg-sky-950/30': task.id === focusedTicketId,
                         }"
                         :style="`animation-delay:${Math.min(index, 8) * 30}ms`">
                        
                        
                        
                        
                        <div @click="openTicketDetail(task.id)" role="button" tabindex="0"
                             @keydown.enter="openTicketDetail(task.id)"
                             class="flex-1 min-w-0 cursor-pointer ticket-card-inner grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2 2xl:flex 2xl:flex-row 2xl:items-center 2xl:gap-2.5">

                            
                            
                            <div class="ticket-card-col1 flex items-center gap-1.5 flex-wrap min-w-0 md:col-span-2 md:pb-2 md:border-b md:border-border/70 2xl:col-span-1 2xl:pb-0 2xl:border-b-0 2xl:shrink-0 2xl:min-w-[210px]">
                                <span class="text-xs font-bold font-mono text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800/60 px-2 py-0.5 rounded shadow-2xs" x-text="task.code"></span>
                                
                                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded uppercase shrink-0"
                                    :class="{
                                        'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800': task.priority === 'Urgent',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800': task.priority === 'High',
                                        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700': task.priority === 'Medium' || task.priority === 'low'
                                    }"
                                    x-text="task.priority">
                                </span>

                                
                                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded border shrink-0" x-show="task.sla_label" :class="task.sla_badge_class" x-text="task.sla_label"></span>

                                <span class="font-semibold text-[10px] flex items-center gap-1 shrink-0 px-2 py-0.5 rounded border"
                                    :class="{
                                        'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800': task.bucket === 'diproses',
                                        'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40 border-sky-200 dark:border-sky-800': task.bucket === 'masuk',
                                        'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800': task.bucket === 'selesai',
                                        'text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700': task.bucket === 'dibatalkan'
                                    }">
                                    <span class="w-1.5 h-1.5 rounded-full animate-pulse"
                                        :class="{
                                            'bg-amber-500': task.bucket === 'diproses',
                                            'bg-sky-500': task.bucket === 'masuk',
                                            'bg-emerald-500': task.bucket === 'selesai',
                                            'bg-slate-400': task.bucket === 'dibatalkan'
                                        }">
                                    </span>
                                    <span x-text="bucketLabel(task.bucket)"></span>
                                </span>

                                
                                <span class="2xl:hidden ml-auto shrink-0 flex items-center gap-1 text-[10px] text-text-muted font-mono">
                                    <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="9"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"></path>
                                    </svg>
                                    <span x-text="task.time"></span>
                                </span>
                            </div>

                            
                            <div class="min-w-0 2xl:w-auto 2xl:min-w-[190px] 2xl:max-w-[240px] 2xl:shrink-0">
                                <div class="text-xs font-bold text-text-main group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors truncate" x-text="task.customer_name"></div>
                                <div class="flex items-center gap-2 text-[11px] text-text-muted mt-0.5 flex-wrap">
                                    <span class="font-mono text-sky-600 dark:text-sky-400 font-semibold" x-text="'CID: ' + task.cid"></span>
                                    <span class="text-text-muted">•</span>
                                    <span class="flex items-center gap-1 font-mono text-[10px]">
                                        <svg class="h-3 w-3 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <span x-text="task.customer_phone"></span>
                                    </span>
                                </div>
                                
                                <div class="2xl:hidden mt-0.5 flex items-center gap-1 text-[10px] text-text-muted min-w-0">
                                    <svg class="h-3 w-3 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="truncate" :title="task.address" x-text="task.pop + ' / ' + task.odp + ' — ' + task.address"></span>
                                </div>
                            </div>

                            
                            
                            <div class="ticket-card-col3 min-w-0 pr-1 md:border-l md:border-border/70 md:pl-4 2xl:w-auto 2xl:flex-1 2xl:border-l-0 2xl:pl-0">
                                
                                <div class="flex items-center gap-2 min-w-0">
                                    <template x-if="task.issue_category">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-surface-muted dark:bg-slate-800 text-text-secondary border border-border shrink-0 truncate max-w-[60%]" x-text="task.issue_category"></span>
                                    </template>
                                    <h4 x-show="task.title && task.title !== task.issue_category"
                                        class="text-xs font-bold text-text-main truncate" x-text="task.title"></h4>
                                </div>
                                
                                <p class="text-[11px] text-text-muted mt-0.5 italic leading-snug line-clamp-2 2xl:line-clamp-1" x-text="task.desc" :title="task.desc"></p>
                                
                                
                                <div class="flex items-center gap-2 flex-wrap text-[10px] text-text-muted font-medium mt-0.5" x-show="task.escalated_noc_by || task.escalated_fop_by || task.returned_to_helpdesk_by || task.closed_by">
                                    <span x-show="task.escalated_noc_by">→ NOC: <span class="font-semibold text-text-secondary" x-text="task.escalated_noc_by"></span></span>
                                    <span x-show="task.escalated_fop_by">→ FOP: <span class="font-semibold text-text-secondary" x-text="task.escalated_fop_by"></span></span>
                                    <span x-show="task.returned_to_helpdesk_by">↩ Ret Helpdesk: <span class="font-semibold text-text-secondary" x-text="task.returned_to_helpdesk_by"></span></span>
                                    <span x-show="task.closed_by">✓ Selesai: <span class="font-semibold text-emerald-600 dark:text-emerald-400" x-text="task.closed_by"></span></span>
                                </div>
                            </div>

                            
                            <div class="hidden 2xl:block shrink-0 text-right min-w-[85px]">
                                <span class="text-[10px] text-text-muted font-mono flex items-center justify-end gap-1">
                                    <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="9"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"></path>
                                    </svg>
                                    <span x-text="task.time"></span>
                                </span>
                            </div>
                        </div>

                        
                        <div x-show="task.actions && (task.actions.can_close || task.actions.can_escalate_noc || task.actions.can_escalate_fop || task.actions.can_return_to_helpdesk)"
                             class="pt-2 2xl:pt-0 border-t 2xl:border-t-0 border-border/60 flex items-center justify-end gap-2 w-full 2xl:w-auto">
                            
                            
                            <div class="flex items-center gap-1.5 w-full sm:w-auto justify-stretch sm:justify-end">
                                <button type="button" x-show="task.actions.can_close" :disabled="actionLoadingId === task.id"
                                        @click="closeTicket(task)"
                                        class="flex-1 sm:flex-initial text-center justify-center inline-flex items-center px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 transition-all duration-200 cursor-pointer shadow-2xs shrink-0">
                                    Selesai
                                </button>
                                <button type="button" x-show="task.actions.can_escalate_noc" :disabled="actionLoadingId === task.id"
                                        @click="escalateTicket(task, 'noc')"
                                        class="flex-1 sm:flex-initial text-center justify-center inline-flex items-center px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-600 text-white hover:bg-amber-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 transition-all duration-200 cursor-pointer shadow-2xs shrink-0">
                                    Ke NOC
                                </button>
                                <button type="button" x-show="task.actions.can_escalate_fop" :disabled="actionLoadingId === task.id"
                                        @click="escalateTicket(task, 'fop')"
                                        class="flex-1 sm:flex-initial text-center justify-center inline-flex items-center px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-sky-600 text-white hover:bg-sky-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 transition-all duration-200 cursor-pointer shadow-2xs shrink-0">
                                    Ke FOP
                                </button>
                                <button type="button" x-show="task.actions.can_return_to_helpdesk" :disabled="actionLoadingId === task.id"
                                        @click="returnTicketToHelpdesk(task)"
                                        class="flex-1 sm:flex-initial text-center justify-center inline-flex items-center px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-slate-600 text-white hover:bg-slate-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 transition-all duration-200 cursor-pointer shadow-2xs shrink-0">
                                    Kembalikan
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            
            <template x-if="worksheetTotalCount > tasks.length">
                <p class="block text-center text-xs font-semibold text-text-muted py-3 border-t border-border/60 mt-3">
                    <span x-text="'+ ' + (worksheetTotalCount - tasks.length) + ' tiket aktif lainnya (di luar 30 terbaru)'"></span>
                </p>
            </template>

            
            <p class="mt-3 text-[10px] text-text-muted italic">
                Menampilkan <span x-text="filteredTasks.length"></span> tiket.
                Selesai &amp; Dibatalkan gak masuk antrean kerja — ada di halaman sendiri.
            </p>
        </div>
    </div>
    </div>
</div>
<?php echo $__env->make('tickets.partials.action-dialog', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make('tickets.partials.detail-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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

            // Sort kolom panel kanan (docs/plan/analisa-percepatan-alur-helpdesk-noc.md
            // §6.1) — clientside di atas `filteredTasks`, null = urutan asli dari
            // server (priority/SLA dulu, lihat worksheetTasks()). 'code' = Ticket ID
            // & Time, 'issue_category' = Status/Issue, 'odp' = Lokasi/POP/ODP.
            // Pelanggan sengaja gak sortable (keputusan user).
            sortField: null,
            sortDir: 'asc',

            // Row yang lagi "fokus" keyboard (§6.3/§6.4) — disimpan sebagai id,
            // BUKAN index array, biar gak "loncat" kalau ada tiket baru nyempil
            // lewat broadcast realtime di tengah user navigasi Arrow Up/Down.
            focusedTicketId: null,

            // Arrow/C/V/B row-navigasi WAJIB nonaktif selagi drawer detail kebuka
            // (lihat komentar di listener open-ticket-drawer/close-ticket-drawer
            // di root elemen) — di-toggle lewat event, bukan baca DOM drawer.
            drawerOpen: false,

            // Panel kanan: tabel (default) atau kartu. Disimpan di localStorage
            // sama kayak formOpen — dibaca sebelum render pertama biar gak
            // kedip ganti mode pas halaman dimuat.
            viewMode: localStorage.getItem('ticket-view-mode') === 'cards' ? 'cards' : 'table',

            // Filter prioritas panel kanan — client-side atas array `tasks`.
            // Nilainya WAJIB sama persis App\Enums\FopTaskPriority (perhatikan
            // 'low' huruf kecil, sisanya kapital — itu memang value enumnya).
            filterPriority: 'ALL',

            // Input pencarian cepat panel kanan (nomor tiket, nama, CID, desa, keluhan)
            searchQuery: '',

            // Layar sempit (< lg) — dipaksa mode kartu lewat activeViewMode.
            // Ambangnya lg, bukan md: sidebar app 256px static dari md, jadi di
            // tablet 768px area kontennya cuma ~512px — tabel 5 kolom pun jadi
            // scroll horizontal terus. Pilihan `viewMode` user TETAP disimpan
            // apa adanya, biar balik sendiri begitu layarnya lebar lagi.
            narrowViewport: window.matchMedia('(max-width: 1023px)').matches,

            get activeViewMode() {
                return this.narrowViewport ? 'cards' : this.viewMode;
            },

            // Label tab = "di tangan siapa" (TicketHandler), bukan bucket.
            // shortLabel dipakai di layar sempit — "Assign NOC" kepanjangan
            // buat tab sepertiga lebar layar HP.
            tabs: [
                { value: 'helpdesk', label: 'Ticket', shortLabel: 'Ticket', badgeClass: 'bg-slate-700 dark:bg-slate-600' },
                { value: 'noc', label: 'Assign NOC', shortLabel: 'NOC', badgeClass: 'bg-amber-600' },
                { value: 'fop', label: 'Assign FOP', shortLabel: 'FOP', badgeClass: 'bg-sky-600' },
            ],

            // Panel form kebuka/kelipat — dibaca SEBELUM render pertama
            // (bukan di init()) supaya gak ada kedipan panel kebuka lalu
            // langsung nutup pas halaman dimuat dalam kondisi terlipat.
            formOpen: localStorage.getItem('ticket-form-open') !== 'false',

            // Transisi lebar panel baru diaktifkan setelah frame pertama.
            // Tanpa ini, halaman yang dimuat dalam kondisi terlipat bakal
            // "menganimasikan" dirinya sendiri dari 0 pas load — kelihatan
            // seperti panel mental sendiri.
            animReady: false,

            // Master Issue — dari Master Data sungguhan (Task 1 rancangan), bukan mock lagi.
            issueCategories: <?php echo json_encode($issueCategories, 15, 512) ?>,

            // Snapshot awal panel kanan — data server-side saat halaman dimuat.
            // Item baru hasil submit di-prepend lokal (optimistik) di submitForm(),
            // update dari aktor lain masuk lewat broadcast Reverb (initEchoListeners()).
            tasks: <?php echo json_encode($initialTasks, 15, 512) ?>,

            // Total tiket aktif TANPA kena cap panel (Gap #4) — dibandingin sama
            // tasks.length buat nampilin indikator "+N lainnya".
            worksheetTotalCount: <?php echo e($worksheetTotalCount); ?>,

            // POP yang kelihatan user — subscribe Echo.private('tickets.{popId}')
            // per POP ini (Gap #3).
            allowedPopIds: <?php echo json_encode($allowedPopIds, 15, 512) ?>,

            init() {
                this.initEchoListeners();
                const narrow = window.matchMedia('(max-width: 1023px)');
                const updateNarrow = () => { this.narrowViewport = narrow.matches; };
                narrow.addEventListener('change', updateNarrow);
                window.addEventListener('resize', updateNarrow);
                // Dua rAF: satu buat nunggu Alpine selesai render class awal,
                // satu lagi buat memastikan browser sudah melukisnya sebelum
                // properti transition dipasang.
                requestAnimationFrame(() => requestAnimationFrame(() => { this.animReady = true; }));
            },

            setFormOpen(open) {
                this.formOpen = open;
                localStorage.setItem('ticket-form-open', open ? 'true' : 'false');
                // Fokus baru dipindah setelah animasi lebar kelar — fokus ke
                // elemen yang lagi bergerak bikin browser auto-scroll dan
                // gerakannya kelihatan tersendat.
                if (open) {
                    setTimeout(() => this.$refs.searchInput?.focus(), 320);
                }
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
                    const res = await fetch('<?php echo e(route('tickets.worksheet-tasks')); ?>', {
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

            setViewMode(mode) {
                this.viewMode = mode;
                localStorage.setItem('ticket-view-mode', mode);
            },

            // Jumlah tiket per tab — SENGAJA tanpa filter prioritas, biar badge
            // tetap nunjukin antrean penuh tiap tab walau list sedang disaring.
            get tabCounts() {
                return {
                    helpdesk: this.tasks.filter(t => t.handler === 'helpdesk').length,
                    noc: this.tasks.filter(t => t.handler === 'noc').length,
                    fop: this.tasks.filter(t => t.handler === 'fop').length,
                };
            },

            priorityBadgeClass(priority) {
                return {
                    Urgent: 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300',
                    High: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
                    Medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
                }[priority] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
            },

            // Warna badge kategori issue di-derive dari NAMA kategori (Master
            // Issue bisa nambah kapan aja, jadi gak ada peta warna hardcode
            // lengkap) — kategori yang gak dikenal jatuh ke netral, bukan error.
            issueBadgeClass(category) {
                const name = (category || '').toLowerCase();
                if (name.includes('los') || name.includes('down') || name.includes('mati')) return 'bg-rose-600 text-white';
                if (name.includes('lemot') || name.includes('lambat') || name.includes('latency')) return 'bg-amber-500 text-white';
                if (name.includes('putus') || name.includes('kabel') || name.includes('cut')) return 'bg-purple-600 text-white';
                if (!category) return 'bg-surface-muted dark:bg-slate-800 text-text-secondary border border-border';
                return 'bg-sky-600 text-white';
            },

            bucketDotClass(bucket) {
                return {
                    masuk: 'bg-sky-500',
                    diproses: 'bg-amber-500',
                    selesai: 'bg-emerald-500',
                    dibatalkan: 'bg-slate-400',
                }[bucket] || 'bg-slate-400';
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
            /**
             * Detail tiket = drawer kanan (tickets/partials/detail-drawer.blade.php),
             * BUKAN navigasi ke /tickets/{id}. Partial-nya yang fetch isinya dari
             * tickets.detail-json; halaman ini cuma ngasih id.
             */
            openTicketDetail(id) {
                // Klik mouse & Enter (row focus keyboard) sama-sama lewat sini —
                // sinkronin focusedTicketId biar highlight row selalu ngikutin
                // detail yang lagi dibuka, gak peduli dibuka lewat mouse atau keyboard.
                this.focusedTicketId = id;
                window.dispatchEvent(new CustomEvent('open-ticket-drawer', { detail: { id } }));
            },

            /**
             * Tombol aksi DI DALAM drawer cuma men-dispatch niat — konfirmasi &
             * POST tetap lewat fungsi di bawah ini, satu jalur dengan tombol di
             * tabel/kartu (biar array `tasks` & counter tetap ke-update sekali).
             */
            handleDrawerAction({ id, action }) {
                const task = this.tasks.find(t => t.id === id);

                if (! task) {
                    return;
                }

                switch (action) {
                    case 'close': this.closeTicket(task); break;
                    case 'noc': this.escalateTicket(task, 'noc'); break;
                    case 'fop': this.escalateTicket(task, 'fop'); break;
                    case 'return': this.returnTicketToHelpdesk(task); break;
                    case 'cancel': this.cancelTicket(task); break;
                }
            },

            closeTicket(task) {
                window.confirmTicketAction({
                    title: 'Selesaikan Tiket',
                    message: `Tandai tiket ${task.code} selesai?`,
                    label: 'Apa yang sudah dikerjakan? (opsional)',
                    required: false,
                    confirmText: 'Ya, Selesaikan',
                    icon: 'success',
                    onConfirm: (reason) => this.performTicketAction(
                        task.id, `<?php echo e(url('/tickets')); ?>/${task.id}/close`, { reason }
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
                        task.id, `<?php echo e(url('/tickets')); ?>/${task.id}/escalate`, { target, reason }
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
                        task.id, `<?php echo e(url('/tickets')); ?>/${task.id}/return-to-helpdesk`, { reason }
                    ),
                });
            },

            /**
             * Pembatalan pra-FOP — permission `tickets.cancel`, alasan WAJIB
             * (ReasonValidationRule di server). Cuma muncul dari drawer: tombol
             * merah di tabel/kartu bikin aksi destruktif kepencet sambil scroll.
             */
            cancelTicket(task) {
                window.confirmTicketAction({
                    title: 'Batalkan Tiket',
                    message: `Batalkan tiket ${task.code}?`,
                    label: 'Alasan pembatalan (wajib diisi)',
                    required: true,
                    confirmText: 'Ya, Batalkan',
                    confirmType: 'danger',
                    icon: 'error',
                    onConfirm: (reason) => this.performTicketAction(
                        task.id, `<?php echo e(url('/tickets')); ?>/${task.id}/cancel`, { reason }
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
                    // Tiket yang baru diaksi udah gak relevan lagi di drawer —
                    // flag aksinya pasti berubah, dan barisnya bisa hilang dari
                    // daftar. Tutup, jangan biarkan nampilin state basi.
                    window.dispatchEvent(new CustomEvent('close-ticket-drawer'));
                } catch (e) {
                    this.showToast('Aksi gagal, coba lagi.', 'error');
                } finally {
                    this.actionLoadingId = null;
                }
            },

            // Filter per TAB = per `handler` (di tangan siapa tiketnya), BUKAN
            // per bucket/status pengerjaan. Juga mendukung filter prioritas & pencarian cepat (searchQuery).
            get filteredTasks() {
                const q = (this.searchQuery || '').toLowerCase().trim();

                return this.tasks.filter(t => {
                    if (t.handler !== this.taskFilter) return false;
                    if (this.filterPriority !== 'ALL' && t.priority !== this.filterPriority) return false;

                    if (q) {
                        const code = (t.code || '').toLowerCase();
                        const name = (t.customer_name || t.customer?.name || '').toLowerCase();
                        const cid = (t.cid || t.customer?.cid || '').toLowerCase();
                        const phone = (t.customer_phone || t.phone || '').toLowerCase();
                        const address = (t.address || t.customer?.village || '').toLowerCase();
                        const pop = (t.pop || '').toLowerCase();
                        const odp = (t.odp || '').toLowerCase();
                        const title = (t.title || '').toLowerCase();
                        const desc = (t.desc || '').toLowerCase();

                        return code.includes(q) || name.includes(q) || cid.includes(q) || phone.includes(q) || address.includes(q) || pop.includes(q) || odp.includes(q) || title.includes(q) || desc.includes(q);
                    }

                    return true;
                });
            },

            /**
             * `filteredTasks` + sort manual (§6.1) — dipisah dari filteredTasks
             * biar count/empty-state di tempat lain (gak peduli urutan) tetap
             * baca filteredTasks apa adanya, cuma dua x-for (tabel & kartu) yang
             * pakai getter ini.
             */
            get sortedTasks() {
                if (! this.sortField) {
                    return this.filteredTasks;
                }

                const field = this.sortField;
                const dir = this.sortDir === 'desc' ? -1 : 1;

                return [...this.filteredTasks].sort((a, b) => {
                    const av = (a[field] || '').toString().toLowerCase();
                    const bv = (b[field] || '').toString().toLowerCase();

                    if (av < bv) return -1 * dir;
                    if (av > bv) return 1 * dir;

                    return 0;
                });
            },

            /**
             * Klik header kolom — sama kolom kepencet lagi → toggle ASC/DESC,
             * kolom lain → pindah sort, mulai dari ASC. Manual sort OVERRIDE
             * default urutan priority/SLA dari server sampai user ganti tab
             * atau reload halaman (§6.1).
             */
            sortBy(field) {
                if (this.sortField === field) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortField = field;
                    this.sortDir = 'asc';
                }
            },

            /**
             * Index row yang lagi fokus di `sortedTasks` (BUKAN filteredTasks —
             * navigasi jalan di urutan yang KELIATAN user, ngikut sort manual
             * kalau lagi aktif). -1 kalau gak ada yang fokus / tiketnya udah
             * hilang dari daftar (mis. abis di-close).
             */
            get focusedRowIndex() {
                return this.sortedTasks.findIndex(t => t.id === this.focusedTicketId);
            },

            /**
             * Arrow Up/Down (§6.3) — gerak fokus antar row, clamp di ujung
             * (gak wrap-around), scroll row ke viewport kalau kegeser keluar.
             */
            moveRowFocus(delta) {
                const list = this.sortedTasks;
                if (list.length === 0) return;

                const current = this.focusedRowIndex;
                const next = current === -1 ? 0 : Math.min(Math.max(current + delta, 0), list.length - 1);

                this.focusedTicketId = list[next].id;

                this.$nextTick(() => {
                    document.querySelector(`[data-ticket-row="${this.focusedTicketId}"]`)
                        ?.scrollIntoView({ block: 'nearest' });
                });
            },

            focusFirstRow() {
                this.focusedTicketId = this.sortedTasks[0]?.id ?? null;
            },

            /**
             * Ganti tab Ticket/Assign NOC/Assign FOP — dipakai klik tombol tab
             * MAUPUN Arrow Left/Right (switchTabByDelta di bawah). Ganti tab
             * = isi `filteredTasks` beda total, jadi fokus row WAJIB direset ke
             * baris pertama (dikonfirmasi user, §6.3) — index lama gak nyambung
             * ke tiket yang sama di tab baru.
             */
            setTab(value) {
                this.taskFilter = value;
                this.focusFirstRow();
            },

            switchTabByDelta(delta) {
                const values = this.tabs.map(t => t.value);
                const current = values.indexOf(this.taskFilter);
                const next = Math.min(Math.max(current + delta, 0), values.length - 1);

                this.setTab(values[next]);
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
                    const res = await fetch(`<?php echo e(route('tickets.duplicates')); ?>?customer_id=${customerId}`, {
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
                    const res = await fetch(`<?php echo e(route('tickets.lookup-customer')); ?>?q=${encodeURIComponent(q)}`, {
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
                    // Drawer detail punya listener Escape SENDIRI (lihat
                    // x-on:keydown.escape.window="close()" di
                    // detail-drawer.blade.php) — kalau drawer lagi kebuka,
                    // biarin drawer itu doang yang nanganin, JANGAN ikut
                    // resetForm() di sini. Sebelumnya dua-duanya nembak
                    // bareng: drawer ketutup TAPI form ikut kereset dan
                    // fokus kepaksa pindah ke search box, jadi row-navigasi
                    // (Arrow/C/V/B) kececer gak bisa dipake abis nutup
                    // drawer pakai Escape.
                    if (this.drawerOpen) return;
                    this.resetForm();
                    return;
                }
                // "N" buka/lipat panel form — cuma waktu fokus TIDAK di field
                // input, biar gak ketelan waktu user lagi ngetik keluhan.
                if ((e.key === 'n' || e.key === 'N') && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    if (this.isTypingTarget(e)) return;
                    e.preventDefault();
                    this.setFormOpen(!this.formOpen);
                    return;
                }

                // Row-navigasi panel kanan (docs/plan/analisa-percepatan-alur-helpdesk-noc.md
                // §6.3/§6.4) — Arrow Up/Down gerak fokus antar row, Arrow Left/Right
                // ganti tab (reset fokus ke row pertama), Enter buka drawer,
                // C/V/B dispatch aksi row yang fokus (modal konfirmasi TETAP
                // muncul, tombol Quick Dispatch existing gak disentuh/dihapus —
                // ini jalur TAMBAHAN, bukan pengganti). Semua nonaktif kalau
                // fokus lagi di input/textarea/select ATAU drawer detail kebuka
                // (drawer punya navigasi/scroll sendiri).
                if (this.isTypingTarget(e) || this.drawerOpen || e.ctrlKey || e.metaKey || e.altKey) {
                    return;
                }

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.moveRowFocus(1);
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.moveRowFocus(-1);
                    return;
                }
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    this.switchTabByDelta(1);
                    return;
                }
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this.switchTabByDelta(-1);
                    return;
                }

                const focusedTask = this.sortedTasks.find(t => t.id === this.focusedTicketId);
                if (! focusedTask) return;

                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.openTicketDetail(focusedTask.id);
                    return;
                }
                // C/V/B cuma nembak kalau aksinya emang kebuka buat tiket ini
                // (`task.actions` — sumber yang sama persis dipakai gerbang
                // tombol Quick Dispatch, lihat Ticket::actionFlagsFor()) — biar
                // gak ada jalur belakang yang lolosin aksi yang mestinya
                // ke-disable.
                if (e.key === 'c' || e.key === 'C') {
                    if (!focusedTask.actions?.can_close) return;
                    e.preventDefault();
                    this.closeTicket(focusedTask);
                    return;
                }
                if (e.key === 'v' || e.key === 'V') {
                    if (!focusedTask.actions?.can_escalate_noc) return;
                    e.preventDefault();
                    this.escalateTicket(focusedTask, 'noc');
                    return;
                }
                if (e.key === 'b' || e.key === 'B') {
                    if (!focusedTask.actions?.can_escalate_fop) return;
                    e.preventDefault();
                    this.escalateTicket(focusedTask, 'fop');
                }
            },

            /**
             * True kalau target event ini field yang nerima ketikan —
             * dipakai SEMUA shortcut (N, Arrow, C/V/B) biar gak ke-hijack
             * waktu user lagi ngetik di search box / textarea keluhan / dsb.
             */
            isTypingTarget(e) {
                const tag = (e.target.tagName || '').toLowerCase();

                return tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
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
                    const res = await fetch('<?php echo e(route('tickets.store')); ?>', {
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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/tickets/create.blade.php ENDPATH**/ ?>