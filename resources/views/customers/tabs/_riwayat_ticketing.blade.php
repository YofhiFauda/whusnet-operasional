<div class="space-y-6">
    <!-- Header Summary -->
    <div class="border border-slate-200 rounded-xl p-5 bg-gradient-to-r from-slate-50 via-white to-slate-50 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center font-bold text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800">Riwayat Ticketing & Tugas Operasional</h3>
            </div>
            <p class="text-xs text-slate-500 mt-1.5">
                Daftar seluruh tiket/tugas yang diturunkan untuk pelanggan ini. <strong>Tekan pada baris tiket</strong> untuk melihat riwayat lengkap progres dan penanganan tiket tersebut.
            </p>
        </div>

        @php
            $totalTickets = $customerTasks->count() + $customerFopTasks->count();
            $completedTickets = $customerTasks->filter(fn($t) => ($t->status->value ?? $t->status) === 'selesai')->count() +
                                $customerFopTasks->filter(fn($t) => ($t->status->value ?? $t->status) === 'selesai')->count();
        @endphp

        <div class="flex items-center gap-3 shrink-0">
            <div class="bg-white border border-slate-200 rounded-lg px-3.5 py-2 text-center">
                <span class="block text-[10px] font-bold text-slate-400 uppercase">Total Tiket</span>
                <span class="text-base font-extrabold text-slate-800">{{ $totalTickets }}</span>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-3.5 py-2 text-center">
                <span class="block text-[10px] font-bold text-emerald-600 uppercase">Selesai</span>
                <span class="text-base font-extrabold text-emerald-800">{{ $completedTickets }}</span>
            </div>
        </div>
    </div>

    @if($totalTickets === 0)
        <div class="py-12 text-center text-slate-400 border border-slate-200 rounded-xl bg-slate-50/50">
            <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
            </svg>
            <h4 class="text-sm font-semibold text-slate-700">Belum Ada Tiket yang Diturunkan</h4>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                Pelanggan ini belum memiliki riwayat tugas lapangan (Survey, Pemasangan, Maintenance, maupun Request).
            </p>
        </div>
    @else
        <div class="space-y-4">
            <!-- Central Tasks (Sprint 8) -->
            @foreach($customerTasks as $task)
                @php
                    $uniqueId = 'task_' . $task->id;
                    $typeLabel = $task->task_type instanceof \App\Enums\TaskType ? $task->task_type->label() : ($task->task_type ?? 'Tugas');
                    $typeBadge = $task->task_type instanceof \App\Enums\TaskType ? $task->task_type->badgeClasses() : 'bg-blue-50 text-blue-700 border-blue-200';
                    $statusLabel = $task->status instanceof \App\Enums\TaskStatus ? $task->status->label() : ($task->status ?? 'Pending');
                    $statusBadge = $task->status instanceof \App\Enums\TaskStatus ? $task->status->badgeClasses() : 'bg-slate-100 text-slate-700 border-slate-200';
                    
                    $assignedTeam = $task->teamMembers->map(function($m) {
                        return $m->user ? $m->user->name : null;
                    })->filter()->implode(', ');
                    if(empty($assignedTeam)) $assignedTeam = 'Belum ditugaskan';
                @endphp

                <div class="border border-slate-200 rounded-xl bg-white shadow-sm overflow-hidden transition-all hover:border-slate-300">
                    <!-- Ticket Header / Clickable Bar -->
                    <div onclick="toggleTicketHistory('{{ $uniqueId }}')" class="p-4 bg-white hover:bg-slate-50/75 transition-colors cursor-pointer flex flex-col md:flex-row md:items-center justify-between gap-3 select-none">
                        <div class="flex items-start md:items-center gap-3.5">
                            <!-- Icon & Number -->
                            <div class="w-10 h-10 rounded-lg bg-slate-100 border border-slate-200 flex flex-col items-center justify-center shrink-0 text-slate-700 font-mono text-[11px] font-bold">
                                <svg class="w-4 h-4 mb-0.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                            </div>

                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-xs text-indigo-700">{{ $task->task_number }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $typeBadge }}">{{ strtoupper($typeLabel) }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $statusBadge }}">{{ $statusLabel }}</span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-800 mt-1">{{ $task->title }}</h4>
                                @if($task->description)
                                    <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ $task->description }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between md:justify-end gap-6 border-t md:border-t-0 pt-3 md:pt-0 border-slate-100 text-xs">
                            <div class="text-right">
                                <span class="block text-[10px] text-slate-400 font-medium">Petugas Lapangan</span>
                                <span class="font-semibold text-slate-700">{{ $assignedTeam }}</span>
                                <span class="block text-[10px] text-slate-400 mt-0.5">
                                    {{ $task->created_at ? \Carbon\Carbon::parse($task->created_at)->translatedFormat('d M Y') : '-' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 text-indigo-600 font-bold text-xs shrink-0 bg-indigo-50 px-3 py-2 rounded-lg group hover:bg-indigo-100 transition-colors">
                                <span>Lihat Riwayat</span>
                                <svg id="arrow_{{ $uniqueId }}" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Expandable Detail & Timeline -->
                    <div id="{{ $uniqueId }}" class="hidden border-t border-slate-200 bg-slate-50/50 p-5 space-y-6">
                        <!-- Task Metadata Panel -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-white p-4 rounded-xl border border-slate-200 text-xs">
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase">Dibuat Oleh</span>
                                <span class="font-bold text-slate-800">{{ $task->creator ? $task->creator->name : 'System' }}</span>
                                <span class="block text-[10px] text-slate-500">{{ $task->created_at ? \Carbon\Carbon::parse($task->created_at)->translatedFormat('d M Y, H:i') : '-' }} WIB</span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase">Jadwal / Terjadwal</span>
                                <span class="font-bold text-slate-800">{{ $task->scheduled_at ? \Carbon\Carbon::parse($task->scheduled_at)->translatedFormat('d M Y, H:i') : 'Belum Dijadwalkan' }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase">FOP Reviewer</span>
                                <span class="font-bold text-slate-800">{{ $task->fop ? $task->fop->name : '-' }}</span>
                                <span class="block text-[10px] text-slate-500">Status: {{ strtoupper($task->fop_review_status ?? 'PENDING') }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase">Target SLA</span>
                                <span class="font-bold text-slate-800">{{ $task->sla_minutes ? $task->sla_minutes . ' Menit' : '-' }}</span>
                            </div>
                        </div>

                        <!-- Checklists & Evidences -->
                        @if($task->checklists->count() > 0 || $task->evidences->count() > 0)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @if($task->checklists->count() > 0)
                                    <div class="bg-white p-4 rounded-xl border border-slate-200">
                                        <h5 class="text-xs font-bold text-slate-800 mb-2.5 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Checklist Pekerjaan
                                        </h5>
                                        <ul class="space-y-2 text-xs">
                                            @foreach($task->checklists as $chk)
                                                <li class="flex items-start gap-2">
                                                    <span class="mt-0.5 {{ $chk->is_checked ? 'text-emerald-600' : 'text-slate-300' }}">
                                                        @if($chk->is_checked)
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                                        @else
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd" /></svg>
                                                        @endif
                                                    </span>
                                                    <span class="{{ $chk->is_checked ? 'text-slate-800 font-medium' : 'text-slate-500' }}">
                                                        {{ $chk->item_name }}
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if($task->evidences->count() > 0)
                                    <div class="bg-white p-4 rounded-xl border border-slate-200">
                                        <h5 class="text-xs font-bold text-slate-800 mb-2.5 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /></svg>
                                            Bukti Foto Pekerjaan (Evidence)
                                        </h5>
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach($task->evidences as $ev)
                                                <a href="{{ Storage::url($ev->file_path) }}" target="_blank" class="block aspect-square rounded-lg overflow-hidden border border-slate-200 bg-slate-100 hover:opacity-90">
                                                    <img src="{{ Storage::url($ev->file_path) }}" alt="Evidence" class="w-full h-full object-cover">
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Step-by-Step Ticket Audit History -->
                        <div class="bg-white p-5 rounded-xl border border-slate-200">
                            <h5 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Riwayat Log Penanganan Tiket Ini
                            </h5>

                            @if($task->auditLogs->count() > 0)
                                <div class="relative pl-5 border-l-2 border-indigo-100 space-y-4 ml-1">
                                    @foreach($task->auditLogs as $alog)
                                        <div class="relative">
                                            <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-indigo-600 border-2 border-white shadow-sm"></div>
                                            <div class="text-xs">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-bold text-slate-800">
                                                        {{ match($alog->action) {
                                                            'create' => 'Tiket Dibuat',
                                                            'update' => 'Pembaruan Progres / Status Tiket',
                                                            'delete' => 'Tiket Dihapus',
                                                            default => ucfirst($alog->action)
                                                        } }}
                                                    </span>
                                                    <span class="text-[11px] text-slate-400 font-mono">
                                                        {{ $alog->created_at ? \Carbon\Carbon::parse($alog->created_at)->translatedFormat('d M Y, H:i:s') : '-' }}
                                                    </span>
                                                </div>
                                                <div class="text-slate-500 mt-0.5 flex items-center gap-1.5">
                                                    <span>Oleh:</span>
                                                    <span class="font-semibold text-slate-700">{{ $alog->user ? $alog->user->name : 'System' }}</span>
                                                </div>
                                                @if(!empty($alog->new_values))
                                                    <div class="mt-1.5 p-2 rounded bg-slate-50 border border-slate-100 font-mono text-[11px] text-slate-600">
                                                        @foreach($alog->new_values as $k => $v)
                                                            @if(is_scalar($v))
                                                                <div><strong class="text-slate-700">{{ ucwords(str_replace('_', ' ', $k)) }}:</strong> {{ $v }}</div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <!-- Fallback Milestones if no individual auditLogs exist yet -->
                                <div class="relative pl-5 border-l-2 border-slate-200 space-y-4 ml-1">
                                    @if($task->created_at)
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-indigo-600 border-2 border-white"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-800">Tiket Diterbitkan</span>
                                            <p class="text-slate-500">Dibuat oleh {{ $task->creator ? $task->creator->name : 'System' }} pada {{ \Carbon\Carbon::parse($task->created_at)->translatedFormat('d M Y, H:i') }} WIB</p>
                                        </div>
                                    </div>
                                    @endif

                                    @if($task->scheduled_at)
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-blue-500 border-2 border-white"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-800">Dijadwalkan untuk Pengerjaan</span>
                                            <p class="text-slate-500">Jadwal: {{ \Carbon\Carbon::parse($task->scheduled_at)->translatedFormat('d M Y, H:i') }} WIB</p>
                                        </div>
                                    </div>
                                    @endif

                                    @if($task->started_at)
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-amber-500 border-2 border-white"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-800">Pengerjaan Dimulai (In Progress)</span>
                                            <p class="text-slate-500">Mulai dikerjakan pada {{ \Carbon\Carbon::parse($task->started_at)->translatedFormat('d M Y, H:i') }} WIB</p>
                                        </div>
                                    </div>
                                    @endif

                                    @if($task->completed_at)
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-emerald-600 border-2 border-white"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-emerald-800">Tiket Selesai Dikerjakan</span>
                                            <p class="text-slate-500">Selesai pada {{ \Carbon\Carbon::parse($task->completed_at)->translatedFormat('d M Y, H:i') }} WIB</p>
                                        </div>
                                    </div>
                                    @endif

                                    @if($task->cancelled_at)
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-rose-600 border-2 border-white"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-rose-800">Tiket Dibatalkan</span>
                                            <p class="text-slate-500">Dibatalkan pada {{ \Carbon\Carbon::parse($task->cancelled_at)->translatedFormat('d M Y, H:i') }} WIB. Alasan: {{ $task->cancel_reason ?? '-' }}</p>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <!-- FOP Tasks / Legacy Field Tasks -->
            @foreach($customerFopTasks as $ftask)
                @php
                    $uniqueId = 'foptask_' . $ftask->id;
                    $statusStr = $ftask->status instanceof \App\Enums\FopTaskStatus ? $ftask->status->value : ($ftask->status ?? 'pending');
                    
                    $statusBadge = match($statusStr) {
                        'completed', 'selesai' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'in_progress', 'proses' => 'bg-amber-50 text-amber-700 border-amber-200',
                        'cancelled', 'batal' => 'bg-rose-50 text-rose-700 border-rose-200',
                        default => 'bg-slate-100 text-slate-700 border-slate-200'
                    };

                    $assignedTeam = $ftask->technicians->map(fn($t) => $t->name)->implode(', ');
                    if(empty($assignedTeam)) $assignedTeam = 'Belum ditugaskan';
                @endphp

                <div class="border border-slate-200 rounded-xl bg-white shadow-sm overflow-hidden transition-all hover:border-slate-300">
                    <div onclick="toggleTicketHistory('{{ $uniqueId }}')" class="p-4 bg-white hover:bg-slate-50/75 transition-colors cursor-pointer flex flex-col md:flex-row md:items-center justify-between gap-3 select-none">
                        <div class="flex items-start md:items-center gap-3.5">
                            <div class="w-10 h-10 rounded-lg bg-teal-50 border border-teal-200 flex flex-col items-center justify-center shrink-0 text-teal-700 font-mono text-[11px] font-bold">
                                <svg class="w-4 h-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-xs text-teal-800">{{ $ftask->task_number ?? ('FOP-TSK-' . $ftask->id) }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-teal-50 text-teal-700 border-teal-200">FOP FIELD TASK</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $statusBadge }}">{{ strtoupper($statusStr) }}</span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-800 mt-1">{{ $ftask->issue ?? $ftask->tugas ?? 'Tugas Lapangan' }}</h4>
                                @if($ftask->notes)
                                    <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ $ftask->notes }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between md:justify-end gap-6 border-t md:border-t-0 pt-3 md:pt-0 border-slate-100 text-xs">
                            <div class="text-right">
                                <span class="block text-[10px] text-slate-400 font-medium">Teknisi Ditugaskan</span>
                                <span class="font-semibold text-slate-700">{{ $assignedTeam }}</span>
                                <span class="block text-[10px] text-slate-400 mt-0.5">
                                    {{ $ftask->task_date ? \Carbon\Carbon::parse($ftask->task_date)->translatedFormat('d M Y') : '-' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 text-teal-600 font-bold text-xs shrink-0 bg-teal-50 px-3 py-2 rounded-lg group hover:bg-teal-100 transition-colors">
                                <span>Lihat Riwayat</span>
                                <svg id="arrow_{{ $uniqueId }}" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div id="{{ $uniqueId }}" class="hidden border-t border-slate-200 bg-slate-50/50 p-5 space-y-4">
                        <div class="bg-white p-5 rounded-xl border border-slate-200">
                            <h5 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                                <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Riwayat Pengerjaan Tugas Lapangan
                            </h5>
                            <div class="relative pl-5 border-l-2 border-teal-100 space-y-4 ml-1">
                                <div class="relative">
                                    <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-teal-600 border-2 border-white"></div>
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800">Tugas Lapangan Diturunkan</span>
                                        <p class="text-slate-500">Tanggal Tugas: {{ $ftask->task_date ? \Carbon\Carbon::parse($ftask->task_date)->translatedFormat('d M Y, H:i') : '-' }} WIB</p>
                                        @if($ftask->notes)
                                            <p class="mt-1 p-2 rounded bg-slate-50 border border-slate-100 font-mono text-[11px] text-slate-600">{{ $ftask->notes }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
    function toggleTicketHistory(id) {
        const el = document.getElementById(id);
        const arrow = document.getElementById('arrow_' + id);
        if (el) {
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                if (arrow) arrow.classList.add('rotate-180');
            } else {
                el.classList.add('hidden');
                if (arrow) arrow.classList.remove('rotate-180');
            }
        }
    }
</script>
