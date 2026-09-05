@extends('layouts.app')

@section('title', 'Task Saya')

@section('content')
<div x-data="taskWorksheet()" class="max-w-4xl mx-auto space-y-5">

    {{-- ══ Welcome Stats Banner (Mobile Optimized Premium Design) ════════════════════ --}}
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 rounded-3xl p-5 sm:p-6 text-white shadow-xl shadow-slate-950/20 relative overflow-hidden border border-slate-800 select-none">
        <!-- Background Decorative Glow -->
        <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-sky-500/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-10 -top-10 w-44 h-44 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            {{-- Welcome title --}}
            <div>
                <div class="flex items-center gap-1.5 text-sky-400 text-[10px] font-bold uppercase tracking-wider mb-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Status Aktif &middot; GPS Terhubung</span>
                </div>
                <h1 class="text-lg sm:text-xl font-bold tracking-tight text-white font-ui">Halo, {{ auth()->user()->name }} 👋</h1>
                <p class="text-xs text-slate-400 mt-0.5 font-medium font-ui">
                    {{ now()->translatedFormat('l, d F Y') }}
                </p>
            </div>

            {{-- Stats sub-cards --}}
            <div class="grid grid-cols-3 gap-2 sm:gap-3 bg-slate-800/60 dark:bg-slate-950/60 p-2.5 rounded-2xl border border-slate-700/60 backdrop-blur-sm">
                <div class="text-center px-1">
                    <p class="text-[9px] uppercase tracking-wider text-slate-400 font-bold font-ui">Total</p>
                    <p class="text-base font-black font-mono text-white mt-0.5" x-text="getTaskCount('all')"></p>
                </div>
                <div class="text-center border-x border-slate-700/80 px-2">
                    <p class="text-[9px] uppercase tracking-wider text-rose-400 font-bold font-ui">Terlewat</p>
                    <p class="text-base font-black font-mono text-rose-400 mt-0.5" x-text="getTaskCount('overdue')"></p>
                </div>
                <div class="text-center px-1">
                    <p class="text-[9px] uppercase tracking-wider text-emerald-400 font-bold font-ui">Selesai</p>
                    <p class="text-base font-black font-mono text-emerald-400 mt-0.5" x-text="getTaskCount('completed')"></p>
                </div>
            </div>
        </div>

        {{-- Quick action links at the bottom --}}
        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-300">
            <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-ui font-medium">Jadwal Tugas Lapangan</span>
            </div>
            <a href="{{ route('tasks.own.history') }}" class="font-bold text-sky-400 hover:text-sky-300 flex items-center gap-1 transition-colors font-ui">
                <span>Lihat Riwayat Selesai</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>

    {{-- ══ Stok Saya (ADHOC-54, rancangan-ui.md §2.5) — barang yang lagi
         dipegang, belum dipasang/dikembalikan. Embedded, bukan halaman/menu
         terpisah — cuma punya sendiri, gak digerbangi permission. ═════ --}}
    @if($myCustodies->isNotEmpty() || $mySerials->isNotEmpty())
    <div class="bg-surface border border-border rounded-2xl p-4 shadow-xs">
        <h3 class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-3 font-ui flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            Stok Saya
        </h3>
        <div class="flex flex-wrap gap-2">
            @foreach($mySerials as $serial)
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800/60 text-[11px] font-ui">
                <span class="font-semibold text-sky-700 dark:text-sky-300">{{ $serial->item->name }}</span>
                <span class="font-mono text-sky-600 dark:text-sky-400">SN {{ $serial->serial_number }}</span>
            </span>
            @endforeach
            @foreach($myCustodies as $custody)
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-surface-muted border border-border text-[11px] font-ui">
                <span class="font-semibold text-text-main">{{ $custody->item->name }}</span>
                <span class="font-mono text-text-secondary">{{ rtrim(rtrim(number_format((float) $custody->qty_remaining, 2, ',', '.'), '0'), ',') }} {{ $custody->item->unit }}</span>
                <span class="text-text-muted">· {{ $custody->ageLabel() }}</span>
            </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ══ Mobile Search and Sorting controls ════════════════════════ --}}
    <div class="flex flex-col sm:flex-row items-center gap-3">
        {{-- Search input --}}
        <div class="relative w-full">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4.5 w-4.5 text-text-muted/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3"></path>
            </svg>
            <input type="text" x-model="searchQuery" placeholder="Cari nama, alamat, nomor task..." 
                class="w-full h-11 pl-10 pr-9 rounded-2xl border border-border bg-surface text-xs text-text-main placeholder-text-disabled focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all font-ui shadow-sm">
            <button x-show="searchQuery" @click="searchQuery = ''" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-main transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- ══ Horizontal slider filter tabs ═════════════════════════════ --}}
    <div class="flex items-center gap-1.5 overflow-x-auto pb-1.5 -mx-4 px-4 scrollbar-none select-none">
        <button @click="activeTab = 'all'" 
            :class="activeTab === 'all' ? 'bg-primary text-white font-bold shadow-md shadow-primary/10' : 'bg-surface border border-border text-text-secondary hover:bg-surface-muted hover:text-text-main font-medium'"
            class="px-4 py-2 rounded-xl text-xs transition duration-150 whitespace-nowrap cursor-pointer active:scale-95 font-ui">
            Semua (<span x-text="getTaskCount('all')"></span>)
        </button>

        <button @click="activeTab = 'overdue'" 
            :class="activeTab === 'overdue' ? 'bg-rose-600 text-white font-bold shadow-md shadow-rose-600/10' : 'bg-surface border border-border text-text-secondary hover:bg-surface-muted hover:text-text-main font-medium'"
            class="px-4 py-2 rounded-xl text-xs transition duration-150 whitespace-nowrap flex items-center gap-1.5 cursor-pointer active:scale-95 font-ui">
            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
            <span>Terlewat</span>
            <span x-text="getTaskCount('overdue')" class="text-[10px] bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 font-bold px-1.5 py-0.2 rounded-md"></span>
        </button>

        <button @click="activeTab = 'survey'" 
            :class="activeTab === 'survey' ? 'bg-primary text-white font-bold shadow-md shadow-primary/10' : 'bg-surface border border-border text-text-secondary hover:bg-surface-muted hover:text-text-main font-medium'"
            class="px-4 py-2 rounded-xl text-xs transition duration-150 whitespace-nowrap cursor-pointer active:scale-95 font-ui">
            Survey
        </button>

        <button @click="activeTab = 'psb'" 
            :class="activeTab === 'psb' ? 'bg-primary text-white font-bold shadow-md shadow-primary/10' : 'bg-surface border border-border text-text-secondary hover:bg-surface-muted hover:text-text-main font-medium'"
            class="px-4 py-2 rounded-xl text-xs transition duration-150 whitespace-nowrap cursor-pointer active:scale-95 font-ui">
            Pemasangan
        </button>

        <button @click="activeTab = 'maintenance'" 
            :class="activeTab === 'maintenance' ? 'bg-primary text-white font-bold shadow-md shadow-primary/10' : 'bg-surface border border-border text-text-secondary hover:bg-surface-muted hover:text-text-main font-medium'"
            class="px-4 py-2 rounded-xl text-xs transition duration-150 whitespace-nowrap cursor-pointer active:scale-95 font-ui">
            Maintenance
        </button>
    </div>

    {{-- ══ Task Hari Ini Container ════════════════════════════════════════════ --}}
    <div x-data="technicianNotifier()" class="space-y-3.5">
        <div class="space-y-3.5" id="today-task-list">
            @foreach($tasks as $task)
                @include('tasks.partials.own-card', ['task' => $task])
            @endforeach
        </div>

        {{-- Empty State Card --}}
        <div id="empty-state-card"
             class="bg-surface border border-border rounded-2xl flex flex-col items-center justify-center py-16 px-4 gap-3 select-none text-center shadow-xs transition-all duration-200"
             style="{{ $tasks->count() > 0 ? 'display: none;' : '' }}">
            <div class="w-12 h-12 rounded-2xl bg-surface-muted border border-border flex items-center justify-center text-text-muted opacity-60">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <p class="text-sm text-text-main font-bold font-ui">Tidak ada task yang ditemukan</p>
            <p class="text-xs text-text-muted font-ui">Coba ubah filter atau kata kunci pencarian Anda.</p>
            <button type="button" @click="searchQuery = ''; activeTab = 'all'" class="text-xs font-semibold text-primary hover:underline mt-1 font-ui cursor-pointer">Reset Filter</button>
        </div>
    </div>

    {{-- ══ Task Mendatang ════════════════════════════════════════════ --}}
    @if($upcomingTasks->count() > 0)
    <div data-section="mendatang" class="pt-4 border-t border-border/85 select-none">
        <h3 class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-3 font-ui">Jadwal Mendatang</h3>
        <div class="space-y-3">
            @foreach($upcomingTasks as $task)
            <a href="{{ route('tasks.show', $task) }}"
               class="relative flex items-center justify-between bg-surface border border-border rounded-xl pl-5 pr-4 py-3.5 hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-sm transition-all duration-200 group">
                
                {{-- Left accent strip --}}
                <div class="absolute left-0 top-0 bottom-0 w-1 bg-slate-300 dark:bg-slate-700 rounded-l-xl"></div>

                <div class="flex items-center gap-3">
                    <span class="text-[9px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full border {{ $task->task_type->cardClasses() }} shrink-0">
                        {{ $task->task_type->label() }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-text-main truncate group-hover:text-primary transition-colors font-ui">{{ $task->customer?->full_name ?? $task->title }}</p>
                        <p class="text-[11px] text-text-muted mt-0.5 font-ui">
                            {{ $task->scheduled_at?->translatedFormat('l, d M') }} &middot;
                            <span class="font-mono">{{ $task->scheduled_at?->format('H:i') }}</span>
                        </p>
                    </div>
                </div>
                <svg class="h-4 w-4 text-text-muted shrink-0 group-hover:text-text-main transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
/**
 * taskWorksheet — Alpine.js filtering & sorting logic
 */
function taskWorksheet() {
    return {
        searchQuery: '',
        activeTab: 'all',
        sortBy: 'time',
        tasks: [],

        init() {
            this.updateTasksList();
            
            // Listen to real-time updates event
            document.addEventListener('task-list-updated', () => {
                this.updateTasksList();
            });

            this.$watch('searchQuery', () => this.filterTasks());
            this.$watch('activeTab', () => this.filterTasks());
            this.$watch('sortBy', () => this.sortTasks());
        },

        updateTasksList() {
            this.tasks = Array.from(document.querySelectorAll('[data-task-id]')).map(el => {
                const id = el.getAttribute('data-task-id');
                const type = el.getAttribute('data-task-type');
                const status = el.getAttribute('data-task-status');
                const isOverdue = el.getAttribute('data-is-overdue') === 'true';
                const name = el.getAttribute('data-customer-name') || '';
                const address = el.getAttribute('data-customer-address') || '';
                const number = el.getAttribute('data-task-number') || '';
                const priority = parseInt(el.getAttribute('data-priority-weight') || '5');
                const timestamp = parseInt(el.getAttribute('data-scheduled-timestamp') || '0');
                return { el, id, type, status, isOverdue, name, address, number, priority, timestamp };
            });

            this.filterTasks();
            this.sortTasks();
        },

        filterTasks() {
            let visibleCount = 0;
            this.tasks.forEach(task => {
                const matchesSearch = !this.searchQuery || 
                    task.name.includes(this.searchQuery.toLowerCase()) || 
                    task.address.includes(this.searchQuery.toLowerCase()) || 
                    task.number.includes(this.searchQuery.toLowerCase());
                    
                let matchesTab = true;
                if (this.activeTab === 'overdue') {
                    matchesTab = task.isOverdue;
                } else if (this.activeTab === 'survey') {
                    matchesTab = task.type === 'SURVEY';
                } else if (this.activeTab === 'psb') {
                    matchesTab = task.type === 'PSB';
                } else if (this.activeTab === 'maintenance') {
                    matchesTab = task.type === 'MAINTENANCE';
                }
                
                const isVisible = matchesSearch && matchesTab;
                if (isVisible) {
                    task.el.style.display = '';
                    visibleCount++;
                } else {
                    task.el.style.display = 'none';
                }
            });
            
            const emptyState = document.getElementById('empty-state-card');
            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? '' : 'none';
            }
        },

        getStatusWeight(status) {
            if (status === 'in_progress') return 1;
            if (status === 'pending') return 2;
            if (status === 'terjadwal') return 3;
            return 4;
        },

        sortTasks() {
            const container = document.getElementById('today-task-list');
            if (!container) return;
            
            this.tasks.sort((a, b) => {
                const weightA = this.getStatusWeight(a.status);
                const weightB = this.getStatusWeight(b.status);
                
                if (weightA !== weightB) {
                    return weightA - weightB;
                }

                if (this.sortBy === 'priority') {
                    if (a.priority !== b.priority) {
                        return a.priority - b.priority;
                    }
                    return a.timestamp - b.timestamp;
                } else {
                    if (a.timestamp !== b.timestamp) {
                        return a.timestamp - b.timestamp;
                    }
                    return a.priority - b.priority;
                }
            });

            this.tasks.forEach(task => {
                container.appendChild(task.el);
            });
        },

        getTaskCount(tab) {
            if (tab === 'all') return this.tasks.length;
            if (tab === 'overdue') return this.tasks.filter(t => t.isOverdue).length;
            if (tab === 'completed') return this.tasks.filter(t => t.status === 'selesai').length;
            if (tab === 'survey') return this.tasks.filter(t => t.type === 'SURVEY').length;
            if (tab === 'psb') return this.tasks.filter(t => t.type === 'PSB').length;
            if (tab === 'maintenance') return this.tasks.filter(t => t.type === 'MAINTENANCE').length;
            return 0;
        }
    };
}

/**
 * technicianNotifier — real-time Laravel Reverb channels handler
 */
function technicianNotifier() {
    return {
        init() {
            const userId = {{ auth()->id() }};
            let attempts = 0;
            const maxAttempts = 10;

            const attach = () => {
                if (typeof window.Echo !== 'undefined') {
                    window.Echo.private(`teknisi.${userId}`)
                        .listen('TaskScheduled', (event) => {
                            this.handleTaskScheduled(event);
                        })
                        // Task tim (2-3 orang) — kalau anggota LAIN yang mulai/selesaikan
                        // task yang lagi ditampilkan di kartu, kartu itu harus ikut update
                        // sendiri tanpa refresh manual. Reuse refreshTaskCard() yang sama
                        // dipakai TaskScheduled(rescheduled/team_changed) di atas — kalau
                        // kartunya belum ada di list (task baru buat user ini), otomatis
                        // fallback nyuntik kartu baru (lihat refreshTaskCard()).
                        .listen('TaskStarted', (event) => this.refreshTaskCard(event.id))
                        .listen('TaskCompleted', (event) => this.refreshTaskCard(event.id));
                    return;
                }

                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(attach, 300);
                } else {
                    console.warn('[technicianNotifier] window.Echo tidak tersedia. Notifikasi real-time tidak aktif.');
                }
            };

            attach();
        },

        handleTaskScheduled(event) {
            let jadwalLabel = '';
            if (event.scheduled_at) {
                const dt = new Date(event.scheduled_at);
                const pad = (n) => String(n).padStart(2, '0');
                jadwalLabel = `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
            }

            const isRefresh = event.event_type === 'rescheduled' || event.event_type === 'team_changed';
            const isRemoval = event.event_type === 'removed' || event.event_type === 'cancelled';

            const toastType = isRemoval ? 'warning' : (isRefresh ? 'info' : 'success');
            const toastTitle = isRemoval
                ? 'Task Dibatalkan / Dipindahkan'
                : (isRefresh ? 'Jadwal Task Diperbarui' : 'Task Baru Ditugaskan');
            const toastDesc = `${event.title}` + (!isRemoval && jadwalLabel ? ` • Jadwal: ${jadwalLabel}` : '');

            if (window.Toast) {
                window.Toast.show(toastType, toastTitle, toastDesc, 15000);
            }

            if (isRemoval) {
                this.removeTaskCard(event.id);
            } else if (isRefresh) {
                this.refreshTaskCard(event.id);
            } else {
                this.injectTaskCard(event.id);
            }
        },

        scrollToCard(taskId) {
            if (!taskId) return;
            setTimeout(() => {
                const card = document.getElementById(`task-card-${taskId}`);
                if (card) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    card.style.transition = 'box-shadow 0.3s, border-color 0.3s';
                    card.style.boxShadow = '0 0 0 3px var(--color-primary, #0284c7)';
                    setTimeout(() => { card.style.boxShadow = ''; }, 2500);
                }
            }, 300);
        },

        async injectTaskCard(taskId) {
            if (document.getElementById(`task-card-${taskId}`)) return;

            const freshCard = await this.fetchCard(taskId);
            if (!freshCard) return;

            let container = document.getElementById('today-task-list');
            if (!container) {
                container = document.createElement('div');
                container.id = 'today-task-list';
                container.className = 'space-y-3';
                
                const contentWrapper = document.getElementById('today-task-list').parentElement;
                if (contentWrapper) {
                    contentWrapper.insertBefore(container, contentWrapper.firstChild);
                }
            }

            container.insertBefore(freshCard, container.firstChild);
            this.initAlpineOn(freshCard);
            
            // Dispatch event to update Alpine's task array cache
            document.dispatchEvent(new CustomEvent('task-list-updated'));
            this.scrollToCard(taskId);
        },

        async refreshTaskCard(taskId) {
            const existing = document.getElementById(`task-card-${taskId}`);
            if (!existing) {
                this.injectTaskCard(taskId);
                return;
            }

            const freshCard = await this.fetchCard(taskId);
            if (!freshCard) return;

            existing.replaceWith(freshCard);
            this.initAlpineOn(freshCard);
            
            // Dispatch event to update Alpine's task array cache
            document.dispatchEvent(new CustomEvent('task-list-updated'));
            this.scrollToCard(taskId);
        },

        removeTaskCard(taskId) {
            const card = document.getElementById(`task-card-${taskId}`);
            if (!card) return;
            card.remove();

            // Dispatch event to update Alpine's task array cache
            document.dispatchEvent(new CustomEvent('task-list-updated'));
        },

        async fetchCard(taskId) {
            try {
                const res = await fetch(`/tasks-saya/partial/${taskId}`, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!res.ok) {
                    console.warn(`[technicianNotifier] Gagal fetch card task #${taskId}: HTTP ${res.status}`);
                    return null;
                }

                const html = await res.text();
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                return wrapper.firstElementChild;
            } catch (err) {
                console.error('[technicianNotifier] Error saat fetch task card:', err);
                return null;
            }
        },

        initAlpineOn(card) {
            if (card && window.Alpine && typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(card);
            }
        },
    };
}
</script>
@endpush
