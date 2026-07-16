@extends('layouts.app')

@section('title', 'Task Saya')

@section('content')
<div x-data="{}" class="max-w-2xl mx-auto px-4 py-6 space-y-5">

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <div>
                <h1 class="text-base font-semibold text-text-main leading-tight">Task Saya Hari Ini</h1>
                <p class="text-xs text-text-muted">
                    Halo, {{ auth()->user()->name }} 👋 &mdash; {{ now()->translatedFormat('l, d F Y') }}
                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-2xl font-bold font-mono text-text-main leading-none">{{ $tasks->count() }}</p>
            <p class="text-[11px] text-text-muted">task hari ini</p>
        </div>
    </div>

    {{-- ══ Banner Notifikasi Real-Time (S8.2-T010) ═══════════════════ --}}
    {{-- Banner muncul saat FOP assign task baru via echo channel private-teknisi.{userId} --}}
    <div x-data="technicianNotifier()"
         x-show="banner.visible"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-3"
         class="flex items-start gap-3 px-4 py-3 rounded-lg border shadow-md cursor-pointer"
         style="background:var(--color-primary-soft,#eff6ff); border-color:var(--color-primary-border,#93c5fd); color:var(--color-primary,#2563eb)"
         @click="scrollToCard()"
         id="task-notification-banner"
         role="alert"
         aria-live="polite">

        {{-- Bell Icon --}}
        <svg class="h-5 w-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold leading-tight" x-text="banner.title"></p>
            <p class="text-xs mt-0.5 opacity-80" x-text="banner.subtitle"></p>
            <p class="text-[11px] mt-1 opacity-60">Klik banner ini untuk melihat task baru &darr;</p>
        </div>

        {{-- Close button --}}
        <button @click.stop="dismissBanner()"
                class="shrink-0 p-1 rounded hover:opacity-70 transition-opacity"
                title="Tutup notifikasi"
                aria-label="Tutup notifikasi">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

    {{-- ══ Task Hari Ini ════════════════════════════════════════════ --}}
    @if($tasks->count() > 0)
    <div class="space-y-3" id="today-task-list">
        @foreach($tasks as $task)
        <div class="bg-surface border border-border rounded-lg overflow-hidden
            @if($task->status->value === 'in_progress') ring-2 ring-amber-400 @endif">

            {{-- Status bar atas --}}
            @php
                $barColor = match($task->status->value) {
                    'terjadwal'   => 'var(--color-info)',
                    'in_progress' => 'var(--color-warning)',
                    'selesai'     => 'var(--color-success)',
                    'dibatalkan'  => 'var(--color-error)',
                    default       => 'var(--color-border)',
                };
            @endphp
            <div class="h-1 w-full" style="background: {{ $barColor }}"></div>

            <div class="px-4 py-4">

                {{-- Header task --}}
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Tipe badge --}}
                        <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border {{ $task->task_type->cardClasses() }}">
                            {{ $task->task_type->label() }}
                        </span>
                        {{-- Status badge --}}
                        @php
                            $statusStyle = match(true) {
                                $task->status->value === 'terjadwal'   => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
                                $task->status->value === 'in_progress' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
                                $task->status->value === 'selesai'     => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
                                $task->status->value === 'dibatalkan'  => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
                                $task->status->value === 'pending' && $task->report_deferred => 'background:#f5f3ff; color:#6d28d9; border-color:#c4b5fd',
                                $task->status->value === 'pending'     => 'background:#fefce8; color:#a16207; border-color:#fde68a',
                                default                                => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
                            };
                        @endphp
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border" style="{{ $statusStyle }}">
                            {{ $task->status->displayLabel($task->report_deferred) }}
                        </span>
                        @if($task->isOverSla())
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                              style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                            Melewati SLA
                        </span>
                        @endif
                        @if($task->status->value === 'terjadwal' && $task->scheduled_at && $task->scheduled_at->isPast() && !$task->scheduled_at->isToday())
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                              style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                            Jadwal Terlewat
                        </span>
                        @endif
                    </div>
                    <span class="font-mono text-[11px] text-text-muted shrink-0">{{ $task->task_number }}</span>
                </div>

                {{-- Nama pelanggan + alamat --}}
                <p class="font-semibold text-text-main">{{ $task->customer?->full_name ?? $task->title }}</p>
                @if($task->customer)
                <p class="text-xs text-text-muted mt-0.5">
                    {{ $task->customer->clean_address ?? '' }}
                    @if($task->pop)
                        &mdash; {{ $task->pop->name }}
                    @endif
                </p>
                @endif

                {{-- Jadwal --}}
                <div class="flex items-center gap-1.5 mt-2 text-xs text-text-secondary">
                    <svg class="h-3.5 w-3.5 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-mono font-semibold">
                        {{ $task->scheduled_at?->isToday() ? $task->scheduled_at->format('H:i') : $task->scheduled_at?->translatedFormat('d M, H:i') }}
                    </span>
                    <span class="text-text-muted">· SLA {{ $task->sla_minutes }} menit</span>
                </div>

                {{-- Countdown SLA Eksekusi — aktif saat in_progress --}}
                @if($task->status->value === 'in_progress' && $task->started_at)
                @php
                    $slaDeadlineIso = $task->started_at
                        ->addMinutes($task->sla_minutes)
                        ->toIso8601String();
                @endphp
                <div class="mt-2">
                    <x-countdown-timer
                        deadline="{{ $slaDeadlineIso }}"
                        :total-seconds="$task->sla_minutes * 60"
                        label="Sisa SLA"
                    />
                </div>
                @endif

                {{-- Ringkasan Waktu Survey/Pemasangan — tampil setelah task selesai --}}
                @if($task->status->value === 'selesai' && $task->started_at && $task->completed_at)
                @php
                    $taskStartedAt   = $task->started_at;
                    $taskCompletedAt = $task->completed_at;
                    $actualMinutes   = (int) $taskStartedAt->diffInMinutes($taskCompletedAt);
                    $actualHours     = intdiv($actualMinutes, 60);
                    $actualRemMins   = $actualMinutes % 60;
                    $durationLabel   = $actualHours > 0
                        ? "{$actualHours} jam {$actualRemMins} menit"
                        : "{$actualRemMins} menit";
                    $isOverSla       = $actualMinutes > $task->sla_minutes;
                    $typeLabel       = $task->task_type->value === 'PSB' ? 'Pemasangan' : 'Survey';
                @endphp
                <div class="mt-2 flex items-center gap-1.5">
                    <svg class="h-3 w-3 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[11px] font-medium text-text-secondary">
                        Waktu {{ $typeLabel }}:
                    </span>
                    <span class="text-[11px] font-mono font-semibold text-text-main">
                        {{ $taskStartedAt->format('H:i') }} – {{ $taskCompletedAt->format('H:i') }}
                    </span>
                    <span class="text-[11px] font-semibold px-1.5 py-0.5 rounded"
                          style="{{ $isOverSla
                              ? 'background:var(--color-error-bg); color:var(--color-error)'
                              : 'background:var(--color-success-bg); color:var(--color-success)' }}">
                        {{ $durationLabel }}
                    </span>
                </div>
                @endif

                {{-- Tombol aksi --}}
                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-border">
                    <a href="{{ route('tasks.show', $task) }}"
                       class="flex-1 text-center text-xs font-semibold py-2 px-3 border border-border rounded-md bg-background hover:bg-surface-muted text-text-secondary transition-colors">
                        Buka Detail
                    </a>

                    @if($task->status->value === 'terjadwal')
                        @if($task->task_type->value === 'SURVEY')
                            @if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                            <form action="{{ route('customers.survey.start', $task->customer_id) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                                        style="background:var(--color-warning)">
                                    Mulai Survey
                                </button>
                            </form>
                            @endif
                        @elseif($task->task_type->value === 'PSB')
                            @if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                            <form action="{{ route('customers.installation.start', $task->customer_id) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                                        style="background:var(--color-warning)">
                                    Mulai Pemasangan
                                </button>
                            </form>
                            @endif
                        @else
                            @can('statusStart', $task)
                            <form action="{{ route('tasks.start', $task) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                                        style="background:var(--color-warning)">
                                    {{ $task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value ? 'Mulai Maintenance' : 'Mulai Task' }}
                                </button>
                            </form>
                            @endcan
                        @endif
                    @endif

                    @can('statusComplete', $task)
                    @if(in_array($task->status->value, ['in_progress', 'pending']))
                        @php
                            $reportUrl = match(true) {
                                $task->task_type->value === 'SURVEY' => route('customers.survey.report', $task->customer_id),
                                $task->task_type->value === 'PSB' => route('customers.installation.report', $task->customer_id),
                                default => route('tasks.maintenance.report', $task),
                            };
                        @endphp
                        @if($task->status->value === 'in_progress')
                            <x-task.report-choice-dialog :task="$task" :report-url="$reportUrl" class="flex-1 justify-center">
                                Isi Laporan
                            </x-task.report-choice-dialog>
                        @else
                            <a href="{{ $reportUrl }}"
                               class="flex-1 text-center text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                               style="background:var(--color-success)">
                                Lanjutkan Laporan
                            </a>
                        @endif
                    @endif
                    @endcan
                </div>

            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="bg-surface border border-border rounded-lg flex flex-col items-center justify-center py-16 gap-3"
         data-empty-tasks>
        <svg class="h-10 w-10 text-text-muted opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        <p class="text-sm text-text-muted">Tidak ada task untuk hari ini.</p>
        <p class="text-xs text-text-muted">Hubungi FOP jika ada penugasan yang belum muncul.</p>
    </div>
    @endif

    {{-- ══ Task Mendatang ════════════════════════════════════════════ --}}
    @if($upcomingTasks->count() > 0)
    <div data-section="mendatang">
        <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted mb-3">Jadwal Mendatang</p>
        <div class="space-y-2">
            @foreach($upcomingTasks as $task)
            <a href="{{ route('tasks.show', $task) }}"
               class="flex items-center justify-between bg-surface border border-border rounded-lg px-4 py-3 hover:bg-surface-muted transition-colors">
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border {{ $task->task_type->cardClasses() }}">
                        {{ $task->task_type->label() }}
                    </span>
                    <div>
                        <p class="text-sm font-medium text-text-main">{{ $task->customer?->full_name ?? $task->title }}</p>
                        <p class="text-[11px] text-text-muted">
                            {{ $task->scheduled_at?->translatedFormat('l, d M') }} &middot;
                            {{ $task->scheduled_at?->format('H:i') }}
                        </p>
                    </div>
                </div>
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
            @endforeach
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
/**
 * technicianNotifier — Alpine.js component (S8.2-T010)
 * ─────────────────────────────────────────────────────
 * Listen ke Laravel Reverb channel private-teknisi.{userId} untuk event TaskScheduled.
 * Saat event diterima:
 *   1. Tampilkan banner notifikasi di atas list task.
 *   2. Fetch HTML card parsial dari /tasks-saya/partial/{taskId} lalu inject ke DOM.
 *   3. Klik banner → smooth-scroll ke card yang baru diinject.
 *   4. Auto-dismiss banner setelah 10 detik.
 */
function technicianNotifier() {
    return {
        banner: {
            visible: false,
            title: '',
            subtitle: '',
            taskId: null,
        },
        dismissTimer: null,

        init() {
            const userId = {{ auth()->id() }};

            // Retry loop: tunggu window.Echo tersedia (race condition antara
            // Alpine mount dan Vite bundle selesai load echo.js).
            // Retry maksimal 10x dengan interval 300ms (total ~3 detik).
            let attempts = 0;
            const maxAttempts = 10;

            const attach = () => {
                if (typeof window.Echo !== 'undefined') {
                    window.Echo.private(`teknisi.${userId}`)
                        .listen('TaskScheduled', (event) => {
                            this.handleTaskScheduled(event);
                        });
                    return;
                }

                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(attach, 300);
                } else {
                    console.warn('[technicianNotifier] window.Echo tidak tersedia setelah 3 detik. Notifikasi real-time tidak aktif.');
                }
            };

            attach();
        },

        handleTaskScheduled(event) {
            // Hitung jadwal yang tampil di banner
            let jadwalLabel = '';
            if (event.scheduled_at) {
                const dt = new Date(event.scheduled_at);
                // Format: YYYY-MM-DD HH:mm (sederhana, tanpa dependency locale)
                const pad = (n) => String(n).padStart(2, '0');
                jadwalLabel = `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
            }

            // Teks banner berbeda berdasarkan konteks event
            const isRescheduled = event.event_type === 'rescheduled';
            this.banner.title    = isRescheduled
                ? `Jadwal diperbarui: ${event.title}`
                : `Task baru ditugaskan: ${event.title}`;
            this.banner.subtitle = jadwalLabel ? `Jadwal: ${jadwalLabel}` : '';
            this.banner.taskId   = event.id;
            this.banner.visible  = true;

            // Auto-dismiss setelah 10 detik
            clearTimeout(this.dismissTimer);
            this.dismissTimer = setTimeout(() => this.dismissBanner(), 10000);

            // Inject card task baru ke DOM — hanya untuk event 'created'
            // Untuk reschedule, card sudah ada di DOM (perlu reload untuk update jadwal)
            if (!isRescheduled) {
                this.injectTaskCard(event.id);
            }
        },

        dismissBanner() {
            this.banner.visible = false;
            clearTimeout(this.dismissTimer);
        },

        scrollToCard() {
            if (!this.banner.taskId) return;
            const card = document.getElementById(`task-card-${this.banner.taskId}`);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Highlight card sebentar
                card.style.transition = 'box-shadow 0.3s';
                card.style.boxShadow = '0 0 0 3px var(--color-primary, #2563eb)';
                setTimeout(() => { card.style.boxShadow = ''; }, 2000);
            }
            this.dismissBanner();
        },

        async injectTaskCard(taskId) {
            // Cek apakah card sudah ada (mencegah duplikasi jika event diterima dua kali)
            if (document.getElementById(`task-card-${taskId}`)) return;

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
                    return;
                }

                const html = await res.text();

                // Dapatkan container task list hari ini
                let container = document.getElementById('today-task-list');

                if (!container) {
                    // Jika container belum ada (halaman kosong / tidak ada task hari ini),
                    // cari parent wrapper dan buat container baru
                    const emptyState = document.querySelector('[data-empty-tasks]');
                    if (emptyState) {
                        // Sembunyikan empty state
                        emptyState.style.display = 'none';
                    }
                    // Buat container baru dan inject sebelum section Mendatang atau di akhir content
                    container = document.createElement('div');
                    container.id = 'today-task-list';
                    container.className = 'space-y-3';
                    // Cari titik sisip di atas section Mendatang
                    const mendatangSection = document.querySelector('[data-section="mendatang"]');
                    const contentWrapper = document.querySelector('.max-w-2xl');
                    if (mendatangSection && contentWrapper) {
                        contentWrapper.insertBefore(container, mendatangSection);
                    } else if (contentWrapper) {
                        contentWrapper.appendChild(container);
                    }
                }

                // Inject card di awal list (task baru tampil di atas)
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const newCard = wrapper.firstElementChild;
                if (newCard) {
                    container.insertBefore(newCard, container.firstChild);
                }

            } catch (err) {
                console.error('[technicianNotifier] Error saat inject task card:', err);
            }
        },
    };
}
</script>
@endpush
@endsection

