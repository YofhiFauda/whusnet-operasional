    {{-- ══════════════════════════════════════════════════════════════
     FOP TASK — MOBILE CARD COMPONENT
     Desain khusus layar sentuh (mobile & small tablet < 768px):
     - Touch targets ergonomis (min 44x44px)
     - Hierarki visual tinggi (Kategori, SLA timer, Judul, Pelanggan, Status, Prioritas)
     - Quick Action Bar: Call, Maps, Switch, Edit, Detail, Cancel
     - Terintegrasi dengan Alpine.js handler fopTaskPageHandler()
     ══════════════════════════════════════════════════════════════ --}}
@php
    $isHistory = $isHistory ?? false;
    $ticket = $task->relationLoaded('ticket') ? $task->ticket : null;
    $customer = $task->relationLoaded('customer') ? $task->customer : null;
    $taskRelation = $task->relationLoaded('task') ? $task->task : null;
    $villageRelation = $task->relationLoaded('village') ? $task->village : null;
    $teamRelation = $task->relationLoaded('team') ? $task->team : null;
    $technicians = $task->relationLoaded('technicians') ? $task->technicians : collect([]);

    $canDeleteTask = !in_array($task->category->value, ['SURVEY', 'PSB'], true) && !$ticket && !$isHistory;
    
    // Pelanggan & Kontak (dari ticket atau customer relation)
    $customerName = $ticket?->customer_name ?? ($customer?->full_name ?? null);
    $customerPhone = $ticket?->customer_phone ?? ($customer?->primary_phone ?? null);
    $customerCid = ($ticket && $ticket->relationLoaded('customer') ? $ticket->customer?->cid : null)
        ?? ($customer?->cid ?? null);
    $customerAddress = $ticket?->customer_address ?? ($customer?->address ?? null);
    $customerLat = $ticket?->customer_latitude ?? ($customer?->latitude ?? null);
    $customerLng = $ticket?->customer_longitude ?? ($customer?->longitude ?? null);
    
    // Status Logic
    $statusValue = $task->status->value;
    $statusLabel = $taskRelation
        ? $taskRelation->status->displayLabel($taskRelation->report_deferred)
        : ($statusValue === 'draft' ? 'Belum Ditugaskan' : $task->status->displayLabel());
    $statusClasses = $taskRelation
        ? $taskRelation->status->displayBadgeClasses($taskRelation->report_deferred)
        : ($statusValue === 'draft' ? 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50' : $task->status->displayBadgeClasses());
@endphp

<div class="bg-white dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700/80 rounded-xl p-4 shadow-xs hover:shadow-md transition-all duration-200 flex flex-col gap-3 relative overflow-hidden"
     id="fop-task-card-{{ $task->id }}"
     data-pop-id="{{ $task->pop_id }}">

    {{-- ══ 1. Card Header: Kategori, Task Number, Status / SLA ══ --}}
    <div class="flex items-start justify-between gap-2 border-b border-slate-100 dark:border-slate-700/50 pb-2.5">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wide border {{ $task->category instanceof \App\Enums\TaskType ? $task->category->badgeClasses() : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ $task->category instanceof \App\Enums\TaskType ? $task->category->value : $task->category }}
            </span>
            <span class="text-xs font-mono font-semibold text-slate-500 dark:text-slate-400">
                {{ $task->task_number }}
            </span>
        </div>

        <div class="flex items-center gap-1.5 shrink-0" id="mobile-status-cell-{{ $task->id }}">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $statusClasses }}">
                {{ $statusLabel }}
            </span>
        </div>
    </div>

    {{-- ══ 2. Jadwal & SLA Notice (Jika Ada) ══ --}}
    @if($task->client_request_date && !$isHistory)
        <div class="flex items-center gap-1.5 text-xs">
            @if($task->client_request_date->lte(\Illuminate\Support\Carbon::today()))
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800/50 animate-pulse">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    JADWAL HARI INI
                </span>
            @else
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/50">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Jadwal Klien: {{ $task->client_request_date->format('d/m/Y') }}
                </span>
            @endif
        </div>
    @elseif(!$isHistory && $task->relationLoaded('customer') && !$task->isScheduledForFutureClientDate())
        <div class="flex items-center">
            <x-countdown-timer
                deadline="{{ $task->slaDeadline()->toIso8601String() }}"
                :total-seconds="$task->slaTotalSeconds()"
                label="SLA {{ $task->category->label() }}"
                :compact="true"
            />
        </div>
    @endif

    {{-- ══ 3. Judul Tugas & Info Pelanggan ══ --}}
    <div class="space-y-1">
        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 leading-snug">
            {{ $task->tugas }}
        </h3>
        
        @if($customerName || $customerCid)
            <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="font-semibold">{{ $customerName ?? 'Pelanggan' }}</span>
                @if($customerCid)
                    <span class="font-mono text-[11px] text-slate-400 bg-slate-100 dark:bg-slate-700/60 px-1.5 py-0.2 rounded">CID: {{ $customerCid }}</span>
                @endif
            </div>
        @endif
    </div>

    {{-- ══ 4. Issue / Gangguan / Alasan Batal ══ --}}
    @if($task->status->value === 'dibatalkan' && $task->cancel_reason)
        <div class="flex items-start gap-2 p-2.5 rounded-lg bg-red-50/80 dark:bg-red-950/30 border border-red-200/70 dark:border-red-900/40 text-red-700 dark:text-red-300 text-xs">
            <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="flex-1 leading-snug">
                <span class="font-bold">Alasan Batal:</span> {{ $task->cancel_reason }}
            </div>
        </div>
    @elseif($task->issue)
        <div class="flex items-start gap-2 p-2.5 rounded-lg bg-red-50/80 dark:bg-red-950/30 border border-red-200/70 dark:border-red-900/40 text-red-700 dark:text-red-300 text-xs">
            <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1 leading-snug">
                <span class="font-bold">Issue:</span> {{ $task->issue }}
            </div>
        </div>
    @endif

    {{-- ══ 5. Metadata Grid: Area, POP, Tanggal, Prioritas ══ --}}
    <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800">
        <div>
            <span class="block text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Area</span>
            <span class="font-medium text-slate-800 dark:text-slate-200 truncate block" title="{{ $villageRelation?->name ?? '—' }}">
                {{ $villageRelation?->name ?? '—' }}
            </span>
        </div>

        <div>
            <span class="block text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tanggal Task</span>
            <span class="font-mono font-medium text-slate-800 dark:text-slate-200">
                {{ $task->task_date ? $task->task_date->format('d/m/Y H:i') : '—' }}
            </span>
        </div>

        <div class="col-span-2 flex items-center justify-between pt-1.5 border-t border-slate-200/60 dark:border-slate-700/60">
            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mr-1">Prioritas:</span>
                @if($canEditFopTaskType && !$isHistory)
                    <select @change="updatePriority({{ $task->id }}, $event.target.value)"
                            x-data="{ currentPriority: '{{ $task->priority->value }}' }"
                            x-model="currentPriority"
                            class="text-xs font-semibold rounded-md border px-2 py-1 outline-none focus:ring-1 focus:ring-blue-500 transition-colors"
                            :class="{
                                'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800': currentPriority === 'low',
                                'border-yellow-300 text-yellow-800 bg-yellow-50 dark:bg-yellow-950/40 dark:text-yellow-300': currentPriority === 'Medium',
                                'border-orange-300 text-orange-800 bg-orange-50 dark:orange-950/40 dark:text-orange-300': currentPriority === 'High',
                                'border-red-300 text-red-700 bg-red-50 dark:bg-red-950/40 dark:text-red-300 font-bold': currentPriority === 'Urgent'
                            }">
                        <option value="low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                @else
                    <span class="font-bold text-xs {{ $task->priority->value === 'Urgent' ? 'text-red-600 dark:text-red-400' : ($task->priority->value === 'High' ? 'text-orange-600' : ($task->priority->value === 'Medium' ? 'text-yellow-600' : 'text-slate-600 dark:text-slate-400')) }}">
                        {{ $task->priority->value }}
                    </span>
                @endif
            </div>

            {{-- Team Tag --}}
            <div id="mobile-team-cell-{{ $task->id }}">
                @if($teamRelation)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60">
                        👥 {{ $teamRelation->name }}
                    </span>
                @elseif(!$isHistory && $technicians->count() === 1)
                    <button type="button"
                            @click="openTeamSelectionModal({{ $task->id }}, '{{ $task->task_number }}', '{{ addslashes($task->tugas) }}', '{{ $task->task_date?->format('Y-m-d') }}')"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold">
                        + Masukkan Tim
                    </button>
                @elseif(!$isHistory)
                    @php
                        $taskDate = $task->task_date?->toDateString();
                        $techIds = $technicians->pluck('id')->all();
                        $candidates = \App\Models\FopTaskTeam::whereDate('work_date', $taskDate)
                            ->whereHas('members', fn($q) => $q->whereIn('users.id', $techIds))
                            ->get()
                            ->map(fn($t) => ['team_id' => $t->id, 'team_name' => $t->name])
                            ->all();
                    @endphp
                    @if(count($candidates) >= 2)
                        <button type="button"
                                @click="triggerConflictModal({{ $task->id }}, '{{ $task->task_number }}', {{ json_encode($candidates) }})"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-red-50 text-red-700 border border-red-200 animate-pulse">
                            ⚠️ Konflik Tim
                        </button>
                    @else
                        <span class="text-slate-400 text-xs">—</span>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- ══ 6. Teknisi Bertugas ══ --}}
    <div class="space-y-1.5" id="mobile-tech-cell-{{ $task->id }}">
        <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Teknisi Bertugas</span>
        <div class="flex flex-wrap gap-1.5 items-center">
            @forelse($technicians as $tech)
                @if(!$isHistory)
                    <button type="button"
                            @click="openSwitchModal({{ $task->id }}, '{{ $task->task_number }}', @js($task->tugas), '{{ $task->task_date?->toDateString() }}', {{ $tech->id }}, @js($tech->name))"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors shadow-2xs cursor-pointer"
                            title="{{ $tech->name }} — tap buat switch teknisi">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        <span>{{ $tech->name }}</span>
                        <svg class="w-3 h-3 text-blue-500 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    </button>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        <span>{{ $tech->name }}</span>
                    </span>
                @endif
            @empty
                <span class="text-slate-400 dark:text-slate-500 text-xs italic">Belum ada teknisi yang di-assign</span>
            @endforelse
        </div>
    </div>

    {{-- ══ 7. Quick Touch Actions (Thumb-Zone Ergonomic Footer) ══ --}}
    <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2 flex-wrap">
        {{-- Direct Contact & Navigation Shortcuts --}}
        <div class="flex items-center gap-1.5">
            @if($customerPhone)
                <a href="tel:{{ $customerPhone }}"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 hover:bg-emerald-100 transition-colors shadow-2xs"
                   title="Telepon: {{ $customerPhone }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                </a>
                @php
                    $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
                    if (str_starts_with($cleanPhone, '0')) {
                        $cleanPhone = '62' . substr($cleanPhone, 1);
                    }
                @endphp
                <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-green-50 dark:bg-green-950/40 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800/60 hover:bg-green-100 transition-colors shadow-2xs"
                   title="WhatsApp: {{ $customerPhone }}">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824z"/>
                    </svg>
                </a>
            @endif

            @if($customerLat && $customerLng)
                <a href="https://www.google.com/maps/search/?api=1&query={{ $customerLat }},{{ $customerLng }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60 hover:bg-sky-100 transition-colors shadow-2xs"
                   title="Buka Lokasi Google Maps">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </a>
            @elseif($customerAddress || $villageRelation)
                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($customerAddress ?? '').' '.($villageRelation?->name ?? '')) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600 hover:bg-slate-200 transition-colors shadow-2xs"
                   title="Cari Alamat di Peta">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    </svg>
                </a>
            @endif
        </div>

        {{-- Main Task Action Buttons --}}
        <div class="flex items-center gap-1.5 ml-auto">
            @if(!$isHistory)
                @can('fop_tasks.cancel')
                    @if(!in_array($statusValue, ['selesai', 'dibatalkan']) && !in_array($task->category->value, ['SURVEY', 'PSB']))
                        <button type="button"
                                @click="openCancelModal({{ $task->id }}, '{{ $task->task_number }}')"
                                class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-400 border border-red-200 dark:border-red-900/50 hover:bg-red-100 transition-colors cursor-pointer">
                            Cancel
                        </button>
                    @endif
                @endcan
            @endif

            <a href="{{ route('fop-tasks.history.show', $task->id) }}"
               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700/70 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-200 transition-colors shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                <span>Detail</span>
            </a>

            @if(!$isHistory)
                <button type="button"
                        @click="openEditModal({{ json_encode($task) }}, {{ json_encode($technicians->pluck('id')) }}, '{{ route('fop-tasks.update', $task->id) }}')"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60 hover:bg-sky-100 transition-colors shadow-2xs cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    <span>Edit</span>
                </button>

                @if($canDeleteTask)
                    <form action="{{ route('fop-tasks.destroy', $task->id) }}" method="POST" data-confirm="Apakah Anda yakin ingin menghapus Task FOP ini?" class="inline-block">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-1.5 rounded-lg text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 hover:bg-red-100 transition-colors shadow-2xs cursor-pointer" title="Hapus">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</div>

